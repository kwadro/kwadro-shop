(function () {
    'use strict';

    function findGalleryField(slot) {
        const name = 'Product[galleryImage' + slot + ']';
        const input = document.querySelector('[name="' + name + '"]');
        if (input) {
            return input.closest('.form-group, .field-image, .ea-form-field, .form-widget') || input.parentElement;
        }
        const label = Array.prototype.find.call(document.querySelectorAll('label'), function (el) {
            return (el.getAttribute('for') || '').indexOf('galleryImage' + slot) !== -1;
        });
        return label ? label.closest('.form-group, .field-image, .ea-form-field') : null;
    }

    function ensureButton(slot, urlTemplate, labels) {
        const wrap = findGalleryField(slot);
        if (!wrap || wrap.querySelector('[data-product-clean-slot="' + slot + '"]')) {
            return;
        }
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'btn btn-sm btn-outline-primary mt-2';
        btn.setAttribute('data-product-clean-slot', String(slot));
        btn.textContent = labels.generate || 'Generate clean image';
        wrap.appendChild(btn);

        const status = document.createElement('div');
        status.className = 'form-text mt-1';
        status.setAttribute('data-product-clean-status', String(slot));
        wrap.appendChild(status);

        btn.addEventListener('click', async function () {
            const url = urlTemplate.replace('__SLOT__', String(slot));
            status.textContent = labels.processing || 'Processing…';
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
                status.textContent = data.message || '';
                // Reload so EasyAdmin form picks up cleanImage and won't overwrite it on save.
                window.setTimeout(function () {
                    window.location.reload();
                }, 400);
            } catch (e) {
                status.textContent = (e && e.message) || labels.error || 'Failed';
                btn.disabled = false;
            }
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
        for (let slot = 1; slot <= 5; slot++) {
            ensureButton(slot, cfg.urlTemplate, labels);
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();
