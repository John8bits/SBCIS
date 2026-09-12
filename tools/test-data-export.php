<?php
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require_once __DIR__ . '/../app/Models/data_export.php';
$checks = 0;
function verify(bool $condition, string $label): void
{
    global $checks;
    if (!$condition)
        throw new RuntimeException($label);
    $checks++;
}
verify(sbcis_csv_cell('=HYPERLINK("test")')[0] === "'", 'Formula protection');
verify(sbcis_csv_cell("\t+SUM(1)")[0] === "'", 'Whitespace formula protection');
verify(sbcis_csv_cell('-12.75') === '-12.75', 'Negative coordinate retained');
verify(sbcis_csv_cell('0') === '0', 'Zero retained');
verify(sbcis_csv_cell(null) === '', 'NULL spreadsheet cell');
verify(sbcis_csv_cell("Clay, with \"sand\"\nnext line") === "Clay, with \"sand\"\nnext line", 'Multiline text retained');
$db = sbcis_get_database();
$backup = sbcis_prepare_export($db, 'backup');
$sql = stream_get_contents($backup);
fclose($backup);
verify(strpos($sql, 'CREATE TABLE `admins`') === false, 'No administrator table');
verify(strpos($sql, 'INSERT INTO `admins`') === false, 'No administrator credentials');
verify(strpos($sql, 'DROP TABLE') === false, 'No destructive restore commands');
foreach (array_keys(sbcis_export_queries()) as $table) {
    verify(strpos($sql, 'CREATE TABLE `' . $table . '`') !== false, 'Schema included for ' . $table);
    $stream = sbcis_prepare_export($db, $table);
    verify(fread($stream, 3) === "\xEF\xBB\xBF", 'Spreadsheet UTF-8 BOM');
    verify(count(fgetcsv($stream, 0, ',', '"', '')) > 0, 'CSV headers');
    $rows = 0;
    while (fgetcsv($stream, 0, ',', '"', '') !== false)
        $rows++;
    verify($rows === (int) $db->query('SELECT COUNT(*) FROM ' . $table)->fetchColumn(), 'Complete dataset exported');
    fclose($stream);
}
// Restore INSERT statements into connection-local temporary tables only.
// Production records are read, never modified by this test.
$original = [];
foreach (array_keys(sbcis_export_queries()) as $table) {
    $original[$table] = $db->query('SELECT * FROM `' . $table . '` ORDER BY 1')->fetchAll();
    $columns = [];
    foreach ($db->query('SHOW COLUMNS FROM `' . $table . '`')->fetchAll() as $column) {
        $columns[] = '`' . $column['Field'] . '` ' . $column['Type'] . ' NULL';
    }
    $db->exec('CREATE TEMPORARY TABLE `' . $table . '` (' . implode(',', $columns) . ') ENGINE=InnoDB');
}
foreach (explode("\n", $sql) as $line)
    if (strpos($line, 'INSERT INTO `') === 0)
        $db->exec($line);
foreach ($original as $table => $rows)
    verify($rows === $db->query('SELECT * FROM `' . $table . '` ORDER BY 1')->fetchAll(), 'Exact backup round trip: ' . $table);
// Check difficult values even if the live database is empty.
$db->exec("INSERT INTO municipalities (municipality_id, municipality_name) VALUES (4294967294, 'Test')");
$stmt = $db->prepare('UPDATE municipalities SET municipality_name = ? WHERE municipality_id = 4294967294');
$difficult = "Quotes '\", slash \\, newline\nUnicode: " . hex2bin('c3b1');
$stmt->execute([$difficult]);
$stream = sbcis_prepare_export($db, 'backup');
$fixtureSql = stream_get_contents($stream);
fclose($stream);
$db->exec('DELETE FROM municipalities WHERE municipality_id = 4294967294');
foreach (explode("\n", $fixtureSql) as $line) {
    if (strpos($line, 'INSERT INTO `municipalities`') === 0 && strpos($line, bin2hex($difficult)) !== false)
        $db->exec($line);
}
verify($db->query('SELECT municipality_name FROM municipalities WHERE municipality_id = 4294967294')->fetchColumn() === $difficult, 'Special characters restored exactly');
try {
    sbcis_prepare_export($db, 'admins');
    throw new RuntimeException('Unexpected export');
} catch (InvalidArgumentException $e) {
    verify(true, 'Reject unsupported export');
}
echo "$checks export checks passed.\n";
