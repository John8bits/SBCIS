<?php

declare(strict_types=1);

use App\Controllers\BoreholeController;

require_once __DIR__ . '/../../config/bootstrap.php';

(new BoreholeController())->json($_GET, $_SERVER['REQUEST_METHOD'] ?? 'GET');
