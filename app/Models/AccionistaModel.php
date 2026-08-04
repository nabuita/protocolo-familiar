<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\Uuid;
use PDO;
use RuntimeException;

final readonly class AccionistaModel
{
    public function __construct(private PDO $pdo)
    {
        $this->ensureSchema();
        $this->seedDefaults();
    }

    /** @return list<array<string, mixed>> */
    public function all(): array
    {
        return $this->pdo->query('SELECT * FROM protocolo_accionistas WHERE activo = 1 ORDER BY CAST(SUBSTRING(codigo, 5) AS UNSIGNED), created_at')->fetchAll();
    }

    /** @return list<array{empresa:string,total:float,estado:string}> */
    public function totals(): array
    {
        $rows = $this->pdo->query('SELECT empresa, COALESCE(SUM(porcentaje), 0) total FROM protocolo_accionistas WHERE activo = 1 GROUP BY empresa ORDER BY empresa')->fetchAll();
        return array_map(static function (array $row): array {
            $total = (float) $row['total'];
            return [
                'empresa' => (string) $row['empresa'],
                'total' => $total,
                'estado' => abs($total - 100.0) < 0.01 ? 'Completo' : ($total > 100.0 ? 'Excede 100%' : 'Pendiente'),
            ];
        }, $rows);
    }

    /** @param array<string, mixed> $data */
    public function create(array $data, string $actor): array
    {
        $id = Uuid::v4();
        $codigo = $this->nextCode();
        $stmt = $this->pdo->prepare('INSERT INTO protocolo_accionistas (
            id, codigo, empresa, accionista, vinculo_familiar, numero_acciones_cuotas, porcentaje, ano_ingreso,
            forma_adquisicion, valor_pagado_aportado, quien_aporto_recursos, estado_civil_adquirir,
            convivia_en_ese_momento, existe_documento_adquisicion, registro_libro_accionistas, restriccion_vigente,
            tipo_participacion, naturaleza_participacion, fecha_adquisicion, medio_pago, soporte_pago,
            regimen_patrimonial_adquirir, fecha_registro_libro, tipo_restriccion, valor_nominal,
            fecha_valoracion, metodo_valoracion, fuente_valoracion, alerta_documental, valor_estimado_actual,
            observaciones, activo, created_at, updated_at, created_by, updated_by
        ) VALUES (
            :id, :codigo, :empresa, :accionista, :vinculo_familiar, :numero_acciones_cuotas, :porcentaje, :ano_ingreso,
            :forma_adquisicion, :valor_pagado_aportado, :quien_aporto_recursos, :estado_civil_adquirir,
            :convivia_en_ese_momento, :existe_documento_adquisicion, :registro_libro_accionistas, :restriccion_vigente,
            :tipo_participacion, :naturaleza_participacion, :fecha_adquisicion, :medio_pago, :soporte_pago,
            :regimen_patrimonial_adquirir, :fecha_registro_libro, :tipo_restriccion, :valor_nominal,
            :fecha_valoracion, :metodo_valoracion, :fuente_valoracion, :alerta_documental, :valor_estimado_actual,
            :observaciones, 1, UTC_TIMESTAMP(6), UTC_TIMESTAMP(6), :created_by, :updated_by
        )');
        $stmt->execute($this->params($data) + ['id' => $id, 'codigo' => $codigo, 'created_by' => $actor, 'updated_by' => $actor]);
        return $this->find($id);
    }

    /** @param array<string, mixed> $data */
    public function update(string $id, array $data, string $actor): array
    {
        $stmt = $this->pdo->prepare('UPDATE protocolo_accionistas SET
            empresa = :empresa,
            accionista = :accionista,
            vinculo_familiar = :vinculo_familiar,
            numero_acciones_cuotas = :numero_acciones_cuotas,
            porcentaje = :porcentaje,
            ano_ingreso = :ano_ingreso,
            forma_adquisicion = :forma_adquisicion,
            valor_pagado_aportado = :valor_pagado_aportado,
            quien_aporto_recursos = :quien_aporto_recursos,
            estado_civil_adquirir = :estado_civil_adquirir,
            convivia_en_ese_momento = :convivia_en_ese_momento,
            existe_documento_adquisicion = :existe_documento_adquisicion,
            registro_libro_accionistas = :registro_libro_accionistas,
            restriccion_vigente = :restriccion_vigente,
            tipo_participacion = :tipo_participacion,
            naturaleza_participacion = :naturaleza_participacion,
            fecha_adquisicion = :fecha_adquisicion,
            medio_pago = :medio_pago,
            soporte_pago = :soporte_pago,
            regimen_patrimonial_adquirir = :regimen_patrimonial_adquirir,
            fecha_registro_libro = :fecha_registro_libro,
            tipo_restriccion = :tipo_restriccion,
            valor_nominal = :valor_nominal,
            fecha_valoracion = :fecha_valoracion,
            metodo_valoracion = :metodo_valoracion,
            fuente_valoracion = :fuente_valoracion,
            alerta_documental = :alerta_documental,
            valor_estimado_actual = :valor_estimado_actual,
            observaciones = :observaciones,
            updated_at = UTC_TIMESTAMP(6),
            updated_by = :updated_by
            WHERE id = :id AND activo = 1');
        $stmt->execute($this->params($data) + ['id' => $id, 'updated_by' => $actor]);
        return $this->find($id);
    }

    public function deactivate(string $id, string $actor): void
    {
        $stmt = $this->pdo->prepare('UPDATE protocolo_accionistas SET activo = 0, updated_at = UTC_TIMESTAMP(6), updated_by = :actor WHERE id = :id AND activo = 1');
        $stmt->execute(['id' => $id, 'actor' => $actor]);
    }

    /** @return array<string, mixed> */
    public function find(string $id): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM protocolo_accionistas WHERE id = :id AND activo = 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        if (!is_array($row)) {
            throw new RuntimeException('Accionista no encontrado.');
        }
        return $row;
    }

    private function ensureSchema(): void
    {
        $this->pdo->exec('CREATE TABLE IF NOT EXISTS protocolo_accionistas (
            id CHAR(36) NOT NULL PRIMARY KEY,
            codigo VARCHAR(20) NOT NULL UNIQUE,
            empresa VARCHAR(240) NOT NULL,
            accionista VARCHAR(220) NOT NULL,
            vinculo_familiar VARCHAR(120) NULL,
            numero_acciones_cuotas BIGINT NULL,
            porcentaje DECIMAL(7,4) NOT NULL DEFAULT 0,
            ano_ingreso INT NULL,
            forma_adquisicion VARCHAR(120) NULL,
            valor_pagado_aportado DECIMAL(18,2) NULL,
            quien_aporto_recursos VARCHAR(220) NULL,
            estado_civil_adquirir VARCHAR(120) NULL,
            convivia_en_ese_momento VARCHAR(40) NULL,
            existe_documento_adquisicion VARCHAR(40) NULL,
            registro_libro_accionistas VARCHAR(40) NULL,
            restriccion_vigente VARCHAR(80) NULL,
            valor_estimado_actual DECIMAL(18,2) NULL,
            observaciones TEXT NULL,
            activo TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME(6) NOT NULL,
            updated_at DATETIME(6) NOT NULL,
            created_by VARCHAR(120) NULL,
            updated_by VARCHAR(120) NULL,
            INDEX idx_protocolo_accionistas_empresa (activo, empresa),
            INDEX idx_protocolo_accionistas_codigo (activo, codigo)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $this->ensureColumn('tipo_participacion', 'ALTER TABLE protocolo_accionistas ADD COLUMN tipo_participacion VARCHAR(120) NULL AFTER restriccion_vigente');
        $this->ensureColumn('naturaleza_participacion', 'ALTER TABLE protocolo_accionistas ADD COLUMN naturaleza_participacion VARCHAR(140) NULL AFTER tipo_participacion');
        $this->ensureColumn('fecha_adquisicion', 'ALTER TABLE protocolo_accionistas ADD COLUMN fecha_adquisicion DATE NULL AFTER naturaleza_participacion');
        $this->ensureColumn('medio_pago', 'ALTER TABLE protocolo_accionistas ADD COLUMN medio_pago VARCHAR(140) NULL AFTER fecha_adquisicion');
        $this->ensureColumn('soporte_pago', 'ALTER TABLE protocolo_accionistas ADD COLUMN soporte_pago VARCHAR(140) NULL AFTER medio_pago');
        $this->ensureColumn('regimen_patrimonial_adquirir', 'ALTER TABLE protocolo_accionistas ADD COLUMN regimen_patrimonial_adquirir VARCHAR(160) NULL AFTER soporte_pago');
        $this->ensureColumn('fecha_registro_libro', 'ALTER TABLE protocolo_accionistas ADD COLUMN fecha_registro_libro DATE NULL AFTER regimen_patrimonial_adquirir');
        $this->ensureColumn('tipo_restriccion', 'ALTER TABLE protocolo_accionistas ADD COLUMN tipo_restriccion VARCHAR(160) NULL AFTER fecha_registro_libro');
        $this->ensureColumn('valor_nominal', 'ALTER TABLE protocolo_accionistas ADD COLUMN valor_nominal DECIMAL(18,2) NULL AFTER tipo_restriccion');
        $this->ensureColumn('fecha_valoracion', 'ALTER TABLE protocolo_accionistas ADD COLUMN fecha_valoracion DATE NULL AFTER valor_nominal');
        $this->ensureColumn('metodo_valoracion', 'ALTER TABLE protocolo_accionistas ADD COLUMN metodo_valoracion VARCHAR(160) NULL AFTER fecha_valoracion');
        $this->ensureColumn('fuente_valoracion', 'ALTER TABLE protocolo_accionistas ADD COLUMN fuente_valoracion VARCHAR(220) NULL AFTER metodo_valoracion');
        $this->ensureColumn('alerta_documental', 'ALTER TABLE protocolo_accionistas ADD COLUMN alerta_documental TEXT NULL AFTER fuente_valoracion');
    }

    private function ensureColumn(string $column, string $sql): void
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = "protocolo_accionistas" AND COLUMN_NAME = :column');
        $stmt->execute(['column' => $column]);
        if ((int) $stmt->fetchColumn() === 0) {
            $this->pdo->exec($sql);
        }
    }

    private function seedDefaults(): void
    {
        $exists = (int) $this->pdo->query('SELECT COUNT(*) FROM protocolo_accionistas')->fetchColumn();
        if ($exists > 0) {
            return;
        }

        $stmt = $this->pdo->prepare('INSERT INTO protocolo_accionistas (
            id, codigo, empresa, accionista, vinculo_familiar, numero_acciones_cuotas, porcentaje, ano_ingreso,
            forma_adquisicion, valor_pagado_aportado, quien_aporto_recursos, estado_civil_adquirir,
            convivia_en_ese_momento, existe_documento_adquisicion, registro_libro_accionistas, restriccion_vigente,
            tipo_participacion, naturaleza_participacion, fecha_adquisicion, medio_pago, soporte_pago,
            regimen_patrimonial_adquirir, fecha_registro_libro, tipo_restriccion, valor_nominal,
            fecha_valoracion, metodo_valoracion, fuente_valoracion, alerta_documental, valor_estimado_actual,
            observaciones, activo, created_at, updated_at, created_by, updated_by
        ) VALUES (
            :id, :codigo, :empresa, :accionista, :vinculo_familiar, :numero_acciones_cuotas, :porcentaje, :ano_ingreso,
            :forma_adquisicion, :valor_pagado_aportado, :quien_aporto_recursos, :estado_civil_adquirir,
            :convivia_en_ese_momento, :existe_documento_adquisicion, :registro_libro_accionistas, :restriccion_vigente,
            :tipo_participacion, :naturaleza_participacion, :fecha_adquisicion, :medio_pago, :soporte_pago,
            :regimen_patrimonial_adquirir, :fecha_registro_libro, :tipo_restriccion, :valor_nominal,
            :fecha_valoracion, :metodo_valoracion, :fuente_valoracion, :alerta_documental, :valor_estimado_actual,
            :observaciones, 1, UTC_TIMESTAMP(6), UTC_TIMESTAMP(6), :created_by, :updated_by
        )');
        foreach ($this->defaultRows() as $row) {
            $stmt->execute($this->params($row) + ['id' => Uuid::v4(), 'codigo' => $row['codigo'], 'created_by' => 'install', 'updated_by' => 'install']);
        }
    }

    private function nextCode(): string
    {
        $next = (int) $this->pdo->query("SELECT COALESCE(MAX(CAST(SUBSTRING(codigo, 5) AS UNSIGNED)), 0) + 1 FROM protocolo_accionistas")->fetchColumn();
        return sprintf('ACC-%03d', $next);
    }

    /** @param array<string, mixed> $data @return array<string, mixed> */
    private function params(array $data): array
    {
        return [
            'empresa' => $this->required($data['empresa'] ?? null, 'Empresa es obligatoria.'),
            'accionista' => $this->required($data['accionista'] ?? null, 'Accionista es obligatorio.'),
            'vinculo_familiar' => $this->optional($data['vinculo_familiar'] ?? null),
            'numero_acciones_cuotas' => $this->integer($data['numero_acciones_cuotas'] ?? null),
            'porcentaje' => $this->decimal($data['porcentaje'] ?? null, true),
            'ano_ingreso' => $this->year($data['ano_ingreso'] ?? null),
            'forma_adquisicion' => $this->optional($data['forma_adquisicion'] ?? null),
            'valor_pagado_aportado' => $this->money($data['valor_pagado_aportado'] ?? null),
            'quien_aporto_recursos' => $this->optional($data['quien_aporto_recursos'] ?? null),
            'estado_civil_adquirir' => $this->optional($data['estado_civil_adquirir'] ?? null),
            'convivia_en_ese_momento' => $this->optional($data['convivia_en_ese_momento'] ?? null),
            'existe_documento_adquisicion' => $this->optional($data['existe_documento_adquisicion'] ?? null),
            'registro_libro_accionistas' => $this->optional($data['registro_libro_accionistas'] ?? null),
            'restriccion_vigente' => $this->optional($data['restriccion_vigente'] ?? null),
            'tipo_participacion' => $this->optional($data['tipo_participacion'] ?? null),
            'naturaleza_participacion' => $this->optional($data['naturaleza_participacion'] ?? null),
            'fecha_adquisicion' => $this->date($data['fecha_adquisicion'] ?? null),
            'medio_pago' => $this->optional($data['medio_pago'] ?? null),
            'soporte_pago' => $this->optional($data['soporte_pago'] ?? null),
            'regimen_patrimonial_adquirir' => $this->optional($data['regimen_patrimonial_adquirir'] ?? null),
            'fecha_registro_libro' => $this->date($data['fecha_registro_libro'] ?? null),
            'tipo_restriccion' => $this->optional($data['tipo_restriccion'] ?? null),
            'valor_nominal' => $this->money($data['valor_nominal'] ?? null),
            'fecha_valoracion' => $this->date($data['fecha_valoracion'] ?? null),
            'metodo_valoracion' => $this->optional($data['metodo_valoracion'] ?? null),
            'fuente_valoracion' => $this->optional($data['fuente_valoracion'] ?? null),
            'alerta_documental' => $this->optional($data['alerta_documental'] ?? null),
            'valor_estimado_actual' => $this->money($data['valor_estimado_actual'] ?? null),
            'observaciones' => $this->optional($data['observaciones'] ?? null),
        ];
    }

    private function required(mixed $value, string $message): string
    {
        if (!is_string($value) || trim($value) === '') {
            throw new RuntimeException($message);
        }
        return trim($value);
    }

    private function optional(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    private function integer(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }
        $digits = preg_replace('/\D+/', '', (string) $value);
        return is_string($digits) && $digits !== '' ? (int) $digits : null;
    }

    private function year(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (preg_match('/^\d{4}$/', (string) $value) !== 1) {
            throw new RuntimeException('Ano de ingreso invalido.');
        }
        return (int) $value;
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

    private function decimal(mixed $value, bool $required = false): ?float
    {
        if ($value === null || trim((string) $value) === '') {
            if ($required) {
                throw new RuntimeException('Porcentaje es obligatorio.');
            }
            return null;
        }
        $clean = str_replace(['%', ' '], '', (string) $value);
        if (str_contains($clean, ',')) {
            $clean = str_replace('.', '', $clean);
            $clean = str_replace(',', '.', $clean);
        }
        if (!is_numeric($clean)) {
            throw new RuntimeException('Porcentaje invalido.');
        }
        return (float) $clean;
    }

    private function money(mixed $value): ?float
    {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }
        $clean = preg_replace('/[$\s\x{00A0}\x{202F}]+/u', '', (string) $value);
        if (!is_string($clean)) {
            throw new RuntimeException('Valor monetario invalido.');
        }
        if (str_contains($clean, ',')) {
            $clean = str_replace('.', '', $clean);
            $clean = str_replace(',', '.', $clean);
        } elseif (substr_count($clean, '.') > 1 || preg_match('/\.\d{3}$/', $clean) === 1) {
            $clean = str_replace('.', '', $clean);
        }
        if (!is_numeric($clean)) {
            throw new RuntimeException('Valor monetario invalido.');
        }
        return (float) $clean;
    }

    /** @return list<array<string, mixed>> */
    private function defaultRows(): array
    {
        $empresa1 = 'Soluciones Comerciales y Constructivas SAS';
        $empresa2 = 'Disenos y Soluciones Creativas SAS';
        return [
            ['codigo' => 'ACC-001', 'empresa' => $empresa1, 'accionista' => 'Nassif Abuita Nassar', 'vinculo_familiar' => 'Fundador(a)', 'numero_acciones_cuotas' => 6120, 'porcentaje' => 34.00, 'ano_ingreso' => 2013, 'forma_adquisicion' => 'Constitucion', 'valor_pagado_aportado' => 61200000, 'quien_aporto_recursos' => 'Nassif Abuita Nassar', 'estado_civil_adquirir' => 'Separado(a)', 'convivia_en_ese_momento' => 'Si', 'existe_documento_adquisicion' => 'No', 'registro_libro_accionistas' => 'No', 'restriccion_vigente' => 'No aplica', 'valor_estimado_actual' => 218968405, 'observaciones' => null],
            ['codigo' => 'ACC-002', 'empresa' => $empresa1, 'accionista' => 'Gloria Maria Correa Rivera', 'vinculo_familiar' => 'Fundador(a)', 'numero_acciones_cuotas' => 6480, 'porcentaje' => 36.00, 'ano_ingreso' => 2013, 'forma_adquisicion' => 'Constitucion', 'valor_pagado_aportado' => 64800000, 'quien_aporto_recursos' => 'Gloria Maria Correa Rivera', 'estado_civil_adquirir' => 'Separado(a)', 'convivia_en_ese_momento' => 'Si', 'existe_documento_adquisicion' => 'No', 'registro_libro_accionistas' => 'No', 'restriccion_vigente' => 'No aplica', 'valor_estimado_actual' => 231848899, 'observaciones' => null],
            ['codigo' => 'ACC-003', 'empresa' => $empresa1, 'accionista' => 'Said Latif Abuita Correa', 'vinculo_familiar' => 'Hijo(a)', 'numero_acciones_cuotas' => 2700, 'porcentaje' => 15.00, 'ano_ingreso' => 2013, 'forma_adquisicion' => 'Constitucion', 'valor_pagado_aportado' => 27000000, 'quien_aporto_recursos' => 'Said Latif Abuita Correa', 'estado_civil_adquirir' => 'Soltero(a)', 'convivia_en_ese_momento' => 'No', 'existe_documento_adquisicion' => 'No', 'registro_libro_accionistas' => 'No', 'restriccion_vigente' => 'No aplica', 'valor_estimado_actual' => 96603708, 'observaciones' => null],
            ['codigo' => 'ACC-004', 'empresa' => $empresa1, 'accionista' => 'Karam Nassif Abuita Correa', 'vinculo_familiar' => 'Hijo(a)', 'numero_acciones_cuotas' => 2700, 'porcentaje' => 15.00, 'ano_ingreso' => 2013, 'forma_adquisicion' => 'Constitucion', 'valor_pagado_aportado' => 27000000, 'quien_aporto_recursos' => 'Karam Nassif Abuita Correa', 'estado_civil_adquirir' => 'Soltero(a)', 'convivia_en_ese_momento' => 'No', 'existe_documento_adquisicion' => 'No', 'registro_libro_accionistas' => 'No', 'restriccion_vigente' => 'No aplica', 'valor_estimado_actual' => 96603708, 'observaciones' => null],
            ['codigo' => 'ACC-005', 'empresa' => $empresa2, 'accionista' => 'Gloria Maria Correa Rivera', 'vinculo_familiar' => 'Fundador(a)', 'numero_acciones_cuotas' => 34020, 'porcentaje' => 21.00, 'ano_ingreso' => 2005, 'forma_adquisicion' => 'Constitucion', 'valor_pagado_aportado' => 34020000, 'quien_aporto_recursos' => 'Gloria Maria Correa Rivera', 'estado_civil_adquirir' => 'Separado(a)', 'convivia_en_ese_momento' => 'Si', 'existe_documento_adquisicion' => 'No', 'registro_libro_accionistas' => 'No', 'restriccion_vigente' => 'No aplica', 'valor_estimado_actual' => null, 'observaciones' => null],
            ['codigo' => 'ACC-006', 'empresa' => $empresa2, 'accionista' => 'Nassif Abuita Nassar', 'vinculo_familiar' => 'Fundador(a)', 'numero_acciones_cuotas' => 30780, 'porcentaje' => 19.00, 'ano_ingreso' => 2005, 'forma_adquisicion' => 'Cesion', 'valor_pagado_aportado' => 30780000, 'quien_aporto_recursos' => 'Gloria Maria Correa Rivera', 'estado_civil_adquirir' => 'Separado(a)', 'convivia_en_ese_momento' => 'Si', 'existe_documento_adquisicion' => 'No', 'registro_libro_accionistas' => 'No', 'restriccion_vigente' => 'No aplica', 'valor_estimado_actual' => null, 'observaciones' => null],
            ['codigo' => 'ACC-007', 'empresa' => $empresa2, 'accionista' => 'Said Latif Abuita Correa', 'vinculo_familiar' => 'Hijo(a)', 'numero_acciones_cuotas' => 8100, 'porcentaje' => 5.00, 'ano_ingreso' => 2016, 'forma_adquisicion' => 'Cesion', 'valor_pagado_aportado' => 8100000, 'quien_aporto_recursos' => 'Gloria Maria Correa Rivera', 'estado_civil_adquirir' => 'Soltero(a)', 'convivia_en_ese_momento' => 'No', 'existe_documento_adquisicion' => 'No', 'registro_libro_accionistas' => 'No', 'restriccion_vigente' => 'No aplica', 'valor_estimado_actual' => null, 'observaciones' => null],
            ['codigo' => 'ACC-008', 'empresa' => $empresa2, 'accionista' => 'Karam Nassif Abuita Correa', 'vinculo_familiar' => 'Hijo(a)', 'numero_acciones_cuotas' => 8100, 'porcentaje' => 5.00, 'ano_ingreso' => 2016, 'forma_adquisicion' => 'Cesion', 'valor_pagado_aportado' => 8100000, 'quien_aporto_recursos' => 'Gloria Maria Correa Rivera', 'estado_civil_adquirir' => 'Soltero(a)', 'convivia_en_ese_momento' => 'No', 'existe_documento_adquisicion' => 'No', 'registro_libro_accionistas' => 'No', 'restriccion_vigente' => 'No aplica', 'valor_estimado_actual' => null, 'observaciones' => null],
        ];
    }
}
