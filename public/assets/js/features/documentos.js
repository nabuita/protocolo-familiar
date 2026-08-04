const documentEscape = (value) => String(value ?? '').replace(/[&<>"']/g, (char) => ({
    '&': '&amp;',
    '<': '&lt;',
    '>': '&gt;',
    '"': '&quot;',
    "'": '&#039;',
}[char]));

const documentFields = [
    'sujeto_tipo',
    'sujeto_codigo',
    'sujeto_nombre',
    'categoria',
    'documento_tipo',
    'requerido',
    'estado',
    'nivel_riesgo',
    'fecha_documento',
    'fecha_vencimiento',
    'observaciones',
];

const documentDate = (value) => {
    if (!value || !/^\d{4}-\d{2}-\d{2}$/.test(String(value))) {
        return value ?? '';
    }
    const [year, month, day] = String(value).split('-');
    return `${day}/${month}/${year}`;
};

const documentStateClass = (value) => String(value ?? '').toLowerCase().replace(/\s+/g, '-');

const documentSubjectKey = (row) => `${row.sujeto_tipo ?? ''}|${row.sujeto_codigo ?? ''}|${row.sujeto_nombre ?? ''}`;

const documentModuleForType = (type) => {
    if (type === 'Protocolo general') return { key: 'general', label: '00_Protocolo general' };
    if (type === 'Persona/Familia') return { key: 'familia', label: '01_Familia' };
    if (type === 'Empresa') return { key: 'empresas', label: '02_Empresas' };
    if (type === 'Accionista/Participacion') return { key: 'accionistas', label: '03_Accionistas' };
    if (type === 'Activo/Patrimonio') return { key: 'patrimonio', label: '04_Patrimonio' };
    return { key: 'otros', label: 'Otros documentos' };
};

const parseDocumentDataset = (form, key, fallback) => {
    try {
        return JSON.parse(form.dataset[key] || '');
    } catch {
        return fallback;
    }
};

const renderDocumentSummary = (summary) => {
    const total = document.querySelector('[data-document-total]');
    const missing = document.querySelector('[data-document-missing]');
    const requested = document.querySelector('[data-document-requested]');
    const received = document.querySelector('[data-document-received]');
    const expired = document.querySelector('[data-document-expired]');
    total && (total.textContent = String(summary.total ?? 0));
    missing && (missing.textContent = String(summary.faltantes ?? 0));
    requested && (requested.textContent = String((summary.solicitados ?? 0) + (summary.revision ?? 0)));
    received && (received.textContent = String(summary.recibidos ?? 0));
    expired && (expired.textContent = String(summary.vencidos ?? 0));
};

const renderDocumentRow = (row, basePath) => {
    const tr = document.createElement('tr');
    tr.dataset.documentRow = row.id;
    tr.dataset.subjectKey = documentSubjectKey(row);
    tr.dataset.row = JSON.stringify(row);
    tr.dataset.state = row.estado ?? '';
    const file = row.archivo_ruta
        ? `<a href="${documentEscape(basePath + row.archivo_ruta)}" target="_blank" rel="noopener">Ver archivo</a>`
        : '<span class="document-missing">Sin archivo</span>';
    tr.innerHTML = `
        <td data-label="Codigo"><code>${documentEscape(row.codigo)}</code></td>
        <td data-label="Documento">
            <strong>${documentEscape(row.documento_tipo)}</strong>
            <span>${documentEscape(row.categoria)}</span>
            <small class="document-date-line">Fecha documental: ${documentEscape(documentDate(row.fecha_documento) || 'Sin fecha')}</small>
        </td>
        <td data-label="Requerido">${documentEscape(row.requerido)}</td>
        <td data-label="Estado"><span class="document-state state-${documentEscape(documentStateClass(row.estado))}">${documentEscape(row.estado)}</span></td>
        <td data-label="Archivo">${file}</td>
        <td data-label="Actualizado">${documentEscape(documentDate(String(row.updated_at || '').slice(0, 10)))}</td>
        <td data-label="Vencimiento">${documentEscape(documentDate(row.fecha_vencimiento))}</td>
        <td data-label="Observaciones">${documentEscape(row.observaciones)}</td>
        <td data-label="Acciones" class="document-row-actions"><button type="button" data-edit-document>Editar</button><button type="button" data-delete-document>Desactivar</button></td>
    `;
    return tr;
};

