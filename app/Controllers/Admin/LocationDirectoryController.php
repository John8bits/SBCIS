<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Models\LocationDirectory;
use Throwable;

final class LocationDirectoryController
{
    private LocationDirectory $locations;

    public function __construct(LocationDirectory $locations)
    {
        $this->locations = $locations;
    }

    public function index(string $requestedKind): array
    {
        $kind = $requestedKind === 'barangays' ? 'barangays' : 'municipalities';
        $result = [
            'kind' => $kind,
            'directory' => null,
            'rows' => [],
            'error' => null,
        ];

        try {
            $result['directory'] = $this->locations->all();
            $result['rows'] = $result['directory'][$kind];
        } catch (Throwable $error) {
            error_log('Admin locations: ' . $error->getMessage());
            $result['error'] = 'The location directory is unavailable. Please reload this page.';
        }

        return $result;
    }
}
