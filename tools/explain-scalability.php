<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';

use App\Database\Connection;

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$database = Connection::get();
$queries = [
    'recent_boreholes' => <<<'SQL'
        EXPLAIN SELECT b.borehole_id, b.borehole_code, b.created_at,
            (SELECT COUNT(*) FROM soil_layers sl WHERE sl.borehole_id = b.borehole_id) AS layer_count
        FROM boreholes b
        ORDER BY b.created_at DESC, b.borehole_id DESC
        LIMIT 50
        SQL,
    'revision_lookup' => <<<'SQL'
        EXPLAIN SELECT revision_value
        FROM system_revisions
        WHERE revision_name = 'interpolation_source'
        LIMIT 1
        SQL,
];

foreach ($queries as $name => $sql) {
    echo $name, PHP_EOL;
    foreach ($database->query($sql)->fetchAll() as $row) {
        echo json_encode($row, JSON_UNESCAPED_SLASHES), PHP_EOL;
    }
}

echo 'recent_boreholes_index', PHP_EOL;
foreach ($database->query("SHOW INDEX FROM boreholes WHERE Key_name = 'idx_borehole_recent'")->fetchAll() as $row) {
    echo json_encode($row, JSON_UNESCAPED_SLASHES), PHP_EOL;
}
