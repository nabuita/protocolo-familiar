<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;

final class AuthController extends Controller
{
    public function form(): void
    {
        $this->html('auth/login', ['error' => null]);
    }

    /** @param array<string,mixed> $post */
    public function login(array $post): void
    {
        $this->csrf->assert($post['csrf_token'] ?? null);
        $username = is_string($post['username'] ?? null) ? trim($post['username']) : '';
        $password = is_string($post['password'] ?? null) ? $post['password'] : '';
        if ($this->auth->attempt($username, $password)) {
            $this->redirect('/protocolo-familiar');
            return;
        }
        $this->html('auth/login', ['error' => 'Credenciales inválidas.'], 422);
    }

    /** @param array<string,mixed> $post */
    public function logout(array $post): void
    {
        $this->csrf->assert($post['csrf_token'] ?? null);
        $this->auth->logout();
        $this->redirect('/login');
    }
}
