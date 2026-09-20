<?php
require_once __DIR__ . '/geotechnical_data.php';

function sbcis_export_queries(): array
{
    $sampleBorehole = sbcis_sample_borehole_sql('b');
    $sampleLayer = sbcis_sample_layer_sql('sl', 'b');
    return [
        'municipalities' => 'SELECT * FROM municipalities ORDER BY municipality_id',
        'barangays' => 'SELECT br.*, m.municipality_name FROM barangays br LEFT JOIN municipalities m ON m.municipality_id = br.municipality_id ORDER BY br.barangay_id',
        'boreholes' => "SELECT b.*, m.municipality_name, br.barangay_name,
            CASE WHEN {$sampleBorehole} THEN 'sample' ELSE 'field' END AS record_source
            FROM boreholes b LEFT JOIN municipalities m ON m.municipality_id = b.municipality_id
            LEFT JOIN barangays br ON br.barangay_id = b.barangay_id ORDER BY b.borehole_id",
        'soil_layers' => "SELECT sl.*, b.borehole_code, m.municipality_name, br.barangay_name,
            CASE WHEN {$sampleLayer} THEN 'sample' ELSE 'field' END AS record_source
            FROM soil_layers sl JOIN boreholes b ON b.borehole_id = sl.borehole_id
            LEFT JOIN municipalities m ON m.municipality_id = b.municipality_id
            LEFT JOIN barangays br ON br.barangay_id = b.barangay_id
            ORDER BY sl.borehole_id, sl.layer_number",
    ];
}

function sbcis_sample_borehole_sql(string $boreholeAlias): string
{
    return "(UPPER({$boreholeAlias}.borehole_code) LIKE 'SYNTH-DEMO-%'
        OR EXISTS (SELECT 1 FROM soil_layers source_layer
            WHERE source_layer.borehole_id = {$boreholeAlias}.borehole_id
              AND (UPPER(COALESCE(source_layer.soil_type, '')) LIKE '%SYNTHETIC SAMPLE%'
                OR UPPER(COALESCE(source_layer.soil_description, '')) LIKE '%ARTIFICIAL DEMO%'
                OR UPPER(COALESCE(source_layer.soil_description, '')) LIKE '%NOT MEASURED%'
                OR UPPER(COALESCE(source_layer.soil_description, '')) LIKE '%NOT FOR ENGINEERING USE%')))";
}

function sbcis_sample_layer_sql(string $layerAlias, string $boreholeAlias): string
{
    return "(UPPER({$boreholeAlias}.borehole_code) LIKE 'SYNTH-DEMO-%'
        OR UPPER(COALESCE({$layerAlias}.soil_type, '')) LIKE '%SYNTHETIC SAMPLE%'
        OR UPPER(COALESCE({$layerAlias}.soil_description, '')) LIKE '%ARTIFICIAL DEMO%'
        OR UPPER(COALESCE({$layerAlias}.soil_description, '')) LIKE '%NOT MEASURED%'
        OR UPPER(COALESCE({$layerAlias}.soil_description, '')) LIKE '%NOT FOR ENGINEERING USE%')";
}

function sbcis_backup_schema_tables(): array
{
    return ['municipalities', 'barangays', 'boreholes', 'soil_layers', 'admins', 'system_revisions'];
}

function sbcis_backup_data_tables(): array
{
    return ['municipalities', 'barangays', 'boreholes', 'soil_layers', 'system_revisions'];
}

function sbcis_csv_cell($value): string
{
    $text = $value === null ? '' : (string) $value;
    // Spreadsheet programs must treat user-entered formulas as text.
    if (preg_match('/^[\s\x00-\x1f]*[=+@-]/u', $text) && !preg_match('/^-?\d+(?:\.\d+)?$/D', $text))
        $text = "'" . $text;
    return $text;
}

function sbcis_write_csv(PDO $db, $stream, string $dataset): void
{
    $query = sbcis_export_queries()[$dataset] ?? null;
    if (!$query)
        throw new InvalidArgumentException('Choose a supported dataset.');
    $statement = $db->query($query);
    $headers = [];
    for ($i = 0; $i < $statement->columnCount(); $i++)
        $headers[] = $statement->getColumnMeta($i)['name'];
    fwrite($stream, "\xEF\xBB\xBF");
    fputcsv($stream, $headers, ',', '"', '');
    while ($row = $statement->fetch(PDO::FETCH_NUM))
        fputcsv($stream, array_map('sbcis_csv_cell', $row), ',', '"', '');
}

function sbcis_prepare_rows_csv(array $rows, array $columns)
{
    $stream = fopen('php://temp/maxmemory:5242880', 'w+');
    if (!$stream) throw new RuntimeException('Unable to prepare the download.');
    fwrite($stream, "\xEF\xBB\xBF");
    fputcsv($stream, array_keys($columns), ',', '"', '');
    foreach ($rows as $row) {
        $values = [];
        foreach ($columns as $sourceKey) $values[] = sbcis_csv_cell($row[$sourceKey] ?? null);
        fputcsv($stream, $values, ',', '"', '');
    }
    rewind($stream);
    return $stream;
}

