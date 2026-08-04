const refreshTabs = (root) => {
    const tabs = root.closest('[data-tabs]');
    if (!tabs) {
        return;
    }
    const list = tabs.querySelector('.tab-list');
    const panels = [...tabs.querySelectorAll('[data-section]')];
    if (!list) {
        return;
    }
    list.innerHTML = '';
    panels.forEach((panel, index) => {
        const order = panel.querySelector('input[name$="[orden]"]')?.value || String(index + 1);
        const id = `section-${index}`;
        panel.id = id;
        panel.classList.toggle('active', index === 0);
        const button = document.createElement('button');
        button.type = 'button';
        button.setAttribute('role', 'tab');
        button.dataset.tabTarget = id;
        button.className = index === 0 ? 'active' : '';
        button.textContent = order;
        list.append(button);
    });
};

const updateSectionLabels = (container) => {
    container.querySelectorAll('[data-section]').forEach((section, index) => {
        const label = section.querySelector('strong');
        if (label) {
            label.textContent = `Seccion ${index + 1}`;
        }
    });
    refreshTabs(container);
};

const createSection = (index) => {
    const article = document.createElement('article');
    article.className = 'section tab-panel';
    article.dataset.section = '';
    article.innerHTML = `<div class="split"><strong>Seccion</strong><button type="button" data-remove-section>Quitar</button></div><div class="cols"><label>Clave<input name="secciones[${index}][clave]" required></label><label>Titulo<input name="secciones[${index}][titulo]" required></label><label>Orden<input name="secciones[${index}][orden]" type="number" value="${index + 1}"></label></div><label>Contenido<textarea name="secciones[${index}][contenido]" rows="8" required>Pendiente por documentar.</textarea></label>`;
    return article;
};

document.addEventListener('click', (event) => {
    const target = event.target;
    if (!(target instanceof Element)) {
        return;
    }

    const tabButton = target.closest('[data-tab-target]');
    if (tabButton) {
        const tabs = tabButton.closest('[data-tabs]');
        if (!tabs) {
            return;
        }
        tabs.querySelectorAll('[data-tab-target]').forEach((button) => button.classList.toggle('active', button === tabButton));
        tabs.querySelectorAll('.tab-panel').forEach((panel) => panel.classList.toggle('active', panel.id === tabButton.dataset.tabTarget));
        return;
    }

    const confirmForm = target.closest('form[data-confirm]');
    if (confirmForm && target.closest('button[type="submit"],button:not([type])')) {
        if (!window.confirm(confirmForm.dataset.confirm || 'Confirmar accion?')) {
            event.preventDefault();
        }
        return;
    }

    const add = target.closest('[data-add-section]');
    if (add) {
        const form = add.closest('form') ?? document;
        const container = form.querySelector('[data-sections]');
        if (!(container instanceof HTMLElement)) {
            return;
        }
        const index = Number.parseInt(container.dataset.nextIndex ?? '0', 10);
        container.append(createSection(index));
        container.dataset.nextIndex = String(index + 1);
        updateSectionLabels(container);
        const tabs = container.closest('[data-tabs]');
        tabs?.querySelector(`[data-tab-target="section-${container.querySelectorAll('[data-section]').length - 1}"]`)?.click();
        return;
    }

    const remove = target.closest('[data-remove-section]');
    if (remove) {
        const container = remove.closest('[data-sections]');
        if (!(container instanceof HTMLElement) || container.querySelectorAll('[data-section]').length <= 1) {
            return;
        }
        remove.closest('[data-section]')?.remove();
        updateSectionLabels(container);
    }
});

document.addEventListener('input', (event) => {
    const target = event.target;
    if (target instanceof HTMLInputElement && target.name.endsWith('[orden]')) {
        const container = target.closest('[data-sections]');
        if (container) {
            refreshTabs(container);
        }
    }
});

const updateDependent = (parent) => {
    const key = parent.dataset.dependentParent;
    if (!key) {
        return;
    }
    const child = document.querySelector(`[data-dependent-select="${key}"]`);
    if (!(child instanceof HTMLSelectElement)) {
        return;
    }
    const parentCode = parent.value;
    let first = '';
    child.querySelectorAll('option').forEach((option) => {
        const optionParent = option.dataset.parentCode ?? '';
        const placeholder = option.value === '';
        const visible = placeholder || optionParent === parentCode;
        option.hidden = !visible;
        option.disabled = !visible;
        if (!placeholder && visible && first === '') {
            first = option.value;
        }
    });
    child.value = first;
};

document.querySelectorAll('[data-dependent-parent]').forEach((parent) => {
    if (parent instanceof HTMLSelectElement) {
        updateDependent(parent);
        parent.addEventListener('change', () => updateDependent(parent));
    }
});
