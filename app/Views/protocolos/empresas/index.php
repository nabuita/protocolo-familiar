<?php
declare(strict_types=1);
$e = static fn(mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$json = static fn(mixed $v): string => htmlspecialchars(json_encode($v, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$options = static function (string $name, array $values, ?string $selected = null) use ($e): void {
    ?><select name="<?= $e($name) ?>"><option value="" disabled <?= $selected === null ? 'selected' : '' ?>>Seleccione...</option><?php foreach ($values as $value): ?><option value="<?= $e($value) ?>" <?= $selected === $value ? 'selected' : '' ?>><?= $e($value) ?></option><?php endforeach; ?></select><?php
};
$date = static function (mixed $value): string {
    if (!is_string($value) || $value === '') {
        return '';
    }
    $parts = explode('-', $value);
    return count($parts) === 3 ? $parts[2] . '/' . $parts[1] . '/' . $parts[0] : $value;
};
$companyDocumentsByCode = [];
foreach (($documentoRows ?? []) as $documento) {
    if (($documento['sujeto_tipo'] ?? '') !== 'Empresa') {
        continue;
    }
    $key = (string) ($documento['sujeto_codigo'] ?? '');
    $companyDocumentsByCode[$key] ??= [];
    $companyDocumentsByCode[$key][] = $documento;
}
$companyDocStats = static function (array $row) use ($companyDocumentsByCode): array {
    $docs = $companyDocumentsByCode[(string) ($row['codigo'] ?? '')] ?? [];
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
?>
<div class="heading compact workspace-heading companies-heading">
    <div>
        <small>02. SOCIEDADES Y NEGOCIOS FAMILIARES</small>
        <h1>Empresas</h1>
        <p>Objetivo: conocer todas las sociedades y negocios familiares, su funcion, administracion y estado documental. Unidad de registro: una fila por cada empresa o negocio.</p>
    </div>
    <button type="button" class="primary" data-new-company>Nueva empresa</button>
</div>

<div class="company-modal-backdrop" data-company-modal hidden>
    <div class="company-modal-panel" role="dialog" aria-modal="true" aria-labelledby="company-modal-title">
        <form class="grid company-form" method="post" action="<?= $e($basePath) ?>/protocolo-familiar/empresas" data-company-form data-company-documents="<?= $json($companyDocumentsByCode) ?>">
            <input type="hidden" name="csrf_token" value="<?= $e($csrfToken) ?>">
            <input type="hidden" name="id" value="">
            <div class="company-modal-header">
                <div>
                    <h2 id="company-modal-title" data-company-modal-title>Nueva empresa</h2>
                    <p>Registra una sociedad o negocio familiar.</p>
                </div>
                <button type="button" class="company-icon-button" aria-label="Cerrar formulario" data-close-company>&times;</button>
            </div>
            <div class="company-tabs" role="tablist">
                <button type="button" class="is-active" data-company-tab="identificacion" aria-selected="true">Identificacion</button>
                <button type="button" data-company-tab="gobierno" aria-selected="false">Gobierno</button>
                <button type="button" data-company-tab="tributario" aria-selected="false">Tributario</button>
                <button type="button" data-company-tab="documentos" aria-selected="false">Documentos</button>
                <button type="button" data-company-tab="observaciones" aria-selected="false">Observaciones</button>
            </div>
            <section class="company-tab-panel is-active" data-company-tab-panel="identificacion">
                <div class="cols">
                    <label>Codigo<input name="codigo" value="Automatico" disabled></label>
                    <label>Razon social <span class="company-document-marker">Control documental</span><input name="razon_social" required></label>
                </div>
                <div class="select-grid company-grid">
                    <label>Nombre comercial<input name="nombre_comercial"></label>
                    <label>NIT <span class="company-document-marker">Control documental</span><input name="nit"></label>
                    <label>Matricula mercantil <span class="company-document-marker">Control documental</span><input name="matricula_mercantil"></label>
                    <label>Tipo de sociedad<?php $options('tipo_sociedad', $empresaOptions['tipo_sociedad']); ?></label>
                    <label>Ano creacion <span class="company-document-marker">Control documental</span><input name="ano_creacion" type="date"></label>
                    <label>Empresa operativa/patrimonial<?php $options('empresa_operativa_patrimonial', $empresaOptions['empresa_operativa_patrimonial']); ?></label>
                    <label>Domicilio principal<input name="domicilio_principal"></label>
                    <label>Ciudad domicilio<input name="ciudad_domicilio"></label>
                    <label>Telefono<input name="telefono"></label>
                    <label>Correo contacto<input name="correo_contacto" type="email"></label>
                </div>
                <label>Actividad principal<textarea name="actividad_principal" rows="3"></textarea></label>
            </section>
            <section class="company-tab-panel" data-company-tab-panel="gobierno" hidden>
                <div class="select-grid company-grid">
                    <label>Representante legal <span class="company-document-marker">Control documental</span><input name="representante_legal"></label>
                    <label>Tiene junta directiva<?php $options('tiene_junta_directiva', $empresaOptions['si_no']); ?></label>
                    <label>Tiene revisor fiscal<?php $options('tiene_revisor_fiscal', $empresaOptions['si_no']); ?></label>
                    <label>Tiene inmuebles propios<?php $options('tiene_inmuebles_propios', $empresaOptions['si_no']); ?></label>
                    <label>Tiene empleados<?php $options('tiene_empleados', $empresaOptions['si_no']); ?></label>
                    <label>Estatutos actualizados <span class="company-document-marker">Control documental</span><?php $options('estatutos_actualizados', $empresaOptions['si_no']); ?></label>
                    <label>Acuerdo de accionistas <span class="company-document-marker">Control documental</span><?php $options('acuerdo_accionistas', $empresaOptions['si_no']); ?></label>
                    <label>Libro de accionistas actualizado <span class="company-document-marker">Control documental</span><?php $options('libro_accionistas_actualizado', $empresaOptions['si_no']); ?></label>
                    <label>Nivel de riesgo<?php $options('nivel_riesgo', $empresaOptions['nivel_riesgo']); ?></label>
                </div>
            </section>
            <section class="company-tab-panel" data-company-tab-panel="tributario" hidden>
                <div class="select-grid company-grid">
                    <label>Contador responsable <span class="company-document-marker">Control documental</span><input name="contador_responsable"></label>
                    <label>Correo contador<input name="correo_contador" type="email"></label>
                    <label>Periodicidad IVA<?php $options('periodicidad_iva', $empresaOptions['periodicidad_tributaria']); ?></label>
                    <label>Periodicidad retencion<?php $options('periodicidad_retencion', $empresaOptions['periodicidad_tributaria']); ?></label>
                    <label>Periodicidad ICA<?php $options('periodicidad_ica', $empresaOptions['periodicidad_tributaria']); ?></label>
                    <label>Estados financieros<?php $options('periodicidad_estados_financieros', $empresaOptions['periodicidad_contable']); ?></label>
                    <label>Cierre fiscal<input name="cierre_fiscal" placeholder="Ej: 31 de diciembre"></label>
                    <label>Estado RUT <span class="company-document-marker">Control documental</span><?php $options('estado_rut', $empresaOptions['estado_documental_empresa']); ?></label>
                    <label>Estado Camara de Comercio <span class="company-document-marker">Control documental</span><?php $options('estado_camara_comercio', $empresaOptions['estado_documental_empresa']); ?></label>
                    <label>Obligaciones tributarias <span class="company-document-marker">Control documental</span><?php $options('estado_obligaciones_tributarias', $empresaOptions['estado_documental_empresa']); ?></label>
                </div>
            </section>
            <section class="company-tab-panel" data-company-tab-panel="documentos" hidden>
                <div class="company-external-folder-form">
                    <label>Nombre carpeta externa<input name="carpeta_documental_nombre" placeholder="Ej: Carpeta contable 2026 - SK&C"></label>
                    <label>Link carpeta externa<input name="carpeta_documental_url" type="url" placeholder="https://drive.google.com/..."></label>
                    <label class="wide">Observaciones carpeta externa<textarea name="carpeta_documental_observaciones" rows="2" placeholder="Responsable, estructura interna o restricciones de acceso"></textarea></label>
                </div>
                <div class="company-document-inline" data-company-modal-documents>
                    <p class="muted">Guarda primero la empresa para activar su expediente documental.</p>
                </div>
            </section>
            <section class="company-tab-panel" data-company-tab-panel="observaciones" hidden>
                <label>Deudas o creditos importantes<textarea name="deudas_creditos_importantes" rows="2"></textarea></label>
                <label>Observaciones<textarea name="observaciones" rows="3"></textarea></label>
            </section>
            <div class="company-modal-actions">
                <span class="muted" data-company-status></span>
                <div class="inline">
                    <button type="button" data-cancel-company>Limpiar</button>
                    <button class="primary" type="submit">Guardar empresa</button>
                </div>
            </div>
        </form>
    </div>
</div>

<section class="card company-records">
    <div class="split">
        <h2>Registros</h2>
        <span class="muted"><span data-company-count><?= count($empresaRows) ?></span> empresas</span>
    </div>
    <div class="company-table-wrap">
        <table class="company-data-table company-summary-table" data-company-table>
            <thead>
                <tr>
                    <th>Empresa</th>
                    <th>Identificacion</th>
                    <th>Gobierno</th>
                    <th>Tributario y contable</th>
                    <th>Documentos</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($empresaRows as $row): ?>
                    <?php $docStats = $companyDocStats($row); ?>
                    <tr data-company-row="<?= $e($row['id']) ?>" data-row="<?= $json($row) ?>">
                        <td data-label="Empresa">
                            <div class="company-main-cell">
                                <code><?= $e($row['codigo']) ?></code>
                                <strong><?= $e($row['razon_social']) ?></strong>
                                <span><?= $e($row['nombre_comercial']) ?><?= ($row['empresa_operativa_patrimonial'] ?? '') ? ' / ' . $e($row['empresa_operativa_patrimonial']) : '' ?></span>
                            </div>
                        </td>
                        <td data-label="Identificacion">
                            <div class="company-summary-lines">
                                <span><?= $e($row['tipo_sociedad']) ?><?= ($row['nit'] ?? '') ? ' / NIT ' . $e($row['nit']) : '' ?></span>
                                <span><?= ($row['matricula_mercantil'] ?? '') ? 'Matricula: ' . $e($row['matricula_mercantil']) : 'Matricula pendiente' ?></span>
                                <span><?= $e($row['ciudad_domicilio'] ?? '') ?><?= ($row['ano_creacion'] ?? '') ? ' / ' . $e($date($row['ano_creacion'])) : '' ?></span>
                            </div>
                        </td>
                        <td data-label="Gobierno">
                            <div class="company-summary-lines">
                                <span><?= $row['representante_legal'] ? 'Rep. legal: ' . $e($row['representante_legal']) : 'Representante pendiente' ?></span>
                                <span>Junta: <?= $e($row['tiene_junta_directiva']) ?> / Revisor: <?= $e($row['tiene_revisor_fiscal']) ?></span>
                                <span>Estatutos: <?= $e($row['estatutos_actualizados']) ?> / Libro: <?= $e($row['libro_accionistas_actualizado']) ?></span>
                            </div>
                        </td>
                        <td data-label="Tributario y contable">
                            <div class="company-summary-lines">
                                <span><?= $row['contador_responsable'] ? 'Contador: ' . $e($row['contador_responsable']) : 'Contador pendiente' ?></span>
                                <span>IVA: <?= $e($row['periodicidad_iva'] ?? 'Por definir') ?> / Retencion: <?= $e($row['periodicidad_retencion'] ?? 'Por definir') ?> / ICA: <?= $e($row['periodicidad_ica'] ?? 'Por definir') ?></span>
                                <span>EEFF: <?= $e($row['periodicidad_estados_financieros'] ?? 'Por definir') ?> / RUT: <?= $e($row['estado_rut'] ?? 'Por definir') ?></span>
                                <span><?= ($row['carpeta_documental_url'] ?? '') ? 'Carpeta externa vinculada' : 'Sin carpeta externa' ?></span>
                            </div>
                        </td>
                        <td data-label="Documentos">
                            <div class="company-doc-pillset">
                                <span><?= $e($docStats['total']) ?> docs</span>
                                <span class="<?= $docStats['pending'] > 0 ? 'is-danger' : 'is-ok' ?>"><?= $e($docStats['pending']) ?> pendientes</span>
                                <span><?= $e($docStats['uploaded']) ?> subidos</span>
                                <span class="risk-badge risk-<?= $e(strtolower((string) $row['nivel_riesgo'])) ?>"><?= $e($row['nivel_riesgo']) ?></span>
                            </div>
                        </td>
                        <td data-label="Acciones" class="company-row-actions"><button type="button" data-edit-company>Editar</button><button type="button" data-delete-company>Desactivar</button></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
