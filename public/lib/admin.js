(() => {
    'use strict';

    // Shared admin helpers placeholder (no Webpack/Encore build in this project).
    document.documentElement.classList.add('admin-assets-ready');

    const scrollActiveSidebarGroupIntoView = () => {
        const sidebar = document.querySelector('.ea-sidebar');
        if (!(sidebar instanceof HTMLElement)) {
            return;
        }

        const activeItem = sidebar.querySelector('.ea-sidebar-item.is-active');
        const expandedGroup = sidebar.querySelector('.ea-sidebar-item.has-submenu.is-expanded');
        const target = (activeItem instanceof HTMLElement && activeItem.closest('.ea-sidebar-item.has-submenu'))
            || (expandedGroup instanceof HTMLElement ? expandedGroup : null)
            || (activeItem instanceof HTMLElement ? activeItem : null);

        if (!(target instanceof HTMLElement)) {
            return;
        }

        const parentRect = sidebar.getBoundingClientRect();
        const targetRect = target.getBoundingClientRect();
        const offset = targetRect.top - parentRect.top - Math.max(24, parentRect.height * 0.12);

        sidebar.scrollTo({
            top: sidebar.scrollTop + offset,
            behavior: 'auto',
        });
    };

    const run = () => {
        window.requestAnimationFrame(() => {
            window.requestAnimationFrame(scrollActiveSidebarGroupIntoView);
        });
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', run, { once: true });
    } else {
        run();
    }
})();
