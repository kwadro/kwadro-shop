(() => {
    const MOBILE_MQ = '(max-width: 700px)';

    const init = (root) => {
        const track = root.querySelector('[data-home-slider-track]');
        const dotsRoot = root.querySelector('[data-home-slider-dots]');
        const itemSelector = root.dataset.itemSelector || '[data-home-slider-item]';
        if (!track || !dotsRoot) {
            return;
        }

        const count = Math.max(0, parseInt(root.dataset.count || '0', 10) || 0);
        const sliderMin = Math.max(1, parseInt(root.dataset.sliderMin || '3', 10) || 3);
        const autoplayEnabled = root.dataset.autoplay === '1' || root.dataset.autoplay === 'true';
        const autoplayInterval = Math.max(
            1000,
            parseInt(root.dataset.autoplayInterval || '5000', 10) || 5000,
        );

        let originals = Array.from(track.querySelectorAll(itemSelector));
        if (originals.length === 0) {
            return;
        }

        let looping = false;
        let slideWidth = 0;
        let settleTimer = 0;
        let autoplayTimer = 0;
        let dots = [];

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
                    updateDots();
                }
            }, 80);
        };

        const currentIndex = () => {
            measure();
            const total = originals.length;
            if (slideWidth <= 0 || total === 0) {
                return 0;
            }
            const raw = Math.round(track.scrollLeft / slideWidth) - total;
            return ((raw % total) + total) % total;
        };

        const updateDots = () => {
            if (dots.length === 0) {
                return;
            }
            const active = currentIndex();
            dots.forEach((dot, index) => {
                const isActive = index === active;
                dot.classList.toggle('is-active', isActive);
                dot.setAttribute('aria-current', isActive ? 'true' : 'false');
            });
        };

        const buildDots = () => {
            dotsRoot.replaceChildren();
            dots = [];
            if (!shouldSlide()) {
                dotsRoot.hidden = true;
                return;
            }
            dotsRoot.hidden = false;
            originals.forEach((_, index) => {
                const button = document.createElement('button');
                button.type = 'button';
                button.className = 'shop-home-slider__dot';
                button.setAttribute('aria-label', String(index + 1));
                button.addEventListener('click', () => {
                    goTo(index);
                    restartAutoplay();
                });
                dotsRoot.appendChild(button);
                dots.push(button);
            });
            updateDots();
        };

        const goTo = (index) => {
            if (!root.classList.contains('is-slider')) {
                return;
            }
            measure();
            if (slideWidth <= 0) {
                return;
            }
            const total = originals.length;
            const target = ((index % total) + total) % total;
            track.scrollTo({
                left: (total + target) * slideWidth,
                behavior: 'smooth',
            });
            scheduleNormalize();
        };

        const scrollBySlides = (dir) => {
            if (!root.classList.contains('is-slider')) {
                return;
            }
            measure();
            if (slideWidth <= 0) {
                return;
            }
            track.scrollBy({ left: dir * slideWidth, behavior: 'smooth' });
            scheduleNormalize();
        };

        const stopAutoplay = () => {
            window.clearInterval(autoplayTimer);
            autoplayTimer = 0;
        };

        const startAutoplay = () => {
            stopAutoplay();
            if (!autoplayEnabled || !root.classList.contains('is-slider')) {
                return;
            }
            if (document.hidden) {
                return;
            }
            autoplayTimer = window.setInterval(() => {
                scrollBySlides(1);
            }, autoplayInterval);
        };

        const restartAutoplay = () => {
            stopAutoplay();
            startAutoplay();
        };

        const applyMode = () => {
            const enable = shouldSlide();
            root.classList.toggle('is-slider', enable);

            if (enable) {
                buildLoop();
                measure();
                jumpToOriginalStart();
                buildDots();
                startAutoplay();
            } else {
                stopAutoplay();
                clearClones();
                withInstantScroll(() => {
                    track.scrollLeft = 0;
                });
                buildDots();
            }
        };

        track.addEventListener('scroll', () => {
            if (!looping) {
                scheduleNormalize();
                updateDots();
            }
        }, { passive: true });

        track.addEventListener('scrollend', () => {
            if (!looping) {
                normalizeLoop();
                updateDots();
            }
        });

        root.addEventListener('pointerenter', stopAutoplay);
        root.addEventListener('pointerleave', startAutoplay);
        root.addEventListener('focusin', stopAutoplay);
        root.addEventListener('focusout', (event) => {
            if (!root.contains(event.relatedTarget)) {
                startAutoplay();
            }
        });

        document.addEventListener('visibilitychange', () => {
            if (document.hidden) {
                stopAutoplay();
            } else {
                startAutoplay();
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
