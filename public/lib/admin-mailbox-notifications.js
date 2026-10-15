(function () {
    'use strict';

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
    let clearAllBtn = null;
    let started = false;

    const CHANNEL_META = {
        mail: { icon: '✉', className: 'is-mail' },
        cart: { icon: 'C', className: 'is-cart' },
        order: { icon: 'O', className: 'is-order' },
        payment: { icon: 'P', className: 'is-payment' },
        requests: { icon: 'IP', className: 'is-requests' },
    };

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

    function toastCount() {
        if (!toastHost) {
            return 0;
        }
        return toastHost.querySelectorAll('.admin-mailbox-toast').length;
    }

    function updateClearAllVisibility() {
        if (!clearAllBtn) {
            return;
        }
        clearAllBtn.hidden = toastCount() === 0;
    }

    function clearAllToasts() {
        if (!toastHost) {
            return;
        }
        toastHost.querySelectorAll('.admin-mailbox-toast').forEach(function (el) {
            dismissToast(el, true);
        });
        updateClearAllVisibility();
    }

    function ensureToastHost() {
        if (toastHost && toastHost.isConnected) {
            return toastHost;
        }
        toastHost = document.createElement('div');
        toastHost.id = 'admin-mailbox-toast-host';
        toastHost.setAttribute('aria-live', 'polite');

        const locale = (window.location.pathname.match(/\/admin\/(uk|en)(?:\/|$)/) || [])[1] || 'uk';
        clearAllBtn = document.createElement('button');
        clearAllBtn.type = 'button';
        clearAllBtn.id = 'admin-mailbox-toast-clear-all';
        clearAllBtn.className = 'admin-mailbox-toast-clear-all';
        clearAllBtn.textContent = locale === 'uk' ? 'Видалити всі' : 'Clear all';
        clearAllBtn.hidden = true;
        clearAllBtn.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            clearAllToasts();
        });

        toastHost.appendChild(clearAllBtn);
        document.body.appendChild(toastHost);
        return toastHost;
    }

    function dismissToast(el, immediate) {
        if (!el || !el.parentNode) {
            return;
        }
        if (immediate) {
            el.parentNode.removeChild(el);
            updateClearAllVisibility();
            return;
        }
        el.classList.add('is-leaving');
        window.setTimeout(function () {
            if (el.parentNode) {
                el.parentNode.removeChild(el);
            }
            updateClearAllVisibility();
        }, 220);
    }

    function showToast(title, body, options) {
        options = options || {};
        const channel = options.channel || 'mail';
        const meta = CHANNEL_META[channel] || CHANNEL_META.mail;
        const targetUrl = options.url || MAILBOX_URL;
        const host = ensureToastHost();
        const el = document.createElement('div');
        el.className = 'admin-mailbox-toast ' + meta.className;
        el.innerHTML =
            '<div class="admin-mailbox-toast__icon" aria-hidden="true"></div>' +
            '<div class="admin-mailbox-toast__content">' +
            '<div class="admin-mailbox-toast__title"></div>' +
            (body ? '<div class="admin-mailbox-toast__body"></div>' : '') +
            '</div>' +
            '<button type="button" class="admin-mailbox-toast__close" aria-label="Close">×</button>';
        el.querySelector('.admin-mailbox-toast__icon').textContent = meta.icon;
        el.querySelector('.admin-mailbox-toast__title').textContent = title || '';
        const bodyEl = el.querySelector('.admin-mailbox-toast__body');
        if (bodyEl && body) {
            bodyEl.textContent = body;
        }
        el.querySelector('.admin-mailbox-toast__close').addEventListener('click', function (e) {
            e.stopPropagation();
            dismissToast(el, false);
        });
        el.addEventListener('click', function () {
            window.location.href = targetUrl;
        });
        host.appendChild(el);
        updateClearAllVisibility();

        if (options.ttl && options.ttl > 0) {
            window.setTimeout(function () {
                dismissToast(el, false);
            }, options.ttl);
        }
    }

    function showBrowserNotification(title, body, tag, url) {
        try {
            const n = new Notification(title, {
                body: body || '',
                tag: tag || 'admin-toast',
                renotify: true,
            });
            n.onclick = function () {
                window.focus();
                window.location.href = url || MAILBOX_URL;
                n.close();
            };
        } catch (e) {
            // ignore
        }
    }

    function notify(title, body, options) {
        options = options || {};
        showToast(title, body, options);
        ensurePermission().then(function (ok) {
            if (ok) {
                showBrowserNotification(title, body, options.tag, options.url);
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
                    notify(data.greeting.title, data.greeting.body || '', {
                        channel: 'mail',
                        tag: 'mailbox-greeting',
                        url: MAILBOX_URL,
                    });
                }

                const mailItems = Array.isArray(data.new) ? data.new : [];
                mailItems.forEach(function (item) {
                    notify(item.title || 'New mail', item.body || item.subject || '', {
                        channel: 'mail',
                        tag: 'mailbox-' + item.id,
                        url: MAILBOX_URL,
                    });
                });

                const shopItems = Array.isArray(data.shop) ? data.shop : [];
                shopItems.forEach(function (item) {
                    notify(item.title || 'Shop update', item.body || '', {
                        channel: item.channel || 'order',
                        tag: 'shop-' + item.channel + '-' + item.id,
                        url: item.url || MAILBOX_URL,
                    });
                });
            })
            .catch(function () {
                // silent
            });
    }

    function start() {
        if (started || !document.body) {
            return;
        }
        started = true;
        ensurePermission();
        ensureToastHost();
        poll();
        window.setInterval(poll, POLL_MS);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', start);
    } else {
        start();
    }
})();
