const decisionStateClass = (value) => String(value ?? 'Pendiente de analizar')
    .toLowerCase()
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .replace(/[ /]+/g, '-');

const decisionRequiredValue = (form, name) => String(form.elements[name]?.value ?? '').trim();
let activeDecisionCategory = document.querySelector('[data-decision-tab].is-active')?.dataset.decisionTab || 'CAT-01';

const updateGuidedDecisionFields = (form) => {
    if (!form.classList.contains('is-guided-form')) return;
    const applies = decisionRequiredValue(form, 'aplica');
    const decision = decisionRequiredValue(form, 'estado_decision');
    const implementation = decisionRequiredValue(form, 'estado_implementacion');
    const targetDate = decisionRequiredValue(form, 'fecha_objetivo');
    const hasExistingValue = (field) => String(field.querySelector('textarea, input')?.value ?? '').trim() !== '';
    form.querySelectorAll('[data-decision-conditional]').forEach((field) => {
        if (!(field instanceof HTMLElement)) return;
        const type = field.dataset.decisionConditional;
        let visible = hasExistingValue(field);
        if (type === 'aplicabilidad') visible = visible || ['No aplica', 'Aplicará en el futuro'].includes(applies);
        if (type === 'bloqueo') visible = visible || implementation === 'Bloqueada';
        if (type === 'aplazamiento') visible = visible || ['Aplazada', 'Rechazada'].includes(decision) || implementation === 'Cancelada';
        if (type === 'sin_fecha') visible = visible || (['En preparación', 'En ejecución'].includes(implementation) && !targetDate);
        if (type === 'verificacion') visible = visible || implementation === 'Verificada';
        if (type === 'cambio') visible = visible || decision !== 'Pendiente de analizar' || implementation !== 'No iniciada';
        field.hidden = !visible;
    });
};

const validateDecisionForm = (form) => {
    const applies = decisionRequiredValue(form, 'aplica');
    const decision = decisionRequiredValue(form, 'estado_decision');
    const implementation = decisionRequiredValue(form, 'estado_implementacion');
    const response = decisionRequiredValue(form, 'respuesta');
    const approvalDate = decisionRequiredValue(form, 'fecha_aprobacion');
    const responsible = decisionRequiredValue(form, 'responsable');
    const targetDate = decisionRequiredValue(form, 'fecha_objetivo');
    const nextReview = decisionRequiredValue(form, 'fecha_proxima_revision');
    const applicabilityReason = decisionRequiredValue(form, 'justificacion_aplicabilidad');
    const blockReason = decisionRequiredValue(form, 'motivo_bloqueo');
    const postponeReason = decisionRequiredValue(form, 'motivo_aplazamiento_rechazo');
    const noTargetReason = decisionRequiredValue(form, 'justificacion_sin_fecha_objetivo');
    const implementedAt = decisionRequiredValue(form, 'fecha_real_implementacion');
    const verifiedBy = decisionRequiredValue(form, 'verificado_por');
    const verifiedAt = decisionRequiredValue(form, 'fecha_verificacion');

    if (applies === 'No aplica' && !applicabilityReason) return 'No aplica exige justificacion.';
    if (applies === 'Aplicará en el futuro' && (!applicabilityReason || !nextReview)) return 'Aplicara en el futuro exige justificacion y proxima revision.';
    if (decision === 'Aplazada' && (!postponeReason || !nextReview)) return 'Aplazada exige motivo y proxima revision.';
    if (decision === 'Rechazada' && !postponeReason) return 'Rechazada exige justificacion.';
    if (decision === 'Aprobada' && (!response || !approvalDate)) return 'Aprobada exige regla aprobada y fecha de aprobacion.';
    if (implementation === 'Bloqueada' && !blockReason) return 'Bloqueada exige motivo del bloqueo.';
    if (implementation === 'Cancelada' && !postponeReason) return 'Cancelada exige justificacion.';
    if ((implementation === 'En ejecución' || implementation === 'En preparación') && !responsible) return 'Una decision en ejecucion o preparacion exige responsable.';
    if ((implementation === 'En ejecución' || implementation === 'En preparación') && !targetDate && !noTargetReason) return 'Una decision en ejecucion o preparacion exige fecha objetivo o justificacion autorizada.';
    if (implementation === 'Implementada' && !implementedAt) return 'Implementada exige fecha real de implementacion.';
    if (implementation === 'Verificada' && (!verifiedBy || !verifiedAt)) return 'Verificada exige verificador y fecha de verificacion.';
    return '';
};

