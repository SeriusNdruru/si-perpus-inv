(function () {
    'use strict';

    function initializeMemberMobileMenu() {
        var sidebar = document.querySelector('[data-member-sidebar]');
        var toggle = document.querySelector('[data-member-menu-toggle]');
        var menu = document.querySelector('[data-member-sidebar-menu]');

        if (!sidebar || !toggle || !menu) {
            return;
        }

        var mobileQuery = window.matchMedia('(max-width: 1000px)');

        function isMobile() {
            return mobileQuery.matches;
        }

        function setOpen(open) {
            sidebar.classList.toggle('is-open', open);
            toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
            toggle.setAttribute('aria-label', open ? 'Tutup menu siswa' : 'Buka menu siswa');
        }

        function closeMenu() {
            setOpen(false);
        }

        function toggleMenu(event) {
            if (!isMobile()) {
                return;
            }

            event.preventDefault();
            event.stopPropagation();
            setOpen(!sidebar.classList.contains('is-open'));
        }

        toggle.addEventListener('click', toggleMenu, false);

        menu.addEventListener('click', function (event) {
            event.stopPropagation();
        }, false);

        var links = menu.querySelectorAll('a');
        Array.prototype.forEach.call(links, function (link) {
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

        function syncMenuState() {
            if (!isMobile()) {
                closeMenu();
            }
        }

        if (typeof mobileQuery.addEventListener === 'function') {
            mobileQuery.addEventListener('change', syncMenuState);
        } else if (typeof mobileQuery.addListener === 'function') {
            mobileQuery.addListener(syncMenuState);
        }

        closeMenu();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initializeMemberMobileMenu, { once: true });
    } else {
        initializeMemberMobileMenu();
    }
})();
