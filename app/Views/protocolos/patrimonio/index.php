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
$date = static function (mixed $value): string {
    if (!is_string($value) || $value === '') {
        return '';
    }
    $parts = explode('-', $value);
    return count($parts) === 3 ? $parts[2] . '/' . $parts[1] . '/' . $parts[0] : $value;
};
$percent = static function (mixed $value): string {
    if ($value === null || $value === '') {
        return '';
    }
    return rtrim(rtrim(number_format((float) $value, 2, ',', '.'), '0'), ',') . '%';
};
$assetMetric = static function (array $row, string $key) use ($money, $percent): string {
    $analytics = is_array($row['patrimonio_analytics'] ?? null) ? $row['patrimonio_analytics'] : [];
    if ($key === 'rentabilidad_anual') {
        return isset($analytics[$key]) && $analytics[$key] !== null ? $percent($analytics[$key]) : '';
    }
    return $money($analytics[$key] ?? 0);
};
$assetChart = static function (array $rows, string $label, string $field, callable $format) use ($e): string {
    $source = array_values(array_filter($rows, static fn(mixed $row): bool => is_array($row) && is_numeric($row['ano'] ?? null)));
    if ($source === []) {
        return '<article class="asset-history-chart"><strong>' . $e($label) . '</strong><span class="muted">Sin historial</span></article>';
    }
    $max = max(1, ...array_map(static fn(array $row): float => abs((float) ($row[$field] ?? 0)), $source));
    $bars = '';
    foreach ($source as $row) {
        $value = (float) ($row[$field] ?? 0);
        $height = max(6, min(100, (abs($value) / $max) * 100));
        $bars .= '<span title="' . $e((string) $row['ano'] . ': ' . $format($value)) . '"><i style="height:' . $e((string) $height) . '%"></i><small>' . $e((string) $row['ano']) . '</small></span>';
    }
    return '<article class="asset-history-chart"><strong>' . $e($label) . '</strong><div class="asset-history-bars">' . $bars . '</div></article>';
};
$assetProfitRows = static function (array $row): array {
    $values = [];
    foreach (($row['valoraciones_anuales'] ?? []) as $valueRow) {
        if (is_array($valueRow) && is_numeric($valueRow['ano'] ?? null)) {
            $values[(int) $valueRow['ano']] = (float) ($valueRow['valor_comercial'] ?? 0);
        }
    }
    $share = max(0.0, min(100.0, (float) ($row['participacion_total'] ?? 0))) / 100;
    $fallbackValue = (float) ($row['valor_actual'] ?? 0);
    $rows = [];
    foreach (($row['ingresos_anuales'] ?? []) as $incomeRow) {
        if (!is_array($incomeRow) || !is_numeric($incomeRow['ano'] ?? null)) {
            continue;
        }
        $year = (int) $incomeRow['ano'];
        $basis = (($values[$year] ?? $fallbackValue) * $share);
        $rows[] = ['ano' => $year, 'rentabilidad' => $basis > 0 ? ((float) ($incomeRow['ino_anual'] ?? 0) / $basis) * 100 : 0];
    }
    return $rows;
};
$participantOptions = [];
foreach ($familiaRows as $persona) {
    $participantOptions[] = ['tipo' => 'Persona/Familia', 'nombre' => $persona['nombre_completo']];
}
foreach ($empresaRows as $empresa) {
    $participantOptions[] = ['tipo' => 'Empresa', 'nombre' => $empresa['razon_social']];
}
$assetsByType = [];
foreach ($patrimonioRows as $row) {
    $type = is_string($row['tipo_activo'] ?? null) && $row['tipo_activo'] !== '' ? $row['tipo_activo'] : 'Sin tipo';
    $assetsByType[$type][] = $row;
}
$assetDocumentsByCode = [];
foreach (($documentoRows ?? []) as $documento) {
    if (($documento['sujeto_tipo'] ?? '') !== 'Activo/Patrimonio') {
        continue;
    }
    $key = (string) ($documento['sujeto_codigo'] ?? '');
    if ($key === '') {
        continue;
    }
    $assetDocumentsByCode[$key][] = $documento;
}
?>
<div class="heading compact workspace-heading assets-heading">
    <div>
        <small>04. MAPA PATRIMONIAL</small>
        <h1>Patrimonio</h1>
        <p>Registra activos y pasivos con campos comunes y campos especiales segun su naturaleza. Cada activo conserva su identificador propio: matricula, placa, numero de titulo, cuenta o referencia.</p>
    </div>
    <button type="button" class="primary" data-new-asset>Nuevo activo</button>
