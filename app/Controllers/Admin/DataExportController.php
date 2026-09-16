<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Database\Connection;
use App\Services\DataExportService;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

final class DataExportController
{
    public function index(array $query): array
    {
        $result = ['counts' => [], 'available' => false, 'error' => null];

        try {
            $database = Connection::get();
            if (!$database) {
                throw new RuntimeException('Database unavailable.');
            }

            $exports = new DataExportService($database);
            if (isset($query['download'])) {
                $dataset = is_string($query['download']) ? $query['download'] : '';
                $this->download($exports, $dataset);
            }

            $result['counts'] = $exports->counts();
            $result['available'] = true;
        } catch (InvalidArgumentException $error) {
            http_response_code(400);
            $result['error'] = $error->getMessage();
        } catch (Throwable $error) {
            error_log('Export: ' . $error->getMessage());
            http_response_code(503);
            $result['error'] = 'Downloads are unavailable right now. Please check the database connection and try again.';
        }

        return $result;
    }

    private function download(DataExportService $exports, string $dataset): void
    {
        if ($dataset !== 'backup' && !array_key_exists($dataset, $exports->datasets())) {
            throw new InvalidArgumentException('Choose one of the downloads below.');
        }

        $stream = $exports->prepare($dataset);
        session_write_close();
        $extension = $dataset === 'backup' ? '.sql' : '.csv';
        header('Content-Type: ' . ($dataset === 'backup' ? 'application/sql' : 'text/csv') . '; charset=UTF-8');
        header('Content-Disposition: attachment; filename="sbcis_' . $dataset . '_' . gmdate('Y-m-d_His') . $extension . '"');
        header('X-Content-Type-Options: nosniff');
        fpassthru($stream);
        fclose($stream);
        exit;
    }
}
