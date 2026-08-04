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

const assetMoney = (value) => {
    if (value === null || value === undefined || value === '') {
        return '';
    }
    return new Intl.NumberFormat('es-CO', { style: 'currency', currency: 'COP', maximumFractionDigits: 0 }).format(Number(value));
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
        costo: extra.costo || row.detalle?.costos_mantenimiento || row.detalle?.portal_costo_periodico || row.detalle?.firma_costo_periodico || row.detalle?.suscripcion_costo_periodico || row.detalle?.ia_costo_periodico || '',
    });
};

const assetRenewalItemsFromRow = (row) => {
    const detail = row?.detalle || {};
    const items = [];
    assetExpiryFieldMap.forEach(([key, label, category]) => {
        addAssetExpiryItem(items, row, detail[key], label, category);
    });
    const digitalDueDate = detail.vencimiento_fecha || detail.portal_fecha_renovacion || detail.firma_fecha_renovacion || detail.suscripcion_fecha_renovacion || detail.ia_fecha_renovacion || '';
    const digitalSubcategories = new Set(['Paginas web corporativas', 'Portales inmobiliarios', 'Dominios de internet', 'Plataformas de firma electronica', 'Membresias web / SaaS', 'Membresias de inteligencia artificial', 'Licencias tecnologicas', 'APIs e integraciones']);
    if (row?.tipo_activo === 'Activo intangible/tecnologico/PI' && digitalSubcategories.has(detail.subcategoria || '') && !digitalDueDate) {
        addAssetExpiryItem(items, row, '', 'Vencimiento digital sin fecha', 'Digital', { allowMissing: true });
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
        addAssetExpiryItem(items, row, policy.fecha_fin, `Fin vigencia ${name}`, 'Poliza', {
            responsable: policy.intermediario || policy.aseguradora || '',
            costo: policy.prima_total || '',
            estado: policy.estado || '',
        });
        addAssetExpiryItem(items, row, policy.fecha_renovacion, `Renovacion ${name}`, 'Poliza', {
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
    const normalized = normalizeInsuranceRamo(product);
    return insuranceAcademyData(form).catalog.find((row) => insuranceCatalogRamo(row) === normalized) || null;
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
                <p>Sube aqui los soportes marcados como *Control Documental*. Tambien quedaran visibles en 05_Documentos.</p>
            </div>
            <button type="button" data-asset-new-document>Nuevo soporte</button>
            <div class="asset-doc-inline-summary">
                <span><strong>${stats.total}</strong> documentos</span>
                <span class="${stats.pending > 0 ? 'is-danger' : 'is-ok'}"><strong>${stats.pending}</strong> pendientes</span>
                <span><strong>${stats.uploaded}</strong> subidos</span>
            </div>
        </div>
        <div class="asset-document-folders">
            ${Object.entries(grouped).map(([folder, items], index) => `
                <details class="asset-document-folder" ${index < 2 ? 'open' : ''}>
                    <summary><strong>${assetEscape(folder)}</strong><span>${items.length} docs</span></summary>
                    <div class="asset-document-list">
                        ${items.map((item) => `<article class="asset-document-item" data-asset-doc-row="${assetEscape(item.id)}" data-row="${assetEscape(JSON.stringify(item))}" data-state="${assetEscape(item.estado || '')}">${assetDocumentItemHtml(item, basePath)}</article>`).join('')}
                    </div>
                </details>
            `).join('')}
        </div>
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
        seguro_polizas: assetFormRows(form, '[data-asset-insurance-policy-row]', ['ano', 'tipo_documento', 'ramo', 'aseguradora', 'intermediario', 'agencia_expedidora', 'codigo_agencia', 'numero_poliza', 'numero_emision', 'numero_pago_electronico', 'modalidad_facturacion', 'coaseguro', 'tomador', 'asegurado', 'beneficiario', 'direccion_riesgo', 'ciudad_riesgo', 'actividad_riesgo', 'tipo_riesgo', 'fecha_inicio', 'fecha_fin', 'fecha_renovacion', 'prima_neta', 'iva', 'gastos_expedicion', 'prima_total', 'valor_asegurado_total', 'deducible_general', 'forma_pago', 'numero_cuotas', 'clausulado', 'anexos_endosos', 'exclusiones_relevantes', 'texto_aclaratorio', 'asistencias', 'estado', 'adoptada', 'fecha_adopcion', 'criterio_adopcion', 'soporte', 'observaciones']),
        seguro_coberturas: assetFormRows(form, '[data-asset-insurance-coverage-row]', ['ano', 'numero_poliza', 'ramo', 'cobertura', 'riesgo_cubierto', 'valor_asegurado', 'limite_evento', 'porcentaje_invar', 'indice_variable', 'sublimite', 'tasa', 'prima', 'deducible', 'fuente_valor_asegurado', 'fecha_inicio', 'fecha_fin', 'fecha_renovacion', 'observaciones']),
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

const assetCoverageValueRules = {
    Incendio: ['Construccion', 'Vidrios', 'Muebles y enseres', 'Maquinaria y equipo', 'Equipo electronico / corriente debil', 'Mercancias / inventario', 'Obras de arte'],
    Rayo: ['Construccion', 'Vidrios', 'Muebles y enseres', 'Maquinaria y equipo', 'Equipo electronico / corriente debil', 'Mercancias / inventario'],
    Explosion: ['Construccion', 'Vidrios', 'Muebles y enseres', 'Maquinaria y equipo', 'Equipo electronico / corriente debil', 'Mercancias / inventario'],
    Humo: ['Construccion', 'Vidrios', 'Muebles y enseres', 'Maquinaria y equipo', 'Equipo electronico / corriente debil', 'Mercancias / inventario'],
    'Danos por agua': ['Construccion', 'Vidrios', 'Muebles y enseres', 'Maquinaria y equipo', 'Equipo electronico / corriente debil', 'Mercancias / inventario'],
    Anegacion: ['Construccion', 'Vidrios', 'Muebles y enseres', 'Maquinaria y equipo', 'Equipo electronico / corriente debil', 'Mercancias / inventario'],
    Inundacion: ['Construccion', 'Vidrios', 'Muebles y enseres', 'Maquinaria y equipo', 'Equipo electronico / corriente debil', 'Mercancias / inventario'],
    Terremoto: ['Construccion', 'Vidrios', 'Muebles y enseres', 'Maquinaria y equipo', 'Equipo electronico / corriente debil', 'Mercancias / inventario'],
    'Temblor / erupcion volcanica': ['Construccion', 'Vidrios', 'Muebles y enseres', 'Maquinaria y equipo', 'Equipo electronico / corriente debil', 'Mercancias / inventario'],
    'Actos mal intencionados de terceros': ['Construccion', 'Vidrios', 'Muebles y enseres', 'Maquinaria y equipo', 'Equipo electronico / corriente debil', 'Mercancias / inventario'],
    Terrorismo: ['Construccion', 'Vidrios', 'Muebles y enseres', 'Maquinaria y equipo', 'Equipo electronico / corriente debil', 'Mercancias / inventario'],
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
        return ['Construccion', 'Muebles y enseres', 'Maquinaria y equipo', 'Equipo electronico / corriente debil', 'Mercancias / inventario'];
    }
    if (text.includes('incendio') || text.includes('rayo') || text.includes('explosion') || text.includes('humo') || text.includes('agua') || text.includes('inundacion') || text.includes('anegacion')) {
        return ['Construccion', 'Vidrios', 'Muebles y enseres', 'Maquinaria y equipo', 'Equipo electronico / corriente debil', 'Mercancias / inventario'];
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
    if (text.includes('lucro') || text.includes('arrendamiento')) {
        return ['Construccion', 'Mercancias / inventario', 'Maquinaria y equipo', 'Equipo electronico / corriente debil'];
    }
    if (text.includes('perdida') || text.includes('vehiculo') || text.includes('auto')) {
        return ['Vehiculo'];
    }
    return [];
};

const insuranceCoverageProfiles = {
    'Danos materiales inmueble arrendado': ['Incendio', 'Rayo', 'Explosion', 'Humo', 'Danos por agua', 'Anegacion', 'Terremoto', 'Temblor / erupcion volcanica', 'Vidrios', 'Extension de cobertura', 'Actos mal intencionados de terceros', 'Terrorismo', 'Remocion de escombros', 'Gastos de extincion', 'Honorarios profesionales', 'Responsabilidad civil extracontractual', 'Asistencia'],
    'Todo riesgo dano material': ['Incendio', 'Rayo', 'Explosion', 'Humo', 'Danos por agua', 'Anegacion', 'Inundacion', 'Terremoto', 'Temblor / erupcion volcanica', 'Hurto', 'Sustraccion', 'Actos mal intencionados de terceros', 'Terrorismo', 'Rotura de maquinaria', 'Equipo electronico / corriente debil', 'Lucro cesante', 'Remocion de escombros', 'Gastos de extincion', 'Honorarios profesionales', 'Asistencia'],
    'Incendio y terremoto': ['Incendio', 'Rayo', 'Explosion', 'Humo', 'Danos por agua', 'Anegacion', 'Terremoto', 'Temblor / erupcion volcanica', 'Actos mal intencionados de terceros', 'Terrorismo', 'Remocion de escombros', 'Gastos de extincion', 'Lucro cesante', 'Asistencia'],
    Incendio: ['Incendio', 'Rayo', 'Explosion', 'Humo', 'Danos por agua', 'Anegacion', 'Actos mal intencionados de terceros', 'Terrorismo', 'Remocion de escombros', 'Gastos de extincion', 'Lucro cesante', 'Asistencia'],
    Terremoto: ['Terremoto', 'Temblor / erupcion volcanica', 'Anegacion', 'Remocion de escombros'],
    'Muebles y enseres': ['Incendio', 'Rayo', 'Explosion', 'Danos por agua', 'Hurto', 'Sustraccion', 'Actos mal intencionados de terceros', 'Terrorismo'],
    'Rotura de maquinaria': ['Rotura de maquinaria', 'Dano interno', 'Actos mal intencionados de terceros', 'Gastos adicionales'],
    'Maquinaria y equipo': ['Rotura de maquinaria', 'Dano interno', 'Hurto', 'Sustraccion', 'Incendio'],
    'Equipo electronico / corriente debil': ['Equipo electronico / corriente debil', 'Dano interno', 'Hurto', 'Sustraccion', 'Incendio', 'Danos por agua', 'Gastos adicionales'],
    'Responsabilidad civil extracontractual': ['Responsabilidad civil extracontractual', 'RC predios labores y operaciones', 'RC patronal', 'RC cruzada', 'RC parqueaderos', 'Gastos medicos', 'Asistencia juridica'],
    'Responsabilidad civil profesional': ['Responsabilidad civil profesional', 'Gastos medicos', 'Asistencia juridica'],
    'Responsabilidad civil contractual': ['Responsabilidad civil contractual', 'Asistencia juridica'],
    'Automovil todo riesgo': ['Danos propios', 'Perdida parcial por danos', 'Perdida total por danos', 'Perdida parcial por hurto', 'Perdida total por hurto', 'Responsabilidad civil extracontractual', 'Asistencia juridica', 'Asistencia', 'Gastos de grua'],
    SOAT: ['Responsabilidad civil extracontractual'],
    Hogar: ['Incendio', 'Rayo', 'Explosion', 'Danos por agua', 'Terremoto', 'Hurto', 'Sustraccion', 'Responsabilidad civil extracontractual', 'Asistencia'],
    Copropiedad: ['Incendio', 'Rayo', 'Explosion', 'Danos por agua', 'Terremoto', 'Responsabilidad civil extracontractual', 'Actos mal intencionados de terceros', 'Terrorismo', 'Rotura de maquinaria', 'Equipo electronico / corriente debil'],
    Cumplimiento: ['Responsabilidad civil contractual', 'Seriedad de oferta', 'Cumplimiento del contrato', 'Buen manejo del anticipo', 'Calidad del servicio', 'Pago de salarios y prestaciones'],
    'Cumplimiento arrendamiento': ['Responsabilidad civil contractual', 'Canon de arrendamiento', 'Cuotas de administracion', 'Servicios publicos', 'Clausula penal'],
    Manejo: ['Hurto', 'Sustraccion'],
    Deudores: ['Responsabilidad civil contractual'],
    'Cyber / riesgo digital': ['Responsabilidad civil profesional', 'Lucro cesante', 'Asistencia juridica'],
    'Lucro cesante': ['Lucro cesante'],
};

const insuranceCoverageAcademy = {
    Incendio: { kind: 'Dano material', assets: ['Construccion', 'Muebles y enseres', 'Maquinaria y equipo', 'Equipo electronico / corriente debil', 'Mercancias / inventario'], basis: 'Valor de reposicion por bien asegurado, sin incluir terreno.', review: 'Que la ubicacion, uso, construccion y bienes declarados coincidan con la poliza.' },
    Rayo: { kind: 'Dano material', assets: ['Construccion', 'Equipo electronico / corriente debil', 'Maquinaria y equipo'], basis: 'Valor de reposicion del bien afectado.', review: 'Si existe exclusion o condicion de protecciones electricas/pararrayos.' },
    Explosion: { kind: 'Dano material', assets: ['Construccion', 'Muebles y enseres', 'Maquinaria y equipo', 'Mercancias / inventario'], basis: 'Valor de reposicion del edificio y contenidos.', review: 'Actividad del riesgo, sustancias inflamables y exclusiones.' },
    Humo: { kind: 'Dano material', assets: ['Construccion', 'Muebles y enseres', 'Mercancias / inventario'], basis: 'Valor de reposicion de bienes declarados.', review: 'Alcance frente a humo accidental, chimeneas, cocina o actividad comercial.' },
    'Danos por agua': { kind: 'Dano material', assets: ['Construccion', 'Muebles y enseres', 'Maquinaria y equipo', 'Equipo electronico / corriente debil', 'Mercancias / inventario'], basis: 'Valor de reposicion de bienes expuestos al agua.', review: 'Tuberias, cubiertas, mantenimiento, filtraciones y deducible.' },
    Anegacion: { kind: 'Dano material', assets: ['Construccion', 'Muebles y enseres', 'Mercancias / inventario', 'Equipo electronico / corriente debil'], basis: 'Valor de reposicion de bienes ubicados en zonas expuestas.', review: 'Altura del primer piso, drenajes, zonas inundables y exclusiones.' },
    Inundacion: { kind: 'Dano material', assets: ['Construccion', 'Muebles y enseres', 'Mercancias / inventario'], basis: 'Valor de reposicion soportado por avaluo o inventario.', review: 'Mapa de amenaza, antecedentes, obras de mitigacion y sublimites.' },
    Terremoto: { kind: 'Catastrofico', assets: ['Construccion', 'Muebles y enseres', 'Maquinaria y equipo', 'Equipo electronico / corriente debil'], basis: 'Valor asegurable de reposicion, normalmente con deducible especial.', review: 'Deducible por evento, norma sismo resistente, PH y separacion de terreno.' },
    'Temblor / erupcion volcanica': { kind: 'Catastrofico', assets: ['Construccion', 'Muebles y enseres', 'Maquinaria y equipo'], basis: 'Valor de reposicion de bienes declarados.', review: 'Zona geografica, sublimites y definicion del evento.' },
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
        includes: 'Utilidad bruta, gastos permanentes, perdida de ingresos, gastos extra o periodo de indemnizacion segun poliza.',
        assets: 'Actividad economica dependiente del inmueble, maquinaria, equipo o proceso asegurado.',
        value: 'Debe salir de estados financieros, ingresos historicos, canon o utilidad bruta soportada.',
        analyst: 'Confirmar evento disparador, periodo de indemnizacion, base contable y que no haya doble conteo con perdida de arrendamiento.',
        supports: 'Estados financieros, contratos, canon, ingresos historicos, gastos fijos, caratula y clausulado.',
    },
};

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
    return [...new Set(products)].filter((product) => insuranceProductApplies(product, type, selectedCategories, form));
};

const selectedInsuranceCategoriesFromForm = (form) => {
    const selected = new Set();
    form.querySelectorAll('[data-asset-insurable-toggle]:checked').forEach((input) => {
        if (!(input instanceof HTMLInputElement)) {
            return;
        }
        const option = insuranceAssetOptions.find((item) => item.key === input.value);
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

const insuranceProductAcademyHtml = (products = [], form = null) => {
    const selected = products.filter((product) => insuranceProductAcademy[product] || insuranceCatalogForProduct(form, product));
    if (selected.length === 0) {
        return '<div class="asset-insurance-academy-empty">Selecciona uno o varios seguros para ver su resumen tecnico y continuar con las coberturas requeridas.</div>';
    }
    return selected.map((product, index) => {
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
};

const updateInsuranceProductAcademy = (form) => {
    const target = form.querySelector('[data-insurance-product-academy]');
    if (!(target instanceof HTMLElement)) {
        return;
    }
    const products = [...form.querySelectorAll('[data-asset-insurance-type-toggle]:checked')]
        .filter((input) => input instanceof HTMLInputElement && !input.disabled && !input.closest('[data-asset-insurance-product-chip]')?.hidden)
        .map((input) => input.value)
        .filter(Boolean);
    target.innerHTML = insuranceProductAcademyHtml(products, form);
};

const updateInsuranceProductVisibility = (form) => {
    const type = form.elements.tipo_activo?.value || '';
    const categories = selectedInsuranceCategoriesFromForm(form);
    form.querySelectorAll('[data-asset-insurance-product-chip]').forEach((chip) => {
        if (!(chip instanceof HTMLElement)) {
            return;
        }
        const product = chip.dataset.assetInsuranceProductChip || '';
        const visible = insuranceProductApplies(product, type, categories, form);
        chip.hidden = !visible;
        const input = chip.querySelector('input');
        if (input instanceof HTMLInputElement) {
            input.disabled = !visible;
            if (!visible) {
                input.checked = false;
                chip.classList.remove('is-selected');
            }
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

const assetInsuranceCoverageFields = ['ano', 'numero_poliza', 'ramo', 'cobertura', 'riesgo_cubierto', 'valor_asegurado', 'limite_evento', 'porcentaje_invar', 'indice_variable', 'sublimite', 'tasa', 'prima', 'deducible', 'fuente_valor_asegurado', 'fecha_inicio', 'fecha_fin', 'fecha_renovacion', 'observaciones'];

const selectedInsuranceProductsFromForm = (form) => {
    const selected = [...form.querySelectorAll('[data-asset-insurance-type-toggle]:checked')]
        .filter((input) => input instanceof HTMLInputElement && !input.disabled)
        .map((input) => input.value)
        .filter(Boolean);
    const detailValue = form.elements['detalle[tipo_seguro]']?.value || '';
    return [...new Set([...selected, ...splitInsuranceSelection(detailValue)])];
};

const coverageRowKey = (row) => `${row.ramo || ''}::${row.cobertura || ''}`;

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

const insuranceCoverageRequestTableHtml = (rows = []) => {
    if (rows.length === 0) {
        return `
            <div class="asset-insurance-request-empty">
                Primero marca las coberturas requeridas. Despues esta tabla mostrara la base que se enviara a cotizar.
            </div>
        `;
    }
    return `
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
                return `
                    <div class="asset-insurance-request-row" role="row">
                        <strong>${assetEscape(row.cobertura || 'Cobertura por definir')}</strong>
                        <span>${assetEscape(coverageAssetsLabel(row.cobertura || ''))}</span>
                        <label>
                            <input
                                inputmode="decimal"
                                value="${assetEscape(row.valor_asegurado ?? '')}"
                                placeholder="${assetEscape(requestedValue)}"
                                data-insurance-request-field="valor_asegurado"
                                data-product="${assetEscape(row.ramo || '')}"
                                data-coverage="${assetEscape(row.cobertura || '')}"
                            >
                        </label>
                        <label>
                            <input
                                value="${assetEscape(row.fuente_valor_asegurado ?? '')}"
                                placeholder="Acta, criterio familiar, valor solicitado..."
                                data-insurance-request-field="fuente_valor_asegurado"
                                data-product="${assetEscape(row.ramo || '')}"
                                data-coverage="${assetEscape(row.cobertura || '')}"
                            >
                        </label>
                        <label>
                            <input
                                value="${assetEscape(row.observaciones ?? '')}"
                                placeholder="Solicitar limite evento, sublimites, deducible, indice, tasa y prima."
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
        const fromAcademy = academyCoverages
            .filter((row) => row.Ramo === normalized)
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

const insuranceCoverageMatrixHtml = (products = [], selected = new Set(), form = null) => {
    const academyCoverages = form ? insuranceAcademyData(form).coverages : [];
    const selectedProducts = products.filter((product) => {
        const normalized = normalizeInsuranceRamo(product);
        return insuranceCoverageProfiles[product]?.length || insuranceCoverageProfiles[normalized]?.length || academyCoverages.some((row) => row.Ramo === normalized);
    });
    if (selectedProducts.length === 0) {
        return '<div class="asset-coverage-matrix-empty">Selecciona primero los seguros a incorporar al activo para ver las coberturas que puede requerir cada ramo.</div>';
    }
    return selectedProducts.map((product, productIndex) => {
        const normalized = normalizeInsuranceRamo(product);
        const academyRows = academyCoverages.filter((row) => row.Ramo === normalized);
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
                        <label class="asset-coverage-matrix-row ${selected.has(coverageToggleValue(product, coverage)) ? 'is-selected' : ''}">
                            <input type="checkbox" data-asset-coverage-toggle value="${assetEscape(coverageToggleValue(product, coverage))}" ${selected.has(coverageToggleValue(product, coverage)) ? 'checked' : ''}>
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
    const total = form.elements['detalle[valor_contable_total]'] || form.elements['detalle[valor_contable_inicial]'];
    const accumulated = form.elements['detalle[depreciacion_acumulada]'];
    const net = form.elements['detalle[valor_neto_contable]'];
    if (monthly instanceof HTMLInputElement && annual instanceof HTMLInputElement) {
        setAutoMoney(annual, assetNumeric(monthly.value) * 12);
    }
    if (total instanceof HTMLInputElement && accumulated instanceof HTMLInputElement && net instanceof HTMLInputElement) {
        const value = assetNumeric(total.value) - assetNumeric(accumulated.value);
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

const suggestCoverageValue = (form, coverage) => {
    const text = String(coverage || '').toLowerCase();
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
    const coverageRow = [...form.querySelectorAll('[data-asset-insurance-coverage-row]')]
        .find((row) => {
            const rowProduct = row.querySelector('[name$="[ramo]"]')?.value || '';
            const rowCoverage = row.querySelector('[name$="[cobertura]"]')?.value || '';
            return rowProduct === product && rowCoverage === coverage;
        });
    const target = coverageRow?.querySelector(`[name$="[${field}]"]`);
    if (target instanceof HTMLInputElement || target instanceof HTMLTextAreaElement || target instanceof HTMLSelectElement) {
        target.value = input.value;
    }
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
    const rows = assetFormRows(form, '[data-asset-insurance-policy-row]', ['ano', 'tipo_documento', 'ramo', 'aseguradora', 'intermediario', 'agencia_expedidora', 'codigo_agencia', 'numero_poliza', 'numero_emision', 'numero_pago_electronico', 'modalidad_facturacion', 'coaseguro', 'tomador', 'asegurado', 'beneficiario', 'direccion_riesgo', 'ciudad_riesgo', 'actividad_riesgo', 'tipo_riesgo', 'fecha_inicio', 'fecha_fin', 'fecha_renovacion', 'prima_neta', 'iva', 'gastos_expedicion', 'prima_total', 'valor_asegurado_total', 'deducible_general', 'forma_pago', 'numero_cuotas', 'clausulado', 'anexos_endosos', 'exclusiones_relevantes', 'texto_aclaratorio', 'asistencias', 'estado', 'soporte', 'observaciones']);
    if (rows.length === 0) {
        rows.push({ ano: String(new Date().getFullYear()), ramo: type, tipo_documento: 'Cotizacion' });
    } else {
        rows[0].ramo = type;
    }
    renderAssetInsurancePolicyRows(form, rows);
    form.dataset.assetCoveragePolicyIndex = '0';
    renderAssetInsuranceCoverageRows(form, assetFormRows(form, '[data-asset-insurance-coverage-row]', ['ano', 'numero_poliza', 'ramo', 'cobertura', 'riesgo_cubierto', 'valor_asegurado', 'limite_evento', 'porcentaje_invar', 'indice_variable', 'sublimite', 'tasa', 'prima', 'deducible', 'fuente_valor_asegurado', 'fecha_inicio', 'fecha_fin', 'fecha_renovacion', 'observaciones']));
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
    const activeInsuranceTab = form.dataset.assetInsuranceTab || 'modelo';
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
        const typeChips = insuranceTypes.map((item) => {
            const catalog = insuranceCatalogForProduct(form, item);
            return `
            <label class="asset-coverage-chip asset-insurance-ramo-chip ${selectedInsuranceTypes.has(item) ? 'is-selected' : ''}" data-asset-insurance-product-chip="${assetEscape(item)}">
                <input type="checkbox" data-asset-insurance-type-toggle value="${assetEscape(item)}" ${selectedInsuranceTypes.has(item) ? 'checked' : ''}>
                <span>
                    ${catalog ? `<em>${assetEscape(insuranceCatalogValue(catalog, 'Código', 'Codigo'))}</em>` : ''}
                    <strong>${assetEscape(item)}</strong>
                    ${catalog ? `<small>${assetEscape(insuranceCatalogMacro(catalog))}</small>` : ''}
                </span>
            </label>
        `;
        }).join('');
        const chips = insuranceAssetOptions.map((item) => `
            <label class="asset-coverage-chip ${selectedCategories.has(item.category) ? 'is-selected' : ''}">
                <input type="checkbox" data-asset-insurable-toggle value="${assetEscape(item.key)}" ${selectedCategories.has(item.category) ? 'checked' : ''}>
                <span>${assetEscape(item.label)}</span>
            </label>
        `).join('');
        return `
            <div class="asset-insurance-builder">
                <div class="asset-insurance-route">
                    <strong>Flujo de proteccion del activo</strong>
                    <span>1. Define los seguros a incorporar. 2. Marca sus coberturas. 3. Determina los valores asegurados con soporte.</span>
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
                <div>
                    <strong>Bienes o exposiciones asegurables</strong>
                    <span>Marca los elementos que pueden alimentar valores asegurados. Luego se detallan en la pestana 3 con fuente, fecha y valor.</span>
                </div>
                <div class="asset-coverage-chips">${chips}</div>
                <div class="asset-insurance-derived-summary" data-insurance-derived-summary>Sin bienes asegurables seleccionados.</div>
            </div>
        `;
    };
    const renderField = (field) => {
        if (type === 'Inmueble' && ['tipo_seguro', 'que_se_asegura', 'riesgos_cubiertos'].includes(field.name)) {
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
        return `<label${applies}>${assetFieldLabel(field)}<input name="${assetEscape(name)}" type="${inputType}"${inputMode} value="${assetEscape(value)}"></label>${academy}`;
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
    const assetPanels = groupEntries.map(([group, groupFields], index) => `
        <section class="asset-specific-tab-panel ${index === 0 ? 'is-active' : ''}" data-asset-tab-panel="${index}" ${index === 0 ? '' : 'hidden'}>
            <h3>${assetEscape(group)}</h3>
            ${type === 'Inmueble' && group === '14. Seguros' ? `
                ${renderInsuranceBuilder()}
                <details class="asset-insurance-policy-basics">
                    <summary><strong>Datos de poliza vigente o cotizacion</strong><span>Abrir solo cuando ya exista aseguradora, poliza o vigencia.</span></summary>
                    <div class="asset-specific-fields">${groupFields.map(renderField).join('')}</div>
                </details>
            ` : `<div class="asset-specific-fields">${groupFields.map(renderField).join('')}</div>`}
        </section>
    `).join('');
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
                    <label>Canon mensual<input name="subunidades[${index}][canon_mensual]" inputmode="decimal" value="${assetEscape(row.canon_mensual ?? '')}" placeholder="$0"></label>
                    <label>Administracion mensual<input name="subunidades[${index}][administracion_mensual]" inputmode="decimal" value="${assetEscape(row.administracion_mensual ?? '')}" placeholder="$0"></label>
                    <label>IVA<input name="subunidades[${index}][iva]" inputmode="decimal" value="${assetEscape(row.iva ?? '')}" placeholder="$0"></label>
                    <label>Retencion<input name="subunidades[${index}][retencion]" inputmode="decimal" value="${assetEscape(row.retencion ?? '')}" placeholder="$0"></label>
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
    const activeTab = form.dataset.assetInsuranceTab || 'modelo';
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
    const sourceRows = rows.length > 0 ? rows : [{}];
    container.innerHTML = sourceRows.map((row, index) => {
        const documentTypeOptions = (options.tipo_documento_poliza || []).map((item) => `<option value="${assetEscape(item)}" ${item === (row.tipo_documento ?? '') ? 'selected' : ''}>${assetEscape(item)}</option>`).join('');
        const paymentOptions = (options.forma_pago_seguro || []).map((item) => `<option value="${assetEscape(item)}" ${item === (row.forma_pago ?? '') ? 'selected' : ''}>${assetEscape(item)}</option>`).join('');
        const stateOptions = (options.estado_poliza || []).map((item) => `<option value="${assetEscape(item)}" ${item === (row.estado ?? '') ? 'selected' : ''}>${assetEscape(item)}</option>`).join('');
        const selectedRamos = new Set(splitInsuranceSelection(row.ramo ?? ''));
        const allowedProducts = new Set(insuranceProductsForAsset(type, selectedInsuranceCategoriesFromForm(form), form));
        selectedRamos.forEach((item) => allowedProducts.add(item));
        const adopted = row.adoptada === 'Si';
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
            <details class="asset-insurance-policy-card" data-asset-insurance-policy-row open>
                <summary>
                    <strong>${adopted ? 'Poliza vigente' : assetEscape(row.ramo || `Cotizacion ${index + 1}`)}</strong>
                    <span>${assetEscape([row.aseguradora, row.numero_poliza, row.prima_total ? assetMoney(row.prima_total) : '', adopted ? 'Adoptada' : ''].filter(Boolean).join(' / ') || 'Completa los datos basicos para cotizar')}</span>
                </summary>
                <div class="asset-policy-decision ${adopted ? 'is-adopted' : ''}">
                    <input type="hidden" name="seguro_polizas[${index}][adoptada]" value="${assetEscape(row.adoptada ?? '')}">
                    <input type="hidden" name="seguro_polizas[${index}][fecha_adopcion]" value="${assetEscape(row.fecha_adopcion ?? '')}">
                    <label>Criterio decision<input name="seguro_polizas[${index}][criterio_adopcion]" value="${assetEscape(row.criterio_adopcion ?? '')}" placeholder="Mejor equilibrio cobertura, deducible y prima"></label>
                    <button type="button" data-adopt-asset-insurance-policy>${adopted ? 'Opcion adoptada' : 'Adoptar cotizacion'}</button>
                </div>
                <div class="asset-insurance-policy-row">
                <label>Ano<input name="seguro_polizas[${index}][ano]" inputmode="numeric" value="${assetEscape(row.ano ?? '')}" placeholder="2026"></label>
                <label>Tipo documento<select name="seguro_polizas[${index}][tipo_documento]">${assetPlaceholderOption(row.tipo_documento ?? '')}${documentTypeOptions}</select></label>
                <div class="asset-policy-ramo-picker">
                    <span>Productos incluidos en esta poliza</span>
                    <input type="hidden" name="seguro_polizas[${index}][ramo]" data-asset-policy-ramo value="${assetEscape(row.ramo ?? '')}">
                    <div class="asset-coverage-chips asset-insurance-type-grid">${ramoChecklist}</div>
                </div>
                <label>Aseguradora<input name="seguro_polizas[${index}][aseguradora]" value="${assetEscape(row.aseguradora ?? '')}" placeholder="Aseguradora"></label>
                <label>Intermediario<input name="seguro_polizas[${index}][intermediario]" value="${assetEscape(row.intermediario ?? '')}" placeholder="Corredor / agencia"></label>
                <label>Agencia expedidora<input name="seguro_polizas[${index}][agencia_expedidora]" value="${assetEscape(row.agencia_expedidora ?? '')}" placeholder="Cartagena, Bogota..."></label>
                <label>Codigo agencia<input name="seguro_polizas[${index}][codigo_agencia]" value="${assetEscape(row.codigo_agencia ?? '')}" placeholder="Cod. agencia"></label>
                <label>Numero poliza<input name="seguro_polizas[${index}][numero_poliza]" value="${assetEscape(row.numero_poliza ?? '')}" placeholder="JWS797, LWY154..."></label>
                <label>Numero emision<input name="seguro_polizas[${index}][numero_emision]" value="${assetEscape(row.numero_emision ?? '')}" placeholder="Emision / certificado"></label>
                <label>Numero pago electronico<input name="seguro_polizas[${index}][numero_pago_electronico]" value="${assetEscape(row.numero_pago_electronico ?? '')}" placeholder="PAP / referencia pago"></label>
                <label>Modalidad facturacion<input name="seguro_polizas[${index}][modalidad_facturacion]" value="${assetEscape(row.modalidad_facturacion ?? '')}" placeholder="Anual, contado, financiada..."></label>
                <label>Coaseguro<input name="seguro_polizas[${index}][coaseguro]" value="${assetEscape(row.coaseguro ?? '')}" placeholder="% participacion y companias"></label>
                <label>Tomador<input name="seguro_polizas[${index}][tomador]" value="${assetEscape(row.tomador ?? '')}"></label>
                <label>Asegurado<input name="seguro_polizas[${index}][asegurado]" value="${assetEscape(row.asegurado ?? '')}"></label>
                <label>Beneficiario<input name="seguro_polizas[${index}][beneficiario]" value="${assetEscape(row.beneficiario ?? '')}"></label>
                <label>Direccion riesgo<input name="seguro_polizas[${index}][direccion_riesgo]" value="${assetEscape(row.direccion_riesgo ?? '')}" placeholder="Predio asegurado"></label>
                <label>Ciudad riesgo<input name="seguro_polizas[${index}][ciudad_riesgo]" value="${assetEscape(row.ciudad_riesgo ?? '')}" placeholder="Ciudad del predio"></label>
                <label>Actividad riesgo<input name="seguro_polizas[${index}][actividad_riesgo]" value="${assetEscape(row.actividad_riesgo ?? '')}" placeholder="Oficina privada, comercio..."></label>
                <label>Tipo riesgo<input name="seguro_polizas[${index}][tipo_riesgo]" value="${assetEscape(row.tipo_riesgo ?? '')}" placeholder="Servicios, industrial..."></label>
                <label>Inicio vigencia<input name="seguro_polizas[${index}][fecha_inicio]" type="date" value="${assetEscape(row.fecha_inicio ?? '')}"></label>
                <label>Fin vigencia<input name="seguro_polizas[${index}][fecha_fin]" type="date" value="${assetEscape(row.fecha_fin ?? '')}"></label>
                <label>Renovacion<input name="seguro_polizas[${index}][fecha_renovacion]" type="date" value="${assetEscape(row.fecha_renovacion ?? '')}"></label>
                <label>Prima neta<input name="seguro_polizas[${index}][prima_neta]" inputmode="decimal" value="${assetEscape(row.prima_neta ?? '')}" placeholder="$0"></label>
                <label>IVA<input name="seguro_polizas[${index}][iva]" inputmode="decimal" value="${assetEscape(row.iva ?? '')}" placeholder="$0"></label>
                <label>Gastos expedicion<input name="seguro_polizas[${index}][gastos_expedicion]" inputmode="decimal" value="${assetEscape(row.gastos_expedicion ?? '')}" placeholder="$0"></label>
                <label>Prima total<input name="seguro_polizas[${index}][prima_total]" inputmode="decimal" value="${assetEscape(row.prima_total ?? '')}" placeholder="$0"></label>
                <label>Valor asegurado total<input name="seguro_polizas[${index}][valor_asegurado_total]" inputmode="decimal" value="${assetEscape(row.valor_asegurado_total ?? '')}" placeholder="$0"></label>
                <label>Deducible general<input name="seguro_polizas[${index}][deducible_general]" value="${assetEscape(row.deducible_general ?? '')}" placeholder="% o valor"></label>
                <label>Forma pago<select name="seguro_polizas[${index}][forma_pago]">${assetPlaceholderOption(row.forma_pago ?? '')}${paymentOptions}</select></label>
                <label>Cuotas<input name="seguro_polizas[${index}][numero_cuotas]" inputmode="numeric" value="${assetEscape(row.numero_cuotas ?? '')}" placeholder="1"></label>
                <label>Clausulado<input name="seguro_polizas[${index}][clausulado]" value="${assetEscape(row.clausulado ?? '')}" placeholder="General, SoliPyme, version..."></label>
                <label>Anexos / endosos<input name="seguro_polizas[${index}][anexos_endosos]" value="${assetEscape(row.anexos_endosos ?? '')}" placeholder="Endosos, certificados, anexos"></label>
                <label>Exclusiones relevantes<input name="seguro_polizas[${index}][exclusiones_relevantes]" value="${assetEscape(row.exclusiones_relevantes ?? '')}" placeholder="Exclusiones criticas"></label>
                <label>Texto aclaratorio<input name="seguro_polizas[${index}][texto_aclaratorio]" value="${assetEscape(row.texto_aclaratorio ?? '')}" placeholder="Condiciones particulares relevantes"></label>
                <label>Asistencias<input name="seguro_polizas[${index}][asistencias]" value="${assetEscape(row.asistencias ?? '')}" placeholder="Grua, hogar, juridica..."></label>
                <label>Estado<select name="seguro_polizas[${index}][estado]">${assetPlaceholderOption(row.estado ?? '')}${stateOptions}</select></label>
                <label>Soporte<input name="seguro_polizas[${index}][soporte]" value="${assetEscape(row.soporte ?? '')}" placeholder="Caratula, PDF, documento"></label>
                <label>Observaciones<input name="seguro_polizas[${index}][observaciones]" value="${assetEscape(row.observaciones ?? '')}" placeholder="Cambios de renovacion, exclusiones..."></label>
                <button type="button" class="asset-remove-insurance" aria-label="Quitar poliza" data-remove-asset-insurance-policy>&times;</button>
                </div>
            </details>
        `;
    }).join('');
};

const renderAssetInsuranceCoverageRows = (form, rows = []) => {
    const container = form.querySelector('[data-asset-insurance-coverage-rows]');
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
    const allCoverageNames = [...new Set([
        ...(options.tipo_cobertura_seguro || []),
        ...insuranceAcademyData(form).coverages.map((row) => row.Cobertura).filter(Boolean),
    ])];
    const existingRows = rows.filter((row) => row && Object.values(row).some((value) => String(value ?? '').trim() !== ''));
    const selected = new Set(existingRows
        .filter((row) => row.cobertura)
        .map((row) => coverageToggleValue(row.ramo || '', row.cobertura || '')));
    const policyRows = assetFormRows(form, '[data-asset-insurance-policy-row]', ['numero_poliza', 'ramo', 'fecha_inicio', 'fecha_fin', 'fecha_renovacion'])
        .filter((row) => row.numero_poliza || row.ramo);
    const activePolicyIndex = Number(form.dataset.assetCoveragePolicyIndex || 0);
    const activePolicy = policyRows[Number.isFinite(activePolicyIndex) ? activePolicyIndex : 0] || {};
    const selectedProducts = selectedInsuranceProductsFromForm(form);
    const activeProduct = activeInsuranceProduct(form, selectedProducts, 'assetCoverageActiveProduct');
    const visibleProducts = activeProduct ? [activeProduct] : selectedProducts;
    const selectedRiskProfile = activePolicy.ramo || joinInsuranceSelection(selectedProducts);
    const profileCoverageNames = coverageOptionsForPolicy(selectedRiskProfile, allCoverageNames, form);
    const selectedCoverageNames = [...selected].map((item) => parseCoverageToggleValue(item).coverage).filter(Boolean);
    const extraSelected = selectedCoverageNames.filter((item) => !profileCoverageNames.includes(item));
    const policyOptions = policyRows.map((policy, index) => {
        const label = [policy.numero_poliza || `Poliza ${index + 1}`, policy.ramo].filter(Boolean).join(' / ');
        return `<option value="${index}" ${index === activePolicyIndex ? 'selected' : ''}>${assetEscape(label)}</option>`;
    }).join('');
    const sourceRows = existingRows;
    const activeRows = sourceRows.filter((row) => (row.ramo || '') === activeProduct || (!activeProduct && row.ramo === ''));
    const extraChecklist = extraSelected.map((item) => `
        <label class="asset-coverage-chip ${selectedCoverageNames.includes(item) ? 'is-selected' : ''}">
            <input type="checkbox" data-asset-coverage-toggle value="${assetEscape(coverageToggleValue('', item))}" ${selectedCoverageNames.includes(item) ? 'checked' : ''}>
            <span>${assetEscape(item)}</span>
        </label>
    `).join('');
    container.innerHTML = `
        ${selectedInsuranceStripHtml(form)}
        <div class="asset-coverage-selector" data-asset-coverage-selector>
            <div>
                <strong>Matriz ramo / amparo / bienes asegurables</strong>
                <span>${selectedRiskProfile ? `Productos seleccionados: ${assetEscape(selectedRiskProfile)}. Marca solo los amparos que realmente se van a cotizar o contratar.` : 'Primero selecciona uno o varios productos de seguro.'}</span>
            </div>
            <label class="asset-coverage-policy-source">Poliza base<select data-asset-coverage-policy-source>${policyOptions || '<option value="">Primera poliza registrada</option>'}</select></label>
            ${insuranceProductTabsHtml(selectedProducts, activeProduct, 'coverage')}
            ${insuranceCoverageMatrixHtml(visibleProducts, selected, form)}
            ${extraChecklist ? `<div class="asset-coverage-extra"><strong>Amparos agregados manualmente</strong><div class="asset-coverage-chips">${extraChecklist}</div></div>` : ''}
        </div>
        <div class="asset-coverage-selected">
            ${sourceRows.map((row, index) => {
                const coverageOptions = allCoverageNames.map((item) => `<option value="${assetEscape(item)}" ${item === (row.cobertura ?? '') ? 'selected' : ''}>${assetEscape(item)}</option>`).join('');
                const isActiveRow = (row.ramo || '') === activeProduct || (!activeProduct && row.ramo === '');
                return `
                    <div class="asset-insurance-coverage-row" data-asset-insurance-coverage-row ${isActiveRow ? '' : 'hidden'}>
                        <label>Ano<input name="seguro_coberturas[${index}][ano]" inputmode="numeric" value="${assetEscape(row.ano ?? '')}" placeholder="2026"></label>
                        <label>Numero poliza<input name="seguro_coberturas[${index}][numero_poliza]" value="${assetEscape(row.numero_poliza ?? '')}" placeholder="JWS797, LWY154..."></label>
                <label>Producto<input name="seguro_coberturas[${index}][ramo]" value="${assetEscape(row.ramo ?? '')}" placeholder="Producto de la poliza base"></label>
                        <label>Cobertura<select name="seguro_coberturas[${index}][cobertura]">${assetPlaceholderOption(row.cobertura ?? '')}${coverageOptions}</select></label>
                        <label>Riesgo cubierto<input name="seguro_coberturas[${index}][riesgo_cubierto]" value="${assetEscape(row.riesgo_cubierto ?? row.cobertura ?? '')}" placeholder="Incendio, terremoto, corriente debil..."></label>
                        <label>Valor asegurado<input name="seguro_coberturas[${index}][valor_asegurado]" inputmode="decimal" value="${assetEscape(row.valor_asegurado ?? '')}" placeholder="$0"></label>
                        <label>Limite por evento<input name="seguro_coberturas[${index}][limite_evento]" inputmode="decimal" value="${assetEscape(row.limite_evento ?? '')}" placeholder="$ / % por evento"></label>
                        <label>Sublimite<input name="seguro_coberturas[${index}][sublimite]" value="${assetEscape(row.sublimite ?? '')}" placeholder="% evento / vigencia / valor"></label>
                        <label>Indice variable<input name="seguro_coberturas[${index}][indice_variable]" inputmode="decimal" value="${assetEscape(row.indice_variable ?? '')}" placeholder="IPC, 5%, pactado"></label>
                        <label>% invar<input name="seguro_coberturas[${index}][porcentaje_invar]" inputmode="decimal" value="${assetEscape(row.porcentaje_invar ?? '')}" placeholder="5%"></label>
                        <label>Tasa<input name="seguro_coberturas[${index}][tasa]" inputmode="decimal" value="${assetEscape(row.tasa ?? '')}" placeholder="0% / tarifa"></label>
                        <label>Prima<input name="seguro_coberturas[${index}][prima]" inputmode="decimal" data-coverage-premium value="${assetEscape(row.prima ?? '')}" placeholder="$0"></label>
                        <label>Deducible<input name="seguro_coberturas[${index}][deducible]" value="${assetEscape(row.deducible ?? '')}" placeholder="% o valor"></label>
                        <label>Fuente valor asegurado<input name="seguro_coberturas[${index}][fuente_valor_asegurado]" value="${assetEscape(row.fuente_valor_asegurado ?? '')}" placeholder="Avaluo, cotizacion, factura, relacion de bienes..."></label>
                        <label>Inicio cobertura<input name="seguro_coberturas[${index}][fecha_inicio]" type="date" value="${assetEscape(row.fecha_inicio ?? '')}"></label>
                        <label>Fin cobertura<input name="seguro_coberturas[${index}][fecha_fin]" type="date" value="${assetEscape(row.fecha_fin ?? '')}"></label>
                        <label>Renovacion<input name="seguro_coberturas[${index}][fecha_renovacion]" type="date" value="${assetEscape(row.fecha_renovacion ?? '')}"></label>
                        <label>Observaciones<input name="seguro_coberturas[${index}][observaciones]" value="${assetEscape(row.observaciones ?? '')}" placeholder="Condicion, sublimite, soporte..."></label>
                        <button type="button" class="asset-remove-insurance" aria-label="Quitar cobertura" data-remove-asset-insurance-coverage>&times;</button>
                    </div>
                `;
            }).join('') || '<p class="muted">Marca una cobertura para diligenciar valores asegurados, prima, tasa y deducible.</p>'}
            ${activeRows.length === 0 && sourceRows.length > 0 ? '<p class="muted">Este ramo aun no tiene coberturas marcadas.</p>' : ''}
        </div>
    `;
    syncCoveragePremiumRows(form);
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

const renderAssetInsuranceEquipmentRows = (form, rows = []) => {
    const container = form.querySelector('[data-asset-insurance-equipment-rows]');
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
    const sourceRows = rows.length > 0 ? rows : [];
    const selectedProducts = selectedInsuranceProductsFromForm(form);
    const activeProduct = activeInsuranceProduct(form, selectedProducts, 'assetValueActiveProduct');
    const selectedCoverageRows = historyRowsForType(form, '[data-asset-insurance-coverage-row]', assetInsuranceCoverageFields)
        .filter((item) => item.cobertura || item.ramo);
    const activeCoverageRows = selectedCoverageRows.filter((item) => (item.ramo || '') === activeProduct || (!activeProduct && item.ramo === ''));
    container.innerHTML = `
        ${selectedInsuranceStripHtml(form)}
        ${insuranceProductTabsHtml(selectedProducts, activeProduct, 'values')}
        ${insuranceCoverageRequestTableHtml(activeCoverageRows)}
        ${sourceRows.map((row, index) => {
        return `
            <div data-asset-insurance-equipment-row hidden>
                ${insuredItemFields.map((field) => `<input type="hidden" name="seguro_equipos[${index}][${field}]" value="${assetEscape(row[field] ?? '')}">`).join('')}
            </div>
        `;
    }).join('')}
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
    const policies = assetFormRows(form, '[data-asset-insurance-policy-row]', ['ano', 'ramo', 'aseguradora', 'numero_poliza', 'prima_total', 'valor_asegurado_total', 'estado', 'adoptada']);
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
        const premium = yearCoverages.reduce((sum, row) => sum + assetNumber(row.prima), 0) || yearPolicies.reduce((sum, row) => sum + assetNumber(row.prima_total), 0);
        return `
            <article class="asset-insurance-year">
                <strong>${year}</strong>
                <span>Aseguradora: ${assetEscape(adopted.aseguradora || 'Por definir')}</span>
                <span>Valor asegurado: ${assetMoney(insuredValue)}</span>
                <span>Prima: ${assetMoney(premium)}</span>
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
    const policies = assetFormRows(form, '[data-asset-insurance-policy-row]', ['ano', 'ramo', 'aseguradora', 'numero_poliza', 'fecha_inicio', 'fecha_fin', 'fecha_renovacion', 'prima_total', 'valor_asegurado_total', 'deducible_general', 'estado', 'adoptada', 'criterio_adopcion']);
    const current = policies.find((row) => row.adoptada === 'Si') || policies.find((row) => row.estado === 'Vigente');
    if (!current) {
        target.innerHTML = '<p class="muted">Aun no has adoptado una cotizacion como poliza vigente.</p>';
        return;
    }
    const coverages = assetFormRows(form, '[data-asset-insurance-coverage-row]', ['numero_poliza', 'cobertura', 'valor_asegurado', 'prima', 'deducible'])
        .filter((row) => !current.numero_poliza || !row.numero_poliza || row.numero_poliza === current.numero_poliza);
    target.innerHTML = `
        <article class="asset-insurance-year">
            <strong>${assetEscape(current.ano || 'Vigente')}</strong>
            <span>Aseguradora: ${assetEscape(current.aseguradora || 'Por definir')}</span>
            <span>Poliza: ${assetEscape(current.numero_poliza || 'Sin numero')}</span>
            <span>Productos: ${assetEscape(current.ramo || 'Por definir')}</span>
            <span>Prima: ${assetMoney(assetNumber(current.prima_total))}</span>
            <span>Renovacion: ${assetEscape(assetDate(current.fecha_renovacion || current.fecha_fin) || 'Sin fecha')}</span>
        </article>
        <div class="asset-insurance-current-coverages">
            ${coverages.map((row) => `<span>${assetEscape(row.cobertura || 'Cobertura')} / ${assetMoney(assetNumber(row.valor_asegurado))} / ded. ${assetEscape(row.deducible || 'Por definir')}</span>`).join('') || '<span>Coberturas pendientes de asociar.</span>'}
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
            <label>Valor catastral<input name="valoraciones_anuales[${index}][valor_catastral]" inputmode="decimal" value="${assetEscape(row.valor_catastral ?? '')}" placeholder="$0"></label>
            <label>Valor comercial<input name="valoraciones_anuales[${index}][valor_comercial]" inputmode="decimal" value="${assetEscape(row.valor_comercial ?? '')}" placeholder="$0"></label>
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
            <label>Canon mensual total<input name="ingresos_anuales[${index}][canon_mensual]" inputmode="decimal" value="${assetEscape(row.canon_mensual ?? '')}" placeholder="$0"></label>
            <output data-asset-annual-canon-preview>${assetEscape(assetMoney(assetAnnualCanonForRow(row)))}</output>
            <label>% participacion<input name="ingresos_anuales[${index}][porcentaje_participacion]" inputmode="decimal" value="${assetEscape(row.porcentaje_participacion ?? '')}" placeholder="${assetEscape(assetParticipationShare(form) * 100)}%"></label>
            <label>Incremento %<input name="ingresos_anuales[${index}][incremento_porcentaje]" inputmode="decimal" value="${assetEscape(row.incremento_porcentaje ?? '')}" placeholder="0%"></label>
            <label>Incremento valor<input name="ingresos_anuales[${index}][incremento_valor]" inputmode="decimal" value="${assetEscape(row.incremento_valor ?? '')}" placeholder="$0"></label>
            <label>Nuevo canon mensual<input name="ingresos_anuales[${index}][nuevo_canon_mensual]" inputmode="decimal" value="${assetEscape(row.nuevo_canon_mensual ?? '')}" placeholder="$0"></label>
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
            ${expenseHistoryFields.map((field) => `<label>${assetEscape(assetExpenseLabel(field))}<input name="gastos_anuales[${index}][${field}]" inputmode="decimal" value="${assetEscape(row[field] ?? '')}" placeholder="$0"></label>`).join('')}
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
    const source = draft ? { ...(row || {}), ...(draft.fields || {}), detalle: draft.detalle || {}, participaciones: draft.participaciones || [], fiducia_beneficiarios: draft.fiducia_beneficiarios || [], subunidades: draft.subunidades || [], valoraciones_anuales: draft.valoraciones_anuales || [], ingresos_anuales: draft.ingresos_anuales || [], gastos_anuales: draft.gastos_anuales || [], seguro_polizas: draft.seguro_polizas || [], seguro_coberturas: draft.seguro_coberturas || [], seguro_equipos: draft.seguro_equipos || [], seguro_movimientos: draft.seguro_movimientos || [] } : row;
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
    renderAssetInsuranceMovementRows(form, source?.seguro_movimientos || []);
    renderAssetCurrentPolicy(form);
    renderAssetInsuranceHistory(form);
    renderAssetParticipationRows(form, source?.participaciones || []);
    renderAssetModalDocuments(form, row, parseAssetJson(form, 'assetDocuments'), form.closest('[data-workspace]')?.dataset.basePath ?? '');
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

    assetForm.elements.tipo_activo?.addEventListener('change', () => {
        renderAssetSpecificFields(assetForm);
        renderAssetFiduciaRows(assetForm);
        renderAssetSubunitRows(assetForm);
        renderAssetAnnualHistory(assetForm);
        renderAssetInsuranceCoverageRows(assetForm);
        renderAssetInsuranceEquipmentRows(assetForm);
    });

    assetSearch?.addEventListener('input', applyAssetSearch);

    assetForm.addEventListener('input', scheduleAssetDraft);
    assetForm.addEventListener('change', scheduleAssetDraft);
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
            updateInsuranceTypeSelection(assetForm);
            updateInsuranceProductAcademy(assetForm);
        }
        if (target instanceof HTMLInputElement && target.matches('[data-asset-insurable-toggle]')) {
            const option = insuranceAssetOptions.find((item) => item.key === target.value);
            if (option) {
                if (target.checked) {
                    upsertInsuredAssetFromOption(assetForm, option);
                } else {
                    removeInsuredAssetByOption(assetForm, option);
                }
            }
            updateInsuranceProductVisibility(assetForm);
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

    const assetInsurancePolicyFields = ['ano', 'tipo_documento', 'ramo', 'aseguradora', 'intermediario', 'agencia_expedidora', 'codigo_agencia', 'numero_poliza', 'numero_emision', 'numero_pago_electronico', 'modalidad_facturacion', 'coaseguro', 'tomador', 'asegurado', 'beneficiario', 'direccion_riesgo', 'ciudad_riesgo', 'actividad_riesgo', 'tipo_riesgo', 'fecha_inicio', 'fecha_fin', 'fecha_renovacion', 'prima_neta', 'iva', 'gastos_expedicion', 'prima_total', 'valor_asegurado_total', 'deducible_general', 'forma_pago', 'numero_cuotas', 'clausulado', 'anexos_endosos', 'exclusiones_relevantes', 'texto_aclaratorio', 'asistencias', 'estado', 'adoptada', 'fecha_adopcion', 'criterio_adopcion', 'soporte', 'observaciones'];
    const assetInsuranceCoverageFields = ['ano', 'numero_poliza', 'ramo', 'cobertura', 'riesgo_cubierto', 'valor_asegurado', 'limite_evento', 'porcentaje_invar', 'indice_variable', 'sublimite', 'tasa', 'prima', 'deducible', 'fuente_valor_asegurado', 'fecha_inicio', 'fecha_fin', 'fecha_renovacion', 'observaciones'];
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
        assetForm.dataset.assetInsuranceTab = button.dataset.assetInsuranceTab || 'modelo';
        updateAssetInsuranceSections(assetForm, assetForm.elements.tipo_activo?.value || '');
        renderAssetCurrentPolicy(assetForm);
        renderAssetInsuranceHistory(assetForm);
    });

    assetForm.querySelector('[data-add-asset-insurance-policy]')?.addEventListener('click', () => {
        const rows = historyRowsForType(assetForm, '[data-asset-insurance-policy-row]', assetInsurancePolicyFields);
        rows.push({});
        renderAssetInsurancePolicyRows(assetForm, rows);
        saveAssetDraft(assetForm);
    });

    assetForm.querySelector('[data-asset-insurance-policy-rows]')?.addEventListener('click', (event) => {
        const target = event.target;
        if (!(target instanceof Element) || !target.closest('[data-remove-asset-insurance-policy]')) {
            const adoptButton = target instanceof Element ? target.closest('[data-adopt-asset-insurance-policy]') : null;
            if (adoptButton instanceof HTMLElement) {
                let rows = historyRowsForType(assetForm, '[data-asset-insurance-policy-row]', assetInsurancePolicyFields);
                const card = adoptButton.closest('[data-asset-insurance-policy-row]');
                const index = [...assetForm.querySelectorAll('[data-asset-insurance-policy-row]')].indexOf(card);
                const today = new Date().toISOString().slice(0, 10);
                rows = rows.map((row, rowIndex) => ({
                    ...row,
                    adoptada: rowIndex === index ? 'Si' : '',
                    fecha_adopcion: rowIndex === index ? (row.fecha_adopcion || today) : '',
                    estado: rowIndex === index ? 'Vigente' : (row.estado === 'Vigente' ? 'En renovacion' : row.estado),
                    criterio_adopcion: rowIndex === index ? (row.criterio_adopcion || 'Mejor equilibrio entre cobertura, deducible, sublimites, exclusiones y prima.') : row.criterio_adopcion,
                }));
                renderAssetInsurancePolicyRows(assetForm, rows);
                renderAssetCurrentPolicy(assetForm);
                renderAssetInsuranceHistory(assetForm);
                saveAssetDraft(assetForm);
                return;
            }
            return;
        }
        target.closest('[data-asset-insurance-policy-row]')?.remove();
        renderAssetInsurancePolicyRows(assetForm, historyRowsForType(assetForm, '[data-asset-insurance-policy-row]', assetInsurancePolicyFields));
        renderAssetCurrentPolicy(assetForm);
        renderAssetInsuranceHistory(assetForm);
        saveAssetDraft(assetForm);
    });
    assetForm.querySelector('[data-asset-insurance-policy-rows]')?.addEventListener('change', (event) => {
        const target = event.target;
        if (target instanceof HTMLInputElement && target.matches('[data-asset-policy-ramo-toggle]')) {
            target.closest('.asset-coverage-chip')?.classList.toggle('is-selected', target.checked);
            updatePolicyRamoSelection(target.closest('[data-asset-insurance-policy-row]'));
            renderAssetInsuranceCoverageRows(assetForm, historyRowsForType(assetForm, '[data-asset-insurance-coverage-row]', assetInsuranceCoverageFields));
            renderAssetCurrentPolicy(assetForm);
            renderAssetInsuranceHistory(assetForm);
            saveAssetDraft(assetForm);
            return;
        }
        if (target instanceof HTMLSelectElement) {
            renderAssetCurrentPolicy(assetForm);
            renderAssetInsuranceHistory(assetForm);
            saveAssetDraft(assetForm);
        }
    });
    assetForm.querySelector('[data-asset-insurance-policy-rows]')?.addEventListener('input', () => {
        renderAssetCurrentPolicy(assetForm);
        renderAssetInsuranceHistory(assetForm);
        saveAssetDraft(assetForm);
    });

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
            return;
        }
        if (!(target instanceof HTMLInputElement) || !target.matches('[data-asset-coverage-toggle]')) {
            return;
        }
        const parsedCoverage = parseCoverageToggleValue(target.value);
        const coverage = parsedCoverage.coverage;
        const product = parsedCoverage.product;
        let rows = historyRowsForType(assetForm, '[data-asset-insurance-coverage-row]', assetInsuranceCoverageFields);
        if (target.checked) {
            if (!rows.some((row) => product ? coverageRowKey(row) === coverageToggleValue(product, coverage) : row.cobertura === coverage)) {
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
            rows = rows.filter((row) => product ? coverageRowKey(row) !== coverageToggleValue(product, coverage) : row.cobertura !== coverage);
        }
        renderAssetInsuranceCoverageRows(assetForm, rows);
        renderAssetInsuranceHistory(assetForm);
        saveAssetDraft(assetForm);
    });

    assetForm.querySelector('[data-asset-insurance-coverage-rows]')?.addEventListener('input', (event) => {
        const target = event.target;
        if (!(target instanceof HTMLInputElement) || !target.name.match(/\[(valor_asegurado|tasa)\]$/)) {
            return;
        }
        syncCoveragePremiumRow(target.closest('[data-asset-insurance-coverage-row]'));
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
        renderAssetCurrentPolicy(assetForm);
        renderAssetInsuranceHistory(assetForm);
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
        const category = coverageSupportCategories(selectedCoverageRows)[0] || '';
        rows.push({
            ano: String(new Date().getFullYear()),
            ramo: activeProduct || coverage.ramo || '',
            cobertura_asociada: [coverage.ramo, coverage.cobertura].filter(Boolean).join(' / '),
            categoria_item: category,
            unidad: category === 'Construccion' ? 'm2' : category ? 'und' : '',
            incluye_terreno: category === 'Construccion' ? 'No' : '',
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
            renderAssetInsuranceHistory(assetForm);
            saveAssetDraft(assetForm);
            return;
        }
        if (!(target instanceof Element) || !target.matches('[data-insured-item-calc], [data-insured-item-total]')) {
            return;
        }
        const row = target.closest('[data-asset-insurance-equipment-row]');
        if (row instanceof HTMLElement) {
            updateInsuredItemRow(row);
            refreshCoverageSuggestedValues(assetForm);
            updateInsuranceDerivedSummary(assetForm);
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
            closeAssetModal();
        }
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && modal instanceof HTMLElement && !modal.hidden) {
            closeAssetModal();
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
