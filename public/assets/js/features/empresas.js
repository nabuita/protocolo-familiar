const companyEscape = (value) => String(value ?? '').replace(/[&<>"']/g, (char) => ({
    '&': '&amp;',
    '<': '&lt;',
    '>': '&gt;',
    '"': '&quot;',
    "'": '&#039;',
}[char]));

const companyFields = [
    'razon_social',
    'nombre_comercial',
    'nit',
    'matricula_mercantil',
    'domicilio_principal',
    'ciudad_domicilio',
    'telefono',
    'correo_contacto',
    'tipo_sociedad',
    'ano_creacion',
    'actividad_principal',
    'empresa_operativa_patrimonial',
    'representante_legal',
    'tiene_junta_directiva',
    'tiene_revisor_fiscal',
    'tiene_inmuebles_propios',
    'tiene_empleados',
    'contador_responsable',
    'correo_contador',
    'periodicidad_iva',
    'periodicidad_retencion',
    'periodicidad_ica',
    'periodicidad_estados_financieros',
    'cierre_fiscal',
    'estado_rut',
    'estado_camara_comercio',
    'estado_obligaciones_tributarias',
    'carpeta_documental_nombre',
    'carpeta_documental_url',
    'carpeta_documental_observaciones',
    'deudas_creditos_importantes',
    'estatutos_actualizados',
    'acuerdo_accionistas',
    'libro_accionistas_actualizado',
    'nivel_riesgo',
    'observaciones',
];

const companyDate = (value) => {
    if (!value || !/^\d{4}-\d{2}-\d{2}$/.test(String(value))) {
        return value ?? '';
    }
    const [year, month, day] = String(value).split('-');
    return `${day}/${month}/${year}`;
};

const companyRiskClass = (value) => String(value ?? '').toLowerCase();

const companyDocumentStats = (rows = []) => {
    const pendingStates = ['Faltante', 'Solicitado', 'En revision', 'Vencido'];
    return {
        total: rows.length,
        pending: rows.filter((item) => pendingStates.includes(item.estado || '')).length,
        uploaded: rows.filter((item) => Boolean(item.archivo_ruta)).length,
    };
};

const renderCompanyRow = (row, documents = []) => {
    const stats = companyDocumentStats(documents);
    const tr = document.createElement('tr');
    tr.dataset.companyRow = row.id;
    tr.dataset.row = JSON.stringify(row);
    tr.innerHTML = `
        <td data-label="Empresa">
            <div class="company-main-cell">
                <code>${companyEscape(row.codigo)}</code>
                <strong>${companyEscape(row.razon_social)}</strong>
                <span>${companyEscape([row.nombre_comercial, row.empresa_operativa_patrimonial].filter(Boolean).join(' / '))}</span>
            </div>
        </td>
        <td data-label="Identificacion">
            <div class="company-summary-lines">
                <span>${companyEscape([row.tipo_sociedad, row.nit ? `NIT ${row.nit}` : ''].filter(Boolean).join(' / '))}</span>
                <span>${row.matricula_mercantil ? `Matricula: ${companyEscape(row.matricula_mercantil)}` : 'Matricula pendiente'}</span>
                <span>${companyEscape([row.ciudad_domicilio, companyDate(row.ano_creacion)].filter(Boolean).join(' / '))}</span>
            </div>
        </td>
        <td data-label="Gobierno">
            <div class="company-summary-lines">
                <span>${row.representante_legal ? `Rep. legal: ${companyEscape(row.representante_legal)}` : 'Representante pendiente'}</span>
                <span>Junta: ${companyEscape(row.tiene_junta_directiva)} / Revisor: ${companyEscape(row.tiene_revisor_fiscal)}</span>
                <span>Estatutos: ${companyEscape(row.estatutos_actualizados)} / Libro: ${companyEscape(row.libro_accionistas_actualizado)}</span>
            </div>
        </td>
        <td data-label="Tributario y contable">
            <div class="company-summary-lines">
                <span>${row.contador_responsable ? `Contador: ${companyEscape(row.contador_responsable)}` : 'Contador pendiente'}</span>
                <span>IVA: ${companyEscape(row.periodicidad_iva || 'Por definir')} / Retencion: ${companyEscape(row.periodicidad_retencion || 'Por definir')} / ICA: ${companyEscape(row.periodicidad_ica || 'Por definir')}</span>
                <span>EEFF: ${companyEscape(row.periodicidad_estados_financieros || 'Por definir')} / RUT: ${companyEscape(row.estado_rut || 'Por definir')}</span>
                <span>${row.carpeta_documental_url ? 'Carpeta externa vinculada' : 'Sin carpeta externa'}</span>
            </div>
        </td>
        <td data-label="Documentos">
            <div class="company-doc-pillset">
                <span>${stats.total} docs</span>
                <span class="${stats.pending > 0 ? 'is-danger' : 'is-ok'}">${stats.pending} pendientes</span>
                <span>${stats.uploaded} subidos</span>
                <span class="risk-badge risk-${companyEscape(companyRiskClass(row.nivel_riesgo))}">${companyEscape(row.nivel_riesgo)}</span>
            </div>
        </td>
        <td data-label="Acciones" class="company-row-actions"><button type="button" data-edit-company>Editar</button><button type="button" data-delete-company>Desactivar</button></td>
    `;
    return tr;
};

