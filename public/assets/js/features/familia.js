const htmlEscape = (value) => String(value ?? '').replace(/[&<>"']/g, (char) => ({
    '&': '&amp;',
    '<': '&lt;',
    '>': '&gt;',
    '"': '&quot;',
    "'": '&#039;',
}[char]));

const familyFields = [
    'nombre_completo',
    'tipo_documento',
    'numero_documento',
    'fecha_nacimiento',
    'ciudad_expedicion',
    'nacionalidad',
    'telefono',
    'correo',
    'direccion_residencia',
    'tipo_vinculo',
    'generacion',
    'edad',
    'estado_civil',
    'ano_matrimonio_convivencia',
    'fecha_matrimonio_convivencia',
    'regimen_patrimonial',
    'conyuge_companero',
    'tiene_capitulaciones',
    'sociedad_conyugal_liquidada',
    'numero_hijos',
    'hijos_registrados',
    'trabaja_empresa_familiar',
    'empresa_donde_trabaja',
    'cargo',
    'tipo_vinculacion_laboral',
    'es_accionista',
    'participa_decisiones',
    'rol_gobierno_familiar',
    'tiene_poderes',
    'tiene_testamento',
    'alertas_observaciones',
    'observaciones',
];

const familyDocumentStats = (rows = []) => {
    const pendingStates = ['Faltante', 'Solicitado', 'En revision', 'Vencido'];
    return {
        total: rows.length,
        pending: rows.filter((item) => pendingStates.includes(item.estado || '')).length,
        uploaded: rows.filter((item) => Boolean(item.archivo_ruta)).length,
    };
};

const renderFamilyRow = (row, documents = []) => {
    const stats = familyDocumentStats(documents);
    const tr = document.createElement('tr');
    tr.dataset.familyRow = row.id;
    tr.dataset.row = JSON.stringify(row);
    tr.innerHTML = `
        <td data-label="Persona">
            <div class="family-person-cell">
                <code>${htmlEscape(row.codigo)}</code>
                <strong>${htmlEscape(row.nombre_completo)}</strong>
                <span>${htmlEscape(row.tipo_vinculo)}${row.generacion ? ` / ${htmlEscape(row.generacion)}` : ''}</span>
            </div>
        </td>
        <td data-label="Identificacion">
            <div class="family-summary-lines">
                <span>${htmlEscape(row.tipo_documento || 'Documento por definir')}${row.numero_documento ? `: ${htmlEscape(row.numero_documento)}` : ''}</span>
                <span>${row.fecha_nacimiento ? `Nacimiento: ${htmlEscape(row.fecha_nacimiento)}` : 'Nacimiento pendiente'}${row.edad ? ` / ${htmlEscape(row.edad)} anos` : ''}</span>
                <span>${htmlEscape([row.telefono, row.correo].filter(Boolean).join(' / '))}</span>
            </div>
        </td>
        <td data-label="Estado civil">
            <div class="family-summary-lines">
                <span>${htmlEscape(row.estado_civil)}</span>
                <span>${htmlEscape(row.regimen_patrimonial || 'Regimen por definir')}</span>
                <span>${row.conyuge_companero ? `Conyuge/companero: ${htmlEscape(row.conyuge_companero)}` : 'Sin conyuge registrado'}</span>
            </div>
        </td>
        <td data-label="Empresa y gobierno">
            <div class="family-summary-lines">
                <span>${row.trabaja_empresa_familiar === 'Si' ? 'Trabaja en empresa familiar' : 'No trabaja en empresa familiar'}</span>
                <span>${htmlEscape([row.empresa_donde_trabaja, row.cargo].filter(Boolean).join(' / '))}</span>
                <span>${row.es_accionista === 'Si' ? 'Accionista' : 'No accionista'}${row.participa_decisiones === 'Si' ? ' / Participa en decisiones' : ''}</span>
            </div>
        </td>
        <td data-label="Documentos">
            <div class="family-doc-pillset">
                <span>${stats.total} docs</span>
                <span class="${stats.pending > 0 ? 'is-danger' : 'is-ok'}">${stats.pending} pendientes</span>
                <span>${stats.uploaded} subidos</span>
            </div>
        </td>
        <td data-label="Acciones" class="row-actions"><button type="button" data-edit-family>Editar</button><button type="button" data-delete-family>Desactivar</button></td>
    `;
    return tr;
};

