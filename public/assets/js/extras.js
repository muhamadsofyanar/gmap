(() => {
    const link = document.querySelector('[data-reminder-api]');
    let pending = false;
    async function refresh() {
        if (!link?.dataset.reminderApi || document.hidden || pending) return;
        pending = true;
        try {
            const response = await fetch(link.dataset.reminderApi, { headers: { Accept: 'application/json' } });
            if (!response.ok || !(response.headers.get('content-type') || '').includes('application/json')) return;
            const data = await response.json();
            if (data.success) link.querySelector('[data-reminder-count]').textContent = String(Number(data.counts.total) || 0);
        } catch (_) { /* Retry when the panel is visible again. */ }
        finally { pending = false; }
    }
    window.setInterval(refresh, 60000);
    window.addEventListener('pageshow', refresh);
    document.addEventListener('visibilitychange', refresh);
    document.querySelectorAll('.intake-workspace form').forEach(form => {
        const select=form.querySelector('[name=project_id]'), input=form.querySelector('[name=project_name]');
        function sync() { input.required=select.value==='0'; input.closest('label').hidden=select.value!=='0'; }
        select.addEventListener('change',sync); sync();
    });
})();
