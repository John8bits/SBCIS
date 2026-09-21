<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

require dirname(__DIR__) . '/config/bootstrap.php';

$database = App\Database\Connection::get();
$schema = (string)$database->query('SELECT DATABASE()')->fetchColumn();
$checks = 0;
$expect = static function (bool $condition, string $message) use (&$checks): void {
    if (!$condition) throw new RuntimeException($message);
    $checks++;
};
$column = $database->prepare('SELECT COUNT(*) FROM information_schema.columns
    WHERE table_schema=? AND table_name=? AND column_name=?');
foreach ([['boreholes','archived_at'], ['boreholes','archived_by'], ['boreholes','lock_version']] as [$table,$name]) {
    $column->execute([$schema,$table,$name]);
    $expect((int)$column->fetchColumn() === 1, "Missing {$table}.{$name} migration.");
}
$table = $database->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=? AND table_name=?');
foreach (['audit_log','login_attempts','system_revisions'] as $name) {
    $table->execute([$schema,$name]);
    $expect((int)$table->fetchColumn() === 1, "Missing {$name} table migration.");
}
$constraint = $database->prepare('SELECT COUNT(*) FROM information_schema.table_constraints
    WHERE constraint_schema=? AND table_name=? AND constraint_name=?');
foreach ([['boreholes','fk_borehole_barangay_municipality'], ['boreholes','fk_borehole_archived_by'], ['audit_log','fk_audit_admin']] as [$tableName,$name]) {
    $constraint->execute([$schema,$tableName,$name]);
    $expect((int)$constraint->fetchColumn() === 1, "Missing {$name} constraint.");
}

$apache = file_get_contents(dirname(__DIR__) . '/.htaccess');
$iis = file_get_contents(dirname(__DIR__) . '/web.config');
foreach (['tools','database','config','docs'] as $segment) {
    $expect(str_contains($apache, $segment), "Apache rules do not protect {$segment}.");
    $expect(str_contains($iis, $segment), "IIS rules do not protect {$segment}.");
}
$expect(Config\InterpolationConfig::MAX_OBSERVATIONS === 10000, 'Interpolation worker limit is not the benchmarked value.');
$expect(!str_contains(file_get_contents(__DIR__ . '/hash.php'), 'password_hash(\''), 'Hash utility contains a reusable literal password.');

echo $checks . " production-readiness checks passed.\n";
