(function () {
    'use strict';

    if (!document.body) {
        return;
    }

    const POLL_URL = (function () {
        const locale = (window.location.pathname.match(/\/admin\/(uk|en)(?:\/|$)/) || [])[1] || 'uk';
        return '/admin/' + locale + '/mailbox/notifications/poll';
    })();
    const MAILBOX_URL = (function () {
        const locale = (window.location.pathname.match(/\/admin\/(uk|en)(?:\/|$)/) || [])[1] || 'uk';
        return '/admin/' + locale + '/mailbox-message';
    })();
    const POLL_MS = 30000;
    let permissionRequested = false;
    let toastHost = null;

    function ensurePermission() {
        if (!window.Notification) {
            return Promise.resolve(false);
        }
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
        return Notification.requestPermission().then(function (p) {
            return p === 'granted';
        });
    }

    function ensureToastHost() {
        if (toastHost) {
            return toastHost;
        }
        toastHost = document.createElement('div');
        toastHost.id = 'admin-mailbox-toast-host';
        toastHost.setAttribute('aria-live', 'polite');
        document.body.appendChild(toastHost);
        return toastHost;
    }

    function showToast(title, body, options) {
        options = options || {};
        const host = ensureToastHost();
        const el = document.createElement('div');
        el.className = 'admin-mailbox-toast';
        el.innerHTML =
            '<div class="admin-mailbox-toast__icon" aria-hidden="true">✉</div>' +
            '<div class="admin-mailbox-toast__content">' +
            '<div class="admin-mailbox-toast__title"></div>' +
            (body ? '<div class="admin-mailbox-toast__body"></div>' : '') +
            '</div>' +
            '<button type="button" class="admin-mailbox-toast__close" aria-label="Close">×</button>';
        el.querySelector('.admin-mailbox-toast__title').textContent = title || '';
        const bodyEl = el.querySelector('.admin-mailbox-toast__body');
        if (bodyEl && body) {
            bodyEl.textContent = body;
        }
        const close = function () {
            el.classList.add('is-leaving');
            window.setTimeout(function () {
                if (el.parentNode) {
                    el.parentNode.removeChild(el);
                }
            }, 220);
        };
        el.querySelector('.admin-mailbox-toast__close').addEventListener('click', function (e) {
            e.stopPropagation();
            close();
        });
        el.addEventListener('click', function () {
            window.location.href = MAILBOX_URL;
        });
        host.appendChild(el);
        window.setTimeout(close, options.ttl || 9000);
    }

    function showBrowserNotification(title, body, tag) {
        try {
            const n = new Notification(title, {
                body: body || '',
                tag: tag || 'mailbox',
                renotify: true,
            });
            n.onclick = function () {
                window.focus();
                window.location.href = MAILBOX_URL;
                n.close();
            };
        } catch (e) {
            // ignore
        }
    }

    function notify(title, body, tag) {
        showToast(title, body);
        ensurePermission().then(function (ok) {
            if (ok) {
                showBrowserNotification(title, body, tag);
            }
        });
    }

    function updateBadge(unread) {
        let badge = document.getElementById('admin-mailbox-unread-badge');
        if (!badge) {
            badge = document.createElement('div');
            badge.id = 'admin-mailbox-unread-badge';
            badge.addEventListener('click', function () {
                window.location.href = MAILBOX_URL;
            });
            document.body.appendChild(badge);
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

                if (data.greeting && data.greeting.title) {
                    notify(
                        data.greeting.title,
                        data.greeting.body || '',
                        'mailbox-greeting'
                    );
                }

                const items = Array.isArray(data.new) ? data.new : [];
                items.forEach(function (item) {
                    notify(
                        item.title || 'New mail',
                        item.body || item.subject || '',
                        'mailbox-' + item.id
                    );
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
