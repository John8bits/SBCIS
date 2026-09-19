<?php

declare(strict_types=1);

namespace App\Services;

use PDO;

final class DataExportService
{
    private PDO $database;
    private ?array $locationDirectory;

    public function __construct(PDO $database, ?array $locationDirectory = null)
    {
        $this->database = $database;
        $this->locationDirectory = $locationDirectory;
        require_once dirname(__DIR__) . '/Models/data_export.php';
    }

    public function datasets(): array
    {
        return sbcis_export_queries();
    }

    public function counts(): array
    {
        $counts = [];

        foreach ($this->datasets() as $dataset => $query) {
            if (isset($this->locationExports()[$dataset])) {
                $counts[$dataset] = ['total' => count($this->locationExports()[$dataset]['rows'])];
                continue;
            }
            $counts[$dataset] = ['total' => (int) $this->database
                ->query('SELECT COUNT(*) FROM (' . $query . ') AS export_rows')
                ->fetchColumn()];
        }

        foreach (['boreholes', 'soil_layers'] as $dataset) {
            $sourceCounts = $this->database->query('SELECT record_source, COUNT(*) AS total FROM (' .
                $this->datasets()[$dataset] . ') AS export_rows GROUP BY record_source')->fetchAll();
            $counts[$dataset]['field'] = 0;
            $counts[$dataset]['sample'] = 0;
            foreach ($sourceCounts as $row) {
                if (isset($counts[$dataset][$row['record_source']]))
                    $counts[$dataset][$row['record_source']] = (int) $row['total'];
            }
        }

        return $counts;
    }

    /** @return resource */
    public function prepare(string $dataset)
    {
        $locations = $this->locationExports();
        if (isset($locations[$dataset])) {
            return sbcis_prepare_rows_csv($locations[$dataset]['rows'], $locations[$dataset]['columns']);
        }
        return sbcis_prepare_export($this->database, $dataset);
    }

    private function locationExports(): array
    {
        if ($this->locationDirectory === null) return [];
        return [
            'municipalities' => [
                'rows' => $this->locationDirectory['municipalities'],
                'columns' => [
                    'psgc_code' => 'code',
                    'municipality_name' => 'name',
                    'boundary_id' => 'boundaryId',
                ],
            ],
            'barangays' => [
                'rows' => $this->locationDirectory['barangays'],
                'columns' => [
                    'psgc_code' => 'code',
                    'barangay_name' => 'name',
                    'municipality_psgc_code' => 'municipalityCode',
                    'municipality_name' => 'municipalityName',
                    'boundary_id' => 'boundaryId',
                ],
            ],
        ];
    }
}
