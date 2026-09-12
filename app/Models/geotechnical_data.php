<?php
require_once __DIR__ . '/locations.php';

function sbcis_get_database(): ?PDO
{
    $databaseFile = __DIR__ . '/../../config/config.php';

    if (!file_exists($databaseFile)) {
        return null;
    }

    require $databaseFile;

    if (isset($pdo) && $pdo instanceof PDO) {
        return $pdo;
    }

    if (isset($conn) && $conn instanceof PDO) {
        return $conn;
    }

    return null;
}

function sbcis_fetch_map_boreholes(PDO $db): array
{
    $stmt = $db->query("
        SELECT
            borehole_id,
            borehole_code,
            municipality_name,
            barangay_name,
            latitude,
            longitude,
            elevation_m,
            borehole_depth_m,
            soil_layer_id,
            layer_number,
            soil_type,
            soil_classification,
            soil_description,
            depth_from_m,
            depth_to_m,
            spt_n_value,
            bearing_capacity_kpa
        FROM v_geotechnical_map_data
        WHERE latitude IS NOT NULL
          AND longitude IS NOT NULL
        ORDER BY borehole_code ASC, layer_number ASC
    ");

    $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $boreholes = [];

    foreach ($records as $record) {
        $id = (int) $record['borehole_id'];

        if (!isset($boreholes[$id])) {
            $boreholes[$id] = [
                'borehole_id' => $id,
                'borehole_code' => $record['borehole_code'],
                'municipality_name' => $record['municipality_name'],
                'barangay_name' => $record['barangay_name'],
                'latitude' => (float) $record['latitude'],
                'longitude' => (float) $record['longitude'],
                'elevation_m' => $record['elevation_m'],
                'borehole_depth_m' => $record['borehole_depth_m'],
                'layers' => [],
            ];
        }

        if ($record['soil_layer_id'] !== null) {
            $boreholes[$id]['layers'][] = [
                'soil_layer_id' => (int) $record['soil_layer_id'],
                'layer_number' => (int) $record['layer_number'],
                'soil_type' => $record['soil_type'],
                'soil_classification' => $record['soil_classification'],
                'soil_description' => $record['soil_description'],
                'depth_from_m' => $record['depth_from_m'],
                'depth_to_m' => $record['depth_to_m'],
                'spt_n_value' => $record['spt_n_value'],
                'bearing_capacity_kpa' => $record['bearing_capacity_kpa'],
            ];
        }
    }

    return array_values($boreholes);
}

function sbcis_fetch_recent_boreholes(PDO $db, ?int $limit = 50): array
{
    $limitClause = $limit === null ? '' : 'LIMIT :limit';
    $stmt = $db->prepare("
        SELECT
            b.borehole_id,
            b.borehole_code,
            b.borehole_depth_m,
            b.latitude,
            b.longitude,
            b.elevation_m,
            m.municipality_name,
            br.barangay_name,
            COUNT(sl.soil_layer_id) AS layer_count
        FROM boreholes b
        LEFT JOIN municipalities m ON b.municipality_id = m.municipality_id
        LEFT JOIN barangays br ON b.barangay_id = br.barangay_id
        LEFT JOIN soil_layers sl ON b.borehole_id = sl.borehole_id
        GROUP BY b.borehole_id
        ORDER BY b.created_at DESC, b.borehole_id DESC
        {$limitClause}
    ");

    if ($limit !== null) $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function sbcis_nullable_float($value): ?float
{
    if ($value === null || trim((string) $value) === '') {
        return null;
    }

    if (!is_scalar($value) || !is_numeric($value) || !is_finite((float)$value)) {
        throw new InvalidArgumentException('Enter a valid number in each numeric field.');
    }
    return (float) $value;
}

function sbcis_nullable_int($value): ?int
{
    if ($value === null || trim((string) $value) === '') {
        return null;
    }

    if (!is_scalar($value) || !preg_match('/^\d+$/D', (string)$value) || (float)$value > 4294967295) {
        throw new InvalidArgumentException('SPT N-values must be nonnegative whole numbers.');
    }
    return (int) $value;
}

function sbcis_find_or_create_municipality(PDO $db, string $name): ?int
{
    $name = trim($name);

    if ($name === '') {
        return null;
    }

    // Reuse saved names such as Maasin / City of Maasin when the directory spelling differs.
    $matches = [];
    foreach ($db->query('SELECT municipality_id, municipality_name FROM municipalities')->fetchAll() as $row) {
        if (sbcis_location_key($row['municipality_name']) === sbcis_location_key($name)) $matches[] = (int)$row['municipality_id'];
    }
    if (count($matches) === 1) return $matches[0];

    $stmt = $db->prepare("
        INSERT INTO municipalities (municipality_name)
        VALUES (:name)
        ON DUPLICATE KEY UPDATE municipality_name = VALUES(municipality_name)
    ");
    $stmt->execute([':name' => $name]);

    $stmt = $db->prepare("
        SELECT municipality_id
        FROM municipalities
        WHERE municipality_name = :name
        LIMIT 1
    ");
    $stmt->execute([':name' => $name]);

    $id = $stmt->fetchColumn();

    return $id ? (int) $id : null;
}

function sbcis_find_or_create_barangay(PDO $db, ?int $municipalityId, string $name): ?int
{
    $name = trim($name);

    if ($name === '' || !$municipalityId) {
        return null;
    }

    $saved = $db->prepare('SELECT barangay_id, barangay_name FROM barangays WHERE municipality_id = ?');
    $saved->execute([$municipalityId]); $matches = [];
    foreach ($saved->fetchAll() as $row) {
        if (sbcis_location_key($row['barangay_name']) === sbcis_location_key($name)) $matches[] = (int)$row['barangay_id'];
    }
    if (count($matches) === 1) return $matches[0];

    $stmt = $db->prepare("
        INSERT INTO barangays (municipality_id, barangay_name)
        VALUES (:municipality_id, :name)
        ON DUPLICATE KEY UPDATE barangay_name = VALUES(barangay_name)
    ");
    $stmt->execute([
        ':municipality_id' => $municipalityId,
        ':name' => $name,
    ]);

    $stmt = $db->prepare("
        SELECT barangay_id
        FROM barangays
        WHERE municipality_id = :municipality_id
          AND barangay_name = :name
        LIMIT 1
    ");
    $stmt->execute([
        ':municipality_id' => $municipalityId,
        ':name' => $name,
    ]);

    $id = $stmt->fetchColumn();

    return $id ? (int) $id : null;
}

function sbcis_create_geotechnical_record(PDO $db, array $input): int
{
    $code = trim((string) ($input['borehole_code'] ?? ''));
    $depth = sbcis_nullable_float($input['borehole_depth_m'] ?? null);
    $latitude = sbcis_nullable_float($input['latitude'] ?? null);
    $longitude = sbcis_nullable_float($input['longitude'] ?? null);

    if ($code === '' || $depth === null || $latitude === null || $longitude === null) {
        throw new InvalidArgumentException('Borehole ID, depth, latitude, and longitude are required.');
    }

    if ($depth <= 0 || $depth > 999999.99) {
        throw new InvalidArgumentException('Borehole depth must be between 0.01 and 999999.99 metres.');
    }

    if ($latitude < -90 || $latitude > 90 || $longitude < -180 || $longitude > 180) {
        throw new InvalidArgumentException('Latitude or longitude is outside the valid range.');
    }

    $soilTypes = $input['soil_type'] ?? [];
    $layers = [];
    $previousEnd = 0;
    if (!is_array($soilTypes) || count($soilTypes) > 100) throw new InvalidArgumentException('Enter between 1 and 100 soil layers.');
    if (strlen($code) > 50) throw new InvalidArgumentException('Borehole ID must be at most 50 bytes.');

    foreach ($soilTypes as $index => $soilType) {
        $soilType = trim((string) $soilType);

        if ($soilType === '') {
            throw new InvalidArgumentException('Enter a soil type for every layer.');
        }

        $from = sbcis_nullable_float($input['depth_from_m'][$index] ?? null);
        $to = sbcis_nullable_float($input['depth_to_m'][$index] ?? null);

        if ($from === null || $to === null || $from < 0 || $to <= $from) {
            throw new InvalidArgumentException('Each soil layer needs a valid depth range.');
        }
        if ($from < $previousEnd || $to > $depth) throw new InvalidArgumentException('Enter layers in depth order, without overlap, within the borehole depth.');
        $previousEnd = $to;
        $capacity = sbcis_nullable_float($input['bearing_capacity_kpa'][$index] ?? null);
        if ($capacity !== null && ($capacity < 0 || $capacity > 99999999.99)) throw new InvalidArgumentException('Bearing capacity must be between 0 and 99999999.99 kPa.');

        $layers[] = [
            'soil_type' => $soilType,
            'soil_classification' => trim((string) ($input['soil_classification'][$index] ?? '')),
            'soil_description' => trim((string) ($input['soil_description'][$index] ?? '')),
            'depth_from_m' => $from,
            'depth_to_m' => $to,
            'spt_n_value' => sbcis_nullable_int($input['spt_n_value'][$index] ?? null),
            'bearing_capacity_kpa' => $capacity,
        ];
    }

    if (!$layers) {
        throw new InvalidArgumentException('Add at least one soil layer.');
    }

    $db->beginTransaction();

    try {
        $municipalityId = sbcis_find_or_create_municipality(
            $db,
            (string) ($input['municipality_name'] ?? '')
        );

        $barangayId = sbcis_find_or_create_barangay(
            $db,
            $municipalityId,
            (string) ($input['barangay_name'] ?? '')
        );

        $stmt = $db->prepare("
            INSERT INTO boreholes (
                borehole_code,
                municipality_id,
                barangay_id,
                borehole_depth_m,
                latitude,
                longitude,
                elevation_m
            )
            VALUES (
                :borehole_code,
                :municipality_id,
                :barangay_id,
                :borehole_depth_m,
                :latitude,
                :longitude,
                :elevation_m
            )
        ");

        $stmt->execute([
            ':borehole_code' => $code,
            ':municipality_id' => $municipalityId,
            ':barangay_id' => $barangayId,
            ':borehole_depth_m' => $depth,
            ':latitude' => $latitude,
            ':longitude' => $longitude,
            ':elevation_m' => sbcis_nullable_float($input['elevation_m'] ?? null),
        ]);

        $boreholeId = (int) $db->lastInsertId();

        $layerStmt = $db->prepare("
            INSERT INTO soil_layers (
                borehole_id,
                layer_number,
                soil_type,
                soil_classification,
                soil_description,
                depth_from_m,
                depth_to_m,
                spt_n_value,
                bearing_capacity_kpa
            )
            VALUES (
                :borehole_id,
                :layer_number,
                :soil_type,
                :soil_classification,
                :soil_description,
                :depth_from_m,
                :depth_to_m,
                :spt_n_value,
                :bearing_capacity_kpa
            )
        ");

        foreach ($layers as $index => $layer) {
            $layerStmt->execute([
                ':borehole_id' => $boreholeId,
                ':layer_number' => $index + 1,
                ':soil_type' => $layer['soil_type'],
                ':soil_classification' => $layer['soil_classification'] ?: null,
                ':soil_description' => $layer['soil_description'] ?: null,
                ':depth_from_m' => $layer['depth_from_m'],
                ':depth_to_m' => $layer['depth_to_m'],
                ':spt_n_value' => $layer['spt_n_value'],
                ':bearing_capacity_kpa' => $layer['bearing_capacity_kpa'],
            ]);
        }

        $db->commit();

        return $boreholeId;
    } catch (Throwable $e) {
        $db->rollBack();
        throw $e;
    }
}
