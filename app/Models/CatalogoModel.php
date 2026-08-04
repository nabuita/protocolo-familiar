<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\Uuid;
use PDO;
use RuntimeException;

final readonly class CatalogoModel
{
    public function __construct(private PDO $pdo) {}

    /** @return list<array<string, mixed>> */
    public function all(): array
    {
        return $this->pdo->query('SELECT codigo, nombre, descripcion, tipo, orden FROM pf_catalogos WHERE activo = 1 ORDER BY orden, nombre')->fetchAll();
    }

    /** @return array<string, mixed> */
    public function find(string $codigo): array
    {
        $stmt = $this->pdo->prepare('SELECT codigo, nombre, descripcion, tipo, orden FROM pf_catalogos WHERE codigo = :codigo AND activo = 1');
        $stmt->execute(['codigo' => $codigo]);
        $row = $stmt->fetch();
        if (!is_array($row)) {
            throw new RuntimeException('Catalogo no encontrado.');
        }
        return $row;
    }

    /** @return list<array<string, mixed>> */
    public function items(string $catalogCode, ?string $parentCode = null): array
    {
        $sql = 'SELECT i.codigo, i.nombre, i.valor, i.descripcion, i.orden, p.codigo AS parent_codigo
                FROM pf_catalogo_items i
                INNER JOIN pf_catalogos c ON c.id = i.catalogo_id
                LEFT JOIN pf_catalogo_items p ON p.id = i.parent_id
                WHERE c.codigo = :catalogo AND c.activo = 1 AND i.activo = 1';
        $params = ['catalogo' => $catalogCode];
        if ($parentCode !== null && $parentCode !== '') {
            $sql .= ' AND p.codigo = :parent';
            $params['parent'] = $parentCode;
        }
        $sql .= ' ORDER BY i.orden, i.nombre';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /** @return array<string, list<array<string, mixed>>> */
    public function allItemsByCatalog(): array
    {
        $items = [];
        foreach ($this->all() as $catalog) {
            $items[(string) $catalog['codigo']] = $this->items((string) $catalog['codigo']);
        }
        return $items;
    }

    public function create(string $codigo, string $nombre, string $descripcion, string $tipo, int $orden, string $actor): void
    {
        $stmt = $this->pdo->prepare('INSERT INTO pf_catalogos (id, codigo, nombre, descripcion, tipo, orden, activo, created_by, updated_by, created_at, updated_at) VALUES (:id, :codigo, :nombre, :descripcion, :tipo, :orden, 1, :created_by, :updated_by, UTC_TIMESTAMP(6), UTC_TIMESTAMP(6))');
        $stmt->execute(['id' => Uuid::v4(), 'codigo' => $codigo, 'nombre' => $nombre, 'descripcion' => $descripcion ?: null, 'tipo' => $tipo, 'orden' => $orden, 'created_by' => $actor, 'updated_by' => $actor]);
    }

    public function update(string $codigo, string $nombre, string $descripcion, string $tipo, int $orden, string $actor): void
    {
        $stmt = $this->pdo->prepare('UPDATE pf_catalogos SET nombre = :nombre, descripcion = :descripcion, tipo = :tipo, orden = :orden, updated_by = :actor, updated_at = UTC_TIMESTAMP(6) WHERE codigo = :codigo AND activo = 1');
        $stmt->execute(['codigo' => $codigo, 'nombre' => $nombre, 'descripcion' => $descripcion ?: null, 'tipo' => $tipo, 'orden' => $orden, 'actor' => $actor]);
        if ($stmt->rowCount() === 0) {
            $this->find($codigo);
        }
    }

    public function deactivate(string $codigo, string $actor): void
    {
        $stmt = $this->pdo->prepare('UPDATE pf_catalogos SET activo = 0, updated_by = :actor, updated_at = UTC_TIMESTAMP(6) WHERE codigo = :codigo AND activo = 1');
        $stmt->execute(['codigo' => $codigo, 'actor' => $actor]);
    }

    public function createItem(string $catalogCode, ?string $parentCode, string $codigo, string $nombre, string $valor, string $descripcion, int $orden, string $actor): void
    {
        $catalogId = $this->idForCatalog($catalogCode);
        $parentId = $parentCode ? $this->idForItem($parentCode) : null;
        $stmt = $this->pdo->prepare('INSERT INTO pf_catalogo_items (id, catalogo_id, parent_id, codigo, nombre, valor, descripcion, orden, activo, metadata_json, created_by, updated_by, created_at, updated_at) VALUES (:id, :catalogo_id, :parent_id, :codigo, :nombre, :valor, :descripcion, :orden, 1, NULL, :created_by, :updated_by, UTC_TIMESTAMP(6), UTC_TIMESTAMP(6))');
        $stmt->execute(['id' => Uuid::v4(), 'catalogo_id' => $catalogId, 'parent_id' => $parentId, 'codigo' => $codigo, 'nombre' => $nombre, 'valor' => $valor, 'descripcion' => $descripcion ?: null, 'orden' => $orden, 'created_by' => $actor, 'updated_by' => $actor]);
    }

    /** @return array<string, mixed> */
    public function findItem(string $catalogCode, string $itemCode): array
    {
        $stmt = $this->pdo->prepare('SELECT i.codigo, i.nombre, i.valor, i.descripcion, i.orden, p.codigo AS parent_codigo
                FROM pf_catalogo_items i
                INNER JOIN pf_catalogos c ON c.id = i.catalogo_id
                LEFT JOIN pf_catalogo_items p ON p.id = i.parent_id
                WHERE c.codigo = :catalogo AND i.codigo = :item AND c.activo = 1 AND i.activo = 1');
        $stmt->execute(['catalogo' => $catalogCode, 'item' => $itemCode]);
        $row = $stmt->fetch();
        if (!is_array($row)) {
            throw new RuntimeException('Item no encontrado.');
        }
        return $row;
    }

    public function updateItem(string $catalogCode, string $itemCode, ?string $parentCode, string $nombre, string $valor, string $descripcion, int $orden, string $actor): void
    {
        $parentId = $parentCode ? $this->idForItem($parentCode) : null;
        $stmt = $this->pdo->prepare('UPDATE pf_catalogo_items i
                INNER JOIN pf_catalogos c ON c.id = i.catalogo_id
                SET i.parent_id = :parent_id, i.nombre = :nombre, i.valor = :valor, i.descripcion = :descripcion, i.orden = :orden, i.updated_by = :actor, i.updated_at = UTC_TIMESTAMP(6)
                WHERE c.codigo = :catalogo AND i.codigo = :item AND c.activo = 1 AND i.activo = 1');
        $stmt->execute(['catalogo' => $catalogCode, 'item' => $itemCode, 'parent_id' => $parentId, 'nombre' => $nombre, 'valor' => $valor, 'descripcion' => $descripcion ?: null, 'orden' => $orden, 'actor' => $actor]);
        if ($stmt->rowCount() === 0) {
            $this->findItem($catalogCode, $itemCode);
        }
    }

    public function deactivateItem(string $catalogCode, string $itemCode, string $actor): void
    {
        $stmt = $this->pdo->prepare('UPDATE pf_catalogo_items i
                INNER JOIN pf_catalogos c ON c.id = i.catalogo_id
                SET i.activo = 0, i.updated_by = :actor, i.updated_at = UTC_TIMESTAMP(6)
                WHERE c.codigo = :catalogo AND i.codigo = :item AND c.activo = 1 AND i.activo = 1');
        $stmt->execute(['catalogo' => $catalogCode, 'item' => $itemCode, 'actor' => $actor]);
    }

    /** @return list<array{clave:string,titulo:string,contenido:string,orden:int}> */
    public function recommendedProtocolSections(): array
    {
        $sections = [];
        foreach ($this->items('ESTRUCTURA_CARPETAS') as $item) {
            $sections[] = [
                'clave' => strtolower((string) $item['valor']),
                'titulo' => str_replace('_', ' ', (string) $item['nombre']),
                'contenido' => 'Pendiente por documentar.',
                'orden' => (int) $item['orden'],
            ];
        }
        return $sections;
    }

    private function idForCatalog(string $code): string
    {
        $stmt = $this->pdo->prepare('SELECT id FROM pf_catalogos WHERE codigo = :codigo AND activo = 1');
        $stmt->execute(['codigo' => $code]);
        $id = $stmt->fetchColumn();
        if (!is_string($id) || $id === '') {
            throw new RuntimeException('Catalogo no encontrado.');
        }
        return $id;
    }

    private function idForItem(string $code): string
    {
        $stmt = $this->pdo->prepare('SELECT id FROM pf_catalogo_items WHERE codigo = :codigo AND activo = 1');
        $stmt->execute(['codigo' => $code]);
        $id = $stmt->fetchColumn();
        if (!is_string($id) || $id === '') {
            throw new RuntimeException('Item padre no encontrado.');
        }
        return $id;
    }
}
