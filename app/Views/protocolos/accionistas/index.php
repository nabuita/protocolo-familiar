<?php
declare(strict_types=1);
$e = static fn(mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$json = static fn(mixed $v): string => htmlspecialchars(json_encode($v, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$options = static function (string $name, array $values, ?string $selected = null) use ($e): void {
    ?><select name="<?= $e($name) ?>"><option value="" disabled <?= $selected === null ? 'selected' : '' ?>>Seleccione...</option><?php foreach ($values as $value): ?><option value="<?= $e($value) ?>" <?= $selected === $value ? 'selected' : '' ?>><?= $e($value) ?></option><?php endforeach; ?></select><?php
};
$money = static function (mixed $value): string {
    if ($value === null || $value === '') {
        return '';
    }
    return '$' . number_format((float) $value, 0, ',', '.');
};
$percent = static fn(mixed $value): string => number_format((float) $value, 2, ',', '.') . '%';
$number = static function (mixed $value): string {
    if ($value === null || $value === '') {
        return '';
    }
    return number_format((float) $value, 0, ',', '.');
};
$moneyDecimal = static function (mixed $value): string {
    if ($value === null || $value === '') {
        return '';
    }
    return '$' . number_format((float) $value, 2, ',', '.');
};
$date = static function (mixed $value): string {
    if (!is_string($value) || $value === '') {
        return '';
    }
    $parts = explode('-', $value);
    return count($parts) === 3 ? $parts[2] . '/' . $parts[1] . '/' . $parts[0] : $value;
};
$shareholderDocumentsByCode = [];
foreach (($documentoRows ?? []) as $documento) {
    if (($documento['sujeto_tipo'] ?? '') !== 'Accionista/Participacion') {
        continue;
    }
    $key = (string) ($documento['sujeto_codigo'] ?? '');
    $shareholderDocumentsByCode[$key] ??= [];
    $shareholderDocumentsByCode[$key][] = $documento;
}
$docStats = static function (string $code) use ($shareholderDocumentsByCode): array {
    $docs = $shareholderDocumentsByCode[$code] ?? [];
    $pending = 0;
    $uploaded = 0;
    foreach ($docs as $documento) {
        if (in_array($documento['estado'] ?? '', ['Faltante', 'Solicitado', 'En revision', 'Vencido'], true)) {
            $pending++;
        }
        if (is_string($documento['archivo_ruta'] ?? null) && $documento['archivo_ruta'] !== '') {
            $uploaded++;
        }
    }
    return ['total' => count($docs), 'pending' => $pending, 'uploaded' => $uploaded];
};
$shareholderGroups = [];
foreach ($accionistaRows as $row) {
    $name = (string) $row['accionista'];
    if (!isset($shareholderGroups[$name])) {
        $shareholderGroups[$name] = [
            'accionista' => $name,
            'vinculo' => (string) $row['vinculo_familiar'],
            'participaciones' => [],
            'porcentaje_total' => 0.0,
            'acciones_total' => 0.0,
            'valor_estimado_total' => 0.0,
        ];
    }
    $shareholderGroups[$name]['participaciones'][] = $row;
    $shareholderGroups[$name]['porcentaje_total'] += (float) $row['porcentaje'];
    $shareholderGroups[$name]['acciones_total'] += (float) $row['numero_acciones_cuotas'];
    $shareholderGroups[$name]['valor_estimado_total'] += (float) ($row['valor_estimado_actual'] ?? 0);
}
?>
<div class="heading compact workspace-heading shareholders-heading">
    <div>
        <small>03. COMPOSICION ACCIONARIA Y ORIGEN DE LAS PARTICIPACIONES</small>
        <h1>Accionistas</h1>
        <p>Objetivo: establecer quien es propietario de cada empresa, desde cuando y como adquirio su participacion. Regla principal: una fila por cada accionista de cada empresa.</p>
    </div>
    <button type="button" class="primary" data-new-shareholder>Nuevo accionista</button>
</div>

<section class="shareholder-summary" data-shareholder-summary>
    <?php foreach ($accionistaTotals as $total): ?>
        <?php $stateClass = $total['estado'] === 'Completo' ? 'ok' : ($total['estado'] === 'Excede 100%' ? 'danger' : 'warn'); ?>
        <article class="shareholder-total <?= $e($stateClass) ?>">
            <strong><?= $e($total['empresa']) ?></strong>
            <span><?= $e($percent($total['total'])) ?></span>
            <small><?= $e($total['estado']) ?></small>
        </article>
    <?php endforeach; ?>
</section>

<div class="shareholder-modal-backdrop" data-shareholder-modal hidden>
    <div class="shareholder-modal-panel" role="dialog" aria-modal="true" aria-labelledby="shareholder-modal-title">
        <form class="grid shareholder-form" method="post" action="<?= $e($basePath) ?>/protocolo-familiar/accionistas" data-shareholder-form data-shareholder-documents="<?= $json($shareholderDocumentsByCode) ?>">
            <input type="hidden" name="csrf_token" value="<?= $e($csrfToken) ?>">
            <input type="hidden" name="id" value="">
            <div class="shareholder-modal-header">
                <div>
                    <h2 id="shareholder-modal-title" data-shareholder-modal-title>Nuevo accionista</h2>
                    <p>Registra una participacion accionaria y deja trazabilidad documental del origen, registro y valoracion.</p>
                </div>
                <button type="button" class="shareholder-icon-button" aria-label="Cerrar formulario" data-close-shareholder>&times;</button>
            </div>
            <div class="shareholder-tabs" role="tablist">
                <button type="button" class="is-active" data-shareholder-tab="participacion" aria-selected="true">Participacion</button>
                <button type="button" data-shareholder-tab="origen" aria-selected="false">Origen</button>
                <button type="button" data-shareholder-tab="patrimonial" aria-selected="false">Estado civil</button>
                <button type="button" data-shareholder-tab="registro" aria-selected="false">Registro</button>
                <button type="button" data-shareholder-tab="valoracion" aria-selected="false">Valoracion</button>
                <button type="button" data-shareholder-tab="documentos" aria-selected="false">Documentos</button>
                <button type="button" data-shareholder-tab="observaciones" aria-selected="false">Observaciones</button>
            </div>
            <section class="shareholder-tab-panel is-active" data-shareholder-tab-panel="participacion">
                <div class="cols">
                    <label>Codigo<input name="codigo" value="Automatico" disabled></label>
                    <label>Empresa<select name="empresa" required><option value="" disabled selected hidden>Seleccione...</option><?php foreach ($empresaRows as $empresa): ?><option value="<?= $e($empresa['razon_social']) ?>"><?= $e($empresa['razon_social']) ?></option><?php endforeach; ?></select></label>
                </div>
                <div class="select-grid shareholder-grid">
                    <label>Accionista<select name="accionista" required><option value="" disabled selected hidden>Seleccione...</option><?php foreach ($familiaRows as $persona): ?><option value="<?= $e($persona['nombre_completo']) ?>"><?= $e($persona['nombre_completo']) ?></option><?php endforeach; ?></select></label>
                    <label>Vinculo familiar<?php $options('vinculo_familiar', $accionistaOptions['tipo_vinculo']); ?></label>
                    <label>Tipo de participacion<?php $options('tipo_participacion', $accionistaOptions['tipo_participacion']); ?><small class="shareholder-field-help">Que clase de derecho societario o economico tiene la persona.</small></label>
                    <label>Naturaleza de la participacion<?php $options('naturaleza_participacion', $accionistaOptions['naturaleza_participacion']); ?><small class="shareholder-field-help">Indica si la participacion es propia, conyugal, familiar, empresarial o de tercero.</small></label>
                    <label>Numero de acciones/cuotas<input name="numero_acciones_cuotas" inputmode="numeric" placeholder="Ej: 6.120"></label>
                    <label>Porcentaje<input name="porcentaje" inputmode="decimal" placeholder="0,00%" required></label>
                    <label>Ano de ingreso<input name="ano_ingreso" type="number" min="1800" max="2200" placeholder="Ej: 2013"></label>
                </div>
                <div class="shareholder-academy-grid">
                    <article class="shareholder-academy-card" data-shareholder-help="tipo_participacion">
                        <span>Academia del campo</span>
                        <strong>Tipo de participacion</strong>
                        <p>Selecciona una opcion para ver su definicion, cuando usarla y un ejemplo.</p>
                    </article>
                    <article class="shareholder-academy-card" data-shareholder-help="naturaleza_participacion">
                        <span>Academia del campo</span>
                        <strong>Naturaleza de la participacion</strong>
                        <p>Ayuda a saber si el derecho pertenece solo a la persona o si puede involucrar sociedad conyugal, familia, empresa o terceros.</p>
                    </article>
                </div>
            </section>
            <section class="shareholder-tab-panel" data-shareholder-tab-panel="origen" hidden>
                <div class="select-grid shareholder-grid">
                    <label>Fecha de adquisicion <span class="shareholder-document-marker">Control documental</span><input name="fecha_adquisicion" type="date"></label>
                    <label>Forma de adquisicion <span class="shareholder-document-marker">Control documental</span><?php $options('forma_adquisicion', $accionistaOptions['forma_adquisicion']); ?></label>
                    <label>Valor pagado/aportado <span class="shareholder-document-marker">Control documental</span><input name="valor_pagado_aportado" inputmode="decimal" placeholder="$0"></label>
                    <label>Quien aporto los recursos<input name="quien_aporto_recursos" placeholder="Persona o empresa que aporto"></label>
                    <label>Medio de pago<?php $options('medio_pago', $accionistaOptions['medio_pago']); ?></label>
                    <label>Soporte de pago<?php $options('soporte_pago', $accionistaOptions['soporte_pago']); ?></label>
                    <label>Existe documento de adquisicion <span class="shareholder-document-marker">Control documental</span><?php $options('existe_documento_adquisicion', $accionistaOptions['si_no']); ?></label>
                </div>
            </section>
            <section class="shareholder-tab-panel" data-shareholder-tab-panel="patrimonial" hidden>
                <div class="select-grid shareholder-grid">
                    <label>Estado civil al adquirir <span class="shareholder-document-marker">Control documental</span><?php $options('estado_civil_adquirir', $accionistaOptions['estado_civil']); ?></label>
                    <label>Regimen patrimonial al adquirir<?php $options('regimen_patrimonial_adquirir', $familiaOptions['regimen_patrimonial'] ?? []); ?></label>
                    <label>Convivia en ese momento<?php $options('convivia_en_ese_momento', $accionistaOptions['si_no']); ?></label>
                </div>
            </section>
            <section class="shareholder-tab-panel" data-shareholder-tab-panel="registro" hidden>
                <div class="select-grid shareholder-grid">
                    <label>Registro en libro accionistas <span class="shareholder-document-marker">Control documental</span><?php $options('registro_libro_accionistas', $accionistaOptions['si_no']); ?></label>
                    <label>Fecha registro libro<input name="fecha_registro_libro" type="date"></label>
                    <label>Restriccion vigente <span class="shareholder-document-marker">Control documental</span><?php $options('restriccion_vigente', $accionistaOptions['restriccion_vigente']); ?></label>
                    <label>Tipo de restriccion<?php $options('tipo_restriccion', $accionistaOptions['tipo_restriccion']); ?><small class="shareholder-field-help">Define que limita vender, transferir, gravar o ejercer la participacion.</small></label>
                </div>
                <div class="shareholder-academy-grid">
                    <article class="shareholder-academy-card" data-shareholder-help="tipo_restriccion">
                        <span>Academia del campo</span>
                        <strong>Tipo de restriccion</strong>
                        <p>Selecciona una opcion para entender que limita y que soporte documental deberia existir.</p>
                    </article>
                </div>
            </section>
            <section class="shareholder-tab-panel" data-shareholder-tab-panel="valoracion" hidden>
                <div class="select-grid shareholder-grid">
                    <label>Valor nominal<input name="valor_nominal" inputmode="decimal" placeholder="$0"></label>
                    <label>Valor estimado actual <span class="shareholder-document-marker">Control documental</span><input name="valor_estimado_actual" inputmode="decimal" placeholder="$0"></label>
                    <label>Fecha de valoracion<input name="fecha_valoracion" type="date"></label>
                    <label>Metodo de valoracion<?php $options('metodo_valoracion', $accionistaOptions['metodo_valoracion_participacion']); ?><small class="shareholder-field-help">Indica de donde sale el valor actual de la participacion.</small></label>
                    <label>Fuente de valoracion<input name="fuente_valoracion" placeholder="Avaluo, balance, certificado o analisis"></label>
                </div>
                <div class="shareholder-academy-grid">
                    <article class="shareholder-academy-card" data-shareholder-help="metodo_valoracion">
                        <span>Academia del campo</span>
                        <strong>Metodo de valoracion</strong>
                        <p>Selecciona una opcion para saber que mide y que soporte conviene pedir.</p>
                    </article>
                </div>
            </section>
            <section class="shareholder-tab-panel" data-shareholder-tab-panel="documentos" hidden>
                <div class="shareholder-document-inline" data-shareholder-modal-documents>
                    <p class="muted">Guarda primero la participacion para activar su expediente documental.</p>
                </div>
            </section>
            <section class="shareholder-tab-panel" data-shareholder-tab-panel="observaciones" hidden>
                <label>Alertas documentales<textarea name="alerta_documental" rows="3" placeholder="Pendientes de soporte, inconsistencias o aclaraciones necesarias"></textarea></label>
                <label>Observaciones<textarea name="observaciones" rows="3"></textarea></label>
            </section>
            <div class="shareholder-modal-actions">
                <span class="muted" data-shareholder-status></span>
                <div class="inline">
                    <button type="button" data-cancel-shareholder>Limpiar</button>
                    <button class="primary" type="submit">Guardar accionista</button>
                </div>
            </div>
        </form>
    </div>
</div>

<section class="card shareholder-records">
    <div class="split">
        <div>
            <h2>Registros por accionista</h2>
            <p class="muted">Filtra por persona y abre el acordeon para ver las empresas donde participa.</p>
        </div>
        <span class="muted"><span data-shareholder-count><?= count($accionistaRows) ?></span> participaciones</span>
    </div>
    <div class="shareholder-filterbar">
        <label>Filtrar accionista<input type="search" data-shareholder-filter placeholder="Nombre del accionista"></label>
        <span><strong data-shareholder-group-count><?= count($shareholderGroups) ?></strong> accionistas</span>
    </div>
    <div class="shareholder-accordion" data-shareholder-table>
        <?php foreach ($shareholderGroups as $group): ?>
            <details class="shareholder-accordion-item" data-shareholder-group="<?= $e(strtolower($group['accionista'])) ?>">
                <summary>
                    <span class="shareholder-summary-main">
                        <strong><?= $e($group['accionista']) ?></strong>
                        <small><?= $e($group['vinculo']) ?> / <?= count($group['participaciones']) ?> empresa<?= count($group['participaciones']) === 1 ? '' : 's' ?></small>
                    </span>
                    <span class="shareholder-summary-meta">
                        <span><b><?= $e($percent($group['porcentaje_total'])) ?></b> total</span>
                        <span><?= $e($number($group['acciones_total'])) ?> acciones</span>
                        <?php if ($group['valor_estimado_total'] > 0): ?><span><?= $e($money($group['valor_estimado_total'])) ?></span><?php endif; ?>
                    </span>
                    <button type="button" class="shareholder-accordion-toggle" data-shareholder-toggle aria-label="Abrir o cerrar accionista" aria-expanded="false"></button>
                </summary>
                <div class="shareholder-detail">
                    <div class="shareholder-company-list">
                        <?php foreach ($group['participaciones'] as $row): ?>
                            <?php $stats = $docStats((string) $row['codigo']); ?>
                            <?php
                                $shares = (float) ($row['numero_acciones_cuotas'] ?? 0);
                                $estimatedValue = (float) ($row['valor_estimado_actual'] ?? 0);
                                $valuePerShare = $shares > 0 && $estimatedValue > 0
                                    ? $estimatedValue / $shares
                                    : (float) ($row['valor_nominal'] ?? 0);
                                $valuationSource = trim((string) ($row['fuente_valoracion'] ?? '') . (($row['fecha_valoracion'] ?? '') ? ' / ' . $date($row['fecha_valoracion']) : ''), ' /');
                            ?>
                            <article class="shareholder-company" data-shareholder-row="<?= $e($row['id']) ?>" data-row="<?= $json($row) ?>">
                                <div class="shareholder-company-head">
                                    <div>
                                        <strong><?= $e($row['empresa']) ?></strong>
                                        <span><?= $e($row['codigo']) ?> / <?= $e($row['forma_adquisicion']) ?> / ingreso <?= $e($row['ano_ingreso']) ?></span>
                                    </div>
                                    <div class="shareholder-company-kpis">
                                        <span><b><?= $e($percent($row['porcentaje'])) ?></b> participacion</span>
                                        <span><?= $e($number($row['numero_acciones_cuotas'])) ?> acciones</span>
                                        <?php if (($row['valor_estimado_actual'] ?? '') !== ''): ?><span><?= $e($money($row['valor_estimado_actual'])) ?></span><?php endif; ?>
                                        <span class="<?= $stats['pending'] > 0 ? 'is-danger' : 'is-ok' ?>"><?= $e($stats['pending']) ?> docs pendientes</span>
                                    </div>
                                </div>
                                <div class="shareholder-economic-strip" aria-label="Valor economico de la participacion">
                                    <div>
                                        <span>Valor nominal actual</span>
                                        <strong><?= $e($moneyDecimal(($row['valor_nominal'] ?? '') !== '' ? $row['valor_nominal'] : $valuePerShare)) ?: 'Pendiente' ?></strong>
                                        <small>Por accion/cuota</small>
                                    </div>
                                    <div>
                                        <span>Acciones / cuotas</span>
                                        <strong><?= $e($number($row['numero_acciones_cuotas'] ?? '')) ?: 'Pendiente' ?></strong>
                                        <small><?= $e($percent($row['porcentaje'])) ?> de participacion</small>
                                    </div>
                                    <div>
                                        <span>Valor estimado actual</span>
                                        <strong><?= $e($money($row['valor_estimado_actual'] ?? '')) ?: 'Pendiente' ?></strong>
                                        <small><?= $e(($row['metodo_valoracion'] ?? '') ?: 'Metodo pendiente') ?></small>
                                    </div>
                                    <div>
                                        <span>Valor pagado / aportado</span>
                                        <strong><?= $e($money($row['valor_pagado_aportado'] ?? '')) ?: 'Pendiente' ?></strong>
                                        <small><?= $e($valuationSource ?: 'Fuente pendiente') ?></small>
                                    </div>
                                </div>
                                <dl class="shareholder-detail-grid">
                                    <div><dt>Tipo / naturaleza</dt><dd><?= $e(trim((string) ($row['tipo_participacion'] ?? '') . ' / ' . (string) ($row['naturaleza_participacion'] ?? ''), ' /')) ?></dd></div>
                                    <div><dt>Valor pagado</dt><dd><?= $e($money($row['valor_pagado_aportado'])) ?></dd></div>
                                    <div><dt>Fecha adquisicion</dt><dd><?= $e($date($row['fecha_adquisicion'] ?? '')) ?></dd></div>
                                    <div><dt>Estado civil</dt><dd><?= $e($row['estado_civil_adquirir']) ?></dd></div>
                                    <div><dt>Registro en libro</dt><dd><?= $e($row['registro_libro_accionistas']) ?><?= ($row['fecha_registro_libro'] ?? '') ? ' / ' . $e($date($row['fecha_registro_libro'])) : '' ?></dd></div>
                                    <div><dt>Restriccion</dt><dd><?= $e($row['restriccion_vigente']) ?><?= ($row['tipo_restriccion'] ?? '') ? ' / ' . $e($row['tipo_restriccion']) : '' ?></dd></div>
                                    <div><dt>Documentos</dt><dd><?= $e($stats['total']) ?> requeridos / <?= $e($stats['uploaded']) ?> subidos</dd></div>
                                    <div><dt>Valoracion</dt><dd><?= $e($row['metodo_valoracion'] ?? '') ?><?= ($row['fecha_valoracion'] ?? '') ? ' / ' . $e($date($row['fecha_valoracion'])) : '' ?></dd></div>
                                    <div class="wide"><dt>Observaciones</dt><dd><?= $e($row['observaciones']) ?></dd></div>
                                </dl>
                                <div class="shareholder-row-actions">
                                    <button type="button" data-edit-shareholder>Editar expediente</button>
                                    <button type="button" data-delete-shareholder>Desactivar</button>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                </div>
            </details>
        <?php endforeach; ?>
    </div>
</section>
