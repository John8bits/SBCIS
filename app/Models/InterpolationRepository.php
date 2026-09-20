<?php

declare(strict_types=1);

namespace App\Models;

use Config\InterpolationConfig;
use PDO;
use RuntimeException;

final class InterpolationRepository
{
    private PDO $database;

    public function __construct(PDO $database)
    {
        $this->database = $database;
    }

    public function snapshot(): array
    {
        // One SELECT supplies both the returned data and the hash, including deletions.
        $statement = $this->database->prepare('SELECT b.borehole_id, b.borehole_code, b.latitude, b.longitude,
            sl.soil_layer_id, sl.soil_type, sl.soil_description,
            sl.depth_from_m, sl.depth_to_m, sl.spt_n_value, sl.bearing_capacity_kpa
            FROM boreholes b LEFT JOIN soil_layers sl ON sl.borehole_id = b.borehole_id
            ORDER BY b.borehole_id, sl.soil_layer_id LIMIT :limit');
        $statement->bindValue(':limit', InterpolationConfig::MAX_OBSERVATIONS + 1, PDO::PARAM_INT);
        $statement->execute();
        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);
        if (count($rows) > InterpolationConfig::MAX_OBSERVATIONS) {
            throw new RuntimeException('Interpolation input limit exceeded; no partial dataset returned.');
        }
        return $rows;
    }

    public function revision(): int
    {
        $statement = $this->database->prepare(
            'SELECT revision_value FROM system_revisions WHERE revision_name=:name LIMIT 1'
        );
        $statement->execute([':name'=>'interpolation_source']);
        $revision = $statement->fetchColumn();
        if ($revision === false) throw new RuntimeException('Interpolation source revision is unavailable.');
        return (int) $revision;
    }
}
