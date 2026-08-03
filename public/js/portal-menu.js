(() => {
    'use strict';

    const initializeMenu = (button) => {
        const menuId = button.getAttribute('aria-controls');
        const menu = menuId ? document.getElementById(menuId) : null;

        if (!menu) {
            return;
        }

        const setOpen = (open, returnFocus = false) => {
            menu.classList.toggle('is-open', open);
            button.setAttribute('aria-expanded', open ? 'true' : 'false');
            button.setAttribute('aria-label', open ? 'Tutup menu' : 'Buka menu');

            if (returnFocus) {
                button.focus();
            }
        };

        button.addEventListener('click', (event) => {
            event.preventDefault();
            event.stopPropagation();
            setOpen(!menu.classList.contains('is-open'));
        });

        menu.addEventListener('click', (event) => {
            if (event.target.closest('a')) {
                setOpen(false);
            }
        });

        document.addEventListener('click', (event) => {
            if (!menu.classList.contains('is-open')) {
                return;
            }

            if (!menu.contains(event.target) && !button.contains(event.target)) {
                setOpen(false);
            }
        });

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && menu.classList.contains('is-open')) {
                setOpen(false, true);
            }
        });

        window.addEventListener('resize', () => {
            if (window.innerWidth > 1000 && menu.classList.contains('is-open')) {
                setOpen(false);
            }
        });
    };

    document.addEventListener('DOMContentLoaded', () => {
        document.querySelectorAll('[data-portal-menu-toggle]').forEach(initializeMenu);
    });
})();
