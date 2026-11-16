(() => {
    const mobileMq = window.matchMedia('(max-width: 900px)');
    const openClass = 'is-open';
    const bodyOpenClass = 'shop-sheet-open';

    const isMobile = () => mobileMq.matches;

    const getOpenSheets = () => Array.from(document.querySelectorAll(`[data-sheet].${openClass}`));

    const syncBodyLock = () => {
        document.body.classList.toggle(bodyOpenClass, getOpenSheets().length > 0);
    };

    const syncTriggerState = (sheet, open) => {
        const sheetId = sheet?.id;
        if (!sheetId) {
            return;
        }
        document.querySelectorAll(`[data-sheet-open="${sheetId}"]`).forEach((button) => {
            button.setAttribute('aria-expanded', open ? 'true' : 'false');
        });
    };

    const clearDialogPosition = (sheet) => {
        const dialog = sheet?.querySelector('.shop-sheet__dialog');
        if (!dialog) {
            return;
        }
        dialog.style.top = '';
        dialog.style.left = '';
        dialog.style.right = '';
        dialog.style.width = '';
        dialog.style.maxWidth = '';
        dialog.style.maxHeight = '';
    };

    const positionDialogNearTrigger = (sheet) => {
        if (!sheet?.classList.contains('shop-sheet--popover')) {
            return;
        }
        const dialog = sheet.querySelector('.shop-sheet__dialog');
        const trigger = sheet.id
            ? document.querySelector(`[data-sheet-open="${sheet.id}"]`)
            : null;
        if (!dialog || !trigger) {
            return;
        }

        const rect = trigger.getBoundingClientRect();
        const gutter = 12;
        const gap = 8;
        const viewportWidth = window.innerWidth;
        const viewportHeight = window.innerHeight;
        const maxWidth = Math.min(viewportWidth - gutter * 2, 420);
        const spaceBelow = viewportHeight - rect.bottom - gap - gutter;
        const spaceAbove = rect.top - gutter - gap;
        const preferBelow = spaceBelow >= 180 || spaceBelow >= spaceAbove;
        const availableHeight = Math.max(160, preferBelow ? spaceBelow : spaceAbove);
        const maxHeight = Math.min(availableHeight, Math.floor(viewportHeight * 0.7));

        let left = rect.left;
        if (left + maxWidth > viewportWidth - gutter) {
            left = Math.max(gutter, viewportWidth - gutter - maxWidth);
        }

        if (preferBelow) {
            dialog.style.top = `${Math.round(rect.bottom + gap)}px`;
        } else {
            dialog.style.top = `${Math.round(Math.max(gutter, rect.top - gap - maxHeight))}px`;
        }
        dialog.style.left = `${Math.round(left)}px`;
        dialog.style.right = 'auto';
        dialog.style.width = `${Math.round(Math.min(maxWidth, viewportWidth - left - gutter))}px`;
        dialog.style.maxWidth = `${maxWidth}px`;
        dialog.style.maxHeight = `${Math.round(maxHeight)}px`;
    };

    const openSheet = (sheet) => {
        if (!sheet || !isMobile()) {
            return;
        }
        getOpenSheets().forEach((openSheetEl) => {
            if (openSheetEl !== sheet) {
                openSheetEl.classList.remove(openClass);
                syncTriggerState(openSheetEl, false);
                clearDialogPosition(openSheetEl);
            }
        });
        sheet.classList.add(openClass);
        syncTriggerState(sheet, true);
        positionDialogNearTrigger(sheet);
        syncBodyLock();
        const closeBtn = sheet.querySelector('[data-sheet-close]');
        closeBtn?.focus?.();
    };

    const closeSheet = (sheet) => {
        if (!sheet) {
            return;
        }
        sheet.classList.remove(openClass);
        syncTriggerState(sheet, false);
        clearDialogPosition(sheet);
        syncBodyLock();
    };

    const closeAllSheets = () => {
        getOpenSheets().forEach((sheet) => {
            sheet.classList.remove(openClass);
            syncTriggerState(sheet, false);
            clearDialogPosition(sheet);
        });
        syncBodyLock();
    };

    const repositionOpenSheets = () => {
        if (!isMobile()) {
            return;
        }
        getOpenSheets().forEach((sheet) => positionDialogNearTrigger(sheet));
    };

    window.ShopSheet = {
        open: openSheet,
        close: closeSheet,
        closeAll: closeAllSheets,
        isMobile,
    };

    document.addEventListener('click', (event) => {
        const openBtn = event.target.closest('[data-sheet-open]');
        if (openBtn) {
            if (!isMobile()) {
                return;
            }
            const sheetId = openBtn.getAttribute('data-sheet-open');
            const sheet = sheetId
                ? document.getElementById(sheetId)
                : openBtn.closest('[data-sheet-root]')?.querySelector('[data-sheet]');
            if (!sheet) {
                return;
            }
            event.preventDefault();
            if (sheet.classList.contains(openClass)) {
                closeSheet(sheet);
            } else {
                openSheet(sheet);
            }
            return;
        }

        const closeEl = event.target.closest('[data-sheet-close]');
        if (closeEl) {
            const sheet = closeEl.closest('[data-sheet]');
            if (sheet) {
                event.preventDefault();
                closeSheet(sheet);
            }
            return;
        }

        if (!isMobile()) {
            return;
        }

        const autoCloseEl = event.target.closest('[data-sheet-autoclose]');
        if (autoCloseEl) {
            const sheet = autoCloseEl.closest('[data-sheet]');
            if (sheet) {
                closeSheet(sheet);
            }
            if (autoCloseEl.classList.contains('is-active') || autoCloseEl.getAttribute('aria-current') === 'page') {
                event.preventDefault();
            }
            return;
        }

        const opened = getOpenSheets();
        if (opened.length === 0) {
            return;
        }
        const clickedInsideDialog = event.target.closest('.shop-sheet__dialog');
        const clickedTrigger = event.target.closest('[data-sheet-open]');
        if (!clickedInsideDialog && !clickedTrigger) {
            closeAllSheets();
        }
    });

    document.addEventListener('keydown', (event) => {
        if (event.key !== 'Escape') {
            return;
        }
        const opened = getOpenSheets();
        if (opened.length === 0) {
            return;
        }
        closeSheet(opened[opened.length - 1]);
    });

    window.addEventListener('resize', repositionOpenSheets);
    window.addEventListener('scroll', repositionOpenSheets, true);

    mobileMq.addEventListener('change', () => {
        if (!isMobile()) {
            closeAllSheets();
        }
    });
})();