const parseCompanyDataset = (form, key, fallback) => {
    try {
        return JSON.parse(form.dataset[key] || '');
    } catch {
        return fallback;
    }
};

const companyDocumentStateClass = (value) => String(value ?? '').toLowerCase().replace(/\s+/g, '-');

const companyDocumentItemHtml = (row, basePath) => {
    const hasFile = Boolean(row.archivo_ruta);
    const file = hasFile
        ? `<a href="${companyEscape(basePath + row.archivo_ruta)}" target="_blank" rel="noopener">Ver archivo</a>`
        : '<span class="document-missing">Sin archivo</span>';
    return `
        <div>
            <code>${companyEscape(row.codigo)}</code>
            <strong>${companyEscape(row.documento_tipo)}</strong>
            <span>${companyEscape(row.categoria)}</span>
            <small class="document-date-line">Fecha documental: ${companyEscape(companyDate(row.fecha_documento) || 'Sin fecha')}</small>
        </div>
        <div class="company-document-meta">
            <span class="document-state state-${companyEscape(companyDocumentStateClass(row.estado))}">${companyEscape(row.estado)}</span>
            ${file}
            <button type="button" data-company-open-document>Editar</button>
            <button type="button" data-company-delete-document>Quitar</button>
        </div>
    `;
};

const renderCompanyModalDocuments = (form, row, docsByCode, basePath) => {
    const target = form.querySelector('[data-company-modal-documents]');
    if (!(target instanceof HTMLElement)) {
        return;
    }
    const code = row?.codigo || '';
    const rows = code ? (docsByCode[code] || []) : [];
    if (!code) {
        target.innerHTML = '<p class="muted">Guarda primero la empresa para activar su expediente documental.</p>';
        return;
    }
    if (rows.length === 0) {
        const external = row?.carpeta_documental_url
            ? `<article class="company-external-folder"><div><strong>${companyEscape(row.carpeta_documental_nombre || 'Carpeta documental externa')}</strong><span>${companyEscape(row.carpeta_documental_observaciones || 'Repositorio externo vinculado a esta empresa.')}</span></div><a href="${companyEscape(row.carpeta_documental_url)}" target="_blank" rel="noopener">Abrir carpeta</a></article>`
            : '';
        target.innerHTML = `${external}<p class="muted">Expediente documental pendiente de sincronizar. Guarda la empresa para crearlo.</p>`;
        return;
    }
    const stats = companyDocumentStats(rows);
    const grouped = rows.reduce((acc, item) => {
        const key = item.categoria || 'Sin carpeta';
        acc[key] ||= [];
        acc[key].push(item);
        return acc;
    }, {});
    target.innerHTML = `
        ${row?.carpeta_documental_url ? `<article class="company-external-folder"><div><strong>${companyEscape(row.carpeta_documental_nombre || 'Carpeta documental externa')}</strong><span>${companyEscape(row.carpeta_documental_observaciones || 'Repositorio externo vinculado a esta empresa.')}</span></div><a href="${companyEscape(row.carpeta_documental_url)}" target="_blank" rel="noopener">Abrir carpeta</a></article>` : ''}
        <div class="company-doc-inline-summary">
            <span><strong>${stats.total}</strong> documentos</span>
            <span class="${stats.pending > 0 ? 'is-danger' : 'is-ok'}"><strong>${stats.pending}</strong> pendientes</span>
            <span><strong>${stats.uploaded}</strong> subidos</span>
            <button type="button" data-company-new-document>Nuevo soporte</button>
        </div>
        <div class="company-document-folders">
            ${Object.entries(grouped).map(([folder, items], index) => `
                <details class="company-document-folder" ${index < 2 ? 'open' : ''}>
                    <summary><strong>${companyEscape(folder)}</strong><span>${items.length} docs</span></summary>
                    <div class="company-document-list">
                        ${items.map((item) => `<article class="company-document-item" data-company-doc-row="${companyEscape(item.id)}" data-row="${companyEscape(JSON.stringify(item))}" data-state="${companyEscape(item.estado || '')}">${companyDocumentItemHtml(item, basePath)}</article>`).join('')}
                    </div>
                </details>
            `).join('')}
        </div>
    `;
};