const recalcDocumentGroup = (group) => {
    if (!(group instanceof HTMLElement)) {
        return;
    }
    const rows = [...group.querySelectorAll('[data-document-row]')].filter((row) => row instanceof HTMLElement);
    const total = rows.length;
    const missing = rows.filter((row) => ['Faltante', 'Solicitado', 'En revision', 'Vencido'].includes(row.getAttribute('data-state') || '')).length;
    const updated = rows.filter((row) => !row.querySelector('.document-missing')).length;
    const totalTarget = group.querySelector('[data-group-total]');
    const missingTarget = group.querySelector('[data-group-missing]');
    const updatedTarget = group.querySelector('[data-group-updated]');
    totalTarget && (totalTarget.textContent = String(total));
    missingTarget && (missingTarget.textContent = String(missing));
    updatedTarget && (updatedTarget.textContent = String(updated));
    const missingWrap = missingTarget?.parentElement;
    missingWrap?.classList.toggle('is-danger', missing > 0);
    missingWrap?.classList.toggle('is-ok', missing === 0);
};

const recalcDocumentModule = (module) => {
    if (!(module instanceof HTMLElement)) {
        return;
    }
    const rows = [...module.querySelectorAll('[data-document-row]')].filter((row) => row instanceof HTMLElement);
    const total = rows.length;
    const pending = rows.filter((row) => ['Faltante', 'Solicitado', 'En revision', 'Vencido'].includes(row.getAttribute('data-state') || '')).length;
    const updated = rows.filter((row) => !row.querySelector('.document-missing')).length;
    const stats = module.querySelector('.document-module-stats');
    if (!stats) {
        return;
    }
    const items = stats.querySelectorAll('strong');
    if (items[0]) items[0].textContent = String(total);
    if (items[1]) items[1].textContent = String(pending);
    if (items[2]) items[2].textContent = String(updated);
    const pendingWrap = items[1]?.parentElement;
    pendingWrap?.classList.toggle('is-danger', pending > 0);
    pendingWrap?.classList.toggle('is-ok', pending === 0);
};

const renderDocumentGroup = (row, basePath) => {
    const details = document.createElement('details');
    details.className = 'document-group';
    details.dataset.documentGroup = '';
    details.dataset.subjectKey = documentSubjectKey(row);
    details.open = true;
    details.innerHTML = `
        <summary>
            <span class="document-group-title">
                <strong>${documentEscape(row.sujeto_nombre)}</strong>
                <small>${documentEscape(row.sujeto_tipo)} ${documentEscape(row.sujeto_codigo)}</small>
            </span>
            <span class="document-group-stats">
                <span><strong data-group-total>0</strong> docs</span>
                <span class="is-danger"><strong data-group-missing>0</strong> pendientes</span>
                <span><strong data-group-updated>0</strong> actualizados</span>
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
                <tbody></tbody>
            </table>
        </div>
    `;
    details.querySelector('tbody')?.append(renderDocumentRow(row, basePath));
    recalcDocumentGroup(details);
    return details;
};

const findDocumentGroup = (container, subjectKey) => [...container.querySelectorAll('[data-document-group]')]
    .find((group) => group instanceof HTMLElement && group.dataset.subjectKey === subjectKey);

const renderDocumentModule = (row) => {
    const moduleInfo = documentModuleForType(row.sujeto_tipo);
    const details = document.createElement('details');
    details.className = 'document-module';
    details.dataset.documentModule = '';
    details.dataset.moduleKey = moduleInfo.key;
    details.open = true;
    details.innerHTML = `
        <summary>
            <span class="document-module-title"><strong>${documentEscape(moduleInfo.label)}</strong><small>1 expediente documental</small></span>
            <span class="document-module-stats">
                <span><strong>0</strong> documentos</span>
                <span class="is-danger"><strong>0</strong> pendientes</span>
                <span><strong>0</strong> actualizados</span>
            </span>
        </summary>
        <div class="document-groups"></div>
    `;
    return details;
};

const findDocumentModule = (container, row) => {
    const moduleInfo = documentModuleForType(row.sujeto_tipo);
    return [...container.querySelectorAll('[data-document-module]')]
        .find((module) => module instanceof HTMLElement && module.dataset.moduleKey === moduleInfo.key);
};

const placeDocumentRow = (container, row, nextRow, basePath) => {
    const subjectKey = documentSubjectKey(row);
    let module = findDocumentModule(container, row);
    if (!(module instanceof HTMLElement)) {
        module = renderDocumentModule(row);
        container.prepend(module);
    }
    const groupContainer = module.querySelector('.document-groups');
    if (!(groupContainer instanceof HTMLElement)) {
        return;
    }
    let group = findDocumentGroup(groupContainer, subjectKey);
    if (!(group instanceof HTMLElement)) {
        group = renderDocumentGroup(row, basePath);
        groupContainer.prepend(group);
        recalcDocumentModule(module);
        return;
    }
    group.querySelector('tbody')?.prepend(nextRow);
    if (group instanceof HTMLDetailsElement) {
        group.open = true;
    }
    recalcDocumentGroup(group);
    recalcDocumentModule(module);
};

