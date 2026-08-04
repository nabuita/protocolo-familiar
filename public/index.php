<?php

declare(strict_types=1);

use App\Core\App;
use App\Core\Config;

require dirname(__DIR__) . '/app/bootstrap.php';

$config = Config::load(dirname(__DIR__));
(new App($config))->run($_SERVER['REQUEST_METHOD'] ?? 'GET', $_SERVER['REQUEST_URI'] ?? '/');