const familyDocumentStateClass = (value) => String(value ?? '').toLowerCase().replace(/\s+/g, '-');

const familyDocumentItemHtml = (row, basePath) => {
    const hasFile = Boolean(row.archivo_ruta);
    const file = hasFile
        ? `<a href="${htmlEscape(basePath + row.archivo_ruta)}" target="_blank" rel="noopener">Ver archivo</a>`
        : '<span class="document-missing">Sin archivo</span>';
    return `
        <div>
            <code>${htmlEscape(row.codigo)}</code>
            <strong>${htmlEscape(row.documento_tipo)}</strong>
            <span>${htmlEscape(row.categoria)}</span>
            <small class="document-date-line">Fecha documental: ${htmlEscape(row.fecha_documento ? row.fecha_documento.split('-').reverse().join('/') : 'Sin fecha')}</small>
        </div>
        <div class="family-document-meta">
            <span class="document-state state-${htmlEscape(familyDocumentStateClass(row.estado))}">${htmlEscape(row.estado)}</span>
            ${file}
            <button type="button" data-family-open-document>Editar</button>
            <button type="button" data-family-delete-document>Quitar</button>
        </div>
    `;
};

const recalcFamilyDocumentGroup = (group) => {
    if (!(group instanceof HTMLElement)) {
        return;
    }
    const rows = [...group.querySelectorAll('[data-family-doc-row]')].filter((row) => row instanceof HTMLElement);
    const total = rows.length;
    const pending = rows.filter((row) => ['Faltante', 'Solicitado', 'En revision', 'Vencido'].includes(row.dataset.state || '')).length;
    const updated = rows.filter((row) => !row.querySelector('.document-missing')).length;
    const totalTarget = group.querySelector('[data-family-doc-total]');
    const pendingTarget = group.querySelector('[data-family-doc-pending]');
    const updatedTarget = group.querySelector('[data-family-doc-updated]');
    if (totalTarget) totalTarget.textContent = String(total);
    if (pendingTarget) pendingTarget.textContent = String(pending);
    if (updatedTarget) updatedTarget.textContent = String(updated);
    pendingTarget?.parentElement?.classList.toggle('is-danger', pending > 0);
    pendingTarget?.parentElement?.classList.toggle('is-ok', pending === 0);
};

const renderFamilyDocumentGroup = (person, rows, basePath) => {
    if (!Array.isArray(rows) || rows.length === 0) {
        return null;
    }
    const details = document.createElement('details');
    details.className = 'family-document-group';
    details.dataset.familyDocGroup = person.codigo || rows[0]?.sujeto_codigo || '';
    details.open = true;
    details.innerHTML = `
        <summary>
            <span>
                <strong>${htmlEscape(person.nombre_completo || rows[0]?.sujeto_nombre || '')}</strong>
                <small>${htmlEscape(person.codigo || rows[0]?.sujeto_codigo || '')} · ${htmlEscape(person.tipo_vinculo || '')}</small>
            </span>
            <span class="family-document-stats">
                <span><strong data-family-doc-total>0</strong> docs</span>
                <span class="is-danger"><strong data-family-doc-pending>0</strong> pendientes</span>
                <span><strong data-family-doc-updated>0</strong> subidos</span>
            </span>
        </summary>
        <div class="family-document-list"></div>
    `;
    const list = details.querySelector('.family-document-list');
    rows.forEach((row) => {
        const item = document.createElement('article');
        item.className = 'family-document-item';
        item.dataset.familyDocRow = row.id;
        item.dataset.row = JSON.stringify(row);
        item.dataset.state = row.estado || '';
        item.innerHTML = familyDocumentItemHtml(row, basePath);
        list?.append(item);
    });
    recalcFamilyDocumentGroup(details);
    return details;
};