</div>

<section class="asset-summary" data-asset-summary>
    <article><strong data-asset-total-count><?= $e($patrimonioSummary['activos']) ?></strong><span>Activos registrados</span></article>
    <article><strong data-asset-total-current><?= $e($money($patrimonioSummary['valor_actual'])) ?></strong><span>Valor comercial vigente</span></article>
    <article><strong data-asset-total-acquisition><?= $e($money($patrimonioSummary['valor_adquisicion'])) ?></strong><span>Valor de adquisicion/escritura</span></article>
</section>

<section class="card asset-category-dashboard">
    <div class="split">
        <div>
            <h2>Resumen por categoria</h2>
            <p>Compara valor comercial, participacion registrada, ingresos, gastos e INO por tipo de activo.</p>
        </div>
        <span class="muted">El INO aplica: ingreso bruto anual real - costos y gastos anuales.</span>
    </div>
    <div class="asset-category-grid" data-asset-category-summary>
        <?php foreach (($patrimonioByType ?? []) as $group): ?>
            <article class="asset-category-card">
                <header>
                    <strong><?= $e($group['tipo_activo'] ?? 'Sin categoria') ?></strong>
                    <span><?= $e((string) ($group['activos'] ?? 0)) ?> activos / <?= $e((string) ($group['unidades'] ?? 0)) ?> unidades</span>
                </header>
                <dl>
                    <div><dt>Valor comercial total</dt><dd><?= $e($money($group['valor_comercial_total'] ?? 0)) ?></dd></div>
                    <div><dt>Segun participacion</dt><dd><?= $e($money($group['valor_comercial_participacion'] ?? 0)) ?></dd></div>
                    <div><dt>Ingreso mensual total</dt><dd><?= $e($money($group['ingreso_mensual_total'] ?? 0)) ?></dd></div>
                    <div><dt>Ingreso real mensual</dt><dd><?= $e($money($group['ingreso_mensual_participacion'] ?? 0)) ?></dd></div>
                    <div><dt>Ingreso bruto anual</dt><dd><?= $e($money($group['ingreso_bruto_anual'] ?? 0)) ?></dd></div>
                    <div><dt>Gastos y costos anuales</dt><dd><?= $e($money($group['gastos_anuales'] ?? 0)) ?></dd></div>
                    <div><dt>INO anual</dt><dd><?= $e($money($group['ino_anual'] ?? 0)) ?></dd></div>
                </dl>
            </article>
        <?php endforeach; ?>
        <?php if (count($patrimonioByType ?? []) === 0): ?>
            <div class="asset-category-empty">Registra activos para calcular valor, unidades e ingresos por categoria.</div>
        <?php endif; ?>
    </div>
</section>

<section class="card asset-renewal-calendar" data-asset-renewal-calendar>
    <div class="split">
        <div>
            <h2>Calendario de vencimientos</h2>
            <p>Controla SOAT, tecnico-mecanica, polizas, contratos, dominios, hosting, licencias y membresias antes de que se venzan.</p>
        </div>
        <label class="asset-renewal-range">
            <span>Ventana</span>
            <select data-renewal-range>
                <option value="30">30 dias</option>
                <option value="60" selected>60 dias</option>
                <option value="90">90 dias</option>
                <option value="365">12 meses</option>
            </select>
        </label>
    </div>
    <div class="asset-renewal-metrics" data-renewal-metrics></div>
    <div class="asset-renewal-tabs" role="tablist">
        <button type="button" class="is-active" data-renewal-tab="calendar" aria-selected="true">Calendario</button>
        <button type="button" data-renewal-tab="alerts" aria-selected="false">Alertas</button>
    </div>
    <div class="asset-renewal-panel is-active" data-renewal-panel="calendar">
        <div class="asset-fullcalendar" data-renewal-fullcalendar></div>
    </div>
    <div class="asset-renewal-panel" data-renewal-panel="alerts" hidden>
        <div class="asset-renewal-list" data-renewal-list></div>
    </div>
