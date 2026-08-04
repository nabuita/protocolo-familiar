<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\ProtocolLists;
use App\Support\Uuid;
use PDO;
use RuntimeException;

final readonly class PatrimonioModel
{
    public function __construct(private PDO $pdo)
    {
        $this->ensureSchema();
    }

    /** @return list<array<string, mixed>> */
    public function all(): array
    {
        $rows = $this->pdo->query('SELECT * FROM protocolo_patrimonio_activos WHERE activo = 1 ORDER BY tipo_activo, CAST(SUBSTRING(codigo, 5) AS UNSIGNED), created_at')->fetchAll();
        return array_map([$this, 'decodeRow'], $rows);
    }

    /** @param array<string, mixed> $data */
    public function create(array $data, string $actor): array
    {
        $id = Uuid::v4();
        $codigo = $this->nextCode();
        $stmt = $this->pdo->prepare('INSERT INTO protocolo_patrimonio_activos (
            id, codigo, tipo_activo, nombre_descripcion, identificador, etiqueta_identificador,
            titular, ambito_titular, empresa_relacionada, valor_adquisicion, fecha_adquisicion,
            valor_actual, fecha_corte_valor, metodo_valoracion, moneda, estado_soporte,
            nivel_riesgo, detalle_json, participaciones_json, fiducia_beneficiarios_json, subunidades_json, seguro_polizas_json, seguro_coberturas_json, seguro_equipos_json, seguro_movimientos_json,
            valoraciones_anuales_json, ingresos_anuales_json, gastos_anuales_json, observaciones, activo, created_at, updated_at, created_by, updated_by
        ) VALUES (
            :id, :codigo, :tipo_activo, :nombre_descripcion, :identificador, :etiqueta_identificador,
            :titular, :ambito_titular, :empresa_relacionada, :valor_adquisicion, :fecha_adquisicion,
            :valor_actual, :fecha_corte_valor, :metodo_valoracion, :moneda, :estado_soporte,
            :nivel_riesgo, :detalle_json, :participaciones_json, :fiducia_beneficiarios_json, :subunidades_json, :seguro_polizas_json, :seguro_coberturas_json, :seguro_equipos_json, :seguro_movimientos_json,
            :valoraciones_anuales_json, :ingresos_anuales_json, :gastos_anuales_json, :observaciones, 1, UTC_TIMESTAMP(6), UTC_TIMESTAMP(6), :created_by, :updated_by
        )');
        $stmt->execute($this->params($data) + ['id' => $id, 'codigo' => $codigo, 'created_by' => $actor, 'updated_by' => $actor]);
        return $this->find($id);
    }

    /** @param array<string, mixed> $data */
    public function update(string $id, array $data, string $actor): array
    {
        $stmt = $this->pdo->prepare('UPDATE protocolo_patrimonio_activos SET
            tipo_activo = :tipo_activo,
            nombre_descripcion = :nombre_descripcion,
            identificador = :identificador,
            etiqueta_identificador = :etiqueta_identificador,
            titular = :titular,
            ambito_titular = :ambito_titular,
            empresa_relacionada = :empresa_relacionada,
            valor_adquisicion = :valor_adquisicion,
            fecha_adquisicion = :fecha_adquisicion,
            valor_actual = :valor_actual,
            fecha_corte_valor = :fecha_corte_valor,
            metodo_valoracion = :metodo_valoracion,
            moneda = :moneda,
            estado_soporte = :estado_soporte,
            nivel_riesgo = :nivel_riesgo,
            detalle_json = :detalle_json,
            participaciones_json = :participaciones_json,
            fiducia_beneficiarios_json = :fiducia_beneficiarios_json,
            subunidades_json = :subunidades_json,
            seguro_polizas_json = :seguro_polizas_json,
            seguro_coberturas_json = :seguro_coberturas_json,
            seguro_equipos_json = :seguro_equipos_json,
            seguro_movimientos_json = :seguro_movimientos_json,
            valoraciones_anuales_json = :valoraciones_anuales_json,
            ingresos_anuales_json = :ingresos_anuales_json,
            gastos_anuales_json = :gastos_anuales_json,
            observaciones = :observaciones,
            updated_at = UTC_TIMESTAMP(6),
            updated_by = :updated_by
            WHERE id = :id AND activo = 1');
        $stmt->execute($this->params($data) + ['id' => $id, 'updated_by' => $actor]);
        return $this->find($id);
    }

    public function deactivate(string $id, string $actor): void
    {
        $stmt = $this->pdo->prepare('UPDATE protocolo_patrimonio_activos SET activo = 0, updated_at = UTC_TIMESTAMP(6), updated_by = :actor WHERE id = :id AND activo = 1');
        $stmt->execute(['id' => $id, 'actor' => $actor]);
    }

    /** @return array<string, mixed> */
    public function find(string $id): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM protocolo_patrimonio_activos WHERE id = :id AND activo = 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        if (!is_array($row)) {
            throw new RuntimeException('Activo no encontrado.');
        }
        return $this->decodeRow($row);
    }

    /** @return array{activos:int,valor_actual:float,valor_adquisicion:float} */
    public function summary(): array
    {
        $row = $this->pdo->query('SELECT COUNT(*) activos, COALESCE(SUM(valor_actual), 0) valor_actual, COALESCE(SUM(valor_adquisicion), 0) valor_adquisicion FROM protocolo_patrimonio_activos WHERE activo = 1')->fetch();
        return [
            'activos' => (int) ($row['activos'] ?? 0),
            'valor_actual' => (float) ($row['valor_actual'] ?? 0),
            'valor_adquisicion' => (float) ($row['valor_adquisicion'] ?? 0),
        ];
    }

    /** @return list<array<string,mixed>> */
    public function summaryByType(): array
    {
        $groups = [];
        foreach ($this->all() as $row) {
            $type = is_string($row['tipo_activo'] ?? null) && $row['tipo_activo'] !== '' ? $row['tipo_activo'] : 'Sin categoria';
            if (!isset($groups[$type])) {
                $groups[$type] = [
                    'tipo_activo' => $type,
                    'activos' => 0,
                    'unidades' => 0,
                    'valor_comercial_total' => 0.0,
                    'valor_comercial_participacion' => 0.0,
                    'ingreso_mensual_total' => 0.0,
                    'ingreso_mensual_participacion' => 0.0,
                    'ingreso_bruto_anual' => 0.0,
                    'gastos_anuales' => 0.0,
                    'ino_anual' => 0.0,
                    'participacion_promedio' => 0.0,
                ];
            }

            $sharePercent = max(0.0, min(100.0, (float) ($row['participacion_total'] ?? 0)));
            $share = $sharePercent / 100;
            $commercialValue = $this->asNumber($row['valor_actual'] ?? null);
            $monthlyIncome = $this->assetMonthlyIncome($row);
            $analytics = is_array($row['patrimonio_analytics'] ?? null) ? $row['patrimonio_analytics'] : [];

            $groups[$type]['activos']++;
            $groups[$type]['unidades'] += $this->assetUnitCount($row);
            $groups[$type]['valor_comercial_total'] += $commercialValue;
            $groups[$type]['valor_comercial_participacion'] += $commercialValue * $share;
            $groups[$type]['ingreso_mensual_total'] += $monthlyIncome;
            $groups[$type]['ingreso_mensual_participacion'] += $monthlyIncome * $share;
            $groups[$type]['ingreso_bruto_anual'] += $this->asNumber($analytics['ingreso_bruto_anual'] ?? null);
            $groups[$type]['gastos_anuales'] += $this->asNumber($analytics['gastos_anuales'] ?? null);
            $groups[$type]['ino_anual'] += $this->asNumber($analytics['ino_anual'] ?? null);
            $groups[$type]['participacion_promedio'] += $sharePercent;
        }

        foreach ($groups as &$group) {
            $group['participacion_promedio'] = $group['activos'] > 0 ? $group['participacion_promedio'] / $group['activos'] : 0.0;
        }
        unset($group);

        ksort($groups, SORT_NATURAL | SORT_FLAG_CASE);
        return array_values($groups);
    }

    private function ensureSchema(): void
    {
        $this->pdo->exec('CREATE TABLE IF NOT EXISTS protocolo_patrimonio_activos (
            id CHAR(36) NOT NULL PRIMARY KEY,
            codigo VARCHAR(20) NOT NULL UNIQUE,
            tipo_activo VARCHAR(120) NOT NULL,
            nombre_descripcion VARCHAR(240) NOT NULL,
            identificador VARCHAR(220) NULL,
            etiqueta_identificador VARCHAR(160) NULL,
            titular VARCHAR(220) NULL,
            ambito_titular VARCHAR(120) NULL,
            empresa_relacionada VARCHAR(240) NULL,
            valor_adquisicion DECIMAL(18,2) NULL,
            fecha_adquisicion DATE NULL,
            valor_actual DECIMAL(18,2) NULL,
            fecha_corte_valor DATE NULL,
            metodo_valoracion VARCHAR(120) NULL,
            moneda VARCHAR(20) NULL,
            estado_soporte VARCHAR(120) NULL,
            nivel_riesgo VARCHAR(80) NULL,
            detalle_json JSON NULL,
            participaciones_json JSON NULL,
            fiducia_beneficiarios_json JSON NULL,
            subunidades_json JSON NULL,
            seguro_polizas_json JSON NULL,
            seguro_coberturas_json JSON NULL,
            seguro_equipos_json JSON NULL,
            seguro_movimientos_json JSON NULL,
            valoraciones_anuales_json JSON NULL,
            ingresos_anuales_json JSON NULL,
            gastos_anuales_json JSON NULL,
            observaciones TEXT NULL,
            activo TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME(6) NOT NULL,
            updated_at DATETIME(6) NOT NULL,
            created_by VARCHAR(120) NULL,
            updated_by VARCHAR(120) NULL,
            INDEX idx_protocolo_patrimonio_tipo_codigo (activo, tipo_activo, codigo)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $this->ensureColumn('participaciones_json', 'ALTER TABLE protocolo_patrimonio_activos ADD COLUMN participaciones_json JSON NULL AFTER detalle_json');
        $this->ensureColumn('fiducia_beneficiarios_json', 'ALTER TABLE protocolo_patrimonio_activos ADD COLUMN fiducia_beneficiarios_json JSON NULL AFTER participaciones_json');
        $this->ensureColumn('subunidades_json', 'ALTER TABLE protocolo_patrimonio_activos ADD COLUMN subunidades_json JSON NULL AFTER fiducia_beneficiarios_json');
        $this->ensureColumn('seguro_polizas_json', 'ALTER TABLE protocolo_patrimonio_activos ADD COLUMN seguro_polizas_json JSON NULL AFTER subunidades_json');
        $this->ensureColumn('seguro_coberturas_json', 'ALTER TABLE protocolo_patrimonio_activos ADD COLUMN seguro_coberturas_json JSON NULL AFTER seguro_polizas_json');
        $this->ensureColumn('seguro_equipos_json', 'ALTER TABLE protocolo_patrimonio_activos ADD COLUMN seguro_equipos_json JSON NULL AFTER seguro_coberturas_json');
        $this->ensureColumn('seguro_movimientos_json', 'ALTER TABLE protocolo_patrimonio_activos ADD COLUMN seguro_movimientos_json JSON NULL AFTER seguro_equipos_json');
        $this->ensureColumn('valoraciones_anuales_json', 'ALTER TABLE protocolo_patrimonio_activos ADD COLUMN valoraciones_anuales_json JSON NULL AFTER seguro_movimientos_json');
        $this->ensureColumn('ingresos_anuales_json', 'ALTER TABLE protocolo_patrimonio_activos ADD COLUMN ingresos_anuales_json JSON NULL AFTER valoraciones_anuales_json');
        $this->ensureColumn('gastos_anuales_json', 'ALTER TABLE protocolo_patrimonio_activos ADD COLUMN gastos_anuales_json JSON NULL AFTER ingresos_anuales_json');
    }

    private function nextCode(): string
    {
        $next = (int) $this->pdo->query("SELECT COALESCE(MAX(CAST(SUBSTRING(codigo, 5) AS UNSIGNED)), 0) + 1 FROM protocolo_patrimonio_activos")->fetchColumn();
        return sprintf('PAT-%03d', $next);
    }

    /** @param array<string, mixed> $data @return array<string, mixed> */
    private function params(array $data): array
    {
        $tipo = $this->required($data['tipo_activo'] ?? null, 'Tipo de activo es obligatorio.');
        $schema = ProtocolLists::patrimonioSchemas()[$tipo] ?? null;
        $details = $this->details($tipo, is_array($data['detalle'] ?? null) ? $data['detalle'] : []);
        $participaciones = $this->participaciones(is_array($data['participaciones'] ?? null) ? $data['participaciones'] : []);
        $fiduciaBeneficiarios = $tipo === 'Inmueble' ? $this->fiduciaBeneficiarios(is_array($data['fiducia_beneficiarios'] ?? null) ? $data['fiducia_beneficiarios'] : []) : [];
        $subunidades = $tipo === 'Inmueble' ? $this->subunidadesArrendables(is_array($data['subunidades'] ?? null) ? $data['subunidades'] : []) : [];
        $seguroPolizas = $this->supportsInsurance($tipo) ? $this->seguroPolizas(is_array($data['seguro_polizas'] ?? null) ? $data['seguro_polizas'] : []) : [];
        $seguroCoberturas = $this->supportsInsurance($tipo) ? $this->seguroCoberturas(is_array($data['seguro_coberturas'] ?? null) ? $data['seguro_coberturas'] : []) : [];
        $seguroEquipos = $this->supportsInsurance($tipo) ? $this->seguroEquipos(is_array($data['seguro_equipos'] ?? null) ? $data['seguro_equipos'] : []) : [];
        $seguroMovimientos = $this->supportsInsurance($tipo) ? $this->seguroMovimientos(is_array($data['seguro_movimientos'] ?? null) ? $data['seguro_movimientos'] : []) : [];
        $valoraciones = $tipo === 'Inmueble' ? $this->valoracionesAnuales(is_array($data['valoraciones_anuales'] ?? null) ? $data['valoraciones_anuales'] : []) : [];
        $gastos = $tipo === 'Inmueble' ? $this->gastosAnuales(is_array($data['gastos_anuales'] ?? null) ? $data['gastos_anuales'] : []) : [];
        $ingresos = $tipo === 'Inmueble' ? $this->ingresosAnuales(is_array($data['ingresos_anuales'] ?? null) ? $data['ingresos_anuales'] : [], $participaciones, $gastos) : [];
        return [
            'tipo_activo' => $tipo,
            'nombre_descripcion' => $this->required($data['nombre_descripcion'] ?? null, 'Nombre o descripcion es obligatorio.'),
            'identificador' => $this->optional($data['identificador'] ?? null),
            'etiqueta_identificador' => is_array($schema) ? $schema['identifier'] : 'Identificador',
            'titular' => $this->optional($data['titular'] ?? null),
            'ambito_titular' => $this->optional($data['ambito_titular'] ?? null),
            'empresa_relacionada' => $this->optional($data['empresa_relacionada'] ?? null),
            'valor_adquisicion' => $this->money($data['valor_adquisicion'] ?? null),
            'fecha_adquisicion' => $this->date($data['fecha_adquisicion'] ?? null),
            'valor_actual' => $this->money($data['valor_actual'] ?? null),
            'fecha_corte_valor' => $this->date($data['fecha_corte_valor'] ?? null),
            'metodo_valoracion' => $this->optional($data['metodo_valoracion'] ?? null),
            'moneda' => $this->optional($data['moneda'] ?? null) ?? 'COP',
            'estado_soporte' => $this->optional($data['estado_soporte'] ?? null),
            'nivel_riesgo' => $this->optional($data['nivel_riesgo'] ?? null),
            'detalle_json' => json_encode($details, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
            'participaciones_json' => json_encode($participaciones, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
            'fiducia_beneficiarios_json' => json_encode($fiduciaBeneficiarios, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
            'subunidades_json' => json_encode($subunidades, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
            'seguro_polizas_json' => json_encode($seguroPolizas, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
            'seguro_coberturas_json' => json_encode($seguroCoberturas, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
            'seguro_equipos_json' => json_encode($seguroEquipos, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
            'seguro_movimientos_json' => json_encode($seguroMovimientos, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
            'valoraciones_anuales_json' => json_encode($valoraciones, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
            'ingresos_anuales_json' => json_encode($ingresos, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
            'gastos_anuales_json' => json_encode($gastos, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
            'observaciones' => $this->optional($data['observaciones'] ?? null),
        ];
    }

    private function supportsInsurance(string $tipo): bool
    {
        return in_array($tipo, ['Inmueble', 'Vehiculo/Maquinaria/Equipo'], true);
    }

    /** @param array<string,mixed> $row */
    private function assetMonthlyIncome(array $row): float
    {
        $details = is_array($row['detalle'] ?? null) ? $row['detalle'] : [];
        $subunits = is_array($row['subunidades'] ?? null) ? $row['subunidades'] : [];
        $subunitIncome = 0.0;
        foreach ($subunits as $subunit) {
            if (is_array($subunit)) {
                $subunitIncome += $this->asNumber($subunit['canon_mensual'] ?? null);
            }
        }
        if ($subunitIncome > 0) {
            return $subunitIncome;
        }

        foreach ([
            'canon_arrendamiento',
            'ingreso_bruto_mensual',
            'renta_real_mensual',
            'renta_potencial_mensual',
            'ingreso_mensual_asociado',
            'ingresos_generados',
            'producto_ingresos_periodo',
            'otros_ingresos_mensuales',
        ] as $field) {
            $value = $this->asNumber($details[$field] ?? null);
            if ($value > 0) {
                return $value;
            }
        }

        return 0.0;
    }

    /** @param array<string,mixed> $row */
    private function assetUnitCount(array $row): int
    {
        $subunits = is_array($row['subunidades'] ?? null) ? array_filter($row['subunidades'], 'is_array') : [];
        return max(1, count($subunits));
    }

    private function asNumber(mixed $value): float
    {
        if ($value === null || trim((string) $value) === '') {
            return 0.0;
        }
        $clean = str_replace(['$', '%', ' ', "\xc2\xa0"], '', (string) $value);
        if (str_contains($clean, ',')) {
            $clean = str_replace('.', '', $clean);
            $clean = str_replace(',', '.', $clean);
        } elseif (substr_count($clean, '.') > 1 || preg_match('/\.\d{3}$/', $clean) === 1) {
            $clean = str_replace('.', '', $clean);
        }
        return is_numeric($clean) ? (float) $clean : 0.0;
    }

    /** @param array<string,mixed> $detail */
    private function details(string $tipo, array $detail): array
    {
        $schema = ProtocolLists::patrimonioSchemas()[$tipo]['fields'] ?? [];
        $clean = [];
        foreach ($schema as $field) {
            $name = $field['name'];
            $value = $detail[$name] ?? null;
            $clean[$name] = is_string($value) && trim($value) !== '' ? trim($value) : null;
        }
        return $clean;
    }

    /** @param array<int|string,mixed> $rows @return list<array{tipo_participante:?string,participante:?string,calidad:?string,porcentaje:?float,soporte_observaciones:?string}> */
    private function participaciones(array $rows): array
    {
        $clean = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $tipo = $this->optional($row['tipo_participante'] ?? null);
            $participante = $this->optional($row['participante'] ?? null);
            $calidad = $this->optional($row['calidad'] ?? null);
            $porcentaje = $this->percent($row['porcentaje'] ?? null);
            $soporte = $this->optional($row['soporte_observaciones'] ?? null);
            if ($tipo === null && $participante === null && $calidad === null && $porcentaje === null && $soporte === null) {
                continue;
            }
            $clean[] = [
                'tipo_participante' => $tipo,
                'participante' => $participante,
                'calidad' => $calidad,
                'porcentaje' => $porcentaje,
                'soporte_observaciones' => $soporte,
            ];
        }
        return $clean;
    }

    /** @param array<int|string,mixed> $rows @return list<array{beneficiario:?string,calidad:?string,porcentaje:?float,condicion:?string,soporte_observaciones:?string}> */
    private function fiduciaBeneficiarios(array $rows): array
    {
        $clean = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $beneficiario = $this->optional($row['beneficiario'] ?? null);
            $calidad = $this->optional($row['calidad'] ?? null);
            $porcentaje = $this->percent($row['porcentaje'] ?? null);
            $condicion = $this->optional($row['condicion'] ?? null);
            $soporte = $this->optional($row['soporte_observaciones'] ?? null);
            if ($beneficiario === null && $calidad === null && $porcentaje === null && $condicion === null && $soporte === null) {
                continue;
            }
            $clean[] = [
                'beneficiario' => $beneficiario,
                'calidad' => $calidad,
                'porcentaje' => $porcentaje,
                'condicion' => $condicion,
                'soporte_observaciones' => $soporte,
            ];
        }
        return $clean;
    }

    /** @param array<int|string,mixed> $rows @return list<array<string,mixed>> */
    private function subunidadesArrendables(array $rows): array
    {
        $clean = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $item = [
                'codigo_subunidad' => $this->optional($row['codigo_subunidad'] ?? null),
                'nombre_subunidad' => $this->optional($row['nombre_subunidad'] ?? null),
                'area' => $this->optional($row['area'] ?? null),
                'uso' => $this->optional($row['uso'] ?? null),
                'arrendatario' => $this->optional($row['arrendatario'] ?? null),
                'canon_mensual' => $this->money($row['canon_mensual'] ?? null),
                'administracion_mensual' => $this->money($row['administracion_mensual'] ?? null),
                'iva' => $this->money($row['iva'] ?? null),
                'retencion' => $this->money($row['retencion'] ?? null),
                'contrato_soporte' => $this->optional($row['contrato_soporte'] ?? null),
                'fecha_inicio' => $this->date($row['fecha_inicio'] ?? null),
                'fecha_fin' => $this->date($row['fecha_fin'] ?? null),
                'estado_ocupacion' => $this->optional($row['estado_ocupacion'] ?? null),
                'observaciones' => $this->optional($row['observaciones'] ?? null),
            ];
            if (array_filter($item, static fn(mixed $value): bool => $value !== null) === []) {
                continue;
            }
            $clean[] = $item;
        }
        return $clean;
    }

    /** @param array<int|string,mixed> $rows @return list<array<string,mixed>> */
    private function valoracionesAnuales(array $rows): array
    {
        $clean = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $item = [
                'ano' => $this->year($row['ano'] ?? null),
                'valor_catastral' => $this->money($row['valor_catastral'] ?? null),
                'valor_comercial' => $this->money($row['valor_comercial'] ?? null),
                'fecha_corte' => $this->date($row['fecha_corte'] ?? null),
                'fuente' => $this->optional($row['fuente'] ?? null),
                'observaciones' => $this->optional($row['observaciones'] ?? null),
            ];
            if ($item['ano'] === null && $item['valor_catastral'] === null && $item['valor_comercial'] === null && $item['fecha_corte'] === null && $item['fuente'] === null && $item['observaciones'] === null) {
                continue;
            }
            if ($item['ano'] === null) {
                throw new RuntimeException('El historial de valores requiere ano.');
            }
            $clean[] = $item;
        }
        usort($clean, static fn(array $a, array $b): int => (int) $a['ano'] <=> (int) $b['ano']);
        return $clean;
    }

    /** @param array<int|string,mixed> $rows @return array<int,array<string,mixed>> */
    private function gastosAnuales(array $rows): array
    {
        $fields = [
            'predial',
            'administracion',
            'seguros',
            'mantenimiento',
            'reparaciones',
            'servicios_publicos',
            'valorizacion',
            'impuestos',
            'honorarios_administracion',
            'comisiones',
            'juridicos_notariales',
            'financieros_hipoteca',
            'adecuaciones',
            'otros',
        ];
        $clean = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $item = ['ano' => $this->year($row['ano'] ?? null)];
            $total = 0.0;
            foreach ($fields as $field) {
                $item[$field] = $this->money($row[$field] ?? null);
                $total += (float) ($item[$field] ?? 0);
            }
            $item['total_gastos'] = $total;
            $item['observaciones'] = $this->optional($row['observaciones'] ?? null);
            if ($item['ano'] === null && $total <= 0 && $item['observaciones'] === null) {
                continue;
            }
            if ($item['ano'] === null) {
                throw new RuntimeException('El historial de gastos requiere ano.');
            }
            $clean[] = $item;
        }
        usort($clean, static fn(array $a, array $b): int => (int) $a['ano'] <=> (int) $b['ano']);
        return $clean;
    }

    /** @param array<int|string,mixed> $rows @param list<array<string,mixed>> $participaciones @param list<array<string,mixed>> $gastos @return list<array<string,mixed>> */
    private function ingresosAnuales(array $rows, array $participaciones, array $gastos): array
    {
        $defaultShare = 0.0;
        foreach ($participaciones as $participacion) {
            $defaultShare += is_numeric($participacion['porcentaje'] ?? null) ? (float) $participacion['porcentaje'] : 0.0;
        }
        $defaultShare = max(0.0, min(100.0, $defaultShare));
        $expensesByYear = [];
        foreach ($gastos as $expense) {
            if (is_numeric($expense['ano'] ?? null)) {
                $expensesByYear[(int) $expense['ano']] = (float) ($expense['total_gastos'] ?? 0);
            }
        }
        $clean = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $year = $this->year($row['ano'] ?? null);
            $startDate = $this->date($row['fecha_inicio_vigencia'] ?? null);
            $endDate = $this->date($row['fecha_fin_vigencia'] ?? null);
            $canon = $this->money($row['canon_mensual'] ?? null);
            $newCanon = $this->money($row['nuevo_canon_mensual'] ?? null);
            $share = $this->percent($row['porcentaje_participacion'] ?? null);
            $share = $share ?? $defaultShare;
            $baseCanon = (float) (($newCanon ?? 0) > 0 ? $newCanon : ($canon ?? 0));
            $months = $year !== null ? ($this->decimal($row['meses_vigencia'] ?? null) ?? $this->vigencyMonthsInYear($year, $startDate, $endDate)) : 0.0;
            $annualCanon = $baseCanon * $months;
            $grossAnnual = $annualCanon * ($share / 100);
            $item = [
                'ano' => $year,
                'fecha_inicio_vigencia' => $startDate,
                'fecha_fin_vigencia' => $endDate,
                'meses_vigencia' => $months,
                'canon_mensual' => $canon,
                'canon_anual_total' => $annualCanon,
                'porcentaje_participacion' => $share,
                'incremento_porcentaje' => $this->percent($row['incremento_porcentaje'] ?? null),
                'incremento_valor' => $this->money($row['incremento_valor'] ?? null),
                'nuevo_canon_mensual' => $newCanon,
                'fecha_renovacion' => $this->date($row['fecha_renovacion'] ?? null),
                'ingreso_bruto_anual' => $grossAnnual,
                'ino_anual' => $grossAnnual,
                'observaciones' => $this->optional($row['observaciones'] ?? null),
            ];
            if ($year === null && $startDate === null && $endDate === null && $canon === null && $newCanon === null && $item['fecha_renovacion'] === null && $item['observaciones'] === null) {
                continue;
            }
            if ($year === null) {
                throw new RuntimeException('El historial de ingresos requiere ano.');
            }
            $clean[] = $item;
        }
        $incomeByYear = [];
        foreach ($clean as $item) {
            $incomeByYear[(int) $item['ano']] = ($incomeByYear[(int) $item['ano']] ?? 0.0) + (float) ($item['ingreso_bruto_anual'] ?? 0);
        }
        foreach ($clean as &$item) {
            $year = (int) $item['ano'];
            $gross = (float) ($item['ingreso_bruto_anual'] ?? 0);
            $annualGross = (float) ($incomeByYear[$year] ?? 0);
            $annualExpenses = (float) ($expensesByYear[$year] ?? 0);
            $expenseShare = $annualGross > 0 ? $annualExpenses * ($gross / $annualGross) : 0.0;
            $item['gastos_asignados'] = $expenseShare;
            $item['ino_anual'] = $gross - $expenseShare;
        }
        unset($item);
        usort($clean, static fn(array $a, array $b): int => (int) $a['ano'] <=> (int) $b['ano']);
        return $clean;
    }

    /** @param array<int|string,mixed> $rows @return list<array<string,mixed>> */
    private function seguroPolizas(array $rows): array
    {
        $clean = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $item = [
                'ano' => $this->year($row['ano'] ?? null),
                'tipo_documento' => $this->optional($row['tipo_documento'] ?? null),
                'ramo' => $this->optional($row['ramo'] ?? null),
                'aseguradora' => $this->optional($row['aseguradora'] ?? null),
                'intermediario' => $this->optional($row['intermediario'] ?? null),
                'agencia_expedidora' => $this->optional($row['agencia_expedidora'] ?? null),
                'codigo_agencia' => $this->optional($row['codigo_agencia'] ?? null),
                'numero_poliza' => $this->optional($row['numero_poliza'] ?? null),
                'numero_emision' => $this->optional($row['numero_emision'] ?? null),
                'numero_pago_electronico' => $this->optional($row['numero_pago_electronico'] ?? null),
                'modalidad_facturacion' => $this->optional($row['modalidad_facturacion'] ?? null),
                'coaseguro' => $this->optional($row['coaseguro'] ?? null),
                'tomador' => $this->optional($row['tomador'] ?? null),
                'asegurado' => $this->optional($row['asegurado'] ?? null),
                'beneficiario' => $this->optional($row['beneficiario'] ?? null),
                'direccion_riesgo' => $this->optional($row['direccion_riesgo'] ?? null),
                'ciudad_riesgo' => $this->optional($row['ciudad_riesgo'] ?? null),
                'actividad_riesgo' => $this->optional($row['actividad_riesgo'] ?? null),
                'tipo_riesgo' => $this->optional($row['tipo_riesgo'] ?? null),
                'fecha_inicio' => $this->date($row['fecha_inicio'] ?? null),
                'fecha_fin' => $this->date($row['fecha_fin'] ?? null),
                'fecha_renovacion' => $this->date($row['fecha_renovacion'] ?? null),
                'prima_neta' => $this->money($row['prima_neta'] ?? null),
                'iva' => $this->money($row['iva'] ?? null),
                'gastos_expedicion' => $this->money($row['gastos_expedicion'] ?? null),
                'prima_total' => $this->money($row['prima_total'] ?? null),
                'valor_asegurado_total' => $this->money($row['valor_asegurado_total'] ?? null),
                'deducible_general' => $this->optional($row['deducible_general'] ?? null),
                'forma_pago' => $this->optional($row['forma_pago'] ?? null),
                'numero_cuotas' => $this->positiveInt($row['numero_cuotas'] ?? null),
                'clausulado' => $this->optional($row['clausulado'] ?? null),
                'anexos_endosos' => $this->optional($row['anexos_endosos'] ?? null),
                'exclusiones_relevantes' => $this->optional($row['exclusiones_relevantes'] ?? null),
                'texto_aclaratorio' => $this->optional($row['texto_aclaratorio'] ?? null),
                'asistencias' => $this->optional($row['asistencias'] ?? null),
                'estado' => $this->optional($row['estado'] ?? null),
                'adoptada' => $this->optional($row['adoptada'] ?? null),
                'fecha_adopcion' => $this->date($row['fecha_adopcion'] ?? null),
                'criterio_adopcion' => $this->optional($row['criterio_adopcion'] ?? null),
                'soporte' => $this->optional($row['soporte'] ?? null),
                'observaciones' => $this->optional($row['observaciones'] ?? null),
            ];
            if (array_filter($item, static fn(mixed $value): bool => $value !== null) === []) {
                continue;
            }
            $clean[] = $item;
        }
        usort($clean, static fn(array $a, array $b): int => ((int) ($a['ano'] ?? 0) <=> (int) ($b['ano'] ?? 0)) ?: strcmp((string) ($a['fecha_inicio'] ?? ''), (string) ($b['fecha_inicio'] ?? '')));
        return $clean;
    }

    /** @param array<int|string,mixed> $rows @return list<array<string,mixed>> */
    private function seguroCoberturas(array $rows): array
    {
        $clean = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $item = [
                'ano' => $this->year($row['ano'] ?? null),
                'numero_poliza' => $this->optional($row['numero_poliza'] ?? null),
                'ramo' => $this->optional($row['ramo'] ?? null),
                'cobertura' => $this->optional($row['cobertura'] ?? null),
                'riesgo_cubierto' => $this->optional($row['riesgo_cubierto'] ?? null),
                'valor_asegurado' => $this->money($row['valor_asegurado'] ?? null),
                'porcentaje_invar' => $this->decimal($row['porcentaje_invar'] ?? null),
                'sublimite' => $this->optional($row['sublimite'] ?? null),
                'tasa' => $this->optional($row['tasa'] ?? null),
                'prima' => $this->money($row['prima'] ?? null),
                'deducible' => $this->optional($row['deducible'] ?? null),
                'fuente_valor_asegurado' => $this->optional($row['fuente_valor_asegurado'] ?? null),
                'fecha_inicio' => $this->date($row['fecha_inicio'] ?? null),
                'fecha_fin' => $this->date($row['fecha_fin'] ?? null),
                'fecha_renovacion' => $this->date($row['fecha_renovacion'] ?? null),
                'observaciones' => $this->optional($row['observaciones'] ?? null),
            ];
            if (array_filter($item, static fn(mixed $value): bool => $value !== null) === []) {
                continue;
            }
            $clean[] = $item;
        }
        return $clean;
    }

    /** @param array<int|string,mixed> $rows @return list<array<string,mixed>> */
    private function seguroMovimientos(array $rows): array
    {
        $clean = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $item = [
                'ano' => $this->year($row['ano'] ?? null),
                'fecha' => $this->date($row['fecha'] ?? null),
                'tipo_movimiento' => $this->optional($row['tipo_movimiento'] ?? null),
                'numero_poliza' => $this->optional($row['numero_poliza'] ?? null),
                'ramo' => $this->optional($row['ramo'] ?? null),
                'cobertura' => $this->optional($row['cobertura'] ?? null),
                'item' => $this->optional($row['item'] ?? null),
                'valor_variacion' => $this->money($row['valor_variacion'] ?? null),
                'estado_reporte' => $this->optional($row['estado_reporte'] ?? null),
                'fecha_reporte_aseguradora' => $this->date($row['fecha_reporte_aseguradora'] ?? null),
                'soporte' => $this->optional($row['soporte'] ?? null),
                'observaciones' => $this->optional($row['observaciones'] ?? null),
            ];
            if (array_filter($item, static fn(mixed $value): bool => $value !== null) === []) {
                continue;
            }
            $clean[] = $item;
        }
        usort($clean, static fn(array $a, array $b): int => ((int) ($a['ano'] ?? 0) <=> (int) ($b['ano'] ?? 0)) ?: strcmp((string) ($a['fecha'] ?? ''), (string) ($b['fecha'] ?? '')));
        return $clean;
    }

    /** @param array<int|string,mixed> $rows @return list<array<string,mixed>> */
    private function seguroEquipos(array $rows): array
    {
        $clean = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $item = [
                'ano' => $this->year($row['ano'] ?? null),
                'numero_poliza' => $this->optional($row['numero_poliza'] ?? null),
                'ramo' => $this->optional($row['ramo'] ?? null),
                'cobertura_asociada' => $this->optional($row['cobertura_asociada'] ?? null),
                'categoria_item' => $this->optional($row['categoria_item'] ?? null),
                'item' => $this->optional($row['item'] ?? null) ?? $this->optional($row['equipo'] ?? null),
                'descripcion' => $this->optional($row['descripcion'] ?? null),
                'unidad' => $this->optional($row['unidad'] ?? null),
                'cantidad' => $this->decimal($row['cantidad'] ?? null),
                'ubicacion' => $this->optional($row['ubicacion'] ?? null),
                'serial_referencia' => $this->optional($row['serial_referencia'] ?? null),
                'valor_compra' => $this->money($row['valor_compra'] ?? null),
                'fecha_adquisicion' => $this->date($row['fecha_adquisicion'] ?? null),
                'valor_reposicion_unitario' => $this->money($row['valor_reposicion_unitario'] ?? null),
                'valor_reposicion' => $this->money($row['valor_reposicion'] ?? null),
                'fuente_consulta' => $this->optional($row['fuente_consulta'] ?? null),
                'fecha_consulta' => $this->date($row['fecha_consulta'] ?? null),
                'ano_adquisicion' => $this->year($row['ano_adquisicion'] ?? null),
                'edad_anos' => $this->decimal($row['edad_anos'] ?? null),
                'vida_util_anos' => $this->decimal($row['vida_util_anos'] ?? null),
                'regla_demerito' => $this->optional($row['regla_demerito'] ?? null),
                'depreciacion_porcentaje' => $this->decimal($row['depreciacion_porcentaje'] ?? null),
                'depreciacion_valor' => $this->money($row['depreciacion_valor'] ?? null),
                'valor_asegurable_sugerido' => $this->money($row['valor_asegurable_sugerido'] ?? null),
                'incluye_terreno' => $this->optional($row['incluye_terreno'] ?? null),
                'fecha_inicio' => $this->date($row['fecha_inicio'] ?? null),
                'fecha_fin' => $this->date($row['fecha_fin'] ?? null),
                'fecha_renovacion' => $this->date($row['fecha_renovacion'] ?? null),
                'observaciones' => $this->optional($row['observaciones'] ?? null),
            ];
            if (array_filter($item, static fn(mixed $value): bool => $value !== null) === []) {
                continue;
            }
            $clean[] = $item;
        }
        return $clean;
    }

    private function ensureColumn(string $column, string $sql): void
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = "protocolo_patrimonio_activos" AND COLUMN_NAME = :column');
        $stmt->execute(['column' => $column]);
        if ((int) $stmt->fetchColumn() === 0) {
            $this->pdo->exec($sql);
        }
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

    private function date(mixed $value): ?string
    {
        if (!is_string($value) || trim($value) === '') {
            return null;
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', trim($value)) !== 1) {
            throw new RuntimeException('Fecha invalida.');
        }
        return trim($value);
    }

    private function year(mixed $value): ?int
    {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }
        if (preg_match('/^\d{4}$/', trim((string) $value)) !== 1) {
            throw new RuntimeException('Ano invalido.');
        }
        $year = (int) $value;
        if ($year < 1800 || $year > 2200) {
            throw new RuntimeException('Ano fuera de rango.');
        }
        return $year;
    }

    private function positiveInt(mixed $value): ?int
    {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }
        if (preg_match('/^\d{1,3}$/', trim((string) $value)) !== 1) {
            throw new RuntimeException('Numero invalido.');
        }
        return (int) $value;
    }

    private function decimal(mixed $value): ?float
    {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }
        $clean = str_replace([' ', "\xc2\xa0", '%'], '', (string) $value);
        $clean = str_replace(',', '.', $clean);
        if (!is_numeric($clean)) {
            throw new RuntimeException('Valor decimal invalido.');
        }
        $number = (float) $clean;
        return $number >= 0 ? $number : 0.0;
    }

    private function vigencyMonthsInYear(int $year, ?string $startDate, ?string $endDate): float
    {
        $yearStart = new \DateTimeImmutable(sprintf('%04d-01-01', $year));
        $yearEnd = new \DateTimeImmutable(sprintf('%04d-12-31', $year));
        $start = $startDate ? new \DateTimeImmutable($startDate) : $yearStart;
        $end = $endDate ? new \DateTimeImmutable($endDate) : $yearEnd;
        if ($start < $yearStart) {
            $start = $yearStart;
        }
        if ($end > $yearEnd) {
            $end = $yearEnd;
        }
        if ($end < $start) {
            return 0.0;
        }
        $days = (int) $start->diff($end)->days + 1;
        return round($days / (365.0 / 12.0), 2);
    }

    private function money(mixed $value): ?float
    {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }
        $clean = str_replace(['$', ' ', "\xc2\xa0"], '', (string) $value);
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

    private function percent(mixed $value): ?float
    {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }
        $clean = str_replace(['%', ' '], '', (string) $value);
        $clean = str_replace(',', '.', $clean);
        if (!is_numeric($clean)) {
            throw new RuntimeException('Porcentaje invalido.');
        }
        $number = (float) $clean;
        if ($number < 0 || $number > 100) {
            throw new RuntimeException('El porcentaje debe estar entre 0 y 100.');
        }
        return $number;
    }

    /** @param array<string,mixed> $row @return array<string,mixed> */
    private function decodeRow(array $row): array
    {
        $decoded = json_decode(is_string($row['detalle_json'] ?? null) ? $row['detalle_json'] : '{}', true);
        $row['detalle'] = is_array($decoded) ? $decoded : [];
        $participaciones = json_decode(is_string($row['participaciones_json'] ?? null) ? $row['participaciones_json'] : '[]', true);
        $row['participaciones'] = is_array($participaciones) ? array_values($participaciones) : [];
        $fiduciaBeneficiarios = json_decode(is_string($row['fiducia_beneficiarios_json'] ?? null) ? $row['fiducia_beneficiarios_json'] : '[]', true);
        $row['fiducia_beneficiarios'] = is_array($fiduciaBeneficiarios) ? array_values($fiduciaBeneficiarios) : [];
        $subunidades = json_decode(is_string($row['subunidades_json'] ?? null) ? $row['subunidades_json'] : '[]', true);
        $row['subunidades'] = is_array($subunidades) ? array_values($subunidades) : [];
        $seguroPolizas = json_decode(is_string($row['seguro_polizas_json'] ?? null) ? $row['seguro_polizas_json'] : '[]', true);
        $row['seguro_polizas'] = is_array($seguroPolizas) ? array_values($seguroPolizas) : [];
        $seguroCoberturas = json_decode(is_string($row['seguro_coberturas_json'] ?? null) ? $row['seguro_coberturas_json'] : '[]', true);
        $row['seguro_coberturas'] = is_array($seguroCoberturas) ? array_values($seguroCoberturas) : [];
        $seguroEquipos = json_decode(is_string($row['seguro_equipos_json'] ?? null) ? $row['seguro_equipos_json'] : '[]', true);
        $row['seguro_equipos'] = is_array($seguroEquipos) ? array_values($seguroEquipos) : [];
        $seguroMovimientos = json_decode(is_string($row['seguro_movimientos_json'] ?? null) ? $row['seguro_movimientos_json'] : '[]', true);
        $row['seguro_movimientos'] = is_array($seguroMovimientos) ? array_values($seguroMovimientos) : [];
        $valoraciones = json_decode(is_string($row['valoraciones_anuales_json'] ?? null) ? $row['valoraciones_anuales_json'] : '[]', true);
        $row['valoraciones_anuales'] = is_array($valoraciones) ? array_values($valoraciones) : [];
        $ingresos = json_decode(is_string($row['ingresos_anuales_json'] ?? null) ? $row['ingresos_anuales_json'] : '[]', true);
        $row['ingresos_anuales'] = is_array($ingresos) ? array_values($ingresos) : [];
        $gastos = json_decode(is_string($row['gastos_anuales_json'] ?? null) ? $row['gastos_anuales_json'] : '[]', true);
        $row['gastos_anuales'] = is_array($gastos) ? array_values($gastos) : [];
        $total = 0.0;
        foreach ($row['participaciones'] as $participacion) {
            if (is_array($participacion) && is_numeric($participacion['porcentaje'] ?? null)) {
                $total += (float) $participacion['porcentaje'];
            }
        }
        $row['participacion_total'] = min(100.0, $total);
        $row['participacion_pendiente'] = max(0.0, 100.0 - $total);
        $row['patrimonio_analytics'] = $this->patrimonioAnalytics($row);
        return $row;
    }

    /** @param array<string,mixed> $row @return array<string,mixed> */
    private function patrimonioAnalytics(array $row): array
    {
        $incomeRows = is_array($row['ingresos_anuales'] ?? null) ? $row['ingresos_anuales'] : [];
        $expenseRows = is_array($row['gastos_anuales'] ?? null) ? $row['gastos_anuales'] : [];
        $valueRows = is_array($row['valoraciones_anuales'] ?? null) ? $row['valoraciones_anuales'] : [];
        $latestIncome = $this->latestIncomeYear($incomeRows);
        $latestExpense = $this->latestYearRow($expenseRows);
        $latestValue = $this->latestYearRow($valueRows);
        $commercial = $this->asNumber($latestValue['valor_comercial'] ?? ($row['valor_actual'] ?? null));
        $gross = $this->asNumber($latestIncome['ingreso_bruto_anual'] ?? null);
        $expenses = $this->asNumber($latestExpense['total_gastos'] ?? null);
        $ino = $gross - $expenses;
        $share = max(0.0, min(100.0, (float) ($row['participacion_total'] ?? 0))) / 100;
        $basis = $commercial * $share;
        return [
            'ultimo_ano' => $latestIncome['ano'] ?? $latestExpense['ano'] ?? $latestValue['ano'] ?? null,
            'valor_catastral' => $this->asNumber($latestValue['valor_catastral'] ?? null),
            'valor_comercial' => $commercial,
            'ingreso_bruto_anual' => $gross,
            'gastos_anuales' => $expenses,
            'ino_anual' => $ino,
            'rentabilidad_anual' => $basis > 0 ? ($ino / $basis) * 100 : null,
        ];
    }

    /** @param list<array<string,mixed>> $rows @return array<string,mixed> */
    private function latestYearRow(array $rows): array
    {
        $latest = [];
        foreach ($rows as $row) {
            if (!is_array($row) || !is_numeric($row['ano'] ?? null)) {
                continue;
            }
            if ($latest === [] || (int) $row['ano'] >= (int) ($latest['ano'] ?? 0)) {
                $latest = $row;
            }
        }
        return $latest;
    }

    /** @param list<array<string,mixed>> $rows @return array<string,mixed> */
    private function latestIncomeYear(array $rows): array
    {
        $latestYear = null;
        $aggregate = [
            'ano' => null,
            'ingreso_bruto_anual' => 0.0,
            'ino_anual' => 0.0,
        ];
        foreach ($rows as $row) {
            if (!is_array($row) || !is_numeric($row['ano'] ?? null)) {
                continue;
            }
            $year = (int) $row['ano'];
            if ($latestYear === null || $year > $latestYear) {
                $latestYear = $year;
                $aggregate = ['ano' => $year, 'ingreso_bruto_anual' => 0.0, 'ino_anual' => 0.0];
            }
            if ($year === $latestYear) {
                $aggregate['ingreso_bruto_anual'] += $this->asNumber($row['ingreso_bruto_anual'] ?? null);
                $aggregate['ino_anual'] += $this->asNumber($row['ino_anual'] ?? null);
            }
        }
        return $latestYear === null ? [] : $aggregate;
    }
}
