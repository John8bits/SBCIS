<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require dirname(__DIR__) . '/config/bootstrap.php';
$db = App\Database\Connection::get();
$db->exec("CREATE TEMPORARY TABLE admins (
    admin_id INT UNSIGNED PRIMARY KEY, email VARCHAR(255) NOT NULL,
    password VARCHAR(255) NOT NULL, role ENUM('admin','super_admin') NOT NULL
) ENGINE=InnoDB");
$hash = password_hash('isolated-admin-map-test', PASSWORD_DEFAULT);
$db->prepare('INSERT INTO admins (admin_id,email,password,role) VALUES (1,?,?,?)')
    ->execute(['fixture@example.test',$hash,'admin']);
$directory = sys_get_temp_dir() . '/sbcis-admin-ui-' . bin2hex(random_bytes(6));
mkdir($directory, 0700);
session_save_path($directory);
App\Support\AdminSession::establish([
    'admin_id'=>1, 'email'=>'fixture@example.test', 'password'=>$hash, 'role'=>'admin'
]);

ob_start();
require dirname(__DIR__) . '/views/admin/admin_gis.php';
$html = (string) ob_get_clean();

$checks = [
    'fullscreen control' => 'id="toggleFullscreen"',
    'location panel' => 'id="gisExplorer"',
    'map data panel' => 'id="gisInsights"',
    'accessible area dialog' => 'aria-modal="true"',
    'capacity readout' => 'id="gisCapacityCard"',
    'live interpolation mode' => 'data-mode="admin"',
];

foreach ($checks as $label => $needle) {
    if (!str_contains($html, $needle)) {
        throw new RuntimeException('Missing ' . $label . '.');
    }
}
if (str_contains($html, 'preview=synthetic') || str_contains($html, 'SBCIS_INTERPOLATION_PREVIEW')) {
    throw new RuntimeException('Synthetic preview controls are still present.');
}

echo count($checks) + 1 . " admin GIS render checks passed.\n";
session_write_close();
foreach (glob($directory . '/sess_*') ?: [] as $file) unlink($file);
rmdir($directory);
