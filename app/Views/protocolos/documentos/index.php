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
    $value = substr($value, 0, 10);
    $parts = explode('-', $value);
    return count($parts) === 3 ? $parts[2] . '/' . $parts[1] . '/' . $parts[0] : $value;
};
$subjects = [
    ['tipo' => 'Protocolo general', 'codigo' => 'GEN', 'nombre' => 'Protocolo familiar'],
];
foreach ($empresaRows as $row) {
    $subjects[] = ['tipo' => 'Empresa', 'codigo' => $row['codigo'], 'nombre' => $row['razon_social']];
}
$companyExternalFolders = array_values(array_filter($empresaRows, static fn(array $row): bool => is_string($row['carpeta_documental_url'] ?? null) && $row['carpeta_documental_url'] !== ''));
foreach ($familiaRows as $row) {
    $subjects[] = ['tipo' => 'Persona/Familia', 'codigo' => $row['codigo'], 'nombre' => $row['nombre_completo']];
}
foreach ($accionistaRows as $row) {
    $subjects[] = ['tipo' => 'Accionista/Participacion', 'codigo' => $row['codigo'], 'nombre' => trim((string) $row['accionista'] . ' - ' . (string) $row['empresa'])];
}
foreach ($patrimonioRows as $row) {
    $subjects[] = ['tipo' => 'Activo/Patrimonio', 'codigo' => $row['codigo'], 'nombre' => trim((string) $row['nombre_descripcion'] . ' - ' . (string) $row['identificador'])];
}
$documentGroups = [];
foreach ($documentoRows as $row) {
    $key = (string) $row['sujeto_tipo'] . '|' . (string) $row['sujeto_codigo'] . '|' . (string) $row['sujeto_nombre'];
    if (!isset($documentGroups[$key])) {
        $documentGroups[$key] = [
            'key' => $key,
            'tipo' => $row['sujeto_tipo'],
            'codigo' => $row['sujeto_codigo'],
            'nombre' => $row['sujeto_nombre'],
            'rows' => [],
            'total' => 0,
            'faltantes' => 0,
            'recibidos' => 0,
            'vencidos' => 0,
            'actualizados' => 0,
        ];
    }
    $documentGroups[$key]['rows'][] = $row;
    $documentGroups[$key]['total']++;
    if (in_array($row['estado'], ['Faltante', 'Solicitado', 'En revision', 'Vencido'], true)) {
        $documentGroups[$key]['faltantes']++;
    } elseif ($row['estado'] === 'Recibido') {
        $documentGroups[$key]['recibidos']++;
    } elseif ($row['estado'] === 'Vencido') {
        $documentGroups[$key]['vencidos']++;
    }
    if (is_string($row['archivo_ruta']) && $row['archivo_ruta'] !== '') {
        $documentGroups[$key]['actualizados']++;
    }
}
$moduleForSubject = static function (string $type): array {
    return match ($type) {
        'Protocolo general' => ['key' => 'general', 'label' => '00_Protocolo general'],
        'Persona/Familia' => ['key' => 'familia', 'label' => '01_Familia'],
        'Empresa' => ['key' => 'empresas', 'label' => '02_Empresas'],
        'Accionista/Participacion' => ['key' => 'accionistas', 'label' => '03_Accionistas'],
        'Activo/Patrimonio' => ['key' => 'patrimonio', 'label' => '04_Patrimonio'],
        default => ['key' => 'otros', 'label' => 'Otros documentos'],
    };
};
$documentModules = [];
foreach ($documentGroups as $group) {
    $module = $moduleForSubject((string) $group['tipo']);
    if (!isset($documentModules[$module['key']])) {
        $documentModules[$module['key']] = [
            'key' => $module['key'],
            'label' => $module['label'],
            'groups' => [],
            'total' => 0,
            'pendientes' => 0,
            'actualizados' => 0,
        ];
    }
    $documentModules[$module['key']]['groups'][] = $group;
    $documentModules[$module['key']]['total'] += (int) $group['total'];
    $documentModules[$module['key']]['pendientes'] += (int) $group['faltantes'];
    $documentModules[$module['key']]['actualizados'] += (int) $group['actualizados'];
}
?>
<div class="heading compact workspace-heading document-heading">
    <div>
        <small>05. EXPEDIENTE Y CONTROL DOCUMENTAL</small>
        <h1>Documentos</h1>
        <p>Objetivo: cargar y controlar los soportes del protocolo familiar. Unidad de registro: una fila por cada documento requerido o recibido.</p>
    </div>
    <button type="button" class="primary" data-new-document>Nuevo documento</button>
</div>