const fillDocumentTypes = (form, suggestions) => {
    const list = form.querySelector('[data-document-type-list]');
    const type = form.elements.sujeto_tipo?.value ?? '';
    if (!(list instanceof HTMLDataListElement)) {
        return;
    }
    list.innerHTML = '';
    (suggestions[type] ?? []).forEach((name) => {
        const option = document.createElement('option');
        option.value = name;
        list.append(option);
    });
};

const fillDocumentSubjects = (form, subjects) => {
    const select = form.querySelector('[data-document-subject-select]');
    const type = form.elements.sujeto_tipo?.value ?? '';
    if (!(select instanceof HTMLSelectElement)) {
        return;
    }
    const previous = select.value;
    select.innerHTML = '<option value="" disabled selected hidden>Seleccione...</option>';
    subjects.filter((subject) => subject.tipo === type).forEach((subject) => {
        const option = document.createElement('option');
        option.value = `${subject.codigo}|${subject.nombre}`;
        option.textContent = `${subject.codigo} - ${subject.nombre}`;
        select.append(option);
    });
    select.value = previous;
};

const updateSubjectHiddenFields = (form) => {
    const select = form.querySelector('[data-document-subject-select]');
    if (!(select instanceof HTMLSelectElement)) {
        return;
    }
    const [code = '', name = ''] = select.value.split('|');
    form.elements.sujeto_codigo.value = code;
    form.elements.sujeto_nombre.value = name;
};

const fillDocumentForm = (form, row = null, subjects = [], suggestions = {}) => {
    form.reset();
    form.elements.id.value = row?.id ?? '';
    form.elements.codigo.value = row?.codigo ?? 'Automatico';
    documentFields.forEach((field) => {
        if (form.elements[field]) {
            form.elements[field].value = row?.[field] ?? '';
        }
    });
    if (!row) {
        form.elements.requerido.value = 'Si';
        form.elements.estado.value = 'Faltante';
        form.elements.nivel_riesgo.value = 'Medio';
    }
    fillDocumentSubjects(form, subjects);
    const select = form.querySelector('[data-document-subject-select]');
    if (select instanceof HTMLSelectElement && row) {
        select.value = `${row.sujeto_codigo ?? ''}|${row.sujeto_nombre ?? ''}`;
    }
    fillDocumentTypes(form, suggestions);
};

const applyDocumentTemplate = (form, template = null) => {
    if (!template || typeof template !== 'object') {
        return;
    }
    documentFields.forEach((field) => {
        if (form.elements[field] && template[field] !== undefined) {
            form.elements[field].value = template[field] ?? '';
        }
    });
    fillDocumentSubjects(form, parseDocumentDataset(form, 'documentSubjects', []));
    const select = form.querySelector('[data-document-subject-select]');
    if (select instanceof HTMLSelectElement && (template.sujeto_codigo || template.sujeto_nombre)) {
        select.value = `${template.sujeto_codigo ?? ''}|${template.sujeto_nombre ?? ''}`;
    }
    fillDocumentTypes(form, parseDocumentDataset(form, 'documentSuggestions', {}));
    updateSubjectHiddenFields(form);
};

const applyDocumentFilter = (filter) => {
    document.querySelectorAll('[data-document-filter]').forEach((button) => {
        button.classList.toggle('active', button.getAttribute('data-document-filter') === filter);
    });
    document.querySelectorAll('[data-document-row]').forEach((row) => {
        const state = row.getAttribute('data-state') || '';
        const show = filter === 'todos'
            || (filter === 'faltantes' && state === 'Faltante')
            || (filter === 'recibidos' && state === 'Recibido')
            || (filter === 'vencidos' && state === 'Vencido');
        if (row instanceof HTMLElement) {
            row.hidden = !show;
        }
    });
    document.querySelectorAll('[data-document-group]').forEach((group) => {
        const visibleRows = [...group.querySelectorAll('[data-document-row]')].filter((row) => row instanceof HTMLElement && !row.hidden);
        if (group instanceof HTMLDetailsElement) {
            group.hidden = visibleRows.length === 0;
            if (filter !== 'todos' && visibleRows.length > 0) {
                group.open = true;
            }
        }
    });
    document.querySelectorAll('[data-document-module]').forEach((module) => {
        const visibleGroups = [...module.querySelectorAll('[data-document-group]')].filter((group) => group instanceof HTMLElement && !group.hidden);
        if (module instanceof HTMLDetailsElement) {
            module.hidden = visibleGroups.length === 0;
            if (filter !== 'todos' && visibleGroups.length > 0) {
                module.open = true;
            }
        }
    });
};

