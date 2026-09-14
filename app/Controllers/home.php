<?php

declare(strict_types=1);

use App\Controllers\HomeController;
use App\Models\LocationDirectory;

require_once __DIR__ . '/../../config/bootstrap.php';

return (new HomeController(new LocationDirectory()))->index();
