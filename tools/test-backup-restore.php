<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

require_once dirname(__DIR__) . '/app/Models/data_export.php';

$database = sbcis_get_database();
$sourceSchema = (string)$database->query('SELECT DATABASE()')->fetchColumn();
$testSchema = 'sbcis_restore_' . bin2hex(random_bytes(6));
if (!preg_match('/^sbcis_restore_[a-f0-9]{12}$/D', $testSchema)) throw new RuntimeException('Unsafe restore test database name.');
$expected = [];
foreach (sbcis_backup_data_tables() as $table) $expected[$table] = (int)$database->query('SELECT COUNT(*) FROM `' . $table . '`')->fetchColumn();
$stream = sbcis_prepare_export($database, 'backup');
$sql = stream_get_contents($stream);
fclose($stream);

$checks = 0;
$expect = static function (bool $condition, string $message) use (&$checks): void {
    if (!$condition) throw new RuntimeException($message);
    $checks++;
};

try {
    $database->exec('CREATE DATABASE `' . $testSchema . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    $database->exec('USE `' . $testSchema . '`');

    $triggerStart = strpos($sql, "DELIMITER $$\n");
    $triggerEnd = strpos($sql, "DELIMITER ;", $triggerStart === false ? 0 : $triggerStart);
    if ($triggerStart === false || $triggerEnd === false) throw new RuntimeException('Backup trigger delimiters are missing.');
    $triggerSql = substr($sql, $triggerStart + strlen("DELIMITER $$\n"), $triggerEnd - ($triggerStart + strlen("DELIMITER $$\n")));
    $mainSql = substr($sql, 0, $triggerStart) . substr($sql, $triggerEnd + strlen('DELIMITER ;'));
    $mainSql = preg_replace('/^--.*$/m', '', $mainSql);
    foreach (preg_split('/;\s*(?:\r?\n|$)/', $mainSql) as $statement) {
        $statement = trim($statement);
        if ($statement !== '') $database->exec($statement);
    }
    foreach (explode('$$', $triggerSql) as $statement) {
        $statement = trim($statement);
        if ($statement !== '') $database->exec($statement);
    }

    foreach ($expected as $table => $count) {
        $expect((int)$database->query('SELECT COUNT(*) FROM `' . $table . '`')->fetchColumn() === $count,
            'Restored row count differs for ' . $table . '.');
    }
    $expect((int)$database->query('SELECT COUNT(*) FROM admins')->fetchColumn() === 0, 'Administrator credentials were restored.');
    $expect((int)$database->query("SELECT COUNT(*) FROM information_schema.views WHERE table_schema=" . $database->quote($testSchema) . " AND table_name='v_geotechnical_map_data'")->fetchColumn() === 1, 'Map view was not restored.');
    $expect((int)$database->query('SELECT COUNT(*) FROM information_schema.triggers WHERE trigger_schema=' . $database->quote($testSchema))->fetchColumn() === 6, 'Interpolation triggers were not restored.');
    $expect((int)$database->query("SELECT COUNT(*) FROM information_schema.table_constraints WHERE constraint_schema=" . $database->quote($testSchema) . " AND constraint_name='fk_borehole_barangay_municipality'")->fetchColumn() === 1, 'Composite location constraint was not restored.');
} finally {
    $database->exec('USE `' . str_replace('`', '``', $sourceSchema) . '`');
    $database->exec('DROP DATABASE IF EXISTS `' . $testSchema . '`');
}

echo $checks . " isolated empty-database restore checks passed; temporary database removed.\n";
