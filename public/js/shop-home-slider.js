(() => {
    const MOBILE_MQ = '(max-width: 700px)';

    const init = (root) => {
        const track = root.querySelector('[data-home-slider-track]');
        const prevBtn = root.querySelector('[data-home-slider-prev]');
        const nextBtn = root.querySelector('[data-home-slider-next]');
        const itemSelector = root.dataset.itemSelector || '[data-home-slider-item]';
        if (!track || !prevBtn || !nextBtn) {
            return;
        }

        const count = Math.max(0, parseInt(root.dataset.count || '0', 10) || 0);
        const sliderMin = Math.max(1, parseInt(root.dataset.sliderMin || '3', 10) || 3);
        let originals = Array.from(track.querySelectorAll(itemSelector));
        if (originals.length === 0) {
            return;
        }

        let looping = false;
        let slideWidth = 0;
        let settleTimer = 0;

        const isMobile = () => window.matchMedia(MOBILE_MQ).matches;

        const shouldSlide = () => {
            if (count <= 1) {
                return false;
            }
            return isMobile() ? count > 1 : count > sliderMin;
        };

        const measure = () => {
            const first = track.querySelector(`${itemSelector}:not([data-home-clone])`)
                || track.querySelector(itemSelector);
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
            originals = Array.from(track.querySelectorAll(`${itemSelector}:not([data-home-clone])`));
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
                if (clone instanceof HTMLElement) {
                    clone.tabIndex = -1;
                }
                return clone;
            });
            const after = originals.map((node) => {
                const clone = node.cloneNode(true);
                clone.setAttribute('data-home-clone', 'after');
                clone.setAttribute('aria-hidden', 'true');
                if (clone instanceof HTMLElement) {
                    clone.tabIndex = -1;
                }
                return clone;
            });
            before.reverse().forEach((clone) => track.insertBefore(clone, track.firstChild));
            after.forEach((clone) => track.appendChild(clone));
        };

        const withInstantScroll = (fn) => {
            const prevBehavior = track.style.scrollBehavior;
            track.style.scrollBehavior = 'auto';
            fn();
            track.style.scrollBehavior = prevBehavior;
        };

        const jumpToOriginalStart = () => {
            measure();
            if (slideWidth <= 0) {
                return;
            }
            looping = true;
            withInstantScroll(() => {
                track.scrollLeft = originals.length * slideWidth;
            });
            requestAnimationFrame(() => {
                looping = false;
            });
        };

        /**
         * Track layout: [before x N][originals x N][after x N]
         * Start at originals. Going past the last original scrolls into "after"
         * (first clone = first item), then we teleport back into originals —
         * always continuing forward, never animating backwards.
         */
        const normalizeLoop = () => {
            if (!root.classList.contains('is-slider') || slideWidth <= 0) {
                return;
            }
            const total = originals.length;
            if (total === 0) {
                return;
            }
            const min = total * slideWidth;
            const max = total * 2 * slideWidth;
            const left = track.scrollLeft;

            if (left < min - 1) {
                looping = true;
                withInstantScroll(() => {
                    track.scrollLeft = left + total * slideWidth;
                });
                requestAnimationFrame(() => {
                    looping = false;
                });
            } else if (left >= max - 1) {
                looping = true;
                withInstantScroll(() => {
                    track.scrollLeft = left - total * slideWidth;
                });
                requestAnimationFrame(() => {
                    looping = false;
                });
            }
        };

        const scheduleNormalize = () => {
            window.clearTimeout(settleTimer);
            settleTimer = window.setTimeout(() => {
                if (!looping) {
                    normalizeLoop();
                }
            }, 80);
        };

        const applyMode = () => {
            const enable = shouldSlide();
            root.classList.toggle('is-slider', enable);
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
                withInstantScroll(() => {
                    track.scrollLeft = 0;
                });
            }
        };

        const scrollBySlides = (dir) => {
            if (!root.classList.contains('is-slider')) {
                return;
            }
            measure();
            if (slideWidth <= 0) {
                return;
            }
            // Always move forward/back by one step into the clone buffer;
            // normalizeLoop teleports without reversing the visual direction.
            track.scrollBy({ left: dir * slideWidth, behavior: 'smooth' });
            scheduleNormalize();
        };

        prevBtn.addEventListener('click', () => scrollBySlides(-1));
        nextBtn.addEventListener('click', () => scrollBySlides(1));

        track.addEventListener('scroll', () => {
            if (!looping) {
                scheduleNormalize();
            }
        }, { passive: true });

        track.addEventListener('scrollend', () => {
            if (!looping) {
                normalizeLoop();
            }
        });

        let resizeTimer = 0;
        window.addEventListener('resize', () => {
            window.clearTimeout(resizeTimer);
            resizeTimer = window.setTimeout(applyMode, 120);
        });

        applyMode();
    };

    document.querySelectorAll('[data-home-slider]').forEach(init);
})();