function sbcis_write_backup(PDO $db, $stream): void
{
    $tables = sbcis_backup_data_tables();
    fwrite($stream, "-- SBCIS data backup | " . gmdate('Y-m-d H:i:s') . " UTC\n" .
        "-- Restore into an EMPTY MySQL database. This file never drops or overwrites tables.\n" .
        "-- Includes location, borehole, and soil-layer records with their IDs, relationships, and timestamps.\n" .
        "-- Administrator table structure is included; account rows and password hashes are intentionally excluded.\n" .
        "-- Source row manifest:\n");
    foreach ($tables as $table) {
        $count = (int) $db->query('SELECT COUNT(*) FROM `' . $table . '`')->fetchColumn();
        fwrite($stream, '--   ' . $table . ': ' . $count . " row(s)\n");
    }
    fwrite($stream, "SET NAMES utf8mb4;\n" .
        "SET @SBCIS_OLD_SQL_MODE = @@SQL_MODE;\n" .
        "SET @SBCIS_OLD_FOREIGN_KEY_CHECKS = @@FOREIGN_KEY_CHECKS;\n" .
        "SET @SBCIS_OLD_UNIQUE_CHECKS = @@UNIQUE_CHECKS;\n" .
        "SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';\n" .
        "SET FOREIGN_KEY_CHECKS = 0;\n" .
        "SET UNIQUE_CHECKS = 0;\n\n");
    foreach (sbcis_backup_schema_tables() as $table) {
        $definition = $db->query('SHOW CREATE TABLE `' . $table . '`')->fetch(PDO::FETCH_NUM)[1];
        fwrite($stream, $definition . ";\n\n");
    }
    fwrite($stream, "START TRANSACTION;\n\n");
    foreach ($tables as $table) {
        $stmt = $db->query('SELECT * FROM `' . $table . '` ORDER BY 1');
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $columns = array_map(static function ($key) {
                return '`' . str_replace('`', '``', $key) . '`'; }, array_keys($row));
            // Hex-encoded UTF-8 strings preserve quotes, newlines, and NULL vs empty text in any SQL mode.
            $values = array_map(static function ($value) {
                return $value === null ? 'NULL' : "CONVERT(X'" . bin2hex((string) $value) . "' USING utf8mb4)"; }, array_values($row));
            fwrite($stream, 'INSERT INTO `' . $table . '` (' . implode(', ', $columns) . ') VALUES (' . implode(', ', $values) . ");\n");
        }
        fwrite($stream, "\n");
    }
    fwrite($stream, "COMMIT;\n\n");
    $view = $db->query('SHOW CREATE VIEW `v_geotechnical_map_data`')->fetch(PDO::FETCH_ASSOC);
    $viewDefinition = (string) ($view['Create View'] ?? '');
    $viewDefinition = preg_replace('/DEFINER=`[^`]+`@`[^`]+`\s+/i', '', $viewDefinition);
    $viewDefinition = str_ireplace('SQL SECURITY DEFINER', 'SQL SECURITY INVOKER', $viewDefinition);
    if ($viewDefinition !== '') fwrite($stream, $viewDefinition . ";\n\n");
    foreach ([
        'trg_boreholes_interpolation_insert', 'trg_boreholes_interpolation_update', 'trg_boreholes_interpolation_delete',
        'trg_layers_interpolation_insert', 'trg_layers_interpolation_update', 'trg_layers_interpolation_delete',
    ] as $triggerName) {
        $trigger = $db->query('SHOW CREATE TRIGGER `' . $triggerName . '`')->fetch(PDO::FETCH_ASSOC);
        $definition = (string) ($trigger['SQL Original Statement'] ?? '');
        $definition = preg_replace('/CREATE\s+DEFINER=`[^`]+`@`[^`]+`\s+/i', 'CREATE ', $definition);
        if ($definition !== '') fwrite($stream, $definition . ";\n");
    }
    fwrite($stream, "\n");
    fwrite($stream, "SET UNIQUE_CHECKS = @SBCIS_OLD_UNIQUE_CHECKS;\n" .
        "SET FOREIGN_KEY_CHECKS = @SBCIS_OLD_FOREIGN_KEY_CHECKS;\n" .
        "SET SQL_MODE = @SBCIS_OLD_SQL_MODE;\n-- End of SBCIS backup.\n");
}

function sbcis_prepare_export(PDO $db, string $dataset)
{
    if ($dataset !== 'backup' && !isset(sbcis_export_queries()[$dataset]))
        throw new InvalidArgumentException('Unknown export.');
    $stream = fopen('php://temp/maxmemory:5242880', 'w+');
    if (!$stream)
        throw new RuntimeException('Unable to prepare the download.');
    try {
        $db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
        $db->beginTransaction();
        if ($dataset === 'backup')
            sbcis_write_backup($db, $stream);
        else
            sbcis_write_csv($db, $stream, $dataset);
        $db->commit();
        rewind($stream);
        return $stream;
    } catch (Throwable $error) {
        if ($db->inTransaction())
            $db->rollBack();
        fclose($stream);
        throw $error;
    }
}
