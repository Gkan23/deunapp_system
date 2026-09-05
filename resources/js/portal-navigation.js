const initializePortalNavigation = () => {
    const sidebar = document.querySelector(
        '[data-portal-sidebar]'
    );

    const toggle = document.querySelector(
        '[data-portal-menu-toggle]'
    );

    const closeButton = document.querySelector(
        '[data-portal-menu-close]'
    );

    const overlay = document.querySelector(
        '[data-portal-overlay]'
    );

    if (!sidebar || !toggle || !overlay) {
        return;
    }

    const setOpen = (open) => {
        sidebar.dataset.open = open ? 'true' : 'false';
        toggle.setAttribute(
            'aria-expanded',
            open ? 'true' : 'false'
        );

        document.body.classList.toggle(
            'portal-menu-open',
            open
        );
    };

    toggle.addEventListener('click', () => {
        setOpen(sidebar.dataset.open !== 'true');
    });

    closeButton?.addEventListener('click', () => {
        setOpen(false);
        toggle.focus();
    });

    overlay.addEventListener('click', () => {
        setOpen(false);
        toggle.focus();
    });

    sidebar
        .querySelectorAll('a')
        .forEach((link) => {
            link.addEventListener('click', () => {
                if (window.innerWidth <= 1024) {
                    setOpen(false);
                }
            });
        });

    document.addEventListener('keydown', (event) => {
        if (
            event.key === 'Escape'
            && sidebar.dataset.open === 'true'
        ) {
            setOpen(false);
            toggle.focus();
        }
    });

    window.addEventListener('resize', () => {
        if (window.innerWidth > 1024) {
            setOpen(false);
        }
    });
};

if (document.readyState === 'loading') {
    document.addEventListener(
        'DOMContentLoaded',
        initializePortalNavigation
    );
} else {
    initializePortalNavigation();
}