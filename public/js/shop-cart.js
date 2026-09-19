(() => {
    const root = document.querySelector('[data-minicart]');
    if (!root) {
        return;
    }

    const toggle = root.querySelector('.shop-minicart__toggle');
    const panel = root.querySelector('.shop-minicart__panel');
    const backdrop = root.querySelector('.shop-minicart__backdrop');
    const closeBtn = root.querySelector('.shop-minicart__close');
    const body = root.querySelector('[data-minicart-body]');
    const badge = root.querySelector('[data-minicart-badge]');

    const labels = {
        quantity: root.dataset.labelQuantity || 'Quantity',
        total: root.dataset.labelTotal || 'Total',
        empty: root.dataset.labelEmpty || 'Cart is empty',
        checkout: root.dataset.labelCheckout || 'Checkout',
        continue: root.dataset.labelContinue || 'Continue shopping',
        remove: root.dataset.labelRemove || 'Remove',
    };

    const urls = {
        checkout: root.dataset.checkoutUrl || '#',
        home: root.dataset.homeUrl || '#',
        remove: root.dataset.removeUrl || '',
    };

    const removeToken = root.dataset.removeToken || '';

    const open = () => {
        panel.hidden = false;
        backdrop.hidden = false;
        toggle.setAttribute('aria-expanded', 'true');
        document.body.classList.add('shop-minicart-open');
        panel.classList.add('shop-minicart__panel--open');
    };

    const close = () => {
        panel.hidden = true;
        backdrop.hidden = true;
        toggle.setAttribute('aria-expanded', 'false');
        document.body.classList.remove('shop-minicart-open');
        panel.classList.remove('shop-minicart__panel--open');
    };

    toggle.addEventListener('click', () => {
        if (panel.hidden) {
            open();
        } else {
            close();
        }
    });

    closeBtn?.addEventListener('click', close);
    backdrop.addEventListener('click', close);

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && !panel.hidden) {
            close();
        }
    });

    const escapeHtml = (value) => String(value)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');

    const renderItem = (item) => {
        const thumb = item.image
            ? `<img class="shop-minicart__thumb" src="${escapeHtml(item.image)}" alt="${escapeHtml(item.name)}" width="56" height="56" loading="lazy">`
            : '<div class="shop-minicart__thumb shop-minicart__thumb--placeholder" aria-hidden="true"></div>';
        const supplier = item.supplier_name
            ? `<p class="shop-minicart__item-supplier">${escapeHtml(root.dataset.labelSupplier || 'Supplier')}: ${escapeHtml(item.supplier_name)}</p>`
            : '';

        return `
            <li class="shop-minicart__item shop-minicart__item--enter">
                ${thumb}
                <div class="shop-minicart__item-body">
                    <p class="shop-minicart__item-name">${escapeHtml(item.name)}</p>
                    ${supplier}
                    <p class="shop-minicart__item-meta">${escapeHtml(root.dataset.labelSku || 'SKU')}: ${escapeHtml(item.sku)} · ${escapeHtml(labels.quantity)}: ${item.quantity}</p>
                    <p class="shop-minicart__item-price">${escapeHtml(item.line_total_formatted)}</p>
                </div>
                <button
                    type="button"
                    class="shop-minicart__remove"
                    data-remove-item
                    data-cart-item-id="${item.cart_item_id}"
                    aria-label="${escapeHtml(labels.remove)}"
                    title="${escapeHtml(labels.remove)}"
                >&times;</button>
            </li>
        `;
    };

    const renderBody = (cart) => {
        if (!cart.hasItems) {
            return `
                <p class="shop-minicart__empty">${escapeHtml(labels.empty)}</p>
                <a href="${escapeHtml(urls.home)}" class="shop-minicart__checkout shop-minicart__checkout--secondary">
                    ${escapeHtml(labels.continue)}
                </a>
            `;
        }

        const items = cart.items.map(renderItem).join('');

        return `
            <ul class="shop-minicart__items">${items}</ul>
            <div class="shop-minicart__total">
                <span>${escapeHtml(labels.total)}</span>
                <strong data-minicart-total>${escapeHtml(cart.total_formatted)}</strong>
            </div>
            <a href="${escapeHtml(urls.checkout)}" class="shop-minicart__checkout">
                ${escapeHtml(labels.checkout)}
            </a>
        `;
    };

    const updateBadge = (count) => {
        if (!badge) {
            return;
        }

        badge.textContent = String(count);
        badge.classList.toggle('shop-minicart__badge--hidden', count <= 0);

        if (count > 0) {
            badge.classList.remove('shop-minicart__badge--pulse');
            void badge.offsetWidth;
            badge.classList.add('shop-minicart__badge--pulse');
        }
    };

    const bounceToggle = () => {
        toggle.classList.remove('shop-minicart__toggle--bounce');
        void toggle.offsetWidth;
        toggle.classList.add('shop-minicart__toggle--bounce');
    };

    const flyToCart = (sourceImage) => {
        if (!sourceImage || !toggle) {
            return;
        }

        const sourceRect = sourceImage.getBoundingClientRect();
        const targetRect = toggle.getBoundingClientRect();
        const fly = sourceImage.cloneNode(true);

        fly.className = 'shop-cart-fly';
        fly.removeAttribute('id');
        fly.style.left = `${sourceRect.left}px`;
        fly.style.top = `${sourceRect.top}px`;
        fly.style.width = `${sourceRect.width}px`;
        fly.style.height = `${sourceRect.height}px`;

        document.body.appendChild(fly);

        requestAnimationFrame(() => {
            fly.style.left = `${targetRect.left + targetRect.width / 2 - 28}px`;
            fly.style.top = `${targetRect.top + targetRect.height / 2 - 28}px`;
            fly.style.width = '56px';
            fly.style.height = '56px';
            fly.style.opacity = '0.15';
            fly.style.transform = 'scale(0.35)';
        });

        fly.addEventListener('transitionend', () => fly.remove(), { once: true });
        bounceToggle();
    };

    const scrollToTop = () => new Promise((resolve) => {
        if (window.scrollY <= 0) {
            resolve();
            return;
        }

        let finished = false;
        const finish = () => {
            if (finished) {
                return;
            }

            finished = true;
            window.removeEventListener('scrollend', finish);
            resolve();
        };

        if ('onscrollend' in window) {
            window.addEventListener('scrollend', finish, { once: true });
        }

        window.scrollTo({ top: 0, left: 0, behavior: 'smooth' });
        window.setTimeout(finish, 450);
    });

    const update = (cart, options = {}) => {
        if (!body || !cart) {
            return Promise.resolve();
        }

        body.innerHTML = renderBody(cart);
        updateBadge(cart.itemCount || 0);

        const openPanel = () => {
            open();

            if (options.highlight) {
                panel.classList.add('shop-minicart__panel--highlight');
                window.setTimeout(() => panel.classList.remove('shop-minicart__panel--highlight'), 700);
            }
        };

        if (options.open) {
            if (options.scrollToTop) {
                return scrollToTop().then(openPanel);
            }

            openPanel();
        }

        return Promise.resolve();
    };

    const removeItem = async (cartItemId, button) => {
        if (!urls.remove || !removeToken) {
            return;
        }

        button.disabled = true;
        button.classList.add('shop-minicart__remove--loading');

        const formData = new FormData();
        formData.append('cart_item_id', String(cartItemId));
        formData.append('_token', removeToken);

        try {
            const response = await fetch(urls.remove, {
                method: 'POST',
                body: formData,
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });

            const data = await response.json();

            if (!response.ok || !data.success) {
                throw new Error(data.error || 'Request failed');
            }

            const itemEl = button.closest('.shop-minicart__item');
            if (itemEl) {
                itemEl.classList.add('shop-minicart__item--leave');
                window.setTimeout(() => update(data.cart), 220);
            } else {
                update(data.cart);
            }
        } catch (error) {
            button.disabled = false;
            button.classList.remove('shop-minicart__remove--loading');
        }
    };

    body?.addEventListener('click', (event) => {
        const button = event.target.closest('[data-remove-item]');
        if (!button || !body.contains(button)) {
            return;
        }

        event.preventDefault();
        const cartItemId = button.dataset.cartItemId;
        if (!cartItemId) {
            return;
        }

        removeItem(cartItemId, button);
    });

    const trackAddToCart = (item) => {
        if (!item || typeof item !== 'object') {
            return;
        }

        const price = Number(item.price) || 0;
        const quantity = Math.max(1, Number(item.quantity) || 1);
        const value = Number.isFinite(Number(item.value)) ? Number(item.value) : price * quantity;

        window.dataLayer = window.dataLayer || [];
        window.dataLayer.push({ ecommerce: null });
        window.dataLayer.push({
            event: 'add_to_cart',
            ecommerce: {
                currency: item.currency || 'UAH',
                value: Math.round(value * 100) / 100,
                items: [{
                    item_id: String(item.item_id || item.product_id || ''),
                    item_name: String(item.item_name || ''),
                    item_category: String(item.item_category || '') || undefined,
                    price: Math.round(price * 100) / 100,
                    quantity,
                }],
            },
        });
    };

    window.ShopCart = {
        update,
        flyToCart,
        scrollToTop,
        open,
        close,
        trackAddToCart,
    };
})();
