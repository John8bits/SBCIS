<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__) . '/config/bootstrap.php';

$service = new App\Services\AdminDataService(App\Database\Connection::get());
$checks = 0;
foreach (['boreholes','soil_layers','municipalities','barangays','soil_reports','bearing_capacity'] as $dataset) {
    $result = $service->page($dataset, ['page'=>1, 'page_size'=>10]);
    if (count($result['rows']) > 10 || $result['page_size'] !== 10 || $result['total'] < count($result['rows']))
        throw new RuntimeException('Invalid pagination for ' . $dataset);
    $checks++;
}
$search = $service->page('municipalities', ['search'=>'Maasin', 'page_size'=>10]);
if ($search['total'] < 1 || !str_contains($search['rows'][0]['municipality_name'], 'Maasin'))
    throw new RuntimeException('Municipality search failed.');
$checks++;
$search = $service->page('barangays', ['search'=>'Maasin', 'page_size'=>10]);
if ($search['total'] < 1 || !str_contains($search['rows'][0]['municipality_name'], 'Maasin'))
    throw new RuntimeException('Barangay/municipality search failed.');
$checks++;
try {
    $service->page('boreholes', ['search'=>str_repeat('x', 101)]);
    throw new RuntimeException('Oversized search accepted.');
} catch (InvalidArgumentException $expected) { $checks++; }
$adminPage = file_get_contents(dirname(__DIR__) . '/views/admin/admin_data_page.php');
$soilRecordsPage = file_get_contents(dirname(__DIR__) . '/views/admin/soil_records.php');
$adminCss = file_get_contents(dirname(__DIR__) . '/src/css/admin_simple.css');
foreach ([$adminPage, $soilRecordsPage] as $markup) {
    if (!str_contains($markup, 'table-search-control') ||
        !str_contains($markup, 'table-page-size') ||
        !str_contains($markup, 'table-search-button')) {
        throw new RuntimeException('The server-side search toolbar markup is incomplete.');
    }
    $checks++;
}
if (!str_contains($adminCss, '.table-search-input') ||
    !str_contains($adminCss, '.table-toolbar-actions') ||
    !str_contains($adminCss, '@media(max-width:600px)')) {
    throw new RuntimeException('The responsive search toolbar styles are incomplete.');
}
$checks++;
$directoryPage = file_get_contents(dirname(__DIR__) . '/views/admin/location_directory.php');
$tableScript = file_get_contents(dirname(__DIR__) . '/src/js/admin_tables.js');
if (!str_contains($directoryPage, 'data-local-table-toolbar') ||
    !str_contains($directoryPage, 'Barangay or municipality name') ||
    !str_contains($directoryPage, 'Municipality or city name') ||
    !str_contains($directoryPage, 'data-table-page-size')) {
    throw new RuntimeException('Location-directory search toolbar markup is incomplete.');
}
$checks++;
if (!str_contains($tableScript, "toolbar?.matches('[data-local-table-toolbar]')") ||
    !str_contains($tableScript, "pageSize.addEventListener('change'") ||
    !str_contains($tableScript, "clear?.addEventListener('click'")) {
    throw new RuntimeException('Location-directory search interactions are incomplete.');
}
$checks++;
echo $checks . " admin pagination/search checks passed.\n";
