<?php

declare(strict_types=1);

namespace App\Core;

use RuntimeException;

final class Csrf
{
    public function token(): string
    {
        if (!is_string($_SESSION['csrf_token'] ?? null)) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    public function assert(mixed $token): void
    {
        if (!is_string($token) || !hash_equals($this->token(), $token)) {
            throw new RuntimeException('Token CSRF inválido.');
        }
    }
}
