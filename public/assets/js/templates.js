(() => {
    const workspace = document.getElementById('templateWorkspace');
    if (!workspace) return;
    document.getElementById('useBusinessTemplate')?.addEventListener('click', () => {
        const option = document.getElementById('templateBusinessPreset').selectedOptions[0];
        if (!option.dataset.body) return;
        const form = document.querySelector('#newTemplate .template-form');
        if ((form.elements.name.value.trim() || form.elements.body.value.trim()) &&
            !window.confirm('Ganti isi form template baru dengan contoh yang dipilih?')) return;
        form.elements.name.value = option.dataset.name;
        form.elements.body.value = option.dataset.body;
        form.elements.body.dispatchEvent(new Event('input'));
        form.elements.body.focus();
    });
    workspace.querySelectorAll('.template-form').forEach(form => {
        const preview = () => {
            form.querySelector('.template-preview').textContent = form.elements.body.value.replace(/\{\{(nama_bisnis|nama_projek)\}\}/g,
                (_, key) => key === 'nama_bisnis' ? 'Laundry Bekasi' : 'Proyek Laundry');
        };
        form.elements.body.addEventListener('input', preview);
        preview();
        form.addEventListener('submit', async event => {
            event.preventDefault();
            const button = form.querySelector('button[type=submit]');
            const notice = form.querySelector('[role=status]');
            const buttonLabel = button.textContent;
            button.disabled = true;
            button.textContent = 'Menyimpan...';
            button.setAttribute('aria-busy', 'true');
            notice.hidden = true;
            try {
                const response = await fetch(workspace.dataset.saveUrl, {
                    method: 'POST', body: new FormData(form),
                    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-Token': workspace.dataset.csrf }
                });
                if (!(response.headers.get('content-type') || '').includes('application/json')) throw new Error('Muat ulang halaman dan masuk kembali untuk menyimpan template.');
                const data = await response.json();
                if (!response.ok || !data.success) throw new Error(data.message || 'Template belum berhasil disimpan.');
                if (form.elements.id.value === '0') { window.location.reload(); return; }
                notice.dataset.state = 'success';
                notice.textContent = 'Template tersimpan. Muat ulang tab CRM yang sudah terbuka untuk memakai perubahan.';
            } catch (error) {
                notice.dataset.state = 'error';
                notice.textContent = error.message;
            } finally {
                notice.hidden = false;
                button.disabled = false;
                button.textContent = buttonLabel;
                button.removeAttribute('aria-busy');
            }
        });
    });
})();
