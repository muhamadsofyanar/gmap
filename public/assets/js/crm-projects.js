(() => {
    const form = document.getElementById('crmProjectSearch');
    if (!form) return;
    const input = form.elements.search;
    let timer, pending;
    let revision = 0;
    let removing = false;

    async function search() {
        if (removing) return;
        clearTimeout(timer);
        pending?.abort();
        const current = ++revision;
        pending = new AbortController();
        const url = new URL(form.action);
        if (input.value.trim()) url.searchParams.set('search', input.value.trim());
        const results = document.getElementById('crmProjectResults');
        results.setAttribute('aria-busy', 'true');
        try {
            const response = await fetch(url, { signal: pending.signal });
            if (!response.ok) throw new Error('Search failed');
            const page = new DOMParser().parseFromString(await response.text(), 'text/html');
            const replacement = page.getElementById('crmProjectResults');
            if (!replacement) throw new Error('Missing results');
            if (current !== revision) return;
            results.replaceWith(replacement);
            history.replaceState(null, '', url);
        } catch (error) {
            if (error.name !== 'AbortError' && current === revision) form.submit();
        } finally {
            if (current === revision) results.removeAttribute('aria-busy');
        }
    }
    input.addEventListener('input', () => {
        pending?.abort();
        revision++;
        clearTimeout(timer);
        timer = setTimeout(search, 300);
    });
    form.addEventListener('submit', event => { event.preventDefault(); search(); });

    document.addEventListener('click', async event => {
        const button = event.target.closest('[data-remove-project]');
        if (!button || removing) return;
        if (!window.confirm(`Hapus permanen proyek "${button.dataset.name}" beserta semua prospek, catatan, riwayat, dan lampirannya dari daftar proyek dan CRM? Tindakan ini tidak dapat dibatalkan.`)) return;
        removing = true;
        clearTimeout(timer);
        pending?.abort();
        revision++;
        button.disabled = true;
        const workspace = document.getElementById('crmProjectsWorkspace');
        const notice = document.getElementById('crmProjectNotice');
        notice.hidden = true;
        try {
            const response = await fetch(`${workspace.dataset.api}/removeProjectFromCrm`, {
                method: 'POST',
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-Token': workspace.dataset.csrf },
                body: new URLSearchParams({ project_id: button.dataset.removeProject })
            });
            if (!(response.headers.get('content-type') || '').includes('application/json')) {
                throw new Error('Sesi mungkin telah berakhir. Muat ulang halaman dan masuk kembali.');
            }
            const data = await response.json();
            if (!response.ok || !data.success) throw new Error(data.message || 'Proyek belum berhasil dihapus dari CRM. Coba lagi.');
            window.location.reload();
        } catch (error) {
            removing = false;
            button.disabled = false;
            notice.textContent = error.message;
            notice.hidden = false;
            notice.scrollIntoView({ block: 'nearest' });
            search();
        }
    });
})();
