(() => {
    const root = document.documentElement;
    const media = window.matchMedia('(prefers-color-scheme: dark)');
    const picker = document.querySelector('[data-theme-picker]');
    const allowed = ['light', 'dark', 'system'];
    let preference = 'system';
    try { const stored = localStorage.getItem('legaone-theme'); if (allowed.includes(stored)) preference = stored; } catch (_) {}
    function apply() {
        root.dataset.theme = preference === 'system' ? (media.matches ? 'dark' : 'light') : preference;
        root.dataset.themePreference = preference;
        if (picker) picker.value = preference;
    }
    apply();
    picker?.addEventListener('change', () => {
        preference = allowed.includes(picker.value) ? picker.value : 'system';
        try { localStorage.setItem('legaone-theme', preference); } catch (_) {}
        apply();
    });
    media.addEventListener('change', apply);
    window.addEventListener('storage', event => {
        if (event.key === 'legaone-theme') { preference = allowed.includes(event.newValue) ? event.newValue : 'system'; apply(); }
    });
    // Full-page forms use indeterminate feedback: upload and commit have no measurable percentage.
    document.querySelectorAll('[data-process-form]').forEach(form => {
        form.addEventListener('submit', event => {
            if (event.defaultPrevented || !form.checkValidity()) return;
            if (form.dataset.processing) { event.preventDefault(); return; }
            if (form.hasAttribute('data-import-selection')) {
                const selected=[...form.querySelectorAll('input[name="selected[]"]:checked')].map(input=>input.value);
                let payload=form.querySelector('input[name="selected_json"]');
                if (!payload) { payload=document.createElement('input'); payload.type='hidden'; payload.name='selected_json'; form.append(payload); }
                payload.value=JSON.stringify(selected);
                form.querySelectorAll('input[name="selected[]"]').forEach(input=>{input.dataset.importName=input.name;input.removeAttribute('name');});
            }
            form.dataset.processing = '1'; form.setAttribute('aria-busy', 'true');
            form.querySelectorAll('button[type=submit]').forEach(button => {
                button.disabled = true; button.dataset.originalLabel = button.textContent;
                button.textContent = button.dataset.processLabel || 'Menyimpan pilihan…';
            });
            const status = form.querySelector('[role=status]'); if (status) status.hidden = false;
        });
    });
    // Restore controls when navigating back via browser page cache.
    window.addEventListener('pageshow', () => {
        document.querySelectorAll('[data-process-form]').forEach(form => {
            if (!form.dataset.processing) return;
            form.querySelectorAll('[data-import-name]').forEach(input=>{input.name=input.dataset.importName;delete input.dataset.importName;});
            delete form.dataset.processing; form.removeAttribute('aria-busy');
            form.querySelectorAll('[data-original-label]').forEach(button => {
                button.disabled = false; button.textContent = button.dataset.originalLabel; delete button.dataset.originalLabel;
            });
            const status = form.querySelector('[role=status]'); if (status) status.hidden = true;
        });
    });
})();
