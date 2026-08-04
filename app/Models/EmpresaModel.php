<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\Uuid;
use PDO;
use RuntimeException;

final readonly class EmpresaModel
{
    public function __construct(private PDO $pdo)
    {
        $this->ensureSchema();
        $this->seedDefaults();
    }

    /** @return list<array<string, mixed>> */
    public function all(): array
    {
        return $this->pdo->query('SELECT * FROM protocolo_empresas WHERE activo = 1 ORDER BY CAST(SUBSTRING(codigo, 5) AS UNSIGNED), created_at')->fetchAll();
    }

    /** @param array<string, mixed> $data */
    public function create(array $data, string $actor): array
    {
        $id = Uuid::v4();
        $codigo = $this->nextCode();
        $stmt = $this->pdo->prepare('INSERT INTO protocolo_empresas (
            id, codigo, razon_social, nombre_comercial, nit, matricula_mercantil, domicilio_principal,
            ciudad_domicilio, telefono, correo_contacto, tipo_sociedad, ano_creacion, actividad_principal,
            empresa_operativa_patrimonial, representante_legal, tiene_junta_directiva, tiene_revisor_fiscal,
            tiene_inmuebles_propios, tiene_empleados, contador_responsable, correo_contador, periodicidad_iva,
            periodicidad_retencion, periodicidad_ica, periodicidad_estados_financieros, cierre_fiscal,
            estado_rut, estado_camara_comercio, estado_obligaciones_tributarias, carpeta_documental_nombre,
            carpeta_documental_url, carpeta_documental_observaciones, deudas_creditos_importantes, estatutos_actualizados,
            acuerdo_accionistas, libro_accionistas_actualizado, nivel_riesgo, observaciones, activo,
            created_at, updated_at, created_by, updated_by
        ) VALUES (
            :id, :codigo, :razon_social, :nombre_comercial, :nit, :matricula_mercantil, :domicilio_principal,
            :ciudad_domicilio, :telefono, :correo_contacto, :tipo_sociedad, :ano_creacion, :actividad_principal,
            :empresa_operativa_patrimonial, :representante_legal, :tiene_junta_directiva, :tiene_revisor_fiscal,
            :tiene_inmuebles_propios, :tiene_empleados, :contador_responsable, :correo_contador, :periodicidad_iva,
            :periodicidad_retencion, :periodicidad_ica, :periodicidad_estados_financieros, :cierre_fiscal,
            :estado_rut, :estado_camara_comercio, :estado_obligaciones_tributarias, :carpeta_documental_nombre,
            :carpeta_documental_url, :carpeta_documental_observaciones, :deudas_creditos_importantes, :estatutos_actualizados,
            :acuerdo_accionistas, :libro_accionistas_actualizado, :nivel_riesgo, :observaciones, 1,
            UTC_TIMESTAMP(6), UTC_TIMESTAMP(6), :created_by, :updated_by
        )');
        $stmt->execute($this->params($data) + ['id' => $id, 'codigo' => $codigo, 'created_by' => $actor, 'updated_by' => $actor]);
        return $this->find($id);
    }

    /** @param array<string, mixed> $data */
    public function update(string $id, array $data, string $actor): array
    {
        $stmt = $this->pdo->prepare('UPDATE protocolo_empresas SET
            razon_social = :razon_social,
            nombre_comercial = :nombre_comercial,
            nit = :nit,
            matricula_mercantil = :matricula_mercantil,
            domicilio_principal = :domicilio_principal,
            ciudad_domicilio = :ciudad_domicilio,
            telefono = :telefono,
            correo_contacto = :correo_contacto,
            tipo_sociedad = :tipo_sociedad,
            ano_creacion = :ano_creacion,
            actividad_principal = :actividad_principal,
            empresa_operativa_patrimonial = :empresa_operativa_patrimonial,
            representante_legal = :representante_legal,
            tiene_junta_directiva = :tiene_junta_directiva,
            tiene_revisor_fiscal = :tiene_revisor_fiscal,
            tiene_inmuebles_propios = :tiene_inmuebles_propios,
            tiene_empleados = :tiene_empleados,
            contador_responsable = :contador_responsable,
            correo_contador = :correo_contador,
            periodicidad_iva = :periodicidad_iva,
            periodicidad_retencion = :periodicidad_retencion,
            periodicidad_ica = :periodicidad_ica,
            periodicidad_estados_financieros = :periodicidad_estados_financieros,
            cierre_fiscal = :cierre_fiscal,
            estado_rut = :estado_rut,
            estado_camara_comercio = :estado_camara_comercio,
            estado_obligaciones_tributarias = :estado_obligaciones_tributarias,
            carpeta_documental_nombre = :carpeta_documental_nombre,
            carpeta_documental_url = :carpeta_documental_url,
            carpeta_documental_observaciones = :carpeta_documental_observaciones,
            deudas_creditos_importantes = :deudas_creditos_importantes,
            estatutos_actualizados = :estatutos_actualizados,
            acuerdo_accionistas = :acuerdo_accionistas,
            libro_accionistas_actualizado = :libro_accionistas_actualizado,
            nivel_riesgo = :nivel_riesgo,
            observaciones = :observaciones,
            updated_at = UTC_TIMESTAMP(6),
            updated_by = :updated_by
            WHERE id = :id AND activo = 1');
        $stmt->execute($this->params($data) + ['id' => $id, 'updated_by' => $actor]);
        return $this->find($id);
    }

    public function deactivate(string $id, string $actor): void
    {
        $stmt = $this->pdo->prepare('UPDATE protocolo_empresas SET activo = 0, updated_at = UTC_TIMESTAMP(6), updated_by = :actor WHERE id = :id AND activo = 1');
        $stmt->execute(['id' => $id, 'actor' => $actor]);
    }

    /** @return array<string, mixed> */
    public function find(string $id): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM protocolo_empresas WHERE id = :id AND activo = 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        if (!is_array($row)) {
            throw new RuntimeException('Empresa no encontrada.');
        }
        return $row;
    }

    private function ensureSchema(): void
    {
        $this->pdo->exec('CREATE TABLE IF NOT EXISTS protocolo_empresas (
            id CHAR(36) NOT NULL PRIMARY KEY,
            codigo VARCHAR(20) NOT NULL UNIQUE,
            razon_social VARCHAR(240) NOT NULL,
            nombre_comercial VARCHAR(160) NULL,
            tipo_sociedad VARCHAR(80) NULL,
            ano_creacion DATE NULL,
            actividad_principal TEXT NULL,
            empresa_operativa_patrimonial VARCHAR(80) NULL,
            representante_legal VARCHAR(220) NULL,
            tiene_junta_directiva VARCHAR(40) NULL,
            tiene_revisor_fiscal VARCHAR(40) NULL,
            tiene_inmuebles_propios VARCHAR(40) NULL,
            tiene_empleados VARCHAR(40) NULL,
            deudas_creditos_importantes TEXT NULL,
            estatutos_actualizados VARCHAR(40) NULL,
            acuerdo_accionistas VARCHAR(40) NULL,
            libro_accionistas_actualizado VARCHAR(40) NULL,
            nivel_riesgo VARCHAR(40) NULL,
            observaciones TEXT NULL,
            activo TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME(6) NOT NULL,
            updated_at DATETIME(6) NOT NULL,
            created_by VARCHAR(120) NULL,
            updated_by VARCHAR(120) NULL,
            INDEX idx_protocolo_empresas_activo_codigo (activo, codigo)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $this->ensureColumn('nit', 'ALTER TABLE protocolo_empresas ADD COLUMN nit VARCHAR(80) NULL AFTER nombre_comercial');
        $this->ensureColumn('matricula_mercantil', 'ALTER TABLE protocolo_empresas ADD COLUMN matricula_mercantil VARCHAR(120) NULL AFTER nit');
        $this->ensureColumn('domicilio_principal', 'ALTER TABLE protocolo_empresas ADD COLUMN domicilio_principal VARCHAR(220) NULL AFTER matricula_mercantil');
        $this->ensureColumn('ciudad_domicilio', 'ALTER TABLE protocolo_empresas ADD COLUMN ciudad_domicilio VARCHAR(120) NULL AFTER domicilio_principal');
        $this->ensureColumn('telefono', 'ALTER TABLE protocolo_empresas ADD COLUMN telefono VARCHAR(80) NULL AFTER ciudad_domicilio');
        $this->ensureColumn('correo_contacto', 'ALTER TABLE protocolo_empresas ADD COLUMN correo_contacto VARCHAR(160) NULL AFTER telefono');
        $this->ensureColumn('contador_responsable', 'ALTER TABLE protocolo_empresas ADD COLUMN contador_responsable VARCHAR(220) NULL AFTER tiene_empleados');
        $this->ensureColumn('correo_contador', 'ALTER TABLE protocolo_empresas ADD COLUMN correo_contador VARCHAR(160) NULL AFTER contador_responsable');
        $this->ensureColumn('periodicidad_iva', 'ALTER TABLE protocolo_empresas ADD COLUMN periodicidad_iva VARCHAR(80) NULL AFTER correo_contador');
        $this->ensureColumn('periodicidad_retencion', 'ALTER TABLE protocolo_empresas ADD COLUMN periodicidad_retencion VARCHAR(80) NULL AFTER periodicidad_iva');
        $this->ensureColumn('periodicidad_ica', 'ALTER TABLE protocolo_empresas ADD COLUMN periodicidad_ica VARCHAR(80) NULL AFTER periodicidad_retencion');
        $this->ensureColumn('periodicidad_estados_financieros', 'ALTER TABLE protocolo_empresas ADD COLUMN periodicidad_estados_financieros VARCHAR(80) NULL AFTER periodicidad_ica');
        $this->ensureColumn('cierre_fiscal', 'ALTER TABLE protocolo_empresas ADD COLUMN cierre_fiscal VARCHAR(80) NULL AFTER periodicidad_estados_financieros');
        $this->ensureColumn('estado_rut', 'ALTER TABLE protocolo_empresas ADD COLUMN estado_rut VARCHAR(80) NULL AFTER cierre_fiscal');
        $this->ensureColumn('estado_camara_comercio', 'ALTER TABLE protocolo_empresas ADD COLUMN estado_camara_comercio VARCHAR(80) NULL AFTER estado_rut');
        $this->ensureColumn('estado_obligaciones_tributarias', 'ALTER TABLE protocolo_empresas ADD COLUMN estado_obligaciones_tributarias VARCHAR(80) NULL AFTER estado_camara_comercio');
        $this->ensureColumn('carpeta_documental_nombre', 'ALTER TABLE protocolo_empresas ADD COLUMN carpeta_documental_nombre VARCHAR(180) NULL AFTER estado_obligaciones_tributarias');
        $this->ensureColumn('carpeta_documental_url', 'ALTER TABLE protocolo_empresas ADD COLUMN carpeta_documental_url VARCHAR(600) NULL AFTER carpeta_documental_nombre');
        $this->ensureColumn('carpeta_documental_observaciones', 'ALTER TABLE protocolo_empresas ADD COLUMN carpeta_documental_observaciones TEXT NULL AFTER carpeta_documental_url');
    }

    private function ensureColumn(string $column, string $sql): void
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = "protocolo_empresas" AND COLUMN_NAME = :column');
        $stmt->execute(['column' => $column]);
        if ((int) $stmt->fetchColumn() === 0) {
            $this->pdo->exec($sql);
        }
    }

    private function seedDefaults(): void
    {
        $exists = (int) $this->pdo->query('SELECT COUNT(*) FROM protocolo_empresas')->fetchColumn();
        if ($exists > 0) {
            return;
        }

        $stmt = $this->pdo->prepare('INSERT INTO protocolo_empresas (
            id, codigo, razon_social, nombre_comercial, nit, matricula_mercantil, domicilio_principal,
            ciudad_domicilio, telefono, correo_contacto, tipo_sociedad, ano_creacion, actividad_principal,
            empresa_operativa_patrimonial, representante_legal, tiene_junta_directiva, tiene_revisor_fiscal,
            tiene_inmuebles_propios, tiene_empleados, contador_responsable, correo_contador, periodicidad_iva,
            periodicidad_retencion, periodicidad_ica, periodicidad_estados_financieros, cierre_fiscal,
            estado_rut, estado_camara_comercio, estado_obligaciones_tributarias, carpeta_documental_nombre,
            carpeta_documental_url, carpeta_documental_observaciones, deudas_creditos_importantes, estatutos_actualizados,
            acuerdo_accionistas, libro_accionistas_actualizado, nivel_riesgo, observaciones, activo,
            created_at, updated_at, created_by, updated_by
        ) VALUES (
            :id, :codigo, :razon_social, :nombre_comercial, :nit, :matricula_mercantil, :domicilio_principal,
            :ciudad_domicilio, :telefono, :correo_contacto, :tipo_sociedad, :ano_creacion, :actividad_principal,
            :empresa_operativa_patrimonial, :representante_legal, :tiene_junta_directiva, :tiene_revisor_fiscal,
            :tiene_inmuebles_propios, :tiene_empleados, :contador_responsable, :correo_contador, :periodicidad_iva,
            :periodicidad_retencion, :periodicidad_ica, :periodicidad_estados_financieros, :cierre_fiscal,
            :estado_rut, :estado_camara_comercio, :estado_obligaciones_tributarias, :carpeta_documental_nombre,
            :carpeta_documental_url, :carpeta_documental_observaciones, :deudas_creditos_importantes, :estatutos_actualizados,
            :acuerdo_accionistas, :libro_accionistas_actualizado, :nivel_riesgo, :observaciones, 1,
            UTC_TIMESTAMP(6), UTC_TIMESTAMP(6), :created_by, :updated_by
        )');
        foreach ($this->defaultRows() as $row) {
            $stmt->execute($row + ['id' => Uuid::v4(), 'created_by' => 'install', 'updated_by' => 'install']);
        }
    }

    private function nextCode(): string
    {
        $next = (int) $this->pdo->query("SELECT COALESCE(MAX(CAST(SUBSTRING(codigo, 5) AS UNSIGNED)), 0) + 1 FROM protocolo_empresas")->fetchColumn();
        return sprintf('EMP-%03d', $next);
    }

    /** @param array<string, mixed> $data @return array<string, mixed> */
    private function params(array $data): array
    {
        return [
            'razon_social' => $this->required($data['razon_social'] ?? null),
            'nombre_comercial' => $this->optional($data['nombre_comercial'] ?? null),
            'nit' => $this->optional($data['nit'] ?? null),
            'matricula_mercantil' => $this->optional($data['matricula_mercantil'] ?? null),
            'domicilio_principal' => $this->optional($data['domicilio_principal'] ?? null),
            'ciudad_domicilio' => $this->optional($data['ciudad_domicilio'] ?? null),
            'telefono' => $this->optional($data['telefono'] ?? null),
            'correo_contacto' => $this->optional($data['correo_contacto'] ?? null),
            'tipo_sociedad' => $this->optional($data['tipo_sociedad'] ?? null),
            'ano_creacion' => $this->date($data['ano_creacion'] ?? null),
            'actividad_principal' => $this->optional($data['actividad_principal'] ?? null),
            'empresa_operativa_patrimonial' => $this->optional($data['empresa_operativa_patrimonial'] ?? null),
            'representante_legal' => $this->optional($data['representante_legal'] ?? null),
            'tiene_junta_directiva' => $this->optional($data['tiene_junta_directiva'] ?? null),
            'tiene_revisor_fiscal' => $this->optional($data['tiene_revisor_fiscal'] ?? null),
            'tiene_inmuebles_propios' => $this->optional($data['tiene_inmuebles_propios'] ?? null),
            'tiene_empleados' => $this->optional($data['tiene_empleados'] ?? null),
            'contador_responsable' => $this->optional($data['contador_responsable'] ?? null),
            'correo_contador' => $this->optional($data['correo_contador'] ?? null),
            'periodicidad_iva' => $this->optional($data['periodicidad_iva'] ?? null),
            'periodicidad_retencion' => $this->optional($data['periodicidad_retencion'] ?? null),
            'periodicidad_ica' => $this->optional($data['periodicidad_ica'] ?? null),
            'periodicidad_estados_financieros' => $this->optional($data['periodicidad_estados_financieros'] ?? null),
            'cierre_fiscal' => $this->optional($data['cierre_fiscal'] ?? null),
            'estado_rut' => $this->optional($data['estado_rut'] ?? null),
            'estado_camara_comercio' => $this->optional($data['estado_camara_comercio'] ?? null),
            'estado_obligaciones_tributarias' => $this->optional($data['estado_obligaciones_tributarias'] ?? null),
            'carpeta_documental_nombre' => $this->optional($data['carpeta_documental_nombre'] ?? null),
            'carpeta_documental_url' => $this->url($data['carpeta_documental_url'] ?? null),
            'carpeta_documental_observaciones' => $this->optional($data['carpeta_documental_observaciones'] ?? null),
            'deudas_creditos_importantes' => $this->optional($data['deudas_creditos_importantes'] ?? null),
            'estatutos_actualizados' => $this->optional($data['estatutos_actualizados'] ?? null),
            'acuerdo_accionistas' => $this->optional($data['acuerdo_accionistas'] ?? null),
            'libro_accionistas_actualizado' => $this->optional($data['libro_accionistas_actualizado'] ?? null),
            'nivel_riesgo' => $this->optional($data['nivel_riesgo'] ?? null),
            'observaciones' => $this->optional($data['observaciones'] ?? null),
        ];
    }

    private function required(mixed $value): string
    {
        if (!is_string($value) || trim($value) === '') {
            throw new RuntimeException('Razon social es obligatoria.');
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
        if (preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/', $value, $m) === 1) {
            return sprintf('%04d-%02d-%02d', (int) $m[3], (int) $m[2], (int) $m[1]);
        }
        throw new RuntimeException('Fecha de creacion invalida.');
    }

    private function url(mixed $value): ?string
    {
        if (!is_string($value) || trim($value) === '') {
            return null;
        }
        $value = trim($value);
        if (!filter_var($value, FILTER_VALIDATE_URL) || !str_starts_with($value, 'http')) {
            throw new RuntimeException('El enlace de carpeta documental no es valido.');
        }
        return $value;
    }

    /** @return list<array<string, mixed>> */
    private function defaultRows(): array
    {
        return [
            [
                'codigo' => 'EMP-001',
                'razon_social' => 'Soluciones Comerciales y Constructivas',
                'nombre_comercial' => 'Sk&c',
                'nit' => null,
                'matricula_mercantil' => null,
                'domicilio_principal' => null,
                'ciudad_domicilio' => null,
                'telefono' => null,
                'correo_contacto' => null,
                'tipo_sociedad' => 'S.A.S.',
                'ano_creacion' => '2013-05-31',
                'actividad_principal' => 'CIIU 6820 - Actividades inmobiliarias realizadas a cambio de una retribucion o por contrata. Incluye los servicios de bienes raices prestados por terceros.',
                'empresa_operativa_patrimonial' => 'Operativa',
                'representante_legal' => 'Nassif Abuita Nassar',
                'tiene_junta_directiva' => 'No',
                'tiene_revisor_fiscal' => 'No',
                'tiene_inmuebles_propios' => 'Si',
                'tiene_empleados' => 'Si',
                'contador_responsable' => null,
                'correo_contador' => null,
                'periodicidad_iva' => null,
                'periodicidad_retencion' => null,
                'periodicidad_ica' => null,
                'periodicidad_estados_financieros' => null,
                'cierre_fiscal' => null,
                'estado_rut' => null,
                'estado_camara_comercio' => null,
                'estado_obligaciones_tributarias' => null,
                'carpeta_documental_nombre' => null,
                'carpeta_documental_url' => null,
                'carpeta_documental_observaciones' => null,
                'deudas_creditos_importantes' => 'Prestamos Bancarios',
                'estatutos_actualizados' => 'No',
                'acuerdo_accionistas' => 'No',
                'libro_accionistas_actualizado' => 'No',
                'nivel_riesgo' => 'Medio',
                'observaciones' => 'Definir las siglas que cambia a SK&C-SuCasa Inmobiliaria. No contamos con Junta directiva: los organos de direccion son la Asamblea General.',
            ],
            [
                'codigo' => 'EMP-002',
                'razon_social' => 'Disenos y Soluciones Creativas',
                'nombre_comercial' => 'DSC',
                'nit' => null,
                'matricula_mercantil' => null,
                'domicilio_principal' => null,
                'ciudad_domicilio' => null,
                'telefono' => null,
                'correo_contacto' => null,
                'tipo_sociedad' => 'S.A.S.',
                'ano_creacion' => '2005-06-08',
                'actividad_principal' => 'CIIU 6820 - Actividades inmobiliarias realizadas a cambio de una retribucion o por contrata. Incluye los servicios de bienes raices prestados por terceros.',
                'empresa_operativa_patrimonial' => 'Patrimonial',
                'representante_legal' => 'Nassif Abuita Nassar',
                'tiene_junta_directiva' => 'Si',
                'tiene_revisor_fiscal' => 'No',
                'tiene_inmuebles_propios' => 'Si',
                'tiene_empleados' => 'No',
                'contador_responsable' => null,
                'correo_contador' => null,
                'periodicidad_iva' => null,
                'periodicidad_retencion' => null,
                'periodicidad_ica' => null,
                'periodicidad_estados_financieros' => null,
                'cierre_fiscal' => null,
                'estado_rut' => null,
                'estado_camara_comercio' => null,
                'estado_obligaciones_tributarias' => null,
                'carpeta_documental_nombre' => null,
                'carpeta_documental_url' => null,
                'carpeta_documental_observaciones' => null,
                'deudas_creditos_importantes' => 'No Posee',
                'estatutos_actualizados' => 'No',
                'acuerdo_accionistas' => 'No',
                'libro_accionistas_actualizado' => 'No',
                'nivel_riesgo' => 'Bajo',
                'observaciones' => 'Empresa patrimonial. No tiene operaciones comerciales; solo maneja 2 contratos de terceros que no se han podido trasladar a Soluciones Comerciales y Constructivas y el ingreso por el alquiler del inmueble propio.',
            ],
        ];
    }
}