<section class="document-summary" aria-label="Resumen documental">
    <article><strong data-document-total><?= $e($documentoSummary['total']) ?></strong><span>Total checklist</span></article>
    <article class="is-danger"><strong data-document-missing><?= $e($documentoSummary['faltantes']) ?></strong><span>Faltantes</span></article>
    <article class="is-warning"><strong data-document-requested><?= $e($documentoSummary['solicitados'] + $documentoSummary['revision']) ?></strong><span>Solicitados/revision</span></article>
    <article class="is-ok"><strong data-document-received><?= $e($documentoSummary['recibidos']) ?></strong><span>Recibidos</span></article>
    <article class="is-danger"><strong data-document-expired><?= $e($documentoSummary['vencidos']) ?></strong><span>Vencidos</span></article>
</section>

<div class="document-modal-backdrop" data-document-modal hidden>
    <div class="document-modal-panel" role="dialog" aria-modal="true" aria-labelledby="document-modal-title">
        <form class="grid document-form" method="post" enctype="multipart/form-data" action="<?= $e($basePath) ?>/protocolo-familiar/documentos" data-document-form data-document-suggestions="<?= $json($documentoSuggestions) ?>" data-document-subjects="<?= $json($subjects) ?>">
            <input type="hidden" name="csrf_token" value="<?= $e($csrfToken) ?>">
            <input type="hidden" name="id" value="">
            <input type="hidden" name="sujeto_codigo" value="">
            <input type="hidden" name="sujeto_nombre" value="">
            <div class="document-modal-header">
                <div>
                    <h2 id="document-modal-title" data-document-modal-title>Nuevo documento</h2>
                    <p>Marca el faltante, carga el soporte o deja trazabilidad de lo solicitado.</p>
                </div>
                <button type="button" class="document-icon-button" aria-label="Cerrar formulario" data-close-document>&times;</button>
            </div>
            <div class="cols">
                <label>Codigo<input name="codigo" value="Automatico" disabled></label>
                <label>Tipo de sujeto<?php $options('sujeto_tipo', $documentoOptions['sujeto_tipo']); ?></label>
            </div>
            <div class="cols">
                <label>Sujeto<select name="sujeto_selector" data-document-subject-select><option value="" disabled selected hidden>Seleccione...</option></select></label>
                <label>Documento<input name="documento_tipo" list="document-type-list" required><datalist id="document-type-list" data-document-type-list></datalist></label>
            </div>
            <div class="select-grid document-grid">
                <label>Categoria<?php $options('categoria', $documentoOptions['categoria']); ?></label>
                <label>Requerido<?php $options('requerido', $documentoOptions['requerido'], 'Si'); ?></label>
                <label>Estado<?php $options('estado', $documentoOptions['estado'], 'Faltante'); ?></label>
                <label>Nivel de riesgo<?php $options('nivel_riesgo', $documentoOptions['nivel_riesgo'], 'Medio'); ?></label>
                <label>Fecha de expedicion/actualizacion<input name="fecha_documento" type="date"></label>
                <label>Fecha vencimiento, si aplica<input name="fecha_vencimiento" type="date"></label>
            </div>
            <label class="document-upload">Archivo
                <input name="archivo" type="file" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx,.xls,.xlsx">
                <span>PDF, imagen, Word o Excel. Maximo 12 MB.</span>
            </label>
            <label>Observaciones<textarea name="observaciones" rows="3"></textarea></label>
            <div class="document-modal-actions">
                <span class="muted" data-document-status></span>
                <div class="inline">
                    <button type="button" data-cancel-document>Limpiar</button>
                    <button class="primary" type="submit">Guardar documento</button>
                </div>
            </div>
        </form>
    </div>
</div>

