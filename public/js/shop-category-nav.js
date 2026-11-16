(() => {
    const list = document.querySelector('[data-category-nav-list]');
    if (!list) {
        return;
    }

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
