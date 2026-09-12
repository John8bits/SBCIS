<?php
require_once __DIR__ . '/geotechnical_data.php';

function sbcis_export_queries(): array
{
    return [
        'municipalities' => 'SELECT * FROM municipalities ORDER BY municipality_id',
        'barangays' => 'SELECT br.*, m.municipality_name FROM barangays br LEFT JOIN municipalities m ON m.municipality_id = br.municipality_id ORDER BY br.barangay_id',
        'boreholes' => 'SELECT b.*, m.municipality_name, br.barangay_name FROM boreholes b LEFT JOIN municipalities m ON m.municipality_id = b.municipality_id LEFT JOIN barangays br ON br.barangay_id = b.barangay_id ORDER BY b.borehole_id',
        'soil_layers' => 'SELECT sl.*, b.borehole_code FROM soil_layers sl JOIN boreholes b ON b.borehole_id = sl.borehole_id ORDER BY sl.borehole_id, sl.layer_number',
    ];
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

function sbcis_write_backup(PDO $db, $stream): void
{
    fwrite($stream, "-- SBCIS data backup | " . gmdate('Y-m-d H:i:s') . " UTC\n-- Restore into an EMPTY MySQL database using a database administrator.\n-- Includes all location, borehole, and soil-layer records, IDs and timestamps.\n-- Administrator accounts and passwords are not included.\nSET NAMES utf8mb4;\nSET @SBCIS_OLD_SQL_MODE = @@SQL_MODE;\nSET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';\n\n");
    foreach (array_keys(sbcis_export_queries()) as $table) {
        $definition = $db->query('SHOW CREATE TABLE `' . $table . '`')->fetch(PDO::FETCH_NUM)[1];
        fwrite($stream, $definition . ";\n\n");
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
    fwrite($stream, "SET SQL_MODE = @SBCIS_OLD_SQL_MODE;\n-- End of SBCIS backup.\n");
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