const upsertFamilyDocumentGroup = (container, person, rows, basePath) => {
    if (!(container instanceof HTMLElement) || !Array.isArray(rows) || rows.length === 0) {
        return;
    }
    const key = person.codigo || rows[0]?.sujeto_codigo || '';
    const current = container.querySelector(`[data-family-doc-group="${CSS.escape(key)}"]`);
    const next = renderFamilyDocumentGroup(person, rows, basePath);
    if (!(next instanceof HTMLElement)) {
        return;
    }
    if (current) {
        current.replaceWith(next);
    } else {
        container.prepend(next);
    }
};

const parseFamilyDataset = (form, key, fallback) => {
    try {
        return JSON.parse(form.dataset[key] || '');
    } catch {
        return fallback;
    }
};

const parseFamilyResponse = async (response) => {
    const contentType = response.headers.get('content-type') || '';
    if (contentType.includes('application/json')) {
        return response.json();
    }
    const text = await response.text();
    if (response.status === 401 || text.toLowerCase().includes('<!doctype') || text.toLowerCase().includes('<html')) {
        return { ok: false, error: 'La sesion pudo vencer o el servidor devolvio una pagina en vez de confirmar el guardado. Recarga e inicia sesion si hace falta.' };
    }
    return { ok: false, error: text.trim() || 'No fue posible guardar.' };
};

const renderFamilyModalDocuments = (form, row, docsByCode, basePath) => {
    const target = form.querySelector('[data-family-modal-documents]');
    if (!(target instanceof HTMLElement)) {
        return;
    }
    const code = row?.codigo || '';
    const rows = code ? (docsByCode[code] || []) : [];
    if (!code) {
        target.innerHTML = '<p class="muted">Guarda primero la persona para activar su checklist documental.</p>';
        return;
    }
    if (rows.length === 0) {
        target.innerHTML = '<p class="muted">Checklist documental pendiente de sincronizar. Guarda la persona para crearlo.</p>';
        return;
    }
    const pending = rows.filter((item) => ['Faltante', 'Solicitado', 'En revision', 'Vencido'].includes(item.estado || '')).length;
    const uploaded = rows.filter((item) => Boolean(item.archivo_ruta)).length;
    target.innerHTML = `
        <div class="family-doc-inline-summary">
            <span><strong>${rows.length}</strong> documentos</span>
            <span class="${pending > 0 ? 'is-danger' : 'is-ok'}"><strong>${pending}</strong> pendientes</span>
            <span><strong>${uploaded}</strong> subidos</span>
            <button type="button" data-family-new-document>Nuevo soporte</button>
        </div>
        <div class="family-document-list">
            ${rows.map((item) => `<article class="family-document-item" data-family-doc-row="${htmlEscape(item.id)}" data-row="${htmlEscape(JSON.stringify(item))}" data-state="${htmlEscape(item.estado || '')}">${familyDocumentItemHtml(item, basePath)}</article>`).join('')}
        </div>
    `;
};

const fillFamilyForm = (form, row = null) => {
    form.reset();
    form.elements.id.value = row?.id ?? '';
    form.elements.codigo.value = row?.codigo ?? 'Automatico';
    familyFields.forEach((field) => {
        if (form.elements[field]) {
            form.elements[field].value = row?.[field] ?? '';
        }
    });
    renderFamilyModalDocuments(form, row, parseFamilyDataset(form, 'familyDocuments', {}), form.closest('[data-workspace]')?.dataset.basePath ?? '');
};

