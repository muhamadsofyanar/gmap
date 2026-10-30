(() => {
    const sidebar = document.getElementById('app-sidebar');
    const toggle = document.getElementById('sidebarToggle');
    const backdrop = document.getElementById('sidebarBackdrop');
    const close = document.getElementById('sidebarClose');
    const desktop = window.matchMedia('(min-width: 768px)');
    const background = [...document.querySelectorAll('.app-topbar, .app-content, .app-footer, .app-flashes')];
    let menuOpen = false;

    function sync() {
        if (!sidebar || !toggle) return;
        const collapsed = desktop.matches && document.documentElement.classList.contains('sidebar-collapsed');
        const label = desktop.matches ? (collapsed ? 'Perluas sidebar' : 'Minimalkan sidebar') : 'Buka menu navigasi';
        toggle.setAttribute('aria-expanded', String(desktop.matches ? !collapsed : menuOpen));
        toggle.setAttribute('aria-label', label);
        toggle.title = label;
        sidebar.inert = !desktop.matches && !menuOpen;
        if (desktop.matches || menuOpen) sidebar.removeAttribute('aria-hidden');
        else sidebar.setAttribute('aria-hidden', 'true');
    }
    function setMenu(open, returnFocus = false) {
        menuOpen = open && !desktop.matches;
        document.body.classList.toggle('mobile-menu-open', menuOpen);
        if (backdrop) backdrop.hidden = !menuOpen;
        background.forEach(element => { element.inert = menuOpen; });
        if (menuOpen) {
            sidebar.setAttribute('role', 'dialog');
            sidebar.setAttribute('aria-modal', 'true');
        } else {
            sidebar?.removeAttribute('role');
            sidebar?.removeAttribute('aria-modal');
        }
        sync();
        if (menuOpen) close?.focus();
        else if (returnFocus) toggle?.focus();
    }
    toggle?.addEventListener('click', () => {
        if (!desktop.matches) { setMenu(!menuOpen); return; }
        const collapsed = document.documentElement.classList.toggle('sidebar-collapsed');
        try { localStorage.setItem('gmap-sidebar-collapsed', collapsed ? '1' : '0'); } catch (_) {}
        sync();
    });
    close?.addEventListener('click', () => setMenu(false, true));
    backdrop?.addEventListener('click', () => setMenu(false, true));
    sidebar?.addEventListener('click', event => {
        if (event.target.closest('a') && !desktop.matches) setMenu(false);
    });
    desktop.addEventListener('change', () => setMenu(false));
    window.addEventListener('pageshow', () => setMenu(false));
    document.addEventListener('keydown', event => {
        if (menuOpen) {
            if (event.key === 'Escape') { event.preventDefault(); setMenu(false, true); }
            if (event.key === 'Tab') trapFocus(event, sidebar);
        }
        const modal = document.getElementById('waModal');
        if (modal && !modal.classList.contains('hidden')) {
            if (event.key === 'Escape') { event.preventDefault(); window.closeWaModal(); }
            if (event.key === 'Tab') trapFocus(event, modal);
        }
    });
    function trapFocus(event, container) {
        const items = [...container.querySelectorAll('a[href], button:not(:disabled), input, select, textarea, [tabindex="0"]')].filter(item => item.getClientRects().length && !item.hidden);
        if (!items.length) return;
        const first = items[0], last = items[items.length - 1];
        if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); }
        if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
    }
    sync();

    document.querySelectorAll('.crm-table-scroll, .overflow-x-auto').forEach(region => {
        if (!region.querySelector('table')) return;
        region.setAttribute('role', 'region');
        region.setAttribute('aria-label', 'Tabel data, geser untuk melihat kolom lainnya');
        const hint = document.createElement('p');
        hint.className = 'table-scroll-hint';
        hint.textContent = 'Geser tabel ke samping untuk melihat kolom lainnya.';
        region.before(hint);
        const update = () => {
            const overflow = region.scrollWidth > region.clientWidth + 1;
            hint.hidden = !overflow;
            region.tabIndex = overflow ? 0 : -1;
        };
        new ResizeObserver(update).observe(region);
        update();
    });
    document.addEventListener('submit', event => {
        const form = event.target;
        if (event.defaultPrevented || !form.action || !/\/sales\/save$/.test(new URL(form.action).pathname)) return;
        const button = event.submitter;
        if (button) { button.disabled = true; button.setAttribute('aria-busy', 'true'); button.textContent = 'Menyimpan...'; }
    });
    window.addEventListener('pageshow', () => {
        document.querySelectorAll('form[action$="/sales/save"] button[aria-busy]').forEach(button => {
            button.disabled = false; button.removeAttribute('aria-busy');
            button.textContent = button.dataset.originalLabel || 'Simpan';
        });
    });
    document.querySelectorAll('form[action$="/sales/save"] button[type="submit"]').forEach(button => { button.dataset.originalLabel = button.textContent; });
})();
