<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__) . '/config/bootstrap.php';

use App\Services\InterpolationGeneratorService;
use App\Services\InterpolationPublicationPolicy;
use App\Services\InterpolationPublicationService;
use App\Services\InterpolationResultStore;
use Config\InterpolationConfig;

$checks = 0;
$check = static function (bool $ok, string $message) use (&$checks): void {
    if (!$ok) throw new RuntimeException($message);
    $checks++;
};
$environment = [];
foreach (['SBCIS_ENV','SBCIS_QA_INTERPOLATION','SBCIS_INTERPOLATION_REVIEWED',
    'SBCIS_INTERPOLATION_MAX_MAE_KPA','SBCIS_INTERPOLATION_MAX_RMSE_KPA','SBCIS_INTERPOLATION_MIN_SPAN_KM'] as $key) {
    $environment[$key] = getenv($key); putenv($key);
}
$directory = sys_get_temp_dir() . '/sbcis-recovery-' . bin2hex(random_bytes(8));
$store = new InterpolationResultStore($directory);
try {
    $generator = new InterpolationGeneratorService();
    $estimate = new ReflectionMethod($generator, 'estimate');
    $points = [['coordinates'=>[125.0,10.2], 'value'=>10.0], ['coordinates'=>[125.000005,10.2], 'value'=>100.0]];
    foreach ([$points, array_reverse($points)] as $order) {
        $check($estimate->invoke($generator,125.000005,10.2,$order)[0] === 100.0, 'Nearby point overrides exact match.');
        $check($estimate->invoke($generator,125.0,10.2,$order)[0] === 10.0, 'Exact reproduction is order-dependent.');
    }
    $centroid = new ReflectionMethod($generator,'centroid');
    $geometry = ['type'=>'MultiPolygon','coordinates'=>[
        [[[0,0],[10,0],[10,10],[0,10],[0,0]]],
        [[[20,0],[21,0],[22,0],[22,1],[21,1],[20,1],[20,0]]],
    ]];
    $center = $centroid->invoke($generator,$geometry);
    $check(abs($center[0] - 542/102) < 1e-9, 'Centroid uses vertex count instead of polygon area.');
    $geometry = ['type'=>'Polygon','coordinates'=>[
        [[0,0],[10,0],[10,10],[0,10],[0,0]], [[1,1],[3,1],[3,3],[1,3],[1,1]],
    ]];
    $check(abs($centroid->invoke($generator,$geometry)[0] - 5.125) < 1e-9, 'Hole area was not subtracted.');

    $output = ['method'=>'isolated fixture', 'legend'=>['min'=>0,'max'=>10,'unit'=>'fixture','classes'=>InterpolationConfig::bearingCapacityClasses()],
        'validation'=>['sample_count'=>3,'population_count'=>3,'sampled'=>false,'mae'=>1.0,'rmse'=>1.2,'bias'=>0.1,'exact_location_max_error'=>0.0,'spatial_span_km'=>10.0],
        'surface'=>['type'=>'FeatureCollection','features'=>[['type'=>'Feature','properties'=>['value'=>4,'GID_3'=>'fixture'],
            'geometry'=>['type'=>'Polygon','coordinates'=>[[[125.2161,10.0330],[125.2168,10.0330],[125.2164,10.0337],[125.2161,10.0330]]]]]]]];
    $policy = new InterpolationPublicationPolicy();
    $check($policy->evaluate([], $output)['status'] === 'approved', 'Valid screening candidate was blocked by missing environment settings.');
    putenv('SBCIS_ENV=test'); putenv('SBCIS_QA_INTERPOLATION=1');
    $check(InterpolationConfig::qaPublicationEnabled(), 'Explicit CLI QA mode unavailable.');
    $check($policy->evaluate([], $output)['mode'] === 'screening', 'Candidate was mislabeled as reviewed.');
    $check($policy->evaluate([], $output)['status'] === 'approved', 'Explicit QA still requires scientific review credentials.');
    $check(str_ends_with(InterpolationConfig::cacheDirectory(), '/qa'), 'QA cache is not separate.');
    putenv('SBCIS_ENV=production');
    $check(!InterpolationConfig::qaPublicationEnabled(), 'QA allowed in production.');
    putenv('SBCIS_ENV=test');
    $bad = $output; $bad['validation']['exact_location_max_error'] = 1;
    $check($policy->evaluate([], $bad)['status'] === 'validation_failed', 'QA bypassed failed exact reproduction.');

    $revision = 7; $preparations = 0; $changeDuringGeneration = false;
    $prepare = static function () use (&$revision,&$preparations): array {
        $preparations++;
        return ['status'=>'ready','version'=>'source-'.$revision,'source_revision'=>$revision,'generation_enabled'=>true,
            'variable'=>'fixture','unit'=>'fixture','points'=>[['value'=>1],['value'=>2],['value'=>3]],
            'eligible_count'=>3,'excluded_count'=>0,'exclusion_reasons'=>new stdClass()];
    };
    $generate = static function () use ($output,&$revision,&$changeDuringGeneration): array {
        if ($changeDuringGeneration) $revision++;
        return $output;
    };
    $service = new InterpolationPublicationService($prepare,$store,$generate,
        static function () use (&$revision): int { return $revision; },
        static fn(array $input,array $result): array => (new InterpolationPublicationPolicy())->evaluate($input,$result));
    // A dead worker's metadata must not block a fresh OS lease.
    $store->beginGeneration('abandoned',7);
    $check($service->status()['status'] === 'generation_interrupted', 'Abandoned job falsely active.');
    $preparations = 0;
    $check($service->regenerate()['status'] === 'current', 'Abandoned worker could not recover.');
    $check($preparations === 1, 'One generation rebuilt the full input more than once.');
    $service->status(); $service->status();
    $check($preparations === 1, 'Cached status polling rebuilds the source.');
    $check($service->result()['result']['publication_mode'] === 'qa', 'Public result missing QA provenance.');
    $store->withGenerationLock(static function () use ($store,$check): void {
        $check($store->generationRunning(), 'Held worker lease not detected.');
        try { $store->withGenerationLock(static fn()=>null); throw new LogicException('Concurrent lease accepted.'); }
        catch (RuntimeException $error) { $check($error->getMessage() === 'Another interpolation update is running.', 'Wrong busy outcome.'); }
    });
    $check(!$store->generationRunning(), 'Worker lease not released.');
    putenv('SBCIS_ENV=development'); putenv('SBCIS_QA_INTERPOLATION');
    $check($service->result()['result'] === null, 'QA result was reused by the normal public path.');
    $check($service->regenerate()['status'] === 'current', 'Normal publication still requires environment review settings.');
    $check($service->result()['result']['publication_mode'] === 'screening', 'Normal surface lacks screening provenance.');
    $changeDuringGeneration = true;
    $check($service->regenerate()['status'] === 'needs_regeneration', 'Concurrent source edit was published.');
    $check($service->result()['result'] === null, 'Concurrent source artifact served.');
    echo "$checks recovery/policy/exact-point/centroid checks passed; no database writes.\n";
} finally {
    foreach (['state.json','generation.lock','worker.lock'] as $file) if (is_file($directory.'/'.$file)) unlink($directory.'/'.$file);
    if (is_dir($directory)) rmdir($directory);
    foreach ($environment as $key=>$value) putenv($value === false ? $key : $key.'='.$value);
}