document.querySelectorAll('[data-main-tab]').forEach((tab) => {
    tab.addEventListener('click', () => {
        const workspace = tab.closest('[data-workspace]');
        if (!workspace || tab.disabled) {
            return;
        }
        workspace.querySelectorAll('[data-main-tab]').forEach((button) => button.classList.toggle('active', button === tab));
        workspace.querySelectorAll('[data-main-panel]').forEach((panel) => panel.classList.toggle('active', panel.dataset.mainPanel === tab.dataset.mainTab));
    });
});

const familyForm = document.querySelector('[data-family-form]');
if (familyForm instanceof HTMLFormElement) {
    const workspace = familyForm.closest('[data-workspace]');
    const basePath = workspace?.dataset.basePath ?? '';
    const modal = document.querySelector('[data-family-modal]');
    const modalTitle = document.querySelector('[data-family-modal-title]');
    const tableBody = document.querySelector('[data-family-table] tbody');
    const documentGroups = document.querySelector('[data-family-document-groups]');
    const modalDocuments = familyForm.querySelector('[data-family-modal-documents]');
    const status = document.querySelector('[data-family-status]');
    const count = document.querySelector('[data-family-count]');
    const familyDocuments = parseFamilyDataset(familyForm, 'familyDocuments', {});
    let lastFamilyTrigger = null;

    const openFamilyModal = (row = null, trigger = null) => {
        lastFamilyTrigger = trigger;
        fillFamilyForm(familyForm, row);
        if (modalTitle) {
            modalTitle.textContent = row ? 'Editar persona' : 'Nueva persona';
        }
        if (modal instanceof HTMLElement) {
            modal.hidden = false;
            document.body.classList.add('modal-open');
        }
        status && (status.textContent = '');
        window.setTimeout(() => familyForm.elements.nombre_completo?.focus(), 0);
    };

    const closeFamilyModal = () => {
        if (modal instanceof HTMLElement) {
            modal.hidden = true;
            document.body.classList.remove('modal-open');
        }
        fillFamilyForm(familyForm);
        status && (status.textContent = '');
        if (lastFamilyTrigger instanceof HTMLElement) {
            lastFamilyTrigger.focus();
        }
    };

    familyForm.addEventListener('submit', async (event) => {
        event.preventDefault();
        if (!(tableBody instanceof HTMLTableSectionElement)) {
            return;
        }
        const id = familyForm.elements.id.value;
        const url = id ? `${basePath}/protocolo-familiar/familia/${id}` : `${basePath}/protocolo-familiar/familia`;
        status && (status.textContent = 'Guardando...');
        try {
            const response = await fetch(url, { method: 'POST', body: new FormData(familyForm), credentials: 'same-origin' });
            const payload = await parseFamilyResponse(response);
            if (!response.ok || !payload.ok) {
                throw new Error(payload.error || 'No fue posible guardar.');
            }
            const nextRow = renderFamilyRow(payload.row, payload.documents || []);
            const current = tableBody.querySelector(`[data-family-row="${payload.row.id}"]`);
            if (current) {
                current.replaceWith(nextRow);
            } else {
                tableBody.append(nextRow);
                count && (count.textContent = String(Number.parseInt(count.textContent || '0', 10) + 1));
            }
            if (payload.row?.codigo) {
                familyDocuments[payload.row.codigo] = payload.documents || [];
                familyForm.dataset.familyDocuments = JSON.stringify(familyDocuments);
            }
            upsertFamilyDocumentGroup(documentGroups, payload.row, payload.documents || [], basePath);
            fillFamilyForm(familyForm);
            status && (status.textContent = 'Guardado.');
            closeFamilyModal();
        } catch (error) {
            status && (status.textContent = error instanceof Error ? error.message : 'No fue posible guardar.');
        }
    });

    document.querySelector('[data-new-family]')?.addEventListener('click', (event) => {
        openFamilyModal(null, event.currentTarget instanceof HTMLElement ? event.currentTarget : null);
    });

    document.querySelector('[data-cancel-family]')?.addEventListener('click', () => {
        fillFamilyForm(familyForm);
        status && (status.textContent = '');
    });

    document.querySelector('[data-close-family]')?.addEventListener('click', closeFamilyModal);

    familyForm.querySelector('.family-tabs')?.addEventListener('click', (event) => {
        const target = event.target;
        if (!(target instanceof Element)) {
            return;
        }
        const button = target.closest('[data-family-tab]');
        if (!(button instanceof HTMLButtonElement)) {
            return;
        }
        const tab = button.dataset.familyTab || '';
        familyForm.querySelectorAll('[data-family-tab]').forEach((item) => {
            if (item instanceof HTMLButtonElement) {
                const active = item.dataset.familyTab === tab;
                item.classList.toggle('is-active', active);
                item.setAttribute('aria-selected', active ? 'true' : 'false');
            }
        });
        familyForm.querySelectorAll('[data-family-tab-panel]').forEach((panel) => {
            if (panel instanceof HTMLElement) {
                const active = panel.dataset.familyTabPanel === tab;
                panel.classList.toggle('is-active', active);
                panel.hidden = !active;
            }
        });
    });

    modal?.addEventListener('click', (event) => {
        if (event.target === modal) {
            event.preventDefault();
        }
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && modal instanceof HTMLElement && !modal.hidden) {
            closeFamilyModal();
        }
    });

    tableBody?.addEventListener('click', async (event) => {
        const target = event.target;
        if (!(target instanceof Element)) {
            return;
        }
        const tr = target.closest('[data-family-row]');
        if (!(tr instanceof HTMLTableRowElement)) {
            return;
        }
        const row = JSON.parse(tr.dataset.row || '{}');
        if (target.closest('[data-edit-family]')) {
            openFamilyModal(row, target.closest('[data-edit-family]'));
            return;
        }
        if (target.closest('[data-delete-family]')) {
            if (!window.confirm('Desactivar esta persona?')) {
                return;
            }
            const data = new FormData();
            data.append('csrf_token', workspace?.dataset.csrf ?? '');
            const response = await fetch(`${basePath}/protocolo-familiar/familia/${row.id}/eliminar`, { method: 'POST', body: data, credentials: 'same-origin' });
            const payload = await response.json();
            if (response.ok && payload.ok) {
                tr.remove();
                count && (count.textContent = String(Math.max(0, Number.parseInt(count.textContent || '0', 10) - 1)));
                status && (status.textContent = 'Registro desactivado.');
            }
        }
    });

    documentGroups?.addEventListener('click', (event) => {
        const target = event.target;
        if (!(target instanceof Element)) {
            return;
        }
        const newButton = target.closest('[data-family-new-document]');
        if (newButton instanceof HTMLElement) {
            const code = familyForm.elements.codigo?.value || '';
            const name = familyForm.elements.nombre_completo?.value || code;
            document.dispatchEvent(new CustomEvent('protocolo:open-document', {
                detail: {
                    trigger: newButton,
                    template: {
                        sujeto_tipo: 'Persona/Familia',
                        sujeto_codigo: code,
                        sujeto_nombre: name,
                        categoria: 'Identificacion',
                        documento_tipo: 'Soporte documental familiar',
                        requerido: 'Si',
                        estado: 'Faltante',
                        nivel_riesgo: 'Medio',
                    },
                },
            }));
            return;
        }
        const item = target.closest('[data-family-doc-row]');
        const deleteButton = target.closest('[data-family-delete-document]');
        if (deleteButton instanceof HTMLElement && item instanceof HTMLElement) {
            const row = JSON.parse(item.dataset.row || '{}');
            document.dispatchEvent(new CustomEvent('protocolo:delete-document', { detail: { row, trigger: deleteButton } }));
            return;
        }
        const button = target.closest('[data-family-open-document]');
        if (!(button instanceof HTMLElement) || !(item instanceof HTMLElement)) {
            return;
        }
        const row = JSON.parse(item.dataset.row || '{}');
        document.dispatchEvent(new CustomEvent('protocolo:open-document', { detail: { row, trigger: button } }));
    });

    modalDocuments?.addEventListener('click', (event) => {
        const target = event.target;
        if (!(target instanceof Element)) {
            return;
        }
        const newButton = target.closest('[data-family-new-document]');
        if (newButton instanceof HTMLElement) {
            const code = familyForm.elements.codigo?.value || '';
            const name = familyForm.elements.nombre_completo?.value || code;
            document.dispatchEvent(new CustomEvent('protocolo:open-document', {
                detail: {
                    trigger: newButton,
                    template: {
                        sujeto_tipo: 'Persona/Familia',
                        sujeto_codigo: code,
                        sujeto_nombre: name,
                        categoria: 'Identificacion',
                        documento_tipo: 'Soporte documental familiar',
                        requerido: 'Si',
                        estado: 'Faltante',
                        nivel_riesgo: 'Medio',
                    },
                },
            }));
            return;
        }
        const item = target.closest('[data-family-doc-row]');
        const deleteButton = target.closest('[data-family-delete-document]');
        if (deleteButton instanceof HTMLElement && item instanceof HTMLElement) {
            const row = JSON.parse(item.dataset.row || '{}');
            document.dispatchEvent(new CustomEvent('protocolo:delete-document', { detail: { row, trigger: deleteButton } }));
            return;
        }
        const button = target.closest('[data-family-open-document]');
        if (!(button instanceof HTMLElement) || !(item instanceof HTMLElement)) {
            return;
        }
        const row = JSON.parse(item.dataset.row || '{}');
        document.dispatchEvent(new CustomEvent('protocolo:open-document', { detail: { row, trigger: button } }));
    });

    document.addEventListener('protocolo:document-saved', (event) => {
        const row = event.detail?.row;
        if (!row || row.sujeto_tipo !== 'Persona/Familia') {
            return;
        }
        const docs = familyDocuments[row.sujeto_codigo] || [];
        const index = docs.findIndex((item) => item.id === row.id);
        if (index >= 0) {
            docs[index] = row;
        } else {
            docs.push(row);
        }
        familyDocuments[row.sujeto_codigo] = docs;
        familyForm.dataset.familyDocuments = JSON.stringify(familyDocuments);
        renderFamilyModalDocuments(familyForm, { codigo: row.sujeto_codigo }, familyDocuments, basePath);
        const item = document.querySelector(`[data-family-doc-row="${CSS.escape(row.id)}"]`);
        if (item instanceof HTMLElement) {
            item.dataset.row = JSON.stringify(row);
            item.dataset.state = row.estado || '';
            item.innerHTML = familyDocumentItemHtml(row, basePath);
            recalcFamilyDocumentGroup(item.closest('[data-family-doc-group]'));
        }
    });

    document.addEventListener('protocolo:document-deleted', (event) => {
        const row = event.detail?.row;
        if (!row || row.sujeto_tipo !== 'Persona/Familia') {
            return;
        }
        familyDocuments[row.sujeto_codigo] = (familyDocuments[row.sujeto_codigo] || []).filter((item) => item.id !== row.id);
        familyForm.dataset.familyDocuments = JSON.stringify(familyDocuments);
        document.querySelectorAll(`[data-family-doc-row="${CSS.escape(row.id)}"]`).forEach((item) => {
            if (!(item instanceof HTMLElement)) {
                return;
            }
            const group = item.closest('[data-family-doc-group]');
            item.remove();
            recalcFamilyDocumentGroup(group);
        });
        renderFamilyModalDocuments(familyForm, { codigo: row.sujeto_codigo }, familyDocuments, basePath);
    });
}
