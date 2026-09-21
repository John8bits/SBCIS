<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__) . '/config/bootstrap.php';

$authDb = App\Database\Connection::get();
$authDb->exec("CREATE TEMPORARY TABLE admins (
    admin_id INT UNSIGNED PRIMARY KEY, email VARCHAR(255) NOT NULL,
    password VARCHAR(255) NOT NULL, role ENUM('admin','super_admin') NOT NULL
) ENGINE=InnoDB");
$fixturePasswordHash = password_hash('isolated-controller-test', PASSWORD_DEFAULT);
$authDb->prepare('INSERT INTO admins (admin_id,email,password,role) VALUES (1,?,?,?)')
    ->execute(['fixture@example.test',$fixturePasswordHash,'admin']);

// Authentication fixtures exist only inside this CLI process, never in an HTTP route.
$directory = sys_get_temp_dir() . '/sbcis-admin-test-' . bin2hex(random_bytes(8));
mkdir($directory, 0700);
$previousCache = getenv('SBCIS_INTERPOLATION_CACHE_DIR');
putenv('SBCIS_INTERPOLATION_CACHE_DIR=' . $directory . '/cache');
session_save_path($directory);
session_id('sbcistest' . bin2hex(random_bytes(12)));
$sessionFile = $directory . '/sess_' . session_id();
$controller = new App\Controllers\InterpolationController();
$checks = 0;
function requestCase(array $query, string $method, bool $loggedIn, string $token, int $expectedCode): array {
    global $controller, $checks, $fixturePasswordHash;
    App\Support\AdminSession::start();
    $_SESSION = ['admin_logged_in'=>$loggedIn, 'interpolation_csrf'=>'test-token'];
    if ($loggedIn) $_SESSION += [
        'admin_id'=>1, 'admin_email'=>'fixture@example.test', 'admin_role'=>'admin',
        'admin_auth_fingerprint'=>hash('sha256',$fixturePasswordHash),
        'admin_started_at'=>time(), 'admin_last_seen_at'=>time(),
    ];
    session_write_close();
    $_SERVER['HTTP_X_CSRF_TOKEN'] = $token;
    $_POST = [];
    ob_start();
    $controller->json($query, $method);
    $json = ob_get_clean();
    if (http_response_code() !== $expectedCode) throw new RuntimeException('Unexpected HTTP code: ' . $json);
    $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
    $checks++;
    return $data;
}
try {
    foreach (['status','measurements','regenerate'] as $action) requestCase(['action'=>$action], $action === 'regenerate' ? 'POST' : 'GET', false, '', 401);
    requestCase(['action'=>'regenerate'], 'POST', true, 'wrong', 403);
    requestCase(['action'=>'regenerate'], 'GET', true, 'test-token', 405);
    requestCase(['action'=>'status','variable'=>'spt_n_value'], 'GET', true, '', 400);
    $measurements = requestCase(['action'=>'measurements'], 'GET', true, '', 200);
    $status = requestCase(['action'=>'status'], 'GET', true, '', 200);
    if ($measurements['version'] !== $status['source_hash']) throw new RuntimeException('Admin status has different source snapshot');
    $result = requestCase(['action'=>'regenerate'], 'POST', true, 'test-token', 200);
    $public = requestCase(['action'=>'result'], 'GET', false, '', 200);
    if ($measurements['status'] === 'ready') {
        if ($result['status'] !== 'current' || !$result['generation_enabled']) {
            throw new RuntimeException('Approved generation did not publish');
        }
        if (($public['status'] ?? null) !== 'current' || empty($public['result']['surface_values'])) {
            throw new RuntimeException('Current public surface unavailable');
        }
    } elseif (!in_array($result['status'], ['no_data','insufficient_data'], true) || $public['result'] !== null) {
        throw new RuntimeException('Sparse-data publication guard failed');
    }
    if (isset($public['eligible_count'])) throw new RuntimeException('Public diagnostics leaked');
    $checks += 3;
    if (session_status() === PHP_SESSION_ACTIVE) session_write_close();
    echo "$checks admin authorization/CSRF/measurement/status/regeneration checks passed (isolated CLI session/cache).\n";
} finally {
    if (session_status() === PHP_SESSION_ACTIVE) session_write_close();
    foreach (glob($directory . '/sess_*') ?: [] as $file) if (is_file($file)) unlink($file);
    foreach ([$sessionFile,$directory.'/cache/state.json',$directory.'/cache/generation.lock'] as $file) if (is_file($file)) unlink($file);
    if (is_dir($directory.'/cache')) rmdir($directory.'/cache');
    rmdir($directory);
    putenv($previousCache === false ? 'SBCIS_INTERPOLATION_CACHE_DIR' : 'SBCIS_INTERPOLATION_CACHE_DIR=' . $previousCache);
}
