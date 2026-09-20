<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\LocationDirectory;
use Throwable;

final class LocationController
{
    private LocationDirectory $locations;

    public function __construct(LocationDirectory $locations)
    {
        $this->locations = $locations;
    }

    public function json(string $method = 'GET'): void
    {
        header('Content-Type: application/json; charset=UTF-8');
        header('X-Content-Type-Options: nosniff');

        if ($method !== 'GET') {
            header('Allow: GET');
            http_response_code(405);
            echo json_encode(['error'=>'Unsupported request method.']);
            return;
        }

        try {
            header('Cache-Control: public, max-age=300');
            echo json_encode($this->locations->all(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        } catch (Throwable $error) {
            error_log('Locations endpoint: ' . $error->getMessage());
            http_response_code(503);
            echo json_encode(['error' => 'Location lists are unavailable. Please retry.']);
        }
    }
}
