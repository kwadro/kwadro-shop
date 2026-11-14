(function () {
    'use strict';

    function boot() {
        var root = document.querySelector('.request-list-filters');
        if (!root) {
            return;
        }

        var indexUrl = root.getAttribute('data-index-url') || '';

        function applyFilters() {
            var url = new URL(window.location.href);
            url.searchParams.delete('rl_path');
            url.searchParams.delete('rl_visitor');
            url.searchParams.delete('rl_period');
            url.searchParams.delete('page');

            var pathSelect = root.querySelector('[data-filter="path"]');
            var visitorSelect = root.querySelector('[data-filter="visitor"]');
            var periodSelect = root.querySelector('[data-filter="period"]');
            var pathValue = pathSelect ? pathSelect.value : '';
            var visitorValue = visitorSelect ? visitorSelect.value : '';
            var periodValue = periodSelect ? periodSelect.value : '';

            if (pathValue !== '') {
                url.searchParams.set('rl_path', pathValue);
            }
            if (visitorValue !== '') {
                url.searchParams.set('rl_visitor', visitorValue);
            }
            if (periodValue !== '') {
                url.searchParams.set('rl_period', periodValue);
            }

            window.location.assign(url.toString());
        }

        // Ignore Tom Select init noise: only navigate when the value actually changes.
        root.querySelectorAll('select').forEach(function (select) {
            var previous = select.value;
            select.addEventListener('change', function () {
                if (select.value === previous) {
                    return;
                }
                previous = select.value;
                applyFilters();
            });
        });

        var reset = root.querySelector('[data-filter-reset]');
        if (reset && indexUrl) {
            reset.addEventListener('click', function (event) {
                event.preventDefault();
                window.location.assign(indexUrl);
            });
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();
