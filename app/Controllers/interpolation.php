<?php

declare(strict_types=1);

use App\Controllers\InterpolationController;

require_once __DIR__ . '/../../config/bootstrap.php';

(new InterpolationController())->json($_GET, $_SERVER['REQUEST_METHOD'] ?? 'GET');
