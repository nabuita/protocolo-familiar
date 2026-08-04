<?php
declare(strict_types=1);
$e = static fn(mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$dashboard = is_array($riesgoDashboard ?? null) ? $riesgoDashboard : [];
$options = is_array($riesgoOptions ?? null) ? $riesgoOptions : [];
$summary = is_array($dashboard['summary'] ?? null) ? $dashboard['summary'] : [];
$candidates = is_array($dashboard['candidatos'] ?? null) ? $dashboard['candidatos'] : [];
$risks = is_array($dashboard['riesgos'] ?? null) ? $dashboard['riesgos'] : [];
$controls = is_array($dashboard['controles'] ?? null) ? $dashboard['controles'] : [];
$actions = is_array($dashboard['acciones'] ?? null) ? $dashboard['acciones'] : [];
$documents = is_array($dashboard['documentos'] ?? null) ? $dashboard['documentos'] : [];
$history = is_array($dashboard['historial'] ?? null) ? $dashboard['historial'] : [];
$opt = static fn(string $key): array => is_array($options[$key] ?? null) ? $options[$key] : [];
$select = static function (string $name, array $values, ?string $selected = null, string $placeholder = 'Seleccione...') use ($e): void {
    echo '<select name="' . $e($name) . '"><option value="" disabled' . ($selected === null ? ' selected' : '') . '>' . $e($placeholder) . '</option>';
    foreach ($values as $value) {
        $label = is_array($value) ? (string) ($value['titulo'] ?? $value['nombre'] ?? $value['codigo'] ?? '') : (string) $value;
        $val = is_array($value) ? (string) ($value['id'] ?? $value['codigo'] ?? $label) : $label;
        echo '<option value="' . $e($val) . '"' . ($selected === $val ? ' selected' : '') . '>' . $e($label) . '</option>';
    }
    echo '</select>';
};
$riskChoiceLabel = static fn(array $risk): string => trim((string) ($risk['codigo'] ?? '') . ' - ' . (string) ($risk['titulo'] ?? ''));
$docsChoice = array_map(static fn(array $doc): array => ['id' => (string) $doc['id'], 'titulo' => trim((string) ($doc['codigo'] ?? '') . ' - ' . (string) ($doc['documento_tipo'] ?? '') . ' - ' . (string) ($doc['sujeto_nombre'] ?? ''))], $opt('documents'));
$riskChoices = array_map(static fn(array $risk): array => ['id' => (string) $risk['id'], 'titulo' => trim((string) ($risk['codigo'] ?? '') . ' - ' . (string) ($risk['titulo'] ?? ''))], $risks);
?>
<section class="riesgos-page" data-riesgos>
    <div class="module-head">
        <div>
            <p class="section-kicker">07. Sistema de riesgos familiares y empresariales</p>
            <h1>Riesgos</h1>
            <p>Gestiona candidatos, riesgos definitivos, controles, tratamientos y evidencia documental. Las alertas de decisiones llegan como candidatos: no se convierten solas en riesgos.</p>
        </div>
        <button type="button" class="primary" data-risk-open="new-risk">Nuevo riesgo</button>
    </div>

    <div class="risk-summary" aria-label="Resumen de riesgos">
        <div><strong><?= $e($summary['candidatos'] ?? 0) ?></strong><span>Candidatos</span></div>
        <div><strong><?= $e($summary['riesgos'] ?? 0) ?></strong><span>Riesgos definitivos</span></div>
        <div><strong><?= $e($summary['altos_criticos'] ?? 0) ?></strong><span>Altos / criticos</span></div>
        <div><strong><?= $e($summary['controles'] ?? 0) ?></strong><span>Controles</span></div>
        <div><strong><?= $e($summary['acciones_vencidas'] ?? 0) ?></strong><span>Acciones vencidas</span></div>
        <div><strong><?= $e($summary['documentos_relacionados'] ?? 0) ?></strong><span>Docs vinculados</span></div>
    </div>

    <div class="risk-toolbar" role="tablist" aria-label="Vistas de riesgos">
        <button type="button" class="active" data-risk-tab="candidatos">Candidatos</button>
        <button type="button" data-risk-tab="registro">Registro maestro</button>
        <button type="button" data-risk-tab="matriz">Matriz</button>
        <button type="button" data-risk-tab="controles">Controles</button>
        <button type="button" data-risk-tab="acciones">Tratamientos</button>
        <button type="button" data-risk-tab="documentos">Documentos</button>
        <button type="button" data-risk-tab="historial">Historial</button>
    </div>

    <div class="risk-status" data-risk-status hidden></div>

    <section class="risk-panel active" data-risk-panel="candidatos">
        <div class="risk-section-head">
            <div>
                <h2>Bandeja de candidatos</h2>
                <p>Revisa alertas sugeridas y solo convierte en riesgo cuando exista analisis humano.</p>
            </div>
            <button type="button" class="secondary" data-risk-open="new-candidate">Nuevo candidato</button>
        </div>

        <form class="risk-form collapsed" data-risk-form data-risk-form-block="new-candidate" action="<?= $e($basePath) ?>/protocolo-familiar/riesgos/candidatos" method="post">
            <input type="hidden" name="csrf_token" value="<?= $e($csrfToken) ?>">
            <h3>Registrar candidato manual</h3>
            <div class="risk-grid">
                <label>Titulo<input name="titulo" required></label>
                <label>Fuente<input name="fuente" value="Registro manual"></label>
                <label>Fecha recepcion<input type="date" name="fecha_recepcion" value="<?= $e(date('Y-m-d')) ?>"></label>
                <label>Responsable revision<input name="responsable_revision"></label>
                <label>Categoria sugerida<?php $select('categoria_sugerida', $opt('categories')); ?></label>
                <label>Estado<?php $select('estado', $opt('candidate_states'), 'Recibido'); ?></label>
            </div>
            <div class="risk-grid three">
                <label>Posible causa<textarea name="posible_causa"></textarea></label>
                <label>Posible evento<textarea name="posible_evento"></textarea></label>
                <label>Posible consecuencia<textarea name="posible_consecuencia"></textarea></label>
            </div>
            <label>Observaciones<textarea name="observaciones"></textarea></label>
            <button class="primary">Guardar candidato</button>
        </form>

        <div class="risk-accordion">
            <?php if ($candidates === []): ?>
                <div class="risk-empty">No hay candidatos pendientes. Cuando 06_Decisiones envie una alerta a revision, aparecera aqui sin crear riesgo definitivo.</div>
            <?php endif; ?>
            <?php foreach ($candidates as $candidate): ?>
                <details class="risk-row">
                    <summary>
                        <span><strong><?= $e($candidate['codigo'] ?? '') ?></strong> <?= $e($candidate['titulo'] ?? '') ?></span>
                        <span><?= $e($candidate['estado'] ?? '') ?></span>
                    </summary>
                    <div class="risk-row-body">
                        <div class="risk-facts">
                            <span>Fuente: <?= $e($candidate['fuente'] ?? '') ?></span>
                            <span>Decision: <?= $e($candidate['decision_codigo'] ?? 'Sin decision') ?></span>
                            <span>Categoria: <?= $e($candidate['categoria_sugerida'] ?? 'Sin categoria') ?></span>
                            <span>Responsable: <?= $e($candidate['responsable_revision'] ?? 'Sin responsable') ?></span>
                        </div>
                        <?php if (is_array($candidate['alertas'] ?? null) && $candidate['alertas'] !== []): ?>
                            <div class="risk-alerts"><?php foreach ($candidate['alertas'] as $alert): ?><span><?= $e($alert) ?></span><?php endforeach; ?></div>
                        <?php endif; ?>
                        <form class="risk-form compact" data-risk-form action="<?= $e($basePath) ?>/protocolo-familiar/riesgos/candidatos/<?= $e($candidate['id'] ?? '') ?>" method="post">
                            <input type="hidden" name="csrf_token" value="<?= $e($csrfToken) ?>">
                            <input type="hidden" name="titulo" value="<?= $e($candidate['titulo'] ?? '') ?>">
                            <input type="hidden" name="fuente" value="<?= $e($candidate['fuente'] ?? '') ?>">
                            <input type="hidden" name="fecha_recepcion" value="<?= $e($candidate['fecha_recepcion'] ?? '') ?>">
                            <input type="hidden" name="decision_codigo" value="<?= $e($candidate['decision_codigo'] ?? '') ?>">
                            <input type="hidden" name="alerta_sugerida" value="<?= $e($candidate['alerta_sugerida'] ?? '') ?>">
                            <input type="hidden" name="posible_causa" value="<?= $e($candidate['posible_causa'] ?? '') ?>">
                            <input type="hidden" name="posible_evento" value="<?= $e($candidate['posible_evento'] ?? '') ?>">
                            <input type="hidden" name="posible_consecuencia" value="<?= $e($candidate['posible_consecuencia'] ?? '') ?>">
                            <input type="hidden" name="categoria_sugerida" value="<?= $e($candidate['categoria_sugerida'] ?? '') ?>">
                            <div class="risk-grid">
                                <label>Estado<?php $select('estado', $opt('candidate_states'), (string) ($candidate['estado'] ?? '')); ?></label>
                                <label>Responsable revision<input name="responsable_revision" value="<?= $e($candidate['responsable_revision'] ?? '') ?>"></label>
                                <label>Fecha limite respuesta<input type="date" name="fecha_limite_respuesta" value="<?= $e($candidate['fecha_limite_respuesta'] ?? '') ?>"></label>
                                <label>Riesgo relacionado<?php $select('riesgo_relacionado_id', $riskChoices, (string) ($candidate['riesgo_relacionado_id'] ?? ''), 'Sin relacion'); ?></label>
                            </div>
                            <label>Informacion solicitada<textarea name="informacion_solicitada"><?= $e($candidate['informacion_solicitada'] ?? '') ?></textarea></label>
                            <label>Justificacion / resultado<textarea name="justificacion"><?= $e($candidate['justificacion'] ?? '') ?></textarea></label>
                            <label>Observaciones<textarea name="observaciones"><?= $e($candidate['observaciones'] ?? '') ?></textarea></label>
                            <button class="secondary">Actualizar candidato</button>
                        </form>
                        <form class="risk-form convert" data-risk-form action="<?= $e($basePath) ?>/protocolo-familiar/riesgos/candidatos/<?= $e($candidate['id'] ?? '') ?>/convertir" method="post">
                            <input type="hidden" name="csrf_token" value="<?= $e($csrfToken) ?>">
                            <h4>Convertir en riesgo definitivo</h4>
                            <div class="risk-grid">
                                <label>Titulo<input name="titulo" required value="<?= $e($candidate['titulo'] ?? '') ?>"></label>
                                <label>Categoria<?php $select('categoria', $opt('categories'), (string) ($candidate['categoria_sugerida'] ?? '')); ?></label>
                                <label>Propietario<input name="propietario" required value="<?= $e($candidate['responsable_revision'] ?? '') ?>"></label>
                                <label>Estado<?php $select('estado', $opt('risk_states'), 'Identificado'); ?></label>
                            </div>
                            <div class="risk-grid three">
                                <label>Causa<textarea name="causa" required><?= $e($candidate['posible_causa'] ?? '') ?></textarea></label>
                                <label>Evento<textarea name="evento" required><?= $e($candidate['posible_evento'] ?? '') ?></textarea></label>
                                <label>Consecuencia<textarea name="consecuencia" required><?= $e($candidate['posible_consecuencia'] ?? '') ?></textarea></label>
                            </div>
                            <button class="primary">Crear riesgo definitivo</button>
                        </form>
                    </div>
                </details>
            <?php endforeach; ?>
        </div>
    </section>

    <section class="risk-panel" data-risk-panel="registro">
        <div class="risk-section-head"><div><h2>Registro maestro</h2><p>Solo contiene riesgos aprobados o registrados por el equipo.</p></div></div>
        <form class="risk-form collapsed" data-risk-form data-risk-form-block="new-risk" action="<?= $e($basePath) ?>/protocolo-familiar/riesgos" method="post">
            <input type="hidden" name="csrf_token" value="<?= $e($csrfToken) ?>">
            <h3>Nuevo riesgo definitivo</h3>
            <div class="risk-grid">
                <label>Titulo<input name="titulo" required></label>
                <label>Categoria<?php $select('categoria', $opt('categories')); ?></label>
                <label>Propietario<input name="propietario" required></label>
                <label>Estado<?php $select('estado', $opt('risk_states'), 'Identificado'); ?></label>
                <label>Fuente<input name="fuente" value="Registro manual"></label>
                <label>Fecha identificacion<input type="date" name="fecha_identificacion" value="<?= $e(date('Y-m-d')) ?>"></label>
            </div>
            <div class="risk-grid three">
                <label>Causa<textarea name="causa" required></textarea></label>
                <label>Evento<textarea name="evento" required></textarea></label>
                <label>Consecuencia<textarea name="consecuencia" required></textarea></label>
            </div>
            <div class="risk-grid">
                <label>Probabilidad inherente<input type="number" name="probabilidad_inherente" min="1" max="5"></label>
                <label>Impacto inherente<input type="number" name="impacto_inherente" min="1" max="5"></label>
                <label>Evaluador<input name="evaluador"></label>
                <label>Aprobador<input name="aprobador"></label>
            </div>
            <button class="primary">Guardar riesgo</button>
        </form>

        <div class="risk-register">
            <?php if ($risks === []): ?><div class="risk-empty">Todavia no hay riesgos definitivos.</div><?php endif; ?>
            <?php foreach ($risks as $risk): ?>
                <details class="risk-row">
                    <summary>
                        <span><strong><?= $e($risk['codigo'] ?? '') ?></strong> <?= $e($risk['titulo'] ?? '') ?></span>
                        <span><?= $e(($risk['nivel_inherente'] ?? 'Sin evaluar') . ' / ' . ($risk['estado'] ?? '')) ?></span>
                    </summary>
                    <div class="risk-row-body">
                        <?php if (is_array($risk['alertas'] ?? null) && $risk['alertas'] !== []): ?>
                            <div class="risk-alerts danger"><?php foreach ($risk['alertas'] as $alert): ?><span><?= $e($alert) ?></span><?php endforeach; ?></div>
                        <?php endif; ?>
                        <form class="risk-form compact" data-risk-form action="<?= $e($basePath) ?>/protocolo-familiar/riesgos/<?= $e($risk['id'] ?? '') ?>" method="post">
                            <input type="hidden" name="csrf_token" value="<?= $e($csrfToken) ?>">
                            <div class="risk-grid">
                                <label>Titulo<input name="titulo" value="<?= $e($risk['titulo'] ?? '') ?>" required></label>
                                <label>Categoria<?php $select('categoria', $opt('categories'), (string) ($risk['categoria'] ?? '')); ?></label>
                                <label>Propietario<input name="propietario" value="<?= $e($risk['propietario'] ?? '') ?>" required></label>
                                <label>Estado<?php $select('estado', $opt('risk_states'), (string) ($risk['estado'] ?? '')); ?></label>
                                <label>Subcategoria<input name="subcategoria" value="<?= $e($risk['subcategoria'] ?? '') ?>"></label>
                                <label>Fuente<input name="fuente" value="<?= $e($risk['fuente'] ?? '') ?>"></label>
                            </div>
                            <div class="risk-grid three">
                                <label>Causa<textarea name="causa" required><?= $e($risk['causa'] ?? '') ?></textarea></label>
                                <label>Evento<textarea name="evento" required><?= $e($risk['evento'] ?? '') ?></textarea></label>
                                <label>Consecuencia<textarea name="consecuencia" required><?= $e($risk['consecuencia'] ?? '') ?></textarea></label>
                            </div>
                            <div class="risk-grid">
                                <label>Probabilidad inherente<input type="number" name="probabilidad_inherente" min="1" max="5" value="<?= $e($risk['probabilidad_inherente'] ?? '') ?>"></label>
                                <label>Impacto inherente<input type="number" name="impacto_inherente" min="1" max="5" value="<?= $e($risk['impacto_inherente'] ?? '') ?>"></label>
                                <label>Justificacion probabilidad<textarea name="justificacion_probabilidad"><?= $e($risk['justificacion_probabilidad'] ?? '') ?></textarea></label>
                                <label>Justificacion impacto<textarea name="justificacion_impacto"><?= $e($risk['justificacion_impacto'] ?? '') ?></textarea></label>
                                <label>Probabilidad residual<input type="number" name="probabilidad_residual" min="1" max="5" value="<?= $e($risk['probabilidad_residual'] ?? '') ?>"></label>
                                <label>Impacto residual<input type="number" name="impacto_residual" min="1" max="5" value="<?= $e($risk['impacto_residual'] ?? '') ?>"></label>
                                <label>Estrategia<?php $select('estrategia_tratamiento', $opt('strategies'), (string) ($risk['estrategia_tratamiento'] ?? '')); ?></label>
                                <label>Frecuencia revision<?php $select('frecuencia_revision', $opt('review_frequencies'), (string) ($risk['frecuencia_revision'] ?? '')); ?></label>
                                <label>Proxima revision<input type="date" name="proxima_revision" value="<?= $e($risk['proxima_revision'] ?? '') ?>"></label>
                                <label>Ultima revision<input type="date" name="ultima_revision" value="<?= $e($risk['ultima_revision'] ?? '') ?>"></label>
                                <label>Evaluador<input name="evaluador" value="<?= $e($risk['evaluador'] ?? '') ?>"></label>
                                <label>Fecha evaluacion<input type="date" name="fecha_evaluacion" value="<?= $e($risk['fecha_evaluacion'] ?? '') ?>"></label>
                                <label>Aprobador<input name="aprobador" value="<?= $e($risk['aprobador'] ?? '') ?>"></label>
                                <label>Evaluador residual<input name="evaluador_residual" value="<?= $e($risk['evaluador_residual'] ?? '') ?>"></label>
                                <label>Fecha residual<input type="date" name="fecha_evaluacion_residual" value="<?= $e($risk['fecha_evaluacion_residual'] ?? '') ?>"></label>
                                <label>Aceptador residual<input name="aceptador_residual" value="<?= $e($risk['aceptador_residual'] ?? '') ?>"></label>
                                <label>Residual aceptado<input name="residual_aceptado" value="<?= $e($risk['residual_aceptado'] ?? '') ?>"></label>
                                <label>Fecha materializacion<input type="date" name="fecha_materializacion" value="<?= $e($risk['fecha_materializacion'] ?? '') ?>"></label>
                                <label>Fecha cierre<input type="date" name="fecha_cierre" value="<?= $e($risk['fecha_cierre'] ?? '') ?>"></label>
                                <label>Motivo cambio<input name="motivo_cambio" placeholder="Obligatorio al reabrir o justificar ajustes"></label>
                            </div>
                            <div class="risk-grid three">
                                <label>Justificacion residual<textarea name="justificacion_residual"><?= $e($risk['justificacion_residual'] ?? '') ?></textarea></label>
                                <label>Descripcion materializacion<textarea name="descripcion_materializacion"><?= $e($risk['descripcion_materializacion'] ?? '') ?></textarea></label>
                                <label>Motivo cierre<textarea name="motivo_cierre"><?= $e($risk['motivo_cierre'] ?? '') ?></textarea></label>
                                <label>Evidencia cierre<textarea name="evidencia_cierre"><?= $e($risk['evidencia_cierre'] ?? '') ?></textarea></label>
                                <label>Solicita cierre<input name="solicita_cierre" value="<?= $e($risk['solicita_cierre'] ?? '') ?>"></label>
                                <label>Aprueba cierre<input name="aprueba_cierre" value="<?= $e($risk['aprueba_cierre'] ?? '') ?>"></label>
                            </div>
                            <label>Descripcion<textarea name="descripcion"><?= $e($risk['descripcion'] ?? '') ?></textarea></label>
                            <button class="secondary">Actualizar riesgo</button>
                        </form>
                    </div>
                </details>
            <?php endforeach; ?>
        </div>
    </section>

    <section class="risk-panel" data-risk-panel="matriz">
        <div class="risk-section-head"><div><h2>Matriz de riesgo</h2><p>Priorizacion por probabilidad, impacto y nivel.</p></div></div>
        <div class="risk-matrix">
            <?php foreach ($risks as $risk): ?>
                <div class="matrix-item level-<?= $e(strtolower((string) ($risk['nivel_inherente'] ?? 'sin'))) ?>">
                    <strong><?= $e($risk['codigo'] ?? '') ?></strong>
                    <span><?= $e($risk['titulo'] ?? '') ?></span>
                    <b><?= $e($risk['puntaje_inherente'] ?? 'S/E') ?></b>
                    <small><?= $e($risk['nivel_inherente'] ?? 'Sin evaluar') ?></small>
                </div>
            <?php endforeach; ?>
            <?php if ($risks === []): ?><div class="risk-empty">La matriz se activa cuando existan riesgos definitivos.</div><?php endif; ?>
        </div>
    </section>

    <section class="risk-panel" data-risk-panel="controles">
        <div class="risk-section-head"><div><h2>Controles</h2><p>Asocia controles preventivos, detectivos, correctivos o de contingencia a un riesgo.</p></div></div>
        <form class="risk-form" data-risk-form data-risk-needs-risk action="" data-risk-action-template="<?= $e($basePath) ?>/protocolo-familiar/riesgos/{id}/controles" method="post">
            <input type="hidden" name="csrf_token" value="<?= $e($csrfToken) ?>">
            <div class="risk-grid">
                <label>Riesgo<?php $select('riesgo_id', $riskChoices); ?></label>
                <label>Nombre control<input name="nombre" required></label>
                <label>Tipo<?php $select('tipo', $opt('control_types'), 'Preventivo'); ?></label>
                <label>Naturaleza<?php $select('naturaleza', $opt('control_natures'), 'Manual'); ?></label>
                <label>Responsable<input name="responsable" required></label>
                <label>Estado<?php $select('estado', $opt('control_states'), 'Diseñado'); ?></label>
                <label>Evidencia 05_Documentos<?php $select('documento_id', $docsChoice, null, 'Sin evidencia'); ?></label>
                <label>Fecha revision<input type="date" name="fecha_revision"></label>
            </div>
            <label>Descripcion<textarea name="descripcion"></textarea></label>
            <button class="primary">Agregar control</button>
        </form>
        <div class="risk-list">
            <?php foreach ($risks as $risk): foreach (($controls[$risk['id']] ?? []) as $control): ?>
                <article><strong><?= $e($riskChoiceLabel($risk)) ?></strong><span><?= $e(($control['nombre'] ?? '') . ' - ' . ($control['estado'] ?? '')) ?></span></article>
            <?php endforeach; endforeach; ?>
        </div>
    </section>

    <section class="risk-panel" data-risk-panel="acciones">
        <div class="risk-section-head"><div><h2>Tratamientos</h2><p>Plan de accion, responsables, vencimientos y evidencia.</p></div></div>
        <form class="risk-form" data-risk-form data-risk-needs-risk action="" data-risk-action-template="<?= $e($basePath) ?>/protocolo-familiar/riesgos/{id}/acciones" method="post">
            <input type="hidden" name="csrf_token" value="<?= $e($csrfToken) ?>">
            <div class="risk-grid">
                <label>Riesgo<?php $select('riesgo_id', $riskChoices); ?></label>
                <label>Responsable<input name="responsable" required></label>
                <label>Fecha inicio<input type="date" name="fecha_inicio"></label>
                <label>Fecha objetivo<input type="date" name="fecha_objetivo"></label>
                <label>Prioridad<?php $select('prioridad', $opt('priorities'), 'Media'); ?></label>
                <label>Estado<?php $select('estado', $opt('action_states'), 'Pendiente'); ?></label>
                <label>Avance %<input type="number" name="avance" min="0" max="100"></label>
                <label>Evidencia 05_Documentos<?php $select('documento_id', $docsChoice, null, 'Sin evidencia'); ?></label>
            </div>
            <label>Accion<textarea name="descripcion" required></textarea></label>
            <button class="primary">Agregar tratamiento</button>
        </form>
        <div class="risk-list">
            <?php foreach ($risks as $risk): foreach (($actions[$risk['id']] ?? []) as $action): ?>
                <article><strong><?= $e($riskChoiceLabel($risk)) ?></strong><span><?= $e(($action['descripcion'] ?? '') . ' - ' . ($action['estado'] ?? '') . ' - ' . ($action['avance'] ?? 0) . '%') ?></span></article>
            <?php endforeach; endforeach; ?>
        </div>
    </section>

    <section class="risk-panel" data-risk-panel="documentos">
        <div class="risk-section-head"><div><h2>Documentos relacionados</h2><p>Vincula documentos existentes de 05_Documentos. No se duplican archivos ni checklist.</p></div></div>
        <form class="risk-form" data-risk-form data-risk-needs-risk action="" data-risk-action-template="<?= $e($basePath) ?>/protocolo-familiar/riesgos/{id}/documentos" method="post">
            <input type="hidden" name="csrf_token" value="<?= $e($csrfToken) ?>">
            <div class="risk-grid">
                <label>Riesgo<?php $select('riesgo_id', $riskChoices); ?></label>
                <label>Documento existente<?php $select('documento_id', $docsChoice); ?></label>
                <label>Tipo relacion<?php $select('tipo_relacion', $opt('document_relations'), 'Documento de referencia'); ?></label>
            </div>
            <label>Observaciones<textarea name="observaciones"></textarea></label>
            <button class="primary">Relacionar documento</button>
        </form>
        <div class="risk-list">
            <?php foreach ($risks as $risk): foreach (($documents[$risk['id']] ?? []) as $doc): ?>
                <article><strong><?= $e($riskChoiceLabel($risk)) ?></strong><span><?= $e(($doc['documento_codigo'] ?? '') . ' - ' . ($doc['documento_tipo'] ?? '') . ' - ' . ($doc['tipo_relacion'] ?? '')) ?></span></article>
            <?php endforeach; endforeach; ?>
        </div>
    </section>

    <section class="risk-panel" data-risk-panel="historial">
        <div class="risk-section-head"><div><h2>Historial y trazabilidad</h2><p>Cambios relevantes de candidatos, riesgos, controles, tratamientos y documentos.</p></div></div>
        <div class="risk-list">
            <?php if ($history === []): ?><div class="risk-empty">Sin movimientos registrados todavia.</div><?php endif; ?>
            <?php foreach ($history as $item): ?>
                <article>
                    <strong><?= $e(($item['created_at'] ?? '') . ' - ' . ($item['entidad_tipo'] ?? '') . ' - ' . ($item['campo'] ?? '')) ?></strong>
                    <span><?= $e(($item['valor_anterior'] ?? 'Sin valor') . ' -> ' . ($item['valor_nuevo'] ?? 'Sin valor') . ' / ' . ($item['motivo'] ?? '')) ?></span>
                </article>
            <?php endforeach; ?>
        </div>
    </section>
</section>
