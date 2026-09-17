<?php

declare(strict_types=1);

namespace App\Services;

use PDO;

final class DataExportService
{
    private PDO $database;

    public function __construct(PDO $database)
    {
        $this->database = $database;
        require_once dirname(__DIR__) . '/Models/data_export.php';
    }

    public function datasets(): array
    {
        return sbcis_export_queries();
    }

    public function counts(): array
    {
        $counts = [];

        foreach (array_keys($this->datasets()) as $table) {
            $counts[$table] = ['total' => (int) $this->database
                ->query('SELECT COUNT(*) FROM ' . $table)
                ->fetchColumn()];
        }

        // Sample imports are valid database rows and remain in every backup,
        // but must not be presented as field records. Detect provenance from
        // the same markers used by the interpolation configuration.
        $sampleBoreholes = (int) $this->database->query("
            SELECT COUNT(*)
            FROM boreholes b
            WHERE UPPER(b.borehole_code) LIKE 'SYNTH-DEMO-%'
               OR EXISTS (
                    SELECT 1 FROM soil_layers sl
                    WHERE sl.borehole_id = b.borehole_id
                      AND (UPPER(COALESCE(sl.soil_type, '')) LIKE '%SYNTHETIC SAMPLE%'
                        OR UPPER(COALESCE(sl.soil_description, '')) LIKE '%ARTIFICIAL DEMO%'
                        OR UPPER(COALESCE(sl.soil_description, '')) LIKE '%NOT MEASURED%'
                        OR UPPER(COALESCE(sl.soil_description, '')) LIKE '%NOT FOR ENGINEERING USE%')
               )
        ")->fetchColumn();
        $sampleLayers = (int) $this->database->query("
            SELECT COUNT(*)
            FROM soil_layers sl
            INNER JOIN boreholes b ON b.borehole_id = sl.borehole_id
            WHERE UPPER(b.borehole_code) LIKE 'SYNTH-DEMO-%'
               OR UPPER(COALESCE(sl.soil_type, '')) LIKE '%SYNTHETIC SAMPLE%'
               OR UPPER(COALESCE(sl.soil_description, '')) LIKE '%ARTIFICIAL DEMO%'
               OR UPPER(COALESCE(sl.soil_description, '')) LIKE '%NOT MEASURED%'
               OR UPPER(COALESCE(sl.soil_description, '')) LIKE '%NOT FOR ENGINEERING USE%'
        ")->fetchColumn();

        foreach (['boreholes' => $sampleBoreholes, 'soil_layers' => $sampleLayers] as $table => $sample) {
            $counts[$table]['sample'] = $sample;
            $counts[$table]['field'] = max(0, $counts[$table]['total'] - $sample);
        }

        return $counts;
    }

    /** @return resource */
    public function prepare(string $dataset)
    {
        return sbcis_prepare_export($this->database, $dataset);
    }
}
