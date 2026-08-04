<?php
declare(strict_types=1);
$e = static fn(mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$json = static fn(mixed $v): string => htmlspecialchars(json_encode($v, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$options = static function (string $name, array $values, ?string $selected = null) use ($e): void {
    ?><select name="<?= $e($name) ?>"><option value="" disabled <?= $selected === null ? 'selected' : '' ?>>Seleccione...</option><?php foreach ($values as $value): ?><option value="<?= $e($value) ?>" <?= $selected === $value ? 'selected' : '' ?>><?= $e($value) ?></option><?php endforeach; ?></select><?php
};
$familyDocumentsByCode = [];
foreach (($documentoRows ?? []) as $documento) {
    if (($documento['sujeto_tipo'] ?? '') !== 'Persona/Familia') {
        continue;
    }
    $key = (string) ($documento['sujeto_codigo'] ?? '');
    $familyDocumentsByCode[$key] ??= [];
    $familyDocumentsByCode[$key][] = $documento;
}
$familyDocStats = static function (array $row) use ($familyDocumentsByCode): array {
    $docs = $familyDocumentsByCode[(string) ($row['codigo'] ?? '')] ?? [];
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
<div class="heading compact workspace-heading">
    <div>
        <small>01. MAPA DE LA FAMILIA EMPRESARIA</small>
        <h1>Familia</h1>
        <p>Objetivo: identificar a las personas que integran la familia empresaria y su relacion con las empresas. Unidad de registro: una fila por cada persona.</p>
    </div>
    <button type="button" class="primary" data-new-family>Nueva persona</button>
</div>

<div class="modal-backdrop" data-family-modal hidden>
    <div class="modal-panel" role="dialog" aria-modal="true" aria-labelledby="family-modal-title">
        <form class="grid family-form" method="post" action="<?= $e($basePath) ?>/protocolo-familiar/familia" data-family-form data-family-documents="<?= $json($familyDocumentsByCode) ?>">
            <input type="hidden" name="csrf_token" value="<?= $e($csrfToken) ?>">
            <input type="hidden" name="id" value="">
            <div class="modal-header">
                <div>
                    <h2 id="family-modal-title" data-family-modal-title>Nueva persona</h2>
                    <p>Completa una fila del mapa familiar.</p>
                </div>
                <button type="button" class="icon-button" aria-label="Cerrar formulario" data-close-family>&times;</button>
            </div>
            <div class="family-tabs" role="tablist">
                <button type="button" class="is-active" data-family-tab="basicos" aria-selected="true">Datos basicos</button>
                <button type="button" data-family-tab="civil" aria-selected="false">Estado civil</button>
                <button type="button" data-family-tab="empresas" aria-selected="false">Empresas</button>
                <button type="button" data-family-tab="gobierno" aria-selected="false">Gobierno</button>
                <button type="button" data-family-tab="documentos" aria-selected="false">Documentos</button>
                <button type="button" data-family-tab="observaciones" aria-selected="false">Observaciones</button>
            </div>
            <section class="family-tab-panel is-active" data-family-tab-panel="basicos">
                <div class="cols">
                    <label>Codigo<input name="codigo" value="Automatico" disabled></label>
                    <label>Nombre completo <span class="family-document-marker">Control documental</span><input name="nombre_completo" required></label>
                </div>
                <div class="select-grid">
                    <label>Tipo de documento <span class="family-document-marker">Control documental</span><?php $options('tipo_documento', $familiaOptions['tipo_documento']); ?></label>
                    <label>Numero de documento <span class="family-document-marker">Control documental</span><input name="numero_documento"></label>
                    <label>Ciudad expedicion<input name="ciudad_expedicion"></label>
                    <label>Nacionalidad<input name="nacionalidad"></label>
                    <label>Fecha de nacimiento <span class="family-document-marker">Control documental</span><input name="fecha_nacimiento" type="date"></label>
                    <label>Edad<input name="edad" type="number" min="0" max="130"></label>
                    <label>Tipo de vinculo<?php $options('tipo_vinculo', $familiaOptions['tipo_vinculo']); ?></label>
                    <label>Generacion<?php $options('generacion', $familiaOptions['generacion']); ?></label>
                    <label>Telefono<input name="telefono"></label>
                    <label>Correo<input name="correo" type="email"></label>
                    <label class="wide">Direccion residencia<input name="direccion_residencia"></label>
                </div>
            </section>
            <section class="family-tab-panel" data-family-tab-panel="civil" hidden>
                <div class="select-grid">
                    <label>Estado civil <span class="family-document-marker">Control documental</span><?php $options('estado_civil', $familiaOptions['estado_civil']); ?></label>
                    <label>Ano matrimonio/convivencia<input name="ano_matrimonio_convivencia" type="number" min="1800" max="2200"></label>
                    <label>Fecha matrimonio/convivencia <span class="family-document-marker">Control documental</span><input name="fecha_matrimonio_convivencia" type="date"></label>
                    <label>Regimen patrimonial <span class="family-document-marker">Control documental</span><?php $options('regimen_patrimonial', $familiaOptions['regimen_patrimonial']); ?></label>
                    <label>Conyuge o companero(a)<input name="conyuge_companero"></label>
                    <label>Tiene capitulaciones <span class="family-document-marker">Control documental</span><?php $options('tiene_capitulaciones', $familiaOptions['si_no']); ?></label>
                    <label>Sociedad conyugal/patrimonial liquidada <span class="family-document-marker">Control documental</span><?php $options('sociedad_conyugal_liquidada', $familiaOptions['si_no']); ?></label>
                    <label>Numero de hijos<input name="numero_hijos" type="number" min="0" max="50"></label>
                    <label class="wide">Hijos registrados en familia<textarea name="hijos_registrados" rows="2" placeholder="Nombres o codigos de hijos vinculados al mapa familiar"></textarea></label>
                </div>
            </section>
            <section class="family-tab-panel" data-family-tab-panel="empresas" hidden>
                <div class="select-grid">
                    <label>Trabaja en empresa familiar<?php $options('trabaja_empresa_familiar', $familiaOptions['si_no']); ?></label>
                    <label>Empresa donde trabaja<input name="empresa_donde_trabaja" list="family-company-suggestions" placeholder="Escribe la empresa, sea familiar o externa"><datalist id="family-company-suggestions"><?php foreach ($empresaRows as $empresa): ?><option value="<?= $e($empresa['razon_social']) ?>"></option><?php endforeach; ?></datalist></label>
                    <label>Cargo<input name="cargo"></label>
                    <label>Tipo vinculacion laboral <span class="family-document-marker">Control documental</span><?php $options('tipo_vinculacion_laboral', $familiaOptions['tipo_vinculacion_laboral']); ?></label>
                    <label>Es accionista<?php $options('es_accionista', $familiaOptions['si_no']); ?></label>
                </div>
            </section>
            <section class="family-tab-panel" data-family-tab-panel="gobierno" hidden>
                <div class="select-grid">
                    <label>Participa en decisiones<?php $options('participa_decisiones', $familiaOptions['si_no']); ?></label>
                    <label>Rol en gobierno familiar <span class="family-document-marker">Control documental</span><?php $options('rol_gobierno_familiar', $familiaOptions['rol_gobierno_familiar']); ?></label>
                    <label>Tiene poderes vigentes <span class="family-document-marker">Control documental</span><?php $options('tiene_poderes', $familiaOptions['si_no']); ?></label>
                    <label>Tiene testamento declarado <span class="family-document-marker">Control documental</span><?php $options('tiene_testamento', $familiaOptions['si_no']); ?></label>
                </div>
            </section>
            <section class="family-tab-panel" data-family-tab-panel="documentos" hidden>
                <div class="family-document-inline" data-family-modal-documents>
                    <p class="muted">Guarda primero la persona para activar su checklist documental.</p>
                </div>
            </section>
            <section class="family-tab-panel" data-family-tab-panel="observaciones" hidden>
                <label>Alertas u observaciones del analista<textarea name="alertas_observaciones" rows="3" placeholder="Inconsistencias, datos pendientes, dudas juridicas o familiares"></textarea></label>
                <label>Observaciones generales<textarea name="observaciones" rows="3"></textarea></label>
            </section>
            <div class="modal-actions">
                <span class="muted" data-family-status></span>
                <div class="inline">
                    <button type="button" data-cancel-family>Limpiar</button>
                    <button class="primary" type="submit">Guardar persona</button>
                </div>
            </div>
        </form>
    </div>
</div>

<section class="card family-records">
    <div class="split">
        <h2>Registros</h2>
        <span class="muted"><span data-family-count><?= count($familiaRows) ?></span> personas</span>
    </div>
    <div class="table-wrap family-list-wrap">
        <table class="data-table family-table" data-family-table>
            <thead>
                <tr>
                    <th>Persona</th>
                    <th>Identificacion</th>
                    <th>Estado civil</th>
                    <th>Empresa y gobierno</th>
                    <th>Documentos</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($familiaRows as $row): ?>
                    <?php $docStats = $familyDocStats($row); ?>
                    <tr data-family-row="<?= $e($row['id']) ?>" data-row="<?= $json($row) ?>">
                        <td data-label="Persona">
                            <div class="family-person-cell">
                                <code><?= $e($row['codigo']) ?></code>
                                <strong><?= $e($row['nombre_completo']) ?></strong>
                                <span><?= $e($row['tipo_vinculo']) ?><?= $row['generacion'] ? ' / ' . $e($row['generacion']) : '' ?></span>
                            </div>
                        </td>
                        <td data-label="Identificacion">
                            <div class="family-summary-lines">
                                <span><?= $e($row['tipo_documento'] ?? 'Documento por definir') ?><?= $row['numero_documento'] ? ': ' . $e($row['numero_documento']) : '' ?></span>
                                <span><?= $row['fecha_nacimiento'] ? 'Nacimiento: ' . $e($row['fecha_nacimiento']) : 'Nacimiento pendiente' ?><?= $row['edad'] !== null && $row['edad'] !== '' ? ' / ' . $e($row['edad']) . ' anos' : '' ?></span>
                                <span><?= $e($row['telefono'] ?? '') ?><?= ($row['telefono'] ?? '') && ($row['correo'] ?? '') ? ' / ' : '' ?><?= $e($row['correo'] ?? '') ?></span>
                            </div>
                        </td>
                        <td data-label="Estado civil">
                            <div class="family-summary-lines">
                                <span><?= $e($row['estado_civil']) ?></span>
                                <span><?= $e($row['regimen_patrimonial'] ?? 'Regimen por definir') ?></span>
                                <span><?= $row['conyuge_companero'] ? 'Conyuge/companero: ' . $e($row['conyuge_companero']) : 'Sin conyuge registrado' ?></span>
                            </div>
                        </td>
                        <td data-label="Empresa y gobierno">
                            <div class="family-summary-lines">
                                <span><?= ($row['trabaja_empresa_familiar'] ?? '') === 'Si' ? 'Trabaja en empresa familiar' : 'No trabaja en empresa familiar' ?></span>
                                <span><?= $e($row['empresa_donde_trabaja'] ?? '') ?><?= ($row['cargo'] ?? '') ? ' / ' . $e($row['cargo']) : '' ?></span>
                                <span><?= ($row['es_accionista'] ?? '') === 'Si' ? 'Accionista' : 'No accionista' ?><?= ($row['participa_decisiones'] ?? '') === 'Si' ? ' / Participa en decisiones' : '' ?></span>
                            </div>
                        </td>
                        <td data-label="Documentos">
                            <div class="family-doc-pillset">
                                <span><?= $e($docStats['total']) ?> docs</span>
                                <span class="<?= $docStats['pending'] > 0 ? 'is-danger' : 'is-ok' ?>"><?= $e($docStats['pending']) ?> pendientes</span>
                                <span><?= $e($docStats['uploaded']) ?> subidos</span>
                            </div>
                        </td>
                        <td data-label="Acciones" class="row-actions"><button type="button" data-edit-family>Editar</button><button type="button" data-delete-family>Desactivar</button></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<?php
$familyDocumentGroups = [];
foreach ($familiaRows as $persona) {
    $key = (string) ($persona['codigo'] ?? '');
    $familyDocumentGroups[$key] = [
        'persona' => $persona,
        'rows' => [],
        'total' => 0,
        'pendientes' => 0,
        'actualizados' => 0,
    ];
}
foreach (($documentoRows ?? []) as $documento) {
    if (($documento['sujeto_tipo'] ?? '') !== 'Persona/Familia') {
        continue;
    }
    $key = (string) ($documento['sujeto_codigo'] ?? '');
    if (!isset($familyDocumentGroups[$key])) {
        continue;
    }
    $familyDocumentGroups[$key]['rows'][] = $documento;
    $familyDocumentGroups[$key]['total']++;
    if (in_array($documento['estado'] ?? '', ['Faltante', 'Solicitado', 'En revision', 'Vencido'], true)) {
        $familyDocumentGroups[$key]['pendientes']++;
    }
    if (is_string($documento['archivo_ruta'] ?? null) && $documento['archivo_ruta'] !== '') {
        $familyDocumentGroups[$key]['actualizados']++;
    }
}
?>

<section class="card family-document-control">
    <div class="split">
        <div>
            <h2>Control documental por persona</h2>
            <p class="muted">Sube o actualiza los soportes requeridos sin salir de Familia. El archivo queda guardado en 05_Documentos.</p>
        </div>
        <span class="muted">Repositorio unico documental</span>
    </div>
    <div class="family-document-groups" data-family-document-groups>
        <?php foreach ($familyDocumentGroups as $group): ?>
            <?php if (($group['rows'] ?? []) === []): continue; endif; ?>
            <details class="family-document-group" data-family-doc-group="<?= $e($group['persona']['codigo'] ?? '') ?>">
                <summary>
                    <span>
                        <strong><?= $e($group['persona']['nombre_completo'] ?? '') ?></strong>
                        <small><?= $e($group['persona']['codigo'] ?? '') ?> · <?= $e($group['persona']['tipo_vinculo'] ?? '') ?></small>
                    </span>
                    <span class="family-document-stats">
                        <span><strong data-family-doc-total><?= $e($group['total']) ?></strong> docs</span>
                        <span class="<?= (int) $group['pendientes'] > 0 ? 'is-danger' : 'is-ok' ?>"><strong data-family-doc-pending><?= $e($group['pendientes']) ?></strong> pendientes</span>
                        <span><strong data-family-doc-updated><?= $e($group['actualizados']) ?></strong> subidos</span>
                    </span>
                </summary>
                <div class="family-document-list">
                    <?php foreach ($group['rows'] as $documento): ?>
                        <article class="family-document-item" data-family-doc-row="<?= $e($documento['id']) ?>" data-row="<?= $json($documento) ?>" data-state="<?= $e($documento['estado'] ?? '') ?>">
                            <div>
                                <code><?= $e($documento['codigo'] ?? '') ?></code>
                                <strong><?= $e($documento['documento_tipo'] ?? '') ?></strong>
                                <span><?= $e($documento['categoria'] ?? '') ?></span>
                            </div>
                            <div class="family-document-meta">
                                <span class="document-state state-<?= $e(strtolower(str_replace(' ', '-', (string) ($documento['estado'] ?? '')))) ?>"><?= $e($documento['estado'] ?? '') ?></span>
                                <?php if (is_string($documento['archivo_ruta'] ?? null) && $documento['archivo_ruta'] !== ''): ?>
                                    <a href="<?= $e($basePath . $documento['archivo_ruta']) ?>" target="_blank" rel="noopener">Ver archivo</a>
                                <?php else: ?>
                                    <span class="document-missing">Sin archivo</span>
                                <?php endif; ?>
                                <button type="button" data-family-open-document><?= is_string($documento['archivo_ruta'] ?? null) && $documento['archivo_ruta'] !== '' ? 'Actualizar' : 'Subir' ?></button>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            </details>
        <?php endforeach; ?>
    </div>
</section>
