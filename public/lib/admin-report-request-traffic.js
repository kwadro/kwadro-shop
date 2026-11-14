(function () {
    'use strict';

    function boot() {
        var root = document.querySelector('[data-request-traffic-report]');
        if (!root || typeof window.Chart === 'undefined') {
            if (root && typeof window.Chart === 'undefined') {
                window.setTimeout(boot, 50);
            }
            return;
        }

        var dataUrl = root.getAttribute('data-url') || '';
        var periodSelect = root.querySelector('[data-filter="period"]');
        var customWrap = root.querySelector('[data-custom-range]');
        var fromInput = root.querySelector('[data-filter="from"]');
        var toInput = root.querySelector('[data-filter="to"]');
        var applyButton = root.querySelector('[data-apply-custom]');
        var canvas = root.querySelector('[data-chart]');
        var statusEl = root.querySelector('[data-status]');
        var labelRequests = root.getAttribute('data-label-requests') || 'Requests';
        var labelUniqueIps = root.getAttribute('data-label-unique-ips') || 'Unique IPs';
        var loadError = root.getAttribute('data-load-error') || 'Failed to load chart data';
        var chart = null;
        var activeRequest = null;

        function setStatus(text) {
            if (statusEl) {
                statusEl.textContent = text || '';
            }
        }

        function toggleCustom() {
            if (!customWrap || !periodSelect) {
                return;
            }
            customWrap.classList.toggle('is-hidden', periodSelect.value !== 'custom');
        }

        function buildUrl(period, from, to) {
            var url = new URL(dataUrl, window.location.origin);
            url.searchParams.set('period', period);
            if (period === 'custom') {
                if (from) {
                    url.searchParams.set('from', from);
                }
                if (to) {
                    url.searchParams.set('to', to);
                }
            }
            return url;
        }

        function syncHistory(period, from, to) {
            var params = new URLSearchParams(window.location.search);
            params.set('period', period);
            if (period === 'custom') {
                params.set('from', from || '');
                params.set('to', to || '');
            } else {
                params.delete('from');
                params.delete('to');
            }
            var next = window.location.pathname + '?' + params.toString();
            window.history.replaceState({}, '', next);
        }

        function renderChart(payload) {
            var labels = payload.labels || [];
            var requests = payload.requests || [];
            var uniqueIps = payload.uniqueIps || [];

            if (chart) {
                chart.destroy();
            }

            chart = new window.Chart(canvas.getContext('2d'), {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [
                        {
                            label: labelRequests,
                            data: requests,
                            borderColor: '#2563eb',
                            backgroundColor: 'rgba(37, 99, 235, 0.15)',
                            pointBackgroundColor: '#2563eb',
                            tension: 0.2,
                            fill: false,
                        },
                        {
                            label: labelUniqueIps,
                            data: uniqueIps,
                            borderColor: '#eab308',
                            backgroundColor: 'rgba(234, 179, 8, 0.15)',
                            pointBackgroundColor: '#eab308',
                            tension: 0.2,
                            fill: false,
                        },
                    ],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: {
                        mode: 'index',
                        intersect: false,
                    },
                    scales: {
                        x: {
                            title: {
                                display: true,
                                text: payload.mode === 'hourly' ? '00–24' : '',
                            },
                            ticks: {
                                maxRotation: 45,
                                autoSkip: true,
                                maxTicksLimit: payload.mode === 'hourly' ? 24 : 16,
                            },
                        },
                        y: {
                            beginAtZero: true,
                            ticks: {
                                precision: 0,
                            },
                        },
                    },
                    plugins: {
                        legend: {
                            position: 'top',
                        },
                    },
                },
            });
        }

        function loadData(period, from, to) {
            if (!dataUrl) {
                return;
            }

            if (activeRequest) {
                activeRequest.abort();
            }

            var controller = new AbortController();
            activeRequest = controller;
            setStatus('');
            root.style.opacity = '0.65';

            fetch(buildUrl(period, from, to).toString(), {
                method: 'GET',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    Accept: 'application/json',
                },
                credentials: 'same-origin',
                signal: controller.signal,
            })
                .then(function (response) {
                    if (!response.ok) {
                        throw new Error('Request failed');
                    }
                    return response.json();
                })
                .then(function (payload) {
                    renderChart(payload);
                    syncHistory(payload.period || period, payload.fromDate || from, payload.toDate || to);
                    if (fromInput && payload.fromDate) {
                        fromInput.value = payload.fromDate;
                    }
                    if (toInput && payload.toDate) {
                        toInput.value = payload.toDate;
                    }
                })
                .catch(function (error) {
                    if (error.name === 'AbortError') {
                        return;
                    }
                    setStatus(loadError);
                })
                .finally(function () {
                    root.style.opacity = '1';
                    if (activeRequest === controller) {
                        activeRequest = null;
                    }
                });
        }

        function currentPeriod() {
            return periodSelect ? periodSelect.value : '7d';
        }

        if (periodSelect) {
            periodSelect.addEventListener('change', function () {
                toggleCustom();
                if (periodSelect.value === 'custom') {
                    return;
                }
                loadData(periodSelect.value, '', '');
            });
        }

        if (applyButton) {
            applyButton.addEventListener('click', function () {
                loadData(
                    'custom',
                    fromInput ? fromInput.value : '',
                    toInput ? toInput.value : ''
                );
            });
        }

        toggleCustom();
        loadData(
            root.getAttribute('data-period') || currentPeriod(),
            root.getAttribute('data-from') || '',
            root.getAttribute('data-to') || ''
        );
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();
