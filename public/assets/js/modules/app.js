export class AppShell {
    constructor() {
        this.initTheme();
        this.sidebar = document.querySelector('.app-sidebar');
        this.bindSidebarToggle();
        this.bindSidebarAutoClose();
    }

    initTheme() {
        document.body.classList.add('mrp-ready');
    }

    bindSidebarToggle() {
        const toggles = document.querySelectorAll('[data-sidebar-toggle]');
        if (!this.sidebar || toggles.length === 0) {
            return;
        }

        toggles.forEach((toggle) => {
            toggle.addEventListener('click', () => {
                const isOpen = this.toggleSidebar();
                toggle.setAttribute('aria-expanded', String(isOpen));
            });
        });
    }

    bindSidebarAutoClose() {
        if (!this.sidebar) {
            return;
        }

        const links = this.sidebar.querySelectorAll('.app-sidebar__link');
        links.forEach((link) => {
            link.addEventListener('click', () => {
                if (window.matchMedia('(max-width: 991px)').matches) {
                    this.toggleSidebar(false);
                }
            });
        });
    }

    toggleSidebar(force) {
        if (!this.sidebar) {
            return false;
        }

        const shouldOpen = typeof force === 'boolean' ? force : !this.sidebar.classList.contains('is-open');
        this.sidebar.classList.toggle('is-open', shouldOpen);
        document.body.classList.toggle('sidebar-open', shouldOpen);
        return shouldOpen;
    }
}

