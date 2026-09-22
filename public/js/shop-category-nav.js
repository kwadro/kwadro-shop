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
})();
