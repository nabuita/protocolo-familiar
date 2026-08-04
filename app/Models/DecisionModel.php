<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\Uuid;
use PDO;
use RuntimeException;

final readonly class DecisionModel
{
    private string $seedPath;

    /** @var list<string> */
    private const APPLICABILITY = ['Pendiente de determinar', 'Aplica ahora', 'Aplicará en el futuro', 'No aplica'];
    /** @var list<string> */
    private const DECISION_STATES = ['Pendiente de analizar', 'En análisis', 'Requiere información', 'Aplazada', 'Aprobada', 'Rechazada', 'Requiere ajuste'];
    /** @var list<string> */
    private const IMPLEMENTATION_STATES = ['No iniciada', 'En preparación', 'En ejecución', 'Bloqueada', 'Implementada', 'Verificada', 'Cancelada'];
    /** @var list<string> */
    private const FAMILY_PRIORITIES = ['Crítica', 'Alta', 'Media', 'Baja'];
    /** @var list<string> */
    private const PROVISIONAL_STATES = ['Propuesto', 'En revisión jurídica', 'Aprobado para incorporación', 'Rechazado', 'Integrado en otro documento'];

    public function __construct(private PDO $pdo, string $rootPath)
    {
        $this->seedPath = rtrim($rootPath, '/\\') . '/app/Support/DecisionSeedData.json';
        $this->ensureSchema();
        $this->seedCatalog();
    }

    /** @return array<string,list<string>> */
    public function options(): array
    {
        return [
            'aplicabilidad' => self::APPLICABILITY,
            'estado_decision' => self::DECISION_STATES,
            'estado_implementacion' => self::IMPLEMENTATION_STATES,
            'prioridad_familiar' => self::FAMILY_PRIORITIES,
            'estado_documento_provisional' => self::PROVISIONAL_STATES,
        ];
    }

    /** @return list<array<string,mixed>> */
    public function all(): array
    {
        $rows = $this->pdo->query('SELECT
                c.codigo categoria_codigo,
                c.nombre categoria_nombre,
                c.orden categoria_orden,
                d.id catalogo_id,
                d.codigo,
                d.tema,
                d.pregunta,
                d.objetivo,
                d.opciones_sugeridas,
                d.instrumento_requerido,
                d.documento_soporte,
                d.responsable responsable_sugerido,
                d.riesgo_sugerido,
                d.prioridad prioridad_sugerida,
                r.id respuesta_id,
                r.aplica,
                r.respuesta,
                r.estado_decision,
                r.estado_implementacion,
                r.responsable,
                r.fecha_objetivo,
                r.fecha_aprobacion,
                r.observaciones,
                r.prioridad_familiar,
                r.justificacion_aplicabilidad,
                r.fecha_proxima_revision,
                r.fecha_real_implementacion,
                r.verificado_por,
                r.fecha_verificacion,
                r.observacion_verificacion,
                r.motivo_bloqueo,
                r.motivo_aplazamiento_rechazo,
                r.justificacion_sin_fecha_objetivo,
                r.motivo_cambio,
                r.updated_at respuesta_updated_at
            FROM protocolo_decision_catalogo d
            INNER JOIN protocolo_decision_categorias c ON c.id = d.categoria_id
            LEFT JOIN protocolo_decisiones_respuestas r ON r.decision_catalogo_id = d.id AND r.activo = 1
            WHERE d.activo = 1 AND c.activo = 1
            ORDER BY c.orden, CAST(SUBSTRING(d.codigo, 5) AS UNSIGNED)')->fetchAll();

        $documents = $this->documentsByDecision();
        $history = $this->historyByDecision();
        $riskRequests = $this->riskRequestsByDecision();
        $dueSoonDays = $this->dueSoonDays();
        return array_map(function (array $row) use ($documents, $history, $riskRequests, $dueSoonDays): array {
            $row = $this->normalizeRow($row);
            $row['documentos'] = $documents[(string) $row['codigo']] ?? ['potenciales' => [], 'existentes' => [], 'pendientes' => [], 'resumen' => $this->emptyDocumentSummary()];
            $row['documentos'] = $this->documentsForApplicability($row, $row['documentos']);
            $row['alertas_calculadas'] = $this->calculatedAlerts($row, $row['documentos'], $dueSoonDays);
            $row['historial'] = $history[(string) $row['codigo']] ?? [];
            $row['revision_riesgos'] = $riskRequests[(string) $row['codigo']] ?? [];
            return $row;
        }, $rows);
    }

    /** @return array<string,int|float> */
    public function summary(): array
    {
        return $this->summaryFromRows($this->all());
    }

    /** @param list<array<string,mixed>> $rows @return array<string,int|float> */
    public function summaryFromRows(array $rows): array
    {
        $summary = ['total' => count($rows), 'respondidas' => 0, 'aprobadas' => 0, 'implementadas' => 0, 'verificadas' => 0, 'pendientes' => 0, 'vencidas' => 0, 'documentos_pendientes' => 0, 'provisionales' => 0, 'alertas_riesgo' => 0, 'avance' => 0.0];
        foreach ($rows as $row) {
            if (is_string($row['respuesta_id'] ?? null)) {
                $summary['respondidas']++;
            }
            if ($row['estado_decision'] === 'Aprobada') {
                $summary['aprobadas']++;
            }
            if ($row['estado_implementacion'] === 'Implementada') {
                $summary['implementadas']++;
            }
            if ($row['estado_implementacion'] === 'Verificada') {
                $summary['verificadas']++;
            }
            if ($row['estado_decision'] === 'Pendiente de analizar') {
                $summary['pendientes']++;
            }
            $alerts = is_array($row['alertas_calculadas'] ?? null) ? $row['alertas_calculadas'] : [];
            if (in_array('Vencida', $alerts, true)) {
                $summary['vencidas']++;
            }
            if ((int) ($row['documentos']['resumen']['pendientes'] ?? 0) > 0) {
                $summary['documentos_pendientes']++;
            }
            if ((int) ($row['documentos']['resumen']['provisionales'] ?? 0) > 0) {
                $summary['provisionales']++;
            }
            if (in_array('Posible riesgo pendiente de revisión humana', $alerts, true)) {
                $summary['alertas_riesgo']++;
            }
        }
        $summary['avance'] = $summary['total'] > 0 ? round(($summary['verificadas'] / $summary['total']) * 100, 1) : 0.0;
        return $summary;
    }

    /** @param array<string,mixed> $data @return array<string,mixed> */
    public function saveResponse(string $catalogCode, array $data, string $actor): array
    {
        $catalogId = $this->catalogId($catalogCode);
        $params = $this->responseParams($data, $catalogId) + ['catalogo_id' => $catalogId];
        $existingId = $this->responseId($catalogId);
        $before = $existingId === null ? null : $this->rawResponse($existingId);

        $ownTransaction = !$this->pdo->inTransaction();
        if ($ownTransaction) {
            $this->pdo->beginTransaction();
        }
        try {
            if ($existingId === null) {
                $id = Uuid::v4();
                $stmt = $this->pdo->prepare('INSERT INTO protocolo_decisiones_respuestas (
                    id, decision_catalogo_id, aplica, respuesta, estado_decision, estado_implementacion,
                    responsable, fecha_objetivo, fecha_aprobacion, observaciones, prioridad_familiar,
                    justificacion_aplicabilidad, fecha_proxima_revision, fecha_real_implementacion,
                    verificado_por, fecha_verificacion, observacion_verificacion, motivo_bloqueo,
                    motivo_aplazamiento_rechazo, justificacion_sin_fecha_objetivo, motivo_cambio,
                    activo, created_at, updated_at, created_by, updated_by
                ) VALUES (
                    :id, :catalogo_id, :aplica, :respuesta, :estado_decision, :estado_implementacion,
                    :responsable, :fecha_objetivo, :fecha_aprobacion, :observaciones, :prioridad_familiar,
                    :justificacion_aplicabilidad, :fecha_proxima_revision, :fecha_real_implementacion,
                    :verificado_por, :fecha_verificacion, :observacion_verificacion, :motivo_bloqueo,
                    :motivo_aplazamiento_rechazo, :justificacion_sin_fecha_objetivo, :motivo_cambio,
                    1, UTC_TIMESTAMP(6), UTC_TIMESTAMP(6), :created_by, :updated_by
                )');
                $stmt->execute($params + ['id' => $id, 'created_by' => $actor, 'updated_by' => $actor]);
                $this->writeHistory($id, $catalogId, [], $params, $actor, $params['motivo_cambio']);
            } else {
                $stmt = $this->pdo->prepare('UPDATE protocolo_decisiones_respuestas SET
                    aplica = :aplica,
                    respuesta = :respuesta,
                    estado_decision = :estado_decision,
                    estado_implementacion = :estado_implementacion,
                    responsable = :responsable,
                    fecha_objetivo = :fecha_objetivo,
                    fecha_aprobacion = :fecha_aprobacion,
                    observaciones = :observaciones,
                    prioridad_familiar = :prioridad_familiar,
                    justificacion_aplicabilidad = :justificacion_aplicabilidad,
                    fecha_proxima_revision = :fecha_proxima_revision,
                    fecha_real_implementacion = :fecha_real_implementacion,
                    verificado_por = :verificado_por,
                    fecha_verificacion = :fecha_verificacion,
                    observacion_verificacion = :observacion_verificacion,
                    motivo_bloqueo = :motivo_bloqueo,
                    motivo_aplazamiento_rechazo = :motivo_aplazamiento_rechazo,
                    justificacion_sin_fecha_objetivo = :justificacion_sin_fecha_objetivo,
                    motivo_cambio = :motivo_cambio,
                    updated_at = UTC_TIMESTAMP(6),
                    updated_by = :updated_by
                    WHERE id = :id AND activo = 1');
                $stmt->execute($params + ['id' => $existingId, 'updated_by' => $actor]);
                $this->writeHistory($existingId, $catalogId, is_array($before) ? $before : [], $params, $actor, $params['motivo_cambio']);
            }
            if ($ownTransaction) {
                $this->pdo->commit();
            }
        } catch (\Throwable $e) {
            if ($ownTransaction && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }

        return $this->findByCode($catalogCode);
    }

    /** @param array<string,mixed> $data @return array<string,mixed> */
    public function requestRiskReview(string $catalogCode, array $data, string $actor): array
    {
        $catalogId = $this->catalogId($catalogCode);
        $row = $this->findByCode($catalogCode);
        $alerts = is_array($row['alertas_calculadas'] ?? null) ? $row['alertas_calculadas'] : [];
        if (!in_array('Posible riesgo pendiente de revisión humana', $alerts, true)) {
            throw new RuntimeException('La decision no tiene alerta de riesgo pendiente de revision humana.');
        }

        $id = Uuid::v4();
        $stmt = $this->pdo->prepare('INSERT INTO protocolo_decision_revision_riesgos (
            id, decision_catalogo_id, alerta_sugerida, posible_causa, posible_consecuencia,
            responsable_solicita, fecha_solicitud, observaciones, estado, activo,
            created_at, updated_at, created_by, updated_by
        ) VALUES (
            :id, :decision_catalogo_id, :alerta_sugerida, :posible_causa, :posible_consecuencia,
            :responsable_solicita, UTC_DATE(), :observaciones, "Pendiente de revisión humana", 1,
            UTC_TIMESTAMP(6), UTC_TIMESTAMP(6), :created_by, :updated_by
        )');
        $stmt->execute([
            'id' => $id,
            'decision_catalogo_id' => $catalogId,
            'alerta_sugerida' => implode('; ', $alerts),
            'posible_causa' => $this->optional($data['posible_causa'] ?? null) ?? 'Alerta calculada desde 06_Decisiones.',
            'posible_consecuencia' => $this->optional($data['posible_consecuencia'] ?? null) ?? (string) ($row['riesgo_sugerido'] ?? ''),
            'responsable_solicita' => $this->optional($data['responsable_solicita'] ?? null) ?? $actor,
            'observaciones' => $this->optional($data['observaciones_riesgo'] ?? null),
            'created_by' => $actor,
            'updated_by' => $actor,
        ]);

        return $this->findByCode($catalogCode);
    }

    /** @return array<string,mixed> */
    public function findByCode(string $catalogCode): array
    {
        foreach ($this->all() as $row) {
            if ((string) $row['codigo'] === $catalogCode) {
                return $row;
            }
        }
        throw new RuntimeException('Decision no encontrada.');
    }

    private function ensureSchema(): void
    {
        $this->pdo->exec('CREATE TABLE IF NOT EXISTS protocolo_decision_categorias (
            id CHAR(36) NOT NULL PRIMARY KEY,
            codigo VARCHAR(20) NOT NULL UNIQUE,
            nombre VARCHAR(180) NOT NULL,
            orden INT NOT NULL,
            activo TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME(6) NOT NULL,
            updated_at DATETIME(6) NOT NULL,
            INDEX idx_decision_categorias_orden (activo, orden)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');

        $this->pdo->exec('CREATE TABLE IF NOT EXISTS protocolo_decision_catalogo (
            id CHAR(36) NOT NULL PRIMARY KEY,
            codigo VARCHAR(20) NOT NULL UNIQUE,
            categoria_id CHAR(36) NOT NULL,
            tema VARCHAR(220) NOT NULL,
            pregunta TEXT NOT NULL,
            objetivo TEXT NULL,
            opciones_sugeridas TEXT NULL,
            instrumento_requerido TEXT NULL,
            documento_soporte TEXT NULL,
            responsable VARCHAR(220) NULL,
            riesgo_sugerido TEXT NULL,
            prioridad VARCHAR(60) NULL,
            activo TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME(6) NOT NULL,
            updated_at DATETIME(6) NOT NULL,
            INDEX idx_decision_catalogo_categoria (activo, categoria_id),
            CONSTRAINT fk_decision_catalogo_categoria FOREIGN KEY (categoria_id) REFERENCES protocolo_decision_categorias(id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');

        $this->pdo->exec('CREATE TABLE IF NOT EXISTS protocolo_decision_documentos_catalogo (
            id CHAR(36) NOT NULL PRIMARY KEY,
            codigo VARCHAR(20) NOT NULL UNIQUE,
            nombre VARCHAR(260) NOT NULL,
            grupo VARCHAR(160) NULL,
            finalidad TEXT NULL,
            cuando_se_requiere TEXT NULL,
            responsable_sugerido VARCHAR(220) NULL,
            provisional TINYINT(1) NOT NULL DEFAULT 0,
            estado_provisional VARCHAR(80) NOT NULL DEFAULT "Propuesto",
            activo TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME(6) NOT NULL,
            updated_at DATETIME(6) NOT NULL,
            INDEX idx_decision_documentos_codigo (activo, codigo)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $this->ensureColumn('protocolo_decision_documentos_catalogo', 'estado_provisional', 'ALTER TABLE protocolo_decision_documentos_catalogo ADD COLUMN estado_provisional VARCHAR(80) NOT NULL DEFAULT "Propuesto" AFTER provisional');

        $this->pdo->exec('CREATE TABLE IF NOT EXISTS protocolo_decision_documento_mapeo (
            id CHAR(36) NOT NULL PRIMARY KEY,
            decision_catalogo_id CHAR(36) NOT NULL,
            documento_catalogo_id CHAR(36) NULL,
            documento_codigo VARCHAR(20) NOT NULL,
            documento_nombre VARCHAR(260) NOT NULL,
            tipo_relacion VARCHAR(80) NOT NULL,
            exigibilidad VARCHAR(80) NOT NULL,
            momento_requerido VARCHAR(220) NULL,
            responsable_obtener VARCHAR(220) NULL,
            responsable_aprobar VARCHAR(220) NULL,
            evidencia_implementacion VARCHAR(20) NOT NULL DEFAULT "No",
            justificacion TEXT NULL,
            observaciones TEXT NULL,
            activo TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME(6) NOT NULL,
            updated_at DATETIME(6) NOT NULL,
            UNIQUE KEY uq_decision_documento_mapeo (decision_catalogo_id, documento_codigo),
            INDEX idx_decision_documento_decision (activo, decision_catalogo_id),
            CONSTRAINT fk_decision_documento_decision FOREIGN KEY (decision_catalogo_id) REFERENCES protocolo_decision_catalogo(id),
            CONSTRAINT fk_decision_documento_catalogo FOREIGN KEY (documento_catalogo_id) REFERENCES protocolo_decision_documentos_catalogo(id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');

        $this->pdo->exec('CREATE TABLE IF NOT EXISTS protocolo_decisiones_respuestas (
            id CHAR(36) NOT NULL PRIMARY KEY,
            decision_catalogo_id CHAR(36) NOT NULL,
            aplica VARCHAR(60) NOT NULL DEFAULT "Pendiente de determinar",
            respuesta TEXT NULL,
            estado_decision VARCHAR(80) NOT NULL DEFAULT "Pendiente de analizar",
            estado_implementacion VARCHAR(80) NOT NULL DEFAULT "No iniciada",
            responsable VARCHAR(220) NULL,
            fecha_objetivo DATE NULL,
            fecha_aprobacion DATE NULL,
            observaciones TEXT NULL,
            prioridad_familiar VARCHAR(40) NULL,
            justificacion_aplicabilidad TEXT NULL,
            fecha_proxima_revision DATE NULL,
            fecha_real_implementacion DATE NULL,
            verificado_por VARCHAR(220) NULL,
            fecha_verificacion DATE NULL,
            observacion_verificacion TEXT NULL,
            motivo_bloqueo TEXT NULL,
            motivo_aplazamiento_rechazo TEXT NULL,
            justificacion_sin_fecha_objetivo TEXT NULL,
            motivo_cambio TEXT NULL,
            activo TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME(6) NOT NULL,
            updated_at DATETIME(6) NOT NULL,
            created_by VARCHAR(120) NULL,
            updated_by VARCHAR(120) NULL,
            UNIQUE KEY uq_decisiones_respuestas_catalogo (decision_catalogo_id, activo),
            INDEX idx_decisiones_respuestas_estado (activo, estado_decision, estado_implementacion),
            CONSTRAINT fk_decisiones_respuestas_catalogo FOREIGN KEY (decision_catalogo_id) REFERENCES protocolo_decision_catalogo(id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');

        foreach ([
            'prioridad_familiar' => 'ALTER TABLE protocolo_decisiones_respuestas ADD COLUMN prioridad_familiar VARCHAR(40) NULL AFTER observaciones',
            'justificacion_aplicabilidad' => 'ALTER TABLE protocolo_decisiones_respuestas ADD COLUMN justificacion_aplicabilidad TEXT NULL AFTER prioridad_familiar',
            'fecha_proxima_revision' => 'ALTER TABLE protocolo_decisiones_respuestas ADD COLUMN fecha_proxima_revision DATE NULL AFTER justificacion_aplicabilidad',
            'fecha_real_implementacion' => 'ALTER TABLE protocolo_decisiones_respuestas ADD COLUMN fecha_real_implementacion DATE NULL AFTER fecha_proxima_revision',
            'verificado_por' => 'ALTER TABLE protocolo_decisiones_respuestas ADD COLUMN verificado_por VARCHAR(220) NULL AFTER fecha_real_implementacion',
            'fecha_verificacion' => 'ALTER TABLE protocolo_decisiones_respuestas ADD COLUMN fecha_verificacion DATE NULL AFTER verificado_por',
            'observacion_verificacion' => 'ALTER TABLE protocolo_decisiones_respuestas ADD COLUMN observacion_verificacion TEXT NULL AFTER fecha_verificacion',
            'motivo_bloqueo' => 'ALTER TABLE protocolo_decisiones_respuestas ADD COLUMN motivo_bloqueo TEXT NULL AFTER observacion_verificacion',
            'motivo_aplazamiento_rechazo' => 'ALTER TABLE protocolo_decisiones_respuestas ADD COLUMN motivo_aplazamiento_rechazo TEXT NULL AFTER motivo_bloqueo',
            'justificacion_sin_fecha_objetivo' => 'ALTER TABLE protocolo_decisiones_respuestas ADD COLUMN justificacion_sin_fecha_objetivo TEXT NULL AFTER motivo_aplazamiento_rechazo',
            'motivo_cambio' => 'ALTER TABLE protocolo_decisiones_respuestas ADD COLUMN motivo_cambio TEXT NULL AFTER justificacion_sin_fecha_objetivo',
        ] as $column => $sql) {
            $this->ensureColumn('protocolo_decisiones_respuestas', $column, $sql);
        }

        $this->pdo->exec('CREATE TABLE IF NOT EXISTS protocolo_decision_historial (
            id CHAR(36) NOT NULL PRIMARY KEY,
            decision_catalogo_id CHAR(36) NOT NULL,
            respuesta_id CHAR(36) NOT NULL,
            campo VARCHAR(120) NOT NULL,
            valor_anterior TEXT NULL,
            valor_nuevo TEXT NULL,
            motivo TEXT NULL,
            usuario VARCHAR(120) NULL,
            created_at DATETIME(6) NOT NULL,
            INDEX idx_decision_historial_decision (decision_catalogo_id, created_at),
            CONSTRAINT fk_decision_historial_decision FOREIGN KEY (decision_catalogo_id) REFERENCES protocolo_decision_catalogo(id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');

        $this->pdo->exec('CREATE TABLE IF NOT EXISTS protocolo_decision_revision_riesgos (
            id CHAR(36) NOT NULL PRIMARY KEY,
            decision_catalogo_id CHAR(36) NOT NULL,
            alerta_sugerida TEXT NOT NULL,
            posible_causa TEXT NULL,
            posible_consecuencia TEXT NULL,
            responsable_solicita VARCHAR(220) NULL,
            fecha_solicitud DATE NOT NULL,
            observaciones TEXT NULL,
            estado VARCHAR(80) NOT NULL DEFAULT "Pendiente de revisión humana",
            activo TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME(6) NOT NULL,
            updated_at DATETIME(6) NOT NULL,
            created_by VARCHAR(120) NULL,
            updated_by VARCHAR(120) NULL,
            INDEX idx_decision_revision_riesgos_decision (activo, decision_catalogo_id),
            CONSTRAINT fk_decision_revision_riesgos_decision FOREIGN KEY (decision_catalogo_id) REFERENCES protocolo_decision_catalogo(id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');

        $this->pdo->exec('CREATE TABLE IF NOT EXISTS protocolo_decision_config (
            clave VARCHAR(80) NOT NULL PRIMARY KEY,
            valor VARCHAR(160) NOT NULL,
            descripcion VARCHAR(260) NULL,
            updated_at DATETIME(6) NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $stmt = $this->pdo->prepare('INSERT IGNORE INTO protocolo_decision_config (clave, valor, descripcion, updated_at) VALUES ("dias_proxima_vencer", "30", "Dias configurables para marcar una decision como proxima a vencer.", UTC_TIMESTAMP(6))');
        $stmt->execute();
    }

    private function seedCatalog(): void
    {
        $exists = (int) $this->pdo->query('SELECT COUNT(*) FROM protocolo_decision_catalogo')->fetchColumn();
        if ($exists > 0) {
            return;
        }

        $seed = $this->seedData();
        $categoryIds = [];
        $documentIds = [];
        $decisionIds = [];

        $categoryStmt = $this->pdo->prepare('INSERT INTO protocolo_decision_categorias (id, codigo, nombre, orden, activo, created_at, updated_at) VALUES (:id, :codigo, :nombre, :orden, 1, UTC_TIMESTAMP(6), UTC_TIMESTAMP(6))');
        $decisionStmt = $this->pdo->prepare('INSERT INTO protocolo_decision_catalogo (
            id, codigo, categoria_id, tema, pregunta, objetivo, opciones_sugeridas, instrumento_requerido,
            documento_soporte, responsable, riesgo_sugerido, prioridad, activo, created_at, updated_at
        ) VALUES (
            :id, :codigo, :categoria_id, :tema, :pregunta, :objetivo, :opciones_sugeridas, :instrumento_requerido,
            :documento_soporte, :responsable, :riesgo_sugerido, :prioridad, 1, UTC_TIMESTAMP(6), UTC_TIMESTAMP(6)
        )');
        $documentStmt = $this->pdo->prepare('INSERT INTO protocolo_decision_documentos_catalogo (
            id, codigo, nombre, grupo, finalidad, cuando_se_requiere, responsable_sugerido, provisional, estado_provisional, activo, created_at, updated_at
        ) VALUES (
            :id, :codigo, :nombre, :grupo, :finalidad, :cuando_se_requiere, :responsable_sugerido, :provisional, "Propuesto", 1, UTC_TIMESTAMP(6), UTC_TIMESTAMP(6)
        )');
        $mapStmt = $this->pdo->prepare('INSERT INTO protocolo_decision_documento_mapeo (
            id, decision_catalogo_id, documento_catalogo_id, documento_codigo, documento_nombre, tipo_relacion,
            exigibilidad, momento_requerido, responsable_obtener, responsable_aprobar, evidencia_implementacion,
            justificacion, observaciones, activo, created_at, updated_at
        ) VALUES (
            :id, :decision_catalogo_id, :documento_catalogo_id, :documento_codigo, :documento_nombre, :tipo_relacion,
            :exigibilidad, :momento_requerido, :responsable_obtener, :responsable_aprobar, :evidencia_implementacion,
            :justificacion, :observaciones, 1, UTC_TIMESTAMP(6), UTC_TIMESTAMP(6)
        )');

        $this->pdo->beginTransaction();
        try {
            $order = 1;
            foreach ($seed['decisiones'] as $decision) {
                $category = (string) $decision['categoria'];
                if (!isset($categoryIds[$category])) {
                    $categoryId = Uuid::v4();
                    $categoryIds[$category] = $categoryId;
                    $categoryStmt->execute(['id' => $categoryId, 'codigo' => sprintf('CAT-%02d', $order), 'nombre' => $category, 'orden' => $order]);
                    $order++;
                }
                $decisionId = Uuid::v4();
                $decisionIds[(string) $decision['codigo']] = $decisionId;
                $decisionStmt->execute([
                    'id' => $decisionId,
                    'codigo' => $decision['codigo'],
                    'categoria_id' => $categoryIds[$category],
                    'tema' => $decision['tema'],
                    'pregunta' => $decision['pregunta'],
                    'objetivo' => $decision['objetivo'],
                    'opciones_sugeridas' => $decision['opciones_sugeridas'],
                    'instrumento_requerido' => $decision['instrumento_requerido'],
                    'documento_soporte' => $decision['documento_soporte'],
                    'responsable' => $decision['responsable'],
                    'riesgo_sugerido' => $decision['riesgo_sugerido'],
                    'prioridad' => $decision['prioridad'],
                ]);
            }

            foreach ($seed['documentos'] as $document) {
                $documentId = Uuid::v4();
                $documentIds[(string) $document['codigo']] = $documentId;
                $documentStmt->execute([
                    'id' => $documentId,
                    'codigo' => $document['codigo'],
                    'nombre' => $document['nombre'],
                    'grupo' => $document['grupo'],
                    'finalidad' => $document['finalidad'],
                    'cuando_se_requiere' => $document['cuando_se_requiere'],
                    'responsable_sugerido' => $document['responsable_sugerido'],
                    'provisional' => 0,
                ]);
            }

            foreach ($seed['mapeo'] as $map) {
                $documentCode = (string) $map['documento_codigo'];
                if (str_starts_with($documentCode, 'PROP-DOC-') && !isset($documentIds[$documentCode])) {
                    $documentId = Uuid::v4();
                    $documentIds[$documentCode] = $documentId;
                    $documentStmt->execute([
                        'id' => $documentId,
                        'codigo' => $documentCode,
                        'nombre' => $map['documento_nombre'],
                        'grupo' => 'Documento nuevo sugerido',
                        'finalidad' => $map['justificacion'],
                        'cuando_se_requiere' => $map['momento_requerido'],
                        'responsable_sugerido' => $map['responsable_obtener'],
                        'provisional' => 1,
                    ]);
                }
                $decisionCode = (string) $map['decision_codigo'];
                if (!isset($decisionIds[$decisionCode])) {
                    continue;
                }
                $mapStmt->execute([
                    'id' => Uuid::v4(),
                    'decision_catalogo_id' => $decisionIds[$decisionCode],
                    'documento_catalogo_id' => $documentIds[$documentCode] ?? null,
                    'documento_codigo' => $documentCode,
                    'documento_nombre' => $map['documento_nombre'],
                    'tipo_relacion' => $map['tipo_relacion'],
                    'exigibilidad' => $map['exigibilidad'],
                    'momento_requerido' => $map['momento_requerido'],
                    'responsable_obtener' => $map['responsable_obtener'],
                    'responsable_aprobar' => $map['responsable_aprobar'],
                    'evidencia_implementacion' => $map['evidencia_implementacion'],
                    'justificacion' => $map['justificacion'],
                    'observaciones' => $map['observaciones'],
                ]);
            }
            $this->pdo->commit();
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    /** @return array{decisiones:list<array<string,string>>,documentos:list<array<string,string>>,mapeo:list<array<string,string>>} */
    private function seedData(): array
    {
        $json = is_file($this->seedPath) ? file_get_contents($this->seedPath) : false;
        $data = is_string($json) ? json_decode($json, true) : null;
        if (!is_array($data) || !is_array($data['decisiones'] ?? null) || !is_array($data['documentos'] ?? null) || !is_array($data['mapeo'] ?? null)) {
            throw new RuntimeException('La semilla del catalogo de decisiones no es valida.');
        }
        return ['decisiones' => array_values($data['decisiones']), 'documentos' => array_values($data['documentos']), 'mapeo' => array_values($data['mapeo'])];
    }

    /** @return array<string,array{potenciales:list<array<string,mixed>>,existentes:list<array<string,mixed>>,pendientes:list<array<string,mixed>>,resumen:array<string,int>}> */
    private function documentsByDecision(): array
    {
        $existingDocs = $this->existingDocumentsByName();
        $rows = $this->pdo->query('SELECT d.codigo decision_codigo, dc.provisional, dc.estado_provisional, m.*
            FROM protocolo_decision_documento_mapeo m
            INNER JOIN protocolo_decision_catalogo d ON d.id = m.decision_catalogo_id
            LEFT JOIN protocolo_decision_documentos_catalogo dc ON dc.id = m.documento_catalogo_id
            WHERE m.activo = 1
            ORDER BY FIELD(m.exigibilidad, "Obligatorio", "Condicional", "Recomendado", "No aplica segun la respuesta familiar"), m.documento_codigo')->fetchAll();
        $grouped = [];
        foreach ($rows as $row) {
            $code = (string) $row['decision_codigo'];
            $key = $this->documentKey((string) $row['documento_nombre']);
            $matches = $existingDocs[$key] ?? [];
            $row['existentes'] = $matches;
            $row['pendiente'] = $this->isPendingDocument($row, $matches);
            $row['evidencia_valida'] = $this->isValidEvidence($row, $matches);
            $grouped[$code]['potenciales'][] = $row;
            foreach ($matches as $match) {
                $grouped[$code]['existentes'][] = $match + ['documento_catalogo_codigo' => $row['documento_codigo']];
            }
            if ($row['pendiente']) {
                $grouped[$code]['pendientes'][] = $row;
            }
        }
        foreach ($grouped as $code => $docs) {
            $potentials = $docs['potenciales'] ?? [];
            $existing = $docs['existentes'] ?? [];
            $pending = $docs['pendientes'] ?? [];
            $grouped[$code] = [
                'potenciales' => $potentials,
                'existentes' => $existing,
                'pendientes' => $pending,
                'resumen' => [
                    'requeridos' => count(array_filter($potentials, static fn(array $doc): bool => in_array($doc['exigibilidad'], ['Obligatorio', 'Condicional'], true))),
                    'existentes' => count($existing),
                    'pendientes' => count($pending),
                    'provisionales' => count(array_filter($potentials, static fn(array $doc): bool => str_starts_with((string) $doc['documento_codigo'], 'PROP-DOC-'))),
                    'evidencias_validas' => count(array_filter($potentials, static fn(array $doc): bool => (bool) ($doc['evidencia_valida'] ?? false))),
                ],
            ];
        }
        return $grouped;
    }

    /** @return array<string,list<array<string,mixed>>> */
    private function existingDocumentsByName(): array
    {
        $exists = (int) $this->pdo->query('SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = "protocolo_documentos"')->fetchColumn();
        if ($exists === 0) {
            return [];
        }
        $rows = $this->pdo->query('SELECT * FROM protocolo_documentos WHERE activo = 1')->fetchAll();
        $grouped = [];
        foreach ($rows as $row) {
            $grouped[$this->documentKey((string) ($row['documento_tipo'] ?? ''))][] = $row;
        }
        return $grouped;
    }

    /** @return array<string,list<array<string,mixed>>> */
    private function historyByDecision(): array
    {
        $rows = $this->pdo->query('SELECT d.codigo decision_codigo, h.* FROM protocolo_decision_historial h INNER JOIN protocolo_decision_catalogo d ON d.id = h.decision_catalogo_id ORDER BY h.created_at DESC')->fetchAll();
        $grouped = [];
        foreach ($rows as $row) {
            $grouped[(string) $row['decision_codigo']][] = $row;
        }
        return $grouped;
    }

    /** @return array<string,list<array<string,mixed>>> */
    private function riskRequestsByDecision(): array
    {
        $rows = $this->pdo->query('SELECT d.codigo decision_codigo, r.* FROM protocolo_decision_revision_riesgos r INNER JOIN protocolo_decision_catalogo d ON d.id = r.decision_catalogo_id WHERE r.activo = 1 ORDER BY r.created_at DESC')->fetchAll();
        $grouped = [];
        foreach ($rows as $row) {
            $grouped[(string) $row['decision_codigo']][] = $row;
        }
        return $grouped;
    }

    /** @param array<string,mixed> $row @param array<string,mixed> $documents @return list<string> */
    private function calculatedAlerts(array $row, array $documents, int $dueSoonDays): array
    {
        $alerts = [];
        $today = new \DateTimeImmutable('today');
        $implementation = (string) $row['estado_implementacion'];
        $decision = (string) $row['estado_decision'];
        $priority = (string) ($row['prioridad_familiar'] ?: $row['prioridad_sugerida']);
        $targetDate = $this->dateObject($row['fecha_objetivo'] ?? null);
        $reviewDate = $this->dateObject($row['fecha_proxima_revision'] ?? null);
        $requiresImplementation = !in_array($implementation, ['Verificada', 'Cancelada'], true) && $row['aplica'] !== 'No aplica';

        if ($requiresImplementation && $targetDate !== null && $targetDate < $today && !in_array($implementation, ['Implementada', 'Verificada'], true)) {
            $alerts[] = 'Vencida';
        } elseif ($requiresImplementation && $targetDate !== null && $targetDate <= $today->modify('+' . $dueSoonDays . ' days') && !in_array($implementation, ['Implementada', 'Verificada'], true)) {
            $alerts[] = 'Próxima a vencer';
        }
        if ($row['responsable'] === null || $row['responsable'] === '') {
            $alerts[] = 'Sin responsable';
        }
        if ($row['fecha_objetivo'] === null && in_array($implementation, ['En preparación', 'En ejecución'], true)) {
            $alerts[] = 'Sin fecha objetivo';
        }
        if (in_array($implementation, ['Implementada', 'Verificada'], true) && (int) ($documents['resumen']['evidencias_validas'] ?? 0) === 0) {
            $alerts[] = 'Sin evidencia';
        }
        if ((int) ($documents['resumen']['pendientes'] ?? 0) > 0) {
            $alerts[] = 'Con documento obligatorio pendiente';
        }
        if ((int) ($documents['resumen']['provisionales'] ?? 0) > 0) {
            $alerts[] = 'Con documento provisional';
        }
        if ($reviewDate !== null && $reviewDate < $today && !in_array($decision, ['Aprobada', 'Rechazada'], true)) {
            $alerts[] = 'Revisión vencida';
        }
        if ($implementation === 'Implementada') {
            $alerts[] = 'Implementada sin verificar';
        }
        if ($implementation === 'Bloqueada') {
            $alerts[] = 'Bloqueada';
        }
        if (in_array($priority, ['Crítica', 'Alta'], true) && in_array($decision, ['Pendiente de analizar', 'En análisis', 'Requiere información', 'Aplazada', 'Requiere ajuste'], true)) {
            $alerts[] = 'Posible riesgo pendiente de revisión humana';
        }
        foreach (['Vencida', 'Bloqueada', 'Con documento obligatorio pendiente', 'Sin evidencia', 'Revisión vencida'] as $riskTrigger) {
            if (in_array($riskTrigger, $alerts, true)) {
                $alerts[] = 'Posible riesgo pendiente de revisión humana';
                break;
            }
        }
        return array_values(array_unique($alerts));
    }

    /** @param array<string,mixed> $data @return array<string,mixed> */
    private function responseParams(array $data, string $catalogId): array
    {
        $params = [
            'aplica' => $this->choice($data['aplica'] ?? null, self::APPLICABILITY, 'Pendiente de determinar'),
            'respuesta' => $this->optional($data['respuesta'] ?? null),
            'estado_decision' => $this->choice($data['estado_decision'] ?? null, self::DECISION_STATES, 'Pendiente de analizar'),
            'estado_implementacion' => $this->choice($data['estado_implementacion'] ?? null, self::IMPLEMENTATION_STATES, 'No iniciada'),
            'responsable' => $this->optional($data['responsable'] ?? null),
            'fecha_objetivo' => $this->date($data['fecha_objetivo'] ?? null),
            'fecha_aprobacion' => $this->date($data['fecha_aprobacion'] ?? null),
            'observaciones' => $this->optional($data['observaciones'] ?? null),
            'prioridad_familiar' => $this->choice($data['prioridad_familiar'] ?? null, self::FAMILY_PRIORITIES, 'Media'),
            'justificacion_aplicabilidad' => $this->optional($data['justificacion_aplicabilidad'] ?? null),
            'fecha_proxima_revision' => $this->date($data['fecha_proxima_revision'] ?? null),
            'fecha_real_implementacion' => $this->date($data['fecha_real_implementacion'] ?? null),
            'verificado_por' => $this->optional($data['verificado_por'] ?? null),
            'fecha_verificacion' => $this->date($data['fecha_verificacion'] ?? null),
            'observacion_verificacion' => $this->optional($data['observacion_verificacion'] ?? null),
            'motivo_bloqueo' => $this->optional($data['motivo_bloqueo'] ?? null),
            'motivo_aplazamiento_rechazo' => $this->optional($data['motivo_aplazamiento_rechazo'] ?? null),
            'justificacion_sin_fecha_objetivo' => $this->optional($data['justificacion_sin_fecha_objetivo'] ?? null),
            'motivo_cambio' => $this->optional($data['motivo_cambio'] ?? null),
        ];

        if ($params['aplica'] === 'No aplica' && $params['justificacion_aplicabilidad'] === null) {
            throw new RuntimeException('No aplica exige justificacion.');
        }
        if ($params['aplica'] === 'Aplicará en el futuro' && ($params['justificacion_aplicabilidad'] === null || $params['fecha_proxima_revision'] === null)) {
            throw new RuntimeException('Aplicara en el futuro exige justificacion y proxima fecha de revision.');
        }
        if ($params['estado_decision'] === 'Aplazada' && ($params['motivo_aplazamiento_rechazo'] === null || $params['fecha_proxima_revision'] === null)) {
            throw new RuntimeException('Aplazada exige motivo y proxima revision.');
        }
        if ($params['estado_decision'] === 'Rechazada' && $params['motivo_aplazamiento_rechazo'] === null) {
            throw new RuntimeException('Rechazada exige justificacion.');
        }
        if ($params['estado_decision'] === 'Aprobada' && ($params['respuesta'] === null || $params['fecha_aprobacion'] === null)) {
            throw new RuntimeException('Aprobada exige regla aprobada y fecha de aprobacion.');
        }
        if ($params['estado_implementacion'] === 'Bloqueada' && $params['motivo_bloqueo'] === null) {
            throw new RuntimeException('Bloqueada exige motivo del bloqueo.');
        }
        if ($params['estado_implementacion'] === 'Cancelada' && $params['motivo_aplazamiento_rechazo'] === null) {
            throw new RuntimeException('Cancelada exige justificacion.');
        }
        if (in_array($params['estado_implementacion'], ['En ejecución', 'En preparación'], true) && $params['responsable'] === null) {
            throw new RuntimeException('Una decision en ejecucion o preparacion exige responsable.');
        }
        if (in_array($params['estado_implementacion'], ['En ejecución', 'En preparación'], true) && $params['fecha_objetivo'] === null && $params['justificacion_sin_fecha_objetivo'] === null) {
            throw new RuntimeException('Una decision en ejecucion o preparacion exige fecha objetivo o justificacion autorizada.');
        }
        if ($params['estado_implementacion'] === 'Implementada' && $params['fecha_real_implementacion'] === null) {
            throw new RuntimeException('Implementada exige fecha real de implementacion.');
        }
        if ($params['estado_implementacion'] === 'Verificada') {
            if ($params['verificado_por'] === null || $params['fecha_verificacion'] === null) {
                throw new RuntimeException('Verificada exige verificador y fecha de verificacion.');
            }
            if (!$this->hasValidEvidence($catalogId)) {
                throw new RuntimeException('Verificada exige evidencia documental concreta en 05_Documentos. PROP-DOC no valida por si solo.');
            }
        }

        return $params;
    }

    /** @param array<string,mixed> $before @param array<string,mixed> $after */
    private function writeHistory(string $responseId, string $catalogId, array $before, array $after, string $actor, ?string $reason): void
    {
        $tracked = ['aplica', 'estado_decision', 'estado_implementacion', 'prioridad_familiar', 'responsable', 'fecha_objetivo', 'fecha_aprobacion', 'fecha_proxima_revision', 'fecha_real_implementacion', 'respuesta', 'observaciones', 'verificado_por', 'fecha_verificacion', 'observacion_verificacion'];
        $stmt = $this->pdo->prepare('INSERT INTO protocolo_decision_historial (id, decision_catalogo_id, respuesta_id, campo, valor_anterior, valor_nuevo, motivo, usuario, created_at) VALUES (:id, :decision_catalogo_id, :respuesta_id, :campo, :valor_anterior, :valor_nuevo, :motivo, :usuario, UTC_TIMESTAMP(6))');
        foreach ($tracked as $field) {
            $old = $before[$field] ?? null;
            $new = $after[$field] ?? null;
            if ((string) $old === (string) $new) {
                continue;
            }
            $stmt->execute([
                'id' => Uuid::v4(),
                'decision_catalogo_id' => $catalogId,
                'respuesta_id' => $responseId,
                'campo' => $field,
                'valor_anterior' => $old,
                'valor_nuevo' => $new,
                'motivo' => $reason,
                'usuario' => $actor,
            ]);
        }
    }

    private function hasValidEvidence(string $catalogId): bool
    {
        $stmt = $this->pdo->prepare('SELECT d.codigo decision_codigo FROM protocolo_decision_catalogo d WHERE d.id = :id');
        $stmt->execute(['id' => $catalogId]);
        $code = $stmt->fetchColumn();
        if (!is_string($code)) {
            return false;
        }
        $documents = $this->documentsByDecision()[$code] ?? null;
        return is_array($documents) && (int) ($documents['resumen']['evidencias_validas'] ?? 0) > 0;
    }

    /** @param array<string,mixed> $row @param list<array<string,mixed>> $matches */
    private function isPendingDocument(array $row, array $matches): bool
    {
        if (!in_array($row['exigibilidad'], ['Obligatorio', 'Condicional'], true)) {
            return false;
        }
        if ($row['documento_codigo'] === 'DOC-070') {
            return false;
        }
        foreach ($matches as $match) {
            if (in_array((string) ($match['estado'] ?? ''), ['Recibido', 'En revision'], true)) {
                return false;
            }
        }
        return true;
    }

    /** @param array<string,mixed> $row @param list<array<string,mixed>> $matches */
    private function isValidEvidence(array $row, array $matches): bool
    {
        if ((string) ($row['evidencia_implementacion'] ?? '') !== 'Sí') {
            return false;
        }
        if ($row['documento_codigo'] === 'DOC-070' || str_starts_with((string) $row['documento_codigo'], 'PROP-DOC-')) {
            return false;
        }
        foreach ($matches as $match) {
            if ((string) ($match['estado'] ?? '') === 'Recibido' && is_string($match['archivo_ruta'] ?? null) && $match['archivo_ruta'] !== '') {
                return true;
            }
        }
        return false;
    }

    /** @param array<string,mixed> $row @return array<string,mixed> */
    private function normalizeRow(array $row): array
    {
        $row['aplica'] = $this->normalizeValue($row['aplica'] ?? null, ['Por definir' => 'Pendiente de determinar', 'Si' => 'Aplica ahora', 'Condicional' => 'Aplicará en el futuro'], 'Pendiente de determinar', self::APPLICABILITY);
        $row['estado_decision'] = $this->normalizeValue($row['estado_decision'] ?? null, ['Pendiente' => 'Pendiente de analizar', 'En discusion' => 'En análisis', 'Postergada' => 'Aplazada'], 'Pendiente de analizar', self::DECISION_STATES);
        $row['estado_implementacion'] = $this->normalizeValue($row['estado_implementacion'] ?? null, ['Pendiente' => 'No iniciada', 'En proceso' => 'En ejecución', 'No aplica' => 'Cancelada'], 'No iniciada', self::IMPLEMENTATION_STATES);
        $row['prioridad_familiar'] = $this->normalizeValue($row['prioridad_familiar'] ?? null, ['Crítico' => 'Crítica', 'Critica' => 'Crítica', 'Alto' => 'Alta', 'Medio' => 'Media', 'Bajo' => 'Baja'], 'Media', self::FAMILY_PRIORITIES);
        return $row;
    }

    /** @param array<string,mixed> $row @param array<string,mixed> $documents @return array<string,mixed> */
    private function documentsForApplicability(array $row, array $documents): array
    {
        $pending = [];
        foreach ($documents['potenciales'] ?? [] as $document) {
            if (!is_array($document) || !($document['pendiente'] ?? false)) {
                continue;
            }
            if (($document['exigibilidad'] ?? '') === 'Condicional' && !in_array($row['aplica'], ['Aplica ahora', 'Aplicará en el futuro'], true)) {
                continue;
            }
            $pending[] = $document;
        }
        $documents['pendientes'] = $pending;
        $documents['resumen']['pendientes'] = count($pending);
        return $documents;
    }

    /** @param array<string,string> $map @param list<string> $allowed */
    private function normalizeValue(mixed $value, array $map, string $default, array $allowed): string
    {
        $text = is_string($value) && trim($value) !== '' ? trim($value) : $default;
        $text = $map[$text] ?? $text;
        return in_array($text, $allowed, true) ? $text : $default;
    }

    /** @return array<string,int> */
    private function emptyDocumentSummary(): array
    {
        return ['requeridos' => 0, 'existentes' => 0, 'pendientes' => 0, 'provisionales' => 0, 'evidencias_validas' => 0];
    }

    private function catalogId(string $code): string
    {
        if (preg_match('/^DEC-\d{3}$/', $code) !== 1) {
            throw new RuntimeException('Codigo de decision invalido.');
        }
        $stmt = $this->pdo->prepare('SELECT id FROM protocolo_decision_catalogo WHERE codigo = :codigo AND activo = 1');
        $stmt->execute(['codigo' => $code]);
        $id = $stmt->fetchColumn();
        if (!is_string($id) || $id === '') {
            throw new RuntimeException('Decision no encontrada.');
        }
        return $id;
    }

    private function responseId(string $catalogId): ?string
    {
        $stmt = $this->pdo->prepare('SELECT id FROM protocolo_decisiones_respuestas WHERE decision_catalogo_id = :id AND activo = 1 LIMIT 1');
        $stmt->execute(['id' => $catalogId]);
        $id = $stmt->fetchColumn();
        return is_string($id) && $id !== '' ? $id : null;
    }

    /** @return array<string,mixed>|null */
    private function rawResponse(string $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM protocolo_decisiones_respuestas WHERE id = :id AND activo = 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return is_array($row) ? $row : null;
    }

    private function dueSoonDays(): int
    {
        $value = $this->pdo->query('SELECT valor FROM protocolo_decision_config WHERE clave = "dias_proxima_vencer"')->fetchColumn();
        $days = is_numeric($value) ? (int) $value : 30;
        return max(1, min(365, $days));
    }

    private function ensureColumn(string $table, string $column, string $sql): void
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table AND COLUMN_NAME = :column');
        $stmt->execute(['table' => $table, 'column' => $column]);
        if ((int) $stmt->fetchColumn() === 0) {
            $this->pdo->exec($sql);
        }
    }

    /** @param list<string> $allowed */
    private function choice(mixed $value, array $allowed, string $default): string
    {
        $value = is_string($value) && trim($value) !== '' ? trim($value) : $default;
        if (!in_array($value, $allowed, true)) {
            throw new RuntimeException('Valor no permitido.');
        }
        return $value;
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

    private function dateObject(mixed $value): ?\DateTimeImmutable
    {
        if (!is_string($value) || $value === '') {
            return null;
        }
        return \DateTimeImmutable::createFromFormat('Y-m-d', substr($value, 0, 10)) ?: null;
    }

    private function documentKey(string $value): string
    {
        $value = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value) ?: $value;
        return strtolower((string) preg_replace('/[^a-zA-Z0-9]+/', '', $value));
    }
}
