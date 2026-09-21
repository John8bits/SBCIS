<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__) . '/config/bootstrap.php';

$db = App\Database\Connection::get();
$repository = new App\Models\InterpolationRepository($db);
$before = $repository->revision();
$db->beginTransaction();
$statement = $db->prepare('INSERT INTO boreholes
    (borehole_code, borehole_depth_m, latitude, longitude)
    VALUES (?, 1, 10.2, 125.1)');
$statement->execute(['REVISION-TEST-' . bin2hex(random_bytes(5))]);
$id = (int) $db->lastInsertId();
if ($repository->revision() !== $before + 1) throw new RuntimeException('Insert trigger did not advance revision.');
$statement = $db->prepare('UPDATE boreholes SET lock_version=lock_version WHERE borehole_id=?');
$statement->execute([$id]);
if ($repository->revision() !== $before + 2) throw new RuntimeException('Update trigger did not advance revision.');
$db->rollBack();
if ($repository->revision() !== $before) throw new RuntimeException('Revision did not roll back atomically.');

$directory = sys_get_temp_dir() . '/sbcis-revision-test-' . bin2hex(random_bytes(8));
$store = new App\Services\InterpolationResultStore($directory);
$store->update(static fn(array $state): array => [
    'published'=>['source_hash'=>'fixture','source_revision'=>$before,'policy_version'=>Config\InterpolationConfig::PUBLICATION_POLICY_VERSION,'validation'=>['private'=>true]],
    'attempt'=>null,'outdated'=>false,
]);
$service = new App\Services\InterpolationPublicationService(
    static fn(): array => throw new RuntimeException('Full source preparation must not run for public cache reads.'),
    $store,
    null,
    static fn(): int => $before
);
try {
    $result = $service->result();
    if ($result['status'] !== 'current' || isset($result['result']['validation']))
        throw new RuntimeException('Constant-time public result validation failed.');
    echo "4 interpolation revision/cache checks passed.\n";
} finally {
    foreach (['state.json','generation.lock'] as $file) if (is_file($directory . '/' . $file)) unlink($directory . '/' . $file);
    if (is_dir($directory)) rmdir($directory);
}
