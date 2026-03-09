export class AppShell {
    constructor() {
        this.initTheme();
        this.sidebar = document.querySelector('.app-sidebar');
        this.sidebarPinToggles = document.querySelectorAll('[data-sidebar-pin-toggle]');
        this.storageKey = 'mrp.sidebar.desktop.visible';
        this.applyDesktopSidebarPreference();
        this.bindSidebarToggle();
        this.bindDesktopSidebarPinToggle();
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

    bindDesktopSidebarPinToggle() {
        if (!this.sidebar || this.sidebarPinToggles.length === 0) {
            return;
        }

        this.sidebarPinToggles.forEach((toggle) => {
            toggle.addEventListener('click', () => {
                const isVisible = this.isDesktopSidebarVisible();
                this.setDesktopSidebarVisibility(!isVisible);
            });
        });

        this.updateDesktopSidebarToggleUI();
    }

    applyDesktopSidebarPreference() {
        if (!this.sidebar || window.matchMedia('(max-width: 991px)').matches) {
            return;
        }

        const stored = window.localStorage.getItem(this.storageKey);
        if (stored === null) {
            this.setDesktopSidebarVisibility(true, false);
            return;
        }

        this.setDesktopSidebarVisibility(stored === '1', false);
    }

    setDesktopSidebarVisibility(isVisible, persist = true) {
        document.body.classList.toggle('sidebar-desktop-hidden', !isVisible);
        document.documentElement.classList.toggle('sidebar-desktop-hidden', !isVisible);

        if (persist) {
            window.localStorage.setItem(this.storageKey, isVisible ? '1' : '0');
        }

        this.updateDesktopSidebarToggleUI();
    }

    updateDesktopSidebarToggleUI() {
        if (this.sidebarPinToggles.length === 0) {
            return;
        }

        const isVisible = this.isDesktopSidebarVisible();

        this.sidebarPinToggles.forEach((toggle) => {
            toggle.setAttribute('aria-pressed', String(isVisible));
            toggle.setAttribute('title', isVisible ? 'Ocultar sidebar' : 'Fijar sidebar');
            toggle.innerHTML = isVisible
                ? '<i class="fa-solid fa-thumbtack"></i>'
                : '<i class="fa-solid fa-bars"></i>';
        });
    }

    isDesktopSidebarVisible() {
        return !document.documentElement.classList.contains('sidebar-desktop-hidden');
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
