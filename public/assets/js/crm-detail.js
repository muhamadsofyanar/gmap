(() => {
    const page = document.querySelector('.crm-detail-page');
    if (!page) return;
    const nav = page.querySelector('.prospect-tabs');
    const tabs = [...nav.querySelectorAll('[data-detail-tab]')];
    const panels = [...page.querySelectorAll('[data-detail-panel]')];
    nav.setAttribute('role', 'tablist');
    tabs.forEach(tab => tab.setAttribute('role', 'tab'));
    panels.forEach(panel => {
        panel.setAttribute('role', 'tabpanel');
        panel.setAttribute('aria-labelledby', tabs.find(tab => tab.hash === '#' + panel.id).id);
    });

    function activate(tab) {
        tabs.forEach(item => {
            const selected = item === tab;
            item.setAttribute('aria-selected', String(selected));
            item.tabIndex = selected ? 0 : -1;
            item.setAttribute('aria-controls', item.hash.slice(1));
        });
        panels.forEach(panel => { panel.hidden = tab.hash !== '#' + panel.id; });
    }

    function restore() {
        let id;
        try { id = decodeURIComponent(location.hash.slice(1)); } catch (_) { id = ''; }
        const target = document.getElementById(id);
        const panel = target?.closest('[data-detail-panel]');
        activate(tabs.find(tab => tab.hash === '#' + panel?.id) || tabs[0]);
        if (target) {
            let ancestor = target;
            while (ancestor && ancestor !== page) {
                if (ancestor.tagName === 'DETAILS') ancestor.open = true;
                ancestor = ancestor.parentElement;
            }
            // The browser may attempt anchor scrolling before the panel is revealed.
            requestAnimationFrame(() => target.scrollIntoView({ block: 'nearest', behavior: 'instant' }));
        }
    }

    function select(tab) {
        activate(tab);
        history.replaceState(null, '', tab.hash);
    }
    tabs.forEach((tab, index) => {
        tab.addEventListener('click', event => { event.preventDefault(); select(tab); });
        tab.addEventListener('keydown', event => {
            let next;
            if (event.key === 'ArrowRight') next = (index + 1) % tabs.length;
            if (event.key === 'ArrowLeft') next = (index - 1 + tabs.length) % tabs.length;
            if (event.key === 'Home') next = 0;
            if (event.key === 'End') next = tabs.length - 1;
            if (next === undefined) return;
            event.preventDefault();
            select(tabs[next]);
            tabs[next].focus();
        });
    });
    window.addEventListener('hashchange', restore);
    restore();
})();
