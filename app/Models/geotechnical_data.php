<?php

require_once __DIR__ . '/../../config/bootstrap.php';
require_once __DIR__ . '/locations.php';

function sbcis_get_database(): ?PDO
{
    return App\Database\Connection::get();
}

function sbcis_fetch_map_boreholes(PDO $db): array
{
    $stmt = $db->query("
        SELECT
            b.borehole_id, b.borehole_code, m.municipality_name, br.barangay_name,
            b.latitude, b.longitude, b.elevation_m, b.borehole_depth_m,
            sl.soil_layer_id, sl.layer_number, sl.soil_type, sl.soil_classification,
            sl.soil_description, sl.depth_from_m, sl.depth_to_m, sl.spt_n_value, sl.bearing_capacity_kpa
        FROM boreholes b
        LEFT JOIN municipalities m ON m.municipality_id=b.municipality_id
        LEFT JOIN barangays br ON br.barangay_id=b.barangay_id
        LEFT JOIN soil_layers sl ON sl.borehole_id=b.borehole_id
        WHERE b.latitude IS NOT NULL AND b.longitude IS NOT NULL AND b.archived_at IS NULL
        ORDER BY b.borehole_code ASC, sl.depth_from_m, sl.layer_number
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
                'layer_count' => 0,
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
            $boreholes[$id]['layer_count']++;
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
        WHERE b.archived_at IS NULL
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

function sbcis_normalize_geotechnical_input(array $input): array
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
    if (!(new App\Services\BoundaryService())->contains($latitude, $longitude)) {
        throw new InvalidArgumentException('Coordinates must fall inside the approved Southern Leyte study boundary. Existing out-of-bound records are preserved for administrator review.');
    }

    $soilTypes = $input['soil_type'] ?? [];
    $layers = [];
    $previousEnd = 0;
    if (!is_array($soilTypes) || count($soilTypes) > 100) throw new InvalidArgumentException('Enter between 1 and 100 soil layers.');
    if (mb_strlen($code) > 50) throw new InvalidArgumentException('Borehole ID must be at most 50 characters.');

    foreach ($soilTypes as $index => $soilType) {
        $soilType = trim((string) $soilType);

        if ($soilType === '') {
            throw new InvalidArgumentException('Enter a soil type for every layer.');
        }
        $classification = trim((string) ($input['soil_classification'][$index] ?? ''));
        $description = trim((string) ($input['soil_description'][$index] ?? ''));
        if (mb_strlen($soilType) > 100 || mb_strlen($classification) > 100) {
            throw new InvalidArgumentException('Soil type and classification must each be 100 characters or fewer.');
        }
        if (strlen($description) > 60000) {
            throw new InvalidArgumentException('Soil descriptions must be 60000 bytes or fewer.');
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
            'soil_classification' => $classification,
            'soil_description' => $description,
            'depth_from_m' => $from,
            'depth_to_m' => $to,
            'spt_n_value' => sbcis_nullable_int($input['spt_n_value'][$index] ?? null),
            'bearing_capacity_kpa' => $capacity,
        ];
    }

    if (!$layers) {
        throw new InvalidArgumentException('Add at least one soil layer.');
    }

    $elevation = sbcis_nullable_float($input['elevation_m'] ?? null);
    if ($elevation !== null && ($elevation < -999999.99 || $elevation > 999999.99)) {
        throw new InvalidArgumentException('Elevation is outside the supported numeric storage range.');
    }

    return [
        'code' => $code,
        'depth' => $depth,
        'latitude' => $latitude,
        'longitude' => $longitude,
        'elevation' => $elevation,
        'municipality_name' => trim((string)($input['municipality_name'] ?? '')),
        'barangay_name' => trim((string)($input['barangay_name'] ?? '')),
        'layers' => $layers,
    ];
}

function sbcis_fetch_map_boreholes_window(PDO $db, array $bbox, int $limit, string $search = ''): array
{
    if (count($bbox) !== 4 || $limit < 1 || $limit > 2001) throw new InvalidArgumentException('Invalid map window.');
    [$minLongitude, $minLatitude, $maxLongitude, $maxLatitude] = array_map('floatval', $bbox);
    if ($minLongitude >= $maxLongitude || $minLatitude >= $maxLatitude ||
        !App\Services\BoundaryService::validCoordinates($minLatitude, $minLongitude) ||
        !App\Services\BoundaryService::validCoordinates($maxLatitude, $maxLongitude)) {
        throw new InvalidArgumentException('Invalid map bounds.');
    }
    $search = trim($search);
    if (mb_strlen($search) > 100) throw new InvalidArgumentException('Map search must be 100 characters or fewer.');
    $sql = 'SELECT b.borehole_id FROM boreholes b
        LEFT JOIN municipalities m ON m.municipality_id=b.municipality_id
        LEFT JOIN barangays br ON br.barangay_id=b.barangay_id
        WHERE b.archived_at IS NULL
          AND b.longitude BETWEEN :min_longitude AND :max_longitude
          AND b.latitude BETWEEN :min_latitude AND :max_latitude';
    if ($search !== '') $sql .= " AND (b.borehole_code LIKE :search_code ESCAPE '\\\\' OR m.municipality_name LIKE :search_municipality ESCAPE '\\\\' OR br.barangay_name LIKE :search_barangay ESCAPE '\\\\')";
    $sql .= ' ORDER BY b.borehole_id LIMIT :limit';
    $statement = $db->prepare($sql);
    $statement->bindValue(':min_longitude', $minLongitude);
    $statement->bindValue(':max_longitude', $maxLongitude);
    $statement->bindValue(':min_latitude', $minLatitude);
    $statement->bindValue(':max_latitude', $maxLatitude);
    if ($search !== '') {
        $pattern = '%' . addcslashes($search, '\\%_') . '%';
        $statement->bindValue(':search_code', $pattern);
        $statement->bindValue(':search_municipality', $pattern);
        $statement->bindValue(':search_barangay', $pattern);
    }
    $statement->bindValue(':limit', $limit, PDO::PARAM_INT);
    $statement->execute();
    $ids = array_map('intval', $statement->fetchAll(PDO::FETCH_COLUMN));
    if (!$ids) return [];

    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $details = $db->prepare("SELECT b.borehole_id, b.borehole_code, m.municipality_name, br.barangay_name,
        b.latitude, b.longitude, b.elevation_m, b.borehole_depth_m,
        sl.soil_layer_id, sl.layer_number, sl.soil_type, sl.soil_classification, sl.soil_description,
        sl.depth_from_m, sl.depth_to_m, sl.spt_n_value, sl.bearing_capacity_kpa
        FROM boreholes b
        LEFT JOIN municipalities m ON m.municipality_id=b.municipality_id
        LEFT JOIN barangays br ON br.barangay_id=b.barangay_id
        LEFT JOIN soil_layers sl ON sl.borehole_id=b.borehole_id
        WHERE b.archived_at IS NULL AND b.borehole_id IN ($placeholders)
        ORDER BY b.borehole_id, sl.depth_from_m, sl.layer_number");
    $details->execute($ids);
    $boreholes = [];
    foreach ($details->fetchAll(PDO::FETCH_ASSOC) as $record) {
        $id = (int) $record['borehole_id'];
        if (!isset($boreholes[$id])) {
            $boreholes[$id] = [
                'borehole_id'=>$id, 'borehole_code'=>$record['borehole_code'],
                'municipality_name'=>$record['municipality_name'], 'barangay_name'=>$record['barangay_name'],
                'latitude'=>(float)$record['latitude'], 'longitude'=>(float)$record['longitude'],
                'elevation_m'=>$record['elevation_m'], 'borehole_depth_m'=>$record['borehole_depth_m'],
                'layer_count'=>0, 'layers'=>[],
            ];
        }
        if ($record['soil_layer_id'] !== null) {
            $boreholes[$id]['layers'][] = [
                'soil_layer_id'=>(int)$record['soil_layer_id'], 'layer_number'=>(int)$record['layer_number'],
                'soil_type'=>$record['soil_type'], 'soil_classification'=>$record['soil_classification'],
                'soil_description'=>$record['soil_description'], 'depth_from_m'=>$record['depth_from_m'],
                'depth_to_m'=>$record['depth_to_m'], 'spt_n_value'=>$record['spt_n_value'],
                'bearing_capacity_kpa'=>$record['bearing_capacity_kpa'],
            ];
            $boreholes[$id]['layer_count']++;
        }
    }
    return array_values($boreholes);
}

function sbcis_insert_layers(PDO $db, int $boreholeId, array $layers): void
{
    $layerStmt = $db->prepare("INSERT INTO soil_layers (
        borehole_id, layer_number, soil_type, soil_classification, soil_description,
        depth_from_m, depth_to_m, spt_n_value, bearing_capacity_kpa
    ) VALUES (
        :borehole_id, :layer_number, :soil_type, :soil_classification, :soil_description,
        :depth_from_m, :depth_to_m, :spt_n_value, :bearing_capacity_kpa
    )");
    foreach ($layers as $index => $layer) {
        $layerStmt->execute([
            ':borehole_id' => $boreholeId, ':layer_number' => $index + 1,
            ':soil_type' => $layer['soil_type'], ':soil_classification' => $layer['soil_classification'] ?: null,
            ':soil_description' => $layer['soil_description'] ?: null, ':depth_from_m' => $layer['depth_from_m'],
            ':depth_to_m' => $layer['depth_to_m'], ':spt_n_value' => $layer['spt_n_value'],
            ':bearing_capacity_kpa' => $layer['bearing_capacity_kpa'],
        ]);
    }
}

function sbcis_touch_interpolation_revision(PDO $db): void
{
    $statement = $db->prepare('UPDATE system_revisions
        SET revision_value=revision_value+1
        WHERE revision_name=:name');
    $statement->execute([':name'=>'interpolation_source']);
    if ($statement->rowCount() !== 1) {
        throw new RuntimeException('Interpolation source revision is unavailable.');
    }
}

function sbcis_create_geotechnical_record(PDO $db, array $input): int
{
    $data = sbcis_normalize_geotechnical_input($input);
    ['code' => $code, 'depth' => $depth, 'latitude' => $latitude, 'longitude' => $longitude, 'layers' => $layers] = $data;

    $db->beginTransaction();

    try {
        $municipalityId = sbcis_find_or_create_municipality(
            $db,
            $data['municipality_name']
        );

        $barangayId = sbcis_find_or_create_barangay(
            $db,
            $municipalityId,
            $data['barangay_name']
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
            ':elevation_m' => $data['elevation'],
        ]);

        $boreholeId = (int) $db->lastInsertId();

        sbcis_insert_layers($db, $boreholeId, $layers);

        sbcis_touch_interpolation_revision($db);

        $db->commit();

        return $boreholeId;
    } catch (Throwable $e) {
        $db->rollBack();
        throw $e;
    }
}

function sbcis_fetch_geotechnical_record(PDO $db, int $boreholeId, bool $includeArchived = false): ?array
{
    if ($boreholeId < 1) return null;
    $stmt = $db->prepare("SELECT b.*, m.municipality_name, br.barangay_name
        FROM boreholes b LEFT JOIN municipalities m ON m.municipality_id = b.municipality_id
        LEFT JOIN barangays br ON br.barangay_id = b.barangay_id
        WHERE b.borehole_id = ?" . ($includeArchived ? '' : ' AND b.archived_at IS NULL'));
    $stmt->execute([$boreholeId]); $record = $stmt->fetch();
    if (!$record) return null;
    $stmt = $db->prepare('SELECT * FROM soil_layers WHERE borehole_id = ? ORDER BY layer_number');
    $stmt->execute([$boreholeId]); $record['layers'] = $stmt->fetchAll();
    return $record;
}

function sbcis_update_geotechnical_record(PDO $db, int $boreholeId, array $input): void
{
    if ($boreholeId < 1) throw new InvalidArgumentException('Choose a valid record to edit.');
    $data = sbcis_normalize_geotechnical_input($input);
    $db->beginTransaction();
    try {
        $lock = $db->prepare('SELECT borehole_id, lock_version FROM boreholes WHERE borehole_id = ? AND archived_at IS NULL FOR UPDATE');
        $lock->execute([$boreholeId]);
        $locked = $lock->fetch();
        if (!$locked) throw new InvalidArgumentException('This record no longer exists.');
        $expectedVersion = filter_var($input['record_lock_version'] ?? null, FILTER_VALIDATE_INT, ['options'=>['min_range'=>1]]);
        if ($expectedVersion === false || $expectedVersion === null) {
            throw new InvalidArgumentException('The record version is required. Reload the record before saving.');
        }
        if ((int)$locked['lock_version'] !== $expectedVersion) {
            throw new InvalidArgumentException('This record was changed in another tab. Reload it before saving your changes.');
        }
        $municipalityId = sbcis_find_or_create_municipality($db, $data['municipality_name']);
        $barangayId = sbcis_find_or_create_barangay($db, $municipalityId, $data['barangay_name']);
        $stmt = $db->prepare("UPDATE boreholes SET borehole_code=:code, municipality_id=:municipality,
            barangay_id=:barangay, borehole_depth_m=:depth, latitude=:latitude, longitude=:longitude,
            elevation_m=:elevation, lock_version=lock_version+1 WHERE borehole_id=:id AND lock_version=:lock_version");
        $stmt->execute([':code'=>$data['code'], ':municipality'=>$municipalityId, ':barangay'=>$barangayId,
            ':depth'=>$data['depth'], ':latitude'=>$data['latitude'], ':longitude'=>$data['longitude'],
            ':elevation'=>$data['elevation'], ':id'=>$boreholeId, ':lock_version'=>$expectedVersion]);
        if ($stmt->rowCount() !== 1) throw new InvalidArgumentException('This record was changed in another request. Reload it before saving.');
        $stmt = $db->prepare('DELETE FROM soil_layers WHERE borehole_id = ?'); $stmt->execute([$boreholeId]);
        sbcis_insert_layers($db, $boreholeId, $data['layers']);
        sbcis_touch_interpolation_revision($db);
        $db->commit();
    } catch (Throwable $error) { if ($db->inTransaction()) $db->rollBack(); throw $error; }
}

function sbcis_delete_geotechnical_record(PDO $db, int $boreholeId): bool
{
    if ($boreholeId < 1) throw new InvalidArgumentException('Choose a valid record to delete.');
    $db->beginTransaction();
    try {
        $stmt = $db->prepare('DELETE FROM soil_layers WHERE borehole_id = ?');
        $stmt->execute([$boreholeId]);
        $stmt = $db->prepare('DELETE FROM boreholes WHERE borehole_id = ?');
        $stmt->execute([$boreholeId]);
        $deleted = $stmt->rowCount() === 1;
        if ($deleted) sbcis_touch_interpolation_revision($db);
        $db->commit();
        return $deleted;
    } catch (Throwable $error) {
        if ($db->inTransaction()) $db->rollBack();
        throw $error;
    }
}

function sbcis_archive_geotechnical_record(PDO $db, int $boreholeId, int $adminId): bool
{
    if ($boreholeId < 1 || $adminId < 1) throw new InvalidArgumentException('Choose a valid record to archive.');
    $statement = $db->prepare('UPDATE boreholes SET archived_at=UTC_TIMESTAMP(), archived_by=:admin_id,
        lock_version=lock_version+1 WHERE borehole_id=:id AND archived_at IS NULL');
    $statement->execute([':admin_id'=>$adminId, ':id'=>$boreholeId]);
    $changed = $statement->rowCount() === 1;
    if ($changed) sbcis_touch_interpolation_revision($db);
    return $changed;
}

function sbcis_restore_geotechnical_record(PDO $db, int $boreholeId): bool
{
    if ($boreholeId < 1) throw new InvalidArgumentException('Choose a valid record to restore.');
    $statement = $db->prepare('UPDATE boreholes SET archived_at=NULL, archived_by=NULL,
        lock_version=lock_version+1 WHERE borehole_id=:id AND archived_at IS NOT NULL');
    $statement->execute([':id'=>$boreholeId]);
    $changed = $statement->rowCount() === 1;
    if ($changed) sbcis_touch_interpolation_revision($db);
    return $changed;
}
