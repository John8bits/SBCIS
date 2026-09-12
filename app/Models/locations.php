<?php
// Public PSGC reference data; application records remain in the existing database.
function sbcis_location_key(string $name): string
{
    $name = preg_replace('/\([^)]*\)/u', '', $name);
    $name = preg_replace('/\bcity of\b|\bcity\b/iu', '', $name);
    $name = preg_replace('/\bpob\.?\b/iu', 'poblacion', $name);
    $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $name);
    return strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $ascii === false ? $name : $ascii));
}

function sbcis_validate_locations(array $data): bool
{
    if (empty($data['municipalities']) || empty($data['barangays'])) return false;
    $parents = []; $codes = [];
    foreach ($data['municipalities'] as $row) {
        if (!is_array($row) || !preg_match('/^0864\d{5}$/D', $row['code'] ?? '') || !is_string($row['name'] ?? null) || trim($row['name']) === '' || ($row['provinceCode'] ?? '') !== '086400000' || isset($parents[$row['code']])) return false;
        $parents[$row['code']] = true;
    }
    foreach ($data['barangays'] as $row) {
        $parent = ($row['cityCode'] ?? false) ?: ($row['municipalityCode'] ?? '');
        if (!is_array($row) || !preg_match('/^0864\d{5}$/D', $row['code'] ?? '') || !is_string($row['name'] ?? null) || trim($row['name']) === '' || !isset($parents[$parent]) || ($row['provinceCode'] ?? '') !== '086400000' || isset($codes[$row['code']])) return false;
        $codes[$row['code']] = true;
    }
    return true;
}

function sbcis_location_source(): array
{
    $cache = sys_get_temp_dir() . '/sbcis-psgc-' . substr(hash('sha256', __DIR__), 0, 12) . '.json';
    $cached = json_decode((string)@file_get_contents($cache), true);
    if (is_array($cached) && sbcis_validate_locations($cached) && time() - (int)@filemtime($cache) < 86400) return $cached;
    // Brief backoff prevents a provider outage from slowing down every page request.
    $retry = $cache . '.retry';
    if (!file_exists($retry) || time() - (int)@filemtime($retry) > 300) {
        @touch($retry);
        try {
            $data = ['source' => 'https://psgc.gitlab.io/api/', 'fetchedAt' => gmdate('c'), 'status' => 'live'];
            foreach (['municipalities' => 'cities-municipalities', 'barangays' => 'barangays'] as $key => $path) {
                $context = stream_context_create(['http' => ['timeout' => 4, 'header' => "Accept: application/json\r\nUser-Agent: SBCIS/1.0\r\n", 'follow_location' => 0]]);
                $json = @file_get_contents('https://psgc.gitlab.io/api/provinces/086400000/' . $path . '/', false, $context, 0, 2000000);
                if ($json === false) throw new RuntimeException('PSGC API unavailable.');
                $data[$key] = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
            }
            if (!sbcis_validate_locations($data)) throw new RuntimeException('Invalid PSGC response.');
            @file_put_contents($cache, json_encode($data, JSON_THROW_ON_ERROR), LOCK_EX);
            return $data;
        } catch (Throwable $error) { error_log('Location directory: ' . $error->getMessage()); }
    }
    if (is_array($cached) && sbcis_validate_locations($cached)) { $cached['status'] = 'cached'; return $cached; }
    $snapshot = json_decode(file_get_contents(__DIR__ . '/../../database/locations_snapshot.json'), true, 512, JSON_THROW_ON_ERROR);
    if (!sbcis_validate_locations($snapshot)) throw new RuntimeException('Location directory unavailable.');
    $snapshot['status'] = 'snapshot'; return $snapshot;
}

function sbcis_locations(): array
{
    static $catalogue;
    if ($catalogue !== null) return $catalogue;
    $source = sbcis_location_source();
    $municipalFeatures = json_decode(file_get_contents(__DIR__ . '/../../src/qgis/southern_leyte_municipalities.geojson'), true)['features'];
    $barangayFeatures = json_decode(file_get_contents(__DIR__ . '/../../src/qgis/southern_leyte_barangays.geojson'), true)['features'];
    $municipalIndex = []; $barangayIndex = [];
    foreach ($municipalFeatures as $f) $municipalIndex[sbcis_location_key($f['properties']['NAME_2'])][] = $f['properties']['GID_2'];
    foreach ($barangayFeatures as $f) $barangayIndex[$f['properties']['GID_2'] . ':' . sbcis_location_key($f['properties']['NAME_3'])][] = $f['properties']['GID_3'];
    $municipalities = []; $parents = []; $barangays = [];
    foreach ($source['municipalities'] as $row) {
        $matches = $municipalIndex[sbcis_location_key($row['name'])] ?? [];
        $record = ['code' => $row['code'], 'name' => $row['name'], 'boundaryId' => count($matches) === 1 ? $matches[0] : null];
        $municipalities[] = $record; $parents[$row['code']] = $record;
    }
    foreach ($source['barangays'] as $row) {
        $parent = $parents[($row['cityCode'] ?? false) ?: $row['municipalityCode']];
        $matches = $barangayIndex[$parent['boundaryId'] . ':' . sbcis_location_key($row['name'])] ?? [];
        $barangays[] = ['code' => $row['code'], 'name' => $row['name'], 'municipalityCode' => $parent['code'], 'municipalityName' => $parent['name'], 'boundaryId' => count($matches) === 1 ? $matches[0] : null];
    }
    usort($municipalities, static fn($a, $b) => strnatcasecmp($a['name'], $b['name']));
    usort($barangays, static fn($a, $b) => strnatcasecmp($a['municipalityName'] . ' ' . $a['name'], $b['municipalityName'] . ' ' . $b['name']));
    return $catalogue = ['source' => $source['source'], 'fetchedAt' => $source['fetchedAt'], 'status' => $source['status'] ?? 'cached', 'municipalities' => $municipalities, 'barangays' => $barangays];
}
