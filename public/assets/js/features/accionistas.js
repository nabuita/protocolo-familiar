const shareholderEscape = (value) => String(value ?? '').replace(/[&<>"']/g, (char) => ({
    '&': '&amp;',
    '<': '&lt;',
    '>': '&gt;',
    '"': '&quot;',
    "'": '&#039;',
}[char]));

const shareholderFields = [
    'empresa',
    'accionista',
    'vinculo_familiar',
    'tipo_participacion',
    'naturaleza_participacion',
    'numero_acciones_cuotas',
    'porcentaje',
    'ano_ingreso',
    'fecha_adquisicion',
    'forma_adquisicion',
    'valor_pagado_aportado',
    'quien_aporto_recursos',
    'medio_pago',
    'soporte_pago',
    'estado_civil_adquirir',
    'regimen_patrimonial_adquirir',
    'convivia_en_ese_momento',
    'existe_documento_adquisicion',
    'registro_libro_accionistas',
    'fecha_registro_libro',
    'restriccion_vigente',
    'tipo_restriccion',
    'valor_nominal',
    'valor_estimado_actual',
    'fecha_valoracion',
    'metodo_valoracion',
    'fuente_valoracion',
    'alerta_documental',
    'observaciones',
];

const shareholderNumber = (value) => {
    if (value === null || value === undefined || value === '') {
        return '';
    }
    return new Intl.NumberFormat('es-CO', { maximumFractionDigits: 0 }).format(Number(value));
};

const shareholderMoney = (value) => {
    if (value === null || value === undefined || value === '') {
        return '';
    }
    return new Intl.NumberFormat('es-CO', { style: 'currency', currency: 'COP', maximumFractionDigits: 0 }).format(Number(value));
};

