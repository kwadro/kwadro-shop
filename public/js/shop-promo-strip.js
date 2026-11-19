(() => {
    const roots = document.querySelectorAll('[data-promo-strip]');
    if (!roots.length) {
        return;
    }

    const readDismissed = (key) => {
        try {
            return window.localStorage.getItem(key) === '1';
        } catch (e) {
            return false;
        }
    };

    const writeDismissed = (key) => {
        try {
            window.localStorage.setItem(key, '1');
        } catch (e) {
            // Ignore quota / private mode errors.
        }
    };

    roots.forEach((root) => {
        const dismissKey = root.dataset.dismissKey || '';
        const dismissible = root.dataset.dismissible === '1';
        if (dismissible && dismissKey && readDismissed(dismissKey)) {
            root.remove();
            return;
        }

        root.hidden = false;

        const items = Array.from(root.querySelectorAll('[data-promo-strip-item]'));
        const closeBtn = root.querySelector('[data-promo-strip-close]');
        const intervalMs = Math.max(
            1000,
            parseInt(root.dataset.interval || '5000', 10) || 5000,
        );

        if (closeBtn) {
            closeBtn.addEventListener('click', () => {
                if (dismissKey) {
                    writeDismissed(dismissKey);
                }
                root.remove();
            });
        }

        if (items.length <= 1) {
            return;
        }

        let index = 0;
        let timer = 0;

        const show = (nextIndex) => {
            items[index].classList.remove('is-active');
            items[index].setAttribute('aria-hidden', 'true');
            index = nextIndex;
            items[index].classList.add('is-active');
            items[index].removeAttribute('aria-hidden');
        };

        const tick = () => {
            show((index + 1) % items.length);
        };

        const start = () => {
            stop();
            timer = window.setInterval(tick, intervalMs);
        };

        const stop = () => {
            if (timer) {
                window.clearInterval(timer);
                timer = 0;
            }
        };

        root.addEventListener('mouseenter', stop);
        root.addEventListener('mouseleave', start);
        root.addEventListener('focusin', stop);
        root.addEventListener('focusout', (event) => {
            if (!root.contains(event.relatedTarget)) {
                start();
            }
        });

        start();
    });
})();