const fillCompanyForm = (form, row = null) => {
    form.reset();
    form.elements.id.value = row?.id ?? '';
    form.elements.codigo.value = row?.codigo ?? 'Automatico';
    companyFields.forEach((field) => {
        if (form.elements[field]) {
            form.elements[field].value = row?.[field] ?? '';
        }
    });
    renderCompanyModalDocuments(form, row, parseCompanyDataset(form, 'companyDocuments', {}), form.closest('[data-workspace]')?.dataset.basePath ?? '');
};

const companyForm = document.querySelector('[data-company-form]');
if (companyForm instanceof HTMLFormElement) {
    const workspace = companyForm.closest('[data-workspace]');
    const basePath = workspace?.dataset.basePath ?? '';
    const modal = document.querySelector('[data-company-modal]');
    const modalTitle = document.querySelector('[data-company-modal-title]');
    const tableBody = document.querySelector('[data-company-table] tbody');
    const modalDocuments = companyForm.querySelector('[data-company-modal-documents]');
    const status = document.querySelector('[data-company-status]');
    const count = document.querySelector('[data-company-count]');
    const companyDocuments = parseCompanyDataset(companyForm, 'companyDocuments', {});
    let lastCompanyTrigger = null;

    const openCompanyModal = (row = null, trigger = null) => {
        lastCompanyTrigger = trigger;
        fillCompanyForm(companyForm, row);
        if (modalTitle) {
            modalTitle.textContent = row ? 'Editar empresa' : 'Nueva empresa';
        }
        if (modal instanceof HTMLElement) {
            modal.hidden = false;
            document.body.classList.add('modal-open');
        }
        status && (status.textContent = '');
        window.setTimeout(() => companyForm.elements.razon_social?.focus(), 0);
    };

    const closeCompanyModal = () => {
        if (modal instanceof HTMLElement) {
            modal.hidden = true;
            document.body.classList.remove('modal-open');
        }
        fillCompanyForm(companyForm);
        status && (status.textContent = '');
        if (lastCompanyTrigger instanceof HTMLElement) {
            lastCompanyTrigger.focus();
        }
    };

    companyForm.addEventListener('submit', async (event) => {
        event.preventDefault();
        if (!(tableBody instanceof HTMLTableSectionElement)) {
            return;
        }
        const id = companyForm.elements.id.value;
        const url = id ? `${basePath}/protocolo-familiar/empresas/${id}` : `${basePath}/protocolo-familiar/empresas`;
        status && (status.textContent = 'Guardando...');
        try {
            const response = await fetch(url, { method: 'POST', body: new FormData(companyForm), credentials: 'same-origin' });
            const payload = await response.json();
            if (!response.ok || !payload.ok) {
                throw new Error(payload.error || 'No fue posible guardar.');
            }
            if (payload.row?.codigo) {
                companyDocuments[payload.row.codigo] = payload.documents || [];
                companyForm.dataset.companyDocuments = JSON.stringify(companyDocuments);
            }
            const nextRow = renderCompanyRow(payload.row, payload.documents || []);
            const current = tableBody.querySelector(`[data-company-row="${payload.row.id}"]`);
            if (current) {
                current.replaceWith(nextRow);
            } else {
                tableBody.append(nextRow);
                count && (count.textContent = String(Number.parseInt(count.textContent || '0', 10) + 1));
            }
            fillCompanyForm(companyForm);
            status && (status.textContent = 'Guardado.');
            closeCompanyModal();
        } catch (error) {
            status && (status.textContent = error instanceof Error ? error.message : 'No fue posible guardar.');
        }
    });

    document.querySelector('[data-new-company]')?.addEventListener('click', (event) => {
        openCompanyModal(null, event.currentTarget instanceof HTMLElement ? event.currentTarget : null);
    });

    document.querySelector('[data-cancel-company]')?.addEventListener('click', () => {
        fillCompanyForm(companyForm);
        status && (status.textContent = '');
    });

    document.querySelector('[data-close-company]')?.addEventListener('click', closeCompanyModal);

    companyForm.querySelector('.company-tabs')?.addEventListener('click', (event) => {
        const target = event.target;
        if (!(target instanceof Element)) {
            return;
        }
        const button = target.closest('[data-company-tab]');
        if (!(button instanceof HTMLButtonElement)) {
            return;
        }
        const tab = button.dataset.companyTab || '';
        companyForm.querySelectorAll('[data-company-tab]').forEach((item) => {
            if (item instanceof HTMLButtonElement) {
                const active = item.dataset.companyTab === tab;
                item.classList.toggle('is-active', active);
                item.setAttribute('aria-selected', active ? 'true' : 'false');
            }
        });
        companyForm.querySelectorAll('[data-company-tab-panel]').forEach((panel) => {
            if (panel instanceof HTMLElement) {
                const active = panel.dataset.companyTabPanel === tab;
                panel.classList.toggle('is-active', active);
                panel.hidden = !active;
            }
        });
    });

    modal?.addEventListener('click', (event) => {
        if (event.target === modal) {
            closeCompanyModal();
        }
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && modal instanceof HTMLElement && !modal.hidden) {
            closeCompanyModal();
        }
    });

    tableBody?.addEventListener('click', async (event) => {
        const target = event.target;
        if (!(target instanceof Element)) {
            return;
        }
        const tr = target.closest('[data-company-row]');
        if (!(tr instanceof HTMLTableRowElement)) {
            return;
        }
        const row = JSON.parse(tr.dataset.row || '{}');
        if (target.closest('[data-edit-company]')) {
            openCompanyModal(row, target.closest('[data-edit-company]'));
            return;
        }
        if (target.closest('[data-delete-company]')) {
            if (!window.confirm('Desactivar esta empresa?')) {
                return;
            }
            const data = new FormData();
            data.append('csrf_token', workspace?.dataset.csrf ?? '');
            const response = await fetch(`${basePath}/protocolo-familiar/empresas/${row.id}/eliminar`, { method: 'POST', body: data, credentials: 'same-origin' });
            const payload = await response.json();
            if (response.ok && payload.ok) {
                tr.remove();
                count && (count.textContent = String(Math.max(0, Number.parseInt(count.textContent || '0', 10) - 1)));
                status && (status.textContent = 'Registro desactivado.');
            }
        }
    });

    modalDocuments?.addEventListener('click', (event) => {
        const target = event.target;
        if (!(target instanceof Element)) {
            return;
        }
        const newButton = target.closest('[data-company-new-document]');
        if (newButton instanceof HTMLElement) {
            const code = companyForm.elements.codigo?.value || '';
            const name = companyForm.elements.razon_social?.value || code;
            document.dispatchEvent(new CustomEvent('protocolo:open-document', {
                detail: {
                    trigger: newButton,
                    template: {
                        sujeto_tipo: 'Empresa',
                        sujeto_codigo: code,
                        sujeto_nombre: name,
                        categoria: 'Constitucion y existencia',
                        documento_tipo: 'Soporte documental de la empresa',
                        requerido: 'Si',
                        estado: 'Faltante',
                        nivel_riesgo: 'Medio',
                    },
                },
            }));
            return;
        }
        const item = target.closest('[data-company-doc-row]');
        const deleteButton = target.closest('[data-company-delete-document]');
        if (deleteButton instanceof HTMLElement && item instanceof HTMLElement) {
            const row = JSON.parse(item.dataset.row || '{}');
            document.dispatchEvent(new CustomEvent('protocolo:delete-document', { detail: { row, trigger: deleteButton } }));
            return;
        }
        const button = target.closest('[data-company-open-document]');
        if (!(button instanceof HTMLElement) || !(item instanceof HTMLElement)) {
            return;
        }
        const row = JSON.parse(item.dataset.row || '{}');
        document.dispatchEvent(new CustomEvent('protocolo:open-document', { detail: { row, trigger: button } }));
    });

    document.addEventListener('protocolo:document-saved', (event) => {
        const row = event.detail?.row;
        if (!row || row.sujeto_tipo !== 'Empresa') {
            return;
        }
        const docs = companyDocuments[row.sujeto_codigo] || [];
        const index = docs.findIndex((item) => item.id === row.id);
        if (index >= 0) {
            docs[index] = row;
        } else {
            docs.push(row);
        }
        companyDocuments[row.sujeto_codigo] = docs;
        companyForm.dataset.companyDocuments = JSON.stringify(companyDocuments);
        renderCompanyModalDocuments(companyForm, { codigo: row.sujeto_codigo }, companyDocuments, basePath);
    });

    document.addEventListener('protocolo:document-deleted', (event) => {
        const row = event.detail?.row;
        if (!row || row.sujeto_tipo !== 'Empresa') {
            return;
        }
        companyDocuments[row.sujeto_codigo] = (companyDocuments[row.sujeto_codigo] || []).filter((item) => item.id !== row.id);
        companyForm.dataset.companyDocuments = JSON.stringify(companyDocuments);
        renderCompanyModalDocuments(companyForm, { codigo: row.sujeto_codigo }, companyDocuments, basePath);
    });
}