const shareholderMoneyDecimal = (value) => {
    if (value === null || value === undefined || value === '') {
        return '';
    }
    return new Intl.NumberFormat('es-CO', { style: 'currency', currency: 'COP', minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(Number(value));
};

const shareholderPercent = (value) => `${new Intl.NumberFormat('es-CO', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(Number(value || 0))}%`;

const shareholderDate = (value) => {
    if (!value || !/^\d{4}-\d{2}-\d{2}$/.test(String(value))) {
        return value ?? '';
    }
    const [year, month, day] = String(value).split('-');
    return `${day}/${month}/${year}`;
};

const shareholderStateClass = (value) => String(value ?? '').toLowerCase().replace(/\s+/g, '-');

const shareholderAcademy = {
    tipo_participacion: {
        'Acciones': {
            title: 'Acciones',
            body: 'Usala cuando la empresa sea una sociedad por acciones, por ejemplo S.A.S. o S.A. Cada accion representa una parte del capital de la sociedad.',
            example: 'Ejemplo: Karam tiene 2.700 acciones de Soluciones Comerciales y Constructivas S.A.S., equivalentes al 15%.',
        },
        'Cuotas sociales': {
            title: 'Cuotas sociales',
            body: 'Usala en sociedades de responsabilidad limitada. La participacion no se expresa como acciones sino como cuotas del capital social.',
            example: 'Ejemplo: un socio tiene 40 cuotas sociales de una Ltda., equivalentes al 40%.',
        },
        'Partes de interes': {
            title: 'Partes de interes',
            body: 'Aparece en sociedades de personas, como sociedad colectiva o algunos esquemas en comandita. La relacion entre socios pesa mas que la libre negociacion del titulo.',
            example: 'Ejemplo: un socio gestor tiene una parte de interes en una sociedad colectiva familiar.',
        },
        'Derechos fiduciarios': {
            title: 'Derechos fiduciarios',
            body: 'Usala cuando la participacion no esta directamente en una sociedad, sino en un patrimonio autonomo o fiducia que administra activos o derechos.',
            example: 'Ejemplo: la familia tiene derechos fiduciarios sobre un proyecto inmobiliario administrado por una fiduciaria.',
        },
        'Participacion economica informal': {
            title: 'Participacion economica informal',
            body: 'Usala cuando alguien participa en beneficios, aportes o resultados, pero todavia no existe un registro societario formal que lo demuestre.',
            example: 'Ejemplo: un familiar aporto dinero a un negocio y recibe utilidad, pero no figura en libro de accionistas.',
        },
        'Opcion de compra': {
            title: 'Opcion de compra',
            body: 'Usala cuando existe un derecho para comprar acciones o cuotas en el futuro, sujeto a condiciones, precio o plazo.',
            example: 'Ejemplo: un acuerdo concede opcion para comprar el 10% de una empresa si se cumple una meta.',
        },
        'Otro': {
            title: 'Otro',
            body: 'Usala cuando la figura no encaja en las categorias anteriores. Conviene explicar el caso en observaciones.',
            example: 'Ejemplo: participacion contractual especial, promesa de cesion o acuerdo particular pendiente de revisar.',
        },
        'Por confirmar': {
            title: 'Por confirmar',
            body: 'Usala cuando el analista no tenga soporte suficiente. Debe quedar como pendiente documental o juridico.',
            example: 'Ejemplo: no se sabe si el derecho esta en acciones, cuotas o solo en un acuerdo verbal.',
        },
    },
    naturaleza_participacion: {
        'Propia': {
            title: 'Propia',
            body: 'La participacion aparece atribuida a una persona especifica y, preliminarmente, se trata como de su titularidad individual.',
            example: 'Ejemplo: acciones adquiridas por Nassif con recursos propios y registradas a su nombre.',
        },
        'Social conyugal/patrimonial': {
            title: 'Social conyugal/patrimonial',
            body: 'Usala cuando la participacion puede integrar sociedad conyugal o sociedad patrimonial por la fecha o forma de adquisicion.',
            example: 'Ejemplo: acciones compradas durante matrimonio o union marital sin liquidacion patrimonial clara.',
        },
        'Familiar compartida': {
            title: 'Familiar compartida',
            body: 'Usala cuando hay acuerdo o realidad economica familiar compartida, aunque el registro formal este a nombre de una sola persona.',
            example: 'Ejemplo: una participacion figura a nombre de un hijo, pero fue financiada por padres o familia.',
        },
        'Empresarial': {
            title: 'Empresarial',
            body: 'La participacion pertenece o esta financiada por una empresa, no por una persona natural directamente.',
            example: 'Ejemplo: una sociedad familiar aparece como accionista de otra empresa.',
        },
        'Tercero no familiar': {
            title: 'Tercero no familiar',
            body: 'Usala para dejar claro que esa participacion pertenece a un socio externo a la familia.',
            example: 'Ejemplo: la familia solo tiene el 50% de DSC y el resto corresponde a un socio no familiar.',
        },
        'Por confirmar': {
            title: 'Por confirmar',
            body: 'Usala cuando falta soporte para saber si el derecho es propio, conyugal, familiar, empresarial o de un tercero.',
            example: 'Ejemplo: el libro de accionistas no esta actualizado o no hay soporte de adquisicion.',
        },
    },
    tipo_restriccion: {
        'Derecho de preferencia': {
            title: 'Derecho de preferencia',
            body: 'Antes de vender a un tercero, el accionista debe ofrecer la participacion a la sociedad o a los demas socios, segun estatutos o acuerdo.',
            example: 'Ejemplo: Karam quiere vender sus acciones; primero debe ofrecerselas a los otros accionistas.',
        },
        'Restriccion estatutaria': {
            title: 'Restriccion estatutaria',
            body: 'La limitacion esta escrita en los estatutos de la sociedad. Puede exigir autorizacion, procedimiento o condiciones para transferir.',
            example: 'Ejemplo: los estatutos dicen que ninguna cesion se registra sin aprobacion de la asamblea.',
        },
        'Acuerdo de accionistas': {
            title: 'Acuerdo de accionistas',
            body: 'La limitacion nace de un contrato entre socios. Puede regular venta, voto, administracion, salida, bloqueo o decisiones especiales.',
            example: 'Ejemplo: la familia acuerda no vender acciones a terceros sin aprobacion del consejo familiar.',
        },
        'Prenda o garantia': {
            title: 'Prenda o garantia',
            body: 'La participacion esta dada como garantia de una obligacion. Puede limitar su venta o exigir autorizacion del acreedor.',
            example: 'Ejemplo: acciones prendadas a favor de un banco para respaldar un credito.',
        },
        'Embargo o medida cautelar': {
            title: 'Embargo o medida cautelar',
            body: 'Existe una orden judicial o administrativa que restringe la disposicion de la participacion.',
            example: 'Ejemplo: acciones embargadas dentro de un proceso ejecutivo.',
        },
        'Usufructo': {
            title: 'Usufructo',
            body: 'Una persona puede conservar la propiedad y otra recibir frutos, dividendos o ciertos derechos economicos, segun el acto que lo crea.',
            example: 'Ejemplo: un padre dona nuda propiedad de acciones a sus hijos pero conserva usufructo de dividendos.',
        },
        'No aplica': {
            title: 'No aplica',
            body: 'Usala cuando no existe restriccion identificada sobre esa participacion.',
            example: 'Ejemplo: el libro y los estatutos no muestran limitaciones adicionales a la ley.',
        },
        'Por confirmar': {
            title: 'Por confirmar',
            body: 'Usala cuando falta revisar estatutos, libro, acuerdo de accionistas o certificados para saber si hay restricciones.',
            example: 'Ejemplo: no se ha ubicado el acuerdo de accionistas o el libro no esta actualizado.',
        },
    },
    metodo_valoracion: {
        'Valor nominal': {
            title: 'Valor nominal',
            body: 'Es el valor facial o contable asignado a cada accion o cuota en el capital. Sirve como referencia legal, pero casi nunca refleja el valor comercial real.',
            example: 'Ejemplo: 6.120 acciones con valor nominal de $10.000 cada una.',
        },
        'Valor patrimonial contable': {
            title: 'Valor patrimonial contable',
            body: 'Se calcula con base en patrimonio contable de la empresa dividido entre acciones o cuotas. Depende de estados financieros actualizados.',
            example: 'Ejemplo: patrimonio neto de la sociedad por porcentaje de participacion del socio.',
        },
        'Valor comercial estimado': {
            title: 'Valor comercial estimado',
            body: 'Es una estimacion de mercado. Puede apoyarse en flujos, activos, comparables o criterio profesional, pero debe documentarse.',
            example: 'Ejemplo: el 15% se estima segun valor comercial de la empresa y expectativas de negocio.',
        },
        'Valor segun avaluo de empresa': {
            title: 'Valor segun avaluo de empresa',
            body: 'Proviene de un informe formal de valoracion empresarial. Es el soporte mas robusto cuando hay decisiones familiares importantes.',
            example: 'Ejemplo: informe de valoracion independiente que determina el valor total de SK&C.',
        },
        'Valor de transaccion': {
            title: 'Valor de transaccion',
            body: 'Corresponde al precio usado en una compra, venta, cesion o negociacion real. Puede no coincidir con el valor comercial actual.',
            example: 'Ejemplo: una cesion de acciones hecha por $30.780.000.',
        },
        'Valor fiscal': {
            title: 'Valor fiscal',
            body: 'Valor usado para efectos tributarios o declarativos. Es util para impuestos, pero no necesariamente mide valor economico de mercado.',
            example: 'Ejemplo: valor reportado en declaracion de renta o soportes fiscales.',
        },
        'Por confirmar': {
            title: 'Por confirmar',
            body: 'Usala cuando aun no existe soporte suficiente para definir el metodo.',
            example: 'Ejemplo: falta balance actualizado, avaluo empresarial o documento de transaccion.',
        },
    },
};

const collectShareholderRows = (container) => [...container.querySelectorAll('[data-shareholder-row]')]
    .map((item) => JSON.parse(item.dataset.row || '{}'))
    .filter((row) => row && row.id);

const groupShareholderRows = (rows) => rows.reduce((groups, row) => {
    const name = row.accionista || 'Sin accionista';
    if (!groups[name]) {
        groups[name] = {
            accionista: name,
            vinculo: row.vinculo_familiar || '',
            participaciones: [],
            porcentajeTotal: 0,
            accionesTotal: 0,
            valorTotal: 0,
        };
    }
    groups[name].participaciones.push(row);
    groups[name].porcentajeTotal += Number(row.porcentaje || 0);
    groups[name].accionesTotal += Number(row.numero_acciones_cuotas || 0);
    groups[name].valorTotal += Number(row.valor_estimado_actual || 0);
    return groups;
}, {});

const shareholderDocumentStats = (rows = []) => {
    const pendingStates = ['Faltante', 'Solicitado', 'En revision', 'Vencido'];
    return {
        total: rows.length,
        pending: rows.filter((item) => pendingStates.includes(item.estado || '')).length,
        uploaded: rows.filter((item) => Boolean(item.archivo_ruta)).length,
    };
};

const parseShareholderDataset = (form, key, fallback) => {
    try {
        return JSON.parse(form.dataset[key] || '');
    } catch {
        return fallback;
    }
};

const renderShareholderSummary = (totals) => {
    const summary = document.querySelector('[data-shareholder-summary]');
    if (!(summary instanceof HTMLElement)) {
        return;
    }
    summary.innerHTML = totals.map((item) => {
        const state = item.estado === 'Completo' ? 'ok' : (item.estado === 'Excede 100%' ? 'danger' : 'warn');
        return `<article class="shareholder-total ${state}"><strong>${shareholderEscape(item.empresa)}</strong><span>${shareholderEscape(shareholderPercent(item.total))}</span><small>${shareholderEscape(item.estado)}</small></article>`;
    }).join('');
};

const renderShareholderCompany = (row, documentsByCode = {}) => {
    const docs = documentsByCode[row.codigo] || [];
    const stats = shareholderDocumentStats(docs);
    const typeLine = [row.tipo_participacion, row.naturaleza_participacion].filter(Boolean).join(' / ');
    const restriction = [row.restriccion_vigente, row.tipo_restriccion].filter(Boolean).join(' / ');
    const valuation = [row.metodo_valoracion, shareholderDate(row.fecha_valoracion)].filter(Boolean).join(' / ');
    const shares = Number(row.numero_acciones_cuotas || 0);
    const estimatedValue = Number(row.valor_estimado_actual || 0);
    const valuePerShare = shares > 0 && estimatedValue > 0 ? estimatedValue / shares : Number(row.valor_nominal || 0);
    const valuationSource = [row.fuente_valoracion, shareholderDate(row.fecha_valoracion)].filter(Boolean).join(' / ');
    return `
        <article class="shareholder-company" data-shareholder-row="${shareholderEscape(row.id)}" data-row="${shareholderEscape(JSON.stringify(row))}">
            <div class="shareholder-company-head">
                <div>
                    <strong>${shareholderEscape(row.empresa)}</strong>
                    <span>${shareholderEscape(row.codigo)} / ${shareholderEscape(row.forma_adquisicion)} / ingreso ${shareholderEscape(row.ano_ingreso)}</span>
                </div>
                <div class="shareholder-company-kpis">
                    <span><b>${shareholderEscape(shareholderPercent(row.porcentaje))}</b> participacion</span>
                    <span>${shareholderEscape(shareholderNumber(row.numero_acciones_cuotas))} acciones</span>
                    ${row.valor_estimado_actual ? `<span>${shareholderEscape(shareholderMoney(row.valor_estimado_actual))}</span>` : ''}
                    <span class="${stats.pending > 0 ? 'is-danger' : 'is-ok'}">${stats.pending} docs pendientes</span>
                </div>
            </div>
            <div class="shareholder-economic-strip" aria-label="Valor economico de la participacion">
                <div>
                    <span>Valor nominal actual</span>
                    <strong>${shareholderEscape(shareholderMoneyDecimal(row.valor_nominal || valuePerShare) || 'Pendiente')}</strong>
                    <small>Por accion/cuota</small>
                </div>
                <div>
                    <span>Acciones / cuotas</span>
                    <strong>${shareholderEscape(shareholderNumber(row.numero_acciones_cuotas) || 'Pendiente')}</strong>
                    <small>${shareholderEscape(shareholderPercent(row.porcentaje))} de participacion</small>
                </div>
                <div>
                    <span>Valor estimado actual</span>
                    <strong>${shareholderEscape(shareholderMoney(row.valor_estimado_actual) || 'Pendiente')}</strong>
                    <small>${shareholderEscape(row.metodo_valoracion || 'Metodo pendiente')}</small>
                </div>
                <div>
                    <span>Valor pagado / aportado</span>
                    <strong>${shareholderEscape(shareholderMoney(row.valor_pagado_aportado) || 'Pendiente')}</strong>
                    <small>${shareholderEscape(valuationSource || 'Fuente pendiente')}</small>
                </div>
            </div>
            <dl class="shareholder-detail-grid">
                <div><dt>Tipo / naturaleza</dt><dd>${shareholderEscape(typeLine)}</dd></div>
                <div><dt>Valor pagado</dt><dd>${shareholderEscape(shareholderMoney(row.valor_pagado_aportado))}</dd></div>
                <div><dt>Fecha adquisicion</dt><dd>${shareholderEscape(shareholderDate(row.fecha_adquisicion))}</dd></div>
                <div><dt>Estado civil</dt><dd>${shareholderEscape(row.estado_civil_adquirir)}</dd></div>
                <div><dt>Registro en libro</dt><dd>${shareholderEscape([row.registro_libro_accionistas, shareholderDate(row.fecha_registro_libro)].filter(Boolean).join(' / '))}</dd></div>
                <div><dt>Restriccion</dt><dd>${shareholderEscape(restriction)}</dd></div>
                <div><dt>Documentos</dt><dd>${stats.total} requeridos / ${stats.uploaded} subidos</dd></div>
                <div><dt>Valoracion</dt><dd>${shareholderEscape(valuation)}</dd></div>
                <div class="wide"><dt>Observaciones</dt><dd>${shareholderEscape(row.observaciones)}</dd></div>
            </dl>
            <div class="shareholder-row-actions">
                <button type="button" data-edit-shareholder>Editar expediente</button>
                <button type="button" data-delete-shareholder>Desactivar</button>
            </div>
        </article>
    `;
};

const renderShareholderRows = (container, rows, documentsByCode = {}) => {
    const groups = Object.values(groupShareholderRows(rows));
    const groupCount = document.querySelector('[data-shareholder-group-count]');
    if (groupCount) {
        groupCount.textContent = String(groups.length);
    }
    container.innerHTML = groups.map((group) => `
        <details class="shareholder-accordion-item" data-shareholder-group="${shareholderEscape(group.accionista.toLowerCase())}">
            <summary>
                <span class="shareholder-summary-main">
                    <strong>${shareholderEscape(group.accionista)}</strong>
                    <small>${shareholderEscape(group.vinculo)} / ${group.participaciones.length} empresa${group.participaciones.length === 1 ? '' : 's'}</small>
                </span>
                <span class="shareholder-summary-meta">
                    <span><b>${shareholderEscape(shareholderPercent(group.porcentajeTotal))}</b> total</span>
                    <span>${shareholderEscape(shareholderNumber(group.accionesTotal))} acciones</span>
                    ${group.valorTotal > 0 ? `<span>${shareholderEscape(shareholderMoney(group.valorTotal))}</span>` : ''}
                </span>
            </summary>
            <div class="shareholder-detail">
                <div class="shareholder-company-list">
                    ${group.participaciones.map((row) => renderShareholderCompany(row, documentsByCode)).join('')}
                </div>
            </div>
        </details>`).join('');
};

const shareholderDocumentItemHtml = (row, basePath) => {
    const hasFile = Boolean(row.archivo_ruta);
    const file = hasFile
        ? `<a href="${shareholderEscape(basePath + row.archivo_ruta)}" target="_blank" rel="noopener">Ver archivo</a>`
        : '<span class="document-missing">Sin archivo</span>';
    return `
        <div>
            <code>${shareholderEscape(row.codigo)}</code>
            <strong>${shareholderEscape(row.documento_tipo)}</strong>
            <span>${shareholderEscape(row.categoria)}</span>
            <small class="document-date-line">Fecha documental: ${shareholderEscape(shareholderDate(row.fecha_documento) || 'Sin fecha')}</small>
        </div>
        <div class="shareholder-document-meta">
            <span class="document-state state-${shareholderEscape(shareholderStateClass(row.estado))}">${shareholderEscape(row.estado)}</span>
            ${file}
            <button type="button" data-shareholder-open-document>Editar</button>
            <button type="button" data-shareholder-delete-document>Quitar</button>
        </div>
    `;
};

const renderShareholderModalDocuments = (form, row, docsByCode, basePath) => {
    const target = form.querySelector('[data-shareholder-modal-documents]');
    if (!(target instanceof HTMLElement)) {
        return;
    }
    const code = row?.codigo || '';
    const rows = code ? (docsByCode[code] || []) : [];
    if (!code) {
        target.innerHTML = '<p class="muted">Guarda primero la participacion para activar su expediente documental.</p>';
        return;
    }
    if (rows.length === 0) {
        target.innerHTML = '<p class="muted">Expediente documental pendiente de sincronizar. Guarda la participacion para crearlo.</p>';
        return;
    }
    const stats = shareholderDocumentStats(rows);
    target.innerHTML = `
        <div class="shareholder-doc-inline-summary">
            <span><strong>${stats.total}</strong> documentos</span>
            <span class="${stats.pending > 0 ? 'is-danger' : 'is-ok'}"><strong>${stats.pending}</strong> pendientes</span>
            <span><strong>${stats.uploaded}</strong> subidos</span>
            <button type="button" data-shareholder-new-document>Nuevo soporte</button>
        </div>
        <div class="shareholder-document-list">
            ${rows.map((item) => `<article class="shareholder-document-item" data-shareholder-doc-row="${shareholderEscape(item.id)}" data-row="${shareholderEscape(JSON.stringify(item))}">${shareholderDocumentItemHtml(item, basePath)}</article>`).join('')}
        </div>
    `;
};

const activateShareholderTab = (form, tab) => {
    form.querySelectorAll('[data-shareholder-tab]').forEach((item) => {
        if (item instanceof HTMLButtonElement) {
            const active = item.dataset.shareholderTab === tab;
            item.classList.toggle('is-active', active);
            item.setAttribute('aria-selected', active ? 'true' : 'false');
        }
    });
    form.querySelectorAll('[data-shareholder-tab-panel]').forEach((panel) => {
        if (panel instanceof HTMLElement) {
            const active = panel.dataset.shareholderTabPanel === tab;
            panel.classList.toggle('is-active', active);
            panel.hidden = !active;
        }
    });
};

const fillShareholderForm = (form, row = null) => {
    form.reset();
    form.elements.id.value = row?.id ?? '';
    form.elements.codigo.value = row?.codigo ?? 'Automatico';
    shareholderFields.forEach((field) => {
        if (form.elements[field]) {
            form.elements[field].value = row?.[field] ?? '';
        }
    });
    activateShareholderTab(form, 'participacion');
    updateShareholderAcademy(form, 'tipo_participacion');
    updateShareholderAcademy(form, 'naturaleza_participacion');
    updateShareholderAcademy(form, 'tipo_restriccion');
    updateShareholderAcademy(form, 'metodo_valoracion');
    renderShareholderModalDocuments(form, row, parseShareholderDataset(form, 'shareholderDocuments', {}), form.closest('[data-workspace]')?.dataset.basePath ?? '');
};

const updateShareholderAcademy = (form, field) => {
    const target = form.querySelector(`[data-shareholder-help="${field}"]`);
    const control = form.elements[field];
    if (!(target instanceof HTMLElement) || !(control instanceof HTMLSelectElement)) {
        return;
    }
    const selected = control.value;
    const content = shareholderAcademy[field]?.[selected];
    if (!content) {
        target.innerHTML = `
            <span>Academia del campo</span>
            <strong>${shareholderEscape({
                tipo_participacion: 'Tipo de participacion',
                naturaleza_participacion: 'Naturaleza de la participacion',
                tipo_restriccion: 'Tipo de restriccion',
                metodo_valoracion: 'Metodo de valoracion',
            }[field] || 'Academia del campo')}</strong>
            <p>Selecciona una opcion para ver su definicion, cuando usarla y un ejemplo.</p>
        `;
        return;
    }
    target.innerHTML = `
        <span>Academia del campo</span>
        <strong>${shareholderEscape(content.title)}</strong>
        <p>${shareholderEscape(content.body)}</p>
        <small>${shareholderEscape(content.example)}</small>
    `;
};

const shareholderForm = document.querySelector('[data-shareholder-form]');
if (shareholderForm instanceof HTMLFormElement) {
    const workspace = shareholderForm.closest('[data-workspace]');
    const basePath = workspace?.dataset.basePath ?? '';
    const modal = document.querySelector('[data-shareholder-modal]');
    const modalTitle = document.querySelector('[data-shareholder-modal-title]');
    const tableBody = document.querySelector('[data-shareholder-table]');
    const modalDocuments = shareholderForm.querySelector('[data-shareholder-modal-documents]');
    const status = document.querySelector('[data-shareholder-status]');
    const count = document.querySelector('[data-shareholder-count]');
    const filter = document.querySelector('[data-shareholder-filter]');
    const shareholderDocuments = parseShareholderDataset(shareholderForm, 'shareholderDocuments', {});
    let lastShareholderTrigger = null;

    const openShareholderModal = (row = null, trigger = null) => {
        lastShareholderTrigger = trigger;
        fillShareholderForm(shareholderForm, row);
        if (modalTitle) {
            modalTitle.textContent = row ? 'Editar accionista' : 'Nuevo accionista';
        }
        if (modal instanceof HTMLElement) {
            modal.hidden = false;
            document.body.classList.add('modal-open');
        }
        status && (status.textContent = '');
        window.setTimeout(() => shareholderForm.elements.empresa?.focus(), 0);
    };

    const closeShareholderModal = () => {
        if (modal instanceof HTMLElement) {
            modal.hidden = true;
            document.body.classList.remove('modal-open');
        }
        fillShareholderForm(shareholderForm);
        status && (status.textContent = '');
        if (lastShareholderTrigger instanceof HTMLElement) {
            lastShareholderTrigger.focus();
        }
    };

    shareholderForm.addEventListener('submit', async (event) => {
        event.preventDefault();
        if (!(tableBody instanceof HTMLElement)) {
            return;
        }
        const id = shareholderForm.elements.id.value;
        const url = id ? `${basePath}/protocolo-familiar/accionistas/${id}` : `${basePath}/protocolo-familiar/accionistas`;
        status && (status.textContent = 'Guardando...');
        try {
            const response = await fetch(url, { method: 'POST', body: new FormData(shareholderForm), credentials: 'same-origin' });
            const contentType = response.headers.get('content-type') || '';
            const payload = contentType.includes('application/json') ? await response.json() : { ok: false, error: await response.text() };
            if (!response.ok || !payload.ok) {
                throw new Error(payload.error || 'No fue posible guardar.');
            }
            if (payload.row?.codigo) {
                shareholderDocuments[payload.row.codigo] = payload.documents || [];
                shareholderForm.dataset.shareholderDocuments = JSON.stringify(shareholderDocuments);
            }
            const currentRows = collectShareholderRows(tableBody).filter((item) => item.id !== payload.row.id);
            renderShareholderRows(tableBody, [...currentRows, payload.row], shareholderDocuments);
            count && (count.textContent = String(currentRows.length + 1));
            filter?.dispatchEvent(new Event('input'));
            renderShareholderSummary(payload.totals || []);
            fillShareholderForm(shareholderForm);
            status && (status.textContent = 'Guardado.');
            closeShareholderModal();
        } catch (error) {
            const message = error instanceof Error ? error.message : 'No fue posible guardar.';
            status && (status.textContent = message);
            window.alert(message);
        }
    });

    document.querySelector('[data-new-shareholder]')?.addEventListener('click', (event) => {
        openShareholderModal(null, event.currentTarget instanceof HTMLElement ? event.currentTarget : null);
    });

    document.querySelector('[data-cancel-shareholder]')?.addEventListener('click', () => {
        fillShareholderForm(shareholderForm);
        status && (status.textContent = '');
    });

    document.querySelector('[data-close-shareholder]')?.addEventListener('click', closeShareholderModal);

    shareholderForm.querySelector('.shareholder-tabs')?.addEventListener('click', (event) => {
        const target = event.target;
        if (!(target instanceof Element)) {
            return;
        }
        const button = target.closest('[data-shareholder-tab]');
        if (button instanceof HTMLButtonElement) {
            activateShareholderTab(shareholderForm, button.dataset.shareholderTab || 'participacion');
        }
    });

    shareholderForm.elements.tipo_participacion?.addEventListener('change', () => {
        updateShareholderAcademy(shareholderForm, 'tipo_participacion');
    });

    shareholderForm.elements.naturaleza_participacion?.addEventListener('change', () => {
        updateShareholderAcademy(shareholderForm, 'naturaleza_participacion');
    });

    shareholderForm.elements.tipo_restriccion?.addEventListener('change', () => {
        updateShareholderAcademy(shareholderForm, 'tipo_restriccion');
    });

    shareholderForm.elements.metodo_valoracion?.addEventListener('change', () => {
        updateShareholderAcademy(shareholderForm, 'metodo_valoracion');
    });

    filter?.addEventListener('input', () => {
        const term = filter.value.trim().toLowerCase();
        tableBody?.querySelectorAll('[data-shareholder-group]').forEach((group) => {
            const name = group.getAttribute('data-shareholder-group') || '';
            group.hidden = term !== '' && !name.includes(term);
        });
    });

    modal?.addEventListener('click', (event) => {
        if (event.target === modal) {
            closeShareholderModal();
        }
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && modal instanceof HTMLElement && !modal.hidden) {
            closeShareholderModal();
        }
    });

    tableBody?.addEventListener('click', async (event) => {
        const target = event.target;
        if (!(target instanceof Element)) {
            return;
        }
        const tr = target.closest('[data-shareholder-row]');
        if (!(tr instanceof HTMLElement)) {
            return;
        }
        const row = JSON.parse(tr.dataset.row || '{}');
        if (target.closest('[data-edit-shareholder]')) {
            openShareholderModal(row, target.closest('[data-edit-shareholder]'));
            return;
        }
        if (target.closest('[data-delete-shareholder]')) {
            if (!window.confirm('Desactivar esta participacion accionaria?')) {
                return;
            }
            const data = new FormData();
            data.append('csrf_token', workspace?.dataset.csrf ?? '');
            const response = await fetch(`${basePath}/protocolo-familiar/accionistas/${row.id}/eliminar`, { method: 'POST', body: data, credentials: 'same-origin' });
            const payload = await response.json();
            if (response.ok && payload.ok) {
                const currentRows = collectShareholderRows(tableBody).filter((item) => item.id !== row.id);
                renderShareholderRows(tableBody, currentRows, shareholderDocuments);
                renderShareholderSummary(payload.totals || []);
                count && (count.textContent = String(currentRows.length));
                filter?.dispatchEvent(new Event('input'));
                status && (status.textContent = 'Registro desactivado.');
            }
        }
    });

    modalDocuments?.addEventListener('click', (event) => {
        const target = event.target;
        if (!(target instanceof Element)) {
            return;
        }
        const newButton = target.closest('[data-shareholder-new-document]');
        if (newButton instanceof HTMLElement) {
            const code = shareholderForm.elements.codigo?.value || '';
            const name = [shareholderForm.elements.accionista?.value, shareholderForm.elements.empresa?.value].filter(Boolean).join(' - ') || code;
            document.dispatchEvent(new CustomEvent('protocolo:open-document', {
                detail: {
                    trigger: newButton,
                    template: {
                        sujeto_tipo: 'Accionista/Participacion',
                        sujeto_codigo: code,
                        sujeto_nombre: name,
                        categoria: 'Acciones y participaciones',
                        documento_tipo: 'Soporte documental de la participacion',
                        requerido: 'Si',
                        estado: 'Faltante',
                        nivel_riesgo: 'Medio',
                    },
                },
            }));
            return;
        }
        const item = target.closest('[data-shareholder-doc-row]');
        const deleteButton = target.closest('[data-shareholder-delete-document]');
        if (deleteButton instanceof HTMLElement && item instanceof HTMLElement) {
            const row = JSON.parse(item.dataset.row || '{}');
            document.dispatchEvent(new CustomEvent('protocolo:delete-document', { detail: { row, trigger: deleteButton } }));
            return;
        }
        const button = target.closest('[data-shareholder-open-document]');
        if (!(button instanceof HTMLElement) || !(item instanceof HTMLElement)) {
            return;
        }
        const row = JSON.parse(item.dataset.row || '{}');
        document.dispatchEvent(new CustomEvent('protocolo:open-document', { detail: { row, trigger: button } }));
    });

    document.addEventListener('protocolo:document-saved', (event) => {
        const row = event.detail?.row;
        if (!row || row.sujeto_tipo !== 'Accionista/Participacion') {
            return;
        }
        const docs = shareholderDocuments[row.sujeto_codigo] || [];
        const index = docs.findIndex((item) => item.id === row.id);
        if (index >= 0) {
            docs[index] = row;
        } else {
            docs.push(row);
        }
        shareholderDocuments[row.sujeto_codigo] = docs;
        shareholderForm.dataset.shareholderDocuments = JSON.stringify(shareholderDocuments);
        renderShareholderModalDocuments(shareholderForm, { codigo: row.sujeto_codigo }, shareholderDocuments, basePath);
        if (tableBody instanceof HTMLElement) {
            renderShareholderRows(tableBody, collectShareholderRows(tableBody), shareholderDocuments);
            filter?.dispatchEvent(new Event('input'));
        }
    });

    document.addEventListener('protocolo:document-deleted', (event) => {
        const row = event.detail?.row;
        if (!row || row.sujeto_tipo !== 'Accionista/Participacion') {
            return;
        }
        shareholderDocuments[row.sujeto_codigo] = (shareholderDocuments[row.sujeto_codigo] || []).filter((item) => item.id !== row.id);
        shareholderForm.dataset.shareholderDocuments = JSON.stringify(shareholderDocuments);
        renderShareholderModalDocuments(shareholderForm, { codigo: row.sujeto_codigo }, shareholderDocuments, basePath);
        if (tableBody instanceof HTMLElement) {
            renderShareholderRows(tableBody, collectShareholderRows(tableBody), shareholderDocuments);
            filter?.dispatchEvent(new Event('input'));
        }
    });
}
