(function () {
    'use strict';

    function findGalleryItems() {
        return Array.prototype.slice.call(document.querySelectorAll('[data-gallery-item]'));
    }

    function ensureButtons(urlTemplate, labels) {
        const items = findGalleryItems();
        items.forEach(function (wrap, index) {
            const slot = index + 1;
            let btn = wrap.querySelector('[data-product-clean-slot]');
            let status = wrap.querySelector('[data-product-clean-status]');
            if (!btn) {
                return;
            }
            btn.hidden = false;
            if (status) {
                status.hidden = false;
            }
            if (btn.getAttribute('data-bound') === '1') {
                btn.setAttribute('data-product-clean-slot', String(slot));
                return;
            }
            btn.setAttribute('data-bound', '1');
            btn.setAttribute('data-product-clean-slot', String(slot));
            btn.textContent = labels.generate || 'Generate clean image';
            btn.addEventListener('click', async function () {
                const currentSlot = btn.getAttribute('data-product-clean-slot') || String(slot);
                const url = urlTemplate.replace('__SLOT__', currentSlot);
                if (status) {
                    status.textContent = labels.processing || 'Processing…';
                }
                btn.disabled = true;
                try {
                    const res = await fetch(url, {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: { 'X-Requested-With': 'XMLHttpRequest' },
                    });
                    const data = await res.json();
                    if (!res.ok || !data.ok) {
                        throw new Error(data.error || 'failed');
                    }
                    if (status) {
                        status.textContent = data.message || '';
                    }
                    window.setTimeout(function () {
                        window.location.reload();
                    }, 400);
                } catch (e) {
                    if (status) {
                        status.textContent = (e && e.message) || labels.error || 'Failed';
                    }
                    btn.disabled = false;
                }
            });
        });
    }

    function boot() {
        const cfgEl = document.querySelector('[data-product-clean-config]');
        if (!cfgEl) {
            return;
        }
        let cfg;
        try {
            cfg = JSON.parse(cfgEl.getAttribute('data-product-clean-config') || '{}');
        } catch (e) {
            return;
        }
        if (!cfg.urlTemplate) {
            return;
        }
        const labels = cfg.labels || {};
        ensureButtons(cfg.urlTemplate, labels);

        const collection = document.querySelector('.field-collection');
        if (collection && window.MutationObserver) {
            const observer = new MutationObserver(function () {
                ensureButtons(cfg.urlTemplate, labels);
            });
            observer.observe(collection, { childList: true, subtree: true });
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();