const updateDecisionSummary = (summary) => {
    const pairs = {
        '[data-decision-total]': summary.total,
        '[data-decision-answered]': summary.respondidas,
        '[data-decision-approved]': summary.aprobadas,
        '[data-decision-implemented]': summary.implementadas,
        '[data-decision-verified]': summary.verificadas,
        '[data-decision-pending]': summary.pendientes,
        '[data-decision-expired]': summary.vencidas,
    };
    Object.entries(pairs).forEach(([selector, value]) => {
        const target = document.querySelector(selector);
        if (target) target.textContent = String(value ?? 0);
    });
    const progress = document.querySelector('[data-decision-progress]');
    if (progress) progress.textContent = `${summary.avance ?? 0}%`;
};

const updateActiveCategorySummary = () => {
    const activePanel = document.querySelector(`[data-decision-category="${activeDecisionCategory}"]`);
    if (!(activePanel instanceof HTMLElement)) return;
    const cards = [...activePanel.querySelectorAll('[data-decision-card]')].filter((card) => card instanceof HTMLElement);
    const total = cards.length;
    const answered = cards.filter((card) => {
        const form = card.querySelector('[data-decision-form]');
        if (!(form instanceof HTMLFormElement)) return false;
        return decisionRequiredValue(form, 'respuesta') !== ''
            || decisionRequiredValue(form, 'responsable') !== ''
            || String(card.dataset.state ?? '') !== 'Pendiente de analizar';
    }).length;
    const approved = cards.filter((card) => card.dataset.state === 'Aprobada').length;
    const implemented = cards.filter((card) => card.dataset.implementation === 'Implementada').length;
    const verified = cards.filter((card) => card.dataset.implementation === 'Verificada').length;
    const expired = cards.filter((card) => String(card.dataset.alerts ?? '').includes('Vencida')).length;
    const pending = cards.filter((card) => card.dataset.state === 'Pendiente de analizar').length;
    const progress = total > 0 ? Math.round((verified / total) * 100) : 0;
    updateDecisionSummary({ total, respondidas: answered, aprobadas: approved, implementadas: implemented, verificadas: verified, pendientes: pending, vencidas: expired, avance: progress });
    const activeTotal = document.querySelector('[data-decision-active-total]');
    if (activeTotal) activeTotal.textContent = String(total);
};

const activateDecisionCategory = (code) => {
    activeDecisionCategory = code;
    document.querySelectorAll('[data-decision-tab]').forEach((tab) => {
        if (!(tab instanceof HTMLElement)) return;
        tab.classList.toggle('is-active', tab.dataset.decisionTab === code);
    });
    document.querySelectorAll('[data-decision-category]').forEach((category) => {
        if (!(category instanceof HTMLElement)) return;
        const active = category.dataset.decisionCategory === code;
        category.hidden = !active;
        category.dataset.tabPanelActive = active ? 'true' : 'false';
        if (active && category instanceof HTMLDetailsElement) category.open = true;
    });
    updateActiveCategorySummary();
    applyDecisionFilters();
};

const applyDecisionFilters = () => {
    const query = String(document.querySelector('[data-decision-search]')?.value ?? '').trim().toLowerCase();
    const filter = String(document.querySelector('[data-decision-filter]')?.value ?? 'todos');
    let visible = 0;

    document.querySelectorAll('[data-decision-card]').forEach((card) => {
        if (!(card instanceof HTMLElement)) return;
        card.classList.remove('is-search-hit');
        const alerts = String(card.dataset.alerts ?? '');
        const matchesQuery = query === '' || String(card.dataset.search ?? '').includes(query);
        const matchesFilter = filter === 'todos'
            || card.dataset.state === filter
            || card.dataset.implementation === filter
            || alerts.includes(filter);
        const category = card.closest('[data-decision-category]');
        const matchesCategory = category instanceof HTMLElement && category.dataset.decisionCategory === activeDecisionCategory;
        const show = matchesCategory && matchesQuery && matchesFilter;
        card.hidden = !show;
        if (show) {
            visible++;
            if (query !== '') {
                card.classList.add('is-search-hit');
                const category = card.closest('[data-decision-category]');
                if (category instanceof HTMLDetailsElement) category.open = true;
            }
        }
    });

    document.querySelectorAll('[data-decision-category]').forEach((category) => {
        if (!(category instanceof HTMLElement)) return;
        if (category.dataset.decisionCategory !== activeDecisionCategory) {
            category.hidden = true;
            return;
        }
        const visibleCards = [...category.querySelectorAll('[data-decision-card]')].filter((card) => card instanceof HTMLElement && !card.hidden);
        category.hidden = visibleCards.length === 0;
        if (filter !== 'todos' && visibleCards.length > 0 && category instanceof HTMLDetailsElement) {
            category.open = true;
        }
    });

    const visibleTarget = document.querySelector('[data-decision-visible]');
    if (visibleTarget) visibleTarget.textContent = String(visible);
};

