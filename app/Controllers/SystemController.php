<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;

final class SystemController extends Controller
{
    public function __construct(...$args)
    {
        $this->database = array_pop($args);
        parent::__construct(...$args);
    }

    private Database $database;

    public function status(): void
    {
        $ok = true;
        try {
            $this->database->pdo()->query('SELECT 1');
        } catch (\Throwable) {
            $ok = false;
        }
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => $ok, 'app' => 'protocolo-familiar-mvc-simple'], JSON_THROW_ON_ERROR);
    }
}
