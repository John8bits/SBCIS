<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

// Reuse the existing connection-local temporary schema and its regression suite.
require __DIR__ . '/test-record-entry.php';
$directory = sys_get_temp_dir() . '/sbcis-crud-test-' . bin2hex(random_bytes(8));
$previousCache = getenv('SBCIS_INTERPOLATION_CACHE_DIR');
putenv('SBCIS_INTERPOLATION_CACHE_DIR=' . $directory);
$store = new App\Services\InterpolationResultStore();
$repository = new App\Models\GeotechnicalRepository($db);
$inputs = new App\Models\InterpolationRepository($db);
$service = new App\Services\InterpolationDataService(new App\Services\BoundaryService(), new Config\InterpolationConfig());
$version = static fn() => $service->build($inputs->snapshot(), [])['version'];
$checks = 0;
function expectInvalidated(string $before): void {
    global $store, $version, $checks;
    if (!$store->read()['outdated'] || $before === $version()) throw new RuntimeException('CRUD did not invalidate source/publication');
    $checks++;
    $store->update(static function (array $state): array { $state['outdated'] = false; return $state; });
}
try {
    $before = $version(); $id = $repository->create($input); expectInvalidated($before);
    $before = $version(); $input['bearing_capacity_kpa'][0] = '23'; $repository->update($id,$input); expectInvalidated($before);
    $before = $version(); $input['longitude'] = '124.85'; $repository->update($id,$input); expectInvalidated($before);
    $before = $version(); $invalid = $input; $invalid['latitude'] = 'invalid';
    try { $repository->update($id,$invalid); throw new LogicException('Invalid save accepted'); } catch (InvalidArgumentException $expected) {}
    if ($store->read()['outdated'] || $version() !== $before) throw new RuntimeException('Failed save invalidated data');
    $checks++;
    $before = $version(); $repository->delete($id); expectInvalidated($before);
    if ($repository->delete($id) || $store->read()['outdated']) throw new RuntimeException('Missing delete invalidated data');
    $checks++;
    echo "$checks committed CRUD/source-version/invalidation checks passed (temporary tables/cache).\n";
} finally {
    foreach (['state.json','generation.lock'] as $file) if (is_file($directory.'/'.$file)) unlink($directory.'/'.$file);
    if (is_dir($directory)) rmdir($directory);
    putenv($previousCache === false ? 'SBCIS_INTERPOLATION_CACHE_DIR' : 'SBCIS_INTERPOLATION_CACHE_DIR=' . $previousCache);
}
