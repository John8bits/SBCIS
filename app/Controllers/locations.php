<?php

declare(strict_types=1);

use App\Controllers\LocationController;
use App\Models\LocationDirectory;

require_once __DIR__ . '/../../config/bootstrap.php';

(new LocationController(new LocationDirectory()))->json();
