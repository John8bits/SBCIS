<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__) . '/config/bootstrap.php';

use App\Services\InterpolationPublicationService;
use App\Services\InterpolationResultStore;

$directory = sys_get_temp_dir() . '/sbcis-publication-test-' . bin2hex(random_bytes(8));
$store = new InterpolationResultStore($directory);
$checks = 0;
function expect(bool $value, string $message): void {
    global $checks;
    if (!$value) throw new RuntimeException($message);
    $checks++;
}
$input = ['status'=>'ready', 'version'=>'a', 'generation_enabled'=>true, 'variable'=>'fixture', 'unit'=>'test units',
    'points'=>[['value'=>1], ['value'=>2], ['value'=>3]], 'eligible_count'=>3, 'excluded_count'=>0, 'outside_borehole_count'=>0];
$prepare = static function () use (&$input): array { return $input; };
// Isolated synthetic output tests publication mechanics, not an interpolation algorithm.
$output = ['method'=>'isolated fixture', 'legend'=>['min'=>0,'max'=>10,'unit'=>'test units'],
    'validation'=>['sample_count'=>3,'population_count'=>3,'sampled'=>false,'mae'=>1.0,'rmse'=>1.2,'bias'=>0.1,'exact_location_max_error'=>0.0],
    'surface'=>['type'=>'FeatureCollection','features'=>[['type'=>'Feature','properties'=>['value'=>4],
        'geometry'=>['type'=>'Polygon','coordinates'=>[[
            [125.2161,10.0330],[125.2168,10.0330],[125.2164,10.0337],[125.2161,10.0330]
        ]]]]]]];
$fail = false; $editDuringGeneration = false;
$generate = static function (array $data) use (&$fail, &$editDuringGeneration, &$input, $output): array {
    if ($fail) throw new RuntimeException('Expected isolated generation failure');
    if ($editDuringGeneration) $input['version'] = 'concurrent-edit';
    return $output;
};
$service = new InterpolationPublicationService($prepare, $store, $generate);
try {
    expect($service->result()['result'] === null, 'No publication is not a system error');
    expect(!is_dir($directory), 'Public reads do not create a cache or generate');
    expect($service->status()['status'] === 'needs_regeneration', 'New valid inputs need regeneration');
    expect($service->regenerate()['status'] === 'current', 'Successful publication');
    $original = $store->read()['published'];
    expect($service->result()['result']['source_hash'] === 'a', 'Public serves current source');
    expect(!isset($service->result()['result']['points']), 'Public does not receive input points');
    expect(!isset($service->result()['result']['validation']), 'Public does not receive admin validation diagnostics');
    $input['version'] = 'edited'; $store->markOutdated();
    expect($service->result()['status'] === 'outdated' && $service->result()['result'] === null, 'Old surface hidden after mutation');
    expect($service->status()['published_outdated'], 'Admin sees stale publication');
    $fail = true;
    expect($service->regenerate()['status'] === 'generation_failed', 'Failure distinct from no data');
    expect($store->read()['published'] === $original, 'Failed attempt preserves previous publication');
    $fail = false; $editDuringGeneration = true;
    expect($service->regenerate()['status'] === 'needs_regeneration', 'Concurrent edit prevents stale publication');
    expect($store->read()['published'] === $original, 'Concurrent edit preserves previous publication');
    $editDuringGeneration = false;
    expect($service->regenerate()['status'] === 'current', 'Retry publishes new version');
    expect($store->read()['published']['interpolation_version'] !== $original['interpolation_version'], 'Publication version changes');
    $latest = $store->read()['published'];
    foreach (['no_data','insufficient_data','pending_configuration'] as $state) {
        $input['status'] = $state; $input['version'] = $state;
        expect($service->regenerate()['status'] === $state, 'Normal blocked state: ' . $state);
        expect($store->read()['published'] === $latest, 'Blocked state cannot overwrite publication');
    }
    $input['status'] = 'ready'; $input['generation_enabled'] = false;
    expect($service->regenerate()['status'] === 'pending_configuration', 'Approval gate cannot be bypassed by valid inputs');
    $input['generation_enabled'] = true;
    expect((new InterpolationPublicationService($prepare, $store))->regenerate()['status'] === 'pending_configuration', 'No generator means no fabricated success');
    $badService = new InterpolationPublicationService($prepare, $store, static fn()=>['surface'=>[]]);
    expect($badService->regenerate()['status'] === 'generation_failed', 'Malformed output rejected');
    expect($store->read()['published'] === $latest, 'Malformed output preserves publication');
    $before = $store->read();
    try { $store->update(static function () { throw new RuntimeException('Interrupted write'); }); } catch (RuntimeException $expected) {}
    expect($store->read() === $before, 'Interrupted update leaves complete state');
    $lock = fopen($directory . '/generation.lock', 'c'); flock($lock, LOCK_EX);
    try { $store->markOutdated(); throw new LogicException('Lock ignored'); } catch (RuntimeException $expected) { $checks++; }
    flock($lock, LOCK_UN); fclose($lock);
    echo "$checks publication checks passed; isolated temporary cache only.\n";
} finally {
    foreach (['state.json', 'generation.lock'] as $file) if (is_file($directory . '/' . $file)) unlink($directory . '/' . $file);
    if (is_dir($directory)) rmdir($directory);
}
