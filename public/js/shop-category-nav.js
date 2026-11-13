(() => {
    const toggle = document.querySelector('[data-category-nav-toggle]');
    const list = document.querySelector('[data-category-nav-list]');
    if (!toggle || !list) {
        return;
    }

    toggle.addEventListener('click', () => {
        const open = list.classList.toggle('is-open');
        toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        document.body.classList.toggle('shop-category-nav-open', open);
    });

    list.querySelectorAll('[data-category-nav-expand]').forEach((button) => {
        button.addEventListener('click', (event) => {
            event.preventDefault();
            event.stopPropagation();

            const item = button.closest('li');
            if (!item) {
                return;
            }

            const open = item.classList.toggle('is-expanded');
            button.setAttribute('aria-expanded', open ? 'true' : 'false');
        });
    });
})();
