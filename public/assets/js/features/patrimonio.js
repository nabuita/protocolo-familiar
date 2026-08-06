const assetEscape = (value) => String(value ?? '').replace(/[&<>"']/g, (char) => ({
    '&': '&amp;',
    '<': '&lt;',
    '>': '&gt;',
    '"': '&quot;',
    "'": '&#039;',
}[char]));

const assetFields = [
    'tipo_activo',
    'nombre_descripcion',
    'identificador',
    'titular',
    'ambito_titular',
    'empresa_relacionada',
    'valor_adquisicion',
    'fecha_adquisicion',
    'valor_actual',
    'fecha_corte_valor',
    'metodo_valoracion',
    'moneda',
    'estado_soporte',
    'nivel_riesgo',
    'observaciones',
];

const assetLoadedRows = new WeakMap();

const assetMoney = (value) => {
    if (value === null || value === undefined || value === '') {
        return '';
    }
    return new Intl.NumberFormat('es-CO', { style: 'currency', currency: 'COP', maximumFractionDigits: 0 }).format(Number(value));
};

const assetMoneyPlain = (value) => {
    if (value === null || value === undefined || String(value).trim() === '') {
        return '';
    }
    const raw = String(value);
    let clean = raw.replace(/[$\s\u00a0]/g, '');
    if (clean.includes(',')) {
        clean = clean.replace(/\./g, '').replace(',', '.');
    } else if ((clean.match(/\./g) || []).length > 1 || /\.\d{3}$/.test(clean)) {
        clean = clean.replace(/\./g, '');
    }
    if (!/^-?\d+(\.\d+)?$/.test(clean)) {
        return raw;
    }
    const number = Number(clean);
    return Number.isFinite(number) ? new Intl.NumberFormat('es-CO', { maximumFractionDigits: 0 }).format(number) : '';
};

const assetPercent = (value) => {
    const number = Number(value ?? 0);
    return `${new Intl.NumberFormat('es-CO', { maximumFractionDigits: 2 }).format(number)}%`;
};

const assetNumber = (value) => {
    if (value === null || value === undefined || String(value).trim() === '') {
        return 0;
    }
    let clean = String(value).replace(/[$%\s\u00a0]/g, '');
    if (clean.includes(',')) {
        clean = clean.replace(/\./g, '').replace(',', '.');
    } else if ((clean.match(/\./g) || []).length > 1 || /\.\d{3}$/.test(clean)) {
        clean = clean.replace(/\./g, '');
    }
    const number = Number(clean);
    return Number.isFinite(number) ? number : 0;
};

const formatAssetMoneyInput = (input) => {
    if (!(input instanceof HTMLInputElement)) {
        return;
    }
    const formatted = assetMoneyPlain(input.value);
    input.value = formatted || '';
};

const formatAssetMoneyInputs = (root) => {
    root.querySelectorAll('[data-money-format]').forEach((input) => {
        if (input instanceof HTMLInputElement) {
            formatAssetMoneyInput(input);
        }
    });
};

const assetDate = (value) => {
    if (!value || !/^\d{4}-\d{2}-\d{2}$/.test(String(value))) {
        return value ?? '';
    }
    const [year, month, day] = String(value).split('-');
    return `${day}/${month}/${year}`;
};

const assetFetchJson = async (url, options = {}) => {
    const response = await fetch(url, {
        ...options,
        credentials: options.credentials || 'same-origin',
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
            Accept: 'application/json',
            ...(options.headers || {}),
        },
    });
    const text = await response.text();
    let payload = {};
    try {
        payload = text ? JSON.parse(text) : {};
    } catch {
        const looksLikeHtml = /<!doctype|<html|<form/i.test(text);
        throw new Error(looksLikeHtml
            ? 'La sesion vencio o el servidor devolvio una pagina en vez de confirmar el guardado. Actualiza, inicia sesion si hace falta y vuelve a guardar.'
            : 'El servidor devolvio una respuesta no valida. Revisa si la sesion sigue activa e intenta de nuevo.');
    }
    if (!response.ok || !payload.ok) {
        throw new Error(payload.error || 'No fue posible completar la operacion.');
    }
    return payload;
};

const assetDaysUntil = (dateValue) => {
    if (!dateValue || !/^\d{4}-\d{2}-\d{2}$/.test(String(dateValue))) {
        return null;
    }
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    const target = new Date(`${dateValue}T00:00:00`);
    return Math.round((target.getTime() - today.getTime()) / 86400000);
};

const assetShiftDate = (dateValue, { months = 0, days = 0 } = {}) => {
    if (!dateValue || !/^\d{4}-\d{2}-\d{2}$/.test(String(dateValue))) {
        return '';
    }
    const date = new Date(`${dateValue}T00:00:00`);
    if (months) {
        const originalDay = date.getDate();
        date.setMonth(date.getMonth() + months);
        if (date.getDate() !== originalDay) {
            date.setDate(0);
        }
    }
    if (days) {
        date.setDate(date.getDate() + days);
    }
    return date.toISOString().slice(0, 10);
};

const assetExpiryFieldMap = [
    ['fecha_fin_poliza', 'Fin de vigencia poliza', 'Poliza'],
    ['fecha_renovacion_poliza', 'Renovacion de poliza', 'Poliza'],
    ['fecha_vencimiento_soat', 'Vencimiento SOAT', 'SOAT'],
    ['fecha_vencimiento_tecnomecanica', 'Vencimiento tecnico-mecanica', 'Revision tecnico-mecanica'],
    ['fecha_proxima_calibracion', 'Proxima calibracion / revision tecnica', 'Revision tecnica'],
    ['proxima_revision_mantenimiento', 'Proxima revision de mantenimiento', 'Mantenimiento'],
    ['proxima_revision', 'Proxima revision general', 'Seguimiento'],
    ['proxima_fecha_reajuste', 'Proximo reajuste de arriendo', 'Arrendamiento'],
    ['fecha_vencimiento', 'Fecha de vencimiento', 'Vencimiento'],
    ['vencimiento_fecha', 'Vencimiento / renovacion digital', 'Digital'],
    ['portal_fecha_renovacion', 'Renovacion portal inmobiliario', 'Portal inmobiliario'],
    ['firma_fecha_renovacion', 'Renovacion firma electronica', 'Firma electronica'],
    ['suscripcion_fecha_renovacion', 'Renovacion membresia web / SaaS', 'Membresia web / SaaS'],
    ['ia_fecha_renovacion', 'Renovacion membresia IA', 'Membresia IA'],
    ['fecha_ultima_revision', 'Revision de seguimiento', 'Seguimiento'],
];

const addAssetExpiryItem = (items, row, dateValue, label, category, extra = {}) => {
    if (!dateValue && !extra.allowMissing) {
        return;
    }
    if (dateValue && !/^\d{4}-\d{2}-\d{2}$/.test(String(dateValue))) {
        return;
    }
    items.push({
        id: `${row.id}:${category}:${label}:${dateValue || 'sin-fecha'}:${items.length}`,
        asset_id: row.id,
        codigo: row.codigo,
        nombre: row.nombre_descripcion,
        identificador: row.identificador,
        tipo_activo: row.tipo_activo,
        subcategoria: row.detalle?.subcategoria || '',
        categoria: category,
        concepto: label,
        fecha_vencimiento: dateValue || '',
        responsable: extra.responsable || row.detalle?.vencimiento_responsable || row.detalle?.responsable_seguimiento || row.detalle?.suscripcion_responsable || row.detalle?.ia_responsable_cuenta || row.detalle?.portal_responsable_operacion || '',
        estado: extra.estado || row.detalle?.vencimiento_estado || row.detalle?.estado_poliza || row.detalle?.suscripcion_estado || '',
        costo: extra.costo || row.detalle?.vencimiento_costo_periodico || row.detalle?.costos_mantenimiento || row.detalle?.portal_costo_periodico || row.detalle?.firma_costo_periodico || row.detalle?.suscripcion_costo_periodico || row.detalle?.ia_costo_periodico || '',
    });
};

const assetRenewalItemsFromRow = (row) => {
    const detail = row?.detalle || {};
    const items = [];
    assetExpiryFieldMap.forEach(([key, label, category]) => {
        addAssetExpiryItem(items, row, detail[key], label, category);
    });
    const intangibleDueDate = detail.vencimiento_fecha || detail.portal_fecha_renovacion || detail.firma_fecha_renovacion || detail.suscripcion_fecha_renovacion || detail.ia_fecha_renovacion || '';
    if (['Activo intangible/tecnologico/PI', 'Marca/Intangible'].includes(row?.tipo_activo || '') && !intangibleDueDate) {
        addAssetExpiryItem(items, row, '', 'Intangible sin fecha de vencimiento', 'Intangible', {
            allowMissing: true,
            responsable: detail.vencimiento_responsable || detail.area_responsable || '',
            costo: detail.vencimiento_costo_periodico || detail.costos_mantenimiento || '',
            estado: detail.vencimiento_estado || detail.estado_tecnologico || '',
        });
    }
    if (detail.poliza_seguro === 'Si' && !detail.fecha_fin_poliza && !detail.fecha_renovacion_poliza) {
        addAssetExpiryItem(items, row, '', 'Poliza sin fecha de vencimiento', 'Poliza', { allowMissing: true });
    }
    if (detail.soat_estado && !['No aplica', 'Por confirmar'].includes(detail.soat_estado) && !detail.fecha_vencimiento_soat) {
        addAssetExpiryItem(items, row, '', 'SOAT sin fecha de vencimiento', 'SOAT', { allowMissing: true });
    }
    if (detail.tecnomecanica_estado && !['No aplica', 'Por confirmar'].includes(detail.tecnomecanica_estado) && !detail.fecha_vencimiento_tecnomecanica) {
        addAssetExpiryItem(items, row, '', 'Tecnico-mecanica sin fecha de vencimiento', 'Revision tecnico-mecanica', { allowMissing: true });
    }
    (Array.isArray(row?.subunidades) ? row.subunidades : []).forEach((unit, index) => {
        addAssetExpiryItem(items, row, unit.fecha_fin, `Fin contrato ${unit.nombre_subunidad || unit.codigo_subunidad || index + 1}`, 'Arrendamiento', {
            responsable: unit.arrendatario || '',
            costo: unit.canon_mensual || '',
            estado: unit.estado_ocupacion || '',
        });
        if ((unit.contrato_soporte || unit.arrendatario) && !unit.fecha_fin) {
            addAssetExpiryItem(items, row, '', `Contrato sin fecha ${unit.nombre_subunidad || unit.codigo_subunidad || index + 1}`, 'Arrendamiento', {
                allowMissing: true,
                responsable: unit.arrendatario || '',
                costo: unit.canon_mensual || '',
                estado: unit.estado_ocupacion || '',
            });
        }
    });
    (Array.isArray(row?.ingresos_anuales) ? row.ingresos_anuales : []).forEach((income) => {
        addAssetExpiryItem(items, row, income.fecha_renovacion, `Renovacion canon ${income.ano || ''}`.trim(), 'Arrendamiento', {
            costo: income.nuevo_canon_mensual || income.canon_mensual || '',
            estado: income.ino_anual !== undefined ? `INO ${assetMoney(income.ino_anual)}` : '',
        });
    });
    (Array.isArray(row?.seguro_polizas) ? row.seguro_polizas : []).forEach((policy, index) => {
        const name = [policy.ramo, policy.numero_poliza].filter(Boolean).join(' / ') || `Poliza ${index + 1}`;
        const preventiveDate = policy.fecha_renovacion || assetShiftDate(policy.fecha_fin, { months: -1 });
        addAssetExpiryItem(items, row, policy.fecha_fin, `Fin vigencia ${name}`, 'Poliza', {
            responsable: policy.intermediario || policy.aseguradora || '',
            costo: policy.prima_total || '',
            estado: policy.estado || '',
        });
        addAssetExpiryItem(items, row, preventiveDate, `Renovar un mes antes ${name}`, 'Poliza', {
            responsable: policy.intermediario || policy.aseguradora || '',
            costo: policy.prima_total || '',
            estado: policy.estado || '',
        });
        if ((policy.ramo || policy.numero_poliza || policy.prima_total) && !policy.fecha_fin && !policy.fecha_renovacion) {
            addAssetExpiryItem(items, row, '', `Poliza sin fecha ${name}`, 'Poliza', {
                allowMissing: true,
                responsable: policy.intermediario || policy.aseguradora || '',
                costo: policy.prima_total || '',
                estado: policy.estado || '',
            });
        }
    });
    (Array.isArray(row?.seguro_coberturas) ? row.seguro_coberturas : []).forEach((coverage, index) => {
        const name = coverage.cobertura || `Cobertura ${index + 1}`;
        addAssetExpiryItem(items, row, coverage.fecha_fin, `Fin cobertura ${name}`, 'Poliza', {
            costo: coverage.prima || '',
        });
        addAssetExpiryItem(items, row, coverage.fecha_renovacion, `Renovacion cobertura ${name}`, 'Poliza', {
            costo: coverage.prima || '',
        });
    });
    (Array.isArray(row?.seguro_equipos) ? row.seguro_equipos : []).forEach((equipment, index) => {
        const name = equipment.item || equipment.equipo || `Item asegurado ${index + 1}`;
        addAssetExpiryItem(items, row, equipment.fecha_fin, `Fin cobertura ${name}`, 'Poliza', {
            costo: equipment.valor_asegurable_sugerido || equipment.valor_reposicion || '',
        });
        addAssetExpiryItem(items, row, equipment.fecha_renovacion, `Renovacion cobertura ${name}`, 'Poliza', {
            costo: equipment.valor_asegurable_sugerido || equipment.valor_reposicion || '',
        });
    });
    (Array.isArray(row?.seguro_movimientos) ? row.seguro_movimientos : []).forEach((movement, index) => {
        const name = movement.item || movement.tipo_movimiento || `Movimiento ${index + 1}`;
        if ((movement.estado_reporte || '') === 'Pendiente de reportar') {
            addAssetExpiryItem(items, row, movement.fecha_reporte_aseguradora || '', `Reportar a aseguradora ${name}`, 'Poliza', {
                allowMissing: true,
                costo: movement.valor_variacion || '',
                estado: movement.estado_reporte || '',
            });
        }
    });
    return items;
};

const assetSearchText = (row) => [
    row.codigo,
    row.tipo_activo,
    row.nombre_descripcion,
    row.identificador,
    row.titular,
    row.ambito_titular,
    row.empresa_relacionada,
    row.estado_soporte,
    row.nivel_riesgo,
    ...(Array.isArray(row.subunidades) ? row.subunidades.flatMap((item) => [item.nombre_subunidad, item.codigo_subunidad, item.arrendatario, item.estado_ocupacion]) : []),
].join(' ').toLowerCase();

const parseAssetJson = (form, key) => {
    try {
        return JSON.parse(form.dataset[key] || '{}');
    } catch (_) {
        return {};
    }
};

const insuranceAcademyData = (form) => {
    if (!form) {
        return { catalog: [], coverages: [] };
    }
    const data = parseAssetJson(form, 'insuranceAcademyJson');
    return {
        catalog: Array.isArray(data.catalog) ? data.catalog : [],
        coverages: Array.isArray(data.coverages) ? data.coverages : [],
    };
};

const insuranceCatalogValue = (row, ...keys) => {
    if (!row) {
        return '';
    }
    for (const key of keys) {
        if (row[key]) {
            return row[key];
        }
    }
    return '';
};

const insuranceCatalogRamo = (row) => insuranceCatalogValue(row, 'Ramo oficial', 'Ramo');
const insuranceCatalogMacro = (row) => insuranceCatalogValue(row, 'Macro-ramo');

const normalizeInsuranceText = (value) => String(value || '')
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .toLowerCase()
    .replace(/[^a-z0-9]+/g, ' ')
    .trim();

const insuranceRamoAliases = {
    'Automovil todo riesgo': 'Automóviles',
    'Responsabilidad civil extracontractual': 'Responsabilidad Civil',
    'Responsabilidad civil profesional': 'Responsabilidad Civil',
    'Responsabilidad civil contractual': 'Responsabilidad Civil',
    'Equipo electronico / corriente debil': 'Corriente Débil',
    'Rotura de maquinaria': 'Montaje y Rotura de Maquinaria',
    'Danos materiales inmueble arrendado': 'Incendio',
    'Todo riesgo dano material': 'Incendio',
    'Incendio y terremoto': 'Incendio',
    'Cumplimiento arrendamiento': 'Cumplimiento',
    'Transporte de mercancias': 'Transporte',
    'Vida grupo': 'Vida Grupo',
    'Cyber / riesgo digital': 'Responsabilidad Civil',
};

const normalizeInsuranceRamo = (value) => insuranceRamoAliases[value] || value;

const insuranceMacrosForForm = (form) => [...new Set(insuranceAcademyData(form).catalog
    .map(insuranceCatalogMacro)
    .filter(Boolean))];

const insuranceCatalogForProduct = (form, product) => {
    const normalized = normalizeInsuranceText(normalizeInsuranceRamo(product));
    return insuranceAcademyData(form).catalog.find((row) => normalizeInsuranceText(insuranceCatalogRamo(row)) === normalized) || null;
};

const insuranceAcademyRowsForRamo = (form, product) => {
    const normalized = normalizeInsuranceText(normalizeInsuranceRamo(product));
    return insuranceAcademyData(form).coverages.filter((row) => normalizeInsuranceText(row.Ramo) === normalized);
};

const assetDocumentStateClass = (value) => String(value ?? '').toLowerCase().replace(/\s+/g, '-');

const assetDocumentStats = (rows = []) => {
    const pendingStates = ['Faltante', 'Solicitado', 'En revision', 'Vencido'];
    return {
        total: rows.length,
        pending: rows.filter((item) => pendingStates.includes(item.estado || '')).length,
        uploaded: rows.filter((item) => Boolean(item.archivo_ruta)).length,
    };
};

const assetDocumentItemHtml = (row, basePath) => {
    const hasFile = Boolean(row.archivo_ruta);
    const file = hasFile
        ? `<a href="${assetEscape(basePath + row.archivo_ruta)}" target="_blank" rel="noopener">Ver archivo</a>`
        : '<span class="document-missing">Sin archivo</span>';
    return `
        <div>
            <code>${assetEscape(row.codigo)}</code>
            <strong>${assetEscape(row.documento_tipo)}</strong>
            <span>${assetEscape(row.categoria)}</span>
            <small class="document-date-line">Fecha documental: ${assetEscape(assetDate(row.fecha_documento) || 'Sin fecha')}</small>
        </div>
        <div class="asset-document-meta">
            <span class="document-state state-${assetEscape(assetDocumentStateClass(row.estado))}">${assetEscape(row.estado)}</span>
            ${file}
            <button type="button" data-asset-open-document>Editar</button>
            <button type="button" data-asset-delete-document>Quitar</button>
        </div>
    `;
};

const renderAssetModalDocuments = (form, row, docsByCode, basePath) => {
    const target = form.querySelector('[data-asset-modal-documents]');
    if (!(target instanceof HTMLElement)) {
        return;
    }
    const code = row?.codigo || '';
    const rows = code ? (docsByCode[code] || []) : [];
    if (!code || code === 'Automatico') {
        target.innerHTML = `
            <div class="asset-doc-inline-header">
                <div>
                    <h2>Documentos del activo</h2>
                    <p>Los soportes de campos marcados como *Control Documental* se suben aqui despues de guardar el activo.</p>
                </div>
            </div>
            <p class="muted">Guarda primero el activo para activar su expediente documental.</p>
        `;
        return;
    }
    if (rows.length === 0) {
        target.innerHTML = `
        <div class="asset-doc-inline-header">
            <div>
                <h2>Documentos del activo</h2>
                <p>El checklist documental se crea automaticamente con base en el tipo de activo y sus campos de control.</p>
            </div>
            <button type="button" data-asset-new-document>Nuevo soporte</button>
        </div>
        <p class="muted">Expediente documental pendiente de sincronizar. Guarda el activo para crearlo.</p>
        `;
        return;
    }
    const stats = assetDocumentStats(rows);
    const grouped = rows.reduce((acc, item) => {
        const key = item.categoria || 'Sin carpeta';
        acc[key] ||= [];
        acc[key].push(item);
        return acc;
    }, {});
    target.innerHTML = `
        <div class="asset-doc-inline-header">
            <div>
                <h2>Documentos del activo</h2>
                <p>Expediente general del activo. Abrelo solo cuando necesites subir o revisar soportes.</p>
            </div>
            <button type="button" data-asset-new-document>Nuevo soporte</button>
            <div class="asset-doc-inline-summary">
                <span><strong>${stats.total}</strong> documentos</span>
                <span class="${stats.pending > 0 ? 'is-danger' : 'is-ok'}"><strong>${stats.pending}</strong> pendientes</span>
                <span><strong>${stats.uploaded}</strong> subidos</span>
            </div>
        </div>
        <details class="asset-document-expedient">
            <summary><strong>Ver expediente documental</strong><span>${stats.total} documentos / ${stats.pending} pendientes</span></summary>
            <div class="asset-document-folders">
                ${Object.entries(grouped).map(([folder, items]) => `
                    <details class="asset-document-folder">
                        <summary><strong>${assetEscape(folder)}</strong><span>${items.length} docs</span></summary>
                        <div class="asset-document-list">
                            ${items.map((item) => `<article class="asset-document-item" data-asset-doc-row="${assetEscape(item.id)}" data-row="${assetEscape(JSON.stringify(item))}" data-state="${assetEscape(item.estado || '')}">${assetDocumentItemHtml(item, basePath)}</article>`).join('')}
                        </div>
                    </details>
                `).join('')}
            </div>
        </details>
    `;
};

const ASSET_DRAFT_KEY = 'protocolo-familiar:patrimonio:activo:draft';

const assetDraftKey = (form, id = null) => `${ASSET_DRAFT_KEY}:${id || form.elements.id?.value || 'nuevo'}`;

const assetFormRows = (form, selector, fields) => [...form.querySelectorAll(selector)].map((row) => fields.reduce((carry, field) => {
    carry[field] = row.querySelector(`[name$="[${field}]"]`)?.value ?? '';
    return carry;
}, {}));

const collectAssetDraft = (form) => {
    const detail = {};
    form.querySelectorAll('[name^="detalle["]').forEach((field) => {
        if (field instanceof HTMLInputElement || field instanceof HTMLSelectElement || field instanceof HTMLTextAreaElement) {
            const match = field.name.match(/^detalle\[([^\]]+)\]$/);
            if (match) {
                detail[match[1]] = field.value;
            }
        }
    });
    return {
        savedAt: new Date().toISOString(),
        fields: assetFields.reduce((carry, field) => {
            carry[field] = form.elements[field]?.value ?? '';
            return carry;
        }, {}),
        detalle: detail,
        participaciones: assetFormRows(form, '[data-asset-participation-row]', ['tipo_participante', 'participante', 'calidad', 'porcentaje', 'soporte_observaciones']),
        fiducia_beneficiarios: assetFormRows(form, '[data-asset-fiducia-row]', ['beneficiario', 'calidad', 'porcentaje', 'condicion', 'soporte_observaciones']),
        subunidades: assetFormRows(form, '[data-asset-subunit-row]', ['codigo_subunidad', 'nombre_subunidad', 'area', 'uso', 'arrendatario', 'canon_mensual', 'administracion_mensual', 'iva', 'retencion', 'contrato_soporte', 'fecha_inicio', 'fecha_fin', 'estado_ocupacion', 'observaciones']),
        valoraciones_anuales: assetFormRows(form, '[data-asset-value-year-row]', ['ano', 'valor_catastral', 'valor_comercial', 'fecha_corte', 'fuente', 'observaciones']),
        ingresos_anuales: assetFormRows(form, '[data-asset-income-year-row]', ['ano', 'fecha_inicio_vigencia', 'fecha_fin_vigencia', 'meses_vigencia', 'canon_mensual', 'porcentaje_participacion', 'incremento_porcentaje', 'incremento_valor', 'nuevo_canon_mensual', 'fecha_renovacion', 'observaciones']),
        gastos_anuales: assetFormRows(form, '[data-asset-expense-year-row]', ['ano', 'predial', 'administracion', 'seguros', 'mantenimiento', 'reparaciones', 'servicios_publicos', 'valorizacion', 'impuestos', 'honorarios_administracion', 'comisiones', 'juridicos_notariales', 'financieros_hipoteca', 'adecuaciones', 'otros', 'observaciones']),
        seguro_polizas: assetFormRows(form, '[data-asset-insurance-policy-row]', ['ano', 'tipo_documento', 'ramo', 'aseguradora', 'contacto_nombre', 'contacto_correo', 'contacto_celular', 'intermediario', 'agencia_expedidora', 'codigo_agencia', 'numero_poliza', 'numero_emision', 'numero_pago_electronico', 'modalidad_facturacion', 'coaseguro', 'tomador', 'asegurado', 'beneficiario', 'direccion_riesgo', 'ciudad_riesgo', 'actividad_riesgo', 'tipo_riesgo', 'fecha_inicio', 'fecha_fin', 'fecha_renovacion', 'prima_neta', 'iva', 'gastos_expedicion', 'prima_total', 'valor_asegurado_total', 'deducible_general', 'forma_pago', 'numero_cuotas', 'clausulado', 'anexos_endosos', 'exclusiones_relevantes', 'texto_aclaratorio', 'asistencias', 'estado', 'adoptada', 'fecha_adopcion', 'criterio_adopcion', 'cotizacion_matriz', 'alcance_poliza', 'grupo_poliza', 'metodo_distribucion', 'coeficiente_participacion', 'numero_unidades_cubiertas', 'prima_asignada', 'observaciones_distribucion', 'soporte', 'observaciones']),
        seguro_coberturas: assetFormRows(form, '[data-asset-insurance-coverage-row]', assetInsuranceCoverageFields),
        seguro_equipos: assetFormRows(form, '[data-asset-insurance-equipment-row]', ['ano', 'numero_poliza', 'ramo', 'cobertura_asociada', 'categoria_item', 'item', 'descripcion', 'unidad', 'cantidad', 'ubicacion', 'serial_referencia', 'valor_compra', 'fecha_adquisicion', 'valor_reposicion_unitario', 'valor_reposicion', 'fuente_consulta', 'fecha_consulta', 'ano_adquisicion', 'edad_anos', 'vida_util_anos', 'regla_demerito', 'depreciacion_porcentaje', 'depreciacion_valor', 'valor_asegurable_sugerido', 'incluye_terreno', 'fecha_inicio', 'fecha_fin', 'fecha_renovacion', 'observaciones']),
        seguro_movimientos: assetFormRows(form, '[data-asset-insurance-movement-row]', ['ano', 'fecha', 'tipo_movimiento', 'numero_poliza', 'ramo', 'cobertura', 'item', 'valor_variacion', 'estado_reporte', 'fecha_reporte_aseguradora', 'soporte', 'observaciones']),
    };
};

const saveAssetDraft = (form) => {
    try {
        window.localStorage.setItem(assetDraftKey(form), JSON.stringify(collectAssetDraft(form)));
    } catch (_) {
        // El borrador local es una ayuda; el guardado principal sigue siendo el formulario.
    }
};

const readAssetDraft = (form, id = null) => {
    try {
        const raw = window.localStorage.getItem(assetDraftKey(form, id));
        return raw ? JSON.parse(raw) : null;
    } catch (_) {
        return null;
    }
};

const nonEmptyDraftValues = (values = {}) => Object.entries(values || {}).reduce((carry, [key, value]) => {
    if (String(value ?? '').trim() !== '') {
        carry[key] = value;
    }
    return carry;
}, {});

const mergeAssetDraft = (row, draft) => {
    if (!draft) {
        return row;
    }
    if (!row) {
        return {
            ...(draft.fields || {}),
            detalle: draft.detalle || {},
            participaciones: draft.participaciones || [],
            fiducia_beneficiarios: draft.fiducia_beneficiarios || [],
            subunidades: draft.subunidades || [],
            valoraciones_anuales: draft.valoraciones_anuales || [],
            ingresos_anuales: draft.ingresos_anuales || [],
            gastos_anuales: draft.gastos_anuales || [],
            seguro_polizas: draft.seguro_polizas || [],
            seguro_coberturas: draft.seguro_coberturas || [],
            seguro_equipos: draft.seguro_equipos || [],
            seguro_movimientos: draft.seguro_movimientos || [],
        };
    }
    const draftFields = nonEmptyDraftValues(draft.fields || {});
    delete draftFields.tipo_activo;
    return {
        ...row,
        ...draftFields,
        detalle: {
            ...(row.detalle || {}),
            ...nonEmptyDraftValues(draft.detalle || {}),
        },
        participaciones: Array.isArray(draft.participaciones) && draft.participaciones.length > 0 ? draft.participaciones : row.participaciones || [],
        fiducia_beneficiarios: Array.isArray(draft.fiducia_beneficiarios) && draft.fiducia_beneficiarios.length > 0 ? draft.fiducia_beneficiarios : row.fiducia_beneficiarios || [],
        subunidades: Array.isArray(draft.subunidades) && draft.subunidades.length > 0 ? draft.subunidades : row.subunidades || [],
        valoraciones_anuales: Array.isArray(draft.valoraciones_anuales) && draft.valoraciones_anuales.length > 0 ? draft.valoraciones_anuales : row.valoraciones_anuales || [],
        ingresos_anuales: Array.isArray(draft.ingresos_anuales) && draft.ingresos_anuales.length > 0 ? draft.ingresos_anuales : row.ingresos_anuales || [],
        gastos_anuales: Array.isArray(draft.gastos_anuales) && draft.gastos_anuales.length > 0 ? draft.gastos_anuales : row.gastos_anuales || [],
        seguro_polizas: Array.isArray(draft.seguro_polizas) && draft.seguro_polizas.length > 0 ? draft.seguro_polizas : row.seguro_polizas || [],
        seguro_coberturas: Array.isArray(draft.seguro_coberturas) && draft.seguro_coberturas.length > 0 ? draft.seguro_coberturas : row.seguro_coberturas || [],
        seguro_equipos: Array.isArray(draft.seguro_equipos) && draft.seguro_equipos.length > 0 ? draft.seguro_equipos : row.seguro_equipos || [],
        seguro_movimientos: Array.isArray(draft.seguro_movimientos) && draft.seguro_movimientos.length > 0 ? draft.seguro_movimientos : row.seguro_movimientos || [],
    };
};

const clearAssetDraft = (form, id = null) => {
    try {
        window.localStorage.removeItem(assetDraftKey(form, id));
    } catch (_) {
        // Sin accion: si el navegador bloquea storage, no afecta el guardado real.
    }
};

const assetNumeric = (value) => {
    const raw = String(value ?? '').replace(/[$\s]/g, '');
    if (!raw) return 0;
    if (raw.includes(',')) {
        return Number(raw.replace(/\./g, '').replace(',', '.')) || 0;
    }
    if ((raw.match(/\./g) || []).length > 1 || /\.\d{3}$/.test(raw)) {
        return Number(raw.replace(/\./g, '')) || 0;
    }
    return Number(raw) || 0;
};

const syncAccountingTotal = (form) => {
    const land = form.elements['detalle[valor_contable_terreno]'];
    const building = form.elements['detalle[valor_contable_construccion]'];
    const total = form.elements['detalle[valor_contable_total]'];
    if (!(land instanceof HTMLInputElement) || !(building instanceof HTMLInputElement) || !(total instanceof HTMLInputElement)) {
        return;
    }
    const sum = assetNumeric(land.value) + assetNumeric(building.value);
    if (sum <= 0) {
        return;
    }
    const current = assetNumeric(total.value);
    const previous = Number(total.dataset.autoValue || '0');
    if (!total.value || current === previous) {
        total.value = new Intl.NumberFormat('es-CO', { maximumFractionDigits: 0 }).format(sum);
        total.dataset.autoValue = String(sum);
    }
};

const setAutoMoney = (input, value) => {
    if (!(input instanceof HTMLInputElement) || !Number.isFinite(value) || value < 0) {
        return;
    }
    const current = assetNumeric(input.value);
    const previous = Number(input.dataset.autoValue || '0');
    if (!input.value || current === previous) {
        input.value = new Intl.NumberFormat('es-CO', { maximumFractionDigits: 0 }).format(value);
        input.dataset.autoValue = String(value);
    }
};

const insuranceAssetOptions = [
    { key: 'construccion', label: 'Edificio / construccion', category: 'Construccion', coverage: 'Incendio', unit: 'm2' },
    { key: 'vidrios', label: 'Vidrios', category: 'Vidrios', coverage: 'Incendio', unit: 'gl' },
    { key: 'muebles_enseres', label: 'Muebles y enseres', category: 'Muebles y enseres', coverage: 'Incendio', unit: 'gl' },
    { key: 'maquinaria_equipo', label: 'Maquinaria y equipo', category: 'Maquinaria y equipo', coverage: 'Rotura de maquinaria', unit: 'und' },
    { key: 'corriente_debil', label: 'Corriente debil / equipo electronico', category: 'Equipo electronico / corriente debil', coverage: 'Equipo electronico / corriente debil', unit: 'und' },
    { key: 'mercancias', label: 'Mercancias / inventario', category: 'Mercancias / inventario', coverage: 'Incendio', unit: 'gl' },
    { key: 'obras_arte', label: 'Obras de arte', category: 'Obras de arte', coverage: 'Incendio', unit: 'und' },
    { key: 'joyas', label: 'Joyas', category: 'Joyas', coverage: 'Hurto', unit: 'und' },
    { key: 'dinero_efectivo', label: 'Dinero en efectivo', category: 'Dinero en efectivo', coverage: 'Hurto', unit: 'gl' },
];

const propertyDamageAssetCategories = ['Construccion', 'Vidrios', 'Muebles y enseres', 'Maquinaria y equipo', 'Equipo electronico / corriente debil', 'Mercancias / inventario', 'Obras de arte'];
const propertyDamageCoreCategories = propertyDamageAssetCategories.filter((category) => category !== 'Obras de arte');

const assetCoverageValueRules = {
    Incendio: propertyDamageAssetCategories,
    Rayo: propertyDamageCoreCategories,
    Explosion: propertyDamageCoreCategories,
    Humo: propertyDamageCoreCategories,
    'Danos por agua': propertyDamageCoreCategories,
    Anegacion: propertyDamageCoreCategories,
    Inundacion: propertyDamageCoreCategories,
    Terremoto: propertyDamageAssetCategories,
    'Temblor / erupcion volcanica': propertyDamageAssetCategories,
    'Actos mal intencionados de terceros': propertyDamageCoreCategories,
    Terrorismo: propertyDamageCoreCategories,
    'Vidrios': ['Vidrios'],
    'Gastos de extincion': ['Construccion'],
    'Remocion de escombros': ['Construccion'],
    'Honorarios profesionales': ['Construccion'],
    'Rotura de maquinaria': ['Maquinaria y equipo'],
    'Dano interno': ['Maquinaria y equipo', 'Equipo electronico / corriente debil'],
    'Equipo electronico / corriente debil': ['Equipo electronico / corriente debil'],
    Hurto: ['Muebles y enseres', 'Maquinaria y equipo', 'Equipo electronico / corriente debil', 'Mercancias / inventario', 'Obras de arte', 'Joyas', 'Dinero en efectivo'],
    Sustraccion: ['Muebles y enseres', 'Maquinaria y equipo', 'Equipo electronico / corriente debil', 'Mercancias / inventario', 'Obras de arte', 'Joyas', 'Dinero en efectivo'],
    'Lucro cesante': ['Construccion', 'Maquinaria y equipo', 'Equipo electronico / corriente debil', 'Mercancias / inventario'],
    'Arrendamiento dejado de percibir': ['Construccion'],
    'Perdida de arrendamiento por siniestro': ['Construccion'],
    'Danos propios': ['Maquinaria y equipo', 'Equipo electronico / corriente debil'],
    'Perdida parcial por danos': ['Maquinaria y equipo'],
    'Perdida total por danos': ['Maquinaria y equipo'],
    'Perdida parcial por hurto': ['Maquinaria y equipo'],
    'Perdida total por hurto': ['Maquinaria y equipo'],
    'Asistencia juridica': [],
    'Asistencia': [],
};

const coverageValueCategoriesFor = (coverage) => {
    if (assetCoverageValueRules[coverage]) {
        return assetCoverageValueRules[coverage];
    }
    const text = String(coverage || '').toLowerCase();
    if (text.includes('responsabilidad') || text.includes('tercero') || text.includes('lesion') || text.includes('muerte') || text.includes('defensa')) {
        return [];
    }
    if (text.includes('terremoto') || text.includes('temblor') || text.includes('catastro')) {
        return propertyDamageAssetCategories;
    }
    if (text.includes('incendio') || text.includes('rayo') || text.includes('explosion') || text.includes('humo') || text.includes('agua') || text.includes('inundacion') || text.includes('anegacion')) {
        return propertyDamageCoreCategories;
    }
    if (text.includes('vidrio')) {
        return ['Vidrios'];
    }
    if (text.includes('maquinaria') || text.includes('rotura')) {
        return ['Maquinaria y equipo'];
    }
    if (text.includes('electronico') || text.includes('corriente') || text.includes('debil') || text.includes('ups') || text.includes('cctv')) {
        return ['Equipo electronico / corriente debil'];
    }
    if (text.includes('hurto') || text.includes('sustraccion')) {
        return ['Muebles y enseres', 'Maquinaria y equipo', 'Equipo electronico / corriente debil', 'Mercancias / inventario', 'Obras de arte', 'Joyas', 'Dinero en efectivo'];
    }
    if (text.includes('lucro') || text.includes('arrendamiento') || text.includes('canon')) {
        return ['Construccion', 'Mercancias / inventario', 'Maquinaria y equipo', 'Equipo electronico / corriente debil'];
    }
    if (text.includes('perdida') || text.includes('vehiculo') || text.includes('auto')) {
        return ['Vehiculo'];
    }
    return [];
};

const insuranceCoverageProfiles = {
    'Danos materiales inmueble arrendado': ['Incendio', 'Rayo', 'Explosion', 'Humo', 'Danos por agua', 'Anegacion', 'Terremoto', 'Temblor / erupcion volcanica', 'Vidrios', 'Extension de cobertura', 'Actos mal intencionados de terceros', 'Terrorismo', 'Remocion de escombros', 'Gastos de extincion', 'Honorarios profesionales', 'Responsabilidad civil extracontractual', 'Asistencia'],
    'Todo riesgo dano material': ['Incendio', 'Rayo', 'Explosion', 'Humo', 'Danos por agua', 'Anegacion', 'Inundacion', 'Terremoto', 'Temblor / erupcion volcanica', 'Hurto', 'Sustraccion', 'Actos mal intencionados de terceros', 'Terrorismo', 'Rotura de maquinaria', 'Equipo electronico / corriente debil', 'Lucro cesante', 'Perdida de arrendamiento por siniestro', 'Remocion de escombros', 'Gastos de extincion', 'Honorarios profesionales', 'Asistencia'],
    'Incendio y terremoto': ['Incendio', 'Rayo', 'Explosion', 'Humo', 'Danos por agua', 'Anegacion', 'Terremoto', 'Temblor / erupcion volcanica', 'Actos mal intencionados de terceros', 'Terrorismo', 'Remocion de escombros', 'Gastos de extincion', 'Lucro cesante', 'Perdida de arrendamiento por siniestro', 'Asistencia'],
    Incendio: ['Incendio', 'Rayo', 'Explosion', 'Humo', 'Danos por agua', 'Anegacion', 'Actos mal intencionados de terceros', 'Terrorismo', 'Remocion de escombros', 'Gastos de extincion', 'Lucro cesante', 'Perdida de arrendamiento por siniestro', 'Asistencia'],
    Terremoto: ['Terremoto', 'Temblor / erupcion volcanica', 'Anegacion', 'Remocion de escombros', 'Perdida de arrendamiento por siniestro'],
    'Muebles y enseres': ['Incendio', 'Rayo', 'Explosion', 'Danos por agua', 'Hurto', 'Sustraccion', 'Actos mal intencionados de terceros', 'Terrorismo'],
    'Rotura de maquinaria': ['Rotura de maquinaria', 'Dano interno', 'Actos mal intencionados de terceros', 'Gastos adicionales'],
    'Maquinaria y equipo': ['Rotura de maquinaria', 'Dano interno', 'Hurto', 'Sustraccion', 'Incendio'],
    'Equipo electronico / corriente debil': ['Equipo electronico / corriente debil', 'Dano interno', 'Hurto', 'Sustraccion', 'Incendio', 'Danos por agua', 'Gastos adicionales'],
    'Responsabilidad civil extracontractual': ['Responsabilidad civil extracontractual', 'RC predios labores y operaciones', 'RC patronal', 'RC cruzada', 'RC parqueaderos', 'Gastos medicos', 'Asistencia juridica'],
    'Responsabilidad civil profesional': ['Responsabilidad civil profesional', 'Gastos medicos', 'Asistencia juridica'],
    'Responsabilidad civil contractual': ['Responsabilidad civil contractual', 'Asistencia juridica'],
    'Automovil todo riesgo': ['Danos propios', 'Perdida parcial por danos', 'Perdida total por danos', 'Perdida parcial por hurto', 'Perdida total por hurto', 'Responsabilidad civil extracontractual', 'Asistencia juridica', 'Asistencia', 'Gastos de grua'],
    SOAT: ['Responsabilidad civil extracontractual'],
    Hogar: ['Incendio', 'Rayo', 'Explosion', 'Danos por agua', 'Terremoto', 'Hurto', 'Sustraccion', 'Responsabilidad civil extracontractual', 'Perdida de arrendamiento por siniestro', 'Asistencia'],
    Copropiedad: ['Incendio', 'Rayo', 'Explosion', 'Danos por agua', 'Terremoto', 'Responsabilidad civil extracontractual', 'Actos mal intencionados de terceros', 'Terrorismo', 'Rotura de maquinaria', 'Equipo electronico / corriente debil'],
    Cumplimiento: ['Responsabilidad civil contractual', 'Seriedad de oferta', 'Cumplimiento del contrato', 'Buen manejo del anticipo', 'Calidad del servicio', 'Pago de salarios y prestaciones'],
    'Cumplimiento arrendamiento': ['Responsabilidad civil contractual', 'Canon de arrendamiento', 'Cuotas de administracion', 'Servicios publicos', 'Clausula penal'],
    Manejo: ['Hurto', 'Sustraccion'],
    Deudores: ['Responsabilidad civil contractual'],
    'Cyber / riesgo digital': ['Responsabilidad civil profesional', 'Lucro cesante', 'Asistencia juridica'],
    'Lucro cesante': ['Lucro cesante', 'Perdida de arrendamiento por siniestro'],
};

const insuranceCoverageAcademy = {
    Incendio: { kind: 'Dano material', assets: ['Construccion', 'Muebles y enseres', 'Maquinaria y equipo', 'Equipo electronico / corriente debil', 'Mercancias / inventario'], basis: 'Valor de reposicion por bien asegurado, sin incluir terreno.', review: 'Que la ubicacion, uso, construccion y bienes declarados coincidan con la poliza.' },
    Rayo: { kind: 'Dano material', assets: ['Construccion', 'Equipo electronico / corriente debil', 'Maquinaria y equipo'], basis: 'Valor de reposicion del bien afectado.', review: 'Si existe exclusion o condicion de protecciones electricas/pararrayos.' },
    Explosion: { kind: 'Dano material', assets: ['Construccion', 'Muebles y enseres', 'Maquinaria y equipo', 'Mercancias / inventario'], basis: 'Valor de reposicion del edificio y contenidos.', review: 'Actividad del riesgo, sustancias inflamables y exclusiones.' },
    Humo: { kind: 'Dano material', assets: ['Construccion', 'Muebles y enseres', 'Mercancias / inventario'], basis: 'Valor de reposicion de bienes declarados.', review: 'Alcance frente a humo accidental, chimeneas, cocina o actividad comercial.' },
    'Danos por agua': { kind: 'Dano material', assets: ['Construccion', 'Muebles y enseres', 'Maquinaria y equipo', 'Equipo electronico / corriente debil', 'Mercancias / inventario'], basis: 'Valor de reposicion de bienes expuestos al agua.', review: 'Tuberias, cubiertas, mantenimiento, filtraciones y deducible.' },
    Anegacion: { kind: 'Dano material', assets: ['Construccion', 'Muebles y enseres', 'Mercancias / inventario', 'Equipo electronico / corriente debil'], basis: 'Valor de reposicion de bienes ubicados en zonas expuestas.', review: 'Altura del primer piso, drenajes, zonas inundables y exclusiones.' },
    Inundacion: { kind: 'Dano material', assets: ['Construccion', 'Muebles y enseres', 'Mercancias / inventario'], basis: 'Valor de reposicion soportado por avaluo o inventario.', review: 'Mapa de amenaza, antecedentes, obras de mitigacion y sublimites.' },
    Terremoto: { kind: 'Catastrofico', assets: propertyDamageAssetCategories, basis: 'Valor asegurable de reposicion, normalmente con deducible especial.', review: 'Deducible por evento, norma sismo resistente, PH y separacion de terreno.' },
    'Temblor / erupcion volcanica': { kind: 'Catastrofico', assets: propertyDamageAssetCategories, basis: 'Valor de reposicion de bienes declarados.', review: 'Zona geografica, sublimites y definicion del evento.' },
    Hurto: { kind: 'Sustraccion', assets: ['Muebles y enseres', 'Maquinaria y equipo', 'Equipo electronico / corriente debil', 'Mercancias / inventario', 'Obras de arte', 'Joyas', 'Dinero en efectivo'], basis: 'Relacion valorizada por item, con seriales, facturas o cotizaciones.', review: 'Tipo de hurto cubierto, protecciones exigidas, dinero y bienes excluidos.' },
    Sustraccion: { kind: 'Sustraccion', assets: ['Muebles y enseres', 'Maquinaria y equipo', 'Equipo electronico / corriente debil', 'Mercancias / inventario', 'Obras de arte', 'Joyas', 'Dinero en efectivo'], basis: 'Inventario detallado con valor de reposicion o limite pactado.', review: 'Si exige violencia, cerraduras, alarma, vigilancia o denuncia.' },
    'Actos mal intencionados de terceros': { kind: 'Dano material', assets: ['Construccion', 'Vidrios', 'Muebles y enseres', 'Maquinaria y equipo', 'Equipo electronico / corriente debil'], basis: 'Valor de reposicion de los bienes expuestos.', review: 'Exclusiones por terrorismo, asonada, motin o eventos politicos.' },
    Terrorismo: { kind: 'Dano material', assets: ['Construccion', 'Vidrios', 'Muebles y enseres', 'Maquinaria y equipo', 'Equipo electronico / corriente debil'], basis: 'Valor asegurado del edificio y contenidos.', review: 'Deducible y sublimite catastrofico.' },
    Vidrios: { kind: 'Dano material', assets: ['Vidrios'], basis: 'Relacion de vidrios por medida, tipo y valor de reposicion.', review: 'Fachadas, vitrinas, divisiones, espejos, avisos y deducible.' },
    'Extension de cobertura': { kind: 'Adicional', assets: ['Construccion', 'Muebles y enseres', 'Maquinaria y equipo', 'Equipo electronico / corriente debil'], basis: 'Segun el amparo extendido y su sublimite.', review: 'Que no sea una frase generica: identificar amparo, limite y deducible.' },
    'Remocion de escombros': { kind: 'Gasto adicional', assets: ['Construccion'], basis: 'Sublimite o porcentaje del valor asegurado de edificio.', review: 'Si cubre demolicion, retiro, botadero y permisos.' },
    'Gastos de extincion': { kind: 'Gasto adicional', assets: ['Construccion'], basis: 'Sublimite pactado o porcentaje de la suma asegurada.', review: 'Bomberos, materiales, reposicion de extintores y soportes.' },
    'Honorarios profesionales': { kind: 'Gasto adicional', assets: ['Construccion'], basis: 'Sublimite para arquitectos, ingenieros, interventoria o peritos.', review: 'Si aplica para reconstruccion y ajuste de perdida.' },
    'Rotura de maquinaria': { kind: 'Equipo productivo', assets: ['Maquinaria y equipo'], basis: 'Item por item: descripcion, serial, valor de reposicion, fuente y fecha de consulta.', review: 'Dano interno, mantenimiento, antiguedad, garantias, exclusiones y demerito.' },
    'Dano interno': { kind: 'Equipo productivo', assets: ['Maquinaria y equipo', 'Equipo electronico / corriente debil'], basis: 'Valor de reposicion soportado por cotizacion/factura.', review: 'Si el dano interno esta cubierto o excluido por desgaste, mantenimiento o fabricante.' },
    'Equipo electronico / corriente debil': { kind: 'Equipo electronico', assets: ['Equipo electronico / corriente debil'], basis: 'Inventario por item: UPS, CCTV, red, telefonia, computadores, controles, fuente y fecha.', review: 'Protecciones electricas, mantenimiento, portabilidad, software y datos excluidos.' },
    'Lucro cesante': { kind: 'Perdida financiera', assets: ['Construccion', 'Maquinaria y equipo', 'Equipo electronico / corriente debil', 'Mercancias / inventario'], basis: 'Ingresos, canon, utilidad bruta o gastos permanentes soportados.', review: 'Periodo de indemnizacion, evento detonante, base contable y deducible temporal.' },
    'Arrendamiento dejado de percibir': { kind: 'Perdida financiera', assets: ['Construccion'], basis: 'Canon mensual, contrato, porcentaje de participacion y meses cubiertos.', review: 'Si aplica por imposibilidad fisica del inmueble y periodo maximo.' },
    'Perdida de arrendamiento por siniestro': { kind: 'Perdida financiera', assets: ['Construccion'], basis: 'Suma canon mensual por unidad x meses de indemnizacion solicitados x porcentaje de participacion si aplica.', review: 'Debe depender de un siniestro cubierto; confirmar periodo maximo, limite asegurado, deducible, indice variable/IPC pactado y que no sea seguro de arrendamiento por mora.' },
    'Responsabilidad civil extracontractual': { kind: 'Responsabilidad', assets: ['Exposicion frente a terceros'], basis: 'Limite por evento/vigencia definido por actividad, visitantes, contratos y capacidad de perdida.', review: 'RC predios, labores, operaciones, patronal, cruzada, parqueaderos, contratistas y exclusiones.' },
    'RC predios labores y operaciones': { kind: 'Responsabilidad', assets: ['Exposicion frente a terceros'], basis: 'Limite por evento/vigencia.', review: 'Que cubra danos a terceros por uso del predio y operacion normal.' },
    'RC patronal': { kind: 'Responsabilidad', assets: ['Trabajadores y contratistas'], basis: 'Sublimite por trabajador/evento.', review: 'Compatibilidad con seguridad social y exclusiones laborales.' },
    'RC cruzada': { kind: 'Responsabilidad', assets: ['Contratistas y terceros en obra/operacion'], basis: 'Sublimite por evento.', review: 'Si varios asegurados se consideran terceros entre si.' },
    'RC parqueaderos': { kind: 'Responsabilidad', assets: ['Vehiculos de terceros bajo custodia o parqueo'], basis: 'Limite por vehiculo/evento/vigencia.', review: 'Si hay custodia, valet, vigilancia y exclusiones por objetos dentro del vehiculo.' },
    'Responsabilidad civil profesional': { kind: 'Responsabilidad', assets: ['Servicios profesionales'], basis: 'Limite por reclamacion/vigencia segun contratos y exposicion.', review: 'Retroactividad, claims made, errores u omisiones, defensa y exclusiones.' },
    'Responsabilidad civil contractual': { kind: 'Responsabilidad', assets: ['Obligaciones contractuales'], basis: 'Valor del contrato o limite exigido.', review: 'Que el amparo corresponda a la obligacion real y no sustituya cumplimiento si aplica.' },
    'Gastos medicos': { kind: 'Asistencia / RC', assets: ['Personas afectadas'], basis: 'Sublimite por persona/evento.', review: 'Si opera sin declaracion de responsabilidad y plazo de reclamacion.' },
    'Asistencia juridica': { kind: 'Asistencia / defensa', assets: ['Defensa legal'], basis: 'Sublimite o servicio incluido.', review: 'Si incluye defensa penal/civil, conductor, apoderado y gastos procesales.' },
    'Danos propios': { kind: 'Vehiculo', assets: ['Vehiculo y accesorios'], basis: 'Valor comercial o fasecolda/cotizacion segun vehiculo.', review: 'Deducible, accesorios, uso, conductor y exclusiones.' },
    'Perdida parcial por danos': { kind: 'Vehiculo', assets: ['Vehiculo y accesorios'], basis: 'Valor asegurado del vehiculo.', review: 'Deducible por evento y talleres autorizados.' },
    'Perdida total por danos': { kind: 'Vehiculo', assets: ['Vehiculo y accesorios'], basis: 'Valor comercial asegurado.', review: 'Umbral de perdida total y documentos requeridos.' },
    'Perdida parcial por hurto': { kind: 'Vehiculo', assets: ['Vehiculo y accesorios'], basis: 'Valor comercial de partes/accesorios declarados.', review: 'Protecciones, parqueo, accesorios no originales y denuncia.' },
    'Perdida total por hurto': { kind: 'Vehiculo', assets: ['Vehiculo'], basis: 'Valor comercial asegurado.', review: 'Procedimiento, plazo de espera y transferencia de propiedad.' },
    'Gastos de grua': { kind: 'Asistencia', assets: ['Vehiculo'], basis: 'Sublimite o eventos incluidos.', review: 'Distancia, numero de eventos y proveedor.' },
    Asistencia: { kind: 'Asistencia', assets: ['Servicio asociado al activo'], basis: 'Sublimite o eventos por vigencia.', review: 'Alcance real del servicio y exclusiones.' },
    'Seriedad de oferta': { kind: 'Cumplimiento', assets: ['Oferta / licitacion'], basis: 'Porcentaje o valor exigido en pliego.', review: 'Vigencia, entidad beneficiaria y condiciones de ejecucion.' },
    'Cumplimiento del contrato': { kind: 'Cumplimiento', assets: ['Contrato'], basis: 'Porcentaje o valor contractual exigido.', review: 'Objeto, plazo, prorroga y beneficiario.' },
    'Buen manejo del anticipo': { kind: 'Cumplimiento', assets: ['Anticipo'], basis: 'Valor del anticipo entregado.', review: 'Amortizacion, manejo separado y soportes.' },
    'Calidad del servicio': { kind: 'Cumplimiento', assets: ['Servicio contratado'], basis: 'Porcentaje o valor pactado.', review: 'Periodo posterior a la entrega y obligaciones especificas.' },
    'Pago de salarios y prestaciones': { kind: 'Cumplimiento', assets: ['Personal vinculado al contrato'], basis: 'Porcentaje o valor contractual exigido.', review: 'Vigencia adicional y obligaciones laborales.' },
    'Canon de arrendamiento': { kind: 'Arrendamiento', assets: ['Contrato de arrendamiento'], basis: 'Canon mensual por meses cubiertos.', review: 'Mora, clausula penal, codeudores y requisitos de reclamacion.' },
    'Cuotas de administracion': { kind: 'Arrendamiento', assets: ['Contrato / PH'], basis: 'Valor mensual de administracion por meses cubiertos.', review: 'Responsable contractual y certificado de administracion.' },
    'Servicios publicos': { kind: 'Arrendamiento', assets: ['Servicios del inmueble'], basis: 'Promedio mensual o limite pactado.', review: 'Titularidad de cuentas y lectura final.' },
    'Clausula penal': { kind: 'Arrendamiento', assets: ['Contrato de arrendamiento'], basis: 'Valor pactado en el contrato.', review: 'Exigibilidad, proporcionalidad y soporte contractual.' },
    Otra: { kind: 'Por definir', assets: ['Por definir'], basis: 'Documentar alcance, limite, fuente y deducible.', review: 'No dejar como texto generico.' },
    'Por confirmar': { kind: 'Pendiente', assets: ['Pendiente'], basis: 'Pendiente de definir con clausulado/cotizacion.', review: 'No contratar sin confirmar alcance.' },
};

const insurancePolicyPdfFieldMatrix = [
    {
        ramos: ['Incendio', 'Incendio y terremoto', 'Todo riesgo dano material', 'Danos materiales inmueble arrendado', 'Hogar', 'Copropiedad'],
        cobertura: 'Incendio y coberturas aliadas',
        objeto: 'Edificio(s), vidrios, maquinaria y equipo, muebles, enseres, contenidos o mercancias declaradas.',
        campos: 'Objeto asegurable, suma asegurada, % indice variable, sublimite si existe y deducible (% perdida + minimo SMMLV).',
        fuente: 'Caratula: AMPARO / OBJETO / SUMA ASEGURADA / % INVAR / SUBLIMITE / DEDUCIBLES.',
        alerta: 'No incluir terreno. Separar construccion, vidrios, equipos y contenidos para evitar infra o supraseguro.',
    },
    {
        ramos: ['Incendio', 'Todo riesgo dano material', 'Danos materiales inmueble arrendado', 'Copropiedad'],
        cobertura: 'Asonada, motin, conmocion civil, huelga, AMIT y terrorismo',
        objeto: 'Los mismos bienes declarados en incendio cuando la poliza extiende esos eventos.',
        campos: 'Suma asegurada por objeto, % indice variable, sublimite, deducible y si opera como extension o cobertura separada.',
        fuente: 'Caratula y clausulado de condiciones particulares.',
        alerta: 'Validar si terrorismo/AMIT tiene deducible o sublimite distinto al incendio basico.',
    },
    {
        ramos: ['Terremoto', 'Incendio y terremoto', 'Todo riesgo dano material', 'Hogar', 'Copropiedad'],
        cobertura: 'Terremoto, temblor, erupcion volcanica y eventos de la naturaleza',
        objeto: 'Construccion y contenidos expresamente asegurados frente a evento catastrofico.',
        campos: 'Suma asegurada, % indice variable, deducible especial, minimo SMMLV y sublimite si aplica.',
        fuente: 'Caratula, cuadro de amparos y clausulado de eventos de la naturaleza.',
        alerta: 'Usar valor de reposicion sin terreno. Revisar si varios eventos dentro de 72 horas cuentan como un solo siniestro.',
    },
    {
        ramos: ['Vidrios', 'Incendio', 'Todo riesgo dano material', 'Hogar', 'Copropiedad'],
        cobertura: 'Vidrios',
        objeto: 'Vidrios instalados, vitrinas, divisiones, fachadas o elementos equivalentes declarados.',
        campos: 'Suma asegurada, % indice variable, deducible, medidas m2/ml y fuente del valor de reposicion.',
        fuente: 'Caratula y relacion de vidrios valorizada.',
        alerta: 'La suma debe salir de area/tipo de vidrio por valor de reposicion e instalacion, no de una cifra global.',
    },
    {
        ramos: ['Responsabilidad Civil', 'Responsabilidad civil extracontractual', 'Todo riesgo dano material', 'Incendio'],
        cobertura: 'Responsabilidad civil extracontractual',
        objeto: 'Patrimonio del asegurado por danos materiales, lesiones, muerte o perjuicios causados a terceros.',
        campos: 'Limite evento/vigencia, sublimitaciones, deducible, gastos medicos, defensa, patronal, cruzada y parqueaderos si aplican.',
        fuente: 'Caratula: limite contratado; clausulado: amparos adicionales y sublimitados.',
        alerta: 'No sale de inventario de bienes. Debe definirse por actividad, predio, visitantes, empleados, contratistas y contratos.',
    },
    {
        ramos: ['Responsabilidad Civil', 'Responsabilidad civil extracontractual'],
        cobertura: 'Gastos medicos / gastos de defensa / pagos suplementarios / patronal',
        objeto: 'Sublimites dentro del basico de RCE, no necesariamente adicionales a la suma principal.',
        campos: 'Porcentaje por evento, porcentaje por vigencia, limite principal afectado, deducible y condicion de procedencia.',
        fuente: 'Clausulado de Responsabilidad Civil: amparos adicionales sublimitados.',
        alerta: 'Registrar si el sublimite consume la suma de RCE o si opera en adicion; esa diferencia cambia la comparacion.',
    },
    {
        ramos: ['Sustraccion', 'Todo riesgo dano material', 'Hogar', 'Manejo'],
        cobertura: 'Hurto calificado / sustraccion',
        objeto: 'Contenidos, maquinaria, equipo, mercancias, obras, joyas o dinero declarados.',
        campos: 'Suma asegurada por categoria, deducible, protecciones exigidas, ubicacion y bienes excluidos.',
        fuente: 'Caratula, clausulado y relacion valorizada de bienes.',
        alerta: 'Confirmar modalidad cubierta: hurto simple, hurto calificado, violencia, cerraduras, alarma o vigilancia.',
    },
    {
        ramos: ['Montaje y Rotura de Maquinaria', 'Rotura de maquinaria', 'Maquinaria y equipo', 'Todo riesgo dano material'],
        cobertura: 'Rotura de maquinaria / dano interno',
        objeto: 'Maquinas, motobombas, equipos mecanicos, equipos auxiliares y sistemas declarados.',
        campos: 'Item, descripcion, serial, valor de reposicion, fuente, fecha de consulta, deducible y demerito si aplica.',
        fuente: 'Caratula, clausulado, inventario valorizado, facturas, cotizaciones y mantenimiento.',
        alerta: 'No aceptar suma global sin relacion. Revisar desgaste, mantenimiento, garantia, antiguedad y exclusiones.',
    },
    {
        ramos: ['Corriente Debil', 'Equipo electronico / corriente debil', 'Todo riesgo dano material'],
        cobertura: 'Equipo electronico / corriente debil',
        objeto: 'UPS, CCTV, redes, servidores, computadores, controles de acceso, alarmas y equipos electronicos.',
        campos: 'Inventario por grupo o item, valor de reposicion, serial si aplica, fuente, fecha, deducible y alcance de dano interno.',
        fuente: 'Relacion de equipos, factura/cotizacion, clausulado y condiciones particulares.',
        alerta: 'Separar hardware, software, datos, portabilidad y dano electrico; no todo queda cubierto automaticamente.',
    },
    {
        ramos: ['Lucro Cesante', 'Lucro cesante', 'Incendio', 'Todo riesgo dano material', 'Hogar'],
        cobertura: 'Perdida de arrendamiento por siniestro',
        objeto: 'Canones que el propietario deja de recibir cuando un siniestro cubierto impide arrendar o usar el inmueble.',
        campos: 'Canon mensual por unidad, numero de meses, porcentaje de participacion, limite asegurado, deducible temporal e indice pactado.',
        fuente: 'Contratos de arrendamiento, historial de canon, subunidades, clausulado y caratula.',
        alerta: 'No confundir con seguro de arrendamiento por mora ni con gastos de alojamiento temporal.',
    },
];

const insuranceProductAcademy = {
    'Danos materiales inmueble arrendado': {
        appliesTo: ['Inmueble'],
        title: 'Danos materiales para inmueble arrendado',
        what: 'Protege la construccion y los bienes declarados frente a danos fisicos accidentales, subitos e imprevistos que afecten la continuidad del inmueble arrendado.',
        includes: 'Incendio, terremoto, anegacion, AMIT, terrorismo, vidrios, gastos adicionales, asistencia y RCE cuando la poliza lo contrata.',
        assets: 'Construccion sin terreno, mejoras, vidrios, contenidos del propietario y equipos entregados al arrendatario.',
        value: 'La construccion se calcula a valor de reposicion: m2 asegurables por valor de reposicion por m2. Los contenidos salen de inventario o cotizacion.',
        analyst: 'Verificar titularidad/interes asegurable, uso del inmueble, contrato de arrendamiento, valor de reposicion, exclusiones y quien asume cada riesgo.',
        supports: 'Matricula, predial/catastro, areas, contrato, avaluo o fuente de reposicion, relacion de bienes, caratula y clausulado.',
    },
    'Todo riesgo dano material': {
        appliesTo: ['Inmueble', 'Vehiculo/Maquinaria/Equipo'],
        title: 'Todo riesgo dano material',
        what: 'Ampara danos fisicos a bienes asegurados salvo exclusiones. Es mas amplio que nombrar un solo evento, pero siempre depende del clausulado.',
        includes: 'Incendio, explosion, danos por agua, eventos naturales, AMIT, hurto calificado, dano interno o adicionales si fueron contratados.',
        assets: 'Edificios, contenidos, maquinaria, equipos, inventarios o mejoras, segun la declaracion de la poliza.',
        value: 'Debe partir del valor asegurable total o de reposicion por cada articulo asegurado, separado por ubicacion y tipo de bien.',
        analyst: 'No aceptar valores globales sin desglose; revisar exclusiones, garantias, bienes excluidos, sublimites y aplicacion de infraseguro.',
        supports: 'Relacion valorizada, avaluo, facturas, cotizaciones, ubicacion del riesgo, clausulado, caratula y deducibles.',
    },
    'Incendio y terremoto': {
        appliesTo: ['Inmueble'],
        title: 'Incendio y terremoto',
        what: 'Cubre danos materiales por incendio y por terremoto o eventos sismicos/naturales asociados, segun condiciones de la poliza.',
        includes: 'Incendio, rayo, humo, explosion y terremoto; puede sumar anegacion, AMIT, asistencia o gastos adicionales.',
        assets: 'Principalmente construccion; tambien contenidos si se declaran expresamente.',
        value: 'Construccion a valor de reposicion sin terreno. Contenidos por relacion detallada y fuente de consulta.',
        analyst: 'Validar si existe hipoteca o PH, porque puede haber exigencia de incendio/terremoto. Confirmar deducible de terremoto por articulo afectado.',
        supports: 'Matricula, certificado de deuda si hay hipoteca, areas, fuente de reposicion, caratula y condiciones particulares.',
    },
    Incendio: {
        appliesTo: ['Inmueble'],
        title: 'Incendio',
        what: 'Cubre danos materiales causados por incendio accidental sobre bienes asegurados.',
        includes: 'Puede incluir rayo, calor, humo, explosion y coberturas aliadas si aparecen en caratula o clausulado.',
        assets: 'Construccion, mejoras, muebles, enseres, maquinaria, equipos o mercancias declaradas. El terreno no se asegura.',
        value: 'Valor de reposicion nuevo para construccion y contenidos. En inmuebles: m2 asegurables por valor de reposicion por m2.',
        analyst: 'Confirmar que el bien exista, sea asegurable, tenga fuente de valor y no este subvalorado ni sobrevalorado.',
        supports: 'Avaluo, Construdata/Sispac o fuente equivalente, facturas/cotizaciones, inventario, caratula y condiciones.',
    },
    Terremoto: {
        appliesTo: ['Inmueble'],
        title: 'Terremoto',
        what: 'Ampara danos materiales por terremoto, temblor, erupcion volcanica u otros eventos de la naturaleza definidos en la poliza.',
        includes: 'Puede incluir maremoto, tsunami, marejada, eventos naturales o coberturas aliadas segun el producto.',
        assets: 'Construccion, mejoras y contenidos asegurados si estan declarados.',
        value: 'Valor asegurable de la construccion y contenidos, separado por articulo o ubicacion para aplicar correctamente deducibles.',
        analyst: 'Revisar deducible, minimo en SMMLV, base de aplicacion por articulo afectado y exclusion de terreno.',
        supports: 'Areas, valor reposicion, ubicacion del riesgo, caratula, deducibles y clausulado.',
    },
    Copropiedad: {
        appliesTo: ['Inmueble'],
        title: 'Copropiedad',
        what: 'Protege bienes comunes de una propiedad horizontal y las responsabilidades asociadas a su administracion.',
        includes: 'Incendio, terremoto, danos materiales, RCE, maquinaria comun, equipos electronicos, asistencia y otros amparos segun poliza.',
        assets: 'Bienes comunes, estructura comun, ascensores, equipos, zonas comunes y responsabilidades de la copropiedad.',
        value: 'Valor de reconstruccion o reposicion de bienes comunes y equipos, separado de bienes privados.',
        analyst: 'No confundir seguro de la copropiedad con seguro del bien privado. Revisar acta, administracion, certificado y alcance de bienes comunes.',
        supports: 'Poliza de PH, certificado de administracion, presupuesto/avaluo de bienes comunes, actas y clausulado.',
    },
    Hogar: {
        appliesTo: ['Inmueble'],
        title: 'Hogar',
        what: 'Protege vivienda y contenidos frente a riesgos definidos, usualmente incendio, terremoto, hurto, danos por agua y asistencia.',
        includes: 'Incendio, terremoto, hurto, danos por agua, asistencia domiciliaria y RCE familiar si se contrata.',
        assets: 'Vivienda, mejoras, muebles, enseres, electrodomesticos y contenidos familiares.',
        value: 'Construccion a reposicion y contenidos por inventario o estimacion soportada.',
        analyst: 'Separar construccion de contenidos y verificar si el inmueble es vivienda, arrendado, propio o hipotecado.',
        supports: 'Inventario, avaluo/fuente de reposicion, fotos, factura de bienes relevantes, caratula y clausulado.',
    },
    'Automovil todo riesgo': {
        appliesTo: ['Vehiculo/Maquinaria/Equipo'],
        title: 'Automovil todo riesgo',
        what: 'Protege el vehiculo frente a danos propios, hurto y responsabilidad civil por danos a terceros, segun condiciones.',
        includes: 'Danos parciales y totales, perdida parcial o total por hurto, RCE, asistencia vial, grua, apoyo juridico y coberturas de ocupantes si se contratan.',
        assets: 'Vehiculo identificado por placa, marca, modelo, linea, uso, servicio y accesorios asegurados.',
        value: 'Valor comercial o valor asegurado segun guia, inspeccion, factura o criterio de la aseguradora.',
        analyst: 'Validar uso real, conductor, servicio, accesorios, prenda/leasing, deducibles, exclusiones y asistencia.',
        supports: 'Tarjeta de propiedad, SOAT, tecnomecanica, guia de valor, inspeccion, fotos, caratula y clausulado.',
    },
    SOAT: {
        appliesTo: ['Vehiculo/Maquinaria/Equipo'],
        title: 'SOAT',
        what: 'Seguro obligatorio de accidentes de transito. Atiende victimas de accidentes de transito conforme a la regulacion vigente.',
        includes: 'Gastos medicos, incapacidad, muerte, transporte y otros conceptos definidos por la norma aplicable.',
        assets: 'Vehiculos automotores obligados a circular con SOAT vigente.',
        value: 'La tarifa depende de clase de vehiculo, cilindraje/capacidad, antiguedad y reglas oficiales vigentes.',
        analyst: 'Verificar vigencia, placa, datos del vehiculo y que la renovacion llegue al calendario.',
        supports: 'Certificado SOAT, tarjeta de propiedad y soporte de pago.',
    },
    'Responsabilidad civil extracontractual': {
        appliesTo: ['Inmueble', 'Vehiculo/Maquinaria/Equipo'],
        title: 'Responsabilidad civil extracontractual',
        what: 'Protege el patrimonio del asegurado frente a reclamaciones de terceros por danos materiales, lesiones o muerte causados por su actividad o predio.',
        includes: 'Predios, labores y operaciones, gastos medicos, defensa, patronal, cruzada, parqueaderos, contaminacion accidental o vehiculos propios/no propios segun poliza.',
        assets: 'No asegura un bien fisico; asegura la exposicion a causar perjuicios a terceros.',
        value: 'La suma asegurada se define por exposicion: actividad, flujo de terceros, empleados, parqueaderos, contratos, ubicacion y severidad posible.',
        analyst: 'No aceptar una cifra al azar. Revisar actividad real, contratos, visitantes, empleados, parqueaderos, contratistas y sublimitaciones.',
        supports: 'Descripcion de actividad, contratos, aforo/flujo, empleados, predios, matriz de riesgos, caratula y clausulado.',
    },
    'Responsabilidad civil profesional': {
        appliesTo: ['Otro activo', 'Activo intangible/tecnologico/PI', 'Inmueble'],
        title: 'Responsabilidad civil profesional',
        what: 'Protege frente a reclamaciones de terceros por perjuicios derivados de errores, omisiones o fallas en servicios profesionales.',
        includes: 'Errores u omisiones, gastos de defensa, perjuicios patrimoniales, responsabilidad por empleados y extensiones segun actividad.',
        assets: 'Exposicion profesional de la empresa, contratos de servicios, asesorias, disenos, informes o entregables tecnicos.',
        value: 'La suma asegurada se define por monto de contratos, impacto economico posible, tipo de cliente y exigencias contractuales.',
        analyst: 'Revisar alcance de servicios, exclusiones, retroactividad, reclamaciones conocidas y limites por evento/vigencia.',
        supports: 'Contratos, propuestas, terminos de servicio, historial de reclamaciones, certificados y clausulado.',
    },
    'Responsabilidad civil contractual': {
        appliesTo: ['Inmueble', 'Vehiculo/Maquinaria/Equipo', 'Otro activo'],
        title: 'Responsabilidad civil contractual',
        what: 'Cubre responsabilidades derivadas de obligaciones pactadas en un contrato cuando la poliza lo permite.',
        includes: 'Perjuicios contractuales, incumplimientos especificos o responsabilidades asumidas, siempre segun clausulado.',
        assets: 'Contratos de arrendamiento, servicios, suministro, mantenimiento u operacion.',
        value: 'Debe relacionarse con el valor del contrato, perjuicio posible y limite exigido.',
        analyst: 'No mezclarla con RCE. Confirmar si la obligacion contractual es asegurable o si corresponde a cumplimiento.',
        supports: 'Contrato, anexos, obligaciones aseguradas, matriz de riesgos y clausulado.',
    },
    Cumplimiento: {
        appliesTo: ['Inmueble', 'Vehiculo/Maquinaria/Equipo', 'Otro activo'],
        title: 'Cumplimiento',
        what: 'Garantiza al beneficiario los perjuicios derivados del incumplimiento de obligaciones contractuales del tomador.',
        includes: 'Seriedad, anticipo, cumplimiento, salarios/prestaciones, estabilidad/calidad u otros amparos segun contrato.',
        assets: 'Contratos publicos o privados de obra, suministro, arrendamiento o prestacion de servicios.',
        value: 'Suele definirse como porcentaje del valor del contrato o de la obligacion garantizada.',
        analyst: 'Identificar tomador, asegurado/beneficiario, obligacion garantizada, vigencia y texto exigido por el contrato.',
        supports: 'Contrato, otrosies, acta de inicio, valor contractual, solicitud de garantia y clausulado.',
    },
    'Cumplimiento arrendamiento': {
        appliesTo: ['Inmueble'],
        title: 'Cumplimiento arrendamiento',
        what: 'Garantiza obligaciones del contrato de arrendamiento, como canon, servicios, administracion o restitucion, segun producto.',
        includes: 'Canones, cuotas de administracion, servicios publicos, clausulas penales o danos si fueron pactados y asegurados.',
        assets: 'Contrato de arrendamiento y obligaciones del arrendatario.',
        value: 'Se calcula con base en canon, administracion, servicios estimados y meses cubiertos.',
        analyst: 'Verificar vigencia del contrato, arrendatario, codeudores, deposito, mora y exclusiones.',
        supports: 'Contrato, estudio del arrendatario, canones, administracion, servicios y caratula.',
    },
    'Rotura de maquinaria': {
        appliesTo: ['Vehiculo/Maquinaria/Equipo'],
        requiresAny: ['Maquinaria y equipo'],
        title: 'Rotura de maquinaria',
        what: 'Cubre danos materiales internos, accidentales, subitos e imprevistos en maquinaria descrita.',
        includes: 'Averia mecanica, dano electrico interno, impericia, cuerpos extranos, fuerza centrifuga u otros eventos segun clausulado.',
        assets: 'Maquinas de proceso, servicio, equipos auxiliares e instalaciones descritas en la poliza.',
        value: 'Valor de reposicion nuevo por item, con fecha de adquisicion, valor de compra, fuente de consulta y demerito si aplica.',
        analyst: 'Confirmar que la maquina este individualizada, en buen funcionamiento, mantenida y no excluida por uso, desgaste o garantia.',
        supports: 'Inventario, factura, ficha tecnica, mantenimiento, cotizacion de reposicion, fotos, seriales y clausulado.',
    },
    'Equipo electronico / corriente debil': {
        appliesTo: ['Inmueble', 'Vehiculo/Maquinaria/Equipo', 'Activo intangible/tecnologico/PI'],
        requiresAny: ['Equipo electronico / corriente debil'],
        title: 'Equipo electronico / corriente debil',
        what: 'Protege equipos electricos, electronicos, redes, camaras, servidores, UPS, controles y sistemas de baja tension declarados.',
        includes: 'Dano interno, dano electrico, cortocircuito, hurto calificado, incendio y otros eventos segun condiciones.',
        assets: 'Computadores, servidores, camaras, redes, UPS, cableado, controles de acceso, alarmas y equipos similares.',
        value: 'Inventario item por item a valor de reposicion, con fuente y fecha de consulta.',
        analyst: 'Separar hardware, software/datos y servicios. Revisar demerito, garantia, mantenimiento y exclusiones de datos.',
        supports: 'Inventario, facturas, cotizaciones, seriales, fotos, contratos de mantenimiento y clausulado.',
    },
    'Transporte de mercancias': {
        appliesTo: ['Vehiculo/Maquinaria/Equipo', 'Otro activo'],
        title: 'Transporte de mercancias',
        what: 'Protege mercancias durante su proceso logistico por via terrestre, aerea, maritima, fluvial o ferrea.',
        includes: 'Incendio, explosion, terremoto, volcadura, choque, descarga forzosa, perdida o dano segun trayecto y condiciones.',
        assets: 'Mercancias, inventarios, equipos o valores transportados bajo responsabilidad de la empresa.',
        value: 'Valor factura, costo, precio de venta o valor declarado por despacho, trayecto o vigencia.',
        analyst: 'Revisar trayecto, medio, transportador, embalaje, custodia, tipo de mercancia, limites por despacho y exclusiones.',
        supports: 'Factura, remision, guia, contrato transporte, manifiesto, valor declarado y condiciones.',
    },
    Manejo: {
        appliesTo: ['Cuenta bancaria/Dinero', 'Otro activo'],
        title: 'Manejo global comercial',
        what: 'Protege a la empresa frente a apropiacion indebida de dinero u otros bienes por trabajadores o personas cubiertas.',
        includes: 'Hurto, hurto calificado, abuso de confianza, falsedad, estafa o actos deshonestos segun condiciones.',
        assets: 'Dinero, valores, inventarios, bienes propios o de terceros bajo administracion.',
        value: 'Se define por exposicion maxima: caja, bancos, recaudos, inventarios, cargos sensibles y controles internos.',
        analyst: 'Identificar cargos criticos, segregacion de funciones, arqueos, autorizaciones, historial y exclusiones.',
        supports: 'Manual de procesos, responsables, arqueos, conciliaciones, organigrama, politicas y clausulado.',
    },
    'Vida grupo': {
        appliesTo: ['Otro activo'],
        title: 'Vida grupo',
        what: 'Protege a un grupo de personas vinculadas a la empresa frente a muerte, incapacidad u otros eventos personales asegurados.',
        includes: 'Muerte, incapacidad total y permanente, enfermedades graves, auxilios o asistencias segun plan.',
        assets: 'Personas aseguradas: empleados, directivos o grupo definido.',
        value: 'Suma asegurada por persona, usualmente fija o asociada a salario, cargo o politica empresarial.',
        analyst: 'Revisar elegibilidad, edades, beneficiarios, novedades de ingreso/retiro y continuidad de cobertura.',
        supports: 'Listado de asegurados, cedulas, salarios/cargos, beneficiarios, novedades y certificado.',
    },
    'Key person / hombre clave': {
        appliesTo: ['Otro activo', 'Acciones/Cuotas/Participaciones'],
        title: 'Key person / hombre clave',
        what: 'Protege a la empresa frente al impacto economico por muerte o incapacidad de una persona clave.',
        includes: 'Muerte, incapacidad, enfermedades graves o coberturas de vida segun producto.',
        assets: 'Fundadores, gerentes, vendedores criticos, tecnicos clave o personas con conocimiento esencial.',
        value: 'Debe estimarse por impacto financiero: utilidad, deuda, reemplazo, contratos, dependencia y continuidad.',
        analyst: 'Validar interes asegurable de la empresa, beneficiario, dependencia real y plan de sucesion.',
        supports: 'Acta/justificacion, estados financieros, contratos, rol de la persona, certificado y clausulado.',
    },
    Deudores: {
        appliesTo: ['CDT', 'Inversion financiera', 'Cuenta por cobrar', 'Deuda/Pasivo', 'Inmueble'],
        title: 'Deudores',
        what: 'Protege obligaciones de credito frente a eventos personales del deudor o incumplimientos definidos por el producto.',
        includes: 'Vida deudor, incapacidad, desempleo o coberturas asociadas al saldo insoluto, segun producto.',
        assets: 'Creditos, cartera, hipotecas, leasing u obligaciones financieras.',
        value: 'Saldo de la deuda, valor desembolsado o saldo insoluto actualizado.',
        analyst: 'Revisar saldo, beneficiario oneroso, vigencia, edad/asegurabilidad y renovacion.',
        supports: 'Certificado de deuda, plan de pagos, caratula, beneficiario oneroso y soporte de pago.',
    },
    'Cyber / riesgo digital': {
        appliesTo: ['Activo intangible/tecnologico/PI', 'Otro activo'],
        title: 'Cyber / riesgo digital',
        what: 'Protege consecuencias financieras y operativas de incidentes ciberneticos y de privacidad de datos.',
        includes: 'Respuesta a incidentes, perdida de datos, interrupcion, extorsion, responsabilidad por privacidad, defensa y asistencia especializada.',
        assets: 'Datos personales, sistemas, aplicaciones, portales, infraestructura digital, reputacion y continuidad operativa.',
        value: 'Se define por facturacion, datos tratados, dependencia tecnologica, costos de respuesta y posible interrupcion.',
        analyst: 'Revisar controles, backups, MFA, proveedores, datos sensibles, incidentes previos y exclusiones.',
        supports: 'Inventario tecnologico, politicas de seguridad, backups, contratos cloud, analisis de riesgo y clausulado.',
    },
    'Lucro cesante': {
        appliesTo: ['Inmueble', 'Vehiculo/Maquinaria/Equipo', 'Otro activo'],
        title: 'Lucro cesante',
        what: 'Cubre perdida de utilidad o ingresos por interrupcion de la actividad a causa de un evento material amparado.',
        includes: 'Utilidad bruta, gastos permanentes, perdida de ingresos, perdida de arrendamiento por siniestro, gastos extra o periodo de indemnizacion segun poliza.',
        assets: 'Actividad economica dependiente del inmueble, maquinaria, equipo o proceso asegurado.',
        value: 'Debe salir de estados financieros, ingresos historicos, canon o utilidad bruta soportada. Para perdida de arrendamiento: canon mensual por unidad por meses solicitados, ajustado por participacion.',
        analyst: 'Confirmar evento disparador, periodo de indemnizacion, base contable y que no haya doble conteo con seguro de arrendamiento o alojamiento temporal.',
        supports: 'Estados financieros, contratos, canon, ingresos historicos, gastos fijos, caratula y clausulado.',
    },
};

const insuranceCalculationAcademy = {
    'Danos materiales inmueble arrendado': {
        method: 'Valor de reposicion y perdida de canon',
        tool: 'Tabla de construccion, contenidos y arrendamientos',
        formula: 'Construccion sin terreno + contenidos declarados + canon mensual por unidad x meses cubiertos si se solicita perdida de arrendamiento.',
        source: 'Avaluo, m2, fuente de reposicion por m2, inventario, contratos de arrendamiento, canon por unidad y clausulado.',
        warning: 'No mezclar terreno con construccion. Separar dano material, responsabilidad y perdida de canon.',
    },
    'Todo riesgo dano material': {
        method: 'Valor de reposicion integral',
        tool: 'Tabla de bienes asegurables por categoria',
        formula: 'Suma de construccion, mejoras, contenidos, maquinaria, equipos, mercancias y extensiones asegurables a valor de reposicion.',
        source: 'Avaluo, cotizaciones, facturas, inventario, fotos, seriales, fuente de m2 y fecha de consulta.',
        warning: 'Una poliza puede agrupar ramos, pero los valores deben quedar separados por cobertura.',
    },
    Copropiedad: {
        method: 'Valor de reposicion de bienes comunes',
        tool: 'Tabla de zonas comunes y coeficientes',
        formula: 'Valor reposicion de bienes comunes y privados cubiertos segun reglamento, coeficiente o distribucion aprobada.',
        source: 'Reglamento PH, certificado de administracion, avaluo, presupuesto, poliza matriz y coeficientes.',
        warning: 'Confirmar que cubre bienes comunes, privados, RC copropiedad y participacion de cada unidad.',
    },
    'Cumplimiento arrendamiento': {
        method: 'Canon y obligaciones contractuales',
        tool: 'Tabla de obligaciones del contrato',
        formula: 'Canon mensual, administracion, servicios y clausula penal x numero de meses o limite pactado.',
        source: 'Contrato de arrendamiento, canon, administracion, servicios, clausula penal y condiciones de garantia.',
        warning: 'No confundir con perdida de arrendamiento por siniestro; aqui el riesgo es incumplimiento del arrendatario.',
    },
    Deudores: {
        method: 'Saldo insoluto asegurado',
        tool: 'Tabla de deudores y creditos',
        formula: 'Saldo de deuda o cupo asegurado por deudor segun certificado, edad, plazo y condiciones.',
        source: 'Pagare, credito, saldo, certificado de deuda, edad, beneficiario oneroso y clausulado.',
        warning: 'Controlar novedades de saldo, edad, exclusiones y beneficiario.',
    },
    'Cyber / riesgo digital': {
        method: 'Exposicion digital y continuidad',
        tool: 'Matriz de sistemas, datos y costos de respuesta',
        formula: 'Limite solicitado segun datos, sistemas criticos, facturacion digital, costos de respuesta, multas, defensa y lucro cesante.',
        source: 'Inventario de sistemas, politicas de seguridad, contratos TI, historico de incidentes, facturacion y clausulado.',
        warning: 'Separar dano propio, responsabilidad frente a terceros, respuesta a incidentes y continuidad.',
    },
    'Key person / hombre clave': {
        method: 'Impacto economico por persona clave',
        tool: 'Tabla de personas clave y continuidad',
        formula: 'Costo de reemplazo + perdida esperada de margen/contratos + deuda o continuidad durante meses definidos.',
        source: 'Nomina, contratos, responsabilidades, facturacion atribuible, plan de sucesion y analisis financiero.',
        warning: 'No es valor sentimental; debe justificar el impacto economico asegurable.',
    },
    'Automóviles': {
        method: 'Valor oficial o guia comercial',
        tool: 'Ficha de vehiculo',
        formula: 'Valor Fasecolda o valor comercial soportado + accesorios declarados si aplican.',
        source: 'Guia Fasecolda, factura, avaluo, tarjeta de propiedad, inspeccion y fecha de consulta.',
        warning: 'No usar valor sentimental ni saldo de deuda como valor del vehiculo.',
    },
    'Automovil todo riesgo': {
        method: 'Valor oficial o guia comercial',
        tool: 'Ficha de vehiculo',
        formula: 'Valor Fasecolda o valor comercial soportado + accesorios declarados si aplican.',
        source: 'Guia Fasecolda, factura, avaluo, tarjeta de propiedad, inspeccion y fecha de consulta.',
        warning: 'Separar valor del vehiculo, accesorios y limite de RCE.',
    },
    SOAT: {
        method: 'Tarifa regulada',
        tool: 'Control de vigencia',
        formula: 'Prima segun clase de vehiculo, capacidad/cilindraje, modelo y reglas vigentes.',
        source: 'Certificado SOAT, tarjeta de propiedad y soporte de pago.',
        warning: 'No es una poliza patrimonial; el control principal es vigencia y renovacion.',
    },
    Cumplimiento: {
        method: 'Valor contractual',
        tool: 'Tabla de obligaciones garantizadas',
        formula: 'Valor del contrato u obligacion x porcentaje exigido para cada amparo.',
        source: 'Contrato, pliego, otrosies, acta de inicio, anticipo y solicitud del beneficiario.',
        warning: 'Cada amparo puede tener porcentaje y vigencia distinta.',
    },
    'Responsabilidad Civil': {
        method: 'Limite de responsabilidad por exposicion',
        tool: 'Matriz de exposicion a terceros',
        formula: 'Limite solicitado segun actividad, aforo, empleados, contratos, parqueaderos, predios y severidad posible.',
        source: 'Contratos, actividad real, flujo de visitantes, nomina, predios, matriz de riesgos y clausulado.',
        warning: 'No sale de inventario de bienes. Se define por exposicion y apetito de riesgo.',
    },
    'Responsabilidad civil extracontractual': {
        method: 'Limite de responsabilidad por exposicion',
        tool: 'Matriz de exposicion a terceros',
        formula: 'Limite solicitado por evento/vigencia segun actividad, terceros, empleados, contratistas y predios.',
        source: 'Contratos, actividad real, flujo de visitantes, nomina, predios, matriz de riesgos y clausulado.',
        warning: 'Validar subamparos: patronal, cruzada, parqueaderos, gastos medicos y defensa.',
    },
    'Responsabilidad civil profesional': {
        method: 'Limite de responsabilidad profesional',
        tool: 'Tabla de contratos y servicios',
        formula: 'Limite solicitado segun valor de contratos, posible perjuicio patrimonial, clientes y retroactividad.',
        source: 'Contratos, propuestas, facturacion, historial de reclamaciones y clausulado claims made.',
        warning: 'Confirmar retroactividad, errores u omisiones, defensa y exclusiones profesionales.',
    },
    'Responsabilidad civil contractual': {
        method: 'Limite de responsabilidad contractual',
        tool: 'Tabla de obligaciones contractuales',
        formula: 'Valor del contrato, obligacion o perjuicio maximo razonable que se desea trasladar.',
        source: 'Contrato, anexos, obligaciones asumidas, limites exigidos y clausulado.',
        warning: 'No confundir con cumplimiento; validar si la obligacion asumida es asegurable.',
    },
    Incendio: {
        method: 'Valor de reposicion de bienes materiales',
        tool: 'Tabla de bienes asegurables',
        formula: 'Construccion: m2 asegurables x valor reposicion m2. Contenidos/equipos: suma por item a valor de reposicion.',
        source: 'Avaluo, Construdata/Sispac, cotizaciones, facturas, inventario, fotos y fecha de consulta.',
        warning: 'El terreno no se asegura. Separar construccion, mejoras, muebles, mercancias y equipos.',
    },
    'Incendio y terremoto': {
        method: 'Valor de reposicion de bienes materiales',
        tool: 'Tabla de bienes asegurables',
        formula: 'Construccion sin terreno + contenidos declarados, separados por ubicacion y categoria.',
        source: 'Avaluo, areas, fuente de reposicion por m2, inventario de contenidos y clausulado.',
        warning: 'Terremoto suele tener deducible especial por evento/articulo afectado.',
    },
    Terremoto: {
        method: 'Valor de reposicion con deducible catastrofico',
        tool: 'Tabla de construccion y contenidos',
        formula: 'Valor reposicion de construccion y contenidos asegurados, excluyendo terreno.',
        source: 'Avaluo, areas, fuente de reposicion por m2, inventario y clausulado de deducibles.',
        warning: 'Confirmar minimo, porcentaje y base del deducible.',
    },
    'Sustracción': {
        method: 'Inventario valorizado contra hurto',
        tool: 'Tabla de bienes expuestos',
        formula: 'Suma de bienes susceptibles de sustraccion por item, valor reposicion o valor declarado segun poliza.',
        source: 'Inventario, factura, cotizacion, seriales, fotos, avaluos de joyas/arte y controles de seguridad.',
        warning: 'Confirmar modalidad cubierta: hurto calificado, violencia, alarma, dinero y bienes excluidos.',
    },
    Sustraccion: {
        method: 'Inventario valorizado contra hurto',
        tool: 'Tabla de bienes expuestos',
        formula: 'Suma de bienes susceptibles de sustraccion por item, valor reposicion o valor declarado segun poliza.',
        source: 'Inventario, factura, cotizacion, seriales, fotos, avaluos de joyas/arte y controles de seguridad.',
        warning: 'Confirmar modalidad cubierta: hurto calificado, violencia, alarma, dinero y bienes excluidos.',
    },
    Transporte: {
        method: 'Valor por despacho o vigencia',
        tool: 'Tabla de mercancias transportadas',
        formula: 'Valor factura/costo/valor declarado por despacho x limite por trayecto o vigencia.',
        source: 'Factura, remision, guia, manifiesto, contrato de transporte, valor declarado y trayectos.',
        warning: 'Revisar embalaje, transportador, custodia, trayecto y limite por despacho.',
    },
    'Transporte de mercancias': {
        method: 'Valor por despacho o vigencia',
        tool: 'Tabla de mercancias transportadas',
        formula: 'Valor factura/costo/valor declarado por despacho x limite por trayecto o vigencia.',
        source: 'Factura, remision, guia, manifiesto, contrato de transporte, valor declarado y trayectos.',
        warning: 'Revisar embalaje, transportador, custodia, trayecto y limite por despacho.',
    },
    'Corriente Débil': {
        method: 'Inventario electronico a valor de reposicion',
        tool: 'Tabla de equipos electronicos',
        formula: 'Suma item por item: UPS, CCTV, redes, servidores, controles, computadores y equipos de baja tension.',
        source: 'Inventario, seriales, facturas, cotizaciones, ficha tecnica, fotos y fecha de consulta.',
        warning: 'Separar hardware, software/datos y servicios. Revisar demerito y protecciones electricas.',
    },
    'Equipo electronico / corriente debil': {
        method: 'Inventario electronico a valor de reposicion',
        tool: 'Tabla de equipos electronicos',
        formula: 'Suma item por item: UPS, CCTV, redes, servidores, controles, computadores y equipos de baja tension.',
        source: 'Inventario, seriales, facturas, cotizaciones, ficha tecnica, fotos y fecha de consulta.',
        warning: 'Separar hardware, software/datos y servicios. Revisar demerito y protecciones electricas.',
    },
    'Todo Riesgo Contratista': {
        method: 'Presupuesto de obra y exposicion de obra',
        tool: 'Tabla de presupuesto, equipos y RC',
        formula: 'Valor de obra + materiales + equipos temporales + remocion/gastos + limites de RC segun contrato.',
        source: 'Contrato de obra, presupuesto, cronograma, acta, planos, equipos, subcontratos y clausulado.',
        warning: 'Controlar vigencia por etapa, prorrogas, periodo de mantenimiento y cambios de valor.',
    },
    Manejo: {
        method: 'Exposicion maxima a fraude o apropiacion',
        tool: 'Tabla de cargos, procesos y valores administrados',
        formula: 'Mayor valor expuesto por caja, bancos, recaudos, inventarios, pagos o procesos sensibles en una vigencia.',
        source: 'Arqueos, conciliaciones, manual de funciones, perfiles, autorizaciones, organigrama e historial.',
        warning: 'No sale de activos fisicos solamente; depende de controles internos y cargos con acceso.',
    },
    'Lucro Cesante': {
        method: 'Tabla financiera de ingresos o utilidad',
        tool: 'Tabla de ingresos, canon, utilidad y periodo',
        formula: 'Ingreso/canon/utilidad bruta mensual x meses de indemnizacion x participacion, mas gastos permanentes si aplica.',
        source: 'Estados financieros, contratos, canon mensual por unidad, historico de ingresos, participacion y clausulado.',
        warning: 'Debe existir evento material detonante salvo pacto distinto. Confirmar deducible temporal e indice variable.',
    },
    'Lucro cesante': {
        method: 'Tabla financiera de ingresos o utilidad',
        tool: 'Tabla de ingresos, canon, utilidad y periodo',
        formula: 'Ingreso/canon/utilidad bruta mensual x meses de indemnizacion x participacion, mas gastos permanentes si aplica.',
        source: 'Estados financieros, contratos, canon mensual por unidad, historico de ingresos, participacion y clausulado.',
        warning: 'Debe existir evento material detonante salvo pacto distinto. Confirmar deducible temporal e indice variable.',
    },
    'Montaje y Rotura de Maquinaria': {
        method: 'Inventario tecnico a valor de reposicion',
        tool: 'Tabla de maquinaria',
        formula: 'Valor reposicion por maquina/equipo, con serial, edad, mantenimiento, vida util y demerito si aplica.',
        source: 'Factura, ficha tecnica, cotizacion de reposicion, mantenimientos, seriales, fotos y fecha de consulta.',
        warning: 'No incluir desgaste normal. Confirmar maquinaria cubierta y excluida.',
    },
    'Rotura de maquinaria': {
        method: 'Inventario tecnico a valor de reposicion',
        tool: 'Tabla de maquinaria',
        formula: 'Valor reposicion por maquina/equipo, con serial, edad, mantenimiento, vida util y demerito si aplica.',
        source: 'Factura, ficha tecnica, cotizacion de reposicion, mantenimientos, seriales, fotos y fecha de consulta.',
        warning: 'No incluir desgaste normal. Confirmar maquinaria cubierta y excluida.',
    },
    'Aviación': {
        method: 'Avaluo especializado aeronautico',
        tool: 'Ficha tecnica de aeronave y RC',
        formula: 'Valor casco/aeronave + equipos + limites de responsabilidad por pasajeros/terceros/carga.',
        source: 'Matricula aeronave, avaluo, bitacoras, mantenimiento, horas de vuelo, contratos y clausulado.',
        warning: 'Requiere soporte tecnico especializado y cumplimiento regulatorio.',
    },
    'Navegación y Casco': {
        method: 'Avaluo especializado de embarcacion',
        tool: 'Ficha de embarcacion y exposicion maritima',
        formula: 'Valor casco/maquinaria/equipos + limites de responsabilidad y valores transportados si aplican.',
        source: 'Matricula, avaluo naval, mantenimiento, certificados, rutas, tripulacion y clausulado.',
        warning: 'Validar navegacion autorizada, zonas, averia gruesa, salvamento y exclusiones.',
    },
    'Minas y Petróleos': {
        method: 'Valor tecnico especializado por operacion',
        tool: 'Matriz de activos, pozos, equipos y responsabilidad',
        formula: 'Valor reposicion de instalaciones/equipos + limites de control de pozos, contaminacion y lucro cesante.',
        source: 'Estudios tecnicos, inventario, contratos, licencias, reportes operativos, avaluos y clausulado.',
        warning: 'Ramo altamente especializado; exigir broker/ingenieria de riesgos.',
    },
    Vidrios: {
        method: 'Relacion de vidrios a valor de reposicion',
        tool: 'Tabla de vidrios',
        formula: 'Medida x tipo/espesor/instalacion x valor de reposicion por unidad.',
        source: 'Cotizacion de proveedor, medidas, fotos, ubicacion y fecha de consulta.',
        warning: 'Separar fachadas, vitrinas, divisiones, espejos, avisos y domos.',
    },
    'Crédito Comercial': {
        method: 'Cartera asegurada y limite de credito',
        tool: 'Tabla de compradores y cartera',
        formula: 'Cuentas por cobrar asegurables por comprador, limite de credito y porcentaje cubierto.',
        source: 'Cartera, facturacion, historial de pagos, cupos, estados financieros de clientes y condiciones.',
        warning: 'No toda cartera es asegurable; revisar exclusiones, mora previa y deducible/coaseguro.',
    },
    'Crédito a la Exportación': {
        method: 'Cartera exportadora y riesgo pais/comprador',
        tool: 'Tabla de compradores externos',
        formula: 'Cuentas por cobrar de exportacion por comprador/pais x porcentaje cubierto.',
        source: 'Facturas, contratos, embarques, cartera, pais destino, comprador y condiciones de pago.',
        warning: 'Distinguir riesgo comercial y riesgo politico.',
    },
    Agropecuario: {
        method: 'Valor tecnico agropecuario',
        tool: 'Tabla de cultivos, animales o produccion',
        formula: 'Area/cabezas/unidades productivas x costo o valor asegurado tecnico segun producto.',
        source: 'Inventario agropecuario, areas, costos de produccion, aval tecnico, clima, ciclo y clausulado.',
        warning: 'Puede ser parametrico o indemnizatorio; validar evento, periodo y exclusiones.',
    },
    Desempleo: {
        method: 'Obligacion mensual cubierta',
        tool: 'Tabla de cuotas u obligaciones',
        formula: 'Cuota/canon/obligacion mensual x numero maximo de meses cubiertos.',
        source: 'Contrato laboral, certificacion de ingresos, credito, canon, cuota y clausulado.',
        warning: 'Validar carencias, definicion de desempleo involuntario y periodo maximo.',
    },
    Hogar: {
        method: 'Construccion y contenidos a valor de reposicion',
        tool: 'Tabla de vivienda, menaje y contenidos',
        formula: 'Construccion sin terreno + menaje/contenidos por inventario o estimacion soportada.',
        source: 'Avaluo, areas, inventario, facturas, fotos, cotizaciones y clausulado.',
        warning: 'Separar vivienda, contenidos, joyas, arte, dinero y RCE familiar.',
    },
    Decenal: {
        method: 'Valor de construccion y responsabilidad estructural',
        tool: 'Tabla tecnica de proyecto',
        formula: 'Valor de obra/edificacion asegurada segun presupuesto, costos directos y alcance estructural.',
        source: 'Licencia, presupuesto, estudios, actas, planos, interventoria, certificaciones y clausulado.',
        warning: 'Especial para ruina o amenaza de ruina; requiere soporte tecnico de obra.',
    },
    Exequias: {
        method: 'Plan o limite de servicio',
        tool: 'Listado de asegurados',
        formula: 'Valor del plan funerario o limite por asegurado segun edad/grupo.',
        source: 'Listado de asegurados, edades, parentesco, plan contratado y certificado.',
        warning: 'Normalmente es servicio, no valor de reposicion de activo.',
    },
    'Accidentes Personales': {
        method: 'Suma asegurada por persona y cobertura',
        tool: 'Listado de personas aseguradas',
        formula: 'Suma asegurada por muerte/invalidez/gastos medicos segun rol, ingreso o politica.',
        source: 'Listado, cedulas, edad, ocupacion, salario/ingreso y certificado.',
        warning: 'Validar actividad, exclusiones, beneficiarios y acumulacion con vida grupo.',
    },
    'Colectivo Vida': {
        method: 'Suma asegurada por persona o grupo',
        tool: 'Listado de asegurados',
        formula: 'Valor fijo o multiplo de salario por asegurado, segun politica o plan.',
        source: 'Listado de asegurados, salarios, cargos, beneficiarios, edades y certificado.',
        warning: 'Controlar ingresos/retiros y actualizacion de nomina.',
    },
    Educativo: {
        method: 'Costo educativo proyectado',
        tool: 'Tabla de beneficiarios y costos',
        formula: 'Matricula/pension/costo educativo proyectado x periodos cubiertos.',
        source: 'Certificados de institucion, costos historicos, beneficiario, edad y plan.',
        warning: 'Validar incrementos, periodos cubiertos y condiciones de pago.',
    },
    'Vida Grupo': {
        method: 'Suma asegurada por persona o grupo',
        tool: 'Listado de asegurados',
        formula: 'Valor fijo o multiplo de salario por asegurado, segun politica o plan.',
        source: 'Listado de asegurados, salarios, cargos, beneficiarios, edades y certificado.',
        warning: 'Controlar ingresos/retiros y actualizacion de nomina.',
    },
    Salud: {
        method: 'Plan medico o limite de gasto',
        tool: 'Listado de asegurados y plan',
        formula: 'Prima/plan por persona y limites segun red, coberturas, edades y condiciones.',
        source: 'Listado de asegurados, edades, plan, preexistencias declaradas y certificado.',
        warning: 'No se calcula por valor patrimonial; revisar exclusiones, copagos y red.',
    },
    'Enfermedades de Alto Costo': {
        method: 'Suma o limite por evento medico',
        tool: 'Listado de asegurados y coberturas',
        formula: 'Limite por enfermedad/persona segun plan, edad y condiciones.',
        source: 'Listado, edades, plan, declaraciones de salud, certificado y clausulado.',
        warning: 'Validar definiciones de enfermedad, carencias, preexistencias y exclusiones.',
    },
    'Vida Individual': {
        method: 'Necesidad financiera personal',
        tool: 'Analisis de proteccion familiar',
        formula: 'Deudas + gastos familiares + educacion + continuidad patrimonial - activos disponibles.',
        source: 'Estados financieros personales, deudas, beneficiarios, ingresos, patrimonio y certificado.',
        warning: 'Alinear con sucesion, beneficiarios, insurabilidad y objetivo familiar.',
    },
    'Previsional de Invalidez y Sobrevivencia': {
        method: 'Calculo actuarial/regulatorio',
        tool: 'Ficha de afiliado y pension',
        formula: 'Suma adicional necesaria para financiar pension segun regimen y normas aplicables.',
        source: 'Historia laboral, salario base, edad, beneficiarios, AFP/aseguradora y regulacion.',
        warning: 'Requiere calculo tecnico de seguridad social.',
    },
    'Riesgos Laborales': {
        method: 'Cotizacion por clase de riesgo y nomina',
        tool: 'Nomina y clase de riesgo',
        formula: 'Ingreso base de cotizacion x tarifa segun clase de riesgo y actividad.',
        source: 'Nomina, cargos, centros de trabajo, actividad economica, ARL y soportes de afiliacion.',
        warning: 'Controlar clasificacion real del riesgo laboral y novedades.',
    },
    'Pensiones Ley 100': {
        method: 'Modalidad pensional regulada',
        tool: 'Ficha pensionado/beneficiarios',
        formula: 'Capital necesario o prima segun modalidad, edad, beneficiarios y regulacion.',
        source: 'Historia laboral, bono, capital, beneficiarios, AFP/aseguradora y documentos pensionales.',
        warning: 'No es valor asegurable patrimonial ordinario; requiere calculo actuarial.',
    },
    'Pensiones Voluntarias': {
        method: 'Meta de ahorro o renta',
        tool: 'Plan de ahorro/renta',
        formula: 'Aporte objetivo o capital meta segun horizonte, beneficio esperado y perfil.',
        source: 'Plan voluntario, aportes, beneficiarios, extractos y condiciones.',
        warning: 'Distinguir ahorro/inversion de seguro de riesgo.',
    },
    'Pensiones con Conmutación Pensional': {
        method: 'Calculo actuarial de pasivo pensional',
        tool: 'Tabla de beneficiarios y obligaciones',
        formula: 'Valor presente actuarial de mesadas y obligaciones conmutadas.',
        source: 'Estudio actuarial, listado de pensionados, mesadas, edades, beneficiarios y aprobaciones.',
        warning: 'Requiere soporte actuarial y validacion legal/laboral.',
    },
    'Rentas Voluntarias': {
        method: 'Capital para renta contratada',
        tool: 'Plan de renta',
        formula: 'Capital aportado o necesario para renta periodica segun edad, plazo y modalidad.',
        source: 'Contrato de renta, beneficiario, capital, condiciones y certificado.',
        warning: 'Validar irrevocabilidad, beneficiarios, liquidez y tratamiento tributario.',
    },
    BEPS: {
        method: 'Beneficio periodico regulado',
        tool: 'Ficha del beneficiario',
        formula: 'Ahorro acumulado y beneficio periodico segun reglas del mecanismo.',
        source: 'Extractos BEPS, identificacion, edad, beneficiario y condiciones oficiales.',
        warning: 'No tratar como poliza patrimonial ordinaria.',
    },
};

const insuranceCalculationGuideForProduct = (product) => {
    if (insuranceCalculationAcademy[product]) {
        return insuranceCalculationAcademy[product];
    }
    const normalized = String(product || '').normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase();
    const entry = Object.entries(insuranceCalculationAcademy).find(([key]) => key.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase() === normalized);
    if (entry) {
        return entry[1];
    }
    return {
        method: 'Valor a definir con soporte',
        tool: 'Tabla de soporte segun cobertura',
        formula: 'Documentar valor solicitado, fuente, fecha, limite, deducible y observacion para cotizar.',
        source: 'Cotizacion, contrato, avaluo, inventario, estados financieros o documento tecnico aplicable.',
        warning: 'No enviar a cotizar sin fuente verificable.',
    };
};

const insurableAssetGuidance = {
    Construccion: 'Asegura valor de reposicion de la construccion, sin terreno: m2 asegurables x valor de reposicion por m2.',
    'Muebles y enseres': 'Asegura valor de reposicion nuevo o equivalente, con inventario, factura, cotizacion, fotos o avaluo.',
    'Menaje domestico': 'Asegura relacion detallada o estimacion soportada por categoria; evita valores globales sin respaldo.',
    'Maquinaria y equipo': 'Asegura valor de reposicion de equipo equivalente, con marca, modelo, serial, ano, estado, mantenimiento y fuente.',
    'Equipo electronico / corriente debil': 'Asegura UPS, CCTV, redes, servidores, controles y equipos electronicos a valor de reposicion con serial y fuente.',
    'Mercancias / inventario': 'Asegura costo, valor declarado o valor asegurado segun poliza, con inventario actualizado y metodo de valoracion.',
    Vidrios: 'Asegura por medida, tipo, espesor, instalacion y valor de reposicion cotizado.',
    'Obras de arte': 'Asegura por avaluo especializado, certificado de autenticidad, autor, tecnica, medidas, fecha y registro fotografico.',
    Joyas: 'Asegura por avaluo, factura o certificacion, con material, peso, piedras, descripcion y fotos.',
    'Dinero en efectivo': 'Asegura limite maximo probable en caja o transito, con controles, arqueos y condiciones de custodia.',
    Vehiculo: 'Asegura valor comercial soportado, usualmente Fasecolda, avaluo o factura, separando accesorios.',
    'Exposicion frente a terceros': 'No sale de inventario; define limite por evento/vigencia segun actividad, contratos, visitantes y severidad.',
    'Contrato de arrendamiento': 'Usa canon, administracion, servicios o clausula pactada por los meses cubiertos.',
    'Contrato': 'Usa valor contractual, porcentaje exigido, vigencia y obligacion garantizada.',
    'Predios, labores y operaciones': 'Define el limite por la operacion del predio: visitantes, circulacion, actividad, mantenimiento, accesos y severidad posible.',
    'Visitantes y terceros': 'Define el limite por aforo, frecuencia de visitantes, tipo de actividad y posible severidad de lesiones.',
    'Danos materiales a terceros': 'Define el limite por bienes de terceros expuestos: vecinos, arrendatarios, clientes, parqueaderos o predios colindantes.',
    'Empleados / RC patronal': 'Define el limite segun nomina, labores, riesgo ocupacional y si la poliza cubre responsabilidad patronal.',
    'Contratistas / RC cruzada': 'Define el limite por contratistas, obras, mantenimiento, administracion delegada y cruces de responsabilidad.',
    Parqueaderos: 'Define el limite por numero de cupos, custodia, control de acceso, circulacion y valor probable de vehiculos expuestos.',
    'Gastos de defensa': 'Define si opera dentro o adicional al limite principal y registra el sublimite ofrecido por la aseguradora.',
    'Actividad profesional': 'Define el limite por errores u omisiones asociados al servicio profesional y contratos atendidos.',
};

const insurableAssetTooltip = (category) => insurableAssetGuidance[category] || 'Define el valor con fuente verificable, fecha de consulta y soporte documental.';

const getAssetDetailValue = (form, name) => {
    const field = form?.elements?.[`detalle[${name}]`];
    return field instanceof HTMLInputElement || field instanceof HTMLSelectElement || field instanceof HTMLTextAreaElement ? field.value : '';
};

const insuranceProductApplies = (product, type, selectedCategories = new Set(), form = null) => {
    const academy = insuranceProductAcademy[product];
    const catalogRow = form ? insuranceCatalogForProduct(form, product) : null;
    const activeMacro = getAssetDetailValue(form, 'seguro_macro_ramo');
    if (activeMacro && catalogRow && insuranceCatalogMacro(catalogRow) !== activeMacro) {
        return false;
    }
    if (catalogRow) {
        return true;
    }
    if (type === 'Inmueble') {
        const allowed = new Set([
            'Danos materiales inmueble arrendado',
            'Todo riesgo dano material',
            'Incendio y terremoto',
            'Incendio',
            'Terremoto',
            'Sustracción',
            'Vidrios',
            'Responsabilidad Civil',
            'Responsabilidad civil extracontractual',
            'Cumplimiento',
            'Cumplimiento arrendamiento',
            'Lucro cesante',
            'Lucro Cesante',
            'Crédito Comercial',
            'Otro',
            'Por confirmar',
        ]);
        const use = getAssetDetailValue(form, 'uso');
        const propertyType = getAssetDetailValue(form, 'tipo_inmueble');
        const horizontalProperty = getAssetDetailValue(form, 'propiedad_horizontal');
        const mortgage = getAssetDetailValue(form, 'tiene_hipoteca');
        if (use === 'Residencial' || ['Apartaestudio', 'Apartamento', 'Casa', 'Casa lote'].includes(propertyType)) {
            allowed.add('Hogar');
        }
        if (horizontalProperty === 'Si' || ['Edificio'].includes(propertyType)) {
            allowed.add('Copropiedad');
            allowed.add('Incendio');
            allowed.add('Terremoto');
        }
        if (mortgage === 'Si') {
            allowed.add('Deudores');
            allowed.add('Crédito Comercial');
        }
        if (selectedCategories.has('Maquinaria y equipo')) {
            allowed.add('Rotura de maquinaria');
            allowed.add('Montaje y Rotura de Maquinaria');
        }
        if (selectedCategories.has('Equipo electronico / corriente debil')) {
            allowed.add('Equipo electronico / corriente debil');
            allowed.add('Corriente Débil');
        }
        return allowed.has(product) || allowed.has(normalizeInsuranceRamo(product));
    }
    if (type === 'Vehiculo/Maquinaria/Equipo') {
        const allowed = new Set([
            'Automóviles',
            'Responsabilidad civil extracontractual',
            'Responsabilidad Civil',
            'Transporte',
            'Transporte de mercancias',
            'Deudores',
            'Crédito Comercial',
            'Otro',
            'Por confirmar',
        ]);
        const assetClass = getAssetDetailValue(form, 'clase_activo');
        const assetSubtype = getAssetDetailValue(form, 'tipo_especifico');
        const isVehicle = assetClass === 'Vehiculo' || ['Automovil', 'Camioneta', 'Camion', 'Moto'].includes(assetSubtype);
        const isMachinery = ['Maquinaria', 'Equipo', 'Herramienta', 'Equipo tecnologico'].includes(assetClass)
            || ['Montacargas', 'Maquinaria amarilla', 'Maquinaria agricola', 'Equipo productivo', 'Equipo de oficina', 'Equipo tecnologico', 'Herramienta'].includes(assetSubtype)
            || selectedCategories.has('Maquinaria y equipo');
        const isElectronic = assetClass === 'Equipo tecnologico' || assetSubtype === 'Equipo tecnologico'
            || selectedCategories.has('Equipo electronico / corriente debil');
        if (isVehicle || (!assetClass && !assetSubtype)) {
            allowed.add('Automovil todo riesgo');
            allowed.add('Automóviles');
            allowed.add('SOAT');
        }
        if (isMachinery) {
            allowed.add('Rotura de maquinaria');
            allowed.add('Montaje y Rotura de Maquinaria');
        }
        if (isElectronic) {
            allowed.add('Equipo electronico / corriente debil');
            allowed.add('Corriente Débil');
        }
        return allowed.has(product) || allowed.has(normalizeInsuranceRamo(product));
    }
    if (!academy) {
        return true;
    }
    if (Array.isArray(academy.appliesTo) && !academy.appliesTo.includes(type)) {
        return false;
    }
    if (Array.isArray(academy.requiresAny) && academy.requiresAny.length > 0) {
        return academy.requiresAny.some((category) => selectedCategories.has(category));
    }
    return true;
};

const insuranceProductsForAsset = (type, selectedCategories = new Set(), form = null) => {
    const catalogProducts = form ? insuranceAcademyData(form).catalog.map(insuranceCatalogRamo).filter(Boolean) : [];
    const products = catalogProducts.length > 0 ? catalogProducts : Object.keys(insuranceProductAcademy);
    return [...new Set(products)];
};

const selectedInsuranceCategoriesFromForm = (form) => {
    const selected = new Set();
    form.querySelectorAll('[data-asset-insurable-toggle]:checked').forEach((input) => {
        if (!(input instanceof HTMLInputElement)) {
            return;
        }
        const option = insuranceAssetOptionByKey(input.value);
        if (option) {
            selected.add(option.category);
        }
    });
    form.querySelectorAll('[data-asset-insurance-equipment-row]').forEach((row) => {
        const category = row.querySelector('[name$="[categoria_item]"]')?.value || '';
        if (category) {
            selected.add(category);
        }
    });
    return selected;
};

const selectedInsuranceOptionKeysFromForm = (form, options = [...insuranceAssetOptions, ...liabilityExposureOptions]) => {
    const selected = new Set();
    form.querySelectorAll('[data-asset-insurable-toggle]:checked').forEach((input) => {
        if (input instanceof HTMLInputElement) {
            selected.add(input.value);
        }
    });
    const rows = insuranceEquipmentRowsForForm(form);
    options.forEach((option) => {
        if (rows.some((row) => row.categoria_item === option.category || row.cobertura_asociada === option.coverage || row.cobertura === option.coverage)) {
            selected.add(option.key);
        }
    });
    return selected;
};

const insuranceProductAcademyHtml = (products = [], form = null) => {
    const selected = products.filter((product) => insuranceProductAcademy[product] || insuranceCatalogForProduct(form, product));
    if (selected.length === 0) {
        return '<div class="asset-insurance-academy-empty">Selecciona uno o varios seguros para ver su resumen tecnico y continuar con las coberturas requeridas.</div>';
    }
    const current = form?.dataset?.assetAcademyActiveProduct || selected[0];
    const activeProduct = selected.includes(current) ? current : selected[0];
    if (form) {
        form.dataset.assetAcademyActiveProduct = activeProduct;
    }
    const tabs = selected.map((product) => `
        <button type="button" class="${product === activeProduct ? 'is-active' : ''}" data-insurance-academy-tab="${assetEscape(product)}" aria-pressed="${product === activeProduct ? 'true' : 'false'}">
            ${assetEscape(product)}
        </button>
    `).join('');
    const activeCard = [activeProduct].map((product, index) => {
        const item = insuranceProductAcademy[product];
        const catalog = insuranceCatalogForProduct(form, product);
        if (!item && catalog) {
            return `
                <details class="asset-insurance-academy-card" ${index === 0 ? 'open' : ''}>
                    <summary><strong>${assetEscape(insuranceCatalogRamo(catalog))}</strong><span>${assetEscape(insuranceCatalogMacro(catalog))}</span></summary>
                    <dl>
                        <div><dt>Que cubre</dt><dd>${assetEscape(insuranceCatalogValue(catalog, 'Objeto / cobertura básica', 'Objeto / cobertura basica'))}</dd></div>
                        <div><dt>Principales</dt><dd>${assetEscape(insuranceCatalogValue(catalog, 'Coberturas principales'))}</dd></div>
                        <div><dt>Complementarias</dt><dd>${assetEscape(insuranceCatalogValue(catalog, 'Coberturas complementarias habituales'))}</dd></div>
                        <div><dt>Naturaleza</dt><dd>${assetEscape(insuranceCatalogValue(catalog, 'Naturaleza'))}</dd></div>
                        <div><dt>Caracter</dt><dd>${assetEscape(insuranceCatalogValue(catalog, 'Carácter', 'Caracter'))}</dd></div>
                        <div><dt>Advertencia</dt><dd>${assetEscape(insuranceCatalogValue(catalog, 'Observación', 'Observacion') || 'Verificar clausulado, exclusiones, limites, deducibles y vigencia.')}</dd></div>
                    </dl>
                </details>
            `;
        }
        return `
            <details class="asset-insurance-academy-card" ${index === 0 ? 'open' : ''}>
                <summary><strong>${assetEscape(item.title || product)}</strong><span>${assetEscape((insuranceCoverageProfiles[product] || []).slice(0, 4).join(' / ') || 'Coberturas por definir')}</span></summary>
                <dl>
                    <div><dt>Que es</dt><dd>${assetEscape(item.what)}</dd></div>
                    <div><dt>Puede incluir</dt><dd>${assetEscape(item.includes)}</dd></div>
                    <div><dt>Bienes o exposicion</dt><dd>${assetEscape(item.assets)}</dd></div>
                    <div><dt>Valor asegurado</dt><dd>${assetEscape(item.value)}</dd></div>
                    <div><dt>Debe revisar</dt><dd>${assetEscape(item.analyst)}</dd></div>
                    <div><dt>Soportes</dt><dd>${assetEscape(item.supports)}</dd></div>
                </dl>
            </details>
        `;
    }).join('');
    return `<div class="asset-insurance-academy-tabs" role="tablist" aria-label="Ramos seleccionados">${tabs}</div>${activeCard}`;
};

const updateInsuranceProductAcademy = (form) => {
    const target = form.querySelector('[data-insurance-product-academy]');
    if (!(target instanceof HTMLElement)) {
        return;
    }
    const products = [...form.querySelectorAll('[data-asset-insurance-type-toggle]:checked')]
        .filter((input) => input instanceof HTMLInputElement)
        .map((input) => input.value)
        .filter(Boolean);
    target.innerHTML = insuranceProductAcademyHtml(products, form);
};

const updateInsuranceProductVisibility = (form) => {
    form.querySelectorAll('[data-asset-insurance-product-chip]').forEach((chip) => {
        if (!(chip instanceof HTMLElement)) {
            return;
        }
        chip.hidden = false;
        const input = chip.querySelector('input');
        if (input instanceof HTMLInputElement) {
            input.disabled = false;
            chip.classList.toggle('is-selected', input.checked);
        }
    });
    const selected = [...form.querySelectorAll('[data-asset-insurance-type-toggle]:checked')]
        .map((input) => input instanceof HTMLInputElement ? input.value : '')
        .filter(Boolean);
    const hidden = form.elements['detalle[tipo_seguro]'];
    if (hidden instanceof HTMLInputElement) {
        hidden.value = joinInsuranceSelection(selected);
    }
    syncPolicyTypeFromDetail(form);
    updateInsuranceProductAcademy(form);
};

const splitInsuranceSelection = (value) => String(value ?? '')
    .split(/\s*(?:\||,|;)\s*/)
    .map((item) => item.trim())
    .filter(Boolean);

const joinInsuranceSelection = (values = []) => [...new Set(values.filter(Boolean))].join(' | ');

const assetInsurancePolicyFields = ['ano', 'tipo_documento', 'ramo', 'aseguradora', 'contacto_nombre', 'contacto_correo', 'contacto_celular', 'intermediario', 'agencia_expedidora', 'codigo_agencia', 'numero_poliza', 'numero_emision', 'numero_pago_electronico', 'modalidad_facturacion', 'coaseguro', 'tomador', 'asegurado', 'beneficiario', 'direccion_riesgo', 'ciudad_riesgo', 'actividad_riesgo', 'tipo_riesgo', 'fecha_inicio', 'fecha_fin', 'fecha_renovacion', 'prima_neta', 'iva', 'gastos_expedicion', 'prima_total', 'valor_asegurado_total', 'deducible_general', 'forma_pago', 'numero_cuotas', 'clausulado', 'anexos_endosos', 'exclusiones_relevantes', 'texto_aclaratorio', 'asistencias', 'estado', 'adoptada', 'fecha_adopcion', 'criterio_adopcion', 'cotizacion_matriz', 'alcance_poliza', 'grupo_poliza', 'metodo_distribucion', 'coeficiente_participacion', 'numero_unidades_cubiertas', 'prima_asignada', 'observaciones_distribucion', 'soporte', 'observaciones'];

const assetInsuranceCoverageCurrentFields = ['contratado_actual', 'valor_actual', 'limite_evento_actual', 'limite_vigencia_actual', 'sublimite_actual', 'deducible_actual', 'deducible_porcentaje_actual', 'deducible_minimo_actual', 'indice_variable_actual', 'tasa_actual', 'prima_actual', 'observaciones_actuales', 'renovacion_solicitada'];
const assetInsuranceCoverageFields = ['ano', 'numero_poliza', 'ramo', 'cobertura', 'riesgo_cubierto', ...assetInsuranceCoverageCurrentFields, 'valor_asegurado', 'limite_evento', 'limite_vigencia', 'porcentaje_invar', 'indice_variable', 'sublimite', 'tasa', 'prima', 'deducible', 'deducible_porcentaje', 'deducible_minimo', 'fuente_valor_asegurado', 'fecha_inicio', 'fecha_fin', 'fecha_renovacion', 'observaciones'];

const selectedInsuranceProductsFromForm = (form) => {
    const selected = [...form.querySelectorAll('[data-asset-insurance-type-toggle]:checked')]
        .filter((input) => input instanceof HTMLInputElement)
        .map((input) => input.value)
        .filter(Boolean);
    const detailValue = form.elements['detalle[tipo_seguro]']?.value || '';
    return [...new Set([...selected, ...splitInsuranceSelection(detailValue)])];
};

const coverageRowKey = (row) => `${row.ramo || ''}::${row.cobertura || ''}`;
const normalizedCoverageRowKey = (row = {}) => [
    normalizeInsuranceText(normalizeInsuranceRamo(row.ramo || '')),
    normalizeInsuranceText(row.cobertura || ''),
].join('::');

const coverageToggleValue = (product, coverage) => `${product}::${coverage}`;

const parseCoverageToggleValue = (value) => {
    const [product, ...coverageParts] = String(value || '').split('::');
    return {
        product: product || '',
        coverage: coverageParts.join('::') || '',
    };
};

const activeInsuranceProduct = (form, products, key) => {
    if (products.length === 0) {
        form.dataset[key] = '';
        return '';
    }
    const active = form.dataset[key] || products[0];
    if (!products.includes(active)) {
        form.dataset[key] = products[0];
        return products[0];
    }
    return active;
};

const insuranceProductTabsHtml = (products, active, target) => {
    if (products.length === 0) {
        return '';
    }
    return `
        <div class="asset-insurance-product-tabs" role="tablist" aria-label="Ramos seleccionados">
            ${products.map((product) => `
                <button type="button" role="tab" class="${product === active ? 'is-active' : ''}" data-asset-insurance-product-tab="${assetEscape(target)}" data-product="${assetEscape(product)}">
                    ${assetEscape(product)}
                </button>
            `).join('')}
        </div>
    `;
};

const insurancePolicyPdfFieldsForProducts = (products = []) => {
    const normalizedProducts = new Set(products.map((product) => normalizeInsuranceText(normalizeInsuranceRamo(product))));
    return insurancePolicyPdfFieldMatrix.filter((row) => row.ramos.some((ramo) => normalizedProducts.has(normalizeInsuranceText(normalizeInsuranceRamo(ramo)))));
};

const insurancePolicyPdfFieldMatrixHtml = (products = [], mode = 'actual') => {
    const rows = insurancePolicyPdfFieldsForProducts(products);
    const isRequestMode = mode === 'request';
    const title = isRequestMode
        ? 'Guia para configurar la nueva cobertura'
        : 'Campos que debe leer el analista en la poliza actual';
    const emptyText = isRequestMode
        ? 'Selecciona los ramos del activo para ver que campos debe pedir, mejorar o confirmar en la nueva cotizacion.'
        : 'Selecciona los ramos del activo para ver que campos debe leer el analista en la caratula y el clausulado.';
    if (rows.length === 0) {
        return `
            <div class="asset-insurance-policy-reading-empty">
                ${assetEscape(emptyText)}
            </div>
        `;
    }
    return `
        <details class="asset-insurance-policy-reading" open>
            <summary>
                <strong>${assetEscape(title)}</strong>
                <span>${rows.length} reglas segun ramo/cobertura</span>
            </summary>
            <div class="asset-insurance-policy-reading-table" role="table" aria-label="Matriz tecnica de lectura de poliza">
                <div class="asset-insurance-policy-reading-head" role="row">
                    <span>Cobertura</span>
                    <span>Objeto asegurable</span>
                    <span>${isRequestMode ? 'Campos a solicitar/mejorar' : 'Campos de la caratula'}</span>
                    <span>Fuente</span>
                    <span>Alerta del analista</span>
                </div>
                ${rows.map((row) => `
                    <div class="asset-insurance-policy-reading-row" role="row">
                        <strong>${assetEscape(row.cobertura)}</strong>
                        <span>${assetEscape(row.objeto)}</span>
                        <span>${assetEscape(row.campos)}</span>
                        <span>${assetEscape(row.fuente)}</span>
                        <span>${assetEscape(row.alerta)}</span>
                    </div>
                `).join('')}
            </div>
        </details>
    `;
};

const coverageSupportCategories = (rows = []) => [...new Set(rows
    .flatMap((row) => coverageValueCategoriesFor(row.cobertura || ''))
    .filter(Boolean))];

const coverageSupportTabsHtml = (categories = []) => {
    if (categories.length === 0) {
        return `
            <div class="asset-insurance-support-tabs">
                <span>No requiere relacion de bienes; define limite por evento/vigencia, deducible y fuente contractual.</span>
            </div>
        `;
    }
    return `
        <div class="asset-insurance-support-tabs" aria-label="Datos solicitados">
            ${categories.map((category, index) => `
                <span class="${index === 0 ? 'is-active' : ''}">${assetEscape(category)}</span>
            `).join('')}
        </div>
    `;
};

const liabilityExposureOptions = [
    { key: 'predios_labores', label: 'Predios, labores y operaciones', category: 'Predios, labores y operaciones', coverage: 'Responsabilidad civil extracontractual', unit: 'limite' },
    { key: 'visitantes_terceros', label: 'Visitantes y terceros', category: 'Visitantes y terceros', coverage: 'Lesiones o muerte de terceros', unit: 'limite' },
    { key: 'danos_terceros', label: 'Danos materiales a terceros', category: 'Danos materiales a terceros', coverage: 'Danos materiales a terceros', unit: 'limite' },
    { key: 'empleados_patronal', label: 'Empleados / RC patronal', category: 'Empleados / RC patronal', coverage: 'Responsabilidad civil patronal', unit: 'limite' },
    { key: 'contratistas_cruzada', label: 'Contratistas / RC cruzada', category: 'Contratistas / RC cruzada', coverage: 'Responsabilidad cruzada', unit: 'limite' },
    { key: 'parqueaderos', label: 'Parqueaderos', category: 'Parqueaderos', coverage: 'RC parqueaderos', unit: 'limite' },
    { key: 'gastos_defensa', label: 'Gastos de defensa', category: 'Gastos de defensa', coverage: 'Gastos de defensa', unit: 'limite' },
    { key: 'actividad_profesional', label: 'Actividad profesional', category: 'Actividad profesional', coverage: 'Responsabilidad profesional', unit: 'limite' },
];

const insuranceAssetOptionByKey = (key) => [...insuranceAssetOptions, ...liabilityExposureOptions].find((item) => item.key === key);

const insuranceExposureOptionsForProduct = (product, coverageRows = []) => {
    const text = normalizeInsuranceText(product);
    if (text.includes('responsabilidad')) {
        return liabilityExposureOptions;
    }
    const categories = coverageSupportCategories(coverageRows);
    const selectedCategories = categories.length > 0 ? categories : insuranceAssetOptions.map((item) => item.category);
    return insuranceAssetOptions.filter((item) => selectedCategories.includes(item.category));
};

const insuranceCoverageRequestTableHtml = (rows = []) => {
    if (rows.length === 0) {
        return `
            <div class="asset-insurance-request-empty">
                Primero marca las coberturas requeridas. Despues esta tabla mostrara la base que se enviara a cotizar.
            </div>
        `;
    }
    const guidanceItems = [...new Map(rows.map((row) => [row.cobertura || '', insuranceValueGuidanceFor(row.cobertura || '')])).values()];
    return `
        <div class="asset-insurance-value-help">
            ${guidanceItems.slice(0, 4).map((item) => `
                <span title="${assetEscape(item.tooltip)}">
                    <strong>${assetEscape(item.title)}</strong>
                    ${assetEscape(item.alert)}
                </span>
            `).join('')}
        </div>
        <div class="asset-insurance-request-table" role="table" aria-label="Solicitud de coberturas para cotizar">
            <div class="asset-insurance-request-head" role="row">
                <span>Cobertura</span>
                <span>Dato base</span>
                <span>Valor o limite solicitado</span>
                <span>Fuente del valor</span>
                <span>Observacion para cotizar</span>
            </div>
            ${rows.map((row) => {
                const insuredValue = assetNumber(row.valor_asegurado);
                const limit = row.limite_evento || row.sublimite || '';
                const requestedValue = insuredValue > 0 ? assetMoney(insuredValue) : (limit || 'Definir limite solicitado');
                const guidance = insuranceValueGuidanceFor(row.cobertura || '');
                return `
                    <div class="asset-insurance-request-row" role="row">
                        <strong>${assetHelpButton(guidance.tooltip)}${assetEscape(row.cobertura || 'Cobertura por definir')}<small>${assetEscape(guidance.title)}</small></strong>
                        <span>${assetEscape(guidance.assets)}<small>${assetEscape(guidance.basis)}</small></span>
                        <label>
                            <input
                                inputmode="decimal"
                                value="${assetEscape(assetMoneyPlain(row.valor_asegurado) || row.valor_asegurado || '')}"
                                placeholder="${assetEscape(requestedValue)}"
                                data-money-format
                                data-insurance-request-field="valor_asegurado"
                                data-product="${assetEscape(row.ramo || '')}"
                                data-coverage="${assetEscape(row.cobertura || '')}"
                            >
                        </label>
                        <label>
                            <input
                                value="${assetEscape(row.fuente_valor_asegurado ?? '')}"
                                placeholder="${assetEscape(guidance.source)}"
                                data-insurance-request-field="fuente_valor_asegurado"
                                data-product="${assetEscape(row.ramo || '')}"
                                data-coverage="${assetEscape(row.cobertura || '')}"
                            >
                        </label>
                        <label>
                            <input
                                value="${assetEscape(row.observaciones ?? '')}"
                                placeholder="${assetEscape(guidance.alert)}"
                                data-insurance-request-field="observaciones"
                                data-product="${assetEscape(row.ramo || '')}"
                                data-coverage="${assetEscape(row.cobertura || '')}"
                            >
                        </label>
                    </div>
                `;
            }).join('')}
        </div>
    `;
};

const selectedInsuranceStripHtml = (form) => {
    const products = selectedInsuranceProductsFromForm(form);
    if (products.length === 0) {
        return '<div class="asset-insurance-selected-strip"><strong>Ramos seleccionados</strong><span>Marca primero los seguros a incorporar al activo.</span></div>';
    }
    return `
        <div class="asset-insurance-selected-strip">
            <strong>Ramos seleccionados</strong>
            <div>${products.map((product) => `<span>${assetEscape(product)}</span>`).join('')}</div>
        </div>
    `;
};

const coverageOptionsForPolicy = (ramo, allCoverages, form = null) => {
    const selectedProfiles = splitInsuranceSelection(ramo);
    const academyCoverages = form ? insuranceAcademyData(form).coverages : [];
    const coverageNames = selectedProfiles.flatMap((item) => {
        const normalized = normalizeInsuranceRamo(item);
        const fromAcademy = (form ? insuranceAcademyRowsForRamo(form, item) : academyCoverages
            .filter((row) => normalizeInsuranceText(row.Ramo) === normalizeInsuranceText(normalized)))
            .map((row) => row.Cobertura)
            .filter(Boolean);
        return fromAcademy.length > 0 ? fromAcademy : (insuranceCoverageProfiles[item] || insuranceCoverageProfiles[normalized] || []);
    });
    const unique = [...new Set(coverageNames)];
    return unique.length > 0 ? unique.filter((item) => allCoverages.includes(item) || academyCoverages.some((row) => row.Cobertura === item)) : [];
};

const coverageAssetsLabel = (coverage) => {
    const item = insuranceCoverageAcademy[coverage];
    const assets = item?.assets || coverageValueCategoriesFor(coverage);
    return assets.length > 0 ? assets.join(' / ') : 'Limite o servicio, no sale de inventario de bienes';
};

const coverageReviewLabel = (coverage) => insuranceCoverageAcademy[coverage]?.review || 'Revisar clausulado, deducible, sublimite y soporte.';

const coverageBasisLabel = (coverage) => insuranceCoverageAcademy[coverage]?.basis || 'Definir valor, limite o sublimite con soporte documental.';

const insuranceValueGuidanceFor = (coverage) => {
    const text = String(coverage || '').toLowerCase();
    const academy = insuranceCoverageAcademy[coverage] || {};
    const assets = academy.assets || coverageValueCategoriesFor(coverage);
    let title = 'Valor asegurable con soporte';
    let basis = academy.basis || 'Define el valor, limite o sublimite con soporte documental antes de pedir cotizacion.';
    let source = 'Soporta con avaluo, factura, cotizacion, inventario, contrato, acta o criterio documentado.';
    let alert = academy.review || 'Revisa deducibles, sublimites, exclusiones y fuente del valor.';

    if (text.includes('incendio') || text.includes('rayo') || text.includes('explosion') || text.includes('humo') || text.includes('agua') || text.includes('anegacion') || text.includes('inundacion')) {
        title = 'Dano material a valor de reposicion';
        basis = 'Usa valor de reposicion: costo de reconstruir o reponer el bien hoy. En inmuebles calcula m2 asegurables por valor de reposicion por m2.';
        source = 'Fuente: avaluo, presupuesto de construccion, cotizacion, inventario de contenidos o relacion valorizada.';
        alert = 'Alerta: el terreno no se asegura. Separa construccion, contenidos, equipos, mercancias y mejoras para evitar infra o supraseguro.';
    }
    if (text.includes('terremoto') || text.includes('temblor') || text.includes('erupcion')) {
        title = 'Valor de reposicion para evento catastrofico';
        basis = 'Toma el mismo valor asegurable de reposicion de construccion y contenidos declarados, sin incluir terreno.';
        source = 'Fuente: avaluo, areas, valor m2, inventario y certificado de PH si aplica.';
        alert = 'Alerta: terremoto suele tener deducible especial por evento o por articulo afectado; confirmar sublimites.';
    }
    if (text.includes('responsabilidad') || text.includes('rc ') || text.includes('tercero') || text.includes('lesion') || text.includes('muerte') || text.includes('defensa')) {
        title = 'Limite de responsabilidad';
        basis = 'No sale de inventario. Define limite por evento y vigencia segun exposicion: visitantes, empleados, contratistas, parqueaderos, contratos y actividad.';
        source = 'Fuente: acta de criterio, contratos, exigencias de terceros, historico de siniestros, aforo, operacion y apetito de riesgo.';
        alert = 'Alerta: confirmar si incluye predios/labores/operaciones, patronal, cruzada, parqueaderos, gastos medicos y defensa.';
    }
    if (text.includes('maquinaria') || text.includes('rotura') || text.includes('dano interno')) {
        title = 'Inventario item por item';
        basis = 'Cada maquina o equipo debe tener descripcion, serial, fecha de adquisicion, valor de compra, valor de reposicion, fuente y fecha de consulta.';
        source = 'Fuente: factura, cotizacion actual, proveedor, mantenimiento, ficha tecnica o avaluo especializado.';
        alert = 'Alerta: revisar antiguedad, mantenimiento, demerito, desgaste, garantias y exclusiones de dano interno.';
    }
    if (text.includes('electronico') || text.includes('corriente') || text.includes('debil') || text.includes('ups') || text.includes('cctv')) {
        title = 'Equipo electronico y corriente debil';
        basis = 'Relaciona UPS, CCTV, redes, telefonia, computadores, controles y equipos electronicos a valor de reposicion.';
        source = 'Fuente: cotizacion vigente, factura, proveedor, inventario con serial y fecha de consulta.';
        alert = 'Alerta: confirmar protecciones electricas, portabilidad, software, datos, mantenimiento y dano interno.';
    }
    if (text.includes('hurto') || text.includes('sustraccion')) {
        title = 'Inventario valorizado contra sustraccion';
        basis = 'Lista bienes asegurados por item: descripcion, cantidad, ubicacion, serial, valor de reposicion y fuente.';
        source = 'Fuente: inventario fisico, factura, cotizacion, registro fotografico o avaluo para obras/joyas.';
        alert = 'Alerta: revisar modalidad cubierta, alarma, cerraduras, vigilancia, dinero, joyas y bienes excluidos.';
    }
    if (text.includes('lucro') || text.includes('arrendamiento dejado') || text.includes('perdida de arrendamiento') || text.includes('canon') || text.includes('canones')) {
        title = 'Perdida financiera o canon';
        basis = 'Para perdida de arrendamiento: suma canon mensual por unidad x meses de indemnizacion solicitados x porcentaje de participacion si aplica. Para negocio: utilidad bruta, ingresos o gastos permanentes por el periodo solicitado.';
        source = 'Fuente: contrato de arrendamiento, canon mensual por unidad, participacion, historico de ingresos, estados financieros y fecha de vigencia/renovacion.';
        alert = 'Alerta: normalmente exige dano material amparado como evento detonante; pide a la aseguradora confirmar meses maximos, deducible temporal e indice variable/IPC si se pacta.';
    }
    if (text.includes('vehiculo') || text.includes('auto') || text.includes('perdida total') || text.includes('perdida parcial')) {
        title = 'Valor comercial del vehiculo';
        basis = 'Usa valor comercial soportado, usualmente guia Fasecolda, factura, avaluo o cotizacion segun clase y estado.';
        source = 'Fuente: Fasecolda, factura, tarjeta de propiedad, kilometraje, accesorios y cotizacion de mercado.';
        alert = 'Alerta: declarar uso, conductor, accesorios, prendas, deducible y asistencia.';
    }

    return {
        title,
        basis,
        source,
        alert,
        assets: assets.length > 0 ? assets.join(' / ') : 'No requiere relacion de bienes; define limite o servicio.',
        tooltip: `${title}. ${basis} ${source} ${alert}`,
    };
};

const insuranceCoverageMatrixHtml = (products = [], selected = new Set(), form = null) => {
    const academyCoverages = form ? insuranceAcademyData(form).coverages : [];
    const normalizedSelected = new Set([...selected].map((key) => {
        const parsed = parseCoverageToggleValue(key);
        return normalizedCoverageRowKey({ ramo: parsed.product, cobertura: parsed.coverage });
    }));
    const coverageIsSelected = (product, coverage) => selected.has(coverageToggleValue(product, coverage))
        || normalizedSelected.has(normalizedCoverageRowKey({ ramo: product, cobertura: coverage }));
    const selectedProducts = products.filter((product) => {
        const normalized = normalizeInsuranceRamo(product);
        return insuranceCoverageProfiles[product]?.length
            || insuranceCoverageProfiles[normalized]?.length
            || academyCoverages.some((row) => normalizeInsuranceText(row.Ramo) === normalizeInsuranceText(normalized));
    });
    if (selectedProducts.length === 0) {
        return '<div class="asset-coverage-matrix-empty">Selecciona primero los seguros a incorporar al activo para ver las coberturas que puede requerir cada ramo.</div>';
    }
    return selectedProducts.map((product, productIndex) => {
        const normalized = normalizeInsuranceRamo(product);
        const academyRows = form ? insuranceAcademyRowsForRamo(form, product) : academyCoverages.filter((row) => normalizeInsuranceText(row.Ramo) === normalizeInsuranceText(normalized));
        const coverages = [...new Set(academyRows.length > 0 ? academyRows.map((row) => row.Cobertura).filter(Boolean) : (insuranceCoverageProfiles[product] || insuranceCoverageProfiles[normalized] || []))];
        const coverageMeta = (coverage) => academyRows.find((row) => row.Cobertura === coverage) || null;
        return `
            <section class="asset-coverage-matrix-card">
                <div class="asset-coverage-matrix-header">
                    <strong>${assetEscape(product)}</strong>
                    <span>${coverages.length} amparos sugeridos</span>
                </div>
                <div class="asset-coverage-matrix-table">
                    ${coverages.map((coverage) => `
                        <label class="asset-coverage-matrix-row ${coverageIsSelected(product, coverage) ? 'is-selected' : ''}">
                            <input type="checkbox" data-asset-coverage-toggle value="${assetEscape(coverageToggleValue(product, coverage))}" ${coverageIsSelected(product, coverage) ? 'checked' : ''}>
                            <span class="asset-coverage-matrix-name">${assetEscape(coverage)}</span>
                            <span>${assetEscape(coverageAssetsLabel(coverage))}</span>
                            <span>${assetEscape(coverageBasisLabel(coverage))}</span>
                            <span>${assetEscape(coverageMeta(coverage)?.['Descripción práctica'] || coverageReviewLabel(coverage))}</span>
                        </label>
                    `).join('')}
                </div>
            </section>
        `;
    }).join('');
};

const syncDepreciationValues = (form) => {
    const monthly = form.elements['detalle[depreciacion_mensual]'];
    const annual = form.elements['detalle[depreciacion_anual]'];
    const building = form.elements['detalle[valor_contable_construccion]'];
    const total = form.elements['detalle[valor_contable_total]'] || form.elements['detalle[valor_contable_inicial]'];
    const accumulated = form.elements['detalle[depreciacion_acumulada]'];
    const net = form.elements['detalle[valor_neto_contable]'];
    if (monthly instanceof HTMLInputElement && annual instanceof HTMLInputElement) {
        setAutoMoney(annual, assetNumeric(monthly.value) * 12);
    }
    if (accumulated instanceof HTMLInputElement && net instanceof HTMLInputElement) {
        const buildingBase = building instanceof HTMLInputElement ? assetNumeric(building.value) : 0;
        const fallbackBase = total instanceof HTMLInputElement ? assetNumeric(total.value) : 0;
        const depreciableBase = buildingBase > 0 ? buildingBase : fallbackBase;
        const value = depreciableBase - assetNumeric(accumulated.value);
        setAutoMoney(net, value);
    }
};

const participantNameOptions = (participants, selectedType, selectedName) => {
    const source = Array.isArray(participants) ? participants : [];
    const options = source
        .filter((participant) => !selectedType || participant.tipo === selectedType)
        .map((participant) => participant.nombre)
        .filter(Boolean);
    const unique = [...new Set(options)];
    if (selectedName && !unique.includes(selectedName)) {
        unique.unshift(selectedName);
    }
    return unique.map((name) => `<option value="${assetEscape(name)}">${assetEscape(name)}</option>`).join('');
};

const assetPlaceholderOption = (selectedValue = '') => `<option value="" disabled ${selectedValue ? '' : 'selected'}>Seleccione...</option>`;

const assetHelpText = {
    tipo_activo: 'Elige la naturaleza principal del bien. Esta seleccion cambia los campos que se deben diligenciar.',
    codigo: 'El sistema lo asigna automaticamente para identificar el activo dentro del protocolo.',
    nombre_descripcion: 'Nombre claro del activo. En intangibles se completa desde los campos especificos para evitar duplicidad.',
    identificador: 'Referencia unica del activo: matricula, placa, cuenta, titulo, dominio, repositorio o registro.',
    titular: 'Quien aparece como propietario, titular, tomador o responsable en el soporte principal.',
    ambito_titular: 'Define si el activo es personal, familiar, empresarial, compartido, mixto o pendiente de confirmar.',
    empresa_relacionada: 'Empresa vinculada con el activo por uso, titularidad, explotacion, administracion o registro contable.',
    moneda: 'Moneda en la que se registran los valores economicos.',
    valor_adquisicion: 'Valor historico de compra, aporte, escritura, contrato, desarrollo o ingreso al patrimonio.',
    fecha_adquisicion: 'Fecha en la que se compro, constituyo, aporto, desarrollo o incorporo el activo.',
    valor_actual: 'Valor vigente estimado segun la naturaleza del activo y su soporte de valoracion.',
    fecha_corte_valor: 'Fecha a la que corresponde el valor actual registrado.',
    metodo_valoracion: 'Fuente usada para estimar el valor actual: avaluo, extracto, certificacion, mercado, costo u otra.',
    estado_soporte: 'Estado de disponibilidad documental: completo, pendiente, vencido, por confirmar u otro.',
    nivel_riesgo: 'Alerta preliminar sobre titularidad, soportes, vencimientos, valoracion o continuidad del activo.',
    valor_contable_terreno: 'Valor contable separado del terreno. En inmuebles se informa, pero no se toma como base depreciable.',
    valor_contable_construccion: 'Base contable depreciable del inmueble. La depreciacion debe calcularse sobre la construccion, no sobre terreno mas construccion.',
    valor_contable_total: 'Suma informativa de terreno y construccion. Sirve para conciliacion contable, pero no es la base de depreciacion del inmueble.',
    depreciacion_acumulada: 'Depreciacion acumulada de la construccion. El terreno se excluye porque no se deprecia.',
    valor_neto_contable: 'Valor contable neto de la construccion: valor contable construccion menos depreciacion acumulada.',
    indice_reajuste: 'Define la regla pactada para actualizar el canon: IPC, IPC mas puntos, porcentaje fijo, UVR, SMMLV u otra.',
    puntos_sobre_ipc: 'Si el contrato dice IPC mas puntos, escribe solo los puntos adicionales. Ejemplo: 2 significa IPC + 2 puntos.',
    subcategoria: 'Clasifica el intangible para mostrar solo los campos que aplican a su naturaleza.',
    nombre_aplicacion: 'Nombre comercial, tecnico o interno con el que se reconoce el activo.',
    codigo_interno: 'Codigo usado por la empresa para ubicar el activo, proyecto, repositorio, dominio o registro.',
    descripcion_funcional: 'Explica para que sirve, quien lo usa y que proceso soporta.',
    empresa_usuaria: 'Empresa que usa el activo, aunque no necesariamente sea la propietaria juridica.',
    titular_juridico: 'Persona o sociedad que tiene documentada la propiedad, cesion, registro o contrato.',
    propietario_economico: 'Persona o empresa que pago el desarrollo, compra, licencia o mantenimiento del activo.',
    area_responsable: 'Area o persona que administra el activo en la operacion diaria.',
    estado_tecnologico: 'Situacion actual: produccion, desarrollo, pruebas, suspendido u otro estado operativo.',
    fecha_creacion: 'Fecha aproximada de creacion, compra, registro o inicio de uso.',
    modulos: 'Partes funcionales del sistema o aplicativo, por ejemplo CRM, contratos, facturacion o reportes.',
    bases_datos: 'Bases que almacenan informacion del activo: nombre, motor, ubicacion y responsable.',
    dominios: 'Dominios o subdominios asociados, por ejemplo empresa.com o app.empresa.com.',
    repositorios: 'Ubicacion del codigo fuente: GitHub, GitLab, Bitbucket, servidor interno u otro repositorio.',
    apis_integraciones: 'Servicios conectados al activo: pasarelas, firmas, CRM, IA, mapas, bancos u otros.',
    servidores: 'Hosting, servidor, nube o proveedor donde opera el activo.',
    manuales: 'Manuales de usuario, manual tecnico, documentacion de instalacion o guias de soporte.',
    versiones: 'Version actual o historial relevante de versiones.',
    ingeniero_desarrollador: 'Persona o empresa que construyo o mantiene el desarrollo.',
    tipo_vinculacion: 'Relacion contractual del desarrollador: empleado, contratista, proveedor, socio u otro.',
    quien_pago_desarrollo: 'Quien asumio economicamente el desarrollo o la licencia.',
    existe_contrato: 'Indica si hay contrato firmado que soporte desarrollo, licencia, prestacion de servicios o mantenimiento.',
    existe_cesion_derechos: 'Indica si el autor o desarrollador cedio formalmente los derechos patrimoniales.',
    registro_dnda: 'Indica si el software, obra o documento esta registrado ante la Direccion Nacional de Derecho de Autor.',
    licencias_terceros: 'Librerias, plantillas, plugins o servicios externos usados y sus condiciones.',
    ubicacion_codigo_fuente: 'Ruta o proveedor donde esta guardado el codigo fuente.',
    administrador_repositorio: 'Persona que tiene control administrativo del repositorio.',
    copias_seguridad: 'Confirma si existen respaldos, donde estan y quien los controla.',
    acceso_empresa_codigo: 'Indica si la empresa puede acceder al codigo sin depender solo del desarrollador.',
    documentacion_disponible: 'Confirma si existe documentacion suficiente para operar o continuar el sistema.',
    riesgo_dependencia_desarrollador: 'Mide que tan grave seria perder al desarrollador o proveedor actual.',
    valor_desarrollo_acumulado: 'Costo acumulado invertido en construir, adaptar o implementar el activo.',
    ingresos_generados: 'Ingresos atribuibles al activo en el periodo que se este analizando.',
    costos_mantenimiento: 'Costos periodicos de soporte, hosting, licencias, mejoras o administracion.',
    nivel_importancia: 'Importancia del activo para la operacion, ingresos, marca, datos o continuidad.',
    posibilidad_comercializacion: 'Evalua si el activo se podria vender, licenciar, franquiciar o explotar comercialmente.',
    vida_util_estimada: 'Tiempo esperado durante el cual el activo seguira generando utilidad.',
    metodo_valoracion_intangible: 'Metodo usado para estimar valor: costo, ingresos, mercado, reemplazo u otro.',
    riesgo_juridico: 'Riesgo por falta de contrato, cesion, registro, autorizaciones o derechos claros.',
    riesgo_tecnologico: 'Riesgo por obsolescencia, fallas, dependencia tecnica, seguridad o falta de soporte.',
    riesgo_perdida_informacion: 'Riesgo de perder datos, archivos, codigo, accesos o respaldos.',
    plan_contingencia: 'Acciones previstas si falla el proveedor, se pierde acceso o vence el servicio.',
    fecha_ultima_revision: 'Ultima fecha en la que se verifico vigencia, accesos, costos, soporte o riesgos.',
    vencimiento_fecha: 'Fecha critica para renovar, pagar, actualizar o decidir continuidad.',
    vencimiento_frecuencia_pago: 'Periodicidad del pago o renovacion: mensual, trimestral, anual, por consumo o pago unico.',
    vencimiento_costo_periodico: 'Valor que se paga en cada periodo. Este costo alimenta el calendario y ayuda a medir el riesgo de suspension.',
    vencimiento_responsable: 'Persona encargada de revisar y ejecutar la renovacion antes del vencimiento.',
    vencimiento_metodo_pago: 'Medio usado para pagar o renovar; evita que un servicio se suspenda por tarjeta vencida.',
    portal_proveedor: 'Proveedor del portal inmobiliario, por ejemplo Finca Raiz, Metrocuadrado u otro.',
    portal_publicaciones_activas: 'Numero de inmuebles, avisos o publicaciones activas en el portal.',
    firma_proveedor: 'Proveedor de firma electronica o digital usado por la empresa.',
    firma_creditos_disponibles: 'Cantidad de firmas, sobres o creditos disponibles antes de renovar o recargar.',
    suscripcion_servicio: 'Nombre de la herramienta web contratada, por ejemplo CRM, correo, automatizacion o analitica.',
    ia_modelo_servicio: 'Servicio de IA contratado, por ejemplo ChatGPT, Gemini, Claude u otra plataforma.',
    ia_datos_ingresados: 'Tipo de datos que se cargan en la IA; sirve para evaluar confidencialidad y proteccion de datos.',
    logo_archivo_fuente: 'Archivo editable original del logo, por ejemplo AI, PSD, SVG o Figma.',
    logo_cesion_derechos: 'Indica si el disenador cedio formalmente los derechos de uso y explotacion.',
    revista_issn: 'Registro editorial o identificador de la revista/publicacion, si existe.',
    proyecto_objetivo: 'Resultado que busca el proyecto interno y problema que resuelve.',
    proyecto_entregables: 'Productos concretos esperados: documentos, software, campana, modelo, manual o implementacion.',
};

const assetTypeAcademy = {
    'Inmueble': {
        title: 'Inmueble',
        intro: 'Bien raiz urbano o rural: local, oficina, casa, lote, bodega, edificio, finca, parqueadero u otro. Aqui se controla matricula, catastro, tradicion, areas, PH, gravamenes, fiducia, seguros, renta y rentabilidad.',
        scope: 'Titularidad registral, matricula, referencia catastral, valor de escritura, valor catastral, valor comercial, valor contable separado entre terreno y construccion, renta, hipoteca, seguros y vencimientos.',
        document: 'Certificado de tradicion, escritura, predial/catastro, reglamento PH si aplica, avaluo, contrato de arrendamiento, poliza e hipoteca si existe.',
        economic: 'Valor comercial vigente, porcentaje de participacion, canon total, ingreso real mensual, egresos y rentabilidad.',
    },
    'Vehiculo/Maquinaria/Equipo': {
        title: 'Vehiculo, maquinaria o equipo',
        intro: 'Activo movil o productivo identificado por placa, serial, numero interno o referencia. Incluye vehiculos de empresa, maquinaria, equipos tecnologicos y herramientas relevantes.',
        scope: 'Clase, marca, modelo, ubicacion, estado fisico, valor contable, depreciacion, seguros, SOAT, tecnico-mecanica, impuestos, mantenimiento y responsable.',
        document: 'Tarjeta de propiedad, factura o contrato de compra, poliza, SOAT, revision tecnico-mecanica, impuestos, certificado de prenda o gravamen si aplica.',
        economic: 'Valor de adquisicion, valor neto contable, depreciacion mensual/anual, valor comercial, costo de mantenimiento y vencimientos.',
    },
    'Cuenta bancaria/Dinero': {
        title: 'Cuenta bancaria o dinero',
        intro: 'Saldos en bancos, caja, cuentas corrientes, ahorros o equivalentes de efectivo. No se valora como inmueble: se controla por saldo a fecha de corte.',
        scope: 'Entidad financiera, numero de cuenta, titular, moneda, saldo, fecha de corte, restricciones, autorizados y soporte de extracto.',
        document: 'Extracto bancario, certificacion bancaria, soportes de titularidad y autorizaciones de manejo.',
        economic: 'Saldo disponible a fecha de corte, moneda, participacion real y restricciones de disponibilidad.',
    },
    'CDT': {
        title: 'CDT',
        intro: 'Deposito a termino con entidad financiera. Lo clave no es solo el valor, sino tasa, plazo, vencimiento, beneficiario y renovacion.',
        scope: 'Entidad, numero de titulo, titular, valor inicial, tasa, fecha de apertura, fecha de vencimiento, renovacion y beneficiarios.',
        document: 'Certificado CDT, extracto o constancia bancaria, soporte de renovacion y condiciones del producto.',
        economic: 'Valor inicial, valor a redencion, tasa pactada, rendimientos y fecha de vencimiento.',
    },
    'Inversion financiera': {
        title: 'Inversion financiera',
        intro: 'Portafolios, fondos, bonos, acciones de mercado, encargos fiduciarios financieros u otros instrumentos no operativos.',
        scope: 'Entidad, producto, numero de cuenta o contrato, perfil de riesgo, saldo/valor de mercado, rendimientos y restricciones.',
        document: 'Extracto del portafolio, certificado de inversion, contrato del producto y soporte de titularidad.',
        economic: 'Valor de mercado a fecha de corte, rentabilidad, liquidez, riesgo y participacion registrada.',
    },
    'Acciones/Cuotas/Participaciones': {
        title: 'Acciones, cuotas o participaciones',
        intro: 'Participaciones en sociedades o negocios. Si el detalle ya esta en 03_Accionistas, aqui se registra cuando se quiere tratar como activo patrimonial consolidado.',
        scope: 'Empresa, tipo de participacion, porcentaje, numero de acciones/cuotas, origen, restricciones, valoracion y titularidad economica.',
        document: 'Titulo o certificado, libro de accionistas, contrato de cesion, soporte de pago, acuerdo de accionistas y valoracion.',
        economic: 'Valor estimado de la participacion, porcentaje real, metodo de valoracion y restricciones de venta.',
    },
    'Activo intangible/tecnologico/PI': {
        title: 'Activo intangible, tecnologico o propiedad intelectual',
        intro: 'Software, codigo fuente, paginas, portales, dominios, marcas, bases de datos, membresias, IA, logos, revistas, metodologias y conocimiento interno.',
        scope: 'Propietario economico, titular juridico, usuario, componentes, autoria, cesion de derechos, repositorios, accesos, costos, ingresos, renovaciones y continuidad.',
        document: 'Contrato de desarrollo, cesion de derechos, registro DNDA/marca si aplica, facturas, terminos de servicio, repositorio, manuales y politicas de acceso.',
        economic: 'Costo acumulado, valor estimado, ingresos generados, costos de mantenimiento, vencimientos y posibilidad de comercializacion.',
    },
    'Cuenta por cobrar': {
        title: 'Cuenta por cobrar',
        intro: 'Derecho a recibir dinero de un tercero, familiar, empresa relacionada, cliente o socio. Debe controlarse por soporte, vencimiento y cobrabilidad.',
        scope: 'Deudor, origen, monto, fecha, vencimiento, garantia, estado de cobro, riesgo de recuperacion y responsable.',
        document: 'Contrato, factura, pagare, conciliacion, reconocimiento de deuda, garantia o comunicacion de cobro.',
        economic: 'Saldo pendiente, vencimiento, probabilidad de recuperacion, intereses y deterioro si aplica.',
    },
    'Deuda/Pasivo': {
        title: 'Deuda o pasivo',
        intro: 'Obligacion a cargo de una persona, familia o empresa. En patrimonio es tan importante como el activo porque afecta valor neto y riesgo.',
        scope: 'Acreedor, deudor, saldo, tasa, plazo, garantia, codeudores, vencimientos, estado de pago y documentos soporte.',
        document: 'Contrato de credito, pagare, extracto de obligacion, tabla de amortizacion, garantia, hipoteca/prenda y paz y salvo si aplica.',
        economic: 'Saldo pendiente, cuota, tasa, vencimiento, garantia comprometida y efecto sobre patrimonio neto.',
    },
    'Otro activo': {
        title: 'Otro activo',
        intro: 'Usalo solo cuando el activo no encaje claramente en las categorias anteriores. Debe quedar muy bien descrito para clasificarlo despues.',
        scope: 'Identificacion, titular, soporte, valor, participacion, responsable, riesgo y observaciones suficientes.',
        document: 'Documento que pruebe propiedad, existencia, valor o derecho economico sobre el activo.',
        economic: 'Valor estimado, fecha de corte, metodo de valoracion y participacion registrada.',
    },
};

const intangibleCategoryDefinitions = {
    'Aplicaciones y plataformas': 'Sistemas que la empresa usa para operar un proceso completo: clientes, contratos, inmuebles, cartera, documentos o reportes.',
    'Software y codigo fuente': 'Codigo propio o contratado que puede ejecutarse, mantenerse, licenciarse o transferirse.',
    'Modulos independientes': 'Partes separables de un sistema mayor que tienen funcion propia y podrian mantenerse o reutilizarse.',
    'Bases de datos': 'Conjuntos organizados de informacion que tienen valor operativo, comercial, juridico o historico.',
    'Paginas web corporativas': 'Sitios institucionales o comerciales que representan la empresa, marca, servicios o proyectos.',
    'Portales inmobiliarios': 'Cuentas, membresias o publicaciones pagadas donde se ofrecen inmuebles, proyectos o servicios inmobiliarios.',
    'Dominios de internet': 'Nombres web registrados que controlan identidad digital, correos, portales o acceso a sistemas.',
    'Plataformas de firma electronica': 'Servicios usados para firmar documentos, contratos, autorizaciones o tramites digitales.',
    'Productos digitales': 'Activos digitales que se pueden vender o entregar a clientes: cursos, plantillas, herramientas, reportes o licencias.',
    'Membresias web / SaaS': 'Suscripciones a servicios en la nube que soportan la operacion: CRM, correo, diseno, automatizacion, analitica u otros.',
    'Membresias de inteligencia artificial': 'Servicios de IA usados por la empresa, con control de usuarios, datos ingresados, costos y vencimientos.',
    'Marcas y nombres comerciales': 'Signos, nombres, lemas o identificadores usados para distinguir productos, empresas o servicios.',
    'Derechos de autor': 'Obras protegidas como textos, fotografias, manuales, software, piezas graficas, videos o publicaciones.',
    'Disenos, metodologias y modelos': 'Know-how estructurado: modelos de negocio, metodologias, formatos, procedimientos o sistemas de trabajo.',
    'Licencias tecnologicas': 'Derechos de uso sobre software, plugins, librerias o herramientas que no son propiedad plena de la empresa.',
    'APIs e integraciones': 'Conexiones tecnicas entre sistemas que pueden ser criticas para operacion, datos o automatizaciones.',
    'Documentacion tecnica': 'Manuales, arquitectura, instructivos, credenciales controladas o guias para operar y continuar sistemas.',
    'Secretos empresariales y conocimiento interno': 'Informacion no publica que da ventaja o reduce riesgo: procesos, bases, listas, formulas, estrategias o accesos.',
    'Logos e identidad visual': 'Archivos, manuales, derechos y soportes relacionados con logos, imagen corporativa y piezas de marca.',
    'Revistas y publicaciones corporativas': 'Publicaciones, revistas, boletines o contenidos recurrentes creados por la empresa.',
    'Proyectos internos de empresa': 'Iniciativas internas con valor economico u operativo, aunque todavia no sean software, marca o producto final.',
};

const inmuebleRightDefinitions = {
    'Dominio pleno': {
        title: 'Dominio pleno',
        text: 'El titular tiene propiedad completa: uso, goce y disposicion del inmueble, salvo limitaciones registrales como hipotecas, embargos, afectaciones, patrimonio de familia, servidumbres o reglamento PH.',
        support: 'Verifica escritura publica, certificado de tradicion actualizado, predial/catastro y que el titular registrado coincida con la participacion economica reportada.',
        risk: 'Riesgo bajo si titularidad, valor y soportes estan completos; sube si hay gravamenes, litigios, medidas cautelares o diferencias de area/titularidad.',
    },
    'Nuda propiedad': {
        title: 'Nuda propiedad',
        text: 'La persona conserva la propiedad juridica, pero otra persona puede tener el uso y disfrute mediante usufructo. No equivale a disponibilidad economica plena.',
        support: 'Revisa escritura o acto que separo nuda propiedad y usufructo, certificado de tradicion y condiciones de extincion del usufructo.',
        risk: 'Riesgo medio o alto si no esta claro quien recibe ingresos, quien asume gastos, o cuando termina el usufructo.',
    },
    'Usufructo': {
        title: 'Usufructo',
        text: 'Derecho a usar y disfrutar el inmueble o recibir sus frutos, como arrendamientos, sin ser necesariamente propietario pleno.',
        support: 'Debe soportarse con escritura, contrato o registro que indique usufructuario, duracion, condiciones y si es vitalicio o temporal.',
        risk: 'Riesgo alto si se registran ingresos sin aclarar propietario, usufructuario, obligaciones de mantenimiento, impuestos y terminacion.',
    },
    'Posesion': {
        title: 'Posesion',
        text: 'Existe tenencia material con animo de senor y dueno, pero puede no existir titularidad registral. No debe tratarse como dominio pleno.',
        support: 'Documenta origen, tiempo de posesion, pagos de predial/servicios, mejoras, declaraciones, procesos de pertenencia o soportes de tradicion informal.',
        risk: 'Riesgo alto o critico por posible disputa de propiedad, falta de escritura, imposibilidad de vender libremente o incertidumbre del valor patrimonial.',
    },
    'Mejoras': {
        title: 'Mejoras',
        text: 'Se reconoce valor economico invertido en construcciones, adecuaciones o mejoras sobre un inmueble que puede no ser propio.',
        support: 'Soporta facturas, contratos de obra, autorizaciones del propietario, actas de entrega y acuerdo sobre compensacion o retiro de mejoras.',
        risk: 'Riesgo alto si no hay autorizacion o si no esta claro si las mejoras pertenecen al titular del suelo, al arrendatario o a quien invirtio.',
    },
    'Derecho fiduciario': {
        title: 'Derecho fiduciario',
        text: 'El inmueble puede estar dentro de una fiducia o patrimonio autonomo; lo que se registra es el derecho economico o beneficiario, no necesariamente el dominio directo.',
        support: 'Revisa contrato fiduciario, instrucciones, certificado fiduciario, beneficiarios, porcentajes, condiciones y facultades de disposicion.',
        risk: 'Riesgo medio/alto si no estan claros beneficiarios, porcentajes, condiciones de transferencia, administracion o restricciones de venta.',
    },
    'Arrendatario': {
        title: 'Arrendatario',
        text: 'No es propiedad patrimonial del arrendatario; se controla como derecho de uso, obligacion contractual, ubicacion operativa o fuente de costo/ingreso segun el caso.',
        support: 'Debe existir contrato de arrendamiento, otrosies, actas, canon, vigencia, renovacion, garantias, deposito y obligaciones a cargo de cada parte.',
        risk: 'Riesgo medio/alto si el negocio depende del inmueble, si hay renovacion proxima, canon sin actualizar, mejoras no reconocidas o falta de contrato.',
    },
    'Otro': {
        title: 'Otro derecho',
        text: 'Usalo solo cuando el derecho no encaja en las categorias principales. La observacion debe explicar con precision la naturaleza juridica y economica.',
        support: 'Adjunta el documento que origina el derecho y describe titular, beneficiario, plazo, restricciones, valor economico y obligaciones.',
        risk: 'Riesgo por confirmar hasta que se clasifique y soporte documentalmente.',
    },
    'Por confirmar': {
        title: 'Por confirmar',
        text: 'No se debe asumir propiedad ni renta real hasta validar el certificado de tradicion, escritura, contrato o soporte aplicable.',
        support: 'Solicita certificado de tradicion actualizado, escritura, predial/catastro y cualquier contrato o documento que explique el derecho.',
        risk: 'Riesgo alto por defecto mientras no se confirme la naturaleza del derecho.',
    },
};

const assetHelpButton = (text) => text
    ? `<button type="button" class="asset-help" aria-label="Ayuda: ${assetEscape(text)}" title="${assetEscape(text)}">?</button>`
    : '';

const enhanceStaticAssetHelp = (form) => {
    Object.entries(assetHelpText).forEach(([name, text]) => {
        const field = form.elements[name];
        const element = field instanceof RadioNodeList ? field[0] : field;
        const label = element instanceof HTMLElement ? element.closest('label') : null;
        if (label instanceof HTMLElement && !label.querySelector('.asset-help')) {
            label.insertAdjacentHTML('afterbegin', assetHelpButton(text));
        }
    });
};

const updateAssetTypeAcademy = (form) => {
    const panel = form.querySelector('[data-asset-type-academy]');
    if (!(panel instanceof HTMLElement)) {
        return;
    }
    const type = form.elements.tipo_activo?.value || '';
    const content = assetTypeAcademy[type];
    const title = panel.querySelector('summary strong');
    const intro = panel.querySelector('p');
    const scope = panel.querySelector('[data-asset-academy-scope]');
    const document = panel.querySelector('[data-asset-academy-document]');
    const economic = panel.querySelector('[data-asset-academy-economic]');
    panel.classList.toggle('is-empty', !content);
    if (title) title.textContent = content?.title || 'Selecciona un tipo de activo';
    if (intro) intro.textContent = content?.intro || 'Cada categoria activa campos, controles documentales, vencimientos y calculos diferentes.';
    if (scope) scope.textContent = content?.scope || 'Identificacion, titularidad, valor, soporte y riesgo.';
    if (document) document.textContent = content?.document || 'Depende de la naturaleza del activo.';
    if (economic) economic.textContent = content?.economic || 'Valor actual y participacion registrada.';
};

const currentValueLabel = (type) => {
    if (type === 'Cuenta bancaria/Dinero') return 'Saldo a fecha de corte';
    if (type === 'CDT') return 'Valor actual / redencion';
    if (type === 'Inmueble') return 'Valor comercial estimado';
    if (type === 'Acciones/Cuotas/Participaciones') return 'Valor estimado de la participacion';
    if (isIntangibleAsset(type)) return 'Valor estimado del activo';
    if (type === 'Deuda/Pasivo') return 'Saldo pendiente';
    return 'Valor comercial vigente';
};

const isIntangibleAsset = (type) => ['Activo intangible/tecnologico/PI', 'Marca/Intangible'].includes(type);

const isTabbedAsset = (type) => ['Inmueble', 'Vehiculo/Maquinaria/Equipo'].includes(type);

const assetSupportsInsurance = (type) => ['Inmueble', 'Vehiculo/Maquinaria/Equipo'].includes(type);

const intangibleAlert = (type, details) => {
    if (!isIntangibleAsset(type)) {
        return '';
    }
    const hasAssignment = details.existe_cesion_derechos === 'Si';
    if (hasAssignment) {
        return '';
    }
    return '<div class="asset-warning" data-asset-ip-warning><strong>Propiedad intelectual pendiente de formalizacion.</strong><span>Verifica contrato, cesion de derechos, titular juridico y registro ante DNDA cuando aplique.</span></div>';
};

const assetFieldLabel = (field) => `${assetEscape(field.label)}${field.evidence ? ' <span class="asset-document-marker">*Control Documental*</span>' : ''}${assetHelpButton(field.help || assetHelpText[field.name])}`;

const assetAppliesAttribute = (field) => {
    if (!Array.isArray(field.applies_to) || field.applies_to.length === 0) {
        return '';
    }
    return ` data-asset-applies-to="${assetEscape(field.applies_to.join('|'))}"`;
};

const syncAssetConditionalFields = (form) => {
    const subcategory = form.elements['detalle[subcategoria]']?.value || '';
    form.querySelectorAll('[data-asset-applies-to]').forEach((field) => {
        if (!(field instanceof HTMLElement)) {
            return;
        }
        const allowed = (field.dataset.assetAppliesTo || '').split('|').filter(Boolean);
        const visible = Boolean(subcategory) && allowed.includes(subcategory);
        field.hidden = !visible;
        field.style.display = visible ? '' : 'none';
    });
    form.querySelectorAll('.asset-specific-group').forEach((group) => {
        if (!(group instanceof HTMLElement)) {
            return;
        }
        const conditionalFields = [...group.querySelectorAll('[data-asset-applies-to]')];
        if (conditionalFields.length === 0) {
            group.hidden = false;
            group.style.display = '';
            return;
        }
        const visible = conditionalFields.some((field) => field instanceof HTMLElement && !field.hidden);
        group.hidden = !visible;
        group.style.display = visible ? '' : 'none';
    });
};

const updateCategoryDefinitionPanel = (form) => {
    const panel = form.querySelector('[data-asset-definition-panel]');
    if (!(panel instanceof HTMLElement)) {
        return;
    }
    const subcategory = form.elements['detalle[subcategoria]']?.value || '';
    const title = panel.querySelector('strong');
    const text = panel.querySelector('span');
    if (title) {
        title.textContent = subcategory || 'Definicion de la subcategoria';
    }
    if (text) {
        text.textContent = intangibleCategoryDefinitions[subcategory] || 'Selecciona una subcategoria para ver su alcance y cargar solo los campos que aplican.';
    }
};

const categoryDefinitionPanel = (type, details) => {
    if (!isIntangibleAsset(type)) {
        return '';
    }
    const subcategory = details.subcategoria || '';
    const text = intangibleCategoryDefinitions[subcategory] || 'Selecciona una subcategoria para ver su alcance y cargar solo los campos que aplican.';
    return `
        <div class="asset-definition-panel" data-asset-definition-panel>
            <strong>${assetEscape(subcategory || 'Definicion de la subcategoria')}</strong>
            <span>${assetEscape(text)}</span>
        </div>
    `;
};

const inmuebleRightAcademyHtml = (value = '') => {
    const content = inmuebleRightDefinitions[value] || {
        title: 'Tipo de derecho sobre el inmueble',
        text: 'Selecciona el derecho para ver como debe interpretarse juridica y economicamente dentro del mapa patrimonial.',
        support: 'El soporte documental define si se trata de propiedad plena, derecho economico, uso, posesion, mejora o relacion contractual.',
        risk: 'El nivel de riesgo depende de titularidad, disponibilidad, restricciones, vigencia, soportes y posibilidad real de recibir ingresos o disponer del inmueble.',
    };
    return `
        <div class="asset-right-academy" data-asset-right-academy>
            <strong>${assetEscape(content.title)}</strong>
            <span data-right-academy-text>${assetEscape(content.text)}</span>
            <dl>
                <div><dt>Soporte clave</dt><dd data-right-academy-support>${assetEscape(content.support)}</dd></div>
                <div><dt>Lectura de riesgo</dt><dd data-right-academy-risk>${assetEscape(content.risk)}</dd></div>
            </dl>
        </div>
    `;
};

const updateInmuebleRightAcademy = (form) => {
    const panel = form.querySelector('[data-asset-right-academy]');
    if (!(panel instanceof HTMLElement)) {
        return;
    }
    const value = form.elements['detalle[tipo_derecho]']?.value || '';
    const content = inmuebleRightDefinitions[value] || inmuebleRightDefinitions['Por confirmar'];
    const title = panel.querySelector('strong');
    const text = panel.querySelector('[data-right-academy-text]');
    const support = panel.querySelector('[data-right-academy-support]');
    const risk = panel.querySelector('[data-right-academy-risk]');
    if (title) title.textContent = value ? content.title : 'Tipo de derecho sobre el inmueble';
    if (text) text.textContent = value ? content.text : 'Selecciona el derecho para ver como debe interpretarse juridica y economicamente dentro del mapa patrimonial.';
    if (support) support.textContent = value ? content.support : 'El soporte documental define si se trata de propiedad plena, derecho economico, uso, posesion, mejora o relacion contractual.';
    if (risk) risk.textContent = value ? content.risk : 'El nivel de riesgo depende de titularidad, disponibilidad, restricciones, vigencia, soportes y posibilidad real de recibir ingresos o disponer del inmueble.';
};

const updateAssetCommonFields = (form, type) => {
    form.querySelectorAll('[data-asset-common-field]').forEach((field) => {
        if (field instanceof HTMLElement) {
            const commonField = field.dataset.assetCommonField || '';
            const hideForInmueble = type === 'Inmueble' && ['adquisicion', 'valor_actual', 'metodo_valoracion'].includes(commonField);
            const hideForIntangible = isIntangibleAsset(type) && ['nombre_descripcion', 'identificador'].includes(commonField);
            const hidden = hideForInmueble || hideForIntangible;
            field.hidden = hidden;
            field.style.display = hidden ? 'none' : '';
        }
    });
};

const setFormValueIfPresent = (form, name, value) => {
    const field = form.elements[name];
    if (field instanceof HTMLInputElement || field instanceof HTMLSelectElement || field instanceof HTMLTextAreaElement) {
        field.value = value ?? '';
    }
};

const syncInmuebleCommonFields = (form) => {
    if (form.elements.tipo_activo?.value !== 'Inmueble') {
        return;
    }
    setFormValueIfPresent(form, 'valor_adquisicion', form.elements['detalle[valor_escritura]']?.value ?? '');
    setFormValueIfPresent(form, 'fecha_adquisicion', form.elements['detalle[fecha_escritura]']?.value ?? '');
    setFormValueIfPresent(form, 'valor_actual', form.elements['detalle[valor_comercial]']?.value ?? '');
    setFormValueIfPresent(form, 'fecha_corte_valor', form.elements['detalle[fecha_valor_comercial]']?.value ?? '');
    const method = form.elements.metodo_valoracion;
    if (method instanceof HTMLSelectElement && !method.value) {
        const commercial = form.elements['detalle[valor_comercial]']?.value ?? '';
        const source = form.elements['detalle[fuente_valor_comercial]']?.value ?? '';
        if (commercial || source) {
            method.value = [...method.options].some((option) => option.value === 'Avaluo comercial') ? 'Avaluo comercial' : '';
        }
    }
};

const syncInsuranceConstructionValue = (form) => {
    if (form.elements.tipo_activo?.value !== 'Inmueble') {
        return;
    }
    const area = assetNumeric(form.elements['detalle[area_construida_asegurable]']?.value || form.elements['detalle[area_construida]']?.value);
    const valueM2 = assetNumeric(form.elements['detalle[valor_reposicion_m2]']?.value);
    const construction = form.elements['detalle[valor_reposicion_construccion]'];
    const source = form.elements['detalle[fuente_reposicion_construccion]'];
    const date = form.elements['detalle[fecha_consulta_reposicion_construccion]'];
    if (area > 0 && valueM2 > 0) {
        const total = area * valueM2;
        setAutoMoney(construction, total);
        if (source instanceof HTMLInputElement && (!source.value || source.dataset.autoInsuranceSource === source.value)) {
            const text = `Calculado: ${area} m2 x ${assetMoney(valueM2)} por m2${date?.value ? `, consulta ${date.value}` : ''}. Fuente pendiente de soporte.`;
            source.value = text;
            source.dataset.autoInsuranceSource = text;
        }
        const rows = insuranceEquipmentRowsForForm(form);
        const index = rows.findIndex((row) => row.categoria_item === 'Construccion');
        if (index >= 0) {
            rows[index] = {
                ...rows[index],
                cantidad: String(area),
                valor_reposicion_unitario: String(Math.round(valueM2)),
                valor_reposicion: String(Math.round(total)),
                valor_asegurable_sugerido: String(Math.round(total)),
                fuente_consulta: `${source?.value || 'Fuente pendiente'}${date?.value ? ` | consulta ${date.value}` : ''}`.trim(),
                descripcion: 'Valor asegurable de construccion. El terreno no se asegura.',
                incluye_terreno: 'No',
            };
            renderAssetInsuranceEquipmentRows(form, rows);
        }
    }
};

const insuranceEquipmentRowsForForm = (form) => assetInsuranceEquipmentRows(form).filter((row) => Object.values(row).some((value) => String(value ?? '').trim() !== ''));

const sumInsuredCategories = (form, categories) => insuranceEquipmentRowsForForm(form).reduce((sum, row) => {
    if (!categories.includes(row.categoria_item || '')) {
        return sum;
    }
    return sum + (assetNumber(row.valor_asegurable_sugerido) || assetNumber(row.valor_reposicion) || 0);
}, 0);

const insuranceSourcesForCategories = (form, categories) => insuranceEquipmentRowsForForm(form)
    .filter((row) => categories.includes(row.categoria_item || '') && (row.fuente_consulta || row.descripcion || row.item))
    .map((row) => [row.item, row.fuente_consulta, row.ano_adquisicion ? `adq. ${row.ano_adquisicion}` : ''].filter(Boolean).join(' - '))
    .slice(0, 4)
    .join(' | ');

const lossOfRentCoverageText = (coverage) => {
    const normalized = normalizeInsuranceText(coverage);
    return normalized.includes('arrendamiento') && (normalized.includes('perdida') || normalized.includes('canon') || normalized.includes('siniestro') || normalized.includes('ingreso'));
};

const lossOfRentBasisForForm = (form) => {
    const sharePercent = assetParticipationShare(form) > 0 ? assetParticipationShare(form) * 100 : 100;
    const months = 12;
    const subunits = assetFormRows(form, '[data-asset-subunit-row]', ['codigo_subunidad', 'nombre_subunidad', 'canon_mensual', 'arrendatario', 'contrato_soporte'])
        .filter((row) => assetNumber(row.canon_mensual) > 0);
    const rows = subunits.length > 0 ? subunits.map((row, index) => ({
        unit: row.nombre_subunidad || row.codigo_subunidad || `Unidad ${index + 1}`,
        canon: assetNumber(row.canon_mensual),
        share: sharePercent,
        months,
        source: row.contrato_soporte || row.arrendatario || 'Contrato/canon por soportar',
    })) : [{
        unit: form.elements.nombre?.value || 'Inmueble principal',
        canon: assetNumber(form.elements['detalle[canon_arrendamiento]']?.value || form.elements['detalle[renta_potencial_mensual]']?.value),
        share: sharePercent,
        months,
        source: 'Contrato de arrendamiento, canon mensual y vigencia',
    }];
    const valuedRows = rows.map((row) => ({
        ...row,
        value: row.canon * row.months * (row.share / 100),
    }));
    const total = valuedRows.reduce((sum, row) => sum + row.value, 0);
    const source = valuedRows
        .filter((row) => row.canon > 0)
        .map((row) => `${row.unit}: canon ${assetMoney(row.canon)} x ${row.months} meses x ${assetPercent(row.share)}`)
        .join(' | ');
    return { rows: valuedRows, total, months, source: source || 'Diligenciar canon mensual por unidad, meses de indemnizacion y soporte contractual.' };
};

const suggestCoverageValue = (form, coverage) => {
    const text = String(coverage || '').toLowerCase();
    if (lossOfRentCoverageText(coverage)) {
        const basis = lossOfRentBasisForForm(form);
        return { value: basis.total, source: basis.source };
    }
    if (text.includes('responsabilidad') || text.includes('tercero') || text.includes('lesion') || text.includes('muerte') || text.includes('defensa')) {
        return { value: 0, source: 'Definir limite por evento/vigencia segun contrato, actividad y apetito de riesgo.' };
    }
    if (text.includes('vehiculo') || text.includes('auto') || text.includes('perdida total') || text.includes('perdida parcial')) {
        return { value: 0, source: 'Definir con guia Fasecolda, factura, avaluo o valor comercial soportado del vehiculo.' };
    }
    const categories = coverageValueCategoriesFor(coverage);
    const value = sumInsuredCategories(form, categories);
    return { value, source: insuranceSourcesForCategories(form, categories) };
};

const refreshCoverageSuggestedValues = (form) => {
    form.querySelectorAll('[data-asset-insurance-coverage-row]').forEach((row) => {
        const coverage = row.querySelector('[name$="[cobertura]"]')?.value || '';
        const valueInput = row.querySelector('[name$="[valor_asegurado]"]');
        const sourceInput = row.querySelector('[name$="[fuente_valor_asegurado]"]');
        const observations = row.querySelector('[name$="[observaciones]"]');
        const suggestion = suggestCoverageValue(form, coverage);
        if (suggestion.value > 0) {
            setAutoMoney(valueInput, suggestion.value);
            form.querySelectorAll('[data-insurance-request-field="valor_asegurado"]').forEach((input) => {
                if (!(input instanceof HTMLInputElement)) {
                    return;
                }
                if ((input.dataset.product || '') === (row.querySelector('[name$="[ramo]"]')?.value || '') && (input.dataset.coverage || '') === coverage) {
                    setAutoMoney(input, suggestion.value);
                }
            });
            if (sourceInput instanceof HTMLInputElement && (!sourceInput.value || sourceInput.dataset.autoCoverageSource === sourceInput.value)) {
                const text = suggestion.source || 'Relacion de bienes a valor de reposicion pendiente de soporte.';
                sourceInput.value = text;
                sourceInput.dataset.autoCoverageSource = text;
            }
            if (observations instanceof HTMLInputElement && (!observations.value || observations.dataset.autoCoverageSource === observations.value)) {
                const text = `Valor sugerido segun relacion de bienes a reposicion. Fuente: ${suggestion.source || 'pendiente de soporte por item.'}`;
                observations.value = text;
                observations.dataset.autoCoverageSource = text;
            }
        }
    });
};

const syncInsuranceRequestField = (form, input) => {
    const field = input.dataset.insuranceRequestField || '';
    const product = input.dataset.product || '';
    const coverage = input.dataset.coverage || '';
    if (!field || !coverage) {
        return;
    }
    let coverageRow = [...form.querySelectorAll('[data-asset-insurance-coverage-row]')]
        .find((row) => {
            const rowProduct = row.querySelector('[name$="[ramo]"]')?.value || '';
            const rowCoverage = row.querySelector('[name$="[cobertura]"]')?.value || '';
            return normalizedCoverageRowKey({ ramo: rowProduct, cobertura: rowCoverage }) === normalizedCoverageRowKey({ ramo: product, cobertura: coverage });
        });
    if (!coverageRow) {
        const rows = historyRowsForType(form, '[data-asset-insurance-coverage-row]', assetInsuranceCoverageFields);
        rows.push({
            ano: String(new Date().getFullYear()),
            ramo: product,
            cobertura: coverage,
            riesgo_cubierto: coverage,
            [field]: input.value,
            observaciones: field === 'observaciones' ? input.value : 'Definir valor o limite con soporte antes de cotizar.',
        });
        renderAssetInsuranceCoverageRows(form, rows);
        coverageRow = [...form.querySelectorAll('[data-asset-insurance-coverage-row]')]
            .find((row) => {
                const rowProduct = row.querySelector('[name$="[ramo]"]')?.value || '';
                const rowCoverage = row.querySelector('[name$="[cobertura]"]')?.value || '';
                return normalizedCoverageRowKey({ ramo: rowProduct, cobertura: rowCoverage }) === normalizedCoverageRowKey({ ramo: product, cobertura: coverage });
            });
    }
    const target = coverageRow?.querySelector(`[name$="[${field}]"]`);
    if (target instanceof HTMLInputElement || target instanceof HTMLTextAreaElement || target instanceof HTMLSelectElement) {
        target.value = input.value;
    }
};

const applyAdoptedPolicyToCoverages = (form, adoptedPolicy) => {
    const policyProducts = splitInsuranceSelection(adoptedPolicy.ramo || '');
    const policyNumber = adoptedPolicy.numero_poliza || '';
    const policyStart = adoptedPolicy.fecha_inicio || '';
    const policyEnd = adoptedPolicy.fecha_fin || '';
    const policyRenewal = adoptedPolicy.fecha_renovacion || '';
    const rows = historyRowsForType(form, '[data-asset-insurance-coverage-row]', assetInsuranceCoverageFields);
    let total = 0;
    const updated = rows.map((row) => {
        const belongs = policyProducts.length === 0 || policyProducts.includes(row.ramo || '') || !row.numero_poliza || row.numero_poliza === policyNumber;
        if (!belongs) {
            return row;
        }
        total += assetNumber(row.valor_asegurado);
        return {
            ...row,
            numero_poliza: policyNumber || row.numero_poliza,
            fecha_inicio: row.fecha_inicio || policyStart,
            fecha_fin: row.fecha_fin || policyEnd,
            fecha_renovacion: row.fecha_renovacion || policyRenewal,
        };
    });
    renderAssetInsuranceCoverageRows(form, updated);
    return total;
};

const syncCoveragePremiumRow = (row) => {
    if (!(row instanceof HTMLElement)) {
        return;
    }
    const valueInput = row.querySelector('[name$="[valor_asegurado]"]');
    const rateInput = row.querySelector('[name$="[tasa]"]');
    const premiumInput = row.querySelector('[name$="[prima]"]');
    if (!(premiumInput instanceof HTMLInputElement)) {
        return;
    }
    const insuredValue = assetNumber(valueInput?.value);
    const rate = assetNumber(rateInput?.value);
    if (insuredValue <= 0 || rate <= 0) {
        return;
    }
    setAutoMoney(premiumInput, insuredValue * rate / 100);
};

const syncCoveragePremiumRows = (form) => {
    form.querySelectorAll('[data-asset-insurance-coverage-row]').forEach(syncCoveragePremiumRow);
};

const insuranceDerivedSummary = (form) => {
    const rows = insuranceEquipmentRowsForForm(form);
    const assets = rows.map((row) => `${row.categoria_item || 'Item'}: ${assetMoney(assetNumber(row.valor_asegurable_sugerido) || assetNumber(row.valor_reposicion) || 0)}`);
    const coverages = assetFormRows(form, '[data-asset-insurance-coverage-row]', ['cobertura', 'valor_asegurado'])
        .filter((row) => row.cobertura)
        .map((row) => `${row.cobertura}: ${assetMoney(assetNumber(row.valor_asegurado) || 0)}`);
    return {
        assets: assets.join(' | '),
        coverages: coverages.join(' | '),
    };
};

const updateInsuranceDerivedSummary = (form) => {
    const summary = insuranceDerivedSummary(form);
    const assetsField = form.querySelector('[data-insurance-assets-summary]');
    const risksField = form.querySelector('[data-insurance-risks-summary]');
    const target = form.querySelector('[data-insurance-derived-summary]');
    if (assetsField instanceof HTMLTextAreaElement) {
        assetsField.value = summary.assets;
    }
    if (risksField instanceof HTMLTextAreaElement) {
        risksField.value = summary.coverages;
    }
    if (target instanceof HTMLElement) {
        target.textContent = summary.assets || 'Sin bienes asegurables seleccionados.';
    }
};

const upsertInsuredAssetFromOption = (form, option) => {
    const rows = insuranceEquipmentRowsForForm(form);
    if (rows.some((row) => row.categoria_item === option.category)) {
        return;
    }
    const today = new Date().toISOString().slice(0, 10);
    const row = {
        ano: String(new Date().getFullYear()),
        numero_poliza: form.querySelector('[name^="seguro_polizas"][name$="[numero_poliza]"]')?.value ?? '',
        ramo: form.querySelector('[name^="seguro_polizas"][name$="[ramo]"]')?.value ?? '',
        cobertura_asociada: option.coverage,
        categoria_item: option.category,
        item: option.label,
        unidad: option.unit,
        cantidad: '',
        valor_reposicion_unitario: '',
        valor_reposicion: '',
        fuente_consulta: '',
        fecha_consulta: '',
        valor_compra: '',
        fecha_adquisicion: '',
        fecha_inicio: form.querySelector('[name^="seguro_polizas"][name$="[fecha_inicio]"]')?.value ?? '',
        fecha_fin: form.querySelector('[name^="seguro_polizas"][name$="[fecha_fin]"]')?.value ?? '',
        fecha_renovacion: form.querySelector('[name^="seguro_polizas"][name$="[fecha_renovacion]"]')?.value ?? '',
    };
    if (option.key === 'construccion') {
        const area = form.elements['detalle[area_construida_asegurable]']?.value || form.elements['detalle[area_construida]']?.value || '';
        const valueM2 = form.elements['detalle[valor_reposicion_m2]']?.value || '';
        const total = form.elements['detalle[valor_reposicion_construccion]']?.value || '';
        const source = form.elements['detalle[fuente_reposicion_construccion]']?.value || '';
        const sourceDate = form.elements['detalle[fecha_consulta_reposicion_construccion]']?.value || today;
        row.cantidad = area;
        row.valor_reposicion_unitario = valueM2;
        row.valor_reposicion = total;
        row.valor_asegurable_sugerido = total;
        row.fuente_consulta = source;
        row.fecha_consulta = sourceDate;
        row.descripcion = 'Valor asegurable de construccion. El terreno no se asegura.';
        row.incluye_terreno = 'No';
    }
    rows.push(row);
    renderAssetInsuranceEquipmentRows(form, rows);
    refreshCoverageSuggestedValues(form);
    updateInsuranceDerivedSummary(form);
};

const removeInsuredAssetByOption = (form, option) => {
    const rows = insuranceEquipmentRowsForForm(form).filter((row) => row.categoria_item !== option.category);
    renderAssetInsuranceEquipmentRows(form, rows);
    refreshCoverageSuggestedValues(form);
    updateInsuranceDerivedSummary(form);
};

const syncPolicyTypeFromDetail = (form) => {
    const type = form.elements['detalle[tipo_seguro]']?.value || '';
    if (!type) {
        return;
    }
    const rows = assetFormRows(form, '[data-asset-insurance-policy-row]', assetInsurancePolicyFields);
    if (rows.length === 0) {
        rows.push({ ano: String(new Date().getFullYear()), ramo: type, tipo_documento: 'Cotizacion' });
    } else {
        rows[0].ramo = type;
    }
    renderAssetInsurancePolicyRows(form, rows);
    form.dataset.assetCoveragePolicyIndex = '0';
    renderAssetInsuranceCoverageRows(form, assetFormRows(form, '[data-asset-insurance-coverage-row]', assetInsuranceCoverageFields));
};

const updateInsuranceTypeSelection = (form) => {
    const selected = [...form.querySelectorAll('[data-asset-insurance-type-toggle]:checked')]
        .map((input) => input instanceof HTMLInputElement ? input.value : '')
        .filter(Boolean);
    const value = joinInsuranceSelection(selected);
    const hidden = form.elements['detalle[tipo_seguro]'];
    if (hidden instanceof HTMLInputElement) {
        hidden.value = value;
    }
    syncPolicyTypeFromDetail(form);
    renderAssetInsuranceCoverageRows(form, historyRowsForType(form, '[data-asset-insurance-coverage-row]', assetInsuranceCoverageFields));
    renderAssetInsuranceEquipmentRows(form, assetInsuranceEquipmentRows(form).filter((row) => Object.values(row).some((item) => String(item ?? '').trim() !== '')));
};

const updatePolicyRamoSelection = (policyRow) => {
    if (!(policyRow instanceof HTMLElement)) {
        return;
    }
    const selected = [...policyRow.querySelectorAll('[data-asset-policy-ramo-toggle]:checked')]
        .map((input) => input instanceof HTMLInputElement ? input.value : '')
        .filter(Boolean);
    const value = joinInsuranceSelection(selected);
    const hidden = policyRow.querySelector('[data-asset-policy-ramo]');
    if (hidden instanceof HTMLInputElement) {
        hidden.value = value;
    }
};

const syncIntangibleCommonFields = (form) => {
    if (!isIntangibleAsset(form.elements.tipo_activo?.value || '')) {
        return;
    }
    const name = form.elements['detalle[nombre_aplicacion]']?.value
        || form.elements['detalle[portal_proveedor]']?.value
        || form.elements['detalle[firma_proveedor]']?.value
        || form.elements['detalle[producto_url]']?.value
        || form.elements['detalle[suscripcion_servicio]']?.value
        || form.elements['detalle[ia_modelo_servicio]']?.value
        || form.elements['detalle[revista_nombre]']?.value
        || form.elements['detalle[proyecto_codigo_interno]']?.value
        || '';
    const identifier = form.elements['detalle[codigo_interno]']?.value
        || form.elements['detalle[portal_url]']?.value
        || form.elements['detalle[firma_url_acceso]']?.value
        || form.elements['detalle[producto_url]']?.value
        || form.elements['detalle[suscripcion_url]']?.value
        || form.elements['detalle[logo_registro_marca_relacionado]']?.value
        || form.elements['detalle[revista_issn]']?.value
        || form.elements['detalle[proyecto_codigo_interno]']?.value
        || '';
    if (name) {
        setFormValueIfPresent(form, 'nombre_descripcion', name);
    }
    if (identifier) {
        setFormValueIfPresent(form, 'identificador', identifier);
    }
};

const setAutoNumber = (input, value, decimals = 2) => {
    if (!(input instanceof HTMLInputElement) || !Number.isFinite(value)) {
        return;
    }
    const current = Number(String(input.value || '').replace(',', '.'));
    const previous = Number(input.dataset.autoValue || 'NaN');
    if (!input.value || current === previous) {
        const formatted = new Intl.NumberFormat('es-CO', { maximumFractionDigits: decimals }).format(value);
        input.value = formatted;
        input.dataset.autoValue = String(value);
    }
};

const assetParticipationShare = (form) => {
    const total = [...form.querySelectorAll('[data-asset-participation-row]')].reduce((sum, row) => {
        const input = row.querySelector('[data-asset-participation-percent]');
        const value = input instanceof HTMLInputElement ? assetNumeric(input.value) : 0;
        return Number.isFinite(value) ? sum + value : sum;
    }, 0);
    return Math.max(0, Math.min(100, total)) / 100;
};

const syncInmuebleEconomics = (form) => {
    if (form.elements.tipo_activo?.value !== 'Inmueble') {
        return;
    }
    const subunitCanon = [...form.querySelectorAll('[data-asset-subunit-row]')].reduce((sum, row) => {
        const input = row.querySelector('[name$="[canon_mensual]"]');
        return sum + (input instanceof HTMLInputElement ? assetNumeric(input.value) : 0);
    }, 0);
    const canon = form.elements['detalle[canon_arrendamiento]'];
    const realRent = form.elements['detalle[renta_real_mensual]'];
    const potentialRent = form.elements['detalle[renta_potencial_mensual]'];
    const share = assetParticipationShare(form);
    const totalCanon = subunitCanon > 0 ? subunitCanon : assetNumeric(canon?.value);
    if (subunitCanon > 0) {
        setAutoMoney(canon, subunitCanon);
        setAutoMoney(potentialRent, subunitCanon);
    }
    const otherIncome = assetNumeric(form.elements['detalle[otros_ingresos_mensuales]']?.value);
    const ownedRent = totalCanon * share;
    const gross = (totalCanon + otherIncome) * share;
    setAutoMoney(realRent, ownedRent);
    const totalExpenses = [
        'predial_mensualizado',
        'administracion_mensual_gasto',
        'seguros_mensualizados',
        'mantenimiento_mensual_promedio',
        'servicios_asumidos_propietario',
        'honorarios_administracion',
        'reparaciones_extraordinarias',
        'gastos_legales',
        'gastos_financieros_hipoteca',
        'otros_egresos_mensuales',
    ].reduce((sum, name) => sum + assetNumeric(form.elements[`detalle[${name}]`]?.value), 0);
    const expenses = totalExpenses * share;
    const net = gross - expenses;
    setAutoMoney(form.elements['detalle[renta_anual_proyectada]'], ownedRent * 12);
    setAutoMoney(form.elements['detalle[ingreso_bruto_mensual]'], gross);
    setAutoMoney(form.elements['detalle[egresos_mensuales]'], expenses);
    setAutoMoney(form.elements['detalle[ingreso_neto_mensual]'], net);
    setAutoMoney(form.elements['detalle[ingreso_neto_anual]'], net * 12);
    const commercialValue = assetNumeric(form.elements['detalle[valor_comercial]']?.value || form.elements.valor_actual?.value);
    const valueBasis = commercialValue * share;
    if (valueBasis > 0) {
        setAutoNumber(form.elements['detalle[rentabilidad_bruta_anual]'], (gross * 12 / valueBasis) * 100);
        setAutoNumber(form.elements['detalle[rentabilidad_neta_anual]'], (net * 12 / valueBasis) * 100);
    }
};

const activeAssetTabTitle = (form) => {
    const activeButton = form.querySelector('[data-asset-tab].is-active');
    if (activeButton instanceof HTMLButtonElement) {
        return activeButton.textContent || '';
    }
    const activePanel = form.querySelector('[data-asset-tab-panel].is-active h3');
    return activePanel?.textContent || '';
};

const updateAssetContextSections = (form) => {
    const type = form.elements.tipo_activo?.value || '';
    const tabTitle = activeAssetTabTitle(form);
    const isInmueble = type === 'Inmueble';
    const supportsInsurance = assetSupportsInsurance(type);
    const tabbed = isTabbedAsset(type);
    const fiducia = form.querySelector('[data-asset-fiducia]');
    const subunits = form.querySelector('[data-asset-subunits]');
    const insuranceFlow = form.querySelector('[data-asset-insurance-flow]');
    const participation = form.querySelector('[data-asset-participation]');
    const annualHistory = form.querySelector('[data-asset-annual-history]');
    if (participation instanceof HTMLElement) {
        const visible = !tabbed || tabTitle.includes('Participacion');
        participation.hidden = !visible;
        participation.style.display = visible ? '' : 'none';
    }
    if (annualHistory instanceof HTMLElement) {
        const visible = isInmueble && (!tabbed || tabTitle.includes('Historial anual'));
        annualHistory.hidden = !visible;
        annualHistory.style.display = visible ? '' : 'none';
    }
    if (fiducia instanceof HTMLElement) {
        const visible = isInmueble && tabTitle.includes('Fiducia civil');
        fiducia.hidden = !visible;
        fiducia.style.display = visible ? '' : 'none';
    }
    if (subunits instanceof HTMLElement) {
        const visible = isInmueble && tabTitle.includes('Arrendamiento');
        subunits.hidden = !visible;
        subunits.style.display = visible ? '' : 'none';
    }
    const insuranceVisible = supportsInsurance && tabTitle.includes('Seguros');
    const activeInsuranceTab = form.dataset.assetInsuranceTab || 'actual';
    if (insuranceFlow instanceof HTMLElement) {
        insuranceFlow.hidden = !insuranceVisible;
        insuranceFlow.style.display = insuranceVisible ? '' : 'none';
        insuranceFlow.querySelectorAll('[data-asset-insurance-tab]').forEach((button) => {
            if (button instanceof HTMLButtonElement) {
                button.classList.toggle('is-active', button.dataset.assetInsuranceTab === activeInsuranceTab);
            }
        });
    }
    form.querySelectorAll('[data-asset-insurance-panel]').forEach((panel) => {
        if (panel instanceof HTMLElement) {
            const visible = insuranceVisible && panel.dataset.assetInsurancePanel === activeInsuranceTab;
            panel.hidden = !visible;
            panel.style.display = visible ? '' : 'none';
        }
    });
};

const renderAssetSpecificFields = (form, row = null) => {
    const schemas = parseAssetJson(form, 'assetSchemas');
    const options = parseAssetJson(form, 'assetOptions');
    const container = form.querySelector('[data-asset-specific]');
    const type = form.elements.tipo_activo.value;
    const schema = schemas[type];
    updateAssetTypeAcademy(form);
    updateAssetCommonFields(form, type);
    const identifierLabel = form.querySelector('[data-asset-identifier-label]');
    const valueLabel = form.querySelector('[data-asset-current-value-label]');
    if (identifierLabel) {
        identifierLabel.childNodes[0].nodeValue = `${schema?.identifier || 'Identificador'}`;
    }
    if (valueLabel) {
        valueLabel.childNodes[0].nodeValue = `${currentValueLabel(type)}`;
    }
    if (!(container instanceof HTMLElement)) {
        return;
    }
    if (!schema) {
        container.innerHTML = '<h2>Campos especificos</h2><p>Selecciona un tipo de activo para cargar los campos de identificacion y detalle.</p>';
        updateAssetTypeAcademy(form);
        return;
    }
    const details = row?.detalle || {};
    const renderInsuranceBuilder = () => {
        const detail = row?.detalle || {};
        const selectedCategories = new Set((row?.seguro_equipos || []).map((item) => item.categoria_item).filter(Boolean));
        const selectedMacro = detail.seguro_macro_ramo || '';
        const macroOptions = insuranceMacrosForForm(form).map((item) => `<option value="${assetEscape(item)}" ${item === selectedMacro ? 'selected' : ''}>${assetEscape(item)}</option>`).join('');
        const insuranceTypes = insuranceProductsForAsset(type, selectedCategories, form);
        const selectedInsuranceTypes = new Set(splitInsuranceSelection(detail.tipo_seguro ?? (row?.seguro_polizas?.[0]?.ramo ?? '')));
        const normalizedSelectedInsuranceTypes = new Set([...selectedInsuranceTypes].map((item) => normalizeInsuranceText(normalizeInsuranceRamo(item))));
        const typeChips = insuranceTypes.map((item) => {
            const catalog = insuranceCatalogForProduct(form, item);
            const isSelected = selectedInsuranceTypes.has(item) || normalizedSelectedInsuranceTypes.has(normalizeInsuranceText(normalizeInsuranceRamo(item)));
            return `
            <label class="asset-coverage-chip asset-insurance-ramo-chip ${isSelected ? 'is-selected' : ''}" data-asset-insurance-product-chip="${assetEscape(item)}">
                <input type="checkbox" data-asset-insurance-type-toggle value="${assetEscape(item)}" ${isSelected ? 'checked' : ''}>
                <span>
                    ${catalog ? `<em>${assetEscape(insuranceCatalogValue(catalog, 'Código', 'Codigo'))}</em>` : ''}
                    <strong>${assetEscape(item)}</strong>
                    ${catalog ? `<small>${assetEscape(insuranceCatalogMacro(catalog))}</small>` : ''}
                </span>
            </label>
        `;
        }).join('');
        return `
            <div class="asset-insurance-builder">
                <div class="asset-insurance-route">
                    <strong>Flujo de proteccion del activo</strong>
                    <span>1. Define seguros. 2. Registra poliza actual. 3. Marca coberturas y bienes/exposiciones. 4. Compara actual vs solicitado. 5. Genera solicitud y compara ofertas.</span>
                </div>
                <div>
                    <strong>1. Seguros a incorporar al activo</strong>
                    <span>El sistema muestra todos los ramos existentes. El analista marca con X los que desea explorar, cotizar o contratar para este activo.</span>
                </div>
                <label class="asset-insurance-macro-filter">Macro-ramo
                    <select name="detalle[seguro_macro_ramo]" data-insurance-macro-filter>
                        <option value="">Todos los ramos</option>
                        ${macroOptions}
                    </select>
                </label>
                <input type="hidden" name="detalle[tipo_seguro]" data-asset-insurance-type value="${assetEscape(joinInsuranceSelection([...selectedInsuranceTypes]))}">
                <div class="asset-coverage-chips asset-insurance-type-grid">${typeChips}</div>
                <div class="asset-insurance-product-academy" data-insurance-product-academy>${insuranceProductAcademyHtml([...selectedInsuranceTypes], form)}</div>
                <textarea name="detalle[que_se_asegura]" data-insurance-assets-summary hidden>${assetEscape(detail.que_se_asegura ?? '')}</textarea>
                <textarea name="detalle[riesgos_cubiertos]" data-insurance-risks-summary hidden>${assetEscape(detail.riesgos_cubiertos ?? '')}</textarea>
            </div>
        `;
    };
    const renderField = (field) => {
        if (assetSupportsInsurance(type) && ['tipo_seguro', 'que_se_asegura', 'riesgos_cubiertos'].includes(field.name)) {
            return '';
        }
        const value = details[field.name] ?? '';
        const name = `detalle[${field.name}]`;
        const applies = assetAppliesAttribute(field);
        const academy = type === 'Inmueble' && field.name === 'tipo_derecho' ? inmuebleRightAcademyHtml(value) : '';
        if (field.type === 'textarea') {
            return `<label${applies}>${assetFieldLabel(field)}<textarea name="${assetEscape(name)}" rows="2">${assetEscape(value)}</textarea></label>${academy}`;
        }
        if (field.type === 'select') {
            const values = options[field.options] || [];
            return `<label${applies}>${assetFieldLabel(field)}<select name="${assetEscape(name)}">${assetPlaceholderOption(value)}${values.map((item) => `<option value="${assetEscape(item)}" ${item === value ? 'selected' : ''}>${assetEscape(item)}</option>`).join('')}</select></label>${academy}`;
        }
        const inputType = field.type === 'date' ? 'date' : 'text';
        const inputMode = ['money', 'decimal', 'number'].includes(field.type) ? ' inputmode="decimal"' : '';
        const moneyFormat = field.type === 'money' ? ' data-money-format' : '';
        const displayValue = field.type === 'money' ? (assetMoneyPlain(value) || value) : value;
        return `<label${applies}>${assetFieldLabel(field)}<input name="${assetEscape(name)}" type="${inputType}"${inputMode}${moneyFormat} value="${assetEscape(displayValue)}"></label>${academy}`;
    };
    const grouped = schema.fields.reduce((carry, field) => {
        const group = field.group || 'Datos del activo';
        carry[group] = carry[group] || [];
        carry[group].push(field);
        return carry;
    }, {});
    const riskHelp = type === 'Inmueble' ? '<details class="asset-info"><summary><strong>Guia de riesgo</strong><span>Bajo / Medio / Alto / Critico</span></summary><p>Es una alerta preliminar: Bajo si la informacion esta completa y soportada; Medio si faltan soportes o valores actualizados; Alto/Critico si hay litigios, gravamenes, vencimientos, hipotecas sin control o titularidad por aclarar.</p></details>' : '';
    const groupEntries = Object.entries(grouped);
    const groups = groupEntries.map(([group, groupFields], index) => `
        <details class="asset-specific-group" ${index < 2 ? 'open' : ''}>
            <summary><h3>${assetEscape(group)}</h3></summary>
            <div class="asset-specific-fields">${groupFields.map(renderField).join('')}</div>
        </details>
    `).join('');
    const extraTabs = `<button type="button" data-asset-tab="participacion" aria-selected="false">Participacion</button>${type === 'Inmueble' ? '<button type="button" data-asset-tab="historial-anual" aria-selected="false">Historial anual</button>' : ''}`;
    const assetTabs = `${groupEntries.map(([group], index) => `<button type="button" class="${index === 0 ? 'is-active' : ''}" data-asset-tab="${index}" aria-selected="${index === 0 ? 'true' : 'false'}">${assetEscape(group.replace(/^\d+\.\s*/, ''))}</button>`).join('')}${extraTabs}`;
    const assetPanels = groupEntries.map(([group, groupFields], index) => {
        const isInsuranceGroup = assetSupportsInsurance(type) && group.includes('Seguros');
        return `
        <section class="asset-specific-tab-panel ${index === 0 ? 'is-active' : ''}" data-asset-tab-panel="${index}" ${index === 0 ? '' : 'hidden'}>
            <h3>${assetEscape(group)}</h3>
            ${isInsuranceGroup ? `
                ${renderInsuranceBuilder()}
                <details class="asset-insurance-policy-basics">
                    <summary><strong>Datos de poliza vigente o cotizacion</strong><span>Abrir solo cuando ya exista aseguradora, poliza o vigencia.</span></summary>
                    <div class="asset-specific-fields">${groupFields.map(renderField).join('')}</div>
                </details>
            ` : `<div class="asset-specific-fields">${groupFields.map(renderField).join('')}</div>`}
        </section>
    `;
    }).join('');
    container.innerHTML = isTabbedAsset(type)
        ? `<h2>${assetEscape(schema.label)}</h2>${riskHelp}<div class="asset-specific-tabs" role="tablist">${assetTabs}</div><div class="asset-specific-tab-panels">${assetPanels}</div>`
        : `<h2>${assetEscape(schema.label)}</h2>${intangibleAlert(type, details)}${categoryDefinitionPanel(type, details)}${groups}`;
    syncInmuebleCommonFields(form);
    syncInsuranceConstructionValue(form);
    syncInmuebleEconomics(form);
    syncIntangibleCommonFields(form);
    syncAssetConditionalFields(form);
    updateCategoryDefinitionPanel(form);
    updateInmuebleRightAcademy(form);
    updateAssetContextSections(form);
    updateInsuranceProductVisibility(form);
    updateInsuranceDerivedSummary(form);
};

const renderAssetSummary = (summary) => {
    const count = document.querySelector('[data-asset-total-count]');
    const current = document.querySelector('[data-asset-total-current]');
    const acquisition = document.querySelector('[data-asset-total-acquisition]');
    if (count) count.textContent = String(summary?.activos ?? 0);
    if (current) current.textContent = assetMoney(summary?.valor_actual ?? 0);
    if (acquisition) acquisition.textContent = assetMoney(summary?.valor_adquisicion ?? 0);
};

const renderAssetCategoryDashboard = (groups = []) => {
    const target = document.querySelector('[data-asset-category-summary]');
    if (!(target instanceof HTMLElement)) {
        return;
    }
    if (!Array.isArray(groups) || groups.length === 0) {
        target.innerHTML = '<div class="asset-category-empty">Registra activos para calcular valor, unidades e ingresos por categoria.</div>';
        return;
    }
    target.innerHTML = groups.map((group) => `
        <article class="asset-category-card">
            <header>
                <strong>${assetEscape(group.tipo_activo || 'Sin categoria')}</strong>
                <span>${assetEscape(group.activos ?? 0)} activos / ${assetEscape(group.unidades ?? 0)} unidades</span>
            </header>
            <dl>
                <div><dt>Valor comercial total</dt><dd>${assetEscape(assetMoney(group.valor_comercial_total || 0))}</dd></div>
                <div><dt>Segun participacion</dt><dd>${assetEscape(assetMoney(group.valor_comercial_participacion || 0))}</dd></div>
                <div><dt>Ingreso mensual total</dt><dd>${assetEscape(assetMoney(group.ingreso_mensual_total || 0))}</dd></div>
                <div><dt>Ingreso real mensual</dt><dd>${assetEscape(assetMoney(group.ingreso_mensual_participacion || 0))}</dd></div>
                <div><dt>Ingreso bruto anual</dt><dd>${assetEscape(assetMoney(group.ingreso_bruto_anual || 0))}</dd></div>
                <div><dt>Gastos y costos anuales</dt><dd>${assetEscape(assetMoney(group.gastos_anuales || 0))}</dd></div>
                <div><dt>INO anual</dt><dd>${assetEscape(assetMoney(group.ino_anual || 0))}</dd></div>
            </dl>
        </article>
    `).join('');
};

const assetCalendarLabel = (item) => {
    const days = item.dias;
    const status = days === null ? 'Sin fecha' : days < 0 ? 'Vencido' : days === 0 ? 'Hoy' : `En ${days}d`;
    const concept = item.categoria || item.concepto || 'Vencimiento';
    return `${status} - ${concept}`;
};

const renderAssetRenewalCalendar = () => {
    const calendar = document.querySelector('[data-asset-renewal-calendar]');
    if (!(calendar instanceof HTMLElement)) {
        return;
    }
    const fullCalendarTarget = calendar.querySelector('[data-renewal-fullcalendar]');
    const range = Number(calendar.querySelector('[data-renewal-range]')?.value || 60);
    const list = calendar.querySelector('[data-renewal-list]');
    const metrics = calendar.querySelector('[data-renewal-metrics]');
    const rows = [...document.querySelectorAll('[data-asset-row]')].map((item) => {
        try {
            return JSON.parse(item.dataset.row || '{}');
        } catch (_) {
            return {};
        }
    });
    const items = rows.flatMap(assetRenewalItemsFromRow);
    const decorated = items.map((item) => ({ ...item, dias: assetDaysUntil(item.fecha_vencimiento) }));
    const expired = decorated.filter((item) => item.dias !== null && item.dias < 0);
    const upcoming = decorated.filter((item) => item.dias !== null && item.dias >= 0 && item.dias <= range);
    const missing = decorated.filter((item) => item.dias === null);
    const visible = [...expired, ...upcoming, ...missing].sort((a, b) => {
        if (a.dias === null) return 1;
        if (b.dias === null) return -1;
        return a.dias - b.dias;
    });
    if (metrics instanceof HTMLElement) {
        metrics.innerHTML = `
            <span><strong>${expired.length}</strong> vencidos</span>
            <span><strong>${upcoming.length}</strong> proximos ${range} dias</span>
            <span><strong>${missing.length}</strong> sin fecha</span>
            <span><strong>${decorated.length}</strong> eventos controlados</span>
        `;
    }
    if (fullCalendarTarget instanceof HTMLElement && window.FullCalendar) {
        const events = decorated
            .filter((item) => item.fecha_vencimiento)
            .map((item) => {
                const state = item.dias === null ? 'missing' : item.dias < 0 ? 'expired' : item.dias <= 30 ? 'soon' : 'ok';
                return {
                    id: item.id,
                    title: `${assetCalendarLabel(item)} - ${item.nombre}`,
                    start: item.fecha_vencimiento,
                    allDay: true,
                    classNames: [`asset-calendar-${state}`],
                    extendedProps: item,
                };
            });
        if (!calendar._assetFullCalendar) {
            calendar._assetFullCalendar = new window.FullCalendar.Calendar(fullCalendarTarget, {
                initialView: 'dayGridMonth',
                locale: 'es',
                height: 'auto',
                headerToolbar: {
                    left: 'prev,next today',
                    center: 'title',
                    right: 'dayGridMonth,listMonth',
                },
                buttonText: {
                    today: 'Hoy',
                    month: 'Mes',
                    list: 'Lista',
                },
                events,
                eventContent(info) {
                    const item = info.event.extendedProps || {};
                    const wrapper = document.createElement('span');
                    wrapper.className = 'asset-calendar-label';
                    wrapper.textContent = assetCalendarLabel(item);
                    wrapper.title = `${item.concepto || 'Vencimiento'} - ${item.nombre || ''}`.trim();
                    return { domNodes: [wrapper] };
                },
                eventClick(info) {
                    const id = info.event.extendedProps.asset_id;
                    const rowElement = id ? document.querySelector(`[data-asset-row="${CSS.escape(id)}"]`) : null;
                    const editButton = rowElement?.querySelector('[data-edit-asset]');
                    if (editButton instanceof HTMLElement) {
                        editButton.click();
                    }
                },
            });
            calendar._assetFullCalendar.render();
        } else {
            calendar._assetFullCalendar.removeAllEvents();
            calendar._assetFullCalendar.addEventSource(events);
        }
    } else if (fullCalendarTarget instanceof HTMLElement) {
        fullCalendarTarget.innerHTML = '<div class="asset-renewal-empty">No se pudo cargar FullCalendar. Revisa la conexion al CDN o usa la lista de vencimientos.</div>';
    }
    if (!(list instanceof HTMLElement)) {
        return;
    }
    if (visible.length === 0) {
        list.innerHTML = '<div class="asset-renewal-empty">No hay vencimientos digitales para la ventana seleccionada.</div>';
        return;
    }
    list.innerHTML = visible.map((item) => {
        const state = item.dias === null ? 'missing' : item.dias < 0 ? 'expired' : item.dias <= 30 ? 'soon' : 'ok';
        const label = item.dias === null ? 'Sin fecha' : item.dias < 0 ? `Vencido hace ${Math.abs(item.dias)} dias` : `Vence en ${item.dias} dias`;
        return `
            <article class="asset-renewal-item is-${state}" data-renewal-asset-id="${assetEscape(item.asset_id)}">
                <div>
                    <strong>${assetEscape(item.nombre || 'Activo digital sin nombre')}</strong>
                    <small>${assetEscape(item.concepto || item.categoria || 'Vencimiento')} - ${assetEscape(item.tipo_activo || item.subcategoria || 'Activo')} ${item.identificador ? `- ${assetEscape(item.identificador)}` : ''}</small>
                </div>
                <span>${assetEscape(label)}</span>
                <span>${assetEscape(assetDate(item.fecha_vencimiento) || 'Sin vencimiento')}</span>
                <span>${assetEscape(item.responsable || 'Sin responsable')}</span>
                <span>${assetEscape(assetMoney(item.costo) || item.costo || 'Sin costo')}</span>
                <button type="button" data-edit-renewal>Editar</button>
            </article>
        `;
    }).join('');
};

const syncAssetRenewalItem = (row) => {
    renderAssetRenewalCalendar();
};

const removeAssetRenewalItem = (id) => {
    renderAssetRenewalCalendar();
};

const updateAssetParticipationMeter = (form) => {
    const rows = [...form.querySelectorAll('[data-asset-participation-row]')];
    const total = rows.reduce((sum, row) => {
        const input = row.querySelector('[data-asset-participation-percent]');
        const value = input instanceof HTMLInputElement ? Number(String(input.value).replace(',', '.')) : 0;
        return Number.isFinite(value) ? sum + value : sum;
    }, 0);
    const totalTarget = form.querySelector('[data-asset-participation-total]');
    const pendingTarget = form.querySelector('[data-asset-participation-pending]');
    if (totalTarget) totalTarget.textContent = assetPercent(Math.min(100, total));
    if (pendingTarget) pendingTarget.textContent = assetPercent(Math.max(0, 100 - total));
    syncInmuebleEconomics(form);
};

const updateAssetFiduciaMeter = (form) => {
    const rows = [...form.querySelectorAll('[data-asset-fiducia-row]')];
    const total = rows.reduce((sum, row) => {
        const input = row.querySelector('[data-asset-fiducia-percent]');
        const value = input instanceof HTMLInputElement ? Number(String(input.value).replace(',', '.')) : 0;
        return Number.isFinite(value) ? sum + value : sum;
    }, 0);
    const totalTarget = form.querySelector('[data-asset-fiducia-total]');
    const pendingTarget = form.querySelector('[data-asset-fiducia-pending]');
    if (totalTarget) totalTarget.textContent = assetPercent(Math.min(100, total));
    if (pendingTarget) pendingTarget.textContent = assetPercent(Math.max(0, 100 - total));
};

const renderAssetFiduciaRows = (form, rows = []) => {
    const section = form.querySelector('[data-asset-fiducia]');
    const container = form.querySelector('[data-asset-fiducia-rows]');
    const options = parseAssetJson(form, 'assetOptions');
    const type = form.elements.tipo_activo.value;
    if (section instanceof HTMLElement) {
        section.hidden = type !== 'Inmueble';
    }
    if (!(container instanceof HTMLElement)) {
        return;
    }
    if (type !== 'Inmueble') {
        container.innerHTML = '';
        updateAssetFiduciaMeter(form);
        return;
    }
    const sourceRows = rows.length > 0 ? rows : [{}];
    container.innerHTML = sourceRows.map((row, index) => {
        const beneficiario = row.beneficiario ?? '';
        const calidad = row.calidad ?? '';
        const porcentaje = row.porcentaje ?? '';
        const condicion = row.condicion ?? '';
        const soporte = row.soporte_observaciones ?? '';
        const qualityOptions = (options.calidad_beneficiario_fiducia || []).map((item) => `<option value="${assetEscape(item)}" ${item === calidad ? 'selected' : ''}>${assetEscape(item)}</option>`).join('');
        return `
            <div class="asset-fiducia-row" data-asset-fiducia-row>
                <label>Beneficiario <span class="asset-document-marker">*Control Documental*</span><input name="fiducia_beneficiarios[${index}][beneficiario]" value="${assetEscape(beneficiario)}" placeholder="Persona, empresa o tercero"></label>
                <label>Calidad<select name="fiducia_beneficiarios[${index}][calidad]">${assetPlaceholderOption(calidad)}${qualityOptions}</select></label>
                <label>Porcentaje<input name="fiducia_beneficiarios[${index}][porcentaje]" inputmode="decimal" data-asset-fiducia-percent value="${assetEscape(porcentaje)}" placeholder="0%"></label>
                <label>Condicion<textarea name="fiducia_beneficiarios[${index}][condicion]" rows="2" placeholder="Condicion que activa o limita el beneficio">${assetEscape(condicion)}</textarea></label>
                <label>Soporte u observaciones <span class="asset-document-marker">*Control Documental*</span><input name="fiducia_beneficiarios[${index}][soporte_observaciones]" value="${assetEscape(soporte)}" placeholder="Escritura, instruccion, otrosi..."></label>
                <button type="button" class="asset-remove-fiducia" aria-label="Quitar beneficiario" data-remove-asset-fiducia>&times;</button>
            </div>
        `;
    }).join('');
    updateAssetFiduciaMeter(form);
    updateAssetContextSections(form);
};

const updateAssetSubunitMeter = (form) => {
    const rows = [...form.querySelectorAll('[data-asset-subunit-row]')];
    const totalTarget = form.querySelector('[data-asset-subunit-total]');
    const canonTarget = form.querySelector('[data-asset-subunit-canon]');
    const canon = rows.reduce((sum, row) => {
        const input = row.querySelector('[name$="[canon_mensual]"]');
        return sum + (input instanceof HTMLInputElement ? assetNumeric(input.value) : 0);
    }, 0);
    if (totalTarget) totalTarget.textContent = String(rows.length);
    if (canonTarget) canonTarget.textContent = assetMoney(canon);
};

const renderAssetSubunitRows = (form, rows = []) => {
    const section = form.querySelector('[data-asset-subunits]');
    const container = form.querySelector('[data-asset-subunit-rows]');
    const options = parseAssetJson(form, 'assetOptions');
    const type = form.elements.tipo_activo.value;
    if (section instanceof HTMLElement) {
        section.hidden = type !== 'Inmueble';
    }
    if (!(container instanceof HTMLElement)) {
        return;
    }
    if (type !== 'Inmueble') {
        container.innerHTML = '';
        updateAssetSubunitMeter(form);
        return;
    }
    const sourceRows = rows.length > 0 ? rows : [{}];
    container.innerHTML = sourceRows.map((row, index) => {
        const usoOptions = (options.uso_inmueble || []).map((item) => `<option value="${assetEscape(item)}" ${item === (row.uso ?? '') ? 'selected' : ''}>${assetEscape(item)}</option>`).join('');
        const estadoOptions = (options.estado_ocupacion_subunidad || []).map((item) => `<option value="${assetEscape(item)}" ${item === (row.estado_ocupacion ?? '') ? 'selected' : ''}>${assetEscape(item)}</option>`).join('');
        const title = row.nombre_subunidad || row.codigo_subunidad || `Subunidad ${index + 1}`;
        return `
            <div class="asset-subunit-row" data-asset-subunit-row>
                <div class="asset-subunit-row-header">
                    <div>
                        <strong>${assetEscape(title)}</strong>
                        <span>Datos comerciales y documentales de esta unidad interna</span>
                    </div>
                    <button type="button" class="asset-remove-subunit" aria-label="Quitar subunidad" data-remove-asset-subunit>&times;</button>
                </div>
                <div class="asset-subunit-fields">
                    <label>Codigo interno<input name="subunidades[${index}][codigo_subunidad]" value="${assetEscape(row.codigo_subunidad ?? '')}" placeholder="L-01, Oficina 2..."></label>
                    <label>Nombre subunidad<input name="subunidades[${index}][nombre_subunidad]" value="${assetEscape(row.nombre_subunidad ?? '')}" placeholder="Local 1, Local 2..."></label>
                    <label>Area<input name="subunidades[${index}][area]" value="${assetEscape(row.area ?? '')}" placeholder="m2 / medida"></label>
                    <label>Uso<select name="subunidades[${index}][uso]">${assetPlaceholderOption(row.uso ?? '')}${usoOptions}</select></label>
                    <label>Arrendatario<input name="subunidades[${index}][arrendatario]" value="${assetEscape(row.arrendatario ?? '')}" placeholder="Cliente o vacante"></label>
                    <label>Canon mensual<input name="subunidades[${index}][canon_mensual]" inputmode="decimal" data-money-format value="${assetEscape(assetMoneyPlain(row.canon_mensual) || row.canon_mensual || '')}" placeholder="$0"></label>
                    <label>Administracion mensual<input name="subunidades[${index}][administracion_mensual]" inputmode="decimal" data-money-format value="${assetEscape(assetMoneyPlain(row.administracion_mensual) || row.administracion_mensual || '')}" placeholder="$0"></label>
                    <label>IVA<input name="subunidades[${index}][iva]" inputmode="decimal" data-money-format value="${assetEscape(assetMoneyPlain(row.iva) || row.iva || '')}" placeholder="$0"></label>
                    <label>Retencion<input name="subunidades[${index}][retencion]" inputmode="decimal" data-money-format value="${assetEscape(assetMoneyPlain(row.retencion) || row.retencion || '')}" placeholder="$0"></label>
                    <label>Contrato soporte <span class="asset-document-marker">*Control Documental*</span><input name="subunidades[${index}][contrato_soporte]" value="${assetEscape(row.contrato_soporte ?? '')}" placeholder="Contrato, otrosi, pendiente"></label>
                    <label>Inicio contrato<input name="subunidades[${index}][fecha_inicio]" type="date" value="${assetEscape(row.fecha_inicio ?? '')}"></label>
                    <label>Fin contrato<input name="subunidades[${index}][fecha_fin]" type="date" value="${assetEscape(row.fecha_fin ?? '')}"></label>
                    <label>Estado ocupacion<select name="subunidades[${index}][estado_ocupacion]">${assetPlaceholderOption(row.estado_ocupacion ?? '')}${estadoOptions}</select></label>
                    <label>Observaciones<input name="subunidades[${index}][observaciones]" value="${assetEscape(row.observaciones ?? '')}" placeholder="Condiciones, reajuste, pendientes..."></label>
                </div>
            </div>
        `;
    }).join('');
    updateAssetSubunitMeter(form);
    syncInmuebleEconomics(form);
    updateAssetContextSections(form);
};

const updateAssetInsuranceSections = (form, type) => {
    const visible = assetSupportsInsurance(type);
    if (form.dataset.assetInsuranceTab === 'bienes') {
        form.dataset.assetInsuranceTab = 'coberturas';
    }
    const activeTab = form.dataset.assetInsuranceTab || 'actual';
    const flow = form.querySelector('[data-asset-insurance-flow]');
    if (flow instanceof HTMLElement) {
        flow.hidden = !visible;
        flow.querySelectorAll('[data-asset-insurance-tab]').forEach((button) => {
            if (button instanceof HTMLButtonElement) {
                button.classList.toggle('is-active', button.dataset.assetInsuranceTab === activeTab);
            }
        });
    }
    form.querySelectorAll('[data-asset-insurance-panel]').forEach((section) => {
        if (section instanceof HTMLElement) {
            section.hidden = !visible || section.dataset.assetInsurancePanel !== activeTab;
        }
    });
    updateAssetContextSections(form);
};

const insuranceRequestedCoverages = (form) => insuranceRequestRowsForSelectedProducts(form)
    .filter((row) => row.ramo || row.cobertura || assetNumber(row.valor_asegurado) > 0);

const insuranceCurrentComparisonCoverages = (form) => {
    const requestedRows = insuranceRequestedCoverages(form);
    const currentRows = historyRowsForType(form, '[data-asset-insurance-coverage-row]', assetInsuranceCoverageFields)
        .filter((row) => row.ramo || row.cobertura)
        .filter((row) => [
            'contratado_actual',
            'valor_actual',
            'limite_evento_actual',
            'limite_vigencia_actual',
            'sublimite_actual',
            'deducible_actual',
            'deducible_porcentaje_actual',
            'deducible_minimo_actual',
            'indice_variable_actual',
            'tasa_actual',
            'prima_actual',
            'observaciones_actuales',
        ].some((field) => String(row[field] ?? '').trim() !== '' || assetNumber(row[field]) > 0));
    return mergeInsuranceCoverageRows([...currentRows, ...requestedRows]);
};

const insuranceComparisonProducts = (form, coverages = []) => {
    const products = selectedInsuranceProductsFromForm(form);
    coverages.forEach((row) => {
        if (row.ramo) {
            products.push(row.ramo);
        }
    });
    const seen = new Set();
    return products.filter((product) => {
        const key = normalizeInsuranceText(normalizeInsuranceRamo(product));
        if (!key || seen.has(key)) {
            return false;
        }
        seen.add(key);
        return true;
    });
};

const insuranceRequestedItems = (form) => assetInsuranceEquipmentRows(form)
    .filter((row) => assetNumber(row.valor_asegurable_sugerido || row.valor_reposicion) > 0
        || String(row.item || row.descripcion || row.fuente_consulta || row.serial_referencia || '').trim() !== '');

const insuranceRequestedTotal = (form) => insuranceRequestedCoverages(form)
    .reduce((sum, row) => sum + assetNumber(row.valor_asegurado), 0);

const insuranceCoverageGroups = (rows = []) => rows.reduce((carry, row) => {
    const key = row.ramo || 'Ramo por definir';
    if (!carry.has(key)) {
        carry.set(key, []);
    }
    carry.get(key).push(row);
    return carry;
}, new Map());

const insuranceCoverageQuoteKey = (row = {}) => normalizedCoverageRowKey(row);

const parseInsuranceQuoteMatrix = (value) => {
    if (Array.isArray(value)) {
        return value;
    }
    try {
        const parsed = JSON.parse(String(value || '[]'));
        return Array.isArray(parsed) ? parsed : [];
    } catch (_) {
        return [];
    }
};

const insuranceQuoteMatrixMap = (policy = {}) => parseInsuranceQuoteMatrix(policy.cotizacion_matriz)
    .reduce((carry, row) => {
        const key = insuranceCoverageQuoteKey(row);
        if (key !== '::') {
            carry.set(key, row);
        }
        return carry;
    }, new Map());

const insuranceQuoteMatrixHtml = (form, policy = {}, index = 0) => {
    const coverages = insuranceRequestedCoverages(form);
    const saved = insuranceQuoteMatrixMap(policy);
    if (!coverages.length) {
        return '<p class="muted">Primero registra las coberturas y valores solicitados. Luego aqui comparas la respuesta de cada aseguradora.</p>';
    }
    return `
        <input type="hidden" name="seguro_polizas[${index}][cotizacion_matriz]" value="${assetEscape(policy.cotizacion_matriz || '')}">
        <div class="asset-insurance-quote-matrix" role="table" aria-label="Matriz comparable de cotizacion">
            <div class="asset-insurance-quote-matrix-head" role="row">
                <span>Cobertura</span>
                <span>Valor solicitado</span>
                <span>Cotiza</span>
                <span>Valor asegurado</span>
                <span>Limite evento</span>
                <span>Limite vigencia</span>
                <span>Sublimite</span>
                <span>Ded. % perdida</span>
                <span>Ded. minimo</span>
                <span>Indice</span>
                <span>Tasa</span>
                <span>Prima</span>
                <span>Fuente / nota</span>
            </div>
            ${coverages.map((coverage) => {
                const key = insuranceCoverageQuoteKey(coverage);
                const quote = saved.get(key) || {};
                return `
                    <div class="asset-insurance-quote-matrix-row" role="row" data-asset-insurance-quote-matrix-row data-ramo="${assetEscape(coverage.ramo || '')}" data-cobertura="${assetEscape(coverage.cobertura || '')}">
                        <strong>${assetEscape([coverage.ramo, coverage.cobertura].filter(Boolean).join(' / ') || 'Cobertura')}</strong>
                        <span>${assetEscape(assetMoney(assetNumber(coverage.valor_asegurado)) || 'Por definir')}</span>
                        <label class="asset-insurance-quote-check">
                            <input type="checkbox" data-quote-field="cotiza" ${quote.cotiza === 'Si' ? 'checked' : ''}>
                        </label>
                        <input data-quote-field="valor_asegurado" data-money-format inputmode="decimal" value="${assetEscape(assetMoneyPlain(quote.valor_asegurado) || quote.valor_asegurado || '')}" placeholder="$0">
                        <input data-quote-field="limite_evento" data-money-format inputmode="decimal" value="${assetEscape(assetMoneyPlain(quote.limite_evento) || quote.limite_evento || '')}" placeholder="Por evento">
                        <input data-quote-field="limite_vigencia" data-money-format inputmode="decimal" value="${assetEscape(assetMoneyPlain(quote.limite_vigencia) || quote.limite_vigencia || '')}" placeholder="Por vigencia">
                        <input data-quote-field="sublimite" value="${assetEscape(quote.sublimite || '')}" placeholder="Si aplica">
                        <input data-quote-field="deducible_porcentaje" inputmode="decimal" value="${assetEscape(quote.deducible_porcentaje || '')}" placeholder="Ej. 10%">
                        <input data-quote-field="deducible_minimo" value="${assetEscape(quote.deducible_minimo || '')}" placeholder="Ej. 1 SMMLV">
                        <input data-quote-field="indice" value="${assetEscape(quote.indice || '')}" placeholder="IVA / variable">
                        <input data-quote-field="tasa" inputmode="decimal" value="${assetEscape(quote.tasa || '')}" placeholder="% o por mil">
                        <input data-quote-field="prima" data-money-format inputmode="decimal" value="${assetEscape(assetMoneyPlain(quote.prima) || quote.prima || '')}" placeholder="$0">
                        <input data-quote-field="fuente" value="${assetEscape(quote.fuente || '')}" placeholder="Caratula, anexo, correo">
                    </div>
                `;
            }).join('')}
        </div>
    `;
};

const syncInsuranceQuoteMatrix = (card) => {
    if (!(card instanceof HTMLElement)) {
        return;
    }
    const hidden = card.querySelector('[name$="[cotizacion_matriz]"]');
    if (!(hidden instanceof HTMLInputElement)) {
        return;
    }
    const rows = [...card.querySelectorAll('[data-asset-insurance-quote-matrix-row]')].map((row) => {
        const valueFor = (field) => {
            const input = row.querySelector(`[data-quote-field="${field}"]`);
            if (input instanceof HTMLInputElement) {
                return input.type === 'checkbox' ? (input.checked ? 'Si' : '') : input.value;
            }
            return '';
        };
        return {
            ramo: row.dataset.ramo || '',
            cobertura: row.dataset.cobertura || '',
            cotiza: valueFor('cotiza'),
            valor_asegurado: valueFor('valor_asegurado'),
            limite_evento: valueFor('limite_evento'),
            limite_vigencia: valueFor('limite_vigencia'),
            sublimite: valueFor('sublimite'),
            deducible_porcentaje: valueFor('deducible_porcentaje'),
            deducible_minimo: valueFor('deducible_minimo'),
            indice: valueFor('indice'),
            tasa: valueFor('tasa'),
            prima: valueFor('prima'),
            fuente: valueFor('fuente'),
        };
    });
    hidden.value = JSON.stringify(rows);
};

const updatePolicyAllocationRow = (card) => {
    if (!(card instanceof HTMLElement)) {
        return;
    }
    const scope = card.querySelector('[name$="[alcance_poliza]"]')?.value || 'Individual';
    const methodInput = card.querySelector('[name$="[metodo_distribucion]"]');
    const premiumInput = card.querySelector('[name$="[prima_total]"]');
    const coefficientInput = card.querySelector('[name$="[coeficiente_participacion]"]');
    const unitsInput = card.querySelector('[name$="[numero_unidades_cubiertas]"]');
    const assignedInput = card.querySelector('[name$="[prima_asignada]"]');
    if (!(assignedInput instanceof HTMLInputElement)) {
        return;
    }
    const fallbackMethod = scope === 'Matriz/global' ? 'Coeficiente' : 'Individual';
    if (methodInput instanceof HTMLSelectElement && !methodInput.value) {
        methodInput.value = fallbackMethod;
    }
    const method = methodInput?.value || fallbackMethod;
    const premium = assetNumber(premiumInput?.value);
    let assigned = 0;
    if (scope !== 'Matriz/global' || method === 'Individual') {
        assigned = premium;
    } else if (method === 'Coeficiente') {
        assigned = premium * assetNumber(coefficientInput?.value) / 100;
    } else if (method === 'Por unidades') {
        const units = assetNumber(unitsInput?.value);
        assigned = units > 0 ? premium / units : 0;
    }
    assignedInput.readOnly = method !== 'Manual';
    if (method !== 'Manual') {
        assignedInput.value = assigned > 0 ? String(Math.round(assigned)) : '';
    }
};

const updatePolicyAllocationRows = (form) => {
    form.querySelectorAll('[data-asset-insurance-policy-row]').forEach(updatePolicyAllocationRow);
};

const syncMatrixPolicyProxy = (form, target) => {
    const row = target.closest('[data-asset-matrix-row]');
    const field = target.dataset.matrixPolicyField;
    if (!(row instanceof HTMLElement) || !field) {
        return;
    }
    const index = Number(row.dataset.assetMatrixRow);
    const card = assetInsurancePolicyCards(form)[index];
    const source = card?.querySelector(`[name$="[${field}]"]`);
    if (source instanceof HTMLInputElement || source instanceof HTMLSelectElement) {
        source.value = target.value;
    }
    if (['prima_neta', 'gastos_expedicion', 'iva'].includes(field)) {
        const total = ['prima_neta', 'gastos_expedicion', 'iva']
            .reduce((sum, key) => sum + assetNumber(card?.querySelector(`[name$="[${key}]"]`)?.value), 0);
        const totalSource = card?.querySelector('[name$="[prima_total]"]');
        const totalProxy = row.querySelector('[data-matrix-policy-field="prima_total"]');
        if (totalSource instanceof HTMLInputElement) {
            totalSource.value = total > 0 ? String(Math.round(total)) : '';
        }
        if (totalProxy instanceof HTMLInputElement) {
            totalProxy.value = total > 0 ? assetMoneyPlain(total) : '';
        }
    }
    updatePolicyAllocationRow(card);
    const assignedSource = card?.querySelector('[name$="[prima_asignada]"]');
    const assignedProxy = row.querySelector('[data-matrix-policy-field="prima_asignada"]');
    if (assignedSource instanceof HTMLInputElement && assignedProxy instanceof HTMLInputElement) {
        assignedProxy.value = assignedSource.value ? (assetMoneyPlain(assignedSource.value) || assignedSource.value) : '';
    }
};

const syncAllInsuranceQuoteMatrices = (form) => {
    const cards = [...form.querySelectorAll('[data-asset-insurance-policy-row]')];
    cards.forEach((card, index) => {
        const hidden = card.querySelector('[name$="[cotizacion_matriz]"]');
        if (!(hidden instanceof HTMLInputElement)) {
            return;
        }
        const rows = [...form.querySelectorAll(`[data-asset-insurance-offer-quote-row][data-policy-index="${index}"]`)].map((row) => {
            const valueFor = (field) => {
                const input = row.querySelector(`[data-quote-field="${field}"]`);
                if (input instanceof HTMLInputElement) {
                    return input.type === 'checkbox' ? (input.checked ? 'Si' : '') : input.value;
                }
                return '';
            };
            return {
                ramo: row.dataset.ramo || '',
                cobertura: row.dataset.cobertura || '',
                cotiza: valueFor('cotiza'),
                valor_asegurado: valueFor('valor_asegurado'),
                limite_evento: valueFor('limite_evento'),
                limite_vigencia: valueFor('limite_vigencia'),
                sublimite: valueFor('sublimite'),
                deducible_porcentaje: valueFor('deducible_porcentaje'),
                deducible_minimo: valueFor('deducible_minimo'),
                indice: valueFor('indice'),
                tasa: valueFor('tasa'),
                prima: valueFor('prima'),
                fuente: valueFor('fuente'),
            };
        });
        hidden.value = JSON.stringify(rows);
        const premiumInput = card.querySelector('[name$="[prima_total]"]');
        const totalPremium = rows.reduce((sum, row) => sum + assetNumber(row.prima), 0);
        if (premiumInput instanceof HTMLInputElement && totalPremium > 0) {
            premiumInput.value = String(Math.round(totalPremium));
        }
    });
};

const insuranceOfferComparisonMatrixHtml = (form, policies = []) => {
    const coverages = insuranceRequestedCoverages(form);
    if (!coverages.length) {
        return `
            <div class="asset-insurance-quote-empty">
                Primero selecciona coberturas y diligencia valores asegurados. Despues aqui aparecera una sola matriz para comparar a todos los oferentes.
            </div>
        `;
    }
    if (!policies.length) {
        return `
            <div class="asset-insurance-quote-empty">
                Agrega una oferta solo cuando tengas una aseguradora o intermediario para cotizar. La poliza matriz no cuenta como oferta.
            </div>
        `;
    }
    const grouped = insuranceCoverageGroups(coverages);
    const offerCount = Math.max(policies.length, 1);
    const offerGridStyle = `grid-template-columns: minmax(210px, 1fr) minmax(190px, .8fr) repeat(${offerCount}, minmax(220px, 1fr)); min-width: ${400 + offerCount * 220}px`;
    return `
        <div class="asset-insurance-offer-matrix">
            <div class="asset-insurance-offer-matrix-head">
                <strong>Matriz unica de respuestas</strong>
                <span>La solicitud base es igual para todos; aqui solo comparas la respuesta de cada aseguradora.</span>
            </div>
            <div class="asset-insurance-offer-table">
                <div class="asset-insurance-offer-table-head" style="${assetEscape(offerGridStyle)}">
                    <span>Ramo / cobertura solicitada</span>
                    <span>Valor y fuente base</span>
                    ${policies.map((policy, index) => `<span>${assetEscape(policy.aseguradora || `Oferente ${index + 1}`)}</span>`).join('')}
                </div>
                ${[...grouped.entries()].map(([ramo, rows]) => `
                    <div class="asset-insurance-offer-group" style="min-width: ${400 + offerCount * 220}px">${assetEscape(ramo)}</div>
                    ${rows.map((coverage) => `
                        <div class="asset-insurance-offer-table-row" style="${assetEscape(offerGridStyle)}">
                            <strong>${assetEscape(coverage.cobertura || 'Cobertura')}</strong>
                            <span>
                                ${assetEscape(assetMoney(assetNumber(coverage.valor_asegurado)) || 'Valor por definir')}
                                <small>${assetEscape(coverage.fuente_valor_asegurado || coverage.observaciones || 'Fuente pendiente')}</small>
                            </span>
                            ${policies.map((policy, index) => {
                                const quote = insuranceQuoteMatrixMap(policy).get(insuranceCoverageQuoteKey(coverage)) || {};
                                return `
                                    <div class="asset-insurance-offer-cell" data-asset-insurance-offer-quote-row data-policy-index="${index}" data-ramo="${assetEscape(coverage.ramo || '')}" data-cobertura="${assetEscape(coverage.cobertura || '')}">
                                        <label class="asset-insurance-offer-check"><input type="checkbox" data-quote-field="cotiza" ${quote.cotiza === 'Si' ? 'checked' : ''}> Cotiza</label>
                                        <input data-quote-field="valor_asegurado" data-money-format inputmode="decimal" value="${assetEscape(assetMoneyPlain(quote.valor_asegurado) || quote.valor_asegurado || '')}" placeholder="Valor asegurado">
                                        <input data-quote-field="limite_evento" data-money-format inputmode="decimal" value="${assetEscape(assetMoneyPlain(quote.limite_evento) || quote.limite_evento || '')}" placeholder="Limite evento">
                                        <input data-quote-field="limite_vigencia" data-money-format inputmode="decimal" value="${assetEscape(assetMoneyPlain(quote.limite_vigencia) || quote.limite_vigencia || '')}" placeholder="Limite vigencia">
                                        <input data-quote-field="sublimite" value="${assetEscape(quote.sublimite || '')}" placeholder="Sublimite">
                                        <input data-quote-field="deducible_porcentaje" inputmode="decimal" value="${assetEscape(quote.deducible_porcentaje || '')}" placeholder="% perdida">
                                        <input data-quote-field="deducible_minimo" value="${assetEscape(quote.deducible_minimo || '')}" placeholder="Minimo">
                                        <input data-quote-field="indice" value="${assetEscape(quote.indice || '')}" placeholder="Indice">
                                        <input data-quote-field="tasa" inputmode="decimal" value="${assetEscape(quote.tasa || '')}" placeholder="Tasa">
                                        <input data-quote-field="prima" data-money-format inputmode="decimal" value="${assetEscape(assetMoneyPlain(quote.prima) || quote.prima || '')}" placeholder="Prima">
                                        <input data-quote-field="fuente" value="${assetEscape(quote.fuente || '')}" placeholder="Fuente / nota">
                                    </div>
                                `;
                            }).join('')}
                        </div>
                    `).join('')}
                `).join('')}
            </div>
        </div>
    `;
};

const insuranceQuoteRecommendationHtml = (form) => {
    const targetCoverages = insuranceRequestedCoverages(form);
    const targetCount = targetCoverages.length;
    const policies = assetFormRows(form, '[data-asset-insurance-policy-row]', assetInsurancePolicyFields)
        .filter((policy) => policy.alcance_poliza !== 'Matriz/global')
        .filter((policy) => policy.estado !== 'Vencida')
        .filter((policy) => policy.aseguradora || policy.numero_poliza || parseInsuranceQuoteMatrix(policy.cotizacion_matriz).length);
    if (!targetCount || !policies.length) {
        return '';
    }
    const evaluated = policies.map((policy, index) => {
        const matrix = parseInsuranceQuoteMatrix(policy.cotizacion_matriz);
        const quoted = matrix.filter((row) => row.cotiza === 'Si');
        const premium = assetNumber(policy.prima_total) || matrix.reduce((sum, row) => sum + assetNumber(row.prima), 0);
        const missing = targetCount - quoted.length;
        const missingDetails = quoted.filter((row) => !(row.deducible_porcentaje || row.deducible_minimo || row.deducible) || !row.tasa || !row.prima).length;
        return {
            index,
            insurer: policy.aseguradora || `Oferta ${index + 1}`,
            full: targetCount > 0 && missing <= 0,
            quotedCount: quoted.length,
            targetCount,
            premium,
            missing,
            missingDetails,
        };
    });
    const eligible = evaluated.filter((row) => row.full && row.premium > 0).sort((a, b) => a.premium - b.premium);
    const best = eligible[0] || null;
    const message = best
        ? `Decision sugerida: ${best.insurer}, porque cotiza todas las coberturas solicitadas y registra la menor prima comparable.`
        : 'Decision pendiente: registra las respuestas de las aseguradoras y luego toma la poliza que ofrezca mejor cobertura neta.';
    const comparisonRows = policies.flatMap((policy, index) => {
        const insurer = policy.aseguradora || `Oferta ${index + 1}`;
        return parseInsuranceQuoteMatrix(policy.cotizacion_matriz).map((row) => ({
            insurer,
            ...row,
        }));
    });
    return `
        <div class="asset-insurance-analysis-card">
            <strong>${assetEscape(message)}</strong>
            <p>La decision se formaliza con el boton "Tomar esta poliza". Esa opcion alimenta el resumen de poliza vigente, cambios, historial y calendario de renovacion.</p>
            <div>
                ${evaluated.map((row) => `
                    <span class="${best && best.index === row.index ? 'is-best' : ''}">
                        ${assetEscape(row.insurer)}: ${row.quotedCount}/${row.targetCount} coberturas${row.premium > 0 ? ` / ${assetEscape(assetMoney(row.premium))}` : ' / prima pendiente'}${row.missingDetails ? ` / faltan datos tecnicos` : ''}
                    </span>
                `).join('')}
            </div>
            ${comparisonRows.length ? `
                <details class="asset-insurance-comparison">
                    <summary>Comparativo tecnico tipo tabla</summary>
                    <div class="asset-insurance-comparison-table">
                        <div>
                            <strong>Aseguradora</strong>
                            <strong>Cobertura</strong>
                            <strong>Cotiza</strong>
                            <strong>Valor asegurado</strong>
                            <strong>Limite evento</strong>
                            <strong>Limite vigencia</strong>
                            <strong>Sublimite</strong>
                            <strong>Deducible</strong>
                            <strong>Indice</strong>
                            <strong>Tasa</strong>
                            <strong>Prima</strong>
                        </div>
                        ${comparisonRows.map((row) => `
                            <div>
                                <span>${assetEscape(row.insurer)}</span>
                                <span>${assetEscape([row.ramo, row.cobertura].filter(Boolean).join(' / '))}</span>
                                <span>${row.cotiza === 'Si' ? 'Si' : 'No'}</span>
                                <span>${assetEscape(assetMoney(assetNumber(row.valor_asegurado)) || row.valor_asegurado || '')}</span>
                                <span>${assetEscape(assetMoney(assetNumber(row.limite_evento)) || row.limite_evento || '')}</span>
                                <span>${assetEscape(assetMoney(assetNumber(row.limite_vigencia)) || row.limite_vigencia || '')}</span>
                                <span>${assetEscape(row.sublimite || '')}</span>
                                <span>${assetEscape([row.deducible_porcentaje, row.deducible_minimo, row.deducible].filter(Boolean).join(' / '))}</span>
                                <span>${assetEscape(row.indice || '')}</span>
                                <span>${assetEscape(row.tasa || '')}</span>
                                <span>${assetEscape(assetMoney(assetNumber(row.prima)) || row.prima || '')}</span>
                            </div>
                        `).join('')}
                    </div>
                </details>
            ` : ''}
        </div>
    `;
};

const renderInsuranceQuoteAnalysis = (form) => {
    const target = form.querySelector('[data-asset-insurance-quote-analysis]');
    if (target instanceof HTMLElement) {
        target.innerHTML = insuranceQuoteRecommendationHtml(form);
    }
};

const insuranceQuotePackageHtml = (form, policy = {}) => {
    const products = splitInsuranceSelection(policy.ramo || joinInsuranceSelection(selectedInsuranceProductsFromForm(form)));
    const coverages = insuranceRequestedCoverages(form);
    const grouped = insuranceCoverageGroups(coverages);
    const items = insuranceRequestedItems(form);
    const total = insuranceRequestedTotal(form);
    return `
        <div class="asset-insurance-quote-package">
            <div class="asset-insurance-quote-package-head">
                <strong>Solicitud consolidada para cotizar</strong>
                <span>${assetEscape(products.join(' | ') || 'Ramos por definir')}</span>
                <b>${assetEscape(assetMoney(total) || '$0')}</b>
            </div>
            <div class="asset-insurance-quote-table">
                <div class="asset-insurance-quote-table-head">
                    <span>Ramo</span>
                    <span>Cobertura</span>
                    <span>Valor solicitado</span>
                    <span>Fuente / soporte</span>
                </div>
                ${coverages.length ? [...grouped.entries()].map(([ramo, rows]) => `
                    <div class="asset-insurance-quote-group"><span>${assetEscape(ramo)}</span></div>
                    ${rows.map((row) => `
                        <div>
                            <span>${assetEscape(row.ramo || 'Por definir')}</span>
                            <span>${assetEscape(row.cobertura || 'Cobertura')}</span>
                            <span>${assetEscape(assetMoney(assetNumber(row.valor_asegurado)) || 'Por definir')}</span>
                            <span>${assetEscape(row.fuente_valor_asegurado || row.observaciones || 'Pendiente')}</span>
                        </div>
                    `).join('')}
                `).join('') : '<p class="muted">Selecciona coberturas y valores antes de solicitar cotizaciones.</p>'}
            </div>
            ${items.length ? `
                <details class="asset-insurance-quote-items">
                    <summary>Bienes/equipos reportados (${items.length})</summary>
                    <div class="asset-insurance-quote-table">
                        <div class="asset-insurance-quote-table-head">
                            <span>Categoria</span>
                            <span>Item</span>
                            <span>Valor reposicion</span>
                            <span>Fuente</span>
                        </div>
                        ${items.map((row) => `
                            <div>
                                <span>${assetEscape(row.categoria_item || 'Item')}</span>
                                <span>${assetEscape(row.item || row.descripcion || 'Por describir')}</span>
                                <span>${assetEscape(assetMoney(assetNumber(row.valor_asegurable_sugerido || row.valor_reposicion)) || 'Por definir')}</span>
                                <span>${assetEscape(row.fuente_consulta || 'Pendiente')}</span>
                            </div>
                        `).join('')}
                    </div>
                </details>
            ` : ''}
        </div>
    `;
};

const printableInsuranceQuoteHtml = (form, policy = {}) => {
    const assetName = form.elements.nombre_descripcion?.value || 'Activo';
    const assetId = form.elements.identificador?.value || '';
    const insurer = policy.aseguradora || 'Aseguradora cotizante';
    const coverageGroups = insuranceCoverageGroups(insuranceRequestedCoverages(form));
    return `
        <!doctype html>
        <html lang="es">
        <head>
            <meta charset="utf-8">
            <title>Solicitud de cotizacion - ${assetEscape(insurer)}</title>
            <style>
                body{font-family:Arial,sans-serif;color:#102018;margin:28px;line-height:1.35}
                h1{font-size:24px;margin:0 0 8px;color:#005c45}
                h2{font-size:16px;margin:24px 0 10px;color:#005c45}
                table{width:100%;border-collapse:collapse;margin-top:10px;font-size:12px}
                th,td{border:1px solid #cfded5;padding:8px;text-align:left;vertical-align:top}
                th{background:#eef7f2;color:#005c45}
                .meta{display:grid;grid-template-columns:repeat(2,1fr);gap:8px;margin:16px 0}
                .meta div{border:1px solid #d8e5dc;padding:10px;border-radius:8px}
            </style>
        </head>
        <body>
            <h1>Solicitud de cotizacion de seguros</h1>
            <p>Documento base para que la aseguradora cotice sobre las mismas condiciones solicitadas.</p>
            <div class="meta">
                <div><strong>Aseguradora:</strong><br>${assetEscape(insurer)}</div>
                <div><strong>Activo:</strong><br>${assetEscape(assetName)} ${assetId ? ` / ${assetEscape(assetId)}` : ''}</div>
                <div><strong>Contacto:</strong><br>${assetEscape([policy.contacto_nombre, policy.contacto_correo, policy.contacto_celular].filter(Boolean).join(' / ') || 'Por definir')}</div>
                <div><strong>Fecha:</strong><br>${assetDate(new Date().toISOString().slice(0, 10))}</div>
            </div>
            <h2>Ramos, coberturas y valores solicitados</h2>
            <table>
                <thead><tr><th>Ramo</th><th>Cobertura</th><th>Valor solicitado</th><th>Fuente / soporte</th><th>Observacion</th></tr></thead>
                <tbody>
                    ${[...coverageGroups.entries()].map(([ramo, rows]) => `
                        <tr><th colspan="5">${assetEscape(ramo)}</th></tr>
                        ${rows.map((row) => `
                            <tr>
                                <td>${assetEscape(row.ramo || '')}</td>
                                <td>${assetEscape(row.cobertura || '')}</td>
                                <td>${assetEscape(assetMoney(assetNumber(row.valor_asegurado)) || 'Por definir')}</td>
                                <td>${assetEscape(row.fuente_valor_asegurado || '')}</td>
                                <td>${assetEscape(row.observaciones || '')}</td>
                            </tr>
                        `).join('')}
                    `).join('')}
                </tbody>
            </table>
            <h2>Bienes/equipos reportados si aplican</h2>
            <table>
                <thead><tr><th>Categoria</th><th>Item</th><th>Descripcion</th><th>Valor reposicion</th><th>Fuente</th></tr></thead>
                <tbody>
                    ${insuranceRequestedItems(form).map((row) => `
                        <tr>
                            <td>${assetEscape(row.categoria_item || '')}</td>
                            <td>${assetEscape(row.item || '')}</td>
                            <td>${assetEscape(row.descripcion || '')}</td>
                            <td>${assetEscape(assetMoney(assetNumber(row.valor_asegurable_sugerido || row.valor_reposicion)) || '')}</td>
                            <td>${assetEscape(row.fuente_consulta || '')}</td>
                        </tr>
                    `).join('') || '<tr><td colspan="5">No aplica relacion de bienes para las coberturas solicitadas.</td></tr>'}
                </tbody>
            </table>
            <h2>La aseguradora debe informar</h2>
            <p>Tasa, prima, deducibles, limites por evento, sublimites, indice variable, exclusiones, garantias, clausulado, vigencia y forma de pago.</p>
        </body>
        </html>
    `;
};

const printInsuranceQuoteRequest = (form, policy) => {
    const win = window.open('', '_blank');
    if (!win) {
        return;
    }
    win.document.write(printableInsuranceQuoteHtml(form, policy));
    win.document.close();
    win.focus();
    win.print();
};

const insurancePolicyRowsFromForm = (form) => assetFormRows(form, '[data-asset-insurance-policy-row]', assetInsurancePolicyFields);

const assetInsurancePolicyCards = (form) => [...form.querySelectorAll('[data-asset-insurance-policy-row]')];

const insurancePolicyRowsWithIndexFromForm = (form) => assetInsurancePolicyCards(form)
    .map((card, index) => ({
        index,
        row: assetInsurancePolicyFields.reduce((carry, field) => {
            carry[field] = card.querySelector(`[name$="[${field}]"]`)?.value ?? '';
            return carry;
        }, {}),
    }));

const renderAssetInsuranceMatrixSummary = (form) => {
    const target = form.querySelector('[data-asset-insurance-matrix-summary]');
    if (!(target instanceof HTMLElement)) {
        return;
    }
    const rows = insurancePolicyRowsWithIndexFromForm(form)
        .filter(({ row }) => row.alcance_poliza === 'Matriz/global' || row.metodo_distribucion === 'Coeficiente' || row.metodo_distribucion === 'Por unidades' || row.grupo_poliza);
    const assetName = form.elements.nombre_descripcion?.value || 'este activo';
    target.innerHTML = `
        <div class="asset-insurance-matrix-guide">
            <strong>Poliza matriz/global de ${assetEscape(assetName)}</strong>
            <p>Este bloque es opcional. Usalo solamente cuando una sola poliza cubre varios inmuebles, unidades o todo un edificio; aqui no se comparan ofertas.</p>
            <div class="asset-insurance-matrix-methods">
                <span><b>Coeficiente PH</b> Prima asignada = prima total x coeficiente %.</span>
                <span><b>Por unidades</b> Prima asignada = prima total / unidades cubiertas.</span>
                <span><b>Manual</b> El analista define el valor y documenta el criterio.</span>
            </div>
        </div>
        ${rows.map(({ row, index }, displayIndex) => `
            <div class="asset-insurance-matrix-form" data-asset-matrix-row="${index}">
                <div class="asset-insurance-matrix-title">
                    <strong>Poliza matriz ${displayIndex + 1}</strong>
                    <span>${assetEscape(row.grupo_poliza || assetName)}</span>
                </div>
                <div class="asset-insurance-matrix-fields">
                    <label>Numero poliza<input data-matrix-policy-field="numero_poliza" value="${assetEscape(row.numero_poliza || '')}" placeholder="Numero de poliza"></label>
                    <label>Aseguradora<input data-matrix-policy-field="aseguradora" value="${assetEscape(row.aseguradora || '')}" placeholder="Aseguradora"></label>
                    <label>Prima neta<input data-matrix-policy-field="prima_neta" data-money-format inputmode="decimal" value="${assetEscape(assetMoneyPlain(row.prima_neta) || row.prima_neta || '')}" placeholder="$0"></label>
                    <label>Gastos expedicion<input data-matrix-policy-field="gastos_expedicion" data-money-format inputmode="decimal" value="${assetEscape(assetMoneyPlain(row.gastos_expedicion) || row.gastos_expedicion || '')}" placeholder="$0"></label>
                    <label>IVA<input data-matrix-policy-field="iva" data-money-format inputmode="decimal" value="${assetEscape(assetMoneyPlain(row.iva) || row.iva || '')}" placeholder="$0"></label>
                    <label>Prima total<input data-matrix-policy-field="prima_total" data-money-format inputmode="decimal" value="${assetEscape(assetMoneyPlain(row.prima_total) || row.prima_total || '')}" placeholder="$0"></label>
                    <label>Metodo distribucion<select data-matrix-policy-field="metodo_distribucion" data-policy-allocation-field><option value="Coeficiente" ${row.metodo_distribucion === 'Coeficiente' ? 'selected' : ''}>Coeficiente PH</option><option value="Por unidades" ${row.metodo_distribucion === 'Por unidades' ? 'selected' : ''}>Por unidades</option><option value="Manual" ${row.metodo_distribucion === 'Manual' ? 'selected' : ''}>Manual documentado</option></select></label>
                    <label>Coeficiente %<input data-matrix-policy-field="coeficiente_participacion" data-policy-allocation-field inputmode="decimal" value="${assetEscape(row.coeficiente_participacion || '')}" placeholder="3.5"></label>
                    <label>Unidades cubiertas<input data-matrix-policy-field="numero_unidades_cubiertas" data-policy-allocation-field inputmode="numeric" value="${assetEscape(row.numero_unidades_cubiertas || '')}" placeholder="5"></label>
                    <label>Prima asignada<input data-matrix-policy-field="prima_asignada" data-policy-allocation-field data-money-format inputmode="decimal" value="${assetEscape(assetMoneyPlain(row.prima_asignada) || row.prima_asignada || '')}" placeholder="$0"></label>
                    <label>Inicio vigencia<input data-matrix-policy-field="fecha_inicio" type="date" value="${assetEscape(row.fecha_inicio || '')}"></label>
                    <label>Fin vigencia<input data-matrix-policy-field="fecha_fin" type="date" value="${assetEscape(row.fecha_fin || '')}"></label>
                    <label>Renovacion<input data-matrix-policy-field="fecha_renovacion" type="date" value="${assetEscape(row.fecha_renovacion || '')}"></label>
                    <label class="wide">Observacion distribucion<input data-matrix-policy-field="observaciones_distribucion" value="${assetEscape(row.observaciones_distribucion || '')}" placeholder="Coeficiente, unidades o criterio usado"></label>
                </div>
                <p class="muted">Total sugerido = prima neta + gastos de expedicion + IVA. La prima asignada se calcula segun el metodo de distribucion.</p>
            </div>
        `).join('') || '<p class="muted">Aun no hay poliza matriz registrada. Usa el boton superior para crearla.</p>'}
    `;
};

const renderAssetInsurancePolicyRows = (form, rows = []) => {
    const container = form.querySelector('[data-asset-insurance-policy-rows]');
    const options = parseAssetJson(form, 'assetOptions');
    const type = form.elements.tipo_activo.value;
    updateAssetInsuranceSections(form, type);
    if (!(container instanceof HTMLElement)) {
        return;
    }
    if (!assetSupportsInsurance(type)) {
        container.innerHTML = '';
        return;
    }
    const selectedProducts = selectedInsuranceProductsFromForm(form);
    const requestedTotal = insuranceRequestedTotal(form);
    const defaultQuote = (index) => ({
        ano: String(new Date().getFullYear()),
        ramo: joinInsuranceSelection(selectedProducts),
        valor_asegurado_total: requestedTotal > 0 ? String(Math.round(requestedTotal)) : '',
        estado: 'Cotizacion solicitada',
        observaciones: `Oferta minima ${index + 1}. Cotizar todos los ramos y coberturas solicitados con tasas, primas, deducibles, limites y sublimites.`,
    });
    const seenMatrixRows = new Set();
    const sourceRows = (rows.length > 0 ? [...rows] : [])
        .filter((row) => !(String(row.observaciones || '').startsWith('Oferta minima') && !row.aseguradora && !row.numero_poliza && !parseInsuranceQuoteMatrix(row.cotizacion_matriz).length))
        .filter((row) => {
            if (row.alcance_poliza !== 'Matriz/global') {
                return true;
            }
            const key = [row.ano, row.numero_poliza, row.grupo_poliza, row.fecha_inicio, row.fecha_fin, row.prima_total].join('|');
            if (seenMatrixRows.has(key)) {
                return false;
            }
            seenMatrixRows.add(key);
            return true;
        });
    const normalizedRows = sourceRows.map((row, index) => ({
        ...defaultQuote(index),
        ...row,
        ramo: row.ramo || joinInsuranceSelection(selectedProducts),
        valor_asegurado_total: row.valor_asegurado_total || (requestedTotal > 0 ? String(Math.round(requestedTotal)) : ''),
    }));
    container.innerHTML = `
        ${insuranceQuotePackageHtml(form, { ramo: joinInsuranceSelection(selectedProducts) })}
        <div class="asset-insurance-offer-cards">
        ${normalizedRows.map((row, index) => {
        const documentTypeOptions = (options.tipo_documento_poliza || []).map((item) => `<option value="${assetEscape(item)}" ${item === (row.tipo_documento ?? '') ? 'selected' : ''}>${assetEscape(item)}</option>`).join('');
        const paymentOptions = (options.forma_pago_seguro || []).map((item) => `<option value="${assetEscape(item)}" ${item === (row.forma_pago ?? '') ? 'selected' : ''}>${assetEscape(item)}</option>`).join('');
        const stateOptions = (options.estado_poliza || []).map((item) => `<option value="${assetEscape(item)}" ${item === (row.estado ?? '') ? 'selected' : ''}>${assetEscape(item)}</option>`).join('');
        const selectedRamos = new Set(splitInsuranceSelection(row.ramo ?? ''));
        const allowedProducts = new Set(selectedRamos);
        const adopted = row.adoptada === 'Si';
        const matrixPolicy = row.alcance_poliza === 'Matriz/global';
        const ramoChecklist = [...allowedProducts].map((item) => {
            const catalog = insuranceCatalogForProduct(form, item);
            return `
            <label class="asset-coverage-chip asset-insurance-ramo-chip ${selectedRamos.has(item) ? 'is-selected' : ''}">
                <input type="checkbox" data-asset-policy-ramo-toggle value="${assetEscape(item)}" ${selectedRamos.has(item) ? 'checked' : ''}>
                <span>
                    ${catalog ? `<em>${assetEscape(insuranceCatalogValue(catalog, 'Código', 'Codigo'))}</em>` : ''}
                    <strong>${assetEscape(item)}</strong>
                    ${catalog ? `<small>${assetEscape(insuranceCatalogMacro(catalog))}</small>` : ''}
                </span>
            </label>
        `;
        }).join('');
        return `
            <details class="asset-insurance-policy-card asset-insurance-offer-card" data-asset-insurance-policy-row ${matrixPolicy ? 'hidden' : 'open'}>
                <summary>
                    <strong>${adopted ? 'Poliza vigente' : `Oferta ${index + 1}`}</strong>
                    <span>${assetEscape([row.aseguradora, row.numero_poliza, row.prima_total ? assetMoney(row.prima_total) : '', adopted ? 'Adoptada' : ''].filter(Boolean).join(' / ') || 'Completa los datos basicos para cotizar')}</span>
                </summary>
                <div class="asset-policy-decision ${adopted ? 'is-adopted' : ''}">
                    <input type="hidden" name="seguro_polizas[${index}][adoptada]" value="${assetEscape(row.adoptada ?? '')}">
                    <input type="hidden" name="seguro_polizas[${index}][fecha_adopcion]" value="${assetEscape(row.fecha_adopcion ?? '')}">
                    <input type="hidden" name="seguro_polizas[${index}][cotizacion_matriz]" value="${assetEscape(row.cotizacion_matriz ?? '')}">
                    <label>Criterio decision<input name="seguro_polizas[${index}][criterio_adopcion]" value="${assetEscape(row.criterio_adopcion ?? '')}" placeholder="Mejor equilibrio cobertura, deducible y prima"></label>
                    <button type="button" data-print-asset-insurance-quote>Generar solicitud PDF</button>
                    <button type="button" data-adopt-asset-insurance-policy>${adopted ? 'Opcion seleccionada' : 'Tomar esta poliza'}</button>
                </div>
                <div class="asset-insurance-policy-row asset-insurance-quote-contact">
                    <input type="hidden" name="seguro_polizas[${index}][ano]" value="${assetEscape(row.ano ?? '')}">
                    <input type="hidden" name="seguro_polizas[${index}][tipo_documento]" value="${assetEscape(row.tipo_documento ?? '')}">
                    <input type="hidden" name="seguro_polizas[${index}][ramo]" data-asset-policy-ramo value="${assetEscape(row.ramo ?? '')}">
                    <input type="hidden" name="seguro_polizas[${index}][agencia_expedidora]" value="${assetEscape(row.agencia_expedidora ?? '')}">
                    <input type="hidden" name="seguro_polizas[${index}][codigo_agencia]" value="${assetEscape(row.codigo_agencia ?? '')}">
                    <input type="hidden" name="seguro_polizas[${index}][numero_emision]" value="${assetEscape(row.numero_emision ?? '')}">
                    <input type="hidden" name="seguro_polizas[${index}][numero_pago_electronico]" value="${assetEscape(row.numero_pago_electronico ?? '')}">
                    <input type="hidden" name="seguro_polizas[${index}][modalidad_facturacion]" value="${assetEscape(row.modalidad_facturacion ?? '')}">
                    <input type="hidden" name="seguro_polizas[${index}][coaseguro]" value="${assetEscape(row.coaseguro ?? '')}">
                    <input type="hidden" name="seguro_polizas[${index}][tomador]" value="${assetEscape(row.tomador ?? '')}">
                    <input type="hidden" name="seguro_polizas[${index}][asegurado]" value="${assetEscape(row.asegurado ?? '')}">
                    <input type="hidden" name="seguro_polizas[${index}][beneficiario]" value="${assetEscape(row.beneficiario ?? '')}">
                    <input type="hidden" name="seguro_polizas[${index}][direccion_riesgo]" value="${assetEscape(row.direccion_riesgo ?? '')}">
                    <input type="hidden" name="seguro_polizas[${index}][ciudad_riesgo]" value="${assetEscape(row.ciudad_riesgo ?? '')}">
                    <input type="hidden" name="seguro_polizas[${index}][actividad_riesgo]" value="${assetEscape(row.actividad_riesgo ?? '')}">
                    <input type="hidden" name="seguro_polizas[${index}][tipo_riesgo]" value="${assetEscape(row.tipo_riesgo ?? '')}">
                    <input type="hidden" name="seguro_polizas[${index}][prima_neta]" value="${assetEscape(row.prima_neta ?? '')}">
                    <input type="hidden" name="seguro_polizas[${index}][iva]" value="${assetEscape(row.iva ?? '')}">
                    <input type="hidden" name="seguro_polizas[${index}][gastos_expedicion]" value="${assetEscape(row.gastos_expedicion ?? '')}">
                    <input type="hidden" name="seguro_polizas[${index}][numero_cuotas]" value="${assetEscape(row.numero_cuotas ?? '')}">
                    <input type="hidden" name="seguro_polizas[${index}][anexos_endosos]" value="${assetEscape(row.anexos_endosos ?? '')}">
                    <input type="hidden" name="seguro_polizas[${index}][texto_aclaratorio]" value="${assetEscape(row.texto_aclaratorio ?? '')}">
                    <input type="hidden" name="seguro_polizas[${index}][asistencias]" value="${assetEscape(row.asistencias ?? '')}">
                    <label>Aseguradora<input name="seguro_polizas[${index}][aseguradora]" value="${assetEscape(row.aseguradora ?? '')}" placeholder="Nombre de la empresa"></label>
                    <label>Persona que atiende<input name="seguro_polizas[${index}][contacto_nombre]" value="${assetEscape(row.contacto_nombre ?? '')}" placeholder="Nombre del contacto"></label>
                    <label>Correo contacto<input name="seguro_polizas[${index}][contacto_correo]" type="email" value="${assetEscape(row.contacto_correo ?? '')}" placeholder="correo@aseguradora.com"></label>
                    <label>Celular contacto<input name="seguro_polizas[${index}][contacto_celular]" inputmode="tel" value="${assetEscape(row.contacto_celular ?? '')}" placeholder="300 000 0000"></label>
                    <label>Intermediario<input name="seguro_polizas[${index}][intermediario]" value="${assetEscape(row.intermediario ?? '')}" placeholder="Corredor / agencia si aplica"></label>
                    <label>Estado<select name="seguro_polizas[${index}][estado]">${assetPlaceholderOption(row.estado ?? '')}${stateOptions}</select></label>
                    <details class="asset-insurance-response-fields">
                        <summary>Datos de respuesta de la aseguradora</summary>
                        <div>
                            <label>Numero poliza/cotizacion<input name="seguro_polizas[${index}][numero_poliza]" value="${assetEscape(row.numero_poliza ?? '')}" placeholder="Numero de cotizacion o poliza"></label>
                            <label>Prima total<input name="seguro_polizas[${index}][prima_total]" inputmode="decimal" data-money-format value="${assetEscape(assetMoneyPlain(row.prima_total) || row.prima_total || '')}" placeholder="$0"></label>
                            <label>Valor asegurado total<input name="seguro_polizas[${index}][valor_asegurado_total]" inputmode="decimal" data-money-format value="${assetEscape(assetMoneyPlain(row.valor_asegurado_total) || row.valor_asegurado_total || '')}" placeholder="$0"></label>
                            <label>Alcance poliza<select name="seguro_polizas[${index}][alcance_poliza]" data-policy-allocation-field>${assetPlaceholderOption(row.alcance_poliza ?? '')}<option value="Individual" ${(row.alcance_poliza ?? '') === 'Individual' ? 'selected' : ''}>Individual de este activo</option><option value="Matriz/global" ${(row.alcance_poliza ?? '') === 'Matriz/global' ? 'selected' : ''}>Matriz/global cubre varias unidades</option></select></label>
                            <label>Edificio o grupo cubierto<input name="seguro_polizas[${index}][grupo_poliza]" value="${assetEscape(row.grupo_poliza ?? '')}" placeholder="Ej. Crespo 270 / poliza edificio"></label>
                            <label>Metodo distribucion<select name="seguro_polizas[${index}][metodo_distribucion]" data-policy-allocation-field>${assetPlaceholderOption(row.metodo_distribucion ?? '')}<option value="Individual" ${(row.metodo_distribucion ?? '') === 'Individual' ? 'selected' : ''}>Individual</option><option value="Coeficiente" ${(row.metodo_distribucion ?? '') === 'Coeficiente' ? 'selected' : ''}>Coeficiente de copropiedad</option><option value="Por unidades" ${(row.metodo_distribucion ?? '') === 'Por unidades' ? 'selected' : ''}>Por numero de unidades</option><option value="Manual" ${(row.metodo_distribucion ?? '') === 'Manual' ? 'selected' : ''}>Manual documentado</option></select></label>
                            <label>Coeficiente %<input name="seguro_polizas[${index}][coeficiente_participacion]" data-policy-allocation-field inputmode="decimal" value="${assetEscape(row.coeficiente_participacion ?? '')}" placeholder="3.5%"></label>
                            <label>Unidades cubiertas<input name="seguro_polizas[${index}][numero_unidades_cubiertas]" data-policy-allocation-field inputmode="numeric" value="${assetEscape(row.numero_unidades_cubiertas ?? '')}" placeholder="5"></label>
                            <label>Prima asignada a este activo<input name="seguro_polizas[${index}][prima_asignada]" data-policy-allocation-field data-money-format inputmode="decimal" value="${assetEscape(assetMoneyPlain(row.prima_asignada) || row.prima_asignada || '')}" placeholder="$0"></label>
                            <label>Observacion distribucion<input name="seguro_polizas[${index}][observaciones_distribucion]" value="${assetEscape(row.observaciones_distribucion ?? '')}" placeholder="Coeficiente, area, unidades o criterio usado"></label>
                            <label>Deducible general<input name="seguro_polizas[${index}][deducible_general]" value="${assetEscape(row.deducible_general ?? '')}" placeholder="% o valor"></label>
                            <label>Inicio vigencia<input name="seguro_polizas[${index}][fecha_inicio]" type="date" value="${assetEscape(row.fecha_inicio ?? '')}"></label>
                            <label>Fin vigencia<input name="seguro_polizas[${index}][fecha_fin]" type="date" value="${assetEscape(row.fecha_fin ?? '')}"></label>
                            <label>Renovacion<input name="seguro_polizas[${index}][fecha_renovacion]" type="date" value="${assetEscape(row.fecha_renovacion ?? '')}"></label>
                            <label>Forma pago<select name="seguro_polizas[${index}][forma_pago]">${assetPlaceholderOption(row.forma_pago ?? '')}${paymentOptions}</select></label>
                            <label>Clausulado<input name="seguro_polizas[${index}][clausulado]" value="${assetEscape(row.clausulado ?? '')}" placeholder="Clausulado / version"></label>
                            <label>Exclusiones relevantes<input name="seguro_polizas[${index}][exclusiones_relevantes]" value="${assetEscape(row.exclusiones_relevantes ?? '')}" placeholder="Exclusiones criticas"></label>
                            <label>Soporte<input name="seguro_polizas[${index}][soporte]" value="${assetEscape(row.soporte ?? '')}" placeholder="PDF, caratula, correo, anexo"></label>
                            <label>Observaciones<input name="seguro_polizas[${index}][observaciones]" value="${assetEscape(row.observaciones ?? '')}" placeholder="Notas de la oferta"></label>
                        </div>
                    </details>
                    <button type="button" class="asset-remove-insurance" aria-label="Quitar oferta" data-remove-asset-insurance-policy>&times;</button>
                </div>
            </details>
        `;
        }).join('')}
        </div>
        ${insuranceOfferComparisonMatrixHtml(form, normalizedRows.filter((row) => row.alcance_poliza !== 'Matriz/global'))}
    `;
    updatePolicyAllocationRows(form);
    renderInsuranceQuoteAnalysis(form);
    renderAssetInsuranceMatrixSummary(form);
};

const renderAssetInsuranceCoverageRows = (form, rows = []) => {
    const container = form.querySelector('[data-asset-insurance-coverage-rows]');
    const type = form.elements.tipo_activo.value;
    updateAssetInsuranceSections(form, type);
    if (!(container instanceof HTMLElement)) {
        return;
    }
    if (!assetSupportsInsurance(type)) {
        container.innerHTML = '';
        return;
    }
    const existingRows = rows.filter((row) => row && Object.values(row).some((value) => String(value ?? '').trim() !== ''));
    const selectedProducts = selectedInsuranceProductsFromForm(form);
    const selectedProductSet = new Set(selectedProducts.map((product) => normalizeInsuranceText(normalizeInsuranceRamo(product))));
    const visibleExistingRows = existingRows.filter((row) => !row.ramo || selectedProductSet.has(normalizeInsuranceText(normalizeInsuranceRamo(row.ramo))));
    const selected = new Set(visibleExistingRows
        .filter((row) => row.cobertura)
        .map((row) => coverageToggleValue(row.ramo || '', row.cobertura || '')));
    const activeProduct = activeInsuranceProduct(form, selectedProducts, 'assetCoverageActiveProduct');
    const visibleProducts = activeProduct ? [activeProduct] : selectedProducts;
    const selectedRiskProfile = joinInsuranceSelection(selectedProducts);
    const sourceRows = existingRows;
    container.innerHTML = `
        ${selectedInsuranceStripHtml(form)}
        <div class="asset-coverage-selector" data-asset-coverage-selector>
            <div>
                <strong>Coberturas requeridas y bienes/exposiciones</strong>
                <span>${selectedRiskProfile ? `Productos seleccionados: ${assetEscape(selectedRiskProfile)}. Marca los amparos y la base que realmente se va a cotizar o contratar.` : 'Primero selecciona uno o varios productos de seguro.'}</span>
            </div>
            ${insuranceProductTabsHtml(selectedProducts, activeProduct, 'coverage')}
            ${insuranceCoverageMatrixHtml(visibleProducts, selected, form)}
        </div>
        ${assetInsuranceAssetExposureContentHtml(form, activeProduct)}
        <div class="asset-coverage-hidden-rows" hidden>
            ${sourceRows.map((row, index) => `
                <div class="asset-insurance-coverage-row" data-asset-insurance-coverage-row>
                    ${assetInsuranceCoverageFields.map((field) => `<input type="hidden" name="seguro_coberturas[${index}][${field}]" value="${assetEscape(row[field] ?? '')}">`).join('')}
                </div>
            `).join('')}
        </div>
    `;
    syncCoveragePremiumRows(form);
};

const assetInsuranceAssetExposureContentHtml = (form, activeProduct) => {
    const activeCoverageRows = selectedInsuranceRequestRowsForProduct(form, activeProduct);
    const options = insuranceExposureOptionsForProduct(activeProduct, activeCoverageRows);
    const selectedKeys = selectedInsuranceOptionKeysFromForm(form, options);
    const isLiability = normalizeInsuranceText(activeProduct).includes('responsabilidad');
    return `
        <div class="asset-insurance-item-list">
            <div class="asset-insurance-item-list-head">
                <strong>${isLiability ? 'Exposiciones cubiertas por este ramo' : 'Bienes que alimentan este ramo'}</strong>
                <span>${isLiability ? 'Marca predios, actividad, terceros, empleados, contratistas u otra exposicion que justifique el limite.' : 'Marca solo las categorias que despues valoraras con fuente, fecha y valor de reposicion.'}</span>
            </div>
            <div class="asset-coverage-chips">
                ${options.map((item) => `
                    <label class="asset-coverage-chip ${selectedKeys.has(item.key) ? 'is-selected' : ''}" title="${assetEscape(insurableAssetTooltip(item.category))}">
                        <input type="checkbox" data-asset-insurable-toggle value="${assetEscape(item.key)}" ${selectedKeys.has(item.key) ? 'checked' : ''}>
                        <span>${assetEscape(item.label)}</span>
                    </label>
                `).join('') || '<p class="muted">Este ramo no requiere relacion de bienes; define limites por cobertura en valores asegurados.</p>'}
            </div>
        </div>
    `;
};

const renderAssetInsuranceAssetExposureRows = (form) => {
    const container = form.querySelector('[data-asset-insurance-assets-rows]');
    const type = form.elements.tipo_activo.value;
    updateAssetInsuranceSections(form, type);
    if (!(container instanceof HTMLElement)) {
        return;
    }
    if (!assetSupportsInsurance(type)) {
        container.innerHTML = '';
        return;
    }
    const selectedProducts = selectedInsuranceProductsFromForm(form);
    const activeProduct = activeInsuranceProduct(form, selectedProducts, 'assetAssetsActiveProduct');
    container.innerHTML = `
        ${selectedInsuranceStripHtml(form)}
        ${insuranceProductTabsHtml(selectedProducts, activeProduct, 'assets')}
        ${assetInsuranceAssetExposureContentHtml(form, activeProduct)}
    `;
};

const insuranceCoverageRowsForCurrentPolicy = (form) => {
    const currentRows = historyRowsForType(form, '[data-asset-insurance-coverage-row]', assetInsuranceCoverageFields)
        .filter((row) => row.ramo || row.cobertura)
        .filter((row) => [
            'contratado_actual',
            'valor_actual',
            'limite_evento_actual',
            'limite_vigencia_actual',
            'sublimite_actual',
            'deducible_actual',
            'deducible_porcentaje_actual',
            'deducible_minimo_actual',
            'indice_variable_actual',
            'tasa_actual',
            'prima_actual',
            'observaciones_actuales',
        ].some((field) => String(row[field] ?? '').trim() !== '' || assetNumber(row[field]) > 0));
    return currentRows.length > 0 ? mergeInsuranceCoverageRows(currentRows) : insuranceRequestRowsForSelectedProducts(form);
};

const insuranceCurrentPolicyOnlyTableHtml = (rows = []) => rows.length ? `
    <div class="asset-insurance-comparison-table asset-insurance-current-only-table" role="table" aria-label="Poliza actual por cobertura">
        <div class="asset-insurance-comparison-head" role="row">
            <span>Cobertura actual</span>
            <span>Contratada</span>
            <span>Bien / exposicion cubierta</span>
            <span>Valor actual</span>
            <span>Limites y sublimite</span>
            <span>Deducible</span>
            <span>Indice / tasa</span>
            <span>Prima</span>
            <span>Fuente / observacion</span>
        </div>
        ${rows.map((row) => `
            <div class="asset-insurance-comparison-row" role="row">
                <strong>${assetEscape(row.ramo || 'Ramo')}<small>${assetEscape(row.cobertura || 'Cobertura')}</small></strong>
                <label>
                    <span>Contratada</span>
                    <select data-insurance-request-field="contratado_actual" data-product="${assetEscape(row.ramo || '')}" data-coverage="${assetEscape(row.cobertura || '')}">
                        ${['', 'Si', 'No', 'Parcial', 'Por confirmar'].map((item) => `<option value="${assetEscape(item)}" ${item === (row.contratado_actual || '') ? 'selected' : ''}>${assetEscape(item || 'Seleccione')}</option>`).join('')}
                    </select>
                </label>
                <label>
                    <span>Bien / exposicion</span>
                    <input data-insurance-request-field="riesgo_cubierto" data-product="${assetEscape(row.ramo || '')}" data-coverage="${assetEscape(row.cobertura || '')}" value="${assetEscape(row.riesgo_cubierto || row.cobertura || '')}" placeholder="Bien, predio, tercero, equipo...">
                </label>
                <label>
                    <span>Valor actual</span>
                    <input data-money-format inputmode="decimal" data-insurance-request-field="valor_actual" data-product="${assetEscape(row.ramo || '')}" data-coverage="${assetEscape(row.cobertura || '')}" value="${assetEscape(assetMoneyPlain(row.valor_actual) || row.valor_actual || '')}" placeholder="$0">
                </label>
                <label>
                    <span>Limites y sublimite</span>
                    <input data-money-format inputmode="decimal" data-insurance-request-field="limite_evento_actual" data-product="${assetEscape(row.ramo || '')}" data-coverage="${assetEscape(row.cobertura || '')}" value="${assetEscape(assetMoneyPlain(row.limite_evento_actual) || row.limite_evento_actual || '')}" placeholder="Limite evento">
                    <input data-money-format inputmode="decimal" data-insurance-request-field="limite_vigencia_actual" data-product="${assetEscape(row.ramo || '')}" data-coverage="${assetEscape(row.cobertura || '')}" value="${assetEscape(assetMoneyPlain(row.limite_vigencia_actual) || row.limite_vigencia_actual || '')}" placeholder="Limite vigencia">
                    <input data-insurance-request-field="sublimite_actual" data-product="${assetEscape(row.ramo || '')}" data-coverage="${assetEscape(row.cobertura || '')}" value="${assetEscape(row.sublimite_actual || '')}" placeholder="Sublimite">
                </label>
                <label>
                    <span>Deducible</span>
                    <input data-insurance-request-field="deducible_porcentaje_actual" inputmode="decimal" data-product="${assetEscape(row.ramo || '')}" data-coverage="${assetEscape(row.cobertura || '')}" value="${assetEscape(row.deducible_porcentaje_actual || '')}" placeholder="% perdida">
                    <input data-insurance-request-field="deducible_minimo_actual" data-product="${assetEscape(row.ramo || '')}" data-coverage="${assetEscape(row.cobertura || '')}" value="${assetEscape(row.deducible_minimo_actual || '')}" placeholder="Minimo ej. 1 SMMLV">
                    <input data-insurance-request-field="deducible_actual" data-product="${assetEscape(row.ramo || '')}" data-coverage="${assetEscape(row.cobertura || '')}" value="${assetEscape(row.deducible_actual || '')}" placeholder="Texto caratula">
                </label>
                <label>
                    <span>Indice / tasa</span>
                    <input data-insurance-request-field="indice_variable_actual" data-product="${assetEscape(row.ramo || '')}" data-coverage="${assetEscape(row.cobertura || '')}" value="${assetEscape(row.indice_variable_actual || '')}" placeholder="IPC, fijo, pactado">
                    <input data-insurance-request-field="tasa_actual" inputmode="decimal" data-product="${assetEscape(row.ramo || '')}" data-coverage="${assetEscape(row.cobertura || '')}" value="${assetEscape(row.tasa_actual || '')}" placeholder="Tasa">
                </label>
                <label>
                    <span>Prima</span>
                    <input data-money-format inputmode="decimal" data-insurance-request-field="prima_actual" data-product="${assetEscape(row.ramo || '')}" data-coverage="${assetEscape(row.cobertura || '')}" value="${assetEscape(assetMoneyPlain(row.prima_actual) || row.prima_actual || '')}" placeholder="$0">
                </label>
                <label>
                    <span>Fuente / observacion</span>
                    <input data-insurance-request-field="observaciones_actuales" data-product="${assetEscape(row.ramo || '')}" data-coverage="${assetEscape(row.cobertura || '')}" value="${assetEscape(row.observaciones_actuales || '')}" placeholder="Caratula, clausulado, exclusion">
                </label>
            </div>
        `).join('')}
    </div>
` : '<p class="muted">Selecciona ramos y registra las coberturas de la poliza actual para construir la linea base.</p>';

const insuranceRenewalComparisonHtml = (form, activeProduct, selectedProducts = []) => {
    const coverages = insuranceCurrentComparisonCoverages(form);
    const normalizedActiveProduct = normalizeInsuranceText(normalizeInsuranceRamo(activeProduct));
    const visibleCoverages = coverages.filter((row) => normalizeInsuranceText(normalizeInsuranceRamo(row.ramo || '')) === normalizedActiveProduct);
    return `
        ${insurancePolicyPdfFieldMatrixHtml(activeProduct ? [activeProduct] : selectedProducts, 'request')}
        ${visibleCoverages.length ? `
            <div class="asset-insurance-comparison-table" role="table" aria-label="Comparativo actual contra solicitud de renovacion">
                <div class="asset-insurance-comparison-head" role="row">
                    <span>Cobertura</span>
                    <span>Contratado actual</span>
                    <span>Valor actual</span>
                    <span>Deducible actual</span>
                    <span>Limite / sublimite actual</span>
                    <span>Indice / tasa actual</span>
                    <span>Prima actual</span>
                    <span>Solicitar renovacion</span>
                    <span>Nuevo valor solicitado</span>
                    <span>Fuente / observacion</span>
                </div>
                ${visibleCoverages.map((row) => `
                    <div class="asset-insurance-comparison-row" role="row">
                        <strong>${assetEscape(row.ramo || 'Ramo')}<small>${assetEscape(row.cobertura || 'Cobertura')}</small>${row.valor_asegurado || row.renovacion_solicitada ? '' : '<em>Solo poliza actual: decidir si se mantiene o elimina</em>'}</strong>
                        <span>${assetEscape(row.contratado_actual || 'Por definir')}</span>
                        <span>${assetEscape(assetMoney(row.valor_actual || 0))}</span>
                        <span>${assetEscape([row.deducible_porcentaje_actual, row.deducible_minimo_actual, row.deducible_actual].filter(Boolean).join(' / ') || 'Por definir')}</span>
                        <span>${assetEscape([row.limite_evento_actual, row.limite_vigencia_actual, row.sublimite_actual].filter(Boolean).join(' / ') || 'Por definir')}</span>
                        <span>${assetEscape([row.indice_variable_actual, row.tasa_actual].filter(Boolean).join(' / ') || 'Por definir')}</span>
                        <span>${assetEscape(assetMoney(row.prima_actual || 0))}</span>
                        <label>
                            <span>Renovacion</span>
                            <input data-insurance-request-field="renovacion_solicitada" data-product="${assetEscape(row.ramo || '')}" data-coverage="${assetEscape(row.cobertura || '')}" value="${assetEscape(row.renovacion_solicitada || '')}" placeholder="Mantener, subir, incluir, excluir">
                        </label>
                        <label>
                            <span>Nuevo valor</span>
                            <input data-money-format inputmode="decimal" data-insurance-request-field="valor_asegurado" data-product="${assetEscape(row.ramo || '')}" data-coverage="${assetEscape(row.cobertura || '')}" value="${assetEscape(assetMoneyPlain(row.valor_asegurado) || row.valor_asegurado || '')}" placeholder="$0">
                        </label>
                        <label>
                            <span>Fuente / observacion</span>
                            <input data-insurance-request-field="fuente_valor_asegurado" data-product="${assetEscape(row.ramo || '')}" data-coverage="${assetEscape(row.cobertura || '')}" value="${assetEscape(row.fuente_valor_asegurado || '')}" placeholder="Avaluo, inventario, acta, criterio">
                            <input data-insurance-request-field="observaciones" data-product="${assetEscape(row.ramo || '')}" data-coverage="${assetEscape(row.cobertura || '')}" value="${assetEscape(row.observaciones || '')}" placeholder="Mejora, exclusion o condicion">
                        </label>
                    </div>
                `).join('')}
            </div>
        ` : '<p class="muted">Marca coberturas requeridas para que aqui aparezca la comparacion y los valores que iran a cotizacion.</p>'}
    `;
};

const renderAssetInsuranceCurrentPolicyRows = (form) => {
    const container = form.querySelector('[data-asset-insurance-current-policy-rows]');
    const type = form.elements.tipo_activo.value;
    updateAssetInsuranceSections(form, type);
    if (!(container instanceof HTMLElement)) {
        return;
    }
    if (!assetSupportsInsurance(type)) {
        container.innerHTML = '';
        return;
    }
    const selectedProducts = selectedInsuranceProductsFromForm(form);
    const policies = assetFormRows(form, '[data-asset-insurance-policy-row]', assetInsurancePolicyFields)
        .filter((row) => row.adoptada === 'Si' || row.estado === 'Vigente' || row.estado === 'Vencida' || row.numero_poliza || row.alcance_poliza === 'Matriz/global');
    const coverages = insuranceCoverageRowsForCurrentPolicy(form);
    const comparisonProducts = insuranceComparisonProducts(form, coverages);
    const activeProduct = activeInsuranceProduct(form, comparisonProducts, 'assetCurrentActiveProduct');
    const normalizedActiveProduct = normalizeInsuranceText(normalizeInsuranceRamo(activeProduct));
    const visibleCoverages = coverages.filter((row) => normalizeInsuranceText(normalizeInsuranceRamo(row.ramo || '')) === normalizedActiveProduct);
    const visiblePolicies = policies.filter((row) => {
        if (!activeProduct || !row.ramo) {
            return true;
        }
        return splitInsuranceSelection(row.ramo)
            .some((item) => normalizeInsuranceText(normalizeInsuranceRamo(item)) === normalizedActiveProduct);
    });
    container.innerHTML = `
        <div class="asset-insurance-guide">
            <strong>Linea base de la poliza actual</strong>
            <p>Registra aqui solo lo contratado hoy. La decision de mantener, mejorar o eliminar se hara en valores asegurados.</p>
        </div>
        ${insuranceProductTabsHtml(comparisonProducts, activeProduct, 'current')}
        ${insurancePolicyPdfFieldMatrixHtml(activeProduct ? [activeProduct] : selectedProducts)}
        ${visiblePolicies.length ? `
            <div class="asset-insurance-current-policy-list">
                ${visiblePolicies.map((row) => `
                    <article>
                        <strong>${assetEscape([row.aseguradora, row.numero_poliza].filter(Boolean).join(' / ') || 'Poliza registrada')}</strong>
                        <span>${assetEscape([row.ramo, row.estado, row.fecha_inicio && row.fecha_fin ? `${row.fecha_inicio} a ${row.fecha_fin}` : 'Sin vigencia'].filter(Boolean).join(' / '))}</span>
                        <b>${assetEscape(assetMoney(assetNumber(row.prima_total || row.prima_asignada)) || 'Prima pendiente')}</b>
                    </article>
                `).join('')}
            </div>
        ` : '<p class="muted">Registra una poliza actual o anterior para dejar evidencia de lo que se tiene contratado.</p>'}
        ${insuranceCurrentPolicyOnlyTableHtml(visibleCoverages)}
    `;
};

const insuredItemFields = [
    'ano',
    'numero_poliza',
    'ramo',
    'cobertura_asociada',
    'categoria_item',
    'item',
    'descripcion',
    'unidad',
    'cantidad',
    'ubicacion',
    'serial_referencia',
    'valor_compra',
    'fecha_adquisicion',
    'valor_reposicion_unitario',
    'valor_reposicion',
    'fuente_consulta',
    'fecha_consulta',
    'ano_adquisicion',
    'edad_anos',
    'vida_util_anos',
    'regla_demerito',
    'depreciacion_porcentaje',
    'depreciacion_valor',
    'valor_asegurable_sugerido',
    'incluye_terreno',
    'fecha_inicio',
    'fecha_fin',
    'fecha_renovacion',
    'observaciones',
];

const assetInsuranceEquipmentRows = (form) => [...form.querySelectorAll('[data-asset-insurance-equipment-row]')].map((row) => {
    const item = {};
    insuredItemFields.forEach((field) => {
        item[field] = row.querySelector(`[name$="[${field}]"]`)?.value ?? '';
    });
    return item;
});

const generatedInsuranceItemNames = new Set([
    'Construccion',
    'Vidrios',
    'Muebles y enseres',
    'Construccion asegurable',
    'Vidrios instalados',
    'Grupo homogeneo de muebles',
    'Grupo homogeneo de maquinaria',
    'Grupo homogeneo de equipos electronicos',
    'Inventario por grupo asegurable',
    'Obra, joya o coleccion soportada',
    'Limite maximo de efectivo',
]);

const isEmptyGeneratedInsuranceItem = (row = {}) => {
    const itemName = String(row.item || '').trim();
    const description = String(row.descripcion || '').trim();
    const category = String(row.categoria_item || '').trim();
    const hasMoney = [
        row.valor_compra,
        row.valor_reposicion_unitario,
        row.valor_reposicion,
        row.valor_asegurable_sugerido,
    ].some((value) => assetNumber(value) > 0);
    const hasSupport = [
        row.fuente_consulta,
        row.fecha_consulta,
        row.serial_referencia,
        row.observaciones,
    ].some((value) => String(value || '').trim() !== '');
    return !hasMoney
        && !hasSupport
        && !description
        && (generatedInsuranceItemNames.has(itemName) || (itemName === category && category !== ''));
};

const updateInsuredItemRow = (row) => {
    const quantityInput = row.querySelector('[name$="[cantidad]"]');
    const unitInput = row.querySelector('[name$="[valor_reposicion_unitario]"]');
    const totalInput = row.querySelector('[data-insured-item-total]');
    const depreciationPercentInput = row.querySelector('[name$="[depreciacion_porcentaje]"]');
    const depreciationValueInput = row.querySelector('[data-insured-item-depreciation]');
    const suggestedInput = row.querySelector('[data-insured-item-suggested]');
    if (!(totalInput instanceof HTMLInputElement)) {
        return;
    }

    const quantity = assetNumber(quantityInput?.value) || 0;
    const unitValue = assetNumber(unitInput?.value) || 0;
    let total = assetNumber(totalInput.value) || 0;
    if (quantity > 0 && unitValue > 0) {
        total = quantity * unitValue;
        totalInput.value = String(Math.round(total));
    }

    const depreciationPercent = assetNumber(depreciationPercentInput?.value) || 0;
    const depreciationValue = total > 0 ? total * Math.min(depreciationPercent, 100) / 100 : 0;
    if (depreciationValueInput instanceof HTMLInputElement) {
        depreciationValueInput.value = depreciationValue > 0 ? String(Math.round(depreciationValue)) : '';
    }
    if (suggestedInput instanceof HTMLInputElement) {
        const suggested = Math.max(total - depreciationValue, 0);
        suggestedInput.value = suggested > 0 ? String(Math.round(suggested)) : '';
    }
};

const updateInsuredItemRows = (container) => {
    container.querySelectorAll('[data-asset-insurance-equipment-row]').forEach(updateInsuredItemRow);
};

const insuredItemQuickCategories = [
    { category: 'Construccion', label: 'Construccion', unit: 'm2' },
    { category: 'Vidrios', label: 'Vidrios', unit: 'm2' },
    { category: 'Muebles y enseres', label: 'Muebles y enseres', unit: 'und' },
    { category: 'Maquinaria y equipo', label: 'Maquinaria/equipo', unit: 'und' },
    { category: 'Equipo electronico / corriente debil', label: 'Corriente debil', unit: 'und' },
    { category: 'Mercancias / inventario', label: 'Mercancias', unit: 'lote' },
    { category: 'Obras de arte', label: 'Arte/joyas', unit: 'und' },
    { category: 'Dinero en efectivo', label: 'Dinero', unit: 'limite' },
];

const insuredItemQuickButtonsHtml = (activeCategory = '') => `
    <div class="asset-insurance-quick-items" aria-label="Categorias de bienes asegurables">
        ${insuredItemQuickCategories.map((item) => `
            <button type="button" data-filter-asset-insurance-equipment-category="${assetEscape(item.category)}" aria-pressed="${activeCategory === item.category ? 'true' : 'false'}">
                ${assetEscape(item.label)}
            </button>
        `).join('')}
    </div>
`;

const buildInsuranceEquipmentRow = (form, category = '', product = '') => {
    const template = insuredItemQuickCategories.find((item) => item.category === category);
    return {
        ano: String(new Date().getFullYear()),
        ramo: product || activeInsuranceProduct(form, selectedInsuranceProductsFromForm(form), 'assetValueActiveProduct') || '',
        categoria_item: template?.category || category || '',
        item: '',
        descripcion: '',
        unidad: template?.unit || (category === 'Construccion' || category === 'Vidrios' ? 'm2' : category ? 'und' : ''),
        cantidad: '1',
        incluye_terreno: category === 'Construccion' ? 'No' : '',
        observaciones: '',
    };
};

const insuranceRequestRowsForProduct = (form, product, existingRows = []) => {
    const normalizedProduct = normalizeInsuranceText(normalizeInsuranceRamo(product || ''));
    const existingForProduct = existingRows.filter((item) => {
        const rowProduct = item.ramo || '';
        return normalizeInsuranceText(normalizeInsuranceRamo(rowProduct)) === normalizedProduct || (!product && !rowProduct);
    });
    if (existingForProduct.length > 0) {
        return existingForProduct;
    }
    const options = parseAssetJson(form, 'assetOptions');
    const allCoverageNames = [...new Set([
        ...(options.tipo_cobertura_seguro || []),
        ...insuranceAcademyData(form).coverages.map((row) => row.Cobertura).filter(Boolean),
    ])];
    return coverageOptionsForPolicy(product, allCoverageNames, form).map((coverage) => {
        const suggestion = suggestCoverageValue(form, coverage);
        return {
            ano: String(new Date().getFullYear()),
            ramo: product,
            cobertura: coverage,
            riesgo_cubierto: coverage,
            valor_asegurado: suggestion.value > 0 ? String(Math.round(suggestion.value)) : '',
            fuente_valor_asegurado: suggestion.source || '',
            observaciones: suggestion.source ? `Valor sugerido segun relacion de bienes a reposicion. Fuente: ${suggestion.source}` : 'Definir valor o limite con soporte antes de cotizar.',
        };
    });
};

const mergeInsuranceCoverageRows = (rows = []) => {
    const merged = new Map();
    rows.forEach((row) => {
        const key = normalizedCoverageRowKey(row);
        if (key === '::') {
            return;
        }
        const previous = merged.get(key) || {};
        const next = { ...previous };
        assetInsuranceCoverageFields.forEach((field) => {
            const previousValue = previous[field] ?? '';
            const rowValue = row[field] ?? '';
            if (['valor_asegurado', 'limite_evento', 'sublimite', 'prima', 'tasa', 'deducible'].includes(field)) {
                const numericRow = assetNumber(rowValue);
                if (numericRow > 0 || (!previousValue && rowValue)) {
                    next[field] = rowValue;
                }
                return;
            }
            if (rowValue !== '') {
                next[field] = rowValue;
            } else if (previousValue !== '') {
                next[field] = previousValue;
            }
        });
        merged.set(key, next);
    });
    return [...merged.values()];
};

const insuranceRequestRowsForSelectedProducts = (form) => {
    const selectedProducts = selectedInsuranceProductsFromForm(form);
    const existingRows = historyRowsForType(form, '[data-asset-insurance-coverage-row]', assetInsuranceCoverageFields)
        .filter((row) => row.ramo || row.cobertura || row.valor_asegurado || row.fuente_valor_asegurado || row.observaciones);
    return mergeInsuranceCoverageRows(selectedProducts.flatMap((product) => insuranceRequestRowsForProduct(form, product, existingRows)));
};

const selectedInsuranceRequestRowsForProduct = (form, product) => {
    const normalizedProduct = normalizeInsuranceText(normalizeInsuranceRamo(product || ''));
    const rows = historyRowsForType(form, '[data-asset-insurance-coverage-row]', assetInsuranceCoverageFields)
        .filter((row) => row.ramo || row.cobertura || row.valor_asegurado || row.fuente_valor_asegurado || row.observaciones);
    return mergeInsuranceCoverageRows(rows)
        .filter((row) => {
            const rowProduct = row.ramo || '';
            return normalizeInsuranceText(normalizeInsuranceRamo(rowProduct)) === normalizedProduct || (!product && !rowProduct);
        })
        .map((row) => {
            const suggestion = suggestCoverageValue(form, row.cobertura || '');
            return {
                ...row,
                valor_asegurado: row.valor_asegurado || (suggestion.value > 0 ? String(Math.round(suggestion.value)) : ''),
                fuente_valor_asegurado: row.fuente_valor_asegurado || suggestion.source || '',
                observaciones: row.observaciones || (suggestion.source ? `Valor sugerido segun relacion de bienes a reposicion. Fuente: ${suggestion.source}` : 'Definir valor o limite con soporte antes de cotizar.'),
            };
        });
};

const insuranceItemTotalsForProduct = (form, product) => assetInsuranceEquipmentRows(form)
    .filter((item) => (item.ramo || '') === product || (!product && !item.ramo))
    .reduce((carry, item) => {
        const category = item.categoria_item || 'Sin categoria';
        carry[category] = (carry[category] || 0) + (assetNumber(item.valor_asegurable_sugerido) || assetNumber(item.valor_reposicion) || 0);
        return carry;
    }, {});

const updateInsuranceItemTotalsDisplay = (form) => {
    const selectedProducts = selectedInsuranceProductsFromForm(form);
    const activeProduct = activeInsuranceProduct(form, selectedProducts, 'assetValueActiveProduct');
    const totals = insuranceItemTotalsForProduct(form, activeProduct);
    const grandTotal = Object.values(totals).reduce((sum, value) => sum + value, 0);
    const totalTarget = form.querySelector('[data-insurance-item-total-all]');
    const chipsTarget = form.querySelector('[data-insurance-item-totals]');
    if (totalTarget) {
        totalTarget.textContent = assetMoney(grandTotal);
    }
    if (chipsTarget) {
        chipsTarget.innerHTML = Object.entries(totals).map(([category, total]) => `<span><strong>${assetEscape(category)}</strong>${assetEscape(assetMoney(total))}</span>`).join('');
    }
};

const insuranceCalculationGuideHtml = (product, coverageRows = []) => {
    if (!product) {
        return '';
    }
    const productGuide = insuranceCalculationGuideForProduct(product);
    const coverageNames = coverageRows.map((row) => row.cobertura).filter(Boolean);
    return `
        <div class="asset-insurance-calculation-guide">
            <div class="asset-insurance-calculation-summary">
                <strong>${assetEscape(productGuide.method)}</strong>
                <span>${assetEscape(productGuide.tool)}</span>
            </div>
            <div class="asset-insurance-calculation-table" role="table" aria-label="Metodo para calcular valor asegurable">
                <div role="row">
                    <strong>Ramo</strong>
                    <span>${assetEscape(product)}</span>
                </div>
                <div role="row">
                    <strong>Formula base</strong>
                    <span>${assetEscape(productGuide.formula)}</span>
                </div>
                <div role="row">
                    <strong>Fuente requerida</strong>
                    <span>${assetEscape(productGuide.source)}</span>
                </div>
                <div role="row">
                    <strong>Alerta</strong>
                    <span>${assetEscape(productGuide.warning)}</span>
                </div>
                <div role="row">
                    <strong>Coberturas</strong>
                    <span>${coverageNames.length > 0 ? assetEscape(coverageNames.join(' / ')) : 'Primero marca coberturas requeridas en la pestaña anterior.'}</span>
                </div>
            </div>
        </div>
    `;
};

const insuranceSpecialCalculatorHtml = (form, coverageRows = []) => {
    if (!coverageRows.some((row) => lossOfRentCoverageText(row.cobertura || ''))) {
        return '';
    }
    const basis = lossOfRentBasisForForm(form);
    return `
        <div class="asset-insurance-special-calculator">
            <div class="asset-insurance-special-head">
                <strong>Calculo de perdida de arrendamiento</strong>
                <span>Base sugerida: canon mensual por unidad x meses cubiertos x participacion.</span>
                <output>${assetEscape(assetMoney(basis.total))}</output>
            </div>
            <div class="asset-insurance-loss-rent-table" role="table" aria-label="Calculo de perdida de arrendamiento">
                <div class="asset-insurance-loss-rent-head" role="row">
                    <span>Unidad</span>
                    <span>Canon mensual</span>
                    <span>Participacion</span>
                    <span>Meses</span>
                    <span>Valor asegurable</span>
                    <span>Fuente</span>
                </div>
                ${basis.rows.map((row) => `
                    <div role="row">
                        <span>${assetEscape(row.unit)}</span>
                        <span>${assetEscape(assetMoney(row.canon))}</span>
                        <span>${assetEscape(assetPercent(row.share))}</span>
                        <span>${assetEscape(String(row.months))}</span>
                        <strong>${assetEscape(assetMoney(row.value))}</strong>
                        <span>${assetEscape(row.source)}</span>
                    </div>
                `).join('')}
            </div>
        </div>
    `;
};

const renderAssetInsuranceEquipmentRows = (form, rows = []) => {
    const container = form.querySelector('[data-asset-insurance-equipment-rows]');
    const type = form.elements.tipo_activo.value;
    updateAssetInsuranceSections(form, type);
    if (!(container instanceof HTMLElement)) {
        return;
    }
    if (!assetSupportsInsurance(type)) {
        container.innerHTML = '';
        return;
    }
    const sourceRows = (rows.length > 0 ? rows : []).filter((row) => !isEmptyGeneratedInsuranceItem(row));
    const selectedProducts = selectedInsuranceProductsFromForm(form);
    const activeProduct = activeInsuranceProduct(form, selectedProducts, 'assetValueActiveProduct');
    const activeCoverageRows = selectedInsuranceRequestRowsForProduct(form, activeProduct);
    const productSourceRows = sourceRows.filter((item) => (item.ramo || '') === activeProduct || (!activeProduct && !item.ramo));
    const productCategories = [...new Set(productSourceRows.map((item) => item.categoria_item).filter(Boolean))];
    const requestedCategory = form.dataset.assetInsuranceItemCategory || '';
    const knownCategories = insuredItemQuickCategories.map((item) => item.category);
    const activeCategory = requestedCategory && (knownCategories.includes(requestedCategory) || productCategories.includes(requestedCategory))
        ? form.dataset.assetInsuranceItemCategory
        : productCategories[0] || insuredItemQuickCategories[0]?.category || '';
    form.dataset.assetInsuranceItemCategory = activeCategory;
    const activeSourceRows = productSourceRows.filter((item) => (item.categoria_item || '') === activeCategory);
    const inactiveSourceRows = sourceRows.filter((item) => !activeSourceRows.includes(item));
    const activeTotalsByCategory = productSourceRows.reduce((carry, item) => {
        const category = item.categoria_item || 'Sin categoria';
        carry[category] = (carry[category] || 0) + (assetNumber(item.valor_asegurable_sugerido) || assetNumber(item.valor_reposicion) || 0);
        return carry;
    }, {});
    const activeGrandTotal = Object.values(activeTotalsByCategory).reduce((sum, value) => sum + value, 0);
    container.innerHTML = `
        ${selectedInsuranceStripHtml(form)}
        ${insuranceProductTabsHtml(selectedProducts, activeProduct, 'values')}
        ${insuranceCalculationGuideHtml(activeProduct, activeCoverageRows)}
        ${insuranceSpecialCalculatorHtml(form, activeCoverageRows)}
        ${insuranceRenewalComparisonHtml(form, activeProduct, selectedProducts)}
        <div class="asset-insurance-item-list">
            <div class="asset-insurance-item-list-head">
                <strong>Relacion de bienes o valores base</strong>
                <span>Registra grupos homogeneos cuando aplique: cantidad x valor de reposicion unitario. Detalla uno a uno solo los bienes singulares o de alto valor.</span>
                <output data-insurance-item-total-all>${assetEscape(assetMoney(activeGrandTotal))}</output>
            </div>
            ${insuredItemQuickButtonsHtml(activeCategory)}
            ${Object.keys(activeTotalsByCategory).length > 0 ? `
                <div class="asset-insurance-item-totals" data-insurance-item-totals>
                    ${Object.entries(activeTotalsByCategory).map(([category, total]) => `<span><strong>${assetEscape(category)}</strong>${assetEscape(assetMoney(total))}</span>`).join('')}
                </div>
            ` : '<div class="asset-insurance-item-totals" data-insurance-item-totals></div>'}
            <div class="asset-insurance-items-table" role="table" aria-label="Relacion de bienes asegurables">
                <div class="asset-insurance-items-head" role="row">
                    <span>Categoria</span>
                    <span>Grupo / descripcion</span>
                    <span>Unidad</span>
                    <span>Cant</span>
                    <span>Serial / ref.</span>
                    <span>Fecha compra</span>
                    <span>Vr compra</span>
                    <span>Vr reposicion und.</span>
                    <span>Vr reposicion total</span>
                    <span>Fuente / fecha</span>
                    <span>Vr asegurable</span>
                    <span>Terreno</span>
                    <span>Observacion</span>
                    <span></span>
                </div>
                ${activeSourceRows.map((row, index) => `
                    <div class="asset-insurance-equipment-row asset-insurance-item-row" data-asset-insurance-equipment-row role="row">
                        <label title="${assetEscape(insurableAssetTooltip(row.categoria_item || ''))}"><span>Categoria</span><input name="seguro_equipos[${index}][categoria_item]" value="${assetEscape(row.categoria_item ?? '')}" placeholder="Construccion, maquinaria..." title="${assetEscape(insurableAssetTooltip(row.categoria_item || ''))}"></label>
                        <label><span>Grupo</span><input name="seguro_equipos[${index}][item]" value="${assetEscape(row.item ?? '')}" placeholder="A/A 3/4 HP, escritorios 60x120..."><input name="seguro_equipos[${index}][descripcion]" value="${assetEscape(row.descripcion ?? '')}" placeholder="Marca, capacidad, medida, referencia o criterio de agrupacion"></label>
                        <label><span>Unidad</span><input name="seguro_equipos[${index}][unidad]" value="${assetEscape(row.unidad ?? '')}" placeholder="und, m2, ml, lote"></label>
                        <label><span>Cant</span><input name="seguro_equipos[${index}][cantidad]" data-insured-item-calc inputmode="decimal" value="${assetEscape(row.cantidad ?? '')}" placeholder="1"></label>
                        <label><span>Serial</span><input name="seguro_equipos[${index}][serial_referencia]" value="${assetEscape(row.serial_referencia ?? '')}" placeholder="Serial, placa"></label>
                        <label><span>Compra</span><input name="seguro_equipos[${index}][fecha_adquisicion]" type="date" value="${assetEscape(row.fecha_adquisicion ?? '')}"></label>
                        <label><span>Vr compra</span><input name="seguro_equipos[${index}][valor_compra]" inputmode="decimal" value="${assetEscape(row.valor_compra ?? '')}" placeholder="$0"></label>
                        <label><span>Reposicion und.</span><input name="seguro_equipos[${index}][valor_reposicion_unitario]" data-insured-item-calc inputmode="decimal" value="${assetEscape(row.valor_reposicion_unitario ?? '')}" placeholder="$0"></label>
                        <label><span>Reposicion total</span><input name="seguro_equipos[${index}][valor_reposicion]" data-insured-item-total inputmode="decimal" value="${assetEscape(row.valor_reposicion ?? '')}" placeholder="$0"></label>
                        <label><span>Fuente</span><input name="seguro_equipos[${index}][fuente_consulta]" value="${assetEscape(row.fuente_consulta ?? '')}" placeholder="Cotizacion, factura, avaluo"><input name="seguro_equipos[${index}][fecha_consulta]" type="date" value="${assetEscape(row.fecha_consulta ?? '')}"></label>
                        <label><span>Asegurable</span><input name="seguro_equipos[${index}][valor_asegurable_sugerido]" data-insured-item-suggested inputmode="decimal" value="${assetEscape(row.valor_asegurable_sugerido ?? '')}" placeholder="$0"></label>
                        <label><span>Terreno</span><select name="seguro_equipos[${index}][incluye_terreno]">${assetPlaceholderOption(row.incluye_terreno ?? '')}<option value="No" ${(row.incluye_terreno ?? '') === 'No' ? 'selected' : ''}>No</option><option value="Si" ${(row.incluye_terreno ?? '') === 'Si' ? 'selected' : ''}>Si</option></select></label>
                        <label><span>Observacion</span><input name="seguro_equipos[${index}][observaciones]" value="${assetEscape(row.observaciones ?? '')}" placeholder="Soporte, exclusion, detalle"></label>
                        <button type="button" class="asset-remove-insurance" aria-label="Quitar item asegurable" data-remove-asset-insurance-equipment>&times;</button>
                        <input type="hidden" name="seguro_equipos[${index}][ano]" value="${assetEscape(row.ano ?? String(new Date().getFullYear()))}">
                        <input type="hidden" name="seguro_equipos[${index}][numero_poliza]" value="${assetEscape(row.numero_poliza ?? '')}">
                        <input type="hidden" name="seguro_equipos[${index}][ramo]" value="${assetEscape(row.ramo ?? activeProduct ?? '')}">
                        <input type="hidden" name="seguro_equipos[${index}][cobertura_asociada]" value="${assetEscape(row.cobertura_asociada ?? '')}">
                        <input type="hidden" name="seguro_equipos[${index}][ubicacion]" value="${assetEscape(row.ubicacion ?? '')}">
                        <input type="hidden" name="seguro_equipos[${index}][ano_adquisicion]" value="${assetEscape(row.ano_adquisicion ?? '')}">
                        <input type="hidden" name="seguro_equipos[${index}][edad_anos]" value="${assetEscape(row.edad_anos ?? '')}">
                        <input type="hidden" name="seguro_equipos[${index}][vida_util_anos]" value="${assetEscape(row.vida_util_anos ?? '')}">
                        <input type="hidden" name="seguro_equipos[${index}][regla_demerito]" value="${assetEscape(row.regla_demerito ?? '')}">
                        <input type="hidden" name="seguro_equipos[${index}][depreciacion_porcentaje]" data-insured-item-calc value="${assetEscape(row.depreciacion_porcentaje ?? '')}">
                        <input type="hidden" name="seguro_equipos[${index}][depreciacion_valor]" data-insured-item-depreciation value="${assetEscape(row.depreciacion_valor ?? '')}">
                        <input type="hidden" name="seguro_equipos[${index}][fecha_inicio]" value="${assetEscape(row.fecha_inicio ?? '')}">
                        <input type="hidden" name="seguro_equipos[${index}][fecha_fin]" value="${assetEscape(row.fecha_fin ?? '')}">
                        <input type="hidden" name="seguro_equipos[${index}][fecha_renovacion]" value="${assetEscape(row.fecha_renovacion ?? '')}">
                    </div>
                `).join('') || '<p class="muted">Agrega una fila para registrar activos reales de esta categoria. No se crean filas automaticas.</p>'}
            </div>
            ${inactiveSourceRows.map((row, index) => `
                <div data-asset-insurance-equipment-row hidden>
                    ${insuredItemFields.map((field) => `<input type="hidden" name="seguro_equipos[${activeSourceRows.length + index}][${field}]" value="${assetEscape(row[field] ?? '')}">`).join('')}
                </div>
            `).join('')}
        </div>
    `;
    updateInsuredItemRows(container);
    updateInsuranceDerivedSummary(form);
    refreshCoverageSuggestedValues(form);
};

const renderAssetInsuranceMovementRows = (form, rows = []) => {
    const container = form.querySelector('[data-asset-insurance-movement-rows]');
    const options = parseAssetJson(form, 'assetOptions');
    const type = form.elements.tipo_activo.value;
    updateAssetInsuranceSections(form, type);
    if (!(container instanceof HTMLElement)) {
        return;
    }
    if (!assetSupportsInsurance(type)) {
        container.innerHTML = '';
        return;
    }
    const movementOptions = (options.tipo_movimiento_seguro || []).map((item) => `<option value="${assetEscape(item)}">${assetEscape(item)}</option>`).join('');
    const stateOptions = (options.estado_reporte_aseguradora || []).map((item) => `<option value="${assetEscape(item)}">${assetEscape(item)}</option>`).join('');
    const sourceRows = rows.length > 0 ? rows : [{}];
    container.innerHTML = sourceRows.map((row, index) => `
        <div class="asset-insurance-movement-row" data-asset-insurance-movement-row>
            <label>Ano<input name="seguro_movimientos[${index}][ano]" inputmode="numeric" value="${assetEscape(row.ano ?? '')}" placeholder="2026"></label>
            <label>Fecha movimiento<input name="seguro_movimientos[${index}][fecha]" type="date" value="${assetEscape(row.fecha ?? '')}"></label>
            <label>Tipo movimiento<select name="seguro_movimientos[${index}][tipo_movimiento]">${assetPlaceholderOption(row.tipo_movimiento ?? '')}${movementOptions.replace(`value="${assetEscape(row.tipo_movimiento ?? '')}"`, `value="${assetEscape(row.tipo_movimiento ?? '')}" selected`)}</select></label>
            <label>Numero poliza<input name="seguro_movimientos[${index}][numero_poliza]" value="${assetEscape(row.numero_poliza ?? '')}" placeholder="Poliza o cotizacion afectada"></label>
            <label>Producto<input name="seguro_movimientos[${index}][ramo]" value="${assetEscape(row.ramo ?? '')}" placeholder="Incendio, RM, corriente debil..."></label>
            <label>Cobertura<input name="seguro_movimientos[${index}][cobertura]" value="${assetEscape(row.cobertura ?? '')}" placeholder="Amparo afectado"></label>
            <label>Item / cambio<input name="seguro_movimientos[${index}][item]" value="${assetEscape(row.item ?? '')}" placeholder="UPS, equipo retirado, valor ajustado..."></label>
            <label>Valor variacion<input name="seguro_movimientos[${index}][valor_variacion]" inputmode="decimal" value="${assetEscape(row.valor_variacion ?? '')}" placeholder="$0"></label>
            <label>Estado reporte<select name="seguro_movimientos[${index}][estado_reporte]">${assetPlaceholderOption(row.estado_reporte ?? '')}${stateOptions.replace(`value="${assetEscape(row.estado_reporte ?? '')}"`, `value="${assetEscape(row.estado_reporte ?? '')}" selected`)}</select></label>
            <label>Fecha reporte<input name="seguro_movimientos[${index}][fecha_reporte_aseguradora]" type="date" value="${assetEscape(row.fecha_reporte_aseguradora ?? '')}"></label>
            <label>Soporte<input name="seguro_movimientos[${index}][soporte]" value="${assetEscape(row.soporte ?? '')}" placeholder="Endoso, correo, radicado, factura"></label>
            <label>Observaciones<input name="seguro_movimientos[${index}][observaciones]" value="${assetEscape(row.observaciones ?? '')}" placeholder="Efecto en valores, deducible, pendiente..."></label>
            <button type="button" class="asset-remove-insurance" aria-label="Quitar movimiento" data-remove-asset-insurance-movement>&times;</button>
        </div>
    `).join('');
};

const renderAssetInsuranceHistory = (form) => {
    const target = form.querySelector('[data-asset-insurance-history-summary]');
    if (!(target instanceof HTMLElement)) {
        return;
    }
    const policies = assetFormRows(form, '[data-asset-insurance-policy-row]', ['ano', 'ramo', 'aseguradora', 'numero_poliza', 'prima_total', 'prima_asignada', 'alcance_poliza', 'metodo_distribucion', 'valor_asegurado_total', 'estado', 'adoptada']);
    const coverages = assetFormRows(form, '[data-asset-insurance-coverage-row]', ['ano', 'cobertura', 'valor_asegurado', 'prima', 'deducible']);
    const equipment = assetInsuranceEquipmentRows(form);
    const movements = assetFormRows(form, '[data-asset-insurance-movement-row]', ['ano', 'tipo_movimiento', 'valor_variacion', 'estado_reporte']);
    const years = [...new Set([...policies, ...coverages, ...equipment, ...movements].map((row) => Number(row.ano)).filter(Boolean))].sort((a, b) => a - b);
    if (years.length === 0) {
        target.innerHTML = '<p class="muted">Aun no hay registros anuales de seguros.</p>';
        return;
    }
    target.innerHTML = years.map((year) => {
        const yearPolicies = policies.filter((row) => Number(row.ano) === year);
        const adopted = yearPolicies.find((row) => row.adoptada === 'Si') || yearPolicies.find((row) => row.estado === 'Vigente') || {};
        const yearCoverages = coverages.filter((row) => Number(row.ano) === year);
        const yearEquipment = equipment.filter((row) => Number(row.ano) === year);
        const yearMovements = movements.filter((row) => Number(row.ano) === year);
        const insuredValue = yearCoverages.reduce((sum, row) => sum + assetNumber(row.valor_asegurado), 0) || yearEquipment.reduce((sum, row) => sum + assetNumber(row.valor_asegurable_sugerido || row.valor_reposicion), 0);
        const premium = yearCoverages.reduce((sum, row) => sum + assetNumber(row.prima), 0) || yearPolicies.reduce((sum, row) => sum + (assetNumber(row.prima_asignada) || assetNumber(row.prima_total)), 0);
        return `
            <article class="asset-insurance-year">
                <strong>${year}</strong>
                <span>Aseguradora: ${assetEscape(adopted.aseguradora || 'Por definir')}</span>
                <span>Valor asegurado: ${assetMoney(insuredValue)}</span>
                <span>Costo asignado: ${assetMoney(premium)}</span>
                <span>${assetEscape(adopted.alcance_poliza || 'Poliza individual')}</span>
                <span>Coberturas: ${assetEscape(yearCoverages.map((row) => row.cobertura).filter(Boolean).join(', ') || 'Pendientes')}</span>
                <span>Movimientos: ${yearMovements.length}</span>
            </article>
        `;
    }).join('');
};

const renderAssetCurrentPolicy = (form) => {
    const target = form.querySelector('[data-asset-insurance-current-summary]');
    if (!(target instanceof HTMLElement)) {
        return;
    }
    const policies = assetFormRows(form, '[data-asset-insurance-policy-row]', ['ano', 'ramo', 'aseguradora', 'numero_poliza', 'fecha_inicio', 'fecha_fin', 'fecha_renovacion', 'prima_total', 'prima_asignada', 'alcance_poliza', 'grupo_poliza', 'metodo_distribucion', 'coeficiente_participacion', 'numero_unidades_cubiertas', 'valor_asegurado_total', 'deducible_general', 'estado', 'adoptada', 'criterio_adopcion']);
    const current = policies.find((row) => row.adoptada === 'Si') || policies.find((row) => row.estado === 'Vigente');
    if (!current) {
        target.innerHTML = `
            <div class="asset-insurance-guide">
                <strong>Sin poliza vigente seleccionada</strong>
                <p>Este bloque no se diligencia directamente. Primero registra una oferta en "Cotizaciones y decision", una poliza anterior o una poliza matriz/global; luego presiona "Tomar esta poliza" cuando corresponda.</p>
            </div>
        `;
        return;
    }
    const coverages = assetFormRows(form, '[data-asset-insurance-coverage-row]', ['numero_poliza', 'cobertura', 'valor_asegurado', 'prima', 'deducible', 'deducible_porcentaje', 'deducible_minimo', 'deducible_actual', 'deducible_porcentaje_actual', 'deducible_minimo_actual'])
        .filter((row) => !current.numero_poliza || !row.numero_poliza || row.numero_poliza === current.numero_poliza);
    const coverageDeductible = (row) => [row.deducible_porcentaje, row.deducible_minimo, row.deducible, row.deducible_porcentaje_actual, row.deducible_minimo_actual, row.deducible_actual].filter(Boolean).join(' / ') || 'Por definir';
    target.innerHTML = `
        <article class="asset-insurance-year">
            <strong>${assetEscape(current.alcance_poliza === 'Matriz/global' ? 'Poliza matriz/global vigente' : 'Poliza vigente')}</strong>
            <span>Ano: ${assetEscape(current.ano || 'Sin ano')}</span>
            <span>Aseguradora: ${assetEscape(current.aseguradora || 'Por definir')}</span>
            <span>Poliza: ${assetEscape(current.numero_poliza || 'Sin numero')}</span>
            <span>Productos: ${assetEscape(current.ramo || 'Por definir')}</span>
            <span>Valor asegurado total: ${assetMoney(assetNumber(current.valor_asegurado_total))}</span>
            <span>Prima total: ${assetMoney(assetNumber(current.prima_total))}</span>
            <span>Costo asignado: ${assetMoney(assetNumber(current.prima_asignada || current.prima_total))}</span>
            <span>${assetEscape([current.alcance_poliza, current.grupo_poliza, current.metodo_distribucion].filter(Boolean).join(' / ') || 'Poliza individual')}</span>
            <span>Renovacion: ${assetEscape(assetDate(current.fecha_renovacion || current.fecha_fin) || 'Sin fecha')}</span>
        </article>
        <div class="asset-insurance-current-coverages">
            ${coverages.map((row) => `<span>${assetEscape(row.cobertura || 'Cobertura')} / ${assetMoney(assetNumber(row.valor_asegurado))} / ded. ${assetEscape(coverageDeductible(row))}</span>`).join('') || '<span>Coberturas pendientes de asociar.</span>'}
        </div>
        <p class="muted">${assetEscape(current.criterio_adopcion || 'Decision pendiente de documentar.')}</p>
    `;
};

const renderAssetParticipationRows = (form, rows = []) => {
    const container = form.querySelector('[data-asset-participation-rows]');
    const options = parseAssetJson(form, 'assetOptions');
    const participants = parseAssetJson(form, 'assetParticipantOptions');
    if (!(container instanceof HTMLElement)) {
        return;
    }
    const sourceRows = rows.length > 0 ? rows : [{}];
    container.innerHTML = sourceRows.map((row, index) => {
        const tipo = row.tipo_participante ?? '';
        const participante = row.participante ?? '';
        const calidad = row.calidad ?? '';
        const porcentaje = row.porcentaje ?? '';
        const soporte = row.soporte_observaciones ?? '';
        const typeOptions = (options.tipo_participante || []).map((item) => `<option value="${assetEscape(item)}" ${item === tipo ? 'selected' : ''}>${assetEscape(item)}</option>`).join('');
        const qualityOptions = (options.calidad_participacion || []).map((item) => `<option value="${assetEscape(item)}" ${item === calidad ? 'selected' : ''}>${assetEscape(item)}</option>`).join('');
        return `
            <div class="asset-participation-row" data-asset-participation-row>
                <label>Tipo participante<select name="participaciones[${index}][tipo_participante]" data-asset-participation-type>${assetPlaceholderOption(tipo)}${typeOptions}</select></label>
                <label>Participante<input name="participaciones[${index}][participante]" list="asset-participant-list-${index}" value="${assetEscape(participante)}" placeholder="Persona, empresa o tercero"><datalist id="asset-participant-list-${index}">${participantNameOptions(participants, tipo, participante)}</datalist></label>
                <label>Calidad<select name="participaciones[${index}][calidad]">${assetPlaceholderOption(calidad)}${qualityOptions}</select></label>
                <label>Porcentaje<input name="participaciones[${index}][porcentaje]" inputmode="decimal" data-asset-participation-percent value="${assetEscape(porcentaje)}" placeholder="0%"></label>
                <label>Soporte u observaciones<input name="participaciones[${index}][soporte_observaciones]" value="${assetEscape(soporte)}" placeholder="Escritura, certificado, contrato..."></label>
                <button type="button" class="asset-remove-participation" aria-label="Quitar participante" data-remove-asset-participation>&times;</button>
            </div>
        `;
    }).join('');
    updateAssetParticipationMeter(form);
    updateAssetContextSections(form);
};

const historyRowsForType = (form, selector, fields) => assetFormRows(form, selector, fields);

const expenseHistoryFields = ['predial', 'administracion', 'seguros', 'mantenimiento', 'reparaciones', 'servicios_publicos', 'valorizacion', 'impuestos', 'honorarios_administracion', 'comisiones', 'juridicos_notariales', 'financieros_hipoteca', 'adecuaciones', 'otros'];

const assetAcquisitionYear = (form, row = null) => {
    const date = row?.fecha_adquisicion
        || row?.detalle?.fecha_escritura
        || form.elements.fecha_adquisicion?.value
        || form.elements['detalle[fecha_escritura]']?.value
        || '';
    const match = String(date).match(/^(\d{4})-\d{2}-\d{2}$/);
    if (match) {
        return Number(match[1]);
    }
    return new Date().getFullYear();
};

const assetHistoryYears = (form, row = null) => {
    const start = assetAcquisitionYear(form, row);
    const end = new Date().getFullYear();
    const safeStart = Math.min(start, end);
    return Array.from({ length: end - safeStart + 1 }, (_, index) => safeStart + index);
};

const mergeRowsWithYears = (rows = [], years = []) => {
    const output = Array.isArray(rows) ? rows.filter((row) => row && Object.values(row).some((value) => value !== null && value !== '')) : [];
    const present = new Set(output.map((row) => Number(row.ano)).filter(Boolean));
    years.forEach((year) => {
        if (!present.has(year)) {
            output.push({ ano: year });
        }
    });
    return output.sort((a, b) => Number(a.ano || 0) - Number(b.ano || 0));
};

const historyMissingClass = (missing = []) => missing.length > 0 ? ' is-incomplete' : '';

const renderAssetAnnualHistory = (form, row = null) => {
    const section = form.querySelector('[data-asset-annual-history]');
    const visible = form.elements.tipo_activo?.value === 'Inmueble';
    if (section instanceof HTMLElement) {
        section.hidden = true;
        section.style.display = 'none';
    }
    if (!visible) {
        ['[data-asset-value-year-rows]', '[data-asset-income-year-rows]', '[data-asset-expense-year-rows]'].forEach((selector) => {
            const container = form.querySelector(selector);
            if (container instanceof HTMLElement) container.innerHTML = '';
        });
        return;
    }
    const years = assetHistoryYears(form, row);
    renderAssetValueYearRows(form, mergeRowsWithYears(row?.valoraciones_anuales || [], years));
    renderAssetIncomeYearRows(form, mergeRowsWithYears(row?.ingresos_anuales || [], years));
    renderAssetExpenseYearRows(form, mergeRowsWithYears(row?.gastos_anuales || [], years));
    renderAssetFinancialSummary(form);
    updateAssetContextSections(form);
};

const missingValueFields = (row) => ['valor_catastral', 'valor_comercial'].filter((field) => assetNumeric(row?.[field]) <= 0);

const renderAssetValueYearRows = (form, rows = []) => {
    const container = form.querySelector('[data-asset-value-year-rows]');
    if (!(container instanceof HTMLElement)) return;
    const sourceRows = rows.length > 0 ? rows : [{}];
    container.innerHTML = sourceRows.map((row, index) => {
        const missing = missingValueFields(row);
        return `
        <div class="asset-history-row asset-value-year-row${historyMissingClass(missing)}" data-asset-value-year-row title="${missing.length ? `Falta: ${assetEscape(missing.join(', '))}` : ''}">
            <label>Ano<input name="valoraciones_anuales[${index}][ano]" inputmode="numeric" value="${assetEscape(row.ano ?? '')}" placeholder="2026"></label>
            <label>Valor catastral<input name="valoraciones_anuales[${index}][valor_catastral]" inputmode="decimal" data-money-format value="${assetEscape(assetMoneyPlain(row.valor_catastral) || row.valor_catastral || '')}" placeholder="$0"></label>
            <label>Valor comercial<input name="valoraciones_anuales[${index}][valor_comercial]" inputmode="decimal" data-money-format value="${assetEscape(assetMoneyPlain(row.valor_comercial) || row.valor_comercial || '')}" placeholder="$0"></label>
            <label>Fecha corte<input name="valoraciones_anuales[${index}][fecha_corte]" type="date" value="${assetEscape(row.fecha_corte ?? '')}"></label>
            <label>Fuente<input name="valoraciones_anuales[${index}][fuente]" value="${assetEscape(row.fuente ?? '')}" placeholder="Catastro, avaluo..."></label>
            <label>Observaciones<input name="valoraciones_anuales[${index}][observaciones]" value="${assetEscape(row.observaciones ?? '')}"></label>
            <button type="button" class="asset-remove-history" aria-label="Quitar ano" data-remove-asset-value-year>&times;</button>
        </div>
    `;
    }).join('');
};

const renderAssetIncomeYearRows = (form, rows = []) => {
    const container = form.querySelector('[data-asset-income-year-rows]');
    if (!(container instanceof HTMLElement)) return;
    const sourceRows = rows.length > 0 ? rows : [{}];
    const tableHead = `
        <div class="asset-history-table-head asset-income-table-head" aria-hidden="true">
            <span>Ano</span>
            <span>Desde</span>
            <span>Hasta</span>
            <span>Meses</span>
            <span>Canon mes</span>
            <span>Canon ano</span>
            <span>% part.</span>
            <span>Inc. %</span>
            <span>Inc. valor</span>
            <span>Nuevo canon</span>
            <span>Renovacion</span>
            <span>Observaciones</span>
            <span>Bruto / INO</span>
            <span></span>
        </div>
    `;
    container.innerHTML = tableHead + sourceRows.map((row, index) => {
        const missing = ['canon_mensual'].filter((field) => assetNumeric(row?.[field]) <= 0);
        if (!row?.fecha_inicio_vigencia && !row?.fecha_fin_vigencia) {
            missing.push('vigencia');
        }
        return `
        <div class="asset-history-row asset-income-year-row${historyMissingClass(missing)}" data-asset-income-year-row title="${missing.length ? `Falta: ${assetEscape(missing.join(', '))}` : ''}">
            <label>Ano<input name="ingresos_anuales[${index}][ano]" inputmode="numeric" value="${assetEscape(row.ano ?? '')}" placeholder="2026"></label>
            <label>Vigencia desde<input name="ingresos_anuales[${index}][fecha_inicio_vigencia]" type="date" value="${assetEscape(row.fecha_inicio_vigencia ?? '')}"></label>
            <label>Vigencia hasta<input name="ingresos_anuales[${index}][fecha_fin_vigencia]" type="date" value="${assetEscape(row.fecha_fin_vigencia ?? '')}"></label>
            <label>Meses a dic. 31<input name="ingresos_anuales[${index}][meses_vigencia]" inputmode="decimal" value="${assetEscape(row.meses_vigencia ?? '')}" placeholder="Auto"></label>
            <label>Canon mensual total<input name="ingresos_anuales[${index}][canon_mensual]" inputmode="decimal" data-money-format value="${assetEscape(assetMoneyPlain(row.canon_mensual) || row.canon_mensual || '')}" placeholder="$0"></label>
            <output data-asset-annual-canon-preview>${assetEscape(assetMoney(assetAnnualCanonForRow(row)))}</output>
            <label>% participacion<input name="ingresos_anuales[${index}][porcentaje_participacion]" inputmode="decimal" value="${assetEscape(row.porcentaje_participacion ?? '')}" placeholder="${assetEscape(assetParticipationShare(form) * 100)}%"></label>
            <label>Incremento %<input name="ingresos_anuales[${index}][incremento_porcentaje]" inputmode="decimal" value="${assetEscape(row.incremento_porcentaje ?? '')}" placeholder="0%"></label>
            <label>Incremento valor<input name="ingresos_anuales[${index}][incremento_valor]" inputmode="decimal" data-money-format value="${assetEscape(assetMoneyPlain(row.incremento_valor) || row.incremento_valor || '')}" placeholder="$0"></label>
            <label>Nuevo canon mensual<input name="ingresos_anuales[${index}][nuevo_canon_mensual]" inputmode="decimal" data-money-format value="${assetEscape(assetMoneyPlain(row.nuevo_canon_mensual) || row.nuevo_canon_mensual || '')}" placeholder="$0"></label>
            <label>Fecha renovacion<input name="ingresos_anuales[${index}][fecha_renovacion]" type="date" value="${assetEscape(row.fecha_renovacion ?? '')}"></label>
            <label>Observaciones<input name="ingresos_anuales[${index}][observaciones]" value="${assetEscape(row.observaciones ?? '')}"></label>
            <output data-asset-income-preview>${assetEscape(assetHistoryIncomePreview(form, row))}</output>
            <button type="button" class="asset-remove-history" aria-label="Quitar ano" data-remove-asset-income-year>&times;</button>
        </div>
    `;
    }).join('');
    updateAssetIncomePreviews(form);
};

const missingExpenseFields = (row) => assetExpenseRowTotal(row) <= 0 ? ['gastos/costos'] : [];

const renderAssetExpenseYearRows = (form, rows = []) => {
    const container = form.querySelector('[data-asset-expense-year-rows]');
    if (!(container instanceof HTMLElement)) return;
    const sourceRows = rows.length > 0 ? rows : [{}];
    container.innerHTML = sourceRows.map((row, index) => {
        const missing = missingExpenseFields(row);
        return `
        <div class="asset-history-row asset-expense-year-row${historyMissingClass(missing)}" data-asset-expense-year-row title="${missing.length ? `Falta: ${assetEscape(missing.join(', '))}` : ''}">
            <label>Ano<input name="gastos_anuales[${index}][ano]" inputmode="numeric" value="${assetEscape(row.ano ?? '')}" placeholder="2026"></label>
            ${expenseHistoryFields.map((field) => `<label>${assetEscape(assetExpenseLabel(field))}<input name="gastos_anuales[${index}][${field}]" inputmode="decimal" data-money-format value="${assetEscape(assetMoneyPlain(row[field]) || row[field] || '')}" placeholder="$0"></label>`).join('')}
            <label>Observaciones<input name="gastos_anuales[${index}][observaciones]" value="${assetEscape(row.observaciones ?? '')}"></label>
            <output data-asset-expense-preview>Total ${assetEscape(assetMoney(assetExpenseRowTotal(row)))}</output>
            <button type="button" class="asset-remove-history" aria-label="Quitar ano" data-remove-asset-expense-year>&times;</button>
        </div>
    `;
    }).join('');
    updateAssetExpensePreviews(form);
};

const assetExpenseLabel = (field) => ({
    predial: 'Predial',
    administracion: 'Administracion',
    seguros: 'Seguros',
    mantenimiento: 'Mantenimiento',
    reparaciones: 'Reparaciones',
    servicios_publicos: 'Servicios',
    valorizacion: 'Valorizacion',
    impuestos: 'Impuestos',
    honorarios_administracion: 'Honorarios',
    comisiones: 'Comisiones',
    juridicos_notariales: 'Juridicos/notariales',
    financieros_hipoteca: 'Financieros/hipoteca',
    adecuaciones: 'Adecuaciones',
    otros: 'Otros',
}[field] || field);

const assetExpenseRowTotal = (row) => expenseHistoryFields.reduce((sum, field) => sum + assetNumeric(row?.[field]), 0);

const assetExpensesByYear = (form) => historyRowsForType(form, '[data-asset-expense-year-row]', ['ano', ...expenseHistoryFields]).reduce((carry, row) => {
    carry[row.ano] = assetExpenseRowTotal(row);
    return carry;
}, {});

const assetDateValue = (value) => {
    if (!value || !/^\d{4}-\d{2}-\d{2}$/.test(String(value))) return null;
    return new Date(`${value}T00:00:00`);
};

const assetMonthsInYear = (year, startValue, endValue) => {
    const numberYear = Number(year);
    if (!numberYear) return 0;
    const yearStart = new Date(`${numberYear}-01-01T00:00:00`);
    const yearEnd = new Date(`${numberYear}-12-31T00:00:00`);
    let start = assetDateValue(startValue) || yearStart;
    let end = assetDateValue(endValue) || yearEnd;
    if (start < yearStart) start = yearStart;
    if (end > yearEnd) end = yearEnd;
    if (end < start) return 0;
    const days = Math.round((end.getTime() - start.getTime()) / 86400000) + 1;
    return Math.round((days / (365 / 12)) * 100) / 100;
};

const assetMonthsForIncomeRow = (row) => assetNumeric(row.meses_vigencia) || assetMonthsInYear(row.ano, row.fecha_inicio_vigencia, row.fecha_fin_vigencia);

const assetAnnualCanonForRow = (row) => {
    const canon = assetNumeric(row.nuevo_canon_mensual) || assetNumeric(row.canon_mensual);
    return canon * assetMonthsForIncomeRow(row);
};

const assetIncomeGrossForRow = (form, row) => {
    const share = assetNumeric(row.porcentaje_participacion) || assetParticipationShare(form) * 100;
    return assetAnnualCanonForRow(row) * (share / 100);
};

const assetIncomeRowsFromForm = (form) => historyRowsForType(form, '[data-asset-income-year-row]', ['ano', 'fecha_inicio_vigencia', 'fecha_fin_vigencia', 'meses_vigencia', 'canon_mensual', 'porcentaje_participacion', 'nuevo_canon_mensual']);

const assetIncomeGrossByYear = (form) => assetIncomeRowsFromForm(form).reduce((carry, row) => {
    carry[row.ano] = (carry[row.ano] || 0) + assetIncomeGrossForRow(form, row);
    return carry;
}, {});

const assetHistoryIncomePreview = (form, row, grossByYear = null) => {
    const gross = assetIncomeGrossForRow(form, row);
    const expenses = assetExpensesByYear(form)[row.ano] || 0;
    const annualGross = (grossByYear || assetIncomeGrossByYear(form))[row.ano] || 0;
    const expenseShare = annualGross > 0 ? expenses * (gross / annualGross) : 0;
    return `Bruto ${assetMoney(gross)} / INO ${assetMoney(gross - expenseShare)}`;
};

const updateAssetIncomePreviews = (form) => {
    const grossByYear = assetIncomeGrossByYear(form);
    form.querySelectorAll('[data-asset-income-year-row]').forEach((row) => {
        const output = row.querySelector('[data-asset-income-preview]');
        if (!(output instanceof HTMLOutputElement)) return;
        const values = ['ano', 'fecha_inicio_vigencia', 'fecha_fin_vigencia', 'meses_vigencia', 'canon_mensual', 'porcentaje_participacion', 'nuevo_canon_mensual'].reduce((carry, field) => {
            carry[field] = row.querySelector(`[name$="[${field}]"]`)?.value ?? '';
            return carry;
        }, {});
        const annualCanon = row.querySelector('[data-asset-annual-canon-preview]');
        if (annualCanon instanceof HTMLOutputElement) {
            annualCanon.textContent = assetMoney(assetAnnualCanonForRow(values));
        }
        output.textContent = assetHistoryIncomePreview(form, values, grossByYear);
    });
    renderAssetFinancialSummary(form);
};

const updateAssetExpensePreviews = (form) => {
    form.querySelectorAll('[data-asset-expense-year-row]').forEach((row) => {
        const output = row.querySelector('[data-asset-expense-preview]');
        if (!(output instanceof HTMLOutputElement)) return;
        const values = expenseHistoryFields.reduce((carry, field) => {
            carry[field] = row.querySelector(`[name$="[${field}]"]`)?.value ?? '';
            return carry;
        }, {});
        output.textContent = `Total ${assetMoney(assetExpenseRowTotal(values))}`;
    });
    updateAssetIncomePreviews(form);
    renderAssetFinancialSummary(form);
};

const renderAssetFinancialSummary = (form) => {
    const target = form.querySelector('[data-asset-financial-summary]');
    if (!(target instanceof HTMLElement)) {
        return;
    }
    const incomeByYear = assetIncomeRowsFromForm(form).reduce((carry, row) => {
        if (!row.ano) return carry;
        carry[row.ano] ||= { year: row.ano, gross: 0, canon: 0, months: 0 };
        carry[row.ano].gross += assetIncomeGrossForRow(form, row);
        carry[row.ano].canon += assetAnnualCanonForRow(row);
        carry[row.ano].months += assetMonthsForIncomeRow(row);
        return carry;
    }, {});
    const valueByYear = historyRowsForType(form, '[data-asset-value-year-row]', ['ano', 'valor_catastral', 'valor_comercial']).reduce((carry, row) => {
        if (row.ano) carry[row.ano] = row;
        return carry;
    }, {});
    const expenseByYear = assetExpensesByYear(form);
    Object.entries(expenseByYear).forEach(([year, expenses]) => {
        incomeByYear[year] ||= { year, gross: 0, canon: 0, months: 0 };
        incomeByYear[year].expenses = expenses;
    });
    Object.keys(valueByYear).forEach((year) => {
        incomeByYear[year] ||= { year, gross: 0, canon: 0, months: 0 };
    });
    const years = Object.values(incomeByYear).sort((a, b) => Number(a.year) - Number(b.year));
    if (years.length === 0) {
        target.innerHTML = '<div class="asset-renewal-empty">Agrega ingresos o gastos anuales para ver el resumen financiero.</div>';
        return;
    }
    target.innerHTML = `
        <div class="asset-financial-table">
            <div class="asset-financial-head">
                <span>Ano</span>
                <span>Meses</span>
                <span>Canon ano total</span>
                <span>Ingreso bruto real</span>
                <span>Gastos y costos</span>
                <span>INO</span>
                <span>Margen operativo</span>
                <span>Estado</span>
            </div>
            ${years.map((row) => {
                const expenses = Number(row.expenses ?? expenseByYear[row.year] ?? 0);
                const ino = Number(row.gross || 0) - expenses;
                const margin = Number(row.gross || 0) > 0 ? (ino / Number(row.gross || 0)) * 100 : 0;
                const missing = [];
                if (missingValueFields(valueByYear[row.year] || {}).length > 0) missing.push('valores');
                if (Number(row.gross || 0) <= 0) missing.push('ingresos');
                if (expenses <= 0) missing.push('gastos');
                return `
                    <div class="asset-financial-row">
                        <span>${assetEscape(row.year)}</span>
                        <span>${assetEscape(new Intl.NumberFormat('es-CO', { maximumFractionDigits: 2 }).format(row.months || 0))}</span>
                        <span>${assetEscape(assetMoney(row.canon || 0))}</span>
                        <span>${assetEscape(assetMoney(row.gross || 0))}</span>
                        <span>${assetEscape(assetMoney(expenses))}</span>
                        <span class="${ino < 0 ? 'is-negative' : 'is-positive'}">${assetEscape(assetMoney(ino))}</span>
                        <span>${assetEscape(assetPercent(margin))}</span>
                        <span class="${missing.length ? 'is-negative' : 'is-positive'}">${assetEscape(missing.length ? `Falta ${missing.join(', ')}` : 'Completo')}</span>
                    </div>
                `;
            }).join('')}
        </div>
    `;
};

const assetChartHtml = (rows, label, field, formatter = assetMoney) => {
    const source = (Array.isArray(rows) ? rows : []).filter((row) => row && row.ano);
    if (source.length === 0) {
        return `<article class="asset-history-chart"><strong>${assetEscape(label)}</strong><span class="muted">Sin historial</span></article>`;
    }
    const max = Math.max(1, ...source.map((row) => Math.abs(Number(row[field] || 0))));
    return `
        <article class="asset-history-chart">
            <strong>${assetEscape(label)}</strong>
            <div class="asset-history-bars">
                ${source.map((row) => {
                    const value = Number(row[field] || 0);
                    const height = Math.max(6, Math.min(100, (Math.abs(value) / max) * 100));
                    return `<span title="${assetEscape(`${row.ano}: ${formatter(value)}`)}"><i style="height:${height}%"></i><small>${assetEscape(row.ano)}</small></span>`;
                }).join('')}
            </div>
        </article>
    `;
};

const assetProfitRows = (row) => {
    const valuesByYear = (Array.isArray(row.valoraciones_anuales) ? row.valoraciones_anuales : []).reduce((carry, valueRow) => {
        if (valueRow?.ano) carry[valueRow.ano] = Number(valueRow.valor_comercial || 0);
        return carry;
    }, {});
    const share = Math.max(0, Math.min(100, Number(row.participacion_total || 0))) / 100;
    const fallback = Number(row.valor_actual || 0);
    return (Array.isArray(row.ingresos_anuales) ? row.ingresos_anuales : []).map((incomeRow) => {
        const basis = (valuesByYear[incomeRow.ano] || fallback) * share;
        return { ano: incomeRow.ano, rentabilidad: basis > 0 ? (Number(incomeRow.ino_anual || 0) / basis) * 100 : 0 };
    });
};

const assetHistoryDashboardHtml = (row) => row.tipo_activo === 'Inmueble' ? `
    <div class="asset-history-dashboard">
        ${assetChartHtml(row.valoraciones_anuales, 'Valor catastral ano tras ano', 'valor_catastral')}
        ${assetChartHtml(row.valoraciones_anuales, 'Valor comercial ano tras ano', 'valor_comercial')}
        ${assetChartHtml(row.gastos_anuales, 'Gastos y costos ano tras ano', 'total_gastos')}
        ${assetChartHtml(assetProfitRows(row), 'Rentabilidad inmueble', 'rentabilidad', assetPercent)}
    </div>
` : '';

const renderAssetRow = (row) => {
    const details = document.createElement('details');
    const subunitCount = row.tipo_activo === 'Inmueble' && Array.isArray(row.subunidades) ? row.subunidades.length : 0;
    details.className = 'asset-accordion-item';
    details.dataset.assetRow = row.id;
    details.dataset.row = JSON.stringify(row);
    details.dataset.assetSearchText = assetSearchText(row);
    details.innerHTML = `
        <summary>
            <span class="asset-summary-main">
                <strong>${assetEscape(row.nombre_descripcion)}</strong>
                <small>${assetEscape(row.identificador || row.etiqueta_identificador || 'Sin identificador')}</small>
            </span>
            <span class="asset-summary-meta">
                <span><b>${assetEscape(assetMoney(row.valor_actual))}</b> valor vigente</span>
                <span>${assetEscape(assetPercent(row.participacion_total || 0))} registrada</span>
                ${subunitCount > 0 ? `<span>${assetEscape(subunitCount)} subunidades</span>` : ''}
                <code>${assetEscape(row.codigo)}</code>
            </span>
        </summary>
        <div class="asset-detail">
            <dl class="asset-detail-grid">
                <div><dt>Titular</dt><dd>${assetEscape(row.titular)}</dd></div>
                <div><dt>Ambito titular</dt><dd>${assetEscape(row.ambito_titular)}</dd></div>
                <div><dt>Empresa relacionada</dt><dd>${assetEscape(row.empresa_relacionada)}</dd></div>
                <div><dt>Valor adquisicion/escritura</dt><dd>${assetEscape(assetMoney(row.valor_adquisicion))}</dd></div>
                <div><dt>Valor comercial vigente</dt><dd><span class="asset-value">${assetEscape(assetMoney(row.valor_actual))}</span></dd></div>
                <div><dt>Fecha corte</dt><dd>${assetEscape(assetDate(row.fecha_corte_valor))}</dd></div>
                <div><dt>Soporte</dt><dd>${assetEscape(row.estado_soporte)}</dd></div>
                <div><dt>Riesgo</dt><dd>${assetEscape(row.nivel_riesgo)}</dd></div>
                <div><dt>Participacion</dt><dd>${assetEscape(assetPercent(row.participacion_total || 0))} registrada${Number(row.participacion_pendiente || 0) > 0 ? `<span class="asset-pending-share">${assetEscape(assetPercent(row.participacion_pendiente))} pendiente</span>` : ''}</dd></div>
                <div><dt>Ingreso bruto anual</dt><dd>${assetEscape(assetMoney(row.patrimonio_analytics?.ingreso_bruto_anual || 0))}</dd></div>
                <div><dt>Gastos anuales</dt><dd>${assetEscape(assetMoney(row.patrimonio_analytics?.gastos_anuales || 0))}</dd></div>
                <div><dt>INO anual</dt><dd><span class="asset-value">${assetEscape(assetMoney(row.patrimonio_analytics?.ino_anual || 0))}</span></dd></div>
                <div><dt>Rentabilidad anual</dt><dd>${row.patrimonio_analytics?.rentabilidad_anual !== null && row.patrimonio_analytics?.rentabilidad_anual !== undefined ? assetEscape(assetPercent(row.patrimonio_analytics.rentabilidad_anual)) : ''}</dd></div>
                <div class="wide"><dt>Observaciones</dt><dd>${assetEscape(row.observaciones)}</dd></div>
            </dl>
            ${assetHistoryDashboardHtml(row)}
            <div class="asset-row-actions">
                <button type="button" data-edit-asset>Editar</button>
                <button type="button" data-delete-asset>Desactivar</button>
            </div>
        </div>
    `;
    return details;
};

const fillAssetForm = (form, row = null, { restoreDraft = false } = {}) => {
    form.reset();
    const draft = restoreDraft ? readAssetDraft(form, row?.id || 'nuevo') : null;
    const source = mergeAssetDraft(row, draft);
    assetLoadedRows.set(form, source || null);
    form.elements.id.value = row?.id ?? '';
    form.elements.codigo.value = row?.codigo ?? 'Automatico';
    assetFields.forEach((field) => {
        if (form.elements[field]) {
            form.elements[field].value = source?.[field] ?? '';
        }
    });
    if (!source && form.elements.moneda) {
        form.elements.moneda.value = 'COP';
    }
    renderAssetSpecificFields(form, source);
    renderAssetFiduciaRows(form, source?.fiducia_beneficiarios || []);
    renderAssetSubunitRows(form, source?.subunidades || []);
    renderAssetAnnualHistory(form, source);
    renderAssetInsurancePolicyRows(form, source?.seguro_polizas || []);
    renderAssetInsuranceCoverageRows(form, source?.seguro_coberturas || []);
    renderAssetInsuranceEquipmentRows(form, source?.seguro_equipos || []);
    renderAssetInsuranceCurrentPolicyRows(form);
    renderAssetInsuranceAssetExposureRows(form);
    renderAssetInsuranceMovementRows(form, source?.seguro_movimientos || []);
    renderAssetCurrentPolicy(form);
    renderAssetInsuranceHistory(form);
    renderAssetParticipationRows(form, source?.participaciones || []);
    renderAssetModalDocuments(form, row, parseAssetJson(form, 'assetDocuments'), form.closest('[data-workspace]')?.dataset.basePath ?? '');
    formatAssetMoneyInputs(form);
    return Boolean(draft);
};

const assetForm = document.querySelector('[data-asset-form]');
if (assetForm instanceof HTMLFormElement) {
    const workspace = assetForm.closest('[data-workspace]');
    const basePath = workspace?.dataset.basePath ?? '';
    const modal = document.querySelector('[data-asset-modal]');
    const modalTitle = document.querySelector('[data-asset-modal-title]');
    const tableBody = document.querySelector('[data-asset-table]');
    const assetSearch = document.querySelector('[data-asset-search]');
    const assetSearchStatus = document.querySelector('[data-asset-search-status]');
    const status = document.querySelector('[data-asset-status]');
    const count = document.querySelector('[data-asset-count]');
    const modalDocuments = assetForm.querySelector('[data-asset-modal-documents]');
    const assetDocuments = parseAssetJson(assetForm, 'assetDocuments');
    let lastAssetTrigger = null;
    let assetDraftTimer = null;
    let assetDirty = false;
    enhanceStaticAssetHelp(assetForm);

    const updateAssetTypeCounts = () => {
        document.querySelectorAll('[data-asset-type-group]').forEach((group) => {
            if (!(group instanceof HTMLElement)) {
                return;
            }
            const rows = group.querySelectorAll('[data-asset-row]');
            const countTarget = group.querySelector('[data-asset-type-count]');
            if (countTarget) {
                countTarget.textContent = String(rows.length);
            }
            group.hidden = rows.length === 0;
        });
    };

    const applyAssetSearch = () => {
        if (!(assetSearch instanceof HTMLInputElement)) {
            return;
        }
        const query = assetSearch.value.trim().toLowerCase();
        let visible = 0;
        document.querySelectorAll('[data-asset-type-group]').forEach((group) => {
            if (!(group instanceof HTMLDetailsElement)) {
                return;
            }
            let groupVisible = 0;
            group.querySelectorAll('[data-asset-row]').forEach((row) => {
                if (!(row instanceof HTMLElement)) {
                    return;
                }
                const match = !query || (row.dataset.assetSearchText || '').includes(query);
                row.hidden = !match;
                if (match) {
                    groupVisible += 1;
                    visible += 1;
                }
            });
            group.hidden = groupVisible === 0;
            if (query && groupVisible > 0) {
                group.open = true;
            }
        });
        if (assetSearchStatus) {
            assetSearchStatus.textContent = query ? `${visible} activos encontrados` : '';
        }
    };

    const assetGroupForType = (type) => {
        if (!(tableBody instanceof HTMLElement)) {
            return null;
        }
        const safeType = type || 'Sin tipo';
        let group = [...tableBody.querySelectorAll('[data-asset-type-group]')].find((item) => item instanceof HTMLElement && item.dataset.assetTypeGroup === safeType);
        if (group instanceof HTMLElement) {
            return group;
        }
        group = document.createElement('details');
        group.className = 'asset-type-group';
        group.dataset.assetTypeGroup = safeType;
        group.open = true;
        group.innerHTML = `
            <summary>
                <span>
                    <strong>${assetEscape(safeType)}</strong>
                    <small><span data-asset-type-count>0</span> activos</small>
                </span>
            </summary>
            <div class="asset-type-items"></div>
        `;
        tableBody.append(group);
        return group;
    };

    const upsertAssetRow = (row) => {
        const nextRow = renderAssetRow(row);
        const current = tableBody?.querySelector(`[data-asset-row="${row.id}"]`);
        const group = assetGroupForType(row.tipo_activo);
        const items = group?.querySelector('.asset-type-items');
        if (!(items instanceof HTMLElement)) {
            return;
        }
        if (current) {
            current.remove();
        }
        items.append(nextRow);
        updateAssetTypeCounts();
        applyAssetSearch();
    };

    const scheduleAssetDraft = () => {
        assetDirty = true;
        window.clearTimeout(assetDraftTimer);
        assetDraftTimer = window.setTimeout(() => {
            saveAssetDraft(assetForm);
            status && (status.textContent = 'Borrador guardado en este navegador.');
        }, 450);
    };

    const flushAssetDraft = () => {
        window.clearTimeout(assetDraftTimer);
        if (assetDirty) {
            saveAssetDraft(assetForm);
            status && (status.textContent = 'Borrador guardado en este navegador.');
        }
    };

    assetForm.elements.tipo_activo?.addEventListener('change', () => {
        const selectedType = assetForm.elements.tipo_activo?.value || '';
        const loadedSource = assetLoadedRows.get(assetForm);
        const sameLoadedType = loadedSource && loadedSource.tipo_activo === selectedType;
        const source = sameLoadedType ? loadedSource : { tipo_activo: selectedType, moneda: 'COP' };
        assetFields.forEach((field) => {
            const input = assetForm.elements[field];
            if (input instanceof HTMLInputElement || input instanceof HTMLSelectElement || input instanceof HTMLTextAreaElement) {
                input.value = source?.[field] ?? '';
            }
        });
        if (assetForm.elements.tipo_activo) {
            assetForm.elements.tipo_activo.value = selectedType;
        }
        if (!source?.moneda && assetForm.elements.moneda) {
            assetForm.elements.moneda.value = 'COP';
        }
        assetForm.dataset.assetInsuranceTab = 'actual';
        assetForm.dataset.assetCoverageActiveProduct = '';
        assetForm.dataset.assetAssetsActiveProduct = '';
        assetForm.dataset.assetValueActiveProduct = '';
        assetForm.dataset.assetCoveragePolicyIndex = '0';
        renderAssetSpecificFields(assetForm, source);
        renderAssetFiduciaRows(assetForm, sameLoadedType ? source?.fiducia_beneficiarios || [] : []);
        renderAssetSubunitRows(assetForm, sameLoadedType ? source?.subunidades || [] : []);
        renderAssetAnnualHistory(assetForm, sameLoadedType ? source : null);
        renderAssetInsurancePolicyRows(assetForm, sameLoadedType ? source?.seguro_polizas || [] : []);
        renderAssetInsuranceCoverageRows(assetForm, sameLoadedType ? source?.seguro_coberturas || [] : []);
        renderAssetInsuranceEquipmentRows(assetForm, sameLoadedType ? source?.seguro_equipos || [] : []);
        renderAssetInsuranceCurrentPolicyRows(assetForm);
        renderAssetInsuranceAssetExposureRows(assetForm);
        renderAssetInsuranceMovementRows(assetForm, sameLoadedType ? source?.seguro_movimientos || [] : []);
        renderAssetCurrentPolicy(assetForm);
        renderAssetInsuranceHistory(assetForm);
        status && (status.textContent = sameLoadedType ? 'Datos originales restaurados para esta categoria.' : 'Categoria cambiada: completa los datos propios del nuevo tipo de activo.');
    });

    assetSearch?.addEventListener('input', applyAssetSearch);

    assetForm.addEventListener('input', scheduleAssetDraft);
    assetForm.addEventListener('change', scheduleAssetDraft);
    window.addEventListener('beforeunload', flushAssetDraft);
    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'hidden') {
            flushAssetDraft();
        }
    });
    assetForm.addEventListener('change', (event) => {
        const target = event.target;
        if (target instanceof HTMLSelectElement && target.name === 'detalle[tipo_derecho]') {
            updateInmuebleRightAcademy(assetForm);
        }
    });

    assetForm.querySelector('[data-asset-specific]')?.addEventListener('input', (event) => {
        const target = event.target;
        if (target instanceof HTMLInputElement && ['detalle[valor_contable_terreno]', 'detalle[valor_contable_construccion]'].includes(target.name)) {
            syncAccountingTotal(assetForm);
            syncDepreciationValues(assetForm);
        }
        if (target instanceof HTMLInputElement && ['detalle[depreciacion_mensual]', 'detalle[depreciacion_acumulada]', 'detalle[valor_contable_total]', 'detalle[valor_contable_inicial]'].includes(target.name)) {
            syncDepreciationValues(assetForm);
        }
        if (target instanceof HTMLInputElement && ['detalle[valor_escritura]', 'detalle[fecha_escritura]', 'detalle[valor_comercial]', 'detalle[fecha_valor_comercial]', 'detalle[fuente_valor_comercial]'].includes(target.name)) {
            syncInmuebleCommonFields(assetForm);
        }
        if (target instanceof HTMLInputElement && ['detalle[area_construida_asegurable]', 'detalle[area_construida]', 'detalle[valor_reposicion_m2]', 'detalle[fuente_reposicion_construccion]', 'detalle[fecha_consulta_reposicion_construccion]'].includes(target.name)) {
            syncInsuranceConstructionValue(assetForm);
            refreshCoverageSuggestedValues(assetForm);
            updateInsuranceDerivedSummary(assetForm);
        }
        syncInmuebleEconomics(assetForm);
        syncIntangibleCommonFields(assetForm);
    });

    assetForm.querySelector('[data-asset-specific]')?.addEventListener('change', (event) => {
        const target = event.target;
        if (target instanceof HTMLInputElement && target.matches('[data-asset-insurance-type-toggle]')) {
            target.closest('.asset-coverage-chip')?.classList.toggle('is-selected', target.checked);
            if (target.checked) {
                assetForm.dataset.assetAcademyActiveProduct = target.value;
            }
            updateInsuranceTypeSelection(assetForm);
            updateInsuranceProductAcademy(assetForm);
            renderAssetInsuranceCoverageRows(assetForm, historyRowsForType(assetForm, '[data-asset-insurance-coverage-row]', assetInsuranceCoverageFields));
            renderAssetInsuranceAssetExposureRows(assetForm);
            renderAssetInsuranceEquipmentRows(assetForm, assetInsuranceEquipmentRows(assetForm));
            renderAssetInsuranceCurrentPolicyRows(assetForm);
            renderAssetInsurancePolicyRows(assetForm, historyRowsForType(assetForm, '[data-asset-insurance-policy-row]', assetInsurancePolicyFields));
            renderInsuranceQuoteAnalysis(assetForm);
        }
        if (target instanceof HTMLInputElement && target.matches('[data-asset-insurable-toggle]')) {
            const option = insuranceAssetOptionByKey(target.value);
            if (option) {
                if (target.checked) {
                    upsertInsuredAssetFromOption(assetForm, option);
                } else {
                    removeInsuredAssetByOption(assetForm, option);
                }
            }
            updateInsuranceProductVisibility(assetForm);
            renderAssetInsuranceAssetExposureRows(assetForm);
            renderAssetInsuranceEquipmentRows(assetForm, assetInsuranceEquipmentRows(assetForm));
        }
        if (target instanceof HTMLSelectElement && [
            'detalle[uso]',
            'detalle[tipo_inmueble]',
            'detalle[propiedad_horizontal]',
            'detalle[tiene_hipoteca]',
            'detalle[clase_activo]',
            'detalle[tipo_especifico]',
            'detalle[seguro_macro_ramo]',
        ].includes(target.name)) {
            updateInsuranceProductVisibility(assetForm);
            renderAssetInsurancePolicyRows(assetForm);
            renderAssetInsuranceCoverageRows(assetForm, historyRowsForType(assetForm, '[data-asset-insurance-coverage-row]', assetInsuranceCoverageFields));
            renderAssetInsuranceAssetExposureRows(assetForm);
            renderAssetInsuranceEquipmentRows(assetForm, assetInsuranceEquipmentRows(assetForm));
        }
        syncInmuebleCommonFields(assetForm);
        syncInsuranceConstructionValue(assetForm);
        syncInmuebleEconomics(assetForm);
        syncIntangibleCommonFields(assetForm);
        syncAssetConditionalFields(assetForm);
        updateCategoryDefinitionPanel(assetForm);
        updateInsuranceDerivedSummary(assetForm);
    });

    assetForm.querySelector('[data-asset-specific]')?.addEventListener('click', (event) => {
        const target = event.target;
        if (!(target instanceof Element)) {
            return;
        }
        const academyTab = target.closest('[data-insurance-academy-tab]');
        if (academyTab instanceof HTMLButtonElement) {
            assetForm.dataset.assetAcademyActiveProduct = academyTab.dataset.insuranceAcademyTab || '';
            updateInsuranceProductAcademy(assetForm);
            saveAssetDraft(assetForm);
            return;
        }
        const button = target.closest('[data-asset-tab]');
        if (!(button instanceof HTMLButtonElement)) {
            return;
        }
        const tab = button.dataset.assetTab;
        const container = assetForm.querySelector('[data-asset-specific]');
        container?.querySelectorAll('[data-asset-tab]').forEach((item) => {
            if (item instanceof HTMLButtonElement) {
                const active = item.dataset.assetTab === tab;
                item.classList.toggle('is-active', active);
                item.setAttribute('aria-selected', active ? 'true' : 'false');
            }
        });
        container?.querySelectorAll('[data-asset-tab-panel]').forEach((panel) => {
            if (panel instanceof HTMLElement) {
                const active = !['participacion', 'historial-anual'].includes(tab || '') && panel.dataset.assetTabPanel === tab;
                panel.classList.toggle('is-active', active);
                panel.hidden = !active;
            }
        });
        updateAssetContextSections(assetForm);
    });

    assetForm.querySelector('[data-add-asset-participation]')?.addEventListener('click', () => {
        const rows = [...assetForm.querySelectorAll('[data-asset-participation-row]')].map((row) => ({
            tipo_participante: row.querySelector('[name$="[tipo_participante]"]')?.value ?? '',
            participante: row.querySelector('[name$="[participante]"]')?.value ?? '',
            calidad: row.querySelector('[name$="[calidad]"]')?.value ?? '',
            porcentaje: row.querySelector('[name$="[porcentaje]"]')?.value ?? '',
            soporte_observaciones: row.querySelector('[name$="[soporte_observaciones]"]')?.value ?? '',
        }));
        rows.push({});
        renderAssetParticipationRows(assetForm, rows);
        saveAssetDraft(assetForm);
    });

    assetForm.querySelector('[data-asset-participation-rows]')?.addEventListener('input', () => updateAssetParticipationMeter(assetForm));

    assetForm.querySelector('[data-add-asset-fiducia-beneficiary]')?.addEventListener('click', () => {
        const rows = [...assetForm.querySelectorAll('[data-asset-fiducia-row]')].map((row) => ({
            beneficiario: row.querySelector('[name$="[beneficiario]"]')?.value ?? '',
            calidad: row.querySelector('[name$="[calidad]"]')?.value ?? '',
            porcentaje: row.querySelector('[name$="[porcentaje]"]')?.value ?? '',
            condicion: row.querySelector('[name$="[condicion]"]')?.value ?? '',
            soporte_observaciones: row.querySelector('[name$="[soporte_observaciones]"]')?.value ?? '',
        }));
        rows.push({});
        renderAssetFiduciaRows(assetForm, rows);
        const lastRow = assetForm.querySelector('[data-asset-fiducia-row]:last-child');
        if (lastRow instanceof HTMLElement) {
            lastRow.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
            lastRow.querySelector('input, select, textarea')?.focus({ preventScroll: true });
        }
        saveAssetDraft(assetForm);
    });

    assetForm.querySelector('[data-asset-fiducia-rows]')?.addEventListener('input', () => updateAssetFiduciaMeter(assetForm));

    assetForm.querySelector('[data-add-asset-subunit]')?.addEventListener('click', () => {
        const rows = assetFormRows(assetForm, '[data-asset-subunit-row]', ['codigo_subunidad', 'nombre_subunidad', 'area', 'uso', 'arrendatario', 'canon_mensual', 'administracion_mensual', 'iva', 'retencion', 'contrato_soporte', 'fecha_inicio', 'fecha_fin', 'estado_ocupacion', 'observaciones']);
        rows.push({});
        renderAssetSubunitRows(assetForm, rows);
        const lastRow = assetForm.querySelector('[data-asset-subunit-row]:last-child');
        if (lastRow instanceof HTMLElement) {
            lastRow.scrollIntoView({ block: 'center', behavior: 'smooth' });
            lastRow.querySelector('input, select, textarea')?.focus({ preventScroll: true });
        }
        saveAssetDraft(assetForm);
    });

    assetForm.querySelector('[data-asset-subunit-rows]')?.addEventListener('input', () => {
        updateAssetSubunitMeter(assetForm);
        syncInmuebleEconomics(assetForm);
    });

    assetForm.querySelector('[data-asset-subunit-rows]')?.addEventListener('click', (event) => {
        const target = event.target;
        if (!(target instanceof Element) || !target.closest('[data-remove-asset-subunit]')) {
            return;
        }
        target.closest('[data-asset-subunit-row]')?.remove();
        const rows = assetFormRows(assetForm, '[data-asset-subunit-row]', ['codigo_subunidad', 'nombre_subunidad', 'area', 'uso', 'arrendatario', 'canon_mensual', 'administracion_mensual', 'iva', 'retencion', 'contrato_soporte', 'fecha_inicio', 'fecha_fin', 'estado_ocupacion', 'observaciones']);
        renderAssetSubunitRows(assetForm, rows);
        syncInmuebleEconomics(assetForm);
        saveAssetDraft(assetForm);
    });

    assetForm.querySelector('[data-add-asset-value-year]')?.addEventListener('click', () => {
        const rows = historyRowsForType(assetForm, '[data-asset-value-year-row]', ['ano', 'valor_catastral', 'valor_comercial', 'fecha_corte', 'fuente', 'observaciones']);
        rows.push({});
        renderAssetValueYearRows(assetForm, rows);
        saveAssetDraft(assetForm);
    });

    assetForm.querySelector('[data-add-asset-income-year]')?.addEventListener('click', () => {
        const rows = historyRowsForType(assetForm, '[data-asset-income-year-row]', ['ano', 'fecha_inicio_vigencia', 'fecha_fin_vigencia', 'meses_vigencia', 'canon_mensual', 'porcentaje_participacion', 'incremento_porcentaje', 'incremento_valor', 'nuevo_canon_mensual', 'fecha_renovacion', 'observaciones']);
        rows.push({});
        renderAssetIncomeYearRows(assetForm, rows);
        saveAssetDraft(assetForm);
    });

    assetForm.querySelector('[data-add-asset-expense-year]')?.addEventListener('click', () => {
        const rows = historyRowsForType(assetForm, '[data-asset-expense-year-row]', ['ano', ...expenseHistoryFields, 'observaciones']);
        rows.push({});
        renderAssetExpenseYearRows(assetForm, rows);
        saveAssetDraft(assetForm);
    });

    assetForm.querySelector('[data-asset-annual-history]')?.addEventListener('input', () => {
        updateAssetExpensePreviews(assetForm);
        updateAssetIncomePreviews(assetForm);
    });

    assetForm.querySelector('[data-asset-annual-history]')?.addEventListener('click', (event) => {
        const target = event.target;
        if (!(target instanceof Element)) {
            return;
        }
        if (target.closest('[data-remove-asset-value-year]')) {
            target.closest('[data-asset-value-year-row]')?.remove();
            renderAssetValueYearRows(assetForm, historyRowsForType(assetForm, '[data-asset-value-year-row]', ['ano', 'valor_catastral', 'valor_comercial', 'fecha_corte', 'fuente', 'observaciones']));
        }
        if (target.closest('[data-remove-asset-income-year]')) {
            target.closest('[data-asset-income-year-row]')?.remove();
            renderAssetIncomeYearRows(assetForm, historyRowsForType(assetForm, '[data-asset-income-year-row]', ['ano', 'fecha_inicio_vigencia', 'fecha_fin_vigencia', 'meses_vigencia', 'canon_mensual', 'porcentaje_participacion', 'incremento_porcentaje', 'incremento_valor', 'nuevo_canon_mensual', 'fecha_renovacion', 'observaciones']));
        }
        if (target.closest('[data-remove-asset-expense-year]')) {
            target.closest('[data-asset-expense-year-row]')?.remove();
            renderAssetExpenseYearRows(assetForm, historyRowsForType(assetForm, '[data-asset-expense-year-row]', ['ano', ...expenseHistoryFields, 'observaciones']));
        }
        saveAssetDraft(assetForm);
    });

    assetForm.querySelector('[data-asset-fiducia-rows]')?.addEventListener('click', (event) => {
        const target = event.target;
        if (!(target instanceof Element) || !target.closest('[data-remove-asset-fiducia]')) {
            return;
        }
        const row = target.closest('[data-asset-fiducia-row]');
        row?.remove();
        const rows = [...assetForm.querySelectorAll('[data-asset-fiducia-row]')].map((item) => ({
            beneficiario: item.querySelector('[name$="[beneficiario]"]')?.value ?? '',
            calidad: item.querySelector('[name$="[calidad]"]')?.value ?? '',
            porcentaje: item.querySelector('[name$="[porcentaje]"]')?.value ?? '',
            condicion: item.querySelector('[name$="[condicion]"]')?.value ?? '',
            soporte_observaciones: item.querySelector('[name$="[soporte_observaciones]"]')?.value ?? '',
        }));
        renderAssetFiduciaRows(assetForm, rows);
        saveAssetDraft(assetForm);
    });

    const assetInsuranceMovementFields = ['ano', 'fecha', 'tipo_movimiento', 'numero_poliza', 'ramo', 'cobertura', 'item', 'valor_variacion', 'estado_reporte', 'fecha_reporte_aseguradora', 'soporte', 'observaciones'];

    assetForm.querySelector('[data-asset-insurance-flow]')?.addEventListener('click', (event) => {
        const target = event.target;
        if (!(target instanceof Element)) {
            return;
        }
        const button = target.closest('[data-asset-insurance-tab]');
        if (!(button instanceof HTMLButtonElement)) {
            return;
        }
        assetForm.dataset.assetInsuranceTab = button.dataset.assetInsuranceTab || 'actual';
        updateAssetInsuranceSections(assetForm, assetForm.elements.tipo_activo?.value || '');
        renderAssetInsuranceCurrentPolicyRows(assetForm);
        renderAssetInsuranceAssetExposureRows(assetForm);
        renderAssetInsuranceCoverageRows(assetForm, historyRowsForType(assetForm, '[data-asset-insurance-coverage-row]', assetInsuranceCoverageFields));
        renderAssetInsuranceEquipmentRows(assetForm, assetInsuranceEquipmentRows(assetForm));
        if (assetForm.dataset.assetInsuranceTab === 'cotizaciones') {
            renderAssetInsurancePolicyRows(assetForm, historyRowsForType(assetForm, '[data-asset-insurance-policy-row]', assetInsurancePolicyFields));
            renderInsuranceQuoteAnalysis(assetForm);
        }
        renderAssetCurrentPolicy(assetForm);
        renderAssetInsuranceMatrixSummary(assetForm);
        renderAssetInsuranceHistory(assetForm);
    });

    assetForm.querySelector('[data-add-asset-insurance-policy]')?.addEventListener('click', () => {
        const rows = historyRowsForType(assetForm, '[data-asset-insurance-policy-row]', assetInsurancePolicyFields);
        rows.push({
            ano: String(new Date().getFullYear()),
            ramo: joinInsuranceSelection(selectedInsuranceProductsFromForm(assetForm)),
            valor_asegurado_total: insuranceRequestedTotal(assetForm) > 0 ? String(Math.round(insuranceRequestedTotal(assetForm))) : '',
            estado: 'Cotizacion solicitada',
        });
        renderAssetInsurancePolicyRows(assetForm, rows);
        saveAssetDraft(assetForm);
    });

    assetForm.querySelectorAll('[data-add-asset-insurance-matrix]').forEach((matrixButton) => matrixButton.addEventListener('click', () => {
        const rows = historyRowsForType(assetForm, '[data-asset-insurance-policy-row]', assetInsurancePolicyFields);
        const assetLabel = [assetForm.elements.nombre_descripcion?.value, assetForm.elements.identificador?.value]
            .filter(Boolean)
            .join(' / ');
        if (!rows.some((row) => row.alcance_poliza === 'Matriz/global')) {
            rows.push({
                ano: String(new Date().getFullYear()),
                ramo: joinInsuranceSelection(selectedInsuranceProductsFromForm(assetForm)),
                valor_asegurado_total: insuranceRequestedTotal(assetForm) > 0 ? String(Math.round(insuranceRequestedTotal(assetForm))) : '',
                alcance_poliza: 'Matriz/global',
                grupo_poliza: assetLabel || 'Poliza matriz del edificio o grupo de unidades',
                metodo_distribucion: 'Coeficiente',
                estado: 'Vigente',
                observaciones_distribucion: 'Poliza global que cubre varias unidades. Diligenciar coeficiente PH, unidades cubiertas o criterio manual.',
                observaciones: 'Registrar una sola vez la poliza matriz y asignar a este activo solo la prima proporcional.',
            });
        }
        assetForm.dataset.assetInsuranceTab = 'matriz';
        updateAssetInsuranceSections(assetForm, assetForm.elements.tipo_activo?.value || '');
        renderAssetInsurancePolicyRows(assetForm, rows);
        renderAssetInsuranceMatrixSummary(assetForm);
        renderAssetInsuranceHistory(assetForm);
        saveAssetDraft(assetForm);
    }));

    assetForm.querySelector('[data-asset-insurance-matrix-summary]')?.addEventListener('input', (event) => {
        const target = event.target;
        if (target instanceof HTMLInputElement && target.matches('[data-matrix-policy-field]')) {
            syncMatrixPolicyProxy(assetForm, target);
            renderAssetCurrentPolicy(assetForm);
            renderAssetInsuranceHistory(assetForm);
            saveAssetDraft(assetForm);
        }
    });

    assetForm.querySelector('[data-asset-insurance-matrix-summary]')?.addEventListener('change', (event) => {
        const target = event.target;
        if ((target instanceof HTMLInputElement || target instanceof HTMLSelectElement) && target.matches('[data-matrix-policy-field]')) {
            syncMatrixPolicyProxy(assetForm, target);
            renderAssetInsuranceMatrixSummary(assetForm);
            renderAssetCurrentPolicy(assetForm);
            renderAssetInsuranceHistory(assetForm);
            saveAssetDraft(assetForm);
        }
    });

    assetForm.querySelector('[data-asset-insurance-current-policy-rows]')?.addEventListener('input', (event) => {
        const target = event.target;
        if (!(target instanceof HTMLInputElement) || !target.matches('[data-insurance-request-field]')) {
            return;
        }
        syncInsuranceRequestField(assetForm, target);
        renderAssetInsurancePolicyRows(assetForm, historyRowsForType(assetForm, '[data-asset-insurance-policy-row]', assetInsurancePolicyFields));
        renderInsuranceQuoteAnalysis(assetForm);
        renderAssetCurrentPolicy(assetForm);
        renderAssetInsuranceHistory(assetForm);
        saveAssetDraft(assetForm);
    });
    assetForm.querySelector('[data-asset-insurance-current-policy-rows]')?.addEventListener('change', (event) => {
        const target = event.target;
        if (!((target instanceof HTMLInputElement || target instanceof HTMLSelectElement) && target.matches('[data-insurance-request-field]'))) {
            return;
        }
        syncInsuranceRequestField(assetForm, target);
        renderAssetInsurancePolicyRows(assetForm, historyRowsForType(assetForm, '[data-asset-insurance-policy-row]', assetInsurancePolicyFields));
        renderInsuranceQuoteAnalysis(assetForm);
        renderAssetCurrentPolicy(assetForm);
        renderAssetInsuranceHistory(assetForm);
        saveAssetDraft(assetForm);
    });
    assetForm.querySelector('[data-asset-insurance-current-policy-rows]')?.addEventListener('click', (event) => {
        const target = event.target;
        const productTab = target instanceof Element ? target.closest('[data-asset-insurance-product-tab="current"]') : null;
        if (!(productTab instanceof HTMLButtonElement)) {
            return;
        }
        assetForm.dataset.assetCurrentActiveProduct = productTab.dataset.product || '';
        renderAssetInsuranceCurrentPolicyRows(assetForm);
        saveAssetDraft(assetForm);
    });

    assetForm.querySelectorAll('[data-add-asset-insurance-previous]').forEach((previousButton) => previousButton.addEventListener('click', () => {
        const rows = historyRowsForType(assetForm, '[data-asset-insurance-policy-row]', assetInsurancePolicyFields);
        rows.push({
            ano: String(new Date().getFullYear() - 1),
            ramo: joinInsuranceSelection(selectedInsuranceProductsFromForm(assetForm)),
            alcance_poliza: 'Individual',
            metodo_distribucion: 'Individual',
            estado: 'Vencida',
            observaciones: 'Poliza de vigencia anterior registrada como evidencia historica. No reemplaza la poliza vigente.',
        });
        renderAssetInsurancePolicyRows(assetForm, rows);
        renderAssetInsuranceCurrentPolicyRows(assetForm);
        renderAssetInsuranceHistory(assetForm);
        saveAssetDraft(assetForm);
    }));

    assetForm.querySelector('[data-asset-insurance-policy-rows]')?.addEventListener('click', (event) => {
        const target = event.target;
        if (!(target instanceof Element) || !target.closest('[data-remove-asset-insurance-policy]')) {
            const printButton = target instanceof Element ? target.closest('[data-print-asset-insurance-quote]') : null;
            if (printButton instanceof HTMLElement) {
                const card = printButton.closest('[data-asset-insurance-policy-row]');
                syncInsuranceQuoteMatrix(card);
                const fields = {};
                assetInsurancePolicyFields.forEach((field) => {
                    fields[field] = card?.querySelector(`[name$="[${field}]"]`)?.value ?? '';
                });
                printInsuranceQuoteRequest(assetForm, fields);
                return;
            }
            const adoptButton = target instanceof Element ? target.closest('[data-adopt-asset-insurance-policy]') : null;
            if (adoptButton instanceof HTMLElement) {
                syncAllInsuranceQuoteMatrices(assetForm);
                let rows = historyRowsForType(assetForm, '[data-asset-insurance-policy-row]', assetInsurancePolicyFields);
                const card = adoptButton.closest('[data-asset-insurance-policy-row]');
                const index = [...assetForm.querySelectorAll('[data-asset-insurance-policy-row]')].indexOf(card);
                const today = new Date().toISOString().slice(0, 10);
                const selectedPolicy = rows[index] || {};
                const preventiveRenewal = selectedPolicy.fecha_renovacion || assetShiftDate(selectedPolicy.fecha_fin, { months: -1 });
                const adoptedDraft = {
                    ...selectedPolicy,
                    fecha_renovacion: preventiveRenewal || selectedPolicy.fecha_renovacion || '',
                };
                const adoptedCoverageTotal = applyAdoptedPolicyToCoverages(assetForm, adoptedDraft);
                rows = rows.map((row, rowIndex) => ({
                    ...row,
                    adoptada: rowIndex === index ? 'Si' : '',
                    fecha_adopcion: rowIndex === index ? (row.fecha_adopcion || today) : '',
                    fecha_renovacion: rowIndex === index ? (preventiveRenewal || row.fecha_renovacion) : row.fecha_renovacion,
                    valor_asegurado_total: rowIndex === index && adoptedCoverageTotal > 0 ? String(Math.round(adoptedCoverageTotal)) : row.valor_asegurado_total,
                    estado: rowIndex === index ? 'Vigente' : (row.estado === 'Vigente' ? 'En renovacion' : row.estado),
                    criterio_adopcion: rowIndex === index ? (row.criterio_adopcion || 'Mejor equilibrio entre cobertura, deducible, sublimites, exclusiones y prima.') : row.criterio_adopcion,
                }));
                renderAssetInsurancePolicyRows(assetForm, rows);
                assetForm.dataset.assetInsuranceTab = 'vigente';
                updateAssetInsuranceSections(assetForm, assetForm.elements.tipo_activo?.value || '');
                renderAssetCurrentPolicy(assetForm);
                renderAssetInsuranceMatrixSummary(assetForm);
                renderAssetInsuranceHistory(assetForm);
                saveAssetDraft(assetForm);
                return;
            }
            return;
        }
        target.closest('[data-asset-insurance-policy-row]')?.remove();
        renderAssetInsurancePolicyRows(assetForm, historyRowsForType(assetForm, '[data-asset-insurance-policy-row]', assetInsurancePolicyFields));
        renderAssetCurrentPolicy(assetForm);
        renderAssetInsuranceMatrixSummary(assetForm);
        renderAssetInsuranceHistory(assetForm);
        saveAssetDraft(assetForm);
    });
    assetForm.querySelector('[data-asset-insurance-policy-rows]')?.addEventListener('change', (event) => {
        const target = event.target;
        if (target instanceof HTMLInputElement && target.matches('[data-quote-field]')) {
            syncAllInsuranceQuoteMatrices(assetForm);
            renderInsuranceQuoteAnalysis(assetForm);
            renderAssetCurrentPolicy(assetForm);
            renderAssetInsuranceMatrixSummary(assetForm);
            renderAssetInsuranceHistory(assetForm);
            saveAssetDraft(assetForm);
            return;
        }
        if (target instanceof HTMLInputElement && (target.matches('[name$="[prima_total]"]') || target.matches('[data-policy-allocation-field]'))) {
            updatePolicyAllocationRow(target.closest('[data-asset-insurance-policy-row]'));
            renderAssetCurrentPolicy(assetForm);
            renderAssetInsuranceMatrixSummary(assetForm);
            renderAssetInsuranceHistory(assetForm);
            saveAssetDraft(assetForm);
            return;
        }
        if (target instanceof HTMLInputElement && target.matches('[data-asset-policy-ramo-toggle]')) {
            target.closest('.asset-coverage-chip')?.classList.toggle('is-selected', target.checked);
            updatePolicyRamoSelection(target.closest('[data-asset-insurance-policy-row]'));
            renderAssetInsuranceCoverageRows(assetForm, historyRowsForType(assetForm, '[data-asset-insurance-coverage-row]', assetInsuranceCoverageFields));
            renderAssetCurrentPolicy(assetForm);
            renderAssetInsuranceMatrixSummary(assetForm);
            renderAssetInsuranceHistory(assetForm);
            saveAssetDraft(assetForm);
            return;
        }
        if (target instanceof HTMLSelectElement) {
            if (target.matches('[data-policy-allocation-field]')) {
                updatePolicyAllocationRow(target.closest('[data-asset-insurance-policy-row]'));
            }
            renderAssetCurrentPolicy(assetForm);
            renderAssetInsuranceMatrixSummary(assetForm);
            renderAssetInsuranceHistory(assetForm);
            saveAssetDraft(assetForm);
        }
    });
    assetForm.querySelector('[data-asset-insurance-policy-rows]')?.addEventListener('input', (event) => {
        const target = event.target;
        if (target instanceof HTMLInputElement && target.matches('[data-quote-field]')) {
            syncAllInsuranceQuoteMatrices(assetForm);
            renderInsuranceQuoteAnalysis(assetForm);
        }
        if (target instanceof HTMLInputElement && (target.matches('[name$="[prima_total]"]') || target.matches('[data-policy-allocation-field]'))) {
            updatePolicyAllocationRow(target.closest('[data-asset-insurance-policy-row]'));
        }
        renderAssetCurrentPolicy(assetForm);
        renderAssetInsuranceMatrixSummary(assetForm);
        renderAssetInsuranceHistory(assetForm);
        saveAssetDraft(assetForm);
    });

    assetForm.addEventListener('blur', (event) => {
        const target = event.target;
        if (target instanceof HTMLInputElement && target.matches('[data-money-format]')) {
            formatAssetMoneyInput(target);
            saveAssetDraft(assetForm);
            if (target.matches('[data-quote-field]')) {
                syncAllInsuranceQuoteMatrices(assetForm);
                renderInsuranceQuoteAnalysis(assetForm);
                saveAssetDraft(assetForm);
            }
            if (target.matches('[data-insurance-request-field]')) {
                syncInsuranceRequestField(assetForm, target);
                renderAssetInsurancePolicyRows(assetForm, historyRowsForType(assetForm, '[data-asset-insurance-policy-row]', assetInsurancePolicyFields));
                renderInsuranceQuoteAnalysis(assetForm);
                saveAssetDraft(assetForm);
            }
        }
    }, true);

    assetForm.querySelector('[data-add-asset-insurance-coverage]')?.addEventListener('click', () => {
        const rows = historyRowsForType(assetForm, '[data-asset-insurance-coverage-row]', assetInsuranceCoverageFields);
        const policyIndex = Number(assetForm.querySelector('[data-asset-coverage-policy-source]')?.value || 0);
        const policies = assetFormRows(assetForm, '[data-asset-insurance-policy-row]', ['numero_poliza', 'ramo', 'fecha_inicio', 'fecha_fin', 'fecha_renovacion']);
        const policy = policies[Number.isFinite(policyIndex) ? policyIndex : 0] || {};
        const selectedRiskProfile = policy.ramo || selectedInsuranceProductsFromForm(assetForm)[0] || '';
        rows.push({
            ano: String(new Date().getFullYear()),
            numero_poliza: policy.numero_poliza || '',
            ramo: selectedRiskProfile,
            riesgo_cubierto: 'Por definir',
            fecha_inicio: policy.fecha_inicio || '',
            fecha_fin: policy.fecha_fin || '',
            fecha_renovacion: policy.fecha_renovacion || '',
        });
        renderAssetInsuranceCoverageRows(assetForm, rows);
        renderAssetCurrentPolicy(assetForm);
        renderAssetInsuranceHistory(assetForm);
        saveAssetDraft(assetForm);
    });

    assetForm.querySelector('[data-asset-insurance-coverage-rows]')?.addEventListener('change', (event) => {
        const target = event.target;
        if (target instanceof HTMLSelectElement && target.matches('[data-asset-coverage-policy-source]')) {
            assetForm.dataset.assetCoveragePolicyIndex = target.value || '0';
            renderAssetInsuranceCoverageRows(assetForm, historyRowsForType(assetForm, '[data-asset-insurance-coverage-row]', assetInsuranceCoverageFields));
            renderAssetInsuranceCurrentPolicyRows(assetForm);
            renderAssetInsuranceAssetExposureRows(assetForm);
            return;
        }
        if (!(target instanceof HTMLInputElement) || !target.matches('[data-asset-coverage-toggle]')) {
            if (target instanceof HTMLInputElement && target.matches('[data-asset-insurable-toggle]')) {
                const option = insuranceAssetOptionByKey(target.value);
                if (option) {
                    if (target.checked) {
                        upsertInsuredAssetFromOption(assetForm, option);
                    } else {
                        removeInsuredAssetByOption(assetForm, option);
                    }
                }
                target.closest('.asset-coverage-chip')?.classList.toggle('is-selected', target.checked);
                updateInsuranceProductVisibility(assetForm);
                renderAssetInsuranceCoverageRows(assetForm, historyRowsForType(assetForm, '[data-asset-insurance-coverage-row]', assetInsuranceCoverageFields));
                renderAssetInsuranceEquipmentRows(assetForm, assetInsuranceEquipmentRows(assetForm));
                renderAssetInsurancePolicyRows(assetForm, historyRowsForType(assetForm, '[data-asset-insurance-policy-row]', assetInsurancePolicyFields));
                renderInsuranceQuoteAnalysis(assetForm);
                renderAssetInsuranceHistory(assetForm);
                saveAssetDraft(assetForm);
            }
            return;
        }
        const parsedCoverage = parseCoverageToggleValue(target.value);
        const coverage = parsedCoverage.coverage;
        const product = parsedCoverage.product;
        let rows = historyRowsForType(assetForm, '[data-asset-insurance-coverage-row]', assetInsuranceCoverageFields);
        if (target.checked) {
            const targetKey = normalizedCoverageRowKey({ ramo: product, cobertura: coverage });
            if (!rows.some((row) => product ? normalizedCoverageRowKey(row) === targetKey : normalizeInsuranceText(row.cobertura) === normalizeInsuranceText(coverage))) {
                const suggestion = suggestCoverageValue(assetForm, coverage);
                const policyIndex = Number(assetForm.querySelector('[data-asset-coverage-policy-source]')?.value || 0);
                const policies = assetFormRows(assetForm, '[data-asset-insurance-policy-row]', ['numero_poliza', 'ramo', 'fecha_inicio', 'fecha_fin', 'fecha_renovacion']);
                const policy = policies[Number.isFinite(policyIndex) ? policyIndex : 0] || {};
                rows.push({
                    ano: String(new Date().getFullYear()),
                    cobertura: coverage,
                    riesgo_cubierto: coverage,
                    valor_asegurado: suggestion.value > 0 ? String(Math.round(suggestion.value)) : '',
                    fuente_valor_asegurado: suggestion.source || '',
                    numero_poliza: policy.numero_poliza || '',
                    ramo: product || policy.ramo || '',
                    fecha_inicio: policy.fecha_inicio || '',
                    fecha_fin: policy.fecha_fin || '',
                    fecha_renovacion: policy.fecha_renovacion || '',
                    limite_evento: '',
                    sublimite: '',
                    indice_variable: '',
                    deducible: '',
                    observaciones: suggestion.source ? `Valor sugerido segun relacion de bienes a reposicion. Fuente: ${suggestion.source}` : 'Definir valor o limite con soporte antes de cotizar.',
                });
            }
        } else {
            const targetKey = normalizedCoverageRowKey({ ramo: product, cobertura: coverage });
            rows = rows.filter((row) => product ? normalizedCoverageRowKey(row) !== targetKey : normalizeInsuranceText(row.cobertura) !== normalizeInsuranceText(coverage));
        }
        renderAssetInsuranceCoverageRows(assetForm, rows);
        renderAssetInsuranceCurrentPolicyRows(assetForm);
        renderAssetInsuranceAssetExposureRows(assetForm);
        renderAssetInsuranceEquipmentRows(assetForm, assetInsuranceEquipmentRows(assetForm));
        renderAssetInsurancePolicyRows(assetForm, historyRowsForType(assetForm, '[data-asset-insurance-policy-row]', assetInsurancePolicyFields));
        renderInsuranceQuoteAnalysis(assetForm);
        renderAssetInsuranceHistory(assetForm);
        saveAssetDraft(assetForm);
    });

    assetForm.querySelector('[data-asset-insurance-coverage-rows]')?.addEventListener('input', (event) => {
        const target = event.target;
        if (!(target instanceof HTMLInputElement) || !target.name.match(/\[(valor_asegurado|tasa)\]$/)) {
            return;
        }
        syncCoveragePremiumRow(target.closest('[data-asset-insurance-coverage-row]'));
        renderAssetInsuranceCurrentPolicyRows(assetForm);
        renderAssetCurrentPolicy(assetForm);
        renderAssetInsuranceHistory(assetForm);
        saveAssetDraft(assetForm);
    });

    assetForm.querySelector('[data-asset-insurance-coverage-rows]')?.addEventListener('click', (event) => {
        const target = event.target;
        const productTab = target instanceof Element ? target.closest('[data-asset-insurance-product-tab="coverage"]') : null;
        if (productTab instanceof HTMLButtonElement) {
            assetForm.dataset.assetCoverageActiveProduct = productTab.dataset.product || '';
            renderAssetInsuranceCoverageRows(assetForm, historyRowsForType(assetForm, '[data-asset-insurance-coverage-row]', assetInsuranceCoverageFields));
            saveAssetDraft(assetForm);
            return;
        }
        if (!(target instanceof Element) || !target.closest('[data-remove-asset-insurance-coverage]')) {
            return;
        }
        target.closest('[data-asset-insurance-coverage-row]')?.remove();
        const rows = historyRowsForType(assetForm, '[data-asset-insurance-coverage-row]', assetInsuranceCoverageFields);
        renderAssetInsuranceCoverageRows(assetForm, rows);
        renderAssetInsuranceCurrentPolicyRows(assetForm);
        renderAssetInsuranceAssetExposureRows(assetForm);
        renderAssetCurrentPolicy(assetForm);
        renderAssetInsuranceHistory(assetForm);
        saveAssetDraft(assetForm);
    });

    assetForm.querySelector('[data-asset-insurance-assets-rows]')?.addEventListener('click', (event) => {
        const target = event.target;
        const productTab = target instanceof Element ? target.closest('[data-asset-insurance-product-tab="assets"]') : null;
        if (!(productTab instanceof HTMLButtonElement)) {
            return;
        }
        assetForm.dataset.assetAssetsActiveProduct = productTab.dataset.product || '';
        renderAssetInsuranceAssetExposureRows(assetForm);
        saveAssetDraft(assetForm);
    });

    assetForm.querySelector('[data-add-asset-insurance-movement]')?.addEventListener('click', () => {
        const rows = historyRowsForType(assetForm, '[data-asset-insurance-movement-row]', assetInsuranceMovementFields);
        const policy = assetFormRows(assetForm, '[data-asset-insurance-policy-row]', ['numero_poliza', 'ramo', 'adoptada', 'estado']).find((row) => row.adoptada === 'Si' || row.estado === 'Vigente') || {};
        rows.push({
            ano: String(new Date().getFullYear()),
            fecha: new Date().toISOString().slice(0, 10),
            numero_poliza: policy.numero_poliza || '',
            ramo: policy.ramo || '',
            estado_reporte: 'Pendiente de reportar',
        });
        renderAssetInsuranceMovementRows(assetForm, rows);
        renderAssetInsuranceHistory(assetForm);
        saveAssetDraft(assetForm);
    });

    assetForm.querySelector('[data-asset-insurance-movement-rows]')?.addEventListener('input', () => {
        renderAssetInsuranceHistory(assetForm);
        saveAssetDraft(assetForm);
    });

    assetForm.querySelector('[data-asset-insurance-movement-rows]')?.addEventListener('change', () => {
        renderAssetInsuranceHistory(assetForm);
        saveAssetDraft(assetForm);
    });

    assetForm.querySelector('[data-asset-insurance-movement-rows]')?.addEventListener('click', (event) => {
        const target = event.target;
        if (!(target instanceof Element) || !target.closest('[data-remove-asset-insurance-movement]')) {
            return;
        }
        target.closest('[data-asset-insurance-movement-row]')?.remove();
        renderAssetInsuranceMovementRows(assetForm, historyRowsForType(assetForm, '[data-asset-insurance-movement-row]', assetInsuranceMovementFields));
        renderAssetInsuranceHistory(assetForm);
        saveAssetDraft(assetForm);
    });

    assetForm.querySelector('[data-add-asset-insurance-equipment]')?.addEventListener('click', () => {
        const rows = assetInsuranceEquipmentRows(assetForm);
        const selectedProducts = selectedInsuranceProductsFromForm(assetForm);
        const activeProduct = activeInsuranceProduct(assetForm, selectedProducts, 'assetValueActiveProduct');
        const selectedCoverageRows = historyRowsForType(assetForm, '[data-asset-insurance-coverage-row]', assetInsuranceCoverageFields)
            .filter((item) => (item.ramo || '') === activeProduct || (!activeProduct && item.ramo === ''));
        const coverage = selectedCoverageRows[0] || {};
        const category = assetForm.dataset.assetInsuranceItemCategory || coverageSupportCategories(selectedCoverageRows)[0] || '';
        rows.push({
            ...buildInsuranceEquipmentRow(assetForm, category, activeProduct || coverage.ramo || ''),
            cobertura_asociada: [coverage.ramo, coverage.cobertura].filter(Boolean).join(' / '),
        });
        renderAssetInsuranceEquipmentRows(assetForm, rows);
        renderAssetInsuranceHistory(assetForm);
        saveAssetDraft(assetForm);
    });

    const insuranceEquipmentRows = assetForm.querySelector('[data-asset-insurance-equipment-rows]');
    insuranceEquipmentRows?.addEventListener('input', (event) => {
        const target = event.target;
        if (target instanceof HTMLInputElement && target.matches('[data-insurance-request-field]')) {
            syncInsuranceRequestField(assetForm, target);
            updateInsuranceDerivedSummary(assetForm);
            renderAssetInsuranceCurrentPolicyRows(assetForm);
            renderAssetInsurancePolicyRows(assetForm, historyRowsForType(assetForm, '[data-asset-insurance-policy-row]', assetInsurancePolicyFields));
            renderInsuranceQuoteAnalysis(assetForm);
            renderAssetInsuranceHistory(assetForm);
            saveAssetDraft(assetForm);
            return;
        }
        if (!(target instanceof Element) || !target.matches('[data-insured-item-calc], [data-insured-item-total]')) {
            return;
        }
        const row = target.closest('[data-asset-insurance-equipment-row]');
        if (row instanceof HTMLElement) {
            if (target instanceof HTMLInputElement && target.name.endsWith('[categoria_item]')) {
                const tooltip = insurableAssetTooltip(target.value);
                target.title = tooltip;
                target.closest('label')?.setAttribute('title', tooltip);
            }
            updateInsuredItemRow(row);
            refreshCoverageSuggestedValues(assetForm);
            updateInsuranceItemTotalsDisplay(assetForm);
            updateInsuranceDerivedSummary(assetForm);
            renderAssetInsuranceCurrentPolicyRows(assetForm);
            renderAssetInsurancePolicyRows(assetForm, historyRowsForType(assetForm, '[data-asset-insurance-policy-row]', assetInsurancePolicyFields));
            renderInsuranceQuoteAnalysis(assetForm);
            renderAssetInsuranceHistory(assetForm);
            saveAssetDraft(assetForm);
        }
    });
    insuranceEquipmentRows?.addEventListener('change', (event) => {
        const target = event.target;
        if (!(target instanceof Element)) {
            return;
        }
        const row = target.closest('[data-asset-insurance-equipment-row]');
        if (row instanceof HTMLElement) {
            if (target instanceof HTMLInputElement && target.name.endsWith('[categoria_item]')) {
                const tooltip = insurableAssetTooltip(target.value);
                target.title = tooltip;
                target.closest('label')?.setAttribute('title', tooltip);
            }
            updateInsuredItemRow(row);
            refreshCoverageSuggestedValues(assetForm);
            updateInsuranceItemTotalsDisplay(assetForm);
            updateInsuranceDerivedSummary(assetForm);
            renderAssetInsuranceCurrentPolicyRows(assetForm);
            renderAssetInsurancePolicyRows(assetForm, historyRowsForType(assetForm, '[data-asset-insurance-policy-row]', assetInsurancePolicyFields));
            renderInsuranceQuoteAnalysis(assetForm);
            renderAssetInsuranceHistory(assetForm);
            saveAssetDraft(assetForm);
        }
    });
    insuranceEquipmentRows?.addEventListener('click', (event) => {
        const target = event.target;
        const productTab = target instanceof Element ? target.closest('[data-asset-insurance-product-tab="values"]') : null;
        if (productTab instanceof HTMLButtonElement) {
            assetForm.dataset.assetValueActiveProduct = productTab.dataset.product || '';
            renderAssetInsuranceEquipmentRows(assetForm, assetInsuranceEquipmentRows(assetForm));
            saveAssetDraft(assetForm);
            return;
        }
        const categoryButton = target instanceof Element ? target.closest('[data-filter-asset-insurance-equipment-category]') : null;
        if (categoryButton instanceof HTMLButtonElement) {
            assetForm.dataset.assetInsuranceItemCategory = categoryButton.dataset.filterAssetInsuranceEquipmentCategory || '';
            renderAssetInsuranceEquipmentRows(assetForm, assetInsuranceEquipmentRows(assetForm));
            saveAssetDraft(assetForm);
            return;
        }
        if (!(target instanceof Element) || !target.closest('[data-remove-asset-insurance-equipment]')) {
            return;
        }
        target.closest('[data-asset-insurance-equipment-row]')?.remove();
        const rows = assetInsuranceEquipmentRows(assetForm);
        renderAssetInsuranceEquipmentRows(assetForm, rows);
        renderAssetInsuranceHistory(assetForm);
        saveAssetDraft(assetForm);
    });

    assetForm.querySelector('[data-asset-participation-rows]')?.addEventListener('change', (event) => {
        const target = event.target;
        if (!(target instanceof Element)) {
            return;
        }
        if (target.matches('[data-asset-participation-type]')) {
            const rows = [...assetForm.querySelectorAll('[data-asset-participation-row]')].map((row) => ({
                tipo_participante: row.querySelector('[name$="[tipo_participante]"]')?.value ?? '',
                participante: row.querySelector('[name$="[participante]"]')?.value ?? '',
                calidad: row.querySelector('[name$="[calidad]"]')?.value ?? '',
                porcentaje: row.querySelector('[name$="[porcentaje]"]')?.value ?? '',
                soporte_observaciones: row.querySelector('[name$="[soporte_observaciones]"]')?.value ?? '',
            }));
            renderAssetParticipationRows(assetForm, rows);
            saveAssetDraft(assetForm);
        }
    });

    assetForm.querySelector('[data-asset-participation-rows]')?.addEventListener('click', (event) => {
        const target = event.target;
        if (!(target instanceof Element) || !target.closest('[data-remove-asset-participation]')) {
            return;
        }
        const row = target.closest('[data-asset-participation-row]');
        row?.remove();
        const rows = [...assetForm.querySelectorAll('[data-asset-participation-row]')].map((item) => ({
            tipo_participante: item.querySelector('[name$="[tipo_participante]"]')?.value ?? '',
            participante: item.querySelector('[name$="[participante]"]')?.value ?? '',
            calidad: item.querySelector('[name$="[calidad]"]')?.value ?? '',
            porcentaje: item.querySelector('[name$="[porcentaje]"]')?.value ?? '',
            soporte_observaciones: item.querySelector('[name$="[soporte_observaciones]"]')?.value ?? '',
        }));
        renderAssetParticipationRows(assetForm, rows);
        saveAssetDraft(assetForm);
    });

    const openAssetModal = (row = null, trigger = null) => {
        lastAssetTrigger = trigger;
        assetForm.dataset.assetInsuranceTab = 'actual';
        assetForm.dataset.assetCoverageActiveProduct = '';
        assetForm.dataset.assetAssetsActiveProduct = '';
        assetForm.dataset.assetValueActiveProduct = '';
        const restoredDraft = fillAssetForm(assetForm, row, { restoreDraft: true });
        if (modalTitle) {
            modalTitle.textContent = row ? 'Editar activo' : 'Nuevo activo';
        }
        if (modal instanceof HTMLElement) {
            modal.hidden = false;
            document.body.classList.add('modal-open');
        }
        status && (status.textContent = restoredDraft ? 'Borrador recuperado de este navegador.' : '');
        window.setTimeout(() => assetForm.elements.tipo_activo?.focus(), 0);
    };

    const closeAssetModal = ({ force = false } = {}) => {
        if (!force && assetDirty && !window.confirm('Hay cambios sin guardar. Se conservara un borrador en este navegador. Deseas cerrar el formulario?')) {
            return;
        }
        if (assetDirty) {
            saveAssetDraft(assetForm);
        }
        if (modal instanceof HTMLElement) {
            modal.hidden = true;
            document.body.classList.remove('modal-open');
        }
        fillAssetForm(assetForm);
        status && (status.textContent = '');
        assetDirty = false;
        if (lastAssetTrigger instanceof HTMLElement) {
            lastAssetTrigger.focus();
        }
    };

    assetForm.addEventListener('submit', async (event) => {
        event.preventDefault();
        if (!(tableBody instanceof HTMLElement)) {
            return;
        }
        const id = assetForm.elements.id.value;
        const url = id ? `${basePath}/protocolo-familiar/patrimonio/${id}` : `${basePath}/protocolo-familiar/patrimonio`;
        syncInmuebleCommonFields(assetForm);
        syncInmuebleEconomics(assetForm);
        syncIntangibleCommonFields(assetForm);
        status && (status.textContent = 'Guardando...');
        try {
            const payload = await assetFetchJson(url, { method: 'POST', body: new FormData(assetForm) });
            if (payload.row?.codigo) {
                assetDocuments[payload.row.codigo] = payload.documents || [];
                assetForm.dataset.assetDocuments = JSON.stringify(assetDocuments);
                renderAssetModalDocuments(assetForm, payload.row, assetDocuments, basePath);
            }
            const current = tableBody.querySelector(`[data-asset-row="${payload.row.id}"]`);
            upsertAssetRow(payload.row);
            syncAssetRenewalItem(payload.row);
            if (!current) {
                count && (count.textContent = String(Number.parseInt(count.textContent || '0', 10) + 1));
            }
            renderAssetSummary(payload.summary || {});
            renderAssetCategoryDashboard(payload.summaryByType || []);
            clearAssetDraft(assetForm, id || 'nuevo');
            status && (status.textContent = 'Guardado.');
            assetDirty = false;
            closeAssetModal({ force: true });
        } catch (error) {
            status && (status.textContent = error instanceof Error ? error.message : 'No fue posible guardar.');
        }
    });

    document.querySelector('[data-new-asset]')?.addEventListener('click', (event) => {
        openAssetModal(null, event.currentTarget instanceof HTMLElement ? event.currentTarget : null);
    });

    document.querySelector('[data-cancel-asset]')?.addEventListener('click', () => {
        clearAssetDraft(assetForm, assetForm.elements.id.value || 'nuevo');
        fillAssetForm(assetForm);
        status && (status.textContent = '');
        assetDirty = false;
    });

    document.querySelector('[data-close-asset]')?.addEventListener('click', closeAssetModal);

    modal?.addEventListener('click', (event) => {
        if (event.target === modal) {
            event.preventDefault();
        }
    });

    tableBody?.addEventListener('click', async (event) => {
        const target = event.target;
        if (!(target instanceof Element)) {
            return;
        }
        const tr = target.closest('[data-asset-row]');
        if (!(tr instanceof HTMLElement)) {
            return;
        }
        const row = JSON.parse(tr.dataset.row || '{}');
        if (target.closest('[data-edit-asset]')) {
            openAssetModal(row, target.closest('[data-edit-asset]'));
            return;
        }
        if (target.closest('[data-delete-asset]')) {
            if (!window.confirm('Desactivar este activo patrimonial?')) {
                return;
            }
            const data = new FormData();
            data.append('csrf_token', workspace?.dataset.csrf ?? '');
            try {
                const payload = await assetFetchJson(`${basePath}/protocolo-familiar/patrimonio/${row.id}/eliminar`, { method: 'POST', body: data });
                tr.remove();
                removeAssetRenewalItem(row.id);
                updateAssetTypeCounts();
                applyAssetSearch();
                renderAssetSummary(payload.summary || {});
                renderAssetCategoryDashboard(payload.summaryByType || []);
                count && (count.textContent = String(Math.max(0, Number.parseInt(count.textContent || '0', 10) - 1)));
                status && (status.textContent = 'Registro desactivado.');
            } catch (error) {
                status && (status.textContent = error instanceof Error ? error.message : 'No fue posible desactivar el activo.');
            }
        }
    });

    document.querySelector('[data-renewal-range]')?.addEventListener('change', renderAssetRenewalCalendar);

    document.querySelector('[data-asset-renewal-calendar]')?.addEventListener('click', (event) => {
        const target = event.target;
        if (!(target instanceof Element)) {
            return;
        }
        const tab = target.closest('[data-renewal-tab]');
        if (!(tab instanceof HTMLButtonElement)) {
            return;
        }
        const value = tab.dataset.renewalTab || '';
        const calendar = tab.closest('[data-asset-renewal-calendar]');
        calendar?.querySelectorAll('[data-renewal-tab]').forEach((item) => {
            if (item instanceof HTMLButtonElement) {
                const active = item.dataset.renewalTab === value;
                item.classList.toggle('is-active', active);
                item.setAttribute('aria-selected', active ? 'true' : 'false');
            }
        });
        calendar?.querySelectorAll('[data-renewal-panel]').forEach((panel) => {
            if (panel instanceof HTMLElement) {
                const active = panel.dataset.renewalPanel === value;
                panel.hidden = !active;
                panel.classList.toggle('is-active', active);
            }
        });
        if (value === 'calendar') {
            window.setTimeout(() => {
                const instance = calendar instanceof HTMLElement ? calendar._assetFullCalendar : null;
                instance?.updateSize?.();
            }, 0);
        }
    });

    document.querySelector('[data-asset-renewal-calendar]')?.addEventListener('click', (event) => {
        const target = event.target;
        if (!(target instanceof Element) || !target.closest('[data-edit-renewal]')) {
            return;
        }
        const item = target.closest('[data-renewal-asset-id]');
        const id = item instanceof HTMLElement ? item.dataset.renewalAssetId : '';
        const rowElement = id ? tableBody?.querySelector(`[data-asset-row="${CSS.escape(id)}"]`) : null;
        if (!(rowElement instanceof HTMLElement)) {
            return;
        }
        const row = JSON.parse(rowElement.dataset.row || '{}');
        openAssetModal(row, target.closest('[data-edit-renewal]'));
    });

    modalDocuments?.addEventListener('click', (event) => {
        const target = event.target;
        if (!(target instanceof Element)) {
            return;
        }
        const newButton = target.closest('[data-asset-new-document]');
        if (newButton instanceof HTMLElement) {
            const code = assetForm.elements.codigo?.value || '';
            const name = [assetForm.elements.nombre_descripcion?.value, assetForm.elements.identificador?.value].filter(Boolean).join(' - ') || code;
            document.dispatchEvent(new CustomEvent('protocolo:open-document', {
                detail: {
                    trigger: newButton,
                    template: {
                        sujeto_tipo: 'Activo/Patrimonio',
                        sujeto_codigo: code,
                        sujeto_nombre: name,
                        categoria: 'Patrimonio',
                        documento_tipo: 'Soporte documental del activo',
                        requerido: 'Si',
                        estado: 'Faltante',
                        nivel_riesgo: 'Medio',
                    },
                },
            }));
            return;
        }
        const item = target.closest('[data-asset-doc-row]');
        const deleteButton = target.closest('[data-asset-delete-document]');
        if (deleteButton instanceof HTMLElement && item instanceof HTMLElement) {
            const row = JSON.parse(item.dataset.row || '{}');
            document.dispatchEvent(new CustomEvent('protocolo:delete-document', { detail: { row, trigger: deleteButton } }));
            return;
        }
        const button = target.closest('[data-asset-open-document]');
        if (!(button instanceof HTMLElement) || !(item instanceof HTMLElement)) {
            return;
        }
        const row = JSON.parse(item.dataset.row || '{}');
        document.dispatchEvent(new CustomEvent('protocolo:open-document', { detail: { row, trigger: button } }));
    });

    document.addEventListener('protocolo:document-saved', (event) => {
        const row = event.detail?.row;
        if (!row || row.sujeto_tipo !== 'Activo/Patrimonio') {
            return;
        }
        const docs = assetDocuments[row.sujeto_codigo] || [];
        const index = docs.findIndex((item) => item.id === row.id);
        if (index >= 0) {
            docs[index] = row;
        } else {
            docs.push(row);
        }
        assetDocuments[row.sujeto_codigo] = docs;
        assetForm.dataset.assetDocuments = JSON.stringify(assetDocuments);
        const currentCode = assetForm.elements.codigo?.value || '';
        if (currentCode === row.sujeto_codigo) {
            renderAssetModalDocuments(assetForm, { codigo: row.sujeto_codigo }, assetDocuments, basePath);
        }
    });

    document.addEventListener('protocolo:document-deleted', (event) => {
        const row = event.detail?.row;
        if (!row || row.sujeto_tipo !== 'Activo/Patrimonio') {
            return;
        }
        assetDocuments[row.sujeto_codigo] = (assetDocuments[row.sujeto_codigo] || []).filter((item) => item.id !== row.id);
        assetForm.dataset.assetDocuments = JSON.stringify(assetDocuments);
        const currentCode = assetForm.elements.codigo?.value || '';
        if (currentCode === row.sujeto_codigo) {
            renderAssetModalDocuments(assetForm, { codigo: row.sujeto_codigo }, assetDocuments, basePath);
        }
    });

    renderAssetRenewalCalendar();
    updateAssetTypeCounts();
}
