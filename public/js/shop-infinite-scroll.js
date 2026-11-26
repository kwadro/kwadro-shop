(() => {
    const root = document.querySelector('[data-infinite-scroll]');
    if (!root) {
        return;
    }

    const grid = root.querySelector('[data-infinite-scroll-grid]');
    const sentinel = root.querySelector('[data-infinite-scroll-sentinel]');
    const status = root.querySelector('[data-infinite-scroll-status]');
    if (!grid || !sentinel) {
        return;
    }

    const pages = Math.max(1, Number(root.dataset.pages) || 1);
    let page = Math.max(1, Number(root.dataset.page) || 1);
    let loading = false;
    let exhausted = page >= pages;

    const buildNextUrl = (nextPage) => {
        const url = new URL(root.dataset.url || window.location.href, window.location.origin);
        let query = {};
        try {
            query = JSON.parse(root.dataset.query || '{}');
        } catch (e) {
            query = {};
        }

        Object.keys(query).forEach((key) => {
            url.searchParams.delete(key);
            url.searchParams.delete(`${key}[]`);
            const value = query[key];
            if (Array.isArray(value)) {
                value.forEach((item) => {
                    if (item !== null && item !== undefined && String(item) !== '') {
                        // Match checkbox name="attr[]" so PHP keeps every selected value.
                        url.searchParams.append(`${key}[]`, String(item));
                    }
                });
                return;
            }
            if (value !== null && value !== undefined && String(value) !== '') {
                url.searchParams.set(key, String(value));
            }
        });

        url.searchParams.set('page', String(nextPage));
        url.searchParams.set('fragment', 'products');

        return url;
    };

    const setStatus = (text) => {
        if (status) {
            status.textContent = text || '';
        }
    };

    const finish = () => {
        exhausted = true;
        sentinel.hidden = true;
        root.classList.remove('is-loading');
        setStatus('');
        if (observer) {
            observer.disconnect();
        }
    };

    const loadMore = async () => {
        if (loading || exhausted || page >= pages) {
            if (page >= pages) {
                finish();
            }
            return;
        }

        loading = true;
        root.classList.add('is-loading');
        setStatus(root.dataset.loadingLabel || 'Loading…');

        const nextPage = page + 1;
        const url = buildNextUrl(nextPage);

        try {
            const response = await fetch(url.toString(), {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    Accept: 'text/html',
                },
                credentials: 'same-origin',
            });
            if (!response.ok) {
                throw new Error('Failed to load products');
            }

            const html = (await response.text()).trim();
            if (html === '') {
                finish();
                return;
            }

            const wrapper = document.createElement('div');
            wrapper.innerHTML = html;
            const cards = wrapper.querySelectorAll('.shop-product-card');
            if (cards.length === 0) {
                finish();
                return;
            }

            cards.forEach((card) => {
                grid.appendChild(card);
            });

            page = nextPage;
            root.dataset.page = String(page);

            const historyUrl = new URL(window.location.href);
            if (page > 1) {
                historyUrl.searchParams.set('page', String(page));
            } else {
                historyUrl.searchParams.delete('page');
            }
            history.replaceState(null, '', historyUrl.pathname + historyUrl.search + historyUrl.hash);

            if (page >= pages) {
                finish();
            } else {
                setStatus('');
            }
        } catch (e) {
            setStatus(root.dataset.errorLabel || 'Could not load more products');
        } finally {
            loading = false;
            root.classList.remove('is-loading');
        }
    };

    const observer = new IntersectionObserver(
        (entries) => {
            if (entries.some((entry) => entry.isIntersecting)) {
                loadMore();
            }
        },
        {
            root: null,
            rootMargin: '600px 0px',
            threshold: 0,
        }
    );

    if (exhausted) {
        finish();
        return;
    }

    observer.observe(sentinel);
})();
