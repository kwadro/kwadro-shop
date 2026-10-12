(() => {
    const root = document.querySelector('.shop-checkout[data-cart-update-url]');
    if (!root) {
        return;
    }

    const urls = {
        update: root.dataset.cartUpdateUrl,
        remove: root.dataset.cartRemoveUrl,
    };
    const token = root.dataset.cartToken || '';
    const errorMessage = root.dataset.cartError || 'Unable to update the cart.';
    const quantityTimers = new Map();
    let requestSeq = 0;

    const replaceElement = (selector, html) => {
        const current = document.querySelector(selector);
        if (!current || typeof html !== 'string') {
            return;
        }

        const template = document.createElement('template');
        template.innerHTML = html.trim();
        const next = template.content.firstElementChild;
        if (next) {
            current.replaceWith(next);
        }
    };

    const setSlotContent = (selector, html) => {
        const slot = document.querySelector(selector);
        if (slot) {
            slot.innerHTML = typeof html === 'string' ? html : '';
        }
    };

    const showError = () => {
        const summary = document.getElementById('checkout-summary');
        if (!summary) {
            return;
        }

        let alert = summary.querySelector('[data-checkout-cart-error]');
        if (!alert) {
            alert = document.createElement('p');
            alert.className = 'shop-checkout__alert shop-checkout__alert--error';
            alert.setAttribute('data-checkout-cart-error', '');
            alert.setAttribute('role', 'alert');
            summary.querySelector('.shop-checkout__heading')?.after(alert);
        }
        alert.textContent = errorMessage;
    };

    const applyResponse = (data) => {
        if (data.redirect) {
            window.location.assign(data.redirect);
            return;
        }

        replaceElement('#checkout-summary', data.summaryHtml);
        if (typeof data.savedDeliveryHtml === 'string') {
            setSlotContent('#checkout-saved-delivery', data.savedDeliveryHtml);
        }
        setSlotContent('#checkout-delivery-parcels-slot', data.deliveryParcelsHtml);

        if (data.cart && window.ShopCart?.update) {
            window.ShopCart.update(data.cart);
        }

        window.dispatchEvent(new CustomEvent('shop:checkout-cart-updated', {
            detail: {
                subtotal: Number(data.subtotal),
                courierDeliveryCost: Number(data.courierDeliveryCost),
                parcelCount: Number(data.parcelCount),
                minOrderBlocked: Boolean(data.minOrderBlocked),
            },
        }));
    };

    const send = async (url, params) => {
        const seq = ++requestSeq;
        const summary = document.getElementById('checkout-summary');
        summary?.classList.add('is-loading');

        const body = new FormData();
        body.append('_token', token);
        Object.entries(params).forEach(([key, value]) => {
            if (Array.isArray(value)) {
                value.forEach((item) => body.append(`${key}[]`, String(item)));
            } else {
                body.append(key, String(value));
            }
        });

        try {
            const response = await fetch(url, {
                method: 'POST',
                body,
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });
            const data = await response.json();
            if (!response.ok || !data.success) {
                throw new Error(data.error || 'Request failed');
            }

            // A newer request will re-render the summary anyway.
            if (seq === requestSeq) {
                applyResponse(data);
            }
        } catch (error) {
            console.error(error);
            if (seq === requestSeq) {
                showError();
            }
        } finally {
            if (seq === requestSeq) {
                document.getElementById('checkout-summary')?.classList.remove('is-loading');
            }
        }
    };

    const clampQuantity = (input) => {
        const min = 1;
        const max = Number(input.max) || Infinity;
        const value = Math.round(Number(input.value) || min);

        return Math.min(Math.max(value, min), max);
    };

    const scheduleQuantityUpdate = (input) => {
        const cartItemId = input.dataset.cartItemId;
        if (!cartItemId) {
            return;
        }

        window.clearTimeout(quantityTimers.get(cartItemId));
        quantityTimers.set(cartItemId, window.setTimeout(() => {
            quantityTimers.delete(cartItemId);
            send(urls.update, { cart_item_id: cartItemId, quantity: clampQuantity(input) });
        }, 400));
    };

    root.addEventListener('click', (event) => {
        const stepButton = event.target.closest('[data-qty-step]');
        if (stepButton && root.contains(stepButton)) {
            const input = stepButton.closest('[data-checkout-qty]')?.querySelector('input');
            if (!input) {
                return;
            }

            input.value = String(clampQuantity({
                value: (Number(input.value) || 1) + Number(stepButton.dataset.qtyStep),
                max: input.max,
            }));
            scheduleQuantityUpdate(input);

            return;
        }

        const removeButton = event.target.closest('[data-checkout-remove-items]');
        if (removeButton && root.contains(removeButton)) {
            const ids = removeButton.dataset.checkoutRemoveItems.split(',').filter(Boolean);
            if (ids.length === 0) {
                return;
            }

            removeButton.disabled = true;
            ids.forEach((id) => {
                document.querySelector(`.shop-checkout__item[data-cart-item-id="${id}"]`)
                    ?.classList.add('is-removing');
            });
            send(urls.remove, { cart_item_ids: ids });
        }
    });

    root.addEventListener('input', (event) => {
        const input = event.target.closest('.shop-checkout__qty-input');
        if (input && input.value !== '') {
            scheduleQuantityUpdate(input);
        }
    });

    root.addEventListener('change', (event) => {
        const input = event.target.closest('.shop-checkout__qty-input');
        if (input) {
            input.value = String(clampQuantity(input));
            scheduleQuantityUpdate(input);
        }
    });

    root.addEventListener('keydown', (event) => {
        if (event.key === 'Enter' && event.target.closest('.shop-checkout__qty-input')) {
            event.preventDefault();
            event.target.blur();
        }
    });
})();
