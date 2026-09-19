(() => {
    'use strict';

    const STORAGE_KEY = 'shop_checkout_contact_v1';

    const readCache = () => {
        try {
            const raw = localStorage.getItem(STORAGE_KEY);
            if (!raw) {
                return null;
            }

            const parsed = JSON.parse(raw);
            if (!parsed || typeof parsed !== 'object') {
                return null;
            }

            return parsed;
        } catch {
            return null;
        }
    };

    const writeCache = (data) => {
        try {
            localStorage.setItem(STORAGE_KEY, JSON.stringify(data));
        } catch {
            // Ignore quota or privacy mode errors.
        }
    };

    const getInputValue = (form, fieldName) => {
        const input = form.querySelector(`[name="${fieldName}"]`);
        if (!input) {
            return '';
        }

        if (input.type === 'checkbox') {
            return input.checked;
        }

        return String(input.value || '').trim();
    };

    const setInputValue = (form, fieldName, value) => {
        const input = form.querySelector(`[name="${fieldName}"]`);
        if (!input) {
            return;
        }

        if (input.type === 'checkbox') {
            input.checked = Boolean(value);
            return;
        }

        if (String(input.value || '').trim() !== '') {
            return;
        }

        input.value = String(value || '');
    };

    const collectContactFromForm = (contactForm, showEmailField) => {
        const cached = readCache() || {};

        return {
            customerName: getInputValue(contactForm, 'checkout_contact_form[customerName]'),
            customerPhone: getInputValue(contactForm, 'checkout_contact_form[customerPhone]'),
            customerEmail: showEmailField
                ? getInputValue(contactForm, 'checkout_contact_form[customerEmail]')
                : (cached.customerEmail || ''),
            doNotCall: cached.doNotCall ?? false,
        };
    };

    const readSavedContactFromPage = (root) => {
        const raw = root?.dataset?.savedContact;
        if (!raw) {
            return null;
        }

        try {
            const parsed = JSON.parse(raw);
            return parsed && typeof parsed === 'object' ? parsed : null;
        } catch {
            return null;
        }
    };

    const collectFromPaymentForm = (paymentForm, showEmailField) => {
        const root = document.querySelector('.shop-checkout');
        const savedOnPage = readSavedContactFromPage(root);
        const contactForm = document.getElementById('checkout-contact-form');

        if (contactForm) {
            return {
                ...collectContactFromForm(contactForm, showEmailField),
                doNotCall: getInputValue(paymentForm, 'checkout_payment_form[doNotCall]'),
            };
        }

        if (savedOnPage) {
            return {
                customerName: String(savedOnPage.customerName || ''),
                customerPhone: String(savedOnPage.customerPhone || ''),
                customerEmail: String(savedOnPage.customerEmail || ''),
                doNotCall: getInputValue(paymentForm, 'checkout_payment_form[doNotCall]'),
            };
        }

        const cached = readCache() || {};

        return {
            customerName: cached.customerName || '',
            customerPhone: cached.customerPhone || '',
            customerEmail: cached.customerEmail || '',
            doNotCall: getInputValue(paymentForm, 'checkout_payment_form[doNotCall]'),
        };
    };

    const prefillContactForm = (showEmailField) => {
        const contactForm = document.getElementById('checkout-contact-form');
        const cached = readCache();
        if (!contactForm || !cached) {
            return;
        }

        setInputValue(contactForm, 'checkout_contact_form[customerName]', cached.customerName);
        if (showEmailField) {
            setInputValue(contactForm, 'checkout_contact_form[customerEmail]', cached.customerEmail);
        }
        setInputValue(contactForm, 'checkout_contact_form[customerPhone]', cached.customerPhone);
    };

    const prefillPaymentForm = () => {
        const paymentForm = document.getElementById('checkout-payment-form');
        const cached = readCache();
        if (!paymentForm || !cached) {
            return;
        }

        const doNotCallInput = paymentForm.querySelector('[name="checkout_payment_form[doNotCall]"]');
        if (doNotCallInput && cached.doNotCall && !doNotCallInput.checked) {
            doNotCallInput.checked = true;
        }
    };

    const bindContactForm = (showEmailField) => {
        const contactForm = document.getElementById('checkout-contact-form');
        if (!contactForm) {
            return;
        }

        contactForm.addEventListener('submit', () => {
            writeCache(collectContactFromForm(contactForm, showEmailField));
        });
    };

    const bindPaymentForm = (showEmailField) => {
        const paymentForm = document.getElementById('checkout-payment-form');
        if (!paymentForm) {
            return;
        }

        paymentForm.addEventListener('submit', () => {
            writeCache(collectFromPaymentForm(paymentForm, showEmailField));
        });
    };

    const init = () => {
        const root = document.querySelector('.shop-checkout');
        if (!root) {
            return;
        }

        const showEmailField = root.dataset.showContactEmail === '1';

        prefillContactForm(showEmailField);
        prefillPaymentForm();
        bindContactForm(showEmailField);
        bindPaymentForm(showEmailField);
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
