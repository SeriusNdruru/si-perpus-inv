(function () {
    'use strict';

    function initializeAdminMobileMenu() {
        var sidebar = document.querySelector('[data-admin-sidebar]');
        var toggle = document.querySelector('[data-admin-menu-toggle]');
        var menu = document.querySelector('[data-admin-sidebar-menu]');

        if (!sidebar || !toggle || !menu) {
            return;
        }

        var mobileQuery = window.matchMedia('(max-width: 900px)');

        function isMobile() {
            return mobileQuery.matches;
        }

        function setOpen(open) {
            sidebar.classList.toggle('is-open', open);
            toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
            toggle.setAttribute('aria-label', open ? 'Tutup menu admin' : 'Buka menu admin');
        }

        function closeMenu() {
            setOpen(false);
        }

        toggle.addEventListener('click', function (event) {
            if (!isMobile()) {
                return;
            }

            event.preventDefault();
            event.stopPropagation();
            setOpen(!sidebar.classList.contains('is-open'));
        }, false);

        menu.addEventListener('click', function (event) {
            event.stopPropagation();
        }, false);

        Array.prototype.forEach.call(menu.querySelectorAll('a'), function (link) {
            link.addEventListener('click', closeMenu, false);
        });

        document.addEventListener('click', function (event) {
            if (!isMobile() || !sidebar.classList.contains('is-open')) {
                return;
            }

            if (!sidebar.contains(event.target)) {
                closeMenu();
            }
        }, false);

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') {
                closeMenu();
                toggle.focus();
            }
        }, false);

        function synchronizeMenu() {
            if (!isMobile()) {
                closeMenu();
            }
        }

        if (typeof mobileQuery.addEventListener === 'function') {
            mobileQuery.addEventListener('change', synchronizeMenu);
        } else if (typeof mobileQuery.addListener === 'function') {
            mobileQuery.addListener(synchronizeMenu);
        }

        closeMenu();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initializeAdminMobileMenu, { once: true });
    } else {
        initializeAdminMobileMenu();
    }
})();
