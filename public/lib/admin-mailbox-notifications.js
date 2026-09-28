(function () {
    'use strict';

    if (!window.Notification || !document.body) {
        return;
    }

    const POLL_URL = (function () {
        const locale = (window.location.pathname.match(/\/admin\/(uk|en)(?:\/|$)/) || [])[1] || 'uk';
        return '/admin/' + locale + '/mailbox/notifications/poll';
    })();
    const POLL_MS = 45000;
    let permissionRequested = false;

    function ensurePermission() {
        if (Notification.permission === 'granted') {
            return Promise.resolve(true);
        }
        if (Notification.permission === 'denied') {
            return Promise.resolve(false);
        }
        if (permissionRequested) {
            return Promise.resolve(false);
        }
        permissionRequested = true;
        return Notification.requestPermission().then((p) => p === 'granted');
    }

    function showNotification(item) {
        const title = item.subject || 'Новий лист';
        const body = [item.from, item.mailbox].filter(Boolean).join('\n');
        try {
            const n = new Notification(title, {
                body: body,
                tag: 'mailbox-' + item.id,
                renotify: true,
            });
            n.onclick = function () {
                window.focus();
                n.close();
            };
        } catch (e) {
            // ignore
        }
    }

    function updateBadge(unread) {
        let badge = document.getElementById('admin-mailbox-unread-badge');
        if (!badge) {
            const menu = document.querySelector('.sidebar, .content-wrapper, body');
            badge = document.createElement('div');
            badge.id = 'admin-mailbox-unread-badge';
            badge.style.cssText = 'position:fixed;bottom:16px;right:16px;z-index:9999;background:#0d6efd;color:#fff;padding:8px 12px;border-radius:20px;font:600 13px/1 sans-serif;box-shadow:0 4px 12px rgba(0,0,0,.2);display:none;cursor:pointer;';
            badge.addEventListener('click', function () {
                const locale = (window.location.pathname.match(/\/admin\/(uk|en)(?:\/|$)/) || [])[1] || 'uk';
                window.location.href = '/admin/' + locale + '/mailbox-message';
            });
            (menu || document.body).appendChild(badge);
        }
        if (unread > 0) {
            badge.style.display = 'block';
            badge.textContent = '✉ ' + unread;
        } else {
            badge.style.display = 'none';
        }
    }

    function poll() {
        fetch(POLL_URL, {
            credentials: 'same-origin',
            headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' },
        })
            .then(function (r) {
                if (!r.ok) {
                    throw new Error('poll failed');
                }
                return r.json();
            })
            .then(function (data) {
                updateBadge(Number(data.unread) || 0);
                const items = Array.isArray(data.new) ? data.new : [];
                if (items.length === 0) {
                    return;
                }
                ensurePermission().then(function (ok) {
                    if (!ok) {
                        return;
                    }
                    items.forEach(showNotification);
                });
            })
            .catch(function () {
                // silent
            });
    }

    ensurePermission();
    poll();
    window.setInterval(poll, POLL_MS);
})();
