(() => {
    const formRoot = document.querySelector('.ea-email-template-test');
    if (!formRoot) {
        return;
    }

    const templateSelect = formRoot.querySelector('select[name$="[template]"]');
    const orderRow = formRoot.querySelector('[data-context-field="order"]');
    const userRow = formRoot.querySelector('[data-context-field="user"]');
    const orderSelect = orderRow?.querySelector('select');
    const userSelect = userRow?.querySelector('select');

    const syncContextField = () => {
        const selectedOption = templateSelect?.selectedOptions[0];
        const context = selectedOption?.dataset.context || '';

        orderRow?.classList.toggle('d-none', context !== 'order');
        userRow?.classList.toggle('d-none', context !== 'user');

        if (context === 'order' && userSelect) {
            userSelect.value = '';
        }

        if (context === 'user' && orderSelect) {
            orderSelect.value = '';
        }
    };

    templateSelect?.addEventListener('change', syncContextField);
    syncContextField();
})();
