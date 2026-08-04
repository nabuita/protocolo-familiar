<?php

declare(strict_types=1);

namespace App\Core;

use RuntimeException;

final readonly class View
{
    public function __construct(private string $root) {}

    /** @param array<string, mixed> $data */
    public function render(string $view, array $data = [], string $layout = 'layout'): string
    {
        $content = $this->partial($view, $data);
        return $this->partial($layout, $data + ['content' => $content]);
    }

    /** @param array<string, mixed> $data */
    public function partial(string $view, array $data = []): string
    {
        if (preg_match('/^[a-z0-9_\/-]+$/', $view) !== 1) {
            throw new RuntimeException('Vista inválida.');
        }

        $path = $this->root . '/app/Views/' . $view . '.php';
        if (!is_file($path)) {
            throw new RuntimeException('Vista no encontrada.');
        }

        ob_start();
        extract($data, EXTR_SKIP);
        require $path;
        return (string) ob_get_clean();
    }
}