const documentForm = document.querySelector('[data-document-form]');
if (documentForm instanceof HTMLFormElement) {
    const workspace = documentForm.closest('[data-workspace]');
    const basePath = workspace?.dataset.basePath ?? '';
    const modal = document.querySelector('[data-document-modal]');
    if (modal instanceof HTMLElement && modal.parentElement !== document.body) {
        document.body.append(modal);
    }
    const modalTitle = document.querySelector('[data-document-modal-title]');
    const groupsContainer = document.querySelector('[data-document-groups]');
    const status = document.querySelector('[data-document-status]');
    const count = document.querySelector('[data-document-count]');
    const subjects = parseDocumentDataset(documentForm, 'documentSubjects', []);
    const suggestions = parseDocumentDataset(documentForm, 'documentSuggestions', {});
    let lastDocumentTrigger = null;
    let activeFilter = 'todos';

    const openDocumentModal = (row = null, trigger = null, template = null) => {
        lastDocumentTrigger = trigger;
        fillDocumentForm(documentForm, row, subjects, suggestions);
        applyDocumentTemplate(documentForm, template);
        if (modalTitle) {
            modalTitle.textContent = row ? 'Editar documento' : 'Nuevo documento';
        }
        if (modal instanceof HTMLElement) {
            modal.hidden = false;
            document.body.classList.add('modal-open');
        }
        status && (status.textContent = '');
        window.setTimeout(() => documentForm.elements.sujeto_tipo?.focus(), 0);
    };

    const closeDocumentModal = () => {
        if (modal instanceof HTMLElement) {
            modal.hidden = true;
            document.body.classList.remove('modal-open');
        }
        fillDocumentForm(documentForm, null, subjects, suggestions);
        status && (status.textContent = '');
        if (lastDocumentTrigger instanceof HTMLElement) {
            lastDocumentTrigger.focus();
        }
    };

    documentForm.elements.sujeto_tipo?.addEventListener('change', () => {
        documentForm.elements.sujeto_codigo.value = '';
        documentForm.elements.sujeto_nombre.value = '';
        fillDocumentSubjects(documentForm, subjects);
        fillDocumentTypes(documentForm, suggestions);
    });

    documentForm.querySelector('[data-document-subject-select]')?.addEventListener('change', () => updateSubjectHiddenFields(documentForm));

    documentForm.addEventListener('submit', async (event) => {
        event.preventDefault();
        if (!(groupsContainer instanceof HTMLElement)) {
            return;
        }
        updateSubjectHiddenFields(documentForm);
        const id = documentForm.elements.id.value;
        const url = id ? `${basePath}/protocolo-familiar/documentos/${id}` : `${basePath}/protocolo-familiar/documentos`;
        status && (status.textContent = 'Guardando...');
        try {
            const response = await fetch(url, { method: 'POST', body: new FormData(documentForm), credentials: 'same-origin' });
            const payload = await response.json();
            if (!response.ok || !payload.ok) {
                throw new Error(payload.error || 'No fue posible guardar.');
            }
            const nextRow = renderDocumentRow(payload.row, basePath);
            const current = groupsContainer.querySelector(`[data-document-row="${payload.row.id}"]`);
            if (current) {
                const oldGroup = current.closest('[data-document-group]');
                const oldModule = current.closest('[data-document-module]');
                if (current.getAttribute('data-subject-key') === documentSubjectKey(payload.row)) {
                    current.replaceWith(nextRow);
                    recalcDocumentGroup(oldGroup);
                    recalcDocumentModule(oldModule);
                } else {
                    current.remove();
                    recalcDocumentGroup(oldGroup);
                    if (oldGroup instanceof HTMLElement && !oldGroup.querySelector('[data-document-row]')) {
                        oldGroup.remove();
                    }
                    recalcDocumentModule(oldModule);
                    if (oldModule instanceof HTMLElement && !oldModule.querySelector('[data-document-row]')) {
                        oldModule.remove();
                    }
                    placeDocumentRow(groupsContainer, payload.row, nextRow, basePath);
                }
            } else {
                placeDocumentRow(groupsContainer, payload.row, nextRow, basePath);
                count && (count.textContent = String(Number.parseInt(count.textContent || '0', 10) + 1));
            }
            renderDocumentSummary(payload.summary || {});
            applyDocumentFilter(activeFilter);
            document.dispatchEvent(new CustomEvent('protocolo:document-saved', { detail: { row: payload.row, summary: payload.summary || {} } }));
            status && (status.textContent = 'Guardado.');
            closeDocumentModal();
        } catch (error) {
            status && (status.textContent = error instanceof Error ? error.message : 'No fue posible guardar.');
        }
    });

    document.querySelector('[data-new-document]')?.addEventListener('click', (event) => {
        openDocumentModal(null, event.currentTarget instanceof HTMLElement ? event.currentTarget : null);
    });

    document.addEventListener('protocolo:open-document', (event) => {
        const row = event.detail?.row || null;
        const trigger = event.detail?.trigger instanceof HTMLElement ? event.detail.trigger : null;
        const template = event.detail?.template || null;
        openDocumentModal(row, trigger, template);
    });

    document.addEventListener('protocolo:delete-document', async (event) => {
        const row = event.detail?.row || null;
        if (!row?.id || !window.confirm('Quitar este documento del checklist?')) {
            return;
        }
        const data = new FormData();
        data.append('csrf_token', workspace?.dataset.csrf ?? '');
        const response = await fetch(`${basePath}/protocolo-familiar/documentos/${row.id}/eliminar`, { method: 'POST', body: data, credentials: 'same-origin' });
        const payload = await response.json();
        if (!response.ok || !payload.ok) {
            window.alert(payload.error || 'No fue posible quitar el documento.');
            return;
        }
        const tr = groupsContainer?.querySelector(`[data-document-row="${CSS.escape(row.id)}"]`);
        if (tr instanceof HTMLElement) {
            const group = tr.closest('[data-document-group]');
            const module = tr.closest('[data-document-module]');
            tr.remove();
            recalcDocumentGroup(group);
            if (group instanceof HTMLElement && !group.querySelector('[data-document-row]')) {
                group.remove();
            }
            recalcDocumentModule(module);
            if (module instanceof HTMLElement && !module.querySelector('[data-document-row]')) {
                module.remove();
            }
            count && (count.textContent = String(Math.max(0, Number.parseInt(count.textContent || '0', 10) - 1)));
        }
        renderDocumentSummary(payload.summary || {});
        document.dispatchEvent(new CustomEvent('protocolo:document-deleted', { detail: { row, summary: payload.summary || {} } }));
    });

    document.querySelector('[data-cancel-document]')?.addEventListener('click', () => {
        fillDocumentForm(documentForm, null, subjects, suggestions);
        status && (status.textContent = '');
    });

    document.querySelector('[data-close-document]')?.addEventListener('click', closeDocumentModal);

    modal?.addEventListener('click', (event) => {
        if (event.target === modal) {
            event.preventDefault();
        }
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && modal instanceof HTMLElement && !modal.hidden) {
            closeDocumentModal();
        }
    });

    document.querySelectorAll('[data-document-filter]').forEach((button) => {
        button.addEventListener('click', () => {
            activeFilter = button.getAttribute('data-document-filter') || 'todos';
            applyDocumentFilter(activeFilter);
        });
    });

    groupsContainer?.addEventListener('click', async (event) => {
        const target = event.target;
        if (!(target instanceof Element)) {
            return;
        }
        const tr = target.closest('[data-document-row]');
        if (!(tr instanceof HTMLTableRowElement)) {
            return;
        }
        const row = JSON.parse(tr.dataset.row || '{}');
        if (target.closest('[data-edit-document]')) {
            openDocumentModal(row, target.closest('[data-edit-document]'));
            return;
        }
        if (target.closest('[data-delete-document]')) {
            if (!window.confirm('Desactivar este documento del checklist?')) {
                return;
            }
            const data = new FormData();
            data.append('csrf_token', workspace?.dataset.csrf ?? '');
            const response = await fetch(`${basePath}/protocolo-familiar/documentos/${row.id}/eliminar`, { method: 'POST', body: data, credentials: 'same-origin' });
            const payload = await response.json();
            if (response.ok && payload.ok) {
                const group = tr.closest('[data-document-group]');
                const module = tr.closest('[data-document-module]');
                tr.remove();
                recalcDocumentGroup(group);
                if (group instanceof HTMLElement && !group.querySelector('[data-document-row]')) {
                    group.remove();
                }
                recalcDocumentModule(module);
                if (module instanceof HTMLElement && !module.querySelector('[data-document-row]')) {
                    module.remove();
                }
                count && (count.textContent = String(Math.max(0, Number.parseInt(count.textContent || '0', 10) - 1)));
                renderDocumentSummary(payload.summary || {});
                status && (status.textContent = 'Registro desactivado.');
            }
        }
    });
}
