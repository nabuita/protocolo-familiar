<?php

declare(strict_types=1);

namespace App\Core;

final readonly class Auth
{
    public function __construct(private Config $config) {}

    public function check(): bool
    {
        return is_string($_SESSION['actor'] ?? null) && $_SESSION['actor'] !== '';
    }

    public function actor(): ?string
    {
        return $this->check() ? (string) $_SESSION['actor'] : null;
    }

    public function attempt(string $username, string $password): bool
    {
        if (!hash_equals($this->config->get('WEB_USERNAME'), $username)) {
            return false;
        }

        if (!password_verify($password, $this->config->get('WEB_PASSWORD_HASH'))) {
            return false;
        }

        session_regenerate_id(true);
        $_SESSION['actor'] = $username;
        return true;
    }

    public function logout(): void
    {
        $_SESSION = [];
        session_destroy();
    }
}
