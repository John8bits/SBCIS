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
            $counts[$table] = (int) $this->database
                ->query('SELECT COUNT(*) FROM ' . $table)
                ->fetchColumn();
        }

        return $counts;
    }

    /** @return resource */
    public function prepare(string $dataset)
    {
        return sbcis_prepare_export($this->database, $dataset);
    }
}