</section>

<div class="asset-modal-backdrop" data-asset-modal hidden>
    <div class="asset-modal-panel" role="dialog" aria-modal="true" aria-labelledby="asset-modal-title">
        <form class="grid asset-form" method="post" action="<?= $e($basePath) ?>/protocolo-familiar/patrimonio" data-asset-form data-asset-schemas="<?= $json($patrimonioSchemas) ?>" data-asset-options="<?= $json($patrimonioOptions) ?>" data-asset-participant-options="<?= $json($participantOptions) ?>" data-asset-documents="<?= $json($assetDocumentsByCode) ?>" data-insurance-academy-json="<?= $json($insuranceAcademy ?? ['catalog' => [], 'coverages' => []]) ?>">
            <input type="hidden" name="csrf_token" value="<?= $e($csrfToken) ?>">
            <input type="hidden" name="id" value="">
            <div class="asset-modal-header">
                <div>
                    <h2 id="asset-modal-title" data-asset-modal-title>Nuevo activo</h2>
                    <p>Completa los datos esenciales y usa las guias solo cuando necesites criterio.</p>
                </div>
                <button type="button" class="asset-icon-button" aria-label="Cerrar formulario" data-close-asset>&times;</button>
            </div>
            <div class="cols">
                <label>Codigo<input name="codigo" value="Automatico" disabled></label>
                <label>Tipo de activo<?php $options('tipo_activo', $patrimonioOptions['tipo_activo']); ?></label>
            </div>
            <details class="asset-type-academy" data-asset-type-academy>
                <summary><span>Guia del activo</span><strong>Selecciona un tipo de activo</strong></summary>
                <div>
                    <p>Cada categoria activa campos, controles documentales, vencimientos y calculos diferentes.</p>
                    <dl>
                        <div><dt>Que se controla</dt><dd data-asset-academy-scope>Identificacion, titularidad, valor, soporte y riesgo.</dd></div>
                        <div><dt>Documento clave</dt><dd data-asset-academy-document>Depende de la naturaleza del activo.</dd></div>
                        <div><dt>Dato economico</dt><dd data-asset-academy-economic>Valor actual y participacion registrada.</dd></div>
                    </dl>
                </div>
            </details>
            <div class="cols">
                <label data-asset-common-field="nombre_descripcion">Nombre o descripcion<input name="nombre_descripcion" required></label>
                <label data-asset-identifier-label data-asset-common-field="identificador">Identificador <span class="asset-document-marker">*Control Documental*</span><input name="identificador" placeholder="Matricula, placa, cuenta, titulo o referencia"></label>
            </div>
            <div class="select-grid asset-grid">
                <label>Titular registral principal <span class="asset-document-marker">*Control Documental*</span><input name="titular" placeholder="Quien aparece en el soporte principal"></label>
                <label>Ambito titular<?php $options('ambito_titular', $patrimonioOptions['ambito_titular']); ?></label>
                <label>Empresa relacionada, si aplica<select name="empresa_relacionada"><option value="" disabled selected hidden>Seleccione...</option><?php foreach ($empresaRows as $empresa): ?><option value="<?= $e($empresa['razon_social']) ?>"><?= $e($empresa['razon_social']) ?></option><?php endforeach; ?></select></label>
                <label>Moneda<?php $options('moneda', $patrimonioOptions['moneda'], 'COP'); ?></label>
                <label data-asset-common-field="adquisicion">Valor de adquisicion/escritura <span class="asset-document-marker">*Control Documental*</span><input name="valor_adquisicion" inputmode="decimal" data-money-format placeholder="$0"></label>
                <label data-asset-common-field="adquisicion">Fecha de adquisicion <span class="asset-document-marker">*Control Documental*</span><input name="fecha_adquisicion" type="date"></label>
                <label data-asset-current-value-label data-asset-common-field="valor_actual">Valor actual <span class="asset-document-marker">*Control Documental*</span><input name="valor_actual" inputmode="decimal" data-money-format placeholder="$0"></label>
                <label data-asset-common-field="valor_actual">Fecha de corte valor actual<input name="fecha_corte_valor" type="date"></label>
                <label data-asset-common-field="metodo_valoracion">Metodo/fuente de valoracion <span class="asset-document-marker">*Control Documental*</span><?php $options('metodo_valoracion', $patrimonioOptions['metodo_valoracion']); ?></label>
                <label>Estado soporte<?php $options('estado_soporte', $patrimonioOptions['estado_soporte']); ?></label>
                <label>Nivel de riesgo<?php $options('nivel_riesgo', $patrimonioOptions['nivel_riesgo']); ?></label>
            </div>
            <section class="asset-specific" data-asset-specific>
                <h2>Campos especificos</h2>
                <p>Selecciona un tipo de activo para cargar los campos de identificacion y detalle.</p>
            </section>
            <section class="asset-subunits" data-asset-subunits hidden>
                <div class="asset-participation-header">
                    <div>
                        <h2>Subunidades arrendables <span class="asset-document-marker">*Control Documental*</span></h2>
                        <p>Usa esta tabla cuando un solo inmueble con la misma matricula se explota en varios locales, oficinas o espacios. No repitas la matricula: agrega cada unidad interna aqui.</p>
                    </div>
                    <button type="button" data-add-asset-subunit>Agregar subunidad</button>
                </div>
                <div class="asset-subunit-meter" aria-live="polite">
                    <span>Subunidades: <strong data-asset-subunit-total>0</strong></span>
                    <span>Canon mensual total: <strong data-asset-subunit-canon>$0</strong></span>
                </div>
                <div class="asset-subunit-rows" data-asset-subunit-rows></div>
            </section>
            <section class="asset-annual-history" data-asset-annual-history hidden>
                <div class="asset-participation-header">
                    <div>
                        <h2>Historial anual del inmueble</h2>
                        <p>Registra valores, canon, incrementos, renovaciones, costos y gastos por ano. El sistema calcula ingreso bruto real e INO anual.</p>
                    </div>
                </div>
                <div class="asset-history-block">
                    <div class="asset-history-title">
                        <strong>Valores catastral y comercial</strong>
                        <button type="button" data-add-asset-value-year>Agregar ano</button>
                    </div>
                    <div class="asset-history-rows" data-asset-value-year-rows></div>
                </div>
                <div class="asset-history-block">
                    <div class="asset-history-title">
                        <strong>Ingresos y canon</strong>
                        <button type="button" data-add-asset-income-year>Agregar ano</button>
                    </div>
                    <div class="asset-history-rows" data-asset-income-year-rows></div>
                </div>
                <div class="asset-history-block">
                    <div class="asset-history-title">
                        <strong>Gastos y costos</strong>
                        <button type="button" data-add-asset-expense-year>Agregar ano</button>
                    </div>
                    <div class="asset-history-rows" data-asset-expense-year-rows></div>
                </div>
                <div class="asset-history-block">
                    <div class="asset-history-title">
                        <strong>Resumen anual financiero</strong>
                    </div>
                    <div class="asset-financial-summary" data-asset-financial-summary></div>
                </div>
            </section>
            <section class="asset-fiducia" data-asset-fiducia hidden>
                <div class="asset-participation-header">
                    <div>
                        <h2>Beneficiarios fiducia civil <span class="asset-document-marker">*Control Documental*</span></h2>
                        <p>Registra por separado quienes son los beneficiarios, su calidad, condicion y porcentaje. Puede quedar por debajo del 100% si solo se conoce una parte.</p>
                    </div>
                    <button type="button" data-add-asset-fiducia-beneficiary>Agregar beneficiario</button>
                </div>
                <div class="asset-participation-meter" aria-live="polite">
                    <span>Beneficios registrados: <strong data-asset-fiducia-total>0%</strong></span>
                    <span>Pendiente por identificar: <strong data-asset-fiducia-pending>100%</strong></span>
                </div>
                <div class="asset-fiducia-rows" data-asset-fiducia-rows></div>
            </section>
            <nav class="asset-insurance-flow" data-asset-insurance-flow hidden aria-label="Flujo de seguros">
                <button type="button" data-asset-insurance-tab="coberturas">2. Coberturas requeridas</button>
                <button type="button" data-asset-insurance-tab="modelo" class="is-active">3. Valores asegurados</button>
                <button type="button" data-asset-insurance-tab="matriz">4. Poliza global / matriz</button>
                <button type="button" data-asset-insurance-tab="cotizaciones">5. Cotizaciones y decision</button>
                <button type="button" data-asset-insurance-tab="vigente">6. Resumen poliza vigente</button>
                <button type="button" data-asset-insurance-tab="movimientos">7. Cambios / endosos</button>
                <button type="button" data-asset-insurance-tab="historial">8. Historial</button>
            </nav>
            <section class="asset-insurance" data-asset-insurance-matrix data-asset-insurance-panel="matriz" hidden>
                <div class="asset-participation-header">
                    <div>
                        <h2>4. Poliza global / matriz <span class="asset-document-marker">*Control Documental*</span></h2>
                        <p>Registra una sola poliza cuando cubre varios inmuebles, unidades o todo un edificio. El sistema conserva la poliza una vez y asigna a este activo solo su costo proporcional.</p>
                    </div>
                    <button type="button" data-add-asset-insurance-matrix>Registrar poliza matriz</button>
                </div>
                <div class="asset-insurance-matrix-summary" data-asset-insurance-matrix-summary></div>
            </section>
            <section class="asset-insurance" data-asset-insurance data-asset-insurance-panel="cotizaciones" hidden>
                <div class="asset-participation-header">
                    <div>
                        <h2>Cotizaciones y decision <span class="asset-document-marker">*Control Documental*</span></h2>
                        <p>Registra las aseguradoras que cotizan la misma solicitud. Aqui se decide cual se toma; al seleccionar una opcion, pasa automaticamente al resumen de poliza vigente.</p>
                    </div>
                    <div class="asset-header-actions">
                        <button type="button" data-add-asset-insurance-matrix>Registrar poliza matriz</button>
                        <button type="button" data-add-asset-insurance-previous>Registrar poliza anterior</button>
                        <button type="button" data-add-asset-insurance-policy>Agregar oferta</button>
                    </div>
                </div>
                <div class="asset-insurance-guide" aria-label="Guia practica de seguros">
                    <strong>Ruta simple del analista</strong>
                    <ol>
                        <li>Relaciona bienes y construccion a valor de reposicion con fuente y fecha.</li>
                        <li>Marca los amparos que aplican y valida que su valor salga de esa relacion.</li>
                        <li>Define RCE por exposicion: actividad, terceros, empleados, parqueaderos, contratistas y predios.</li>
                        <li>Pide minimo tres ofertas con las mismas sumas aseguradas.</li>
                        <li>Cuando lleguen las respuestas, compara prima, deducibles, sublimites, exclusiones y toma la poliza con mejor cobertura neta.</li>
                    </ol>
                </div>
                <div class="asset-insurance-quote-analysis" data-asset-insurance-quote-analysis></div>
                <div class="asset-insurance-rows" data-asset-insurance-policy-rows></div>
            </section>
            <section class="asset-insurance" data-asset-insurance-current data-asset-insurance-panel="vigente" hidden>
                <div class="asset-participation-header">
                    <div>
                        <h2>Resumen poliza vigente</h2>
                        <p>Aqui no se diligencia manualmente. El resumen aparece cuando tomas una cotizacion, registras una poliza anterior o registras una poliza matriz/global vigente.</p>
                    </div>
                </div>
                <div class="asset-insurance-history" data-asset-insurance-current-summary></div>
            </section>
            <section class="asset-insurance" data-asset-insurance-coverage data-asset-insurance-panel="coberturas" hidden>
                <div class="asset-participation-header">
                    <div>
                        <h2>2. Coberturas requeridas por seguro <span class="asset-document-marker">*Control Documental*</span></h2>
                        <p>Por cada seguro incorporado al activo, marca los amparos que deben cotizarse o contratarse. La matriz sale del catalogo tecnico de seguros.</p>
                    </div>
                </div>
                <div class="asset-insurance-rows" data-asset-insurance-coverage-rows></div>
            </section>
            <section class="asset-insurance" data-asset-insurance-equipment data-asset-insurance-panel="modelo" hidden>
                <div class="asset-participation-header">
                    <div>
                        <h2>3. Valores asegurados y soporte <span class="asset-document-marker">*Control Documental*</span></h2>
                        <p>Relaciona los bienes, limites o exposiciones que alimentan cada cobertura. El valor asegurado debe salir de avaluos, facturas, cotizaciones, inventarios o limites documentados.</p>
                    </div>
                    <button type="button" data-add-asset-insurance-equipment>Agregar item</button>
                </div>
                <div class="asset-insurance-rows" data-asset-insurance-equipment-rows></div>
            </section>
            <section class="asset-insurance" data-asset-insurance-movements data-asset-insurance-panel="movimientos" hidden>
                <div class="asset-participation-header">
                    <div>
                        <h2>Cambios y endosos de vigencia <span class="asset-document-marker">*Control Documental*</span></h2>
                        <p>Registra compras, retiros, aumentos de valor, disminuciones, endosos o reportes. Al agregar un cambio, el sistema toma la poliza vigente como base.</p>
                    </div>
                    <button type="button" data-add-asset-insurance-movement>Agregar movimiento</button>
                </div>
                <div class="asset-insurance-rows" data-asset-insurance-movement-rows></div>
            </section>
            <section class="asset-insurance" data-asset-insurance-history data-asset-insurance-panel="historial" hidden>
                <div class="asset-participation-header">
                    <div>
                        <h2>Historial anual de seguros</h2>
                        <p>Resume por ano los valores asegurados, primas, aseguradora adoptada, coberturas y movimientos para renovar sin empezar desde cero.</p>
                    </div>
                </div>
                <div class="asset-insurance-history" data-asset-insurance-history-summary></div>
            </section>
            <section class="asset-participation" data-asset-participation>
                <div class="asset-participation-header">
                    <div>
                        <h2>Participacion sobre el activo</h2>
                        <p>Registra propietarios, empresas, terceros o calidades juridicas. Puede quedar por debajo del 100% si solo conocemos una parte.</p>
                    </div>
                    <button type="button" data-add-asset-participation>Agregar participante</button>
                </div>
                <div class="asset-participation-meter" aria-live="polite">
                    <span>Registrado: <strong data-asset-participation-total>0%</strong></span>
                    <span>Pendiente por identificar: <strong data-asset-participation-pending>100%</strong></span>
                </div>
                <div class="asset-participation-rows" data-asset-participation-rows></div>
            </section>
            <label class="asset-observations-field">Observaciones generales del activo<span class="asset-observations-help">Registra hallazgos, dudas, pendientes y comentarios transversales que apliquen al activo completo.</span><textarea name="observaciones" rows="3"></textarea></label>
            <section class="asset-document-inline" data-asset-modal-documents>
                <div class="asset-doc-inline-header">
                    <div>
                        <h2>Documentos del activo</h2>
                        <p>Los soportes de campos marcados como *Control Documental* se suben aqui despues de guardar el activo.</p>
                    </div>
                </div>
                <p class="muted">Guarda primero el activo para activar su expediente documental.</p>
            </section>
            <div class="asset-modal-actions">
                <span class="muted" data-asset-status></span>
                <div class="inline">
                    <button type="button" data-cancel-asset>Limpiar</button>
                    <button class="primary" type="submit">Guardar activo</button>
                </div>
            </div>
        </form>
    </div>
