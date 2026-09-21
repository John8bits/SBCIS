<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Database\Connection;
use App\Models\GeotechnicalRepository;
use App\Services\BoundaryService;
use App\Support\AdminSession;
use InvalidArgumentException;
use Throwable;

final class BoreholeController
{
    public function json(array $query, string $method): void
    {
        $administrator = AdminSession::isLoggedIn();
        header('Content-Type: application/json; charset=UTF-8');
        header('Cache-Control: ' . ($administrator ? 'private, no-store' : 'public, max-age=30'));
        if ($method !== 'GET') {
            header('Allow: GET');
            $this->respond(['error'=>'Unsupported request method.'], 405);
            return;
        }
        try {
            if (array_diff(array_keys($query), ['bbox','limit','search'])) throw new InvalidArgumentException('Unsupported map parameter.');
            $parts = array_map('trim', explode(',', (string) ($query['bbox'] ?? '')));
            if (count($parts) !== 4 || array_filter($parts, static fn($value) => !is_numeric($value))) {
                throw new InvalidArgumentException('A four-number bounding box is required.');
            }
            $limit = filter_var($query['limit'] ?? 1000, FILTER_VALIDATE_INT, ['options'=>['min_range'=>1,'max_range'=>2000]]);
            if ($limit === false) throw new InvalidArgumentException('Map limit must be between 1 and 2000.');
            $records = (new GeotechnicalRepository(Connection::get()))->mapBoreholesWindow(array_map('floatval', $parts), $limit + 1, (string) ($query['search'] ?? ''));
            $boundary = new BoundaryService();
            if (!$administrator) {
                $records = array_values(array_filter($records, static fn(array $record): bool =>
                    $boundary->contains($record['latitude'] ?? null, $record['longitude'] ?? null)));
            }
            $truncated = count($records) > $limit;
            if ($truncated) $records = array_slice($records, 0, $limit);
            $this->respond(['records'=>$records, 'count'=>count($records), 'limit'=>$limit, 'truncated'=>$truncated]);
        } catch (InvalidArgumentException $error) {
            $this->respond(['error'=>$error->getMessage()], 400);
        } catch (Throwable $error) {
            error_log('Borehole map endpoint: ' . $error->getMessage());
            $this->respond(['error'=>'Borehole records are temporarily unavailable.'], 503);
        }
    }

    private function respond(array $data, int $status = 200): void
    {
        http_response_code($status);
        echo json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    }
}
