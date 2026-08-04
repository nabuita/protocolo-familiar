<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\Uuid;
use PDO;
use RuntimeException;

final readonly class RiesgoModel
{
    /** @var list<string> */
    private const CANDIDATE_STATES = ['Recibido', 'Pendiente de revisión', 'En revisión', 'Requiere información', 'En observación', 'Descartado', 'Relacionado con riesgo existente', 'Aprobado para conversión', 'Convertido en riesgo'];
    /** @var list<string> */
    private const RISK_STATES = ['Identificado', 'En evaluación', 'Evaluado', 'Pendiente de aprobación', 'Aprobado', 'Tratamiento pendiente', 'En tratamiento', 'En monitoreo', 'Materializado', 'Suspendido', 'Cerrado', 'Reabierto'];
    /** @var list<string> */
    private const STRATEGIES = ['Evitar', 'Reducir o mitigar', 'Transferir', 'Compartir', 'Aceptar', 'Eliminar la causa', 'Preparar contingencia', 'Mantener en observación'];
    /** @var list<string> */
    private const CONTROL_TYPES = ['Preventivo', 'Detectivo', 'Correctivo', 'Directivo', 'De contingencia'];
    /** @var list<string> */
    private const CONTROL_NATURES = ['Manual', 'Automático', 'Mixto'];
    /** @var list<string> */
    private const CONTROL_STATES = ['Diseñado', 'Implementado', 'Operando', 'No efectivo', 'Suspendido', 'Pendiente de evidencia'];
    /** @var list<string> */
    private const ACTION_STATES = ['Pendiente', 'En preparación', 'En ejecución', 'Bloqueada', 'Terminada', 'Verificada', 'Cancelada'];
    /** @var list<string> */
    private const DOCUMENT_RELATIONS = ['Fuente del riesgo', 'Evidencia de identificación', 'Evidencia de control', 'Evidencia de tratamiento', 'Evidencia de materialización', 'Evidencia de seguimiento', 'Evidencia de cierre', 'Documento de referencia'];
    /** @var list<string> */
    private const REVIEW_FREQUENCIES = ['Mensual', 'Trimestral', 'Semestral', 'Anual', 'Por evento', 'No definida'];
    /** @var list<string> */
    private const PRIORITIES = ['Crítica', 'Alta', 'Media', 'Baja'];

    public function __construct(private PDO $pdo)
    {
        $this->ensureSchema();
        $this->seedCatalogs();
    }

    /** @return array<string,mixed> */
    public function dashboard(): array
    {
        $this->syncDecisionCandidates('system');
        $candidates = $this->candidates();
        $riskRows = $this->riskRows();
        if ($riskRows === []) {
            $actions = [];
            $controls = [];
            $documents = [];
            $risks = [];
        } else {
            $actions = $this->actionsByRisk();
            $controls = $this->controlsByRisk();
            $documents = $this->documentsByRisk();
            $risks = $this->enrichRisks($riskRows, $controls, $actions, $documents);
        }
        return [
            'candidatos' => $candidates,
            'riesgos' => $risks,
            'controles' => $controls,
            'acciones' => $actions,
            'documentos' => $documents,
            'historial' => $this->recentHistory(),
            'summary' => $this->summary($candidates, $risks, $controls, $actions),
        ];
    }

    /** @return array<string,list<string>|list<array<string,mixed>>> */
    public function options(): array
    {
        return [
            'candidate_states' => self::CANDIDATE_STATES,
            'risk_states' => self::RISK_STATES,
            'categories' => array_map(static fn(array $row): string => (string) $row['nombre'], $this->catalogItems('RIESGO_CATEGORIA')),
            'probability' => ['1 - Rara', '2 - Improbable', '3 - Posible', '4 - Probable', '5 - Casi segura'],
            'impact' => ['1 - Insignificante', '2 - Menor', '3 - Moderado', '4 - Mayor', '5 - Crítico'],
            'strategies' => self::STRATEGIES,
            'control_types' => self::CONTROL_TYPES,
            'control_natures' => self::CONTROL_NATURES,
            'control_states' => self::CONTROL_STATES,
            'action_states' => self::ACTION_STATES,
            'document_relations' => self::DOCUMENT_RELATIONS,
            'review_frequencies' => self::REVIEW_FREQUENCIES,
            'priorities' => self::PRIORITIES,
            'documents' => $this->availableDocuments(),
            'risks' => $this->riskOptions(),
        ];
    }

    /** @param array<string,mixed> $data @return array<string,mixed> */
    public function createCandidate(array $data, string $actor): array
    {
        $id = Uuid::v4();
        $params = $this->candidateParams($data, false);
        $stmt = $this->pdo->prepare('INSERT INTO protocolo_riesgo_candidatos (
            id, codigo, titulo, fuente, fecha_recepcion, usuario_envia, decision_codigo, alerta_sugerida,
            posible_causa, posible_evento, posible_consecuencia, categoria_sugerida, alcance_sugerido,
            responsable_revision, estado, informacion_solicitada, fecha_limite_respuesta, resultado_revision,
            justificacion, riesgo_relacionado_id, observaciones, activo, created_at, updated_at, created_by, updated_by
        ) VALUES (
            :id, :codigo, :titulo, :fuente, :fecha_recepcion, :usuario_envia, :decision_codigo, :alerta_sugerida,
            :posible_causa, :posible_evento, :posible_consecuencia, :categoria_sugerida, :alcance_sugerido,
            :responsable_revision, :estado, :informacion_solicitada, :fecha_limite_respuesta, :resultado_revision,
            :justificacion, :riesgo_relacionado_id, :observaciones, 1, UTC_TIMESTAMP(6), UTC_TIMESTAMP(6), :created_by, :updated_by
        )');
        $stmt->execute($params + ['id' => $id, 'codigo' => $this->nextCandidateCode(), 'created_by' => $actor, 'updated_by' => $actor]);
        $this->recordHistory('candidato', $id, 'creacion', null, $params['estado'], 'Creacion de candidato', 'manual', $actor);
        return $this->candidate($id);
    }

    /** @param array<string,mixed> $data @return array<string,mixed> */
    public function updateCandidate(string $id, array $data, string $actor): array
    {
        $before = $this->candidate($id);
        $params = $this->candidateParams($data, true);
        $stmt = $this->pdo->prepare('UPDATE protocolo_riesgo_candidatos SET
            titulo = :titulo, fuente = :fuente, fecha_recepcion = :fecha_recepcion, usuario_envia = :usuario_envia,
            decision_codigo = :decision_codigo, alerta_sugerida = :alerta_sugerida, posible_causa = :posible_causa,
            posible_evento = :posible_evento, posible_consecuencia = :posible_consecuencia, categoria_sugerida = :categoria_sugerida,
            alcance_sugerido = :alcance_sugerido, responsable_revision = :responsable_revision, estado = :estado,
            informacion_solicitada = :informacion_solicitada, fecha_limite_respuesta = :fecha_limite_respuesta,
            resultado_revision = :resultado_revision, justificacion = :justificacion, riesgo_relacionado_id = :riesgo_relacionado_id,
            observaciones = :observaciones, updated_at = UTC_TIMESTAMP(6), updated_by = :updated_by
            WHERE id = :id AND activo = 1');
        $stmt->execute($params + ['id' => $id, 'updated_by' => $actor]);
        $this->writeDiff('candidato', $id, $before, $params, $params['justificacion'], 'revision candidato', $actor);
        return $this->candidate($id);
    }

    /** @param array<string,mixed> $data @return array<string,mixed> */
    public function createRisk(array $data, string $actor, ?string $candidateId = null): array
    {
        $id = Uuid::v4();
        $params = $this->riskParams($data);
        $stmt = $this->pdo->prepare('INSERT INTO protocolo_riesgos (
            id, codigo, titulo, descripcion, categoria, subcategoria, fecha_identificacion, fuente,
            propietario, aprobador, estado, causa, evento, consecuencia, probabilidad_inherente,
            impacto_inherente, puntaje_inherente, nivel_inherente, justificacion_probabilidad,
            justificacion_impacto, evaluador, fecha_evaluacion, estrategia_tratamiento,
            probabilidad_residual, impacto_residual, puntaje_residual, nivel_residual,
            justificacion_residual, evaluador_residual, fecha_evaluacion_residual, aceptador_residual,
            frecuencia_revision, ultima_revision, proxima_revision, indicadores, senales_materializacion,
            fecha_materializacion, descripcion_materializacion, fecha_cierre, motivo_cierre,
            residual_aceptado, evidencia_cierre, solicita_cierre, aprueba_cierre, observaciones_finales,
            activo, created_at, updated_at, created_by, updated_by
        ) VALUES (
            :id, :codigo, :titulo, :descripcion, :categoria, :subcategoria, :fecha_identificacion, :fuente,
            :propietario, :aprobador, :estado, :causa, :evento, :consecuencia, :probabilidad_inherente,
            :impacto_inherente, :puntaje_inherente, :nivel_inherente, :justificacion_probabilidad,
            :justificacion_impacto, :evaluador, :fecha_evaluacion, :estrategia_tratamiento,
            :probabilidad_residual, :impacto_residual, :puntaje_residual, :nivel_residual,
            :justificacion_residual, :evaluador_residual, :fecha_evaluacion_residual, :aceptador_residual,
            :frecuencia_revision, :ultima_revision, :proxima_revision, :indicadores, :senales_materializacion,
            :fecha_materializacion, :descripcion_materializacion, :fecha_cierre, :motivo_cierre,
            :residual_aceptado, :evidencia_cierre, :solicita_cierre, :aprueba_cierre, :observaciones_finales,
            1, UTC_TIMESTAMP(6), UTC_TIMESTAMP(6), :created_by, :updated_by
        )');
        $stmt->execute($params + ['id' => $id, 'codigo' => $this->nextRiskCode(), 'created_by' => $actor, 'updated_by' => $actor]);
        if ($candidateId !== null && $candidateId !== '') {
            $this->convertCandidate($candidateId, $id, $actor);
        }
        $this->recordHistory('riesgo', $id, 'creacion', null, $params['estado'], 'Creacion de riesgo definitivo', 'registro maestro', $actor);
        return $this->risk($id);
    }

    /** @param array<string,mixed> $data @return array<string,mixed> */
    public function updateRisk(string $id, array $data, string $actor): array
    {
        $before = $this->risk($id);
        $params = $this->riskParams($data);
        $stmt = $this->pdo->prepare('UPDATE protocolo_riesgos SET
            titulo = :titulo, descripcion = :descripcion, categoria = :categoria, subcategoria = :subcategoria,
            fecha_identificacion = :fecha_identificacion, fuente = :fuente, propietario = :propietario,
            aprobador = :aprobador, estado = :estado, causa = :causa, evento = :evento, consecuencia = :consecuencia,
            probabilidad_inherente = :probabilidad_inherente, impacto_inherente = :impacto_inherente,
            puntaje_inherente = :puntaje_inherente, nivel_inherente = :nivel_inherente,
            justificacion_probabilidad = :justificacion_probabilidad, justificacion_impacto = :justificacion_impacto,
            evaluador = :evaluador, fecha_evaluacion = :fecha_evaluacion, estrategia_tratamiento = :estrategia_tratamiento,
            probabilidad_residual = :probabilidad_residual, impacto_residual = :impacto_residual,
            puntaje_residual = :puntaje_residual, nivel_residual = :nivel_residual, justificacion_residual = :justificacion_residual,
            evaluador_residual = :evaluador_residual, fecha_evaluacion_residual = :fecha_evaluacion_residual,
            aceptador_residual = :aceptador_residual, frecuencia_revision = :frecuencia_revision,
            ultima_revision = :ultima_revision, proxima_revision = :proxima_revision, indicadores = :indicadores,
            senales_materializacion = :senales_materializacion, fecha_materializacion = :fecha_materializacion,
            descripcion_materializacion = :descripcion_materializacion, fecha_cierre = :fecha_cierre,
            motivo_cierre = :motivo_cierre, residual_aceptado = :residual_aceptado, evidencia_cierre = :evidencia_cierre,
            solicita_cierre = :solicita_cierre, aprueba_cierre = :aprueba_cierre, observaciones_finales = :observaciones_finales,
            updated_at = UTC_TIMESTAMP(6), updated_by = :updated_by
            WHERE id = :id AND activo = 1');
        $stmt->execute($params + ['id' => $id, 'updated_by' => $actor]);
        $this->writeDiff('riesgo', $id, $before, $params, $this->optional($data['motivo_cambio'] ?? null), 'edicion riesgo', $actor);
        return $this->risk($id);
    }

    /** @param array<string,mixed> $data @return array<string,mixed> */
    public function addControl(string $riskId, array $data, string $actor): array
    {
        $this->risk($riskId);
        $id = Uuid::v4();
        $params = [
            'riesgo_id' => $riskId,
            'nombre' => $this->required($data['nombre'] ?? null, 'Nombre del control obligatorio.'),
            'descripcion' => $this->optional($data['descripcion'] ?? null),
            'tipo' => $this->choice($data['tipo'] ?? null, self::CONTROL_TYPES, 'Preventivo'),
            'naturaleza' => $this->choice($data['naturaleza'] ?? null, self::CONTROL_NATURES, 'Manual'),
            'responsable' => $this->required($data['responsable'] ?? null, 'Responsable del control obligatorio.'),
            'frecuencia' => $this->optional($data['frecuencia'] ?? null),
            'estado' => $this->choice($data['estado'] ?? null, self::CONTROL_STATES, 'Diseñado'),
            'documento_id' => $this->uuidOrNull($data['documento_id'] ?? null),
            'evaluacion_diseno' => $this->optional($data['evaluacion_diseno'] ?? null),
            'evaluacion_ejecucion' => $this->optional($data['evaluacion_ejecucion'] ?? null),
            'efectividad' => $this->optional($data['efectividad'] ?? null),
            'fecha_revision' => $this->date($data['fecha_revision'] ?? null),
            'observaciones' => $this->optional($data['observaciones'] ?? null),
        ];
        if ($params['estado'] === 'Operando' && $params['documento_id'] === null) {
            throw new RuntimeException('Un control operando requiere evidencia documental.');
        }
        $stmt = $this->pdo->prepare('INSERT INTO protocolo_riesgo_controles (
            id, riesgo_id, nombre, descripcion, tipo, naturaleza, responsable, frecuencia, estado, documento_id,
            evaluacion_diseno, evaluacion_ejecucion, efectividad, fecha_revision, observaciones, activo,
            created_at, updated_at, created_by, updated_by
        ) VALUES (
            :id, :riesgo_id, :nombre, :descripcion, :tipo, :naturaleza, :responsable, :frecuencia, :estado, :documento_id,
            :evaluacion_diseno, :evaluacion_ejecucion, :efectividad, :fecha_revision, :observaciones, 1,
            UTC_TIMESTAMP(6), UTC_TIMESTAMP(6), :created_by, :updated_by
        )');
        $stmt->execute($params + ['id' => $id, 'created_by' => $actor, 'updated_by' => $actor]);
        $this->recordHistory('control', $id, 'creacion', null, $params['estado'], 'Creacion de control', 'controles', $actor);
        return $this->control($id);
    }

    /** @param array<string,mixed> $data @return array<string,mixed> */
    public function addAction(string $riskId, array $data, string $actor): array
    {
        $this->risk($riskId);
        $id = Uuid::v4();
        $params = [
            'riesgo_id' => $riskId,
            'descripcion' => $this->required($data['descripcion'] ?? null, 'Descripcion de accion obligatoria.'),
            'responsable' => $this->required($data['responsable'] ?? null, 'Responsable de accion obligatorio.'),
            'fecha_inicio' => $this->date($data['fecha_inicio'] ?? null),
            'fecha_objetivo' => $this->date($data['fecha_objetivo'] ?? null),
            'prioridad' => $this->choice($data['prioridad'] ?? null, self::PRIORITIES, 'Media'),
            'estado' => $this->choice($data['estado'] ?? null, self::ACTION_STATES, 'Pendiente'),
            'avance' => $this->percent($data['avance'] ?? null),
            'dependencia' => $this->optional($data['dependencia'] ?? null),
            'costo_estimado' => $this->money($data['costo_estimado'] ?? null),
            'documento_id' => $this->uuidOrNull($data['documento_id'] ?? null),
            'resultado' => $this->optional($data['resultado'] ?? null),
            'observaciones' => $this->optional($data['observaciones'] ?? null),
        ];
        if (in_array($params['estado'], ['En ejecución', 'Bloqueada'], true) && $params['fecha_objetivo'] === null) {
            throw new RuntimeException('La accion exige fecha objetivo.');
        }
        if ($params['estado'] === 'Verificada' && $params['documento_id'] === null) {
            throw new RuntimeException('Accion verificada exige evidencia documental.');
        }
        $stmt = $this->pdo->prepare('INSERT INTO protocolo_riesgo_acciones (
            id, riesgo_id, descripcion, responsable, fecha_inicio, fecha_objetivo, prioridad, estado, avance,
            dependencia, costo_estimado, documento_id, resultado, observaciones, activo, created_at, updated_at, created_by, updated_by
        ) VALUES (
            :id, :riesgo_id, :descripcion, :responsable, :fecha_inicio, :fecha_objetivo, :prioridad, :estado, :avance,
            :dependencia, :costo_estimado, :documento_id, :resultado, :observaciones, 1, UTC_TIMESTAMP(6), UTC_TIMESTAMP(6), :created_by, :updated_by
        )');
        $stmt->execute($params + ['id' => $id, 'created_by' => $actor, 'updated_by' => $actor]);
        $this->recordHistory('accion', $id, 'creacion', null, $params['estado'], 'Creacion accion tratamiento', 'tratamiento', $actor);
        return $this->action($id);
    }

    /** @param array<string,mixed> $data @return array<string,mixed> */
    public function relateDocument(string $riskId, array $data, string $actor): array
    {
        $this->risk($riskId);
        $id = Uuid::v4();
        $documentId = $this->uuidOrNull($data['documento_id'] ?? null);
        if ($documentId === null) {
            throw new RuntimeException('Selecciona un documento existente de 05_Documentos.');
        }
        $params = [
            'riesgo_id' => $riskId,
            'documento_id' => $documentId,
            'tipo_relacion' => $this->choice($data['tipo_relacion'] ?? null, self::DOCUMENT_RELATIONS, 'Documento de referencia'),
            'observaciones' => $this->optional($data['observaciones'] ?? null),
        ];
        $stmt = $this->pdo->prepare('INSERT INTO protocolo_riesgo_documentos (id, riesgo_id, documento_id, tipo_relacion, observaciones, activo, created_at, updated_at, created_by, updated_by) VALUES (:id, :riesgo_id, :documento_id, :tipo_relacion, :observaciones, 1, UTC_TIMESTAMP(6), UTC_TIMESTAMP(6), :created_by, :updated_by)');
        $stmt->execute($params + ['id' => $id, 'created_by' => $actor, 'updated_by' => $actor]);
        $this->recordHistory('riesgo', $riskId, 'documento_relacionado', null, $documentId, 'Relacion documento 05_Documentos', 'documentos', $actor);
        return $this->risk($riskId);
    }

    private function ensureSchema(): void
    {
        $this->pdo->exec('CREATE TABLE IF NOT EXISTS protocolo_riesgo_candidatos (
            id CHAR(36) NOT NULL PRIMARY KEY,
            codigo VARCHAR(20) NOT NULL UNIQUE,
            titulo VARCHAR(240) NOT NULL,
            fuente VARCHAR(120) NOT NULL,
            fecha_recepcion DATE NOT NULL,
            usuario_envia VARCHAR(160) NULL,
            decision_codigo VARCHAR(20) NULL,
            decision_revision_id CHAR(36) NULL,
            alerta_sugerida TEXT NULL,
            posible_causa TEXT NULL,
            posible_evento TEXT NULL,
            posible_consecuencia TEXT NULL,
            categoria_sugerida VARCHAR(160) NULL,
            alcance_sugerido VARCHAR(180) NULL,
            responsable_revision VARCHAR(180) NULL,
            estado VARCHAR(80) NOT NULL DEFAULT "Recibido",
            informacion_solicitada TEXT NULL,
            fecha_limite_respuesta DATE NULL,
            resultado_revision TEXT NULL,
            justificacion TEXT NULL,
            riesgo_relacionado_id CHAR(36) NULL,
            observaciones TEXT NULL,
            activo TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME(6) NOT NULL,
            updated_at DATETIME(6) NOT NULL,
            created_by VARCHAR(120) NULL,
            updated_by VARCHAR(120) NULL,
            INDEX idx_riesgo_candidatos_estado (activo, estado),
            INDEX idx_riesgo_candidatos_decision (activo, decision_codigo)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');

        $this->pdo->exec('CREATE TABLE IF NOT EXISTS protocolo_riesgos (
            id CHAR(36) NOT NULL PRIMARY KEY,
            codigo VARCHAR(20) NOT NULL UNIQUE,
            titulo VARCHAR(240) NOT NULL,
            descripcion TEXT NULL,
            categoria VARCHAR(160) NOT NULL,
            subcategoria VARCHAR(160) NULL,
            fecha_identificacion DATE NOT NULL,
            fuente VARCHAR(120) NOT NULL,
            propietario VARCHAR(180) NOT NULL,
            aprobador VARCHAR(180) NULL,
            estado VARCHAR(80) NOT NULL DEFAULT "Identificado",
            causa TEXT NOT NULL,
            evento TEXT NOT NULL,
            consecuencia TEXT NOT NULL,
            probabilidad_inherente TINYINT NULL,
            impacto_inherente TINYINT NULL,
            puntaje_inherente INT NULL,
            nivel_inherente VARCHAR(40) NULL,
            justificacion_probabilidad TEXT NULL,
            justificacion_impacto TEXT NULL,
            evaluador VARCHAR(180) NULL,
            fecha_evaluacion DATE NULL,
            estrategia_tratamiento VARCHAR(80) NULL,
            probabilidad_residual TINYINT NULL,
            impacto_residual TINYINT NULL,
            puntaje_residual INT NULL,
            nivel_residual VARCHAR(40) NULL,
            justificacion_residual TEXT NULL,
            evaluador_residual VARCHAR(180) NULL,
            fecha_evaluacion_residual DATE NULL,
            aceptador_residual VARCHAR(180) NULL,
            frecuencia_revision VARCHAR(80) NULL,
            ultima_revision DATE NULL,
            proxima_revision DATE NULL,
            indicadores TEXT NULL,
            senales_materializacion TEXT NULL,
            fecha_materializacion DATE NULL,
            descripcion_materializacion TEXT NULL,
            fecha_cierre DATE NULL,
            motivo_cierre TEXT NULL,
            residual_aceptado VARCHAR(40) NULL,
            evidencia_cierre VARCHAR(240) NULL,
            solicita_cierre VARCHAR(180) NULL,
            aprueba_cierre VARCHAR(180) NULL,
            observaciones_finales TEXT NULL,
            activo TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME(6) NOT NULL,
            updated_at DATETIME(6) NOT NULL,
            created_by VARCHAR(120) NULL,
            updated_by VARCHAR(120) NULL,
            INDEX idx_riesgos_estado_nivel (activo, estado, nivel_residual),
            INDEX idx_riesgos_categoria (activo, categoria)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');

        $this->pdo->exec('CREATE TABLE IF NOT EXISTS protocolo_riesgo_decisiones (
            id CHAR(36) NOT NULL PRIMARY KEY,
            riesgo_id CHAR(36) NOT NULL,
            decision_codigo VARCHAR(20) NOT NULL,
            observaciones TEXT NULL,
            activo TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME(6) NOT NULL,
            created_by VARCHAR(120) NULL,
            UNIQUE KEY uq_riesgo_decision (riesgo_id, decision_codigo, activo),
            CONSTRAINT fk_riesgo_decision_riesgo FOREIGN KEY (riesgo_id) REFERENCES protocolo_riesgos(id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');

        $this->pdo->exec('CREATE TABLE IF NOT EXISTS protocolo_riesgo_documentos (
            id CHAR(36) NOT NULL PRIMARY KEY,
            riesgo_id CHAR(36) NOT NULL,
            documento_id CHAR(36) NOT NULL,
            tipo_relacion VARCHAR(100) NOT NULL,
            observaciones TEXT NULL,
            activo TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME(6) NOT NULL,
            updated_at DATETIME(6) NOT NULL,
            created_by VARCHAR(120) NULL,
            updated_by VARCHAR(120) NULL,
            INDEX idx_riesgo_documentos_riesgo (activo, riesgo_id),
            CONSTRAINT fk_riesgo_documentos_riesgo FOREIGN KEY (riesgo_id) REFERENCES protocolo_riesgos(id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');

        $this->pdo->exec('CREATE TABLE IF NOT EXISTS protocolo_riesgo_controles (
            id CHAR(36) NOT NULL PRIMARY KEY,
            riesgo_id CHAR(36) NOT NULL,
            nombre VARCHAR(220) NOT NULL,
            descripcion TEXT NULL,
            tipo VARCHAR(80) NOT NULL,
            naturaleza VARCHAR(40) NOT NULL,
            responsable VARCHAR(180) NOT NULL,
            frecuencia VARCHAR(80) NULL,
            estado VARCHAR(80) NOT NULL,
            documento_id CHAR(36) NULL,
            evaluacion_diseno TEXT NULL,
            evaluacion_ejecucion TEXT NULL,
            efectividad VARCHAR(80) NULL,
            fecha_revision DATE NULL,
            observaciones TEXT NULL,
            activo TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME(6) NOT NULL,
            updated_at DATETIME(6) NOT NULL,
            created_by VARCHAR(120) NULL,
            updated_by VARCHAR(120) NULL,
            INDEX idx_riesgo_controles_riesgo (activo, riesgo_id),
            CONSTRAINT fk_riesgo_controles_riesgo FOREIGN KEY (riesgo_id) REFERENCES protocolo_riesgos(id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');

        $this->pdo->exec('CREATE TABLE IF NOT EXISTS protocolo_riesgo_acciones (
            id CHAR(36) NOT NULL PRIMARY KEY,
            riesgo_id CHAR(36) NOT NULL,
            descripcion TEXT NOT NULL,
            responsable VARCHAR(180) NOT NULL,
            fecha_inicio DATE NULL,
            fecha_objetivo DATE NULL,
            prioridad VARCHAR(40) NOT NULL DEFAULT "Media",
            estado VARCHAR(80) NOT NULL DEFAULT "Pendiente",
            avance DECIMAL(5,2) NULL,
            dependencia VARCHAR(220) NULL,
            costo_estimado DECIMAL(18,2) NULL,
            documento_id CHAR(36) NULL,
            resultado TEXT NULL,
            observaciones TEXT NULL,
            activo TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME(6) NOT NULL,
            updated_at DATETIME(6) NOT NULL,
            created_by VARCHAR(120) NULL,
            updated_by VARCHAR(120) NULL,
            INDEX idx_riesgo_acciones_riesgo (activo, riesgo_id),
            INDEX idx_riesgo_acciones_estado_fecha (activo, estado, fecha_objetivo),
            CONSTRAINT fk_riesgo_acciones_riesgo FOREIGN KEY (riesgo_id) REFERENCES protocolo_riesgos(id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');

        $this->pdo->exec('CREATE TABLE IF NOT EXISTS protocolo_riesgo_historial (
            id CHAR(36) NOT NULL PRIMARY KEY,
            entidad_tipo VARCHAR(40) NOT NULL,
            entidad_id CHAR(36) NOT NULL,
            campo VARCHAR(120) NOT NULL,
            valor_anterior TEXT NULL,
            valor_nuevo TEXT NULL,
            motivo TEXT NULL,
            origen VARCHAR(120) NULL,
            usuario VARCHAR(120) NULL,
            created_at DATETIME(6) NOT NULL,
            INDEX idx_riesgo_historial_entidad (entidad_tipo, entidad_id, created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');

        $this->pdo->exec('CREATE TABLE IF NOT EXISTS protocolo_riesgo_config (
            clave VARCHAR(80) NOT NULL PRIMARY KEY,
            valor VARCHAR(160) NOT NULL,
            descripcion VARCHAR(260) NULL,
            updated_at DATETIME(6) NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $this->pdo->exec('INSERT IGNORE INTO protocolo_riesgo_config (clave, valor, descripcion, updated_at) VALUES
            ("dias_candidato_vencer", "10", "Dias para alerta de candidato proximo a vencer.", UTC_TIMESTAMP(6)),
            ("dias_sin_actualizacion", "90", "Dias sin actualizacion para alerta de riesgo.", UTC_TIMESTAMP(6)),
            ("apetito_maximo_residual", "Moderado", "Nivel residual maximo inicialmente aceptable.", UTC_TIMESTAMP(6))');
    }

    private function seedCatalogs(): void
    {
        $categories = ['Familiar y de convivencia', 'Gobierno familiar', 'Gobierno corporativo', 'Societario y accionarial', 'Sucesoral', 'Patrimonial', 'Financiero y de liquidez', 'Tributario', 'Jurídico y regulatorio', 'Laboral y de talento familiar', 'Persona clave y continuidad', 'Documental y contractual', 'Propiedad intelectual', 'Tecnológico', 'Ciberseguridad y protección de datos', 'Operacional', 'Estratégico y de nuevos negocios', 'Reputacional', 'Cumplimiento y ética', 'Seguros y transferencia de riesgos'];
        $this->ensureCatalog('RIESGO_CATEGORIA', 'Categorías de riesgo', 'Categorías administrables del módulo 07_Riesgos.', $categories, 700);
    }

    /** @param list<string> $items */
    private function ensureCatalog(string $code, string $name, string $description, array $items, int $order): void
    {
        $stmt = $this->pdo->prepare('SELECT id FROM pf_catalogos WHERE codigo = :codigo AND activo = 1');
        $stmt->execute(['codigo' => $code]);
        $catalogId = $stmt->fetchColumn();
        if (!is_string($catalogId) || $catalogId === '') {
            $catalogId = Uuid::v4();
            $insert = $this->pdo->prepare('INSERT INTO pf_catalogos (id, codigo, nombre, descripcion, tipo, orden, activo, created_by, updated_by, created_at, updated_at) VALUES (:id, :codigo, :nombre, :descripcion, "SIMPLE", :orden, 1, "install", "install", UTC_TIMESTAMP(6), UTC_TIMESTAMP(6))');
            $insert->execute(['id' => $catalogId, 'codigo' => $code, 'nombre' => $name, 'descripcion' => $description, 'orden' => $order]);
        }
        $count = $this->pdo->prepare('SELECT COUNT(*) FROM pf_catalogo_items WHERE catalogo_id = :catalogo AND activo = 1');
        $count->execute(['catalogo' => $catalogId]);
        if ((int) $count->fetchColumn() >= count($items)) {
            return;
        }
        $exists = $this->pdo->prepare('SELECT COUNT(*) FROM pf_catalogo_items WHERE catalogo_id = :catalogo AND codigo = :codigo AND activo = 1');
        $insertItem = $this->pdo->prepare('INSERT INTO pf_catalogo_items (id, catalogo_id, parent_id, codigo, nombre, valor, descripcion, orden, activo, metadata_json, created_by, updated_by, created_at, updated_at) VALUES (:id, :catalogo_id, NULL, :codigo, :nombre, :valor, NULL, :orden, 1, NULL, "install", "install", UTC_TIMESTAMP(6), UTC_TIMESTAMP(6))');
        foreach ($items as $index => $item) {
            $itemCode = $this->codeFromText($item);
            $exists->execute(['catalogo' => $catalogId, 'codigo' => $itemCode]);
            if ((int) $exists->fetchColumn() === 0) {
                $insertItem->execute(['id' => Uuid::v4(), 'catalogo_id' => $catalogId, 'codigo' => $itemCode, 'nombre' => $item, 'valor' => $item, 'orden' => $index + 1]);
            }
        }
    }

    public function syncDecisionCandidates(string $actor): void
    {
        try {
            $rows = $this->pdo->query('SELECT rr.*, dc.codigo decision_codigo, dc.tema, dc.prioridad, dr.aplica, dr.estado_decision, dr.estado_implementacion
                FROM protocolo_decision_revision_riesgos rr
                INNER JOIN protocolo_decision_catalogo dc ON dc.id = rr.decision_catalogo_id
                LEFT JOIN protocolo_decisiones_respuestas dr ON dr.decision_catalogo_id = dc.id AND dr.activo = 1
                WHERE rr.activo = 1')->fetchAll();
        } catch (\Throwable) {
            return;
        }
        $check = $this->pdo->prepare('SELECT COUNT(*) FROM protocolo_riesgo_candidatos WHERE decision_revision_id = :id AND activo = 1');
        $insert = $this->pdo->prepare('INSERT INTO protocolo_riesgo_candidatos (
            id, codigo, titulo, fuente, fecha_recepcion, usuario_envia, decision_codigo, decision_revision_id,
            alerta_sugerida, posible_causa, posible_evento, posible_consecuencia, categoria_sugerida,
            alcance_sugerido, responsable_revision, estado, observaciones, activo, created_at, updated_at, created_by, updated_by
        ) VALUES (
            :id, :codigo, :titulo, "06_Decisiones", :fecha_recepcion, :usuario_envia, :decision_codigo, :decision_revision_id,
            :alerta_sugerida, :posible_causa, :posible_evento, :posible_consecuencia, NULL,
            :alcance_sugerido, NULL, "Recibido", :observaciones, 1, UTC_TIMESTAMP(6), UTC_TIMESTAMP(6), :created_by, :updated_by
        )');
        foreach ($rows as $row) {
            $check->execute(['id' => $row['id']]);
            if ((int) $check->fetchColumn() > 0) {
                continue;
            }
            $id = Uuid::v4();
            $insert->execute([
                'id' => $id,
                'codigo' => $this->nextCandidateCode(),
                'titulo' => 'Revision riesgo ' . (string) $row['decision_codigo'] . ' - ' . (string) $row['tema'],
                'fecha_recepcion' => substr((string) ($row['fecha_solicitud'] ?? date('Y-m-d')), 0, 10),
                'usuario_envia' => $row['responsable_solicita'] ?: $actor,
                'decision_codigo' => $row['decision_codigo'],
                'decision_revision_id' => $row['id'],
                'alerta_sugerida' => $row['alerta_sugerida'],
                'posible_causa' => $row['posible_causa'],
                'posible_evento' => 'Pendiente de confirmar por revisor humano.',
                'posible_consecuencia' => $row['posible_consecuencia'],
                'alcance_sugerido' => 'Aplicabilidad: ' . (string) ($row['aplica'] ?? 'Pendiente') . '; decision: ' . (string) ($row['estado_decision'] ?? 'Pendiente') . '; implementacion: ' . (string) ($row['estado_implementacion'] ?? 'Pendiente') . '; prioridad: ' . (string) ($row['prioridad'] ?? 'No definida'),
                'observaciones' => $row['observaciones'],
                'created_by' => $actor,
                'updated_by' => $actor,
            ]);
            $this->recordHistory('candidato', $id, 'recepcion', null, 'Recibido', 'Candidato sincronizado desde 06_Decisiones', '06_Decisiones', $actor);
        }
    }

    /** @return list<array<string,mixed>> */
    private function candidates(): array
    {
        $rows = $this->pdo->query('SELECT c.*, r.codigo riesgo_relacionado_codigo, r.titulo riesgo_relacionado_titulo FROM protocolo_riesgo_candidatos c LEFT JOIN protocolo_riesgos r ON r.id = c.riesgo_relacionado_id WHERE c.activo = 1 ORDER BY FIELD(c.estado, "Recibido", "Pendiente de revisión", "En revisión", "Requiere información", "En observación", "Aprobado para conversión", "Relacionado con riesgo existente", "Convertido en riesgo", "Descartado"), c.created_at DESC')->fetchAll();
        return array_map(fn(array $row): array => $row + ['alertas' => $this->candidateAlerts($row)], $rows);
    }

    /** @return list<array<string,mixed>> */
    private function risks(?array $controls = null, ?array $actions = null, ?array $docs = null): array
    {
        $rows = $this->riskRows();
        $controls ??= $this->controlsByRisk();
        $actions ??= $this->actionsByRisk();
        $docs ??= $this->documentsByRisk();
        return $this->enrichRisks($rows, $controls, $actions, $docs);
    }

    /** @return list<array<string,mixed>> */
    private function riskRows(): array
    {
        return $this->pdo->query('SELECT * FROM protocolo_riesgos WHERE activo = 1 ORDER BY FIELD(nivel_residual, "Crítico", "Alto", "Moderado", "Bajo"), updated_at DESC')->fetchAll();
    }

    /** @param list<array<string,mixed>> $rows @param array<string,list<array<string,mixed>>> $controls @param array<string,list<array<string,mixed>>> $actions @param array<string,list<array<string,mixed>>> $docs @return list<array<string,mixed>> */
    private function enrichRisks(array $rows, array $controls, array $actions, array $docs): array
    {
        return array_map(fn(array $row): array => $row + ['alertas' => $this->riskAlerts($row, $controls[$row['id']] ?? [], $actions[$row['id']] ?? [], $docs[$row['id']] ?? [])], $rows);
    }

    /** @return array<string,list<array<string,mixed>>> */
    private function controlsByRisk(): array
    {
        return $this->groupByRisk($this->pdo->query('SELECT c.*, d.codigo documento_codigo, d.documento_tipo FROM protocolo_riesgo_controles c LEFT JOIN protocolo_documentos d ON d.id = c.documento_id WHERE c.activo = 1 ORDER BY c.created_at DESC')->fetchAll());
    }

    /** @return array<string,list<array<string,mixed>>> */
    private function actionsByRisk(): array
    {
        return $this->groupByRisk($this->pdo->query('SELECT a.*, d.codigo documento_codigo, d.documento_tipo FROM protocolo_riesgo_acciones a LEFT JOIN protocolo_documentos d ON d.id = a.documento_id WHERE a.activo = 1 ORDER BY a.fecha_objetivo, a.created_at DESC')->fetchAll());
    }

    /** @return array<string,list<array<string,mixed>>> */
    private function documentsByRisk(): array
    {
        return $this->groupByRisk($this->pdo->query('SELECT rd.*, d.codigo documento_codigo, d.documento_tipo, d.estado documento_estado, d.fecha_vencimiento, d.archivo_ruta FROM protocolo_riesgo_documentos rd INNER JOIN protocolo_documentos d ON d.id = rd.documento_id WHERE rd.activo = 1 ORDER BY rd.created_at DESC')->fetchAll());
    }

    /** @return list<array<string,mixed>> */
    private function riskOptions(): array
    {
        return $this->pdo->query('SELECT id, codigo, titulo FROM protocolo_riesgos WHERE activo = 1 ORDER BY codigo')->fetchAll();
    }

    /** @param list<array<string,mixed>> $rows @return array<string,list<array<string,mixed>>> */
    private function groupByRisk(array $rows): array
    {
        $grouped = [];
        foreach ($rows as $row) {
            $grouped[(string) $row['riesgo_id']][] = $row;
        }
        return $grouped;
    }

    /** @return list<array<string,mixed>> */
    private function recentHistory(): array
    {
        return $this->pdo->query('SELECT * FROM protocolo_riesgo_historial ORDER BY created_at DESC LIMIT 120')->fetchAll();
    }

    /** @param list<array<string,mixed>> $candidates @param list<array<string,mixed>> $risks @param array<string,list<array<string,mixed>>> $controls @param array<string,list<array<string,mixed>>> $actions @return array<string,int> */
    private function summary(array $candidates, array $risks, array $controls, array $actions): array
    {
        $flatControls = [];
        foreach ($controls as $group) {
            foreach ($group as $row) {
                $flatControls[] = $row;
            }
        }
        $flatActions = [];
        foreach ($actions as $group) {
            foreach ($group as $row) {
                $flatActions[] = $row;
            }
        }
        $documentCount = (int) $this->pdo->query('SELECT COUNT(*) FROM protocolo_riesgo_documentos WHERE activo = 1')->fetchColumn();
        return [
            'candidatos' => count($candidates),
            'candidatos_pendientes' => count(array_filter($candidates, static fn(array $row): bool => !in_array($row['estado'], ['Descartado', 'Convertido en riesgo', 'Relacionado con riesgo existente'], true))),
            'candidatos_descartados' => count(array_filter($candidates, static fn(array $row): bool => $row['estado'] === 'Descartado')),
            'riesgos' => count($risks),
            'criticos' => count(array_filter($risks, static fn(array $row): bool => in_array($row['nivel_residual'] ?? $row['nivel_inherente'] ?? '', ['Crítico'], true))),
            'altos' => count(array_filter($risks, static fn(array $row): bool => in_array($row['nivel_residual'] ?? $row['nivel_inherente'] ?? '', ['Alto'], true))),
            'materializados' => count(array_filter($risks, static fn(array $row): bool => $row['estado'] === 'Materializado')),
            'sin_responsable' => count(array_filter($risks, static fn(array $row): bool => trim((string) $row['propietario']) === '')),
            'sin_tratamiento' => count(array_filter($risks, static fn(array $row): bool => empty($row['estrategia_tratamiento']))),
            'controles' => count($flatControls),
            'controles_no_efectivos' => count(array_filter($flatControls, static fn(array $row): bool => $row['estado'] === 'No efectivo')),
            'acciones' => count($flatActions),
            'acciones_vencidas' => count(array_filter($flatActions, fn(array $row): bool => $this->isPast($row['fecha_objetivo'] ?? null) && !in_array((string) $row['estado'], ['Terminada', 'Verificada', 'Cancelada'], true))),
            'documentos_relacionados' => $documentCount,
            'cerrados' => count(array_filter($risks, static fn(array $row): bool => $row['estado'] === 'Cerrado')),
            'reabiertos' => count(array_filter($risks, static fn(array $row): bool => $row['estado'] === 'Reabierto')),
            'altos_criticos' => count(array_filter($risks, static fn(array $row): bool => in_array($row['nivel_residual'] ?? $row['nivel_inherente'] ?? '', ['Alto', 'Crítico', 'Critico'], true))),
        ];
    }

    /** @return array<string,mixed> */
    private function candidate(string $id): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM protocolo_riesgo_candidatos WHERE id = :id AND activo = 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        if (!is_array($row)) {
            throw new RuntimeException('Candidato no encontrado.');
        }
        return $row + ['alertas' => $this->candidateAlerts($row)];
    }

    /** @return array<string,mixed> */
    private function risk(string $id): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM protocolo_riesgos WHERE id = :id AND activo = 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        if (!is_array($row)) {
            throw new RuntimeException('Riesgo no encontrado.');
        }
        return $row;
    }

    /** @return array<string,mixed> */
    private function control(string $id): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM protocolo_riesgo_controles WHERE id = :id AND activo = 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        if (!is_array($row)) {
            throw new RuntimeException('Control no encontrado.');
        }
        return $row;
    }

    /** @return array<string,mixed> */
    private function action(string $id): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM protocolo_riesgo_acciones WHERE id = :id AND activo = 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        if (!is_array($row)) {
            throw new RuntimeException('Accion no encontrada.');
        }
        return $row;
    }

    private function convertCandidate(string $candidateId, string $riskId, string $actor): void
    {
        $stmt = $this->pdo->prepare('UPDATE protocolo_riesgo_candidatos SET estado = "Convertido en riesgo", riesgo_relacionado_id = :riesgo_id, updated_at = UTC_TIMESTAMP(6), updated_by = :actor WHERE id = :id AND activo = 1');
        $stmt->execute(['id' => $candidateId, 'riesgo_id' => $riskId, 'actor' => $actor]);
        $candidate = $this->candidate($candidateId);
        if (is_string($candidate['decision_codigo'] ?? null) && $candidate['decision_codigo'] !== '') {
            $rel = $this->pdo->prepare('INSERT IGNORE INTO protocolo_riesgo_decisiones (id, riesgo_id, decision_codigo, observaciones, activo, created_at, created_by) VALUES (:id, :riesgo_id, :decision_codigo, "Relacion creada desde candidato convertido.", 1, UTC_TIMESTAMP(6), :created_by)');
            $rel->execute(['id' => Uuid::v4(), 'riesgo_id' => $riskId, 'decision_codigo' => $candidate['decision_codigo'], 'created_by' => $actor]);
        }
        $this->recordHistory('candidato', $candidateId, 'estado', $candidate['estado'] ?? null, 'Convertido en riesgo', 'Conversion aprobada por revision humana', 'conversion', $actor);
    }

    /** @param array<string,mixed> $data @return array<string,mixed> */
    private function candidateParams(array $data, bool $editing): array
    {
        $state = $this->choice($data['estado'] ?? null, self::CANDIDATE_STATES, $editing ? 'Pendiente de revisión' : 'Recibido');
        $params = [
            'titulo' => $this->required($data['titulo'] ?? null, 'Titulo del candidato obligatorio.'),
            'fuente' => $this->optional($data['fuente'] ?? null) ?? 'Registro manual',
            'fecha_recepcion' => $this->date($data['fecha_recepcion'] ?? null) ?? date('Y-m-d'),
            'usuario_envia' => $this->optional($data['usuario_envia'] ?? null),
            'decision_codigo' => $this->optional($data['decision_codigo'] ?? null),
            'alerta_sugerida' => $this->optional($data['alerta_sugerida'] ?? null),
            'posible_causa' => $this->optional($data['posible_causa'] ?? null),
            'posible_evento' => $this->optional($data['posible_evento'] ?? null),
            'posible_consecuencia' => $this->optional($data['posible_consecuencia'] ?? null),
            'categoria_sugerida' => $this->optional($data['categoria_sugerida'] ?? null),
            'alcance_sugerido' => $this->optional($data['alcance_sugerido'] ?? null),
            'responsable_revision' => $this->optional($data['responsable_revision'] ?? null),
            'estado' => $state,
            'informacion_solicitada' => $this->optional($data['informacion_solicitada'] ?? null),
            'fecha_limite_respuesta' => $this->date($data['fecha_limite_respuesta'] ?? null),
            'resultado_revision' => $this->optional($data['resultado_revision'] ?? null),
            'justificacion' => $this->optional($data['justificacion'] ?? null),
            'riesgo_relacionado_id' => $this->uuidOrNull($data['riesgo_relacionado_id'] ?? null),
            'observaciones' => $this->optional($data['observaciones'] ?? null),
        ];
        if ($state === 'Descartado' && $params['justificacion'] === null) {
            throw new RuntimeException('Descartar candidato exige justificacion.');
        }
        if ($state === 'Requiere información' && ($params['informacion_solicitada'] === null || $params['responsable_revision'] === null || $params['fecha_limite_respuesta'] === null)) {
            throw new RuntimeException('Requiere informacion exige solicitud, responsable y fecha limite.');
        }
        if ($state === 'Relacionado con riesgo existente' && $params['riesgo_relacionado_id'] === null) {
            throw new RuntimeException('Relacionar con riesgo existente exige identificar el riesgo.');
        }
        return $params;
    }

    /** @param array<string,mixed> $data @return array<string,mixed> */
    private function riskParams(array $data): array
    {
        $prob = $this->scale($data['probabilidad_inherente'] ?? null);
        $impact = $this->scale($data['impacto_inherente'] ?? null);
        $resProb = $this->scale($data['probabilidad_residual'] ?? null);
        $resImpact = $this->scale($data['impacto_residual'] ?? null);
        $score = $prob !== null && $impact !== null ? $prob * $impact : null;
        $resScore = $resProb !== null && $resImpact !== null ? $resProb * $resImpact : null;
        $state = $this->choice($data['estado'] ?? null, self::RISK_STATES, 'Identificado');
        $params = [
            'titulo' => $this->required($data['titulo'] ?? null, 'Titulo del riesgo obligatorio.'),
            'descripcion' => $this->optional($data['descripcion'] ?? null),
            'categoria' => $this->required($data['categoria'] ?? null, 'Categoria obligatoria.'),
            'subcategoria' => $this->optional($data['subcategoria'] ?? null),
            'fecha_identificacion' => $this->date($data['fecha_identificacion'] ?? null) ?? date('Y-m-d'),
            'fuente' => $this->optional($data['fuente'] ?? null) ?? 'Registro manual',
            'propietario' => $this->required($data['propietario'] ?? null, 'Propietario del riesgo obligatorio.'),
            'aprobador' => $this->optional($data['aprobador'] ?? null),
            'estado' => $state,
            'causa' => $this->required($data['causa'] ?? null, 'Causa obligatoria.'),
            'evento' => $this->required($data['evento'] ?? null, 'Evento de riesgo obligatorio.'),
            'consecuencia' => $this->required($data['consecuencia'] ?? null, 'Consecuencia obligatoria.'),
            'probabilidad_inherente' => $prob,
            'impacto_inherente' => $impact,
            'puntaje_inherente' => $score,
            'nivel_inherente' => $this->level($score),
            'justificacion_probabilidad' => $this->optional($data['justificacion_probabilidad'] ?? null),
            'justificacion_impacto' => $this->optional($data['justificacion_impacto'] ?? null),
            'evaluador' => $this->optional($data['evaluador'] ?? null),
            'fecha_evaluacion' => $this->date($data['fecha_evaluacion'] ?? null),
            'estrategia_tratamiento' => $this->optional($data['estrategia_tratamiento'] ?? null),
            'probabilidad_residual' => $resProb,
            'impacto_residual' => $resImpact,
            'puntaje_residual' => $resScore,
            'nivel_residual' => $this->level($resScore),
            'justificacion_residual' => $this->optional($data['justificacion_residual'] ?? null),
            'evaluador_residual' => $this->optional($data['evaluador_residual'] ?? null),
            'fecha_evaluacion_residual' => $this->date($data['fecha_evaluacion_residual'] ?? null),
            'aceptador_residual' => $this->optional($data['aceptador_residual'] ?? null),
            'frecuencia_revision' => $this->optional($data['frecuencia_revision'] ?? null),
            'ultima_revision' => $this->date($data['ultima_revision'] ?? null),
            'proxima_revision' => $this->date($data['proxima_revision'] ?? null),
            'indicadores' => $this->optional($data['indicadores'] ?? null),
            'senales_materializacion' => $this->optional($data['senales_materializacion'] ?? null),
            'fecha_materializacion' => $this->date($data['fecha_materializacion'] ?? null),
            'descripcion_materializacion' => $this->optional($data['descripcion_materializacion'] ?? null),
            'fecha_cierre' => $this->date($data['fecha_cierre'] ?? null),
            'motivo_cierre' => $this->optional($data['motivo_cierre'] ?? null),
            'residual_aceptado' => $this->optional($data['residual_aceptado'] ?? null),
            'evidencia_cierre' => $this->optional($data['evidencia_cierre'] ?? null),
            'solicita_cierre' => $this->optional($data['solicita_cierre'] ?? null),
            'aprueba_cierre' => $this->optional($data['aprueba_cierre'] ?? null),
            'observaciones_finales' => $this->optional($data['observaciones_finales'] ?? null),
        ];
        if (($prob !== null || $impact !== null) && ($prob === null || $impact === null || $params['justificacion_probabilidad'] === null || $params['justificacion_impacto'] === null)) {
            throw new RuntimeException('Probabilidad e impacto exigen ambos valores y sus justificaciones.');
        }
        if (in_array($state, ['Evaluado', 'Pendiente de aprobación', 'Aprobado', 'Tratamiento pendiente', 'En tratamiento', 'En monitoreo'], true) && ($params['evaluador'] === null || $prob === null || $impact === null)) {
            throw new RuntimeException('El riesgo evaluado o aprobado exige evaluador, probabilidad e impacto.');
        }
        if ($state === 'Aprobado' && $params['aprobador'] === null) {
            throw new RuntimeException('Aprobar riesgo exige persona u organo aprobador.');
        }
        if (in_array($state, ['Tratamiento pendiente', 'En tratamiento'], true) && $params['estrategia_tratamiento'] === null) {
            throw new RuntimeException('Pasar a tratamiento exige estrategia definida.');
        }
        if ($state === 'Materializado' && ($params['fecha_materializacion'] === null || $params['descripcion_materializacion'] === null)) {
            throw new RuntimeException('Materializado exige fecha y descripcion del evento.');
        }
        if ($state === 'Cerrado' && ($params['fecha_cierre'] === null || $params['motivo_cierre'] === null || $params['evidencia_cierre'] === null || $params['aprueba_cierre'] === null)) {
            throw new RuntimeException('Cerrar riesgo exige fecha, motivo, evidencia y aprobacion.');
        }
        if ($state === 'Suspendido' && ($params['motivo_cierre'] === null || $params['proxima_revision'] === null)) {
            throw new RuntimeException('Suspendido exige motivo y proxima fecha de revision.');
        }
        if ($state === 'Reabierto' && $this->optional($data['motivo_cambio'] ?? null) === null) {
            throw new RuntimeException('Reabierto exige justificacion.');
        }
        if ($params['residual_aceptado'] === 'Si' && $params['aceptador_residual'] === null) {
            throw new RuntimeException('Riesgo residual aceptado exige indicar quien lo acepta.');
        }
        return $params;
    }

    /** @param array<string,mixed> $row @return list<string> */
    private function candidateAlerts(array $row): array
    {
        $alerts = [];
        if (in_array((string) $row['estado'], ['Recibido', 'Pendiente de revisión'], true)) {
            $alerts[] = 'Candidato sin revisar';
        }
        if ($row['estado'] === 'Requiere información' && $this->isPast($row['fecha_limite_respuesta'] ?? null)) {
            $alerts[] = 'Información solicitada vencida';
        } elseif ($row['fecha_limite_respuesta'] && $this->isSoon($row['fecha_limite_respuesta'], (int) $this->config('dias_candidato_vencer', '10'))) {
            $alerts[] = 'Candidato próximo a vencer';
        }
        return $alerts;
    }

    /** @param array<string,mixed> $row @param list<array<string,mixed>> $controls @param list<array<string,mixed>> $actions @param list<array<string,mixed>> $docs @return list<string> */
    private function riskAlerts(array $row, array $controls, array $actions, array $docs): array
    {
        $alerts = [];
        if (trim((string) $row['propietario']) === '') $alerts[] = 'Riesgo sin responsable';
        if ($row['probabilidad_inherente'] === null || $row['impacto_inherente'] === null) $alerts[] = 'Riesgo sin evaluación';
        if ($row['nivel_inherente'] === 'Crítico' && $row['estado'] !== 'Aprobado') $alerts[] = 'Riesgo crítico sin aprobación';
        if (in_array($row['nivel_residual'] ?: $row['nivel_inherente'], ['Alto', 'Crítico'], true) && $row['estrategia_tratamiento'] === null) $alerts[] = 'Riesgo alto o crítico sin tratamiento';
        if ($this->isPast($row['proxima_revision'] ?? null)) $alerts[] = 'Revisión periódica vencida';
        if ($row['estado'] === 'Materializado') $alerts[] = 'Riesgo materializado';
        if ($this->isResidualAboveAppetite((string) ($row['nivel_residual'] ?? ''))) $alerts[] = 'Riesgo residual superior al apetito definido';
        if ($this->isPastPeriod($row['updated_at'] ?? null, (int) $this->config('dias_sin_actualizacion', '90'))) $alerts[] = 'Riesgo sin actualización durante el periodo configurado';
        foreach ($controls as $control) {
            if ($control['documento_id'] === null) $alerts[] = 'Control sin evidencia';
            if ($control['estado'] === 'No efectivo') $alerts[] = 'Control no efectivo';
        }
        foreach ($actions as $action) {
            if (trim((string) $action['responsable']) === '') $alerts[] = 'Tratamiento sin responsable';
            if ($this->isPast($action['fecha_objetivo'] ?? null) && !in_array((string) $action['estado'], ['Terminada', 'Verificada', 'Cancelada'], true)) $alerts[] = 'Acción vencida';
            if ($action['estado'] === 'Bloqueada') $alerts[] = 'Acción bloqueada';
        }
        foreach ($docs as $doc) {
            if ($this->isPast($doc['fecha_vencimiento'] ?? null)) $alerts[] = 'Documento relacionado vencido';
            if (str_contains((string) ($doc['documento_tipo'] ?? ''), 'PROP-DOC')) $alerts[] = 'Documento provisional utilizado';
        }
        return array_values(array_unique($alerts));
    }

    private function recordHistory(string $type, string $entityId, string $field, mixed $old, mixed $new, ?string $reason, ?string $origin, string $actor): void
    {
        $stmt = $this->pdo->prepare('INSERT INTO protocolo_riesgo_historial (id, entidad_tipo, entidad_id, campo, valor_anterior, valor_nuevo, motivo, origen, usuario, created_at) VALUES (:id, :entidad_tipo, :entidad_id, :campo, :valor_anterior, :valor_nuevo, :motivo, :origen, :usuario, UTC_TIMESTAMP(6))');
        $stmt->execute(['id' => Uuid::v4(), 'entidad_tipo' => $type, 'entidad_id' => $entityId, 'campo' => $field, 'valor_anterior' => $old === null ? null : (string) $old, 'valor_nuevo' => $new === null ? null : (string) $new, 'motivo' => $reason, 'origen' => $origin, 'usuario' => $actor]);
    }

    /** @param array<string,mixed> $before @param array<string,mixed> $after */
    private function writeDiff(string $type, string $entityId, array $before, array $after, ?string $reason, ?string $origin, string $actor): void
    {
        foreach ($after as $field => $new) {
            $old = $before[$field] ?? null;
            if ((string) $old !== (string) $new) {
                $this->recordHistory($type, $entityId, (string) $field, $old, $new, $reason, $origin, $actor);
            }
        }
    }

    /** @return list<array<string,mixed>> */
    private function catalogItems(string $catalog): array
    {
        $stmt = $this->pdo->prepare('SELECT i.codigo, i.nombre, i.valor FROM pf_catalogo_items i INNER JOIN pf_catalogos c ON c.id = i.catalogo_id WHERE c.codigo = :catalog AND c.activo = 1 AND i.activo = 1 ORDER BY i.orden, i.nombre');
        $stmt->execute(['catalog' => $catalog]);
        return $stmt->fetchAll();
    }

    /** @return list<array<string,mixed>> */
    private function availableDocuments(): array
    {
        try {
            return $this->pdo->query('SELECT id, codigo, documento_tipo, sujeto_nombre, estado FROM protocolo_documentos WHERE activo = 1 ORDER BY documento_tipo, sujeto_nombre')->fetchAll();
        } catch (\Throwable) {
            return [];
        }
    }

    private function nextCandidateCode(): string
    {
        $next = (int) $this->pdo->query("SELECT COALESCE(MAX(CAST(SUBSTRING(codigo, 5) AS UNSIGNED)), 0) + 1 FROM protocolo_riesgo_candidatos")->fetchColumn();
        return sprintf('CAN-%03d', $next);
    }

    private function nextRiskCode(): string
    {
        $next = (int) $this->pdo->query("SELECT COALESCE(MAX(CAST(SUBSTRING(codigo, 5) AS UNSIGNED)), 0) + 1 FROM protocolo_riesgos")->fetchColumn();
        return sprintf('RIE-%03d', $next);
    }

    private function level(?int $score): ?string
    {
        if ($score === null) return null;
        return match (true) {
            $score <= 4 => 'Bajo',
            $score <= 9 => 'Moderado',
            $score <= 16 => 'Alto',
            default => 'Crítico',
        };
    }

    private function isResidualAboveAppetite(string $level): bool
    {
        $rank = ['Bajo' => 1, 'Moderado' => 2, 'Alto' => 3, 'Crítico' => 4];
        $appetite = $this->config('apetito_maximo_residual', 'Moderado');
        return ($rank[$level] ?? 0) > ($rank[$appetite] ?? 2);
    }

    private function config(string $key, string $default): string
    {
        $stmt = $this->pdo->prepare('SELECT valor FROM protocolo_riesgo_config WHERE clave = :clave');
        $stmt->execute(['clave' => $key]);
        $value = $stmt->fetchColumn();
        return is_string($value) && $value !== '' ? $value : $default;
    }

    private function required(mixed $value, string $message): string
    {
        if (!is_string($value) || trim($value) === '') throw new RuntimeException($message);
        return trim($value);
    }

    private function optional(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    /** @param list<string> $allowed */
    private function choice(mixed $value, array $allowed, string $default): string
    {
        $value = is_string($value) && trim($value) !== '' ? trim($value) : $default;
        if (!in_array($value, $allowed, true)) throw new RuntimeException('Valor no permitido.');
        return $value;
    }

    private function date(mixed $value): ?string
    {
        if (!is_string($value) || trim($value) === '') return null;
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', trim($value)) !== 1) throw new RuntimeException('Fecha invalida.');
        return trim($value);
    }

    private function uuidOrNull(mixed $value): ?string
    {
        return is_string($value) && preg_match('/^[0-9a-f-]{36}$/i', $value) === 1 ? $value : null;
    }

    private function scale(mixed $value): ?int
    {
        if ($value === null || trim((string) $value) === '') return null;
        $number = (int) preg_replace('/[^0-9]/', '', substr((string) $value, 0, 2));
        if ($number < 1 || $number > 5) throw new RuntimeException('Probabilidad o impacto invalido.');
        return $number;
    }

    private function percent(mixed $value): ?float
    {
        if ($value === null || trim((string) $value) === '') return null;
        $number = (float) str_replace(',', '.', (string) $value);
        if ($number < 0 || $number > 100) throw new RuntimeException('Avance debe estar entre 0 y 100.');
        return $number;
    }

    private function money(mixed $value): ?float
    {
        if ($value === null || trim((string) $value) === '') return null;
        $clean = str_replace(['$', ' ', '.'], '', (string) $value);
        $clean = str_replace(',', '.', $clean);
        if (!is_numeric($clean)) throw new RuntimeException('Valor monetario invalido.');
        return (float) $clean;
    }

    private function isPast(mixed $value): bool
    {
        return is_string($value) && $value !== '' && substr($value, 0, 10) < date('Y-m-d');
    }

    private function isSoon(mixed $value, int $days): bool
    {
        return is_string($value) && $value !== '' && substr($value, 0, 10) <= date('Y-m-d', strtotime('+' . $days . ' days'));
    }

    private function isPastPeriod(mixed $value, int $days): bool
    {
        return is_string($value) && $value !== '' && substr($value, 0, 10) < date('Y-m-d', strtotime('-' . $days . ' days'));
    }

    private function codeFromText(string $text): string
    {
        $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text) ?: $text;
        return trim((string) preg_replace('/[^A-Z0-9]+/', '_', strtoupper($ascii)), '_');
    }
}
