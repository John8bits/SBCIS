<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

require dirname(__DIR__) . '/config/bootstrap.php';

$controller = new App\Controllers\BoreholeController();
$source = file_get_contents(dirname(__DIR__) . '/app/Controllers/BoreholeController.php');
if (!str_contains($source, "'private, no-store'") || !str_contains($source, 'AdminSession::isLoggedIn()'))
    throw new RuntimeException('Authenticated map responses are not private or administrator-aware.');
$checks = 0;
$request = static function (array $query, string $method) use ($controller, &$checks): array {
    http_response_code(200);
    ob_start();
    $controller->json($query, $method);
    $body = ob_get_clean();
    $checks++;
    return ['status'=>http_response_code(), 'data'=>json_decode($body, true, 512, JSON_THROW_ON_ERROR)];
};

$response = $request(['bbox'=>'124,9,126,11','limit'=>'2000'], 'GET');
if ($response['status'] !== 200 || !is_array($response['data']['records'] ?? null)) throw new RuntimeException('Valid map window failed.');
$boundary = new App\Services\BoundaryService();
foreach ($response['data']['records'] as $record) {
    if (!$boundary->contains($record['latitude'], $record['longitude'])) throw new RuntimeException('Outside record leaked through public map endpoint.');
    if (!array_key_exists('layers', $record)) throw new RuntimeException('Borehole detail missing from map endpoint.');
}
$bad = $request(['bbox'=>'invalid'], 'GET');
if ($bad['status'] !== 400) throw new RuntimeException('Invalid bounds accepted.');
$method = $request(['bbox'=>'124,9,126,11'], 'POST');
if ($method['status'] !== 405) throw new RuntimeException('Unsupported method accepted.');

echo $checks . " borehole map API checks passed.\n";