const openHashDecision = () => {
    const hash = window.location.hash.replace('#', '');
    if (!/^DEC-\d{3}$/.test(hash)) return;
    const card = document.getElementById(hash);
    if (!(card instanceof HTMLElement)) return;
    const category = card.closest('[data-decision-category]');
    if (category instanceof HTMLElement && category.dataset.decisionCategory) activateDecisionCategory(category.dataset.decisionCategory);
    if (category instanceof HTMLDetailsElement) category.open = true;
    if (card instanceof HTMLDetailsElement) card.open = true;
    card.classList.add('is-search-hit');
    window.setTimeout(() => card.scrollIntoView({ behavior: 'smooth', block: 'start' }), 50);
};

document.querySelectorAll('[data-decision-search]').forEach((input) => input.addEventListener('input', applyDecisionFilters));
document.querySelectorAll('[data-decision-filter]').forEach((select) => select.addEventListener('change', applyDecisionFilters));
document.querySelectorAll('[data-decision-tab]').forEach((tab) => tab.addEventListener('click', () => {
    if (tab instanceof HTMLElement && tab.dataset.decisionTab) activateDecisionCategory(tab.dataset.decisionTab);
}));
window.addEventListener('hashchange', openHashDecision);

document.querySelectorAll('[data-decision-form]').forEach((form) => {
    if (!(form instanceof HTMLFormElement)) return;
    const status = form.querySelector('[data-decision-status]');
    updateGuidedDecisionFields(form);
    form.addEventListener('change', () => updateGuidedDecisionFields(form));
    form.addEventListener('input', (event) => {
        if (event.target instanceof HTMLInputElement || event.target instanceof HTMLTextAreaElement) {
            updateGuidedDecisionFields(form);
        }
    });
    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        const validation = validateDecisionForm(form);
        if (validation) {
            status && (status.textContent = validation);
            return;
        }
        const card = form.closest('[data-decision-card]');
        status && (status.textContent = 'Guardando...');
        try {
            const response = await fetch(form.action, { method: 'POST', body: new FormData(form), credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            const payload = await response.json();
            if (!response.ok || !payload.ok) throw new Error(payload.error || 'No fue posible guardar la decision.');
            const row = payload.row || {};
            if (card instanceof HTMLElement) {
                card.dataset.state = row.estado_decision || card.dataset.state || '';
                card.dataset.implementation = row.estado_implementacion || card.dataset.implementation || '';
                card.dataset.alerts = Array.isArray(row.alertas_calculadas) ? row.alertas_calculadas.join('|') : card.dataset.alerts || '';
                const stateLabel = card.querySelector('[data-decision-state-label]');
                if (stateLabel) {
                    stateLabel.className = `decision-state state-${decisionStateClass(row.estado_decision)}`;
                    stateLabel.textContent = row.estado_decision || '';
                }
                const implementationLabel = card.querySelector('[data-decision-implementation-label]');
                if (implementationLabel) {
                    implementationLabel.className = `decision-state state-${decisionStateClass(row.estado_implementacion)}`;
                    implementationLabel.textContent = row.estado_implementacion || '';
                }
            }
            updateActiveCategorySummary();
            applyDecisionFilters();
            status && (status.textContent = 'Guardado. Reabre la decision para ver historial y alertas recalculadas.');
        } catch (error) {
            status && (status.textContent = error instanceof Error ? error.message : 'No fue posible guardar.');
        }
    });
});

document.querySelectorAll('[data-risk-review-form]').forEach((form) => {
    if (!(form instanceof HTMLFormElement)) return;
    const status = form.querySelector('[data-risk-review-status]');
    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        status && (status.textContent = 'Enviando propuesta...');
        try {
            const response = await fetch(form.action, { method: 'POST', body: new FormData(form), credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            const payload = await response.json();
            if (!response.ok || !payload.ok) throw new Error(payload.error || 'No fue posible enviar a revision.');
            updateActiveCategorySummary();
            status && (status.textContent = 'Propuesta enviada a revision humana. No se creo riesgo definitivo.');
            form.reset();
        } catch (error) {
            status && (status.textContent = error instanceof Error ? error.message : 'No fue posible enviar a revision.');
        }
    });
});

activateDecisionCategory(activeDecisionCategory);
applyDecisionFilters();
openHashDecision();
