<?php

declare(strict_types=1);

use App\Controllers\AuthController;

require_once __DIR__ . '/../../config/bootstrap.php';

(new AuthController())->logout();
