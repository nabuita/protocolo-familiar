<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\Uuid;
use PDO;
use RuntimeException;

final readonly class FamiliaModel
{
    public function __construct(private PDO $pdo)
    {
        $this->ensureSchema();
    }

    /** @return list<array<string, mixed>> */
    public function all(): array
    {
        return $this->pdo->query('SELECT * FROM protocolo_familia_personas WHERE activo = 1 ORDER BY CAST(SUBSTRING(codigo, 5) AS UNSIGNED), created_at')->fetchAll();
    }

    /** @param array<string, mixed> $data */
    public function create(array $data, string $actor): array
    {
        $id = Uuid::v4();
        $codigo = $this->nextCode();
        $stmt = $this->pdo->prepare('INSERT INTO protocolo_familia_personas (
            id, codigo, nombre_completo, tipo_documento, numero_documento, fecha_nacimiento, ciudad_expedicion,
            nacionalidad, telefono, correo, direccion_residencia, tipo_vinculo, generacion, edad, estado_civil, ano_matrimonio_convivencia,
            fecha_matrimonio_convivencia, regimen_patrimonial, conyuge_companero,
            tiene_capitulaciones, sociedad_conyugal_liquidada, numero_hijos, trabaja_empresa_familiar,
            empresa_donde_trabaja, cargo, tipo_vinculacion_laboral, es_accionista, participa_decisiones,
            rol_gobierno_familiar, tiene_poderes, tiene_testamento, hijos_registrados, alertas_observaciones,
            observaciones, activo,
            created_at, updated_at, created_by, updated_by
        ) VALUES (
            :id, :codigo, :nombre_completo, :tipo_documento, :numero_documento, :fecha_nacimiento, :ciudad_expedicion,
            :nacionalidad, :telefono, :correo, :direccion_residencia, :tipo_vinculo, :generacion, :edad, :estado_civil, :ano_matrimonio_convivencia,
            :fecha_matrimonio_convivencia, :regimen_patrimonial, :conyuge_companero,
            :tiene_capitulaciones, :sociedad_conyugal_liquidada, :numero_hijos, :trabaja_empresa_familiar,
            :empresa_donde_trabaja, :cargo, :tipo_vinculacion_laboral, :es_accionista, :participa_decisiones,
            :rol_gobierno_familiar, :tiene_poderes, :tiene_testamento, :hijos_registrados, :alertas_observaciones,
            :observaciones, 1,
            UTC_TIMESTAMP(6), UTC_TIMESTAMP(6), :created_by, :updated_by
        )');
        $stmt->execute($this->params($data) + ['id' => $id, 'codigo' => $codigo, 'created_by' => $actor, 'updated_by' => $actor]);
        return $this->find($id);
    }

    /** @param array<string, mixed> $data */
    public function update(string $id, array $data, string $actor): array
    {
        $stmt = $this->pdo->prepare('UPDATE protocolo_familia_personas SET
            nombre_completo = :nombre_completo,
            tipo_documento = :tipo_documento,
            numero_documento = :numero_documento,
            fecha_nacimiento = :fecha_nacimiento,
            ciudad_expedicion = :ciudad_expedicion,
            nacionalidad = :nacionalidad,
            telefono = :telefono,
            correo = :correo,
            direccion_residencia = :direccion_residencia,
            tipo_vinculo = :tipo_vinculo,
            generacion = :generacion,
            edad = :edad,
            estado_civil = :estado_civil,
            ano_matrimonio_convivencia = :ano_matrimonio_convivencia,
            fecha_matrimonio_convivencia = :fecha_matrimonio_convivencia,
            regimen_patrimonial = :regimen_patrimonial,
            conyuge_companero = :conyuge_companero,
            tiene_capitulaciones = :tiene_capitulaciones,
            sociedad_conyugal_liquidada = :sociedad_conyugal_liquidada,
            numero_hijos = :numero_hijos,
            trabaja_empresa_familiar = :trabaja_empresa_familiar,
            empresa_donde_trabaja = :empresa_donde_trabaja,
            cargo = :cargo,
            tipo_vinculacion_laboral = :tipo_vinculacion_laboral,
            es_accionista = :es_accionista,
            participa_decisiones = :participa_decisiones,
            rol_gobierno_familiar = :rol_gobierno_familiar,
            tiene_poderes = :tiene_poderes,
            tiene_testamento = :tiene_testamento,
            hijos_registrados = :hijos_registrados,
            alertas_observaciones = :alertas_observaciones,
            observaciones = :observaciones,
            updated_at = UTC_TIMESTAMP(6),
            updated_by = :updated_by
            WHERE id = :id AND activo = 1');
        $stmt->execute($this->params($data) + ['id' => $id, 'updated_by' => $actor]);
        return $this->find($id);
    }

    public function deactivate(string $id, string $actor): void
    {
        $stmt = $this->pdo->prepare('UPDATE protocolo_familia_personas SET activo = 0, updated_at = UTC_TIMESTAMP(6), updated_by = :actor WHERE id = :id AND activo = 1');
        $stmt->execute(['id' => $id, 'actor' => $actor]);
    }

    /** @return array<string, mixed> */
    public function find(string $id): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM protocolo_familia_personas WHERE id = :id AND activo = 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        if (!is_array($row)) {
            throw new RuntimeException('Persona no encontrada.');
        }
        return $row;
    }

    private function nextCode(): string
    {
        $next = (int) $this->pdo->query("SELECT COALESCE(MAX(CAST(SUBSTRING(codigo, 5) AS UNSIGNED)), 0) + 1 FROM protocolo_familia_personas")->fetchColumn();
        return sprintf('FAM-%03d', $next);
    }

    private function ensureSchema(): void
    {
        $this->ensureColumn('tipo_documento', 'ALTER TABLE protocolo_familia_personas ADD COLUMN tipo_documento VARCHAR(80) NULL AFTER nombre_completo');
        $this->ensureColumn('numero_documento', 'ALTER TABLE protocolo_familia_personas ADD COLUMN numero_documento VARCHAR(80) NULL AFTER tipo_documento');
        $this->ensureColumn('fecha_nacimiento', 'ALTER TABLE protocolo_familia_personas ADD COLUMN fecha_nacimiento DATE NULL AFTER numero_documento');
        $this->ensureColumn('ciudad_expedicion', 'ALTER TABLE protocolo_familia_personas ADD COLUMN ciudad_expedicion VARCHAR(160) NULL AFTER fecha_nacimiento');
        $this->ensureColumn('nacionalidad', 'ALTER TABLE protocolo_familia_personas ADD COLUMN nacionalidad VARCHAR(120) NULL AFTER ciudad_expedicion');
        $this->ensureColumn('telefono', 'ALTER TABLE protocolo_familia_personas ADD COLUMN telefono VARCHAR(80) NULL AFTER nacionalidad');
        $this->ensureColumn('correo', 'ALTER TABLE protocolo_familia_personas ADD COLUMN correo VARCHAR(160) NULL AFTER telefono');
        $this->ensureColumn('direccion_residencia', 'ALTER TABLE protocolo_familia_personas ADD COLUMN direccion_residencia VARCHAR(240) NULL AFTER correo');
        $this->ensureColumn('fecha_matrimonio_convivencia', 'ALTER TABLE protocolo_familia_personas ADD COLUMN fecha_matrimonio_convivencia DATE NULL AFTER ano_matrimonio_convivencia');
        $this->ensureColumn('regimen_patrimonial', 'ALTER TABLE protocolo_familia_personas ADD COLUMN regimen_patrimonial VARCHAR(120) NULL AFTER fecha_matrimonio_convivencia');
        $this->ensureColumn('conyuge_companero', 'ALTER TABLE protocolo_familia_personas ADD COLUMN conyuge_companero VARCHAR(220) NULL AFTER regimen_patrimonial');
        $this->ensureColumn('tipo_vinculacion_laboral', 'ALTER TABLE protocolo_familia_personas ADD COLUMN tipo_vinculacion_laboral VARCHAR(120) NULL AFTER cargo');
        $this->ensureColumn('rol_gobierno_familiar', 'ALTER TABLE protocolo_familia_personas ADD COLUMN rol_gobierno_familiar VARCHAR(160) NULL AFTER participa_decisiones');
        $this->ensureColumn('tiene_poderes', 'ALTER TABLE protocolo_familia_personas ADD COLUMN tiene_poderes VARCHAR(40) NULL AFTER rol_gobierno_familiar');
        $this->ensureColumn('tiene_testamento', 'ALTER TABLE protocolo_familia_personas ADD COLUMN tiene_testamento VARCHAR(40) NULL AFTER tiene_poderes');
        $this->ensureColumn('hijos_registrados', 'ALTER TABLE protocolo_familia_personas ADD COLUMN hijos_registrados TEXT NULL AFTER tiene_testamento');
        $this->ensureColumn('alertas_observaciones', 'ALTER TABLE protocolo_familia_personas ADD COLUMN alertas_observaciones TEXT NULL AFTER hijos_registrados');
    }

    private function ensureColumn(string $column, string $sql): void
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = "protocolo_familia_personas" AND COLUMN_NAME = :column');
        $stmt->execute(['column' => $column]);
        if ((int) $stmt->fetchColumn() === 0) {
            $this->pdo->exec($sql);
        }
    }

    /** @param array<string, mixed> $data @return array<string, mixed> */
    private function params(array $data): array
    {
        return [
            'nombre_completo' => $this->required($data['nombre_completo'] ?? null),
            'tipo_documento' => $this->optional($data['tipo_documento'] ?? null),
            'numero_documento' => $this->optional($data['numero_documento'] ?? null),
            'fecha_nacimiento' => $this->date($data['fecha_nacimiento'] ?? null),
            'ciudad_expedicion' => $this->optional($data['ciudad_expedicion'] ?? null),
            'nacionalidad' => $this->optional($data['nacionalidad'] ?? null),
            'telefono' => $this->optional($data['telefono'] ?? null),
            'correo' => $this->optional($data['correo'] ?? null),
            'direccion_residencia' => $this->optional($data['direccion_residencia'] ?? null),
            'tipo_vinculo' => $this->optional($data['tipo_vinculo'] ?? null),
            'generacion' => $this->optional($data['generacion'] ?? null),
            'edad' => $this->nullableInt($data['edad'] ?? null),
            'estado_civil' => $this->optional($data['estado_civil'] ?? null),
            'ano_matrimonio_convivencia' => $this->nullableInt($data['ano_matrimonio_convivencia'] ?? null),
            'fecha_matrimonio_convivencia' => $this->date($data['fecha_matrimonio_convivencia'] ?? null),
            'regimen_patrimonial' => $this->optional($data['regimen_patrimonial'] ?? null),
            'conyuge_companero' => $this->optional($data['conyuge_companero'] ?? null),
            'tiene_capitulaciones' => $this->optional($data['tiene_capitulaciones'] ?? null),
            'sociedad_conyugal_liquidada' => $this->optional($data['sociedad_conyugal_liquidada'] ?? null),
            'numero_hijos' => $this->nullableInt($data['numero_hijos'] ?? null),
            'trabaja_empresa_familiar' => $this->optional($data['trabaja_empresa_familiar'] ?? null),
            'empresa_donde_trabaja' => $this->optional($data['empresa_donde_trabaja'] ?? null),
            'cargo' => $this->optional($data['cargo'] ?? null),
            'tipo_vinculacion_laboral' => $this->optional($data['tipo_vinculacion_laboral'] ?? null),
            'es_accionista' => $this->optional($data['es_accionista'] ?? null),
            'participa_decisiones' => $this->optional($data['participa_decisiones'] ?? null),
            'rol_gobierno_familiar' => $this->optional($data['rol_gobierno_familiar'] ?? null),
            'tiene_poderes' => $this->optional($data['tiene_poderes'] ?? null),
            'tiene_testamento' => $this->optional($data['tiene_testamento'] ?? null),
            'hijos_registrados' => $this->optional($data['hijos_registrados'] ?? null),
            'alertas_observaciones' => $this->optional($data['alertas_observaciones'] ?? null),
            'observaciones' => $this->optional($data['observaciones'] ?? null),
        ];
    }

    private function required(mixed $value): string
    {
        if (!is_string($value) || trim($value) === '') {
            throw new RuntimeException('Nombre completo es obligatorio.');
        }
        return trim($value);
    }

    private function optional(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    private function date(mixed $value): ?string
    {
        if (!is_string($value) || trim($value) === '') {
            return null;
        }
        $value = trim($value);
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1) {
            return $value;
        }
        if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $value, $matches) === 1) {
            $day = (int) $matches[1];
            $month = (int) $matches[2];
            $year = (int) $matches[3];
            if (checkdate($month, $day, $year)) {
                return sprintf('%04d-%02d-%02d', $year, $month, $day);
            }
        }
        if (preg_match('/^(\d{2})-(\d{2})-(\d{4})$/', $value, $matches) === 1) {
            $day = (int) $matches[1];
            $month = (int) $matches[2];
            $year = (int) $matches[3];
            if (checkdate($month, $day, $year)) {
                return sprintf('%04d-%02d-%02d', $year, $month, $day);
            }
        }
        if (preg_match('/^(\d{4})\/(\d{2})\/(\d{2})$/', $value, $matches) === 1) {
            $year = (int) $matches[1];
            $month = (int) $matches[2];
            $day = (int) $matches[3];
            if (checkdate($month, $day, $year)) {
                return sprintf('%04d-%02d-%02d', $year, $month, $day);
            }
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) !== 1) {
            throw new RuntimeException('Fecha invalida.');
        }
        return $value;
    }

    private function nullableInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (is_string($value) && preg_match('/^\d{1,4}$/', $value) === 1) {
            return (int) $value;
        }
        if (is_int($value)) {
            return $value;
        }
        throw new RuntimeException('Los campos numericos solo admiten numeros.');
    }
}
