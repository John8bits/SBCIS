<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../app/Models/geotechnical_data.php';
$db = sbcis_get_database();
// Shadow application tables on this connection so saved fixtures never reach real tables.
foreach (['municipalities', 'barangays', 'boreholes', 'soil_layers'] as $table) {
    $columns = [];
    foreach ($db->query('SHOW COLUMNS FROM ' . $table)->fetchAll() as $column) {
        $definition = '`' . $column['Field'] . '` ' . $column['Type'] . ($column['Null'] === 'YES' ? ' NULL' : ' NOT NULL');
        if ($column['Key'] === 'PRI') $definition .= ' PRIMARY KEY';
        if (strpos($column['Extra'], 'auto_increment') !== false) $definition .= ' AUTO_INCREMENT';
        if ($column['Default'] !== null) $definition .= ' DEFAULT ' . (stripos($column['Default'], 'current_timestamp') === 0 ? 'CURRENT_TIMESTAMP' : $db->quote($column['Default']));
        $columns[] = $definition;
    }
    $unique = ['municipalities' => 'municipality_name', 'barangays' => 'municipality_id,barangay_name', 'boreholes' => 'borehole_code', 'soil_layers' => 'borehole_id,layer_number'];
    $columns[] = 'UNIQUE (' . $unique[$table] . ')';
    $db->exec('CREATE TEMPORARY TABLE ' . $table . ' (' . implode(',', $columns) . ') ENGINE=InnoDB');
}
$input = ['borehole_code' => 'TEST-ENTRY', 'borehole_depth_m' => '10', 'latitude' => '10.2', 'longitude' => '125.1',
    'municipality_name' => 'Test municipality', 'barangay_name' => 'Test barangay', 'soil_type' => ['Clay', 'Sand'],
    'depth_from_m' => ['0', '5'], 'depth_to_m' => ['5', '10'], 'spt_n_value' => ['0', ''], 'bearing_capacity_kpa' => ['0', '100']];
$checks = 0;
foreach ([['depth_to_m' => ['11', '12']], ['depth_from_m' => ['0', '4']], ['latitude' => 'text'], ['spt_n_value' => ['1.5', '']], ['bearing_capacity_kpa' => ['-1', '']], ['soil_type' => ['', 'Sand']]] as $changes) {
    try { sbcis_create_geotechnical_record($db, array_replace($input, $changes)); throw new RuntimeException('Invalid entry was accepted.'); }
    catch (InvalidArgumentException $e) { $checks++; }
}
$id = sbcis_create_geotechnical_record($db, $input);
if ((int)$db->query('SELECT COUNT(*) FROM boreholes')->fetchColumn() !== 1 || (int)$db->query('SELECT COUNT(*) FROM soil_layers')->fetchColumn() !== 2) throw new RuntimeException('Record and layers were not saved together.'); $checks++;
$rows = $db->query('SELECT * FROM soil_layers ORDER BY layer_number')->fetchAll();
if ((int)$rows[0]['spt_n_value'] !== 0 || $rows[1]['spt_n_value'] !== null) throw new RuntimeException('Zero and missing SPT values were not preserved.'); $checks++;
try { sbcis_create_geotechnical_record($db, $input); throw new RuntimeException('Duplicate accepted.'); } catch (PDOException $e) { $checks++; }
if ((int)$db->query('SELECT COUNT(*) FROM soil_layers')->fetchColumn() !== 2) throw new RuntimeException('Duplicate changed layers.'); $checks++;
$municipalityId = sbcis_find_or_create_municipality($db, 'City of Test municipality');
if ((int)$db->query('SELECT COUNT(*) FROM municipalities')->fetchColumn() !== 1) throw new RuntimeException('Directory city alias created a duplicate.'); $checks++;
if (sbcis_find_or_create_barangay($db, $municipalityId, 'Test barangay (Pob.)') !== (int)$db->query('SELECT barangay_id FROM barangays')->fetchColumn()) throw new RuntimeException('Directory barangay alias did not reuse the saved record.'); $checks++;
echo "$checks record-entry checks passed.\n";
