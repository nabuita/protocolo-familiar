<?php

declare(strict_types=1);

namespace App\Core;

abstract class Controller
{
    public function __construct(
        protected readonly Config $config,
        protected readonly View $view,
        protected readonly Auth $auth,
        protected readonly Csrf $csrf,
    ) {}

    /** @param array<string, mixed> $data */
    protected function html(string $view, array $data = [], int $status = 200): void
    {
        http_response_code($status);
        echo $this->view->render($view, $data + $this->shared());
    }

    protected function redirect(string $path): void
    {
        header('Location: ' . $this->basePath() . $path, true, 303);
    }

    protected function basePath(): string
    {
        return rtrim($this->config->get('APP_BASE_PATH', '/public'), '/');
    }

    /** @return array<string, mixed> */
    protected function shared(): array
    {
        return [
            'basePath' => $this->basePath(),
            'actor' => $this->auth->actor(),
            'csrfToken' => $this->csrf->token(),
            'flash' => $this->pullFlash(),
        ];
    }

    protected function flash(string $type, string $message): void
    {
        $_SESSION['flash'] = ['type' => $type, 'message' => $message];
    }

    /** @return array{type:string,message:string}|null */
    private function pullFlash(): ?array
    {
        $flash = $_SESSION['flash'] ?? null;
        unset($_SESSION['flash']);
        return is_array($flash) && is_string($flash['type'] ?? null) && is_string($flash['message'] ?? null) ? $flash : null;
    }
}
