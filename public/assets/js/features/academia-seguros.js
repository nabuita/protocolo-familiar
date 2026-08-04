const insuranceAcademyRoot = document.querySelector('[data-insurance-academy-page]');

if (insuranceAcademyRoot instanceof HTMLElement) {
    const buttons = [...insuranceAcademyRoot.querySelectorAll('[data-insurance-academy-ramo]')];
    const cards = [...insuranceAcademyRoot.querySelectorAll('[data-insurance-academy-card]')];
    const search = insuranceAcademyRoot.querySelector('[data-insurance-academy-search]');
    const macro = insuranceAcademyRoot.querySelector('[data-insurance-academy-macro]');

    const activate = (ramo) => {
        buttons.forEach((button) => button.classList.toggle('is-active', button.dataset.insuranceAcademyRamo === ramo));
        cards.forEach((card) => card.classList.toggle('is-active', card.dataset.insuranceAcademyCard === ramo));
    };

    const applyFilter = () => {
        const term = String(search?.value || '').trim().toLowerCase();
        const macroValue = String(macro?.value || '');
        let firstVisible = null;
        buttons.forEach((button) => {
            const matchesMacro = !macroValue || button.dataset.insuranceAcademyMacroValue === macroValue;
            const matchesText = !term || String(button.dataset.insuranceAcademySearchText || '').includes(term);
            const visible = matchesMacro && matchesText;
            button.hidden = !visible;
            if (visible && !firstVisible) {
                firstVisible = button;
            }
        });
        const active = buttons.find((button) => button.classList.contains('is-active') && !button.hidden);
        if (!active && firstVisible instanceof HTMLElement) {
            activate(firstVisible.dataset.insuranceAcademyRamo || '');
        }
        if (!firstVisible) {
            cards.forEach((card) => card.classList.remove('is-active'));
        }
    };

    buttons.forEach((button) => {
        button.addEventListener('click', () => activate(button.dataset.insuranceAcademyRamo || ''));
    });
    search?.addEventListener('input', applyFilter);
    macro?.addEventListener('change', applyFilter);
}
