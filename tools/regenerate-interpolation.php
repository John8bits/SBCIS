<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__) . '/config/bootstrap.php';

try {
    $status = (new App\Controllers\InterpolationController())->publication()->regenerate();
    echo json_encode($status, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . PHP_EOL;
    exit($status['status'] === 'generation_failed' ? 1 : 0);
} catch (Throwable $error) {
    error_log('Interpolation job: ' . $error->getMessage());
    fwrite(STDERR, "Interpolation job failed; see the PHP error log.\n");
    exit(1);
}
