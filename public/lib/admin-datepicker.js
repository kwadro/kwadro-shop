(() => {
    'use strict';

    const DATE_SELECTOR = 'input[type="date"]:not([readonly]):not([disabled]):not([data-flatpickr-skip])';
    const DATETIME_SELECTOR = 'input[type="datetime-local"]:not([readonly]):not([disabled]):not([data-flatpickr-skip])';
    const TEXT_DATE_SELECTOR = 'input[data-ea-widget="ea-datepicker"]:not([readonly]):not([disabled])';

    const waitForFlatpickr = (callback, attemptsLeft = 50) => {
        if (typeof window.flatpickr === 'function') {
            callback();
            return;
        }

        if (attemptsLeft <= 0) {
            console.warn('Flatpickr failed to load; native date inputs will be used.');
            return;
        }

        window.setTimeout(() => waitForFlatpickr(callback, attemptsLeft - 1), 100);
    };

    const initInput = (input, withTime) => {
        if (!(input instanceof HTMLInputElement) || input.dataset.flatpickrBound === '1') {
            return;
        }

        const options = {
            allowInput: true,
            disableMobile: true,
            dateFormat: withTime ? 'Y-m-d H:i' : 'Y-m-d',
            enableTime: withTime,
            time_24hr: true,
        };

        // Keep value compatible with HTML5 date / datetime-local when possible.
        if (input.type === 'date') {
            options.dateFormat = 'Y-m-d';
            options.enableTime = false;
        } else if (input.type === 'datetime-local') {
            options.dateFormat = 'Y-m-d\\TH:i';
            options.enableTime = true;
        }

        window.flatpickr(input, options);
        input.dataset.flatpickrBound = '1';
    };

    const initDatepickers = (root) => {
        const scope = root instanceof Element ? root : document;

        scope.querySelectorAll(DATE_SELECTOR).forEach((input) => initInput(input, false));
        scope.querySelectorAll(DATETIME_SELECTOR).forEach((input) => initInput(input, true));
        scope.querySelectorAll(TEXT_DATE_SELECTOR).forEach((input) => {
            const withTime = (input.dataset.enableTime || '') === '1'
                || (input.name || '').toLowerCase().includes('time');
            initInput(input, withTime);
        });
    };

    const boot = () => {
        waitForFlatpickr(() => {
            initDatepickers(document);

            document.addEventListener('ea.collection.item-added', (event) => {
                initDatepickers(event.target instanceof Element ? event.target : document);
            });

            // EasyAdmin / Turbo navigations
            document.addEventListener('turbo:load', () => initDatepickers(document));
            document.addEventListener('ea.page:loaded', () => initDatepickers(document));
        });
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();
