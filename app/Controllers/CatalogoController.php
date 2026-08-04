<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Config;
use App\Core\Controller;
use App\Core\Csrf;
use App\Core\View;
use App\Models\CatalogoModel;
use RuntimeException;

final class CatalogoController extends Controller
{
    public function __construct(
        Config $config,
        View $view,
        Auth $auth,
        Csrf $csrf,
        private readonly CatalogoModel $catalogos,
    ) {
        parent::__construct($config, $view, $auth, $csrf);
    }

    public function index(): void
    {
        $this->html('catalogos/index', ['catalogos' => $this->catalogos->all(), 'itemsByCatalog' => $this->catalogos->allItemsByCatalog()]);
    }

    public function create(): void
    {
        $this->html('catalogos/create', ['error' => null, 'old' => []]);
    }

    public function edit(string $catalogCode): void
    {
        $this->html('catalogos/edit', ['catalogo' => $this->catalogos->find($catalogCode), 'error' => null]);
    }

    /** @param array<string,mixed> $post */
    public function store(array $post): void
    {
        $this->csrf->assert($post['csrf_token'] ?? null);
        try {
            $this->catalogos->create($this->code($post['codigo'] ?? null), $this->text($post['nombre'] ?? null), $this->optional($post['descripcion'] ?? null), $this->code($post['tipo'] ?? null), $this->int($post['orden'] ?? null), (string) $this->auth->actor());
            $this->flash('success', 'Lista creada.');
            $this->redirect('/listas');
        } catch (\Throwable $e) {
            $this->html('catalogos/create', ['error' => $e->getMessage(), 'old' => $post], 422);
        }
    }

    /** @param array<string,mixed> $post */
    public function update(string $catalogCode, array $post): void
    {
        $this->csrf->assert($post['csrf_token'] ?? null);
        try {
            $this->catalogos->update($catalogCode, $this->text($post['nombre'] ?? null), $this->optional($post['descripcion'] ?? null), $this->code($post['tipo'] ?? null), $this->int($post['orden'] ?? null), (string) $this->auth->actor());
            $this->flash('success', 'Lista actualizada.');
            $this->redirect('/listas');
        } catch (\Throwable $e) {
            $this->html('catalogos/edit', ['catalogo' => $post + ['codigo' => $catalogCode], 'error' => $e->getMessage()], 422);
        }
    }

    /** @param array<string,mixed> $post */
    public function delete(string $catalogCode, array $post): void
    {
        $this->csrf->assert($post['csrf_token'] ?? null);
        $this->catalogos->deactivate($catalogCode, (string) $this->auth->actor());
        $this->flash('success', 'Lista desactivada.');
        $this->redirect('/listas');
    }

    public function createItem(string $catalogCode): void
    {
        $this->html('catalogos/create_item', ['catalogCode' => $catalogCode, 'parentOptions' => $this->parentOptions($catalogCode), 'error' => null, 'old' => []]);
    }

    public function editItem(string $catalogCode, string $itemCode): void
    {
        $this->html('catalogos/edit_item', ['catalogCode' => $catalogCode, 'parentOptions' => $this->parentOptions($catalogCode), 'item' => $this->catalogos->findItem($catalogCode, $itemCode), 'error' => null]);
    }

    /** @param array<string,mixed> $post */
    public function storeItem(string $catalogCode, array $post): void
    {
        $this->csrf->assert($post['csrf_token'] ?? null);
        try {
            $parent = is_string($post['parent_codigo'] ?? null) && $post['parent_codigo'] !== '' ? $post['parent_codigo'] : null;
            $this->catalogos->createItem($catalogCode, $parent, $this->code($post['codigo'] ?? null), $this->text($post['nombre'] ?? null), $this->text($post['valor'] ?? null), $this->optional($post['descripcion'] ?? null), $this->int($post['orden'] ?? null), (string) $this->auth->actor());
            $this->flash('success', 'Item creado.');
            $this->redirect('/listas');
        } catch (\Throwable $e) {
            $this->html('catalogos/create_item', ['catalogCode' => $catalogCode, 'parentOptions' => $this->parentOptions($catalogCode), 'error' => $e->getMessage(), 'old' => $post], 422);
        }
    }

    /** @param array<string,mixed> $post */
    public function updateItem(string $catalogCode, string $itemCode, array $post): void
    {
        $this->csrf->assert($post['csrf_token'] ?? null);
        try {
            $parent = is_string($post['parent_codigo'] ?? null) && $post['parent_codigo'] !== '' ? $post['parent_codigo'] : null;
            $this->catalogos->updateItem($catalogCode, $itemCode, $parent, $this->text($post['nombre'] ?? null), $this->text($post['valor'] ?? null), $this->optional($post['descripcion'] ?? null), $this->int($post['orden'] ?? null), (string) $this->auth->actor());
            $this->flash('success', 'Item actualizado.');
            $this->redirect('/listas');
        } catch (\Throwable $e) {
            $this->html('catalogos/edit_item', ['catalogCode' => $catalogCode, 'parentOptions' => $this->parentOptions($catalogCode), 'item' => $post + ['codigo' => $itemCode], 'error' => $e->getMessage()], 422);
        }
    }

    /** @param array<string,mixed> $post */
    public function deleteItem(string $catalogCode, string $itemCode, array $post): void
    {
        $this->csrf->assert($post['csrf_token'] ?? null);
        $this->catalogos->deactivateItem($catalogCode, $itemCode, (string) $this->auth->actor());
        $this->flash('success', 'Item desactivado.');
        $this->redirect('/listas');
    }

    /** @return list<array<string,mixed>> */
    private function parentOptions(string $catalogCode): array
    {
        return match ($catalogCode) {
            'TIPO_DOCUMENTO' => $this->catalogos->items('GRUPO_DOCUMENTAL'),
            'SUBCATEGORIA_ACTIVO' => $this->catalogos->items('CATEGORIA_ACTIVO'),
            default => [],
        };
    }

    private function code(mixed $value): string
    {
        $value = $this->text($value);
        if (preg_match('/^[A-Z0-9_]{2,120}$/', $value) !== 1) {
            throw new RuntimeException('Codigo invalido.');
        }
        return $value;
    }

    private function text(mixed $value): string
    {
        if (!is_string($value) || trim($value) === '') {
            throw new RuntimeException('Campo obligatorio.');
        }
        return trim($value);
    }

    private function optional(mixed $value): string
    {
        return is_string($value) ? trim($value) : '';
    }

    private function int(mixed $value): int
    {
        return is_string($value) && ctype_digit($value) ? (int) $value : 0;
    }
}
