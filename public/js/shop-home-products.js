(() => {
    const MOBILE_MQ = '(max-width: 700px)';

    const init = (root) => {
        const track = root.querySelector('.shop-home-products__track');
        const viewport = root.querySelector('.shop-home-products__viewport');
        const prevBtn = root.querySelector('.shop-home-products__nav--prev');
        const nextBtn = root.querySelector('.shop-home-products__nav--next');
        if (!track || !viewport || !prevBtn || !nextBtn) {
            return;
        }

        const count = Math.max(0, parseInt(root.dataset.count || '0', 10) || 0);
        const sliderMin = Math.max(1, parseInt(root.dataset.sliderMin || '5', 10) || 5);
        let originals = Array.from(track.querySelectorAll('[data-home-product]'));
        if (originals.length === 0) {
            return;
        }

        let looping = false;
        let slideWidth = 0;

        const isMobile = () => window.matchMedia(MOBILE_MQ).matches;

        const shouldSlide = () => {
            if (count <= 1) {
                return false;
            }
            return isMobile() ? count > 1 : count > sliderMin;
        };

        const measure = () => {
            const first = track.querySelector('[data-home-product]');
            if (!first) {
                slideWidth = 0;
                return;
            }
            const style = window.getComputedStyle(track);
            const gap = parseFloat(style.columnGap || style.gap || '0') || 0;
            slideWidth = first.getBoundingClientRect().width + gap;
        };

        const clearClones = () => {
            track.querySelectorAll('[data-home-clone]').forEach((node) => node.remove());
            originals = Array.from(track.querySelectorAll('[data-home-product]:not([data-home-clone])'));
        };

        const buildLoop = () => {
            clearClones();
            if (!shouldSlide()) {
                return;
            }
            const before = originals.map((node) => {
                const clone = node.cloneNode(true);
                clone.setAttribute('data-home-clone', 'before');
                clone.setAttribute('aria-hidden', 'true');
                return clone;
            });
            const after = originals.map((node) => {
                const clone = node.cloneNode(true);
                clone.setAttribute('data-home-clone', 'after');
                clone.setAttribute('aria-hidden', 'true');
                return clone;
            });
            before.reverse().forEach((clone) => track.insertBefore(clone, track.firstChild));
            after.forEach((clone) => track.appendChild(clone));
        };

        const jumpToOriginalStart = () => {
            measure();
            if (slideWidth <= 0) {
                return;
            }
            looping = true;
            track.scrollLeft = originals.length * slideWidth;
            requestAnimationFrame(() => {
                looping = false;
            });
        };

        const normalizeLoop = () => {
            if (!root.classList.contains('shop-home-products--slider') || slideWidth <= 0) {
                return;
            }
            const total = originals.length;
            if (total === 0) {
                return;
            }
            const min = total * slideWidth;
            const max = total * 2 * slideWidth;
            if (track.scrollLeft <= min - slideWidth * 0.5) {
                looping = true;
                track.scrollLeft += total * slideWidth;
                requestAnimationFrame(() => {
                    looping = false;
                });
            } else if (track.scrollLeft >= max + slideWidth * 0.5) {
                looping = true;
                track.scrollLeft -= total * slideWidth;
                requestAnimationFrame(() => {
                    looping = false;
                });
            }
        };

        const applyMode = () => {
            const enable = shouldSlide();
            root.classList.toggle('shop-home-products--slider', enable);
            prevBtn.hidden = !enable;
            nextBtn.hidden = !enable;
            prevBtn.disabled = !enable;
            nextBtn.disabled = !enable;

            if (enable) {
                buildLoop();
                measure();
                jumpToOriginalStart();
            } else {
                clearClones();
                track.scrollLeft = 0;
            }
        };

        const scrollBySlides = (dir) => {
            if (!root.classList.contains('shop-home-products--slider')) {
                return;
            }
            measure();
            if (slideWidth <= 0) {
                return;
            }
            track.scrollBy({ left: dir * slideWidth, behavior: 'smooth' });
        };

        prevBtn.addEventListener('click', () => scrollBySlides(-1));
        nextBtn.addEventListener('click', () => scrollBySlides(1));

        track.addEventListener('scroll', () => {
            if (!looping) {
                normalizeLoop();
            }
        }, { passive: true });

        let resizeTimer = 0;
        window.addEventListener('resize', () => {
            window.clearTimeout(resizeTimer);
            resizeTimer = window.setTimeout(applyMode, 120);
        });

        applyMode();
    };

    document.querySelectorAll('[data-home-products]').forEach(init);
})();