<section class="card document-records">
    <div class="document-toolbar">
        <div>
            <h2>Checklist documental</h2>
            <p class="muted"><span data-document-count><?= count($documentoRows) ?></span> documentos controlados</p>
        </div>
        <div class="document-filters" role="group" aria-label="Filtros de documentos">
            <button type="button" class="active" data-document-filter="todos">Todos</button>
            <button type="button" data-document-filter="faltantes">Faltantes</button>
            <button type="button" data-document-filter="recibidos">Recibidos</button>
            <button type="button" data-document-filter="vencidos">Vencidos</button>
        </div>
    </div>
    <?php if ($companyExternalFolders !== []): ?>
        <div class="external-folder-strip">
            <div>
                <h3>Carpetas externas vinculadas</h3>
                <p class="muted">Repositorios completos compartidos por contabilidad o administracion. El checklist interno conserva el control de soportes clave.</p>
            </div>
            <div class="external-folder-list">
                <?php foreach ($companyExternalFolders as $empresa): ?>
                    <article>
                        <div>
                            <strong><?= $e($empresa['razon_social'] ?? '') ?></strong>
                            <span><?= $e($empresa['carpeta_documental_nombre'] ?? 'Carpeta documental externa') ?></span>
                        </div>
                        <a href="<?= $e($empresa['carpeta_documental_url'] ?? '') ?>" target="_blank" rel="noopener">Abrir carpeta</a>
                    </article>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>
    <div class="document-modules" data-document-groups>
        <?php foreach ($documentModules as $moduleIndex => $module): ?>
            <details class="document-module" data-document-module data-module-key="<?= $e($module['key']) ?>" <?= $moduleIndex === array_key_first($documentModules) ? 'open' : '' ?>>
                <summary>
                    <span class="document-module-title"><strong><?= $e($module['label']) ?></strong><small><?= count($module['groups']) ?> expedientes documentales</small></span>
                    <span class="document-module-stats">
                        <span><strong><?= $e($module['total']) ?></strong> documentos</span>
                        <span class="<?= (int) $module['pendientes'] > 0 ? 'is-danger' : 'is-ok' ?>"><strong><?= $e($module['pendientes']) ?></strong> pendientes</span>
                        <span><strong><?= $e($module['actualizados']) ?></strong> actualizados</span>
                    </span>
                </summary>
                <div class="document-groups">
                    <?php foreach ($module['groups'] as $groupIndex => $group): ?>
                        <details class="document-group" data-document-group data-subject-key="<?= $e($group['key']) ?>" <?= ((int) $group['faltantes'] > 0 && $groupIndex === 0) ? 'open' : '' ?>>
                            <summary>
                                <span class="document-group-title">
                                    <strong><?= $e($group['nombre']) ?></strong>
                                    <small><?= $e($group['tipo']) ?> <?= $e($group['codigo']) ?></small>
                                </span>
                                <span class="document-group-stats">
                                    <span><strong data-group-total><?= $e($group['total']) ?></strong> docs</span>
                                    <span class="<?= (int) $group['faltantes'] > 0 ? 'is-danger' : 'is-ok' ?>"><strong data-group-missing><?= $e($group['faltantes']) ?></strong> pendientes</span>
                                    <span><strong data-group-updated><?= $e($group['actualizados']) ?></strong> actualizados</span>
                                </span>
                            </summary>
                            <div class="document-table-wrap">
                                <table class="document-data-table" data-document-table>
                                    <thead>
                                        <tr>
                                            <th>Codigo</th>
                                            <th>Documento</th>
                                            <th>Requerido</th>
                                            <th>Estado</th>
                                            <th>Archivo</th>
                                            <th>Actualizado</th>
                                            <th>Vencimiento</th>
                                            <th>Observaciones</th>
                                            <th>Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($group['rows'] as $row): ?>
                                            <tr data-document-row="<?= $e($row['id']) ?>" data-subject-key="<?= $e($group['key']) ?>" data-row="<?= $json($row) ?>" data-state="<?= $e($row['estado']) ?>">
                                                <td data-label="Codigo"><code><?= $e($row['codigo']) ?></code></td>
                                                <td data-label="Documento"><strong><?= $e($row['documento_tipo']) ?></strong><span><?= $e($row['categoria']) ?></span><small class="document-date-line">Fecha documental: <?= $e($date($row['fecha_documento']) ?: 'Sin fecha') ?></small></td>
                                                <td data-label="Requerido"><?= $e($row['requerido']) ?></td>
                                                <td data-label="Estado"><span class="document-state state-<?= $e(strtolower(str_replace(' ', '-', (string) $row['estado']))) ?>"><?= $e($row['estado']) ?></span></td>
                                                <td data-label="Archivo"><?php if (is_string($row['archivo_ruta']) && $row['archivo_ruta'] !== ''): ?><a href="<?= $e($basePath . $row['archivo_ruta']) ?>" target="_blank" rel="noopener">Ver archivo</a><?php else: ?><span class="document-missing">Sin archivo</span><?php endif; ?></td>
                                                <td data-label="Actualizado"><?= $e($date($row['updated_at'])) ?></td>
                                                <td data-label="Vencimiento"><?= $e($date($row['fecha_vencimiento'])) ?></td>
                                                <td data-label="Observaciones"><?= $e($row['observaciones']) ?></td>
                                                <td data-label="Acciones" class="document-row-actions"><button type="button" data-edit-document>Editar</button><button type="button" data-delete-document>Desactivar</button></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </details>
                    <?php endforeach; ?>
                </div>
            </details>
        <?php endforeach; ?>
    </div>
</section>
