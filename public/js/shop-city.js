(function () {
    const config = document.getElementById('shop-city-config');
    const openButton = document.getElementById('shop-city-open');
    const modal = document.getElementById('shop-city-modal');
    const headerValue = document.getElementById('shop-header-city-value');
    const input = document.getElementById('shop-city-modal-input');
    const results = document.getElementById('shop-city-modal-results');
    const applyButton = document.getElementById('shop-city-modal-apply');

    if (!config || !openButton || !modal || !input || !results || !applyButton) {
        return;
    }

    const saveUrl = config.dataset.saveUrl;
    const searchUrl = config.dataset.searchUrl;
    const token = config.dataset.csrfToken;
    const npEnabled = config.dataset.npEnabled === '1';

    let currentCity = {};
    let selectedCity = null;
    let searchTimer = null;

    try {
        currentCity = JSON.parse(config.dataset.currentCity || '{}');
    } catch (error) {
        currentCity = {};
    }

    const normalizeCity = (city) => ({
        name: typeof city?.name === 'string' ? city.name : '',
        ref: typeof city?.ref === 'string' ? city.ref : '',
        subtitle: typeof city?.subtitle === 'string' ? city.subtitle : '',
        label: typeof city?.label === 'string' ? city.label : '',
        settlementRef: typeof city?.settlementRef === 'string' ? city.settlementRef : '',
        warehouseCityRef: typeof city?.warehouseCityRef === 'string' ? city.warehouseCityRef : '',
        hasLocalWarehouses: Boolean(city?.hasLocalWarehouses),
    });

    const getCityDetails = (city) => {
        if (!city) {
            return '';
        }

        if (city.label && city.label !== city.name) {
            return city.label;
        }

        return city.subtitle || '';
    };

    const getCityDisplayValue = (city) => {
        if (!city) {
            return '';
        }

        return getCityDetails(city) || city.name || '';
    };

    const setResultsOpen = (open) => {
        results.hidden = !open;
    };

    const clearResults = () => {
        results.innerHTML = '';
        setResultsOpen(false);
    };

    const updateHeader = (city) => {
        if (headerValue && city.name) {
            headerValue.textContent = city.name;
        }

        const details = getCityDetails(city);
        openButton.title = details || city.name || '';
    };

    const syncPopularActive = (cityName) => {
        modal.querySelectorAll('[data-city-popular]').forEach((button) => {
            button.classList.toggle('is-active', button.dataset.cityPopular === cityName);
        });
    };

    const renderResults = (items) => {
        results.innerHTML = '';
        if (!Array.isArray(items) || items.length === 0) {
            setResultsOpen(false);
            return;
        }

        items.forEach((city) => {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'shop-city-modal__result';
            button.setAttribute('role', 'option');

            const icon = document.createElement('span');
            icon.className = 'shop-city-modal__result-icon';
            icon.setAttribute('aria-hidden', 'true');
            icon.textContent = '📍';

            const body = document.createElement('span');
            body.className = 'shop-city-modal__result-body';

            const title = document.createElement('strong');
            title.className = 'shop-city-modal__result-name';
            title.textContent = city.name;

            body.appendChild(title);

            const details = getCityDetails(city);
            if (details) {
                const subtitle = document.createElement('span');
                subtitle.className = 'shop-city-modal__result-subtitle';
                subtitle.textContent = details;
                body.appendChild(subtitle);
            }

            button.appendChild(icon);
            button.appendChild(body);

            button.addEventListener('mousedown', (event) => event.preventDefault());
            button.addEventListener('click', () => {
                selectedCity = normalizeCity(city);
                input.value = getCityDisplayValue(selectedCity);
                syncPopularActive(selectedCity.name);
                clearResults();
                input.focus();
            });

            results.appendChild(button);
        });

        setResultsOpen(true);
    };

    const searchCities = (query) => {
        if (!npEnabled || !searchUrl) {
            clearResults();
            return;
        }

        const trimmed = query.trim();
        if (trimmed.length < 1) {
            clearResults();
            return;
        }

        window.clearTimeout(searchTimer);
        searchTimer = window.setTimeout(async () => {
            try {
                const response = await fetch(`${searchUrl}?q=${encodeURIComponent(trimmed)}`, {
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                    credentials: 'same-origin',
                });
                const data = await response.json();
                renderResults(data.items || []);
            } catch (error) {
                console.error(error);
                clearResults();
            }
        }, 250);
    };

    const saveCity = async (city) => {
        const payload = normalizeCity(city);
        if (!payload.name) {
            return false;
        }

        applyButton.disabled = true;

        try {
            const body = new URLSearchParams();
            body.set('city', payload.name);
            body.set('name', payload.name);
            body.set('ref', payload.ref);
            body.set('subtitle', payload.subtitle);
            body.set('label', payload.label);
            body.set('settlementRef', payload.settlementRef);
            body.set('warehouseCityRef', payload.warehouseCityRef || payload.ref);
            body.set('hasLocalWarehouses', payload.hasLocalWarehouses ? '1' : '0');
            body.set('_token', token);

            const response = await fetch(saveUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: body.toString(),
                credentials: 'same-origin',
            });

            const data = await response.json();
            if (!response.ok || !data.success || !data.city) {
                throw new Error(data.error || 'Save failed');
            }

            currentCity = normalizeCity(data.city);
            selectedCity = currentCity;
            config.dataset.currentCity = JSON.stringify(currentCity);
            updateHeader(currentCity);
            syncPopularActive(currentCity.name);
            window.dispatchEvent(new CustomEvent('shop:city-changed', {
                detail: {
                    ...currentCity,
                    deliveryCleared: Boolean(data.deliveryCleared),
                },
            }));

            return true;
        } catch (error) {
            console.error(error);
            return false;
        } finally {
            applyButton.disabled = false;
        }
    };

    const openModal = () => {
        selectedCity = normalizeCity(currentCity);
        input.value = getCityDisplayValue(selectedCity);
        syncPopularActive(selectedCity.name || '');
        clearResults();
        modal.hidden = false;
        modal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('shop-city-modal-open');
        window.setTimeout(() => input.focus(), 0);
    };

    const closeModal = () => {
        modal.hidden = true;
        modal.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('shop-city-modal-open');
        clearResults();
    };

    openButton.addEventListener('click', openModal);

    modal.querySelectorAll('[data-city-close]').forEach((element) => {
        element.addEventListener('click', closeModal);
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && !modal.hidden) {
            closeModal();
        }
    });

    input.addEventListener('input', (event) => {
        selectedCity = null;
        searchCities(event.target.value);
    });

    input.addEventListener('focus', () => {
        if (input.value.trim().length >= 1) {
            searchCities(input.value);
        }
    });

    input.addEventListener('blur', () => {
        window.setTimeout(() => {
            if (!results.contains(document.activeElement)) {
                clearResults();
            }
        }, 150);
    });

    modal.querySelectorAll('[data-city-popular]').forEach((button) => {
        button.addEventListener('click', async () => {
            const cityName = button.dataset.cityPopular || '';
            input.value = cityName;
            syncPopularActive(cityName);

            if (!npEnabled) {
                selectedCity = { name: cityName, ref: '', subtitle: '', label: '', settlementRef: '', warehouseCityRef: '', hasLocalWarehouses: false };
                input.value = cityName;
                return;
            }

            try {
                const response = await fetch(`${searchUrl}?q=${encodeURIComponent(cityName)}`, {
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                    credentials: 'same-origin',
                });
                const data = await response.json();
                const items = data.items || [];
                const exact = items.find((item) => item.name === cityName) || items[0];
                if (exact) {
                    selectedCity = normalizeCity(exact);
                    input.value = getCityDisplayValue(selectedCity);
                    syncPopularActive(selectedCity.name);
                } else {
                    selectedCity = { name: cityName, ref: '', subtitle: '', label: '', settlementRef: '', warehouseCityRef: '', hasLocalWarehouses: false };
                    input.value = cityName;
                }
                clearResults();
            } catch (error) {
                console.error(error);
            }
        });
    });

    applyButton.addEventListener('click', async () => {
        const city = selectedCity || normalizeCity({ name: input.value.trim() });
        const saved = await saveCity(city);
        if (saved) {
            closeModal();
        }
    });
})();