</div>

<section class="card asset-records">
    <div class="split">
        <h2>Registros</h2>
        <span class="muted"><span data-asset-count><?= count($patrimonioRows) ?></span> activos</span>
    </div>
    <div class="asset-record-tools">
        <label class="asset-search">
            <span>Buscar activo</span>
            <input type="search" placeholder="Nombre, titular, matricula, placa, empresa..." data-asset-search>
        </label>
        <span class="muted" data-asset-search-status></span>
    </div>
    <div class="asset-type-accordion" data-asset-table>
        <?php foreach ($assetsByType as $type => $rows): ?>
            <details class="asset-type-group" data-asset-type-group="<?= $e($type) ?>" open>
                <summary>
                    <span>
                        <strong><?= $e($type) ?></strong>
                        <small><span data-asset-type-count><?= count($rows) ?></span> activos</small>
                    </span>
                </summary>
                <div class="asset-type-items">
                    <?php foreach ($rows as $row): ?>
                        <details class="asset-accordion-item" data-asset-row="<?= $e($row['id']) ?>" data-row="<?= $json($row) ?>" data-asset-search-text="<?= $e(strtolower(trim(implode(' ', [$row['codigo'], $row['tipo_activo'], $row['nombre_descripcion'], $row['identificador'], $row['titular'], $row['ambito_titular'], $row['empresa_relacionada'], $row['estado_soporte'], $row['nivel_riesgo']])))) ?>">
                            <summary>
                                <span class="asset-summary-main">
                                    <strong><?= $e($row['nombre_descripcion']) ?></strong>
                                    <small><?= $e($row['identificador'] ?: ($row['etiqueta_identificador'] ?: 'Sin identificador')) ?></small>
                                </span>
                                <span class="asset-summary-meta">
                                    <span><b><?= $e($money($row['valor_actual'])) ?></b> valor vigente</span>
                                    <span><?= $e($percent($row['participacion_total'] ?? 0)) ?> registrada</span>
                                    <?php if (($row['tipo_activo'] ?? '') === 'Inmueble' && count($row['subunidades'] ?? []) > 0): ?><span><?= count($row['subunidades']) ?> subunidades</span><?php endif; ?>
                                    <code><?= $e($row['codigo']) ?></code>
                                </span>
                            </summary>
                            <div class="asset-detail">
                                <dl class="asset-detail-grid">
                                    <div><dt>Titular</dt><dd><?= $e($row['titular']) ?></dd></div>
                                    <div><dt>Ambito titular</dt><dd><?= $e($row['ambito_titular']) ?></dd></div>
                                    <div><dt>Empresa relacionada</dt><dd><?= $e($row['empresa_relacionada']) ?></dd></div>
                                    <div><dt>Valor adquisicion/escritura</dt><dd><?= $e($money($row['valor_adquisicion'])) ?></dd></div>
                                    <div><dt>Valor comercial vigente</dt><dd><span class="asset-value"><?= $e($money($row['valor_actual'])) ?></span></dd></div>
                                    <div><dt>Fecha corte</dt><dd><?= $e($date($row['fecha_corte_valor'])) ?></dd></div>
                                    <div><dt>Soporte</dt><dd><?= $e($row['estado_soporte']) ?></dd></div>
                                    <div><dt>Riesgo</dt><dd><?= $e($row['nivel_riesgo']) ?></dd></div>
                                    <div><dt>Participacion</dt><dd><?= $e($percent($row['participacion_total'] ?? 0)) ?> registrada<?php if (($row['participacion_pendiente'] ?? 0) > 0): ?><span class="asset-pending-share"><?= $e($percent($row['participacion_pendiente'])) ?> pendiente</span><?php endif; ?></dd></div>
                                    <div><dt>Ingreso bruto anual</dt><dd><?= $e($assetMetric($row, 'ingreso_bruto_anual')) ?></dd></div>
                                    <div><dt>Gastos anuales</dt><dd><?= $e($assetMetric($row, 'gastos_anuales')) ?></dd></div>
                                    <div><dt>INO anual</dt><dd><span class="asset-value"><?= $e($assetMetric($row, 'ino_anual')) ?></span></dd></div>
                                    <div><dt>Rentabilidad anual</dt><dd><?= $e($assetMetric($row, 'rentabilidad_anual')) ?></dd></div>
                                    <div class="wide"><dt>Observaciones</dt><dd><?= $e($row['observaciones']) ?></dd></div>
                                </dl>
                                <?php if (($row['tipo_activo'] ?? '') === 'Inmueble'): ?>
                                    <div class="asset-history-dashboard">
                                        <?= $assetChart($row['valoraciones_anuales'] ?? [], 'Valor catastral ano tras ano', 'valor_catastral', $money) ?>
                                        <?= $assetChart($row['valoraciones_anuales'] ?? [], 'Valor comercial ano tras ano', 'valor_comercial', $money) ?>
                                        <?= $assetChart($row['gastos_anuales'] ?? [], 'Gastos y costos ano tras ano', 'total_gastos', $money) ?>
                                        <?= $assetChart($assetProfitRows($row), 'Rentabilidad inmueble', 'rentabilidad', $percent) ?>
                                    </div>
                                <?php endif; ?>
                                <div class="asset-row-actions">
                                    <button type="button" data-edit-asset>Editar</button>
                                    <button type="button" data-delete-asset>Desactivar</button>
                                </div>
                            </div>
                        </details>
                    <?php endforeach; ?>
                </div>
            </details>
        <?php endforeach; ?>
    </div>
</section>
