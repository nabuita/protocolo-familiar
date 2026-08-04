<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\Uuid;
use PDO;

final readonly class ProtocoloModel
{
    public function __construct(private PDO $pdo) {}

    /** @return list<array<string, mixed>> */
    public function all(): array
    {
        return $this->pdo->query('SELECT id, codigo, empresa_nombre, titulo, estado, version, updated_at FROM protocolo_familiar ORDER BY updated_at DESC')->fetchAll();
    }

    /** @return array<string, mixed> */
    public function find(string $id): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM protocolo_familiar WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        if (!is_array($row)) { throw new \RuntimeException('Protocolo no encontrado.'); }
        $row['secciones'] = $this->sections($id);
        $row['firmas'] = $this->signatures($id);
        return $row;
    }

    /** @param list<array{clave:string,titulo:string,contenido:string,orden:int}> $sections */
    public function create(string $codigo, string $empresa, string $titulo, string $descripcion, array $sections, string $actor): string
    {
        $id = Uuid::v4();
        $this->pdo->beginTransaction();
        try {
            $this->pdo->prepare('INSERT INTO protocolo_familiar (id,codigo,empresa_nombre,titulo,descripcion,estado,version,created_at,updated_at,created_by,updated_by) VALUES (:id,:codigo,:empresa,:titulo,:descripcion,"borrador",1,UTC_TIMESTAMP(6),UTC_TIMESTAMP(6),:created_by,:updated_by)')->execute(['id' => $id, 'codigo' => $codigo, 'empresa' => $empresa, 'titulo' => $titulo, 'descripcion' => $descripcion, 'created_by' => $actor, 'updated_by' => $actor]);
            $this->replaceSections($id, $sections, $actor);
            $this->recordVersion($id, 1, $actor, 'creado');
            $this->pdo->commit();
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
        return $id;
    }

    /** @param list<array{clave:string,titulo:string,contenido:string,orden:int}> $sections */
    public function updateSections(string $id, array $sections, string $actor): void
    {
        $this->pdo->beginTransaction();
        try {
            $version = (int) $this->pdo->query('SELECT version FROM protocolo_familiar WHERE id = ' . $this->pdo->quote($id))->fetchColumn() + 1;
            $this->pdo->prepare('UPDATE protocolo_familiar SET version=:version, updated_at=UTC_TIMESTAMP(6), updated_by=:actor WHERE id=:id')->execute(['version' => $version, 'actor' => $actor, 'id' => $id]);
            $this->replaceSections($id, $sections, $actor);
            $this->recordVersion($id, $version, $actor, 'secciones_actualizadas');
            $this->pdo->commit();
        } catch (\Throwable $e) { $this->pdo->rollBack(); throw $e; }
    }

    public function changeState(string $id, string $estado, string $actor): void
    {
        $version = (int) $this->pdo->query('SELECT version FROM protocolo_familiar WHERE id = ' . $this->pdo->quote($id))->fetchColumn() + 1;
        $this->pdo->prepare('UPDATE protocolo_familiar SET estado=:estado, version=:version, updated_at=UTC_TIMESTAMP(6), updated_by=:actor WHERE id=:id')->execute(['estado' => $estado, 'version' => $version, 'actor' => $actor, 'id' => $id]);
        $this->recordVersion($id, $version, $actor, 'estado_actualizado');
    }

    public function sign(string $id, string $nombre, string $cargo, string $actor): void
    {
        $version = (int) $this->pdo->query('SELECT version FROM protocolo_familiar WHERE id = ' . $this->pdo->quote($id))->fetchColumn();
        $hash = hash('sha256', $id . '|' . $version . '|' . $nombre . '|' . $cargo);
        $this->pdo->prepare('INSERT INTO protocolo_familiar_firma (id, protocolo_id, protocolo_version, firmante_nombre, firmante_cargo, firma_hash, firmado_at, created_by) VALUES (:id,:protocolo_id,:version,:nombre,:cargo,:hash,UTC_TIMESTAMP(6),:actor)')->execute(['id' => Uuid::v4(), 'protocolo_id' => $id, 'version' => $version, 'nombre' => $nombre, 'cargo' => $cargo, 'hash' => $hash, 'actor' => $actor]);
    }

    /** @return list<array<string,mixed>> */
    private function sections(string $id): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM protocolo_familiar_seccion WHERE protocolo_id = :id ORDER BY orden, titulo');
        $stmt->execute(['id' => $id]);
        return $stmt->fetchAll();
    }

    /** @return list<array<string,mixed>> */
    private function signatures(string $id): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM protocolo_familiar_firma WHERE protocolo_id = :id ORDER BY firmado_at DESC');
        $stmt->execute(['id' => $id]);
        return $stmt->fetchAll();
    }

    /** @param list<array{clave:string,titulo:string,contenido:string,orden:int}> $sections */
    private function replaceSections(string $id, array $sections, string $actor): void
    {
        $this->pdo->prepare('DELETE FROM protocolo_familiar_seccion WHERE protocolo_id = :id')->execute(['id' => $id]);
        $stmt = $this->pdo->prepare('INSERT INTO protocolo_familiar_seccion (id,protocolo_id,clave,titulo,contenido,orden,created_at,updated_at,created_by,updated_by) VALUES (:id,:protocolo_id,:clave,:titulo,:contenido,:orden,UTC_TIMESTAMP(6),UTC_TIMESTAMP(6),:created_by,:updated_by)');
        foreach ($sections as $section) {
            $stmt->execute(['id' => Uuid::v4(), 'protocolo_id' => $id, 'clave' => $section['clave'], 'titulo' => $section['titulo'], 'contenido' => $section['contenido'], 'orden' => $section['orden'], 'created_by' => $actor, 'updated_by' => $actor]);
        }
    }

    private function recordVersion(string $id, int $version, string $actor, string $action): void
    {
        $snapshot = json_encode($this->find($id), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        $this->pdo->prepare('INSERT INTO protocolo_familiar_version (protocolo_id, version, snapshot_json, snapshot_hash, created_at, created_by) VALUES (:id,:version,:snapshot,:hash,UTC_TIMESTAMP(6),:actor)')->execute(['id' => $id, 'version' => $version, 'snapshot' => $snapshot, 'hash' => hash('sha256', $snapshot), 'actor' => $actor]);
        $this->pdo->prepare('INSERT INTO protocolo_familiar_auditoria (protocolo_id, version, accion, actor_id, detalle_json, created_at) VALUES (:id,:version,:accion,:actor,:detalle,UTC_TIMESTAMP(6))')->execute(['id' => $id, 'version' => $version, 'accion' => $action, 'actor' => $actor, 'detalle' => '{}']);
    }
}
