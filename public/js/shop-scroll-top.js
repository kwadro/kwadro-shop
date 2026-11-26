(() => {
    const button = document.querySelector('[data-scroll-top]');
    if (!button) {
        return;
    }

    const threshold = 320;
    let ticking = false;

    const sync = () => {
        const visible = window.scrollY > threshold;
        button.classList.toggle('is-visible', visible);
        button.toggleAttribute('hidden', !visible);
        ticking = false;
    };

    const onScroll = () => {
        if (ticking) {
            return;
        }
        ticking = true;
        window.requestAnimationFrame(sync);
    };

    button.addEventListener('click', () => {
        const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        window.scrollTo({
            top: 0,
            behavior: reduceMotion ? 'auto' : 'smooth',
        });
    });

    window.addEventListener('scroll', onScroll, { passive: true });
    sync();
})();
