(function () {
    'use strict';

    function boot() {
    var root = document.querySelector('.mailbox-list-filters');
    if (!root) {
        return;
    }

    var indexUrl = root.getAttribute('data-index-url') || window.location.href;

    function applyFilters() {
        var url = new URL(indexUrl, window.location.origin);
        var account = root.querySelector('[data-filter="account"]');
        var group = root.querySelector('[data-filter="group"]');
        var accountValue = account ? account.value : '';
        var groupValue = group ? group.value : '';

        if (accountValue !== '') {
            url.searchParams.set('filters[mailbox][comparison]', '=');
            url.searchParams.set('filters[mailbox][value]', accountValue);
        }
        if (groupValue !== '') {
            url.searchParams.set('filters[messageGroup][comparison]', '=');
            url.searchParams.set('filters[messageGroup][value]', groupValue);
        }

        window.location.assign(url.toString());
    }

    root.querySelectorAll('select').forEach(function (select) {
        select.addEventListener('change', applyFilters);
    });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();
