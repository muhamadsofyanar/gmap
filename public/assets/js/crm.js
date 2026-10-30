(() => {
    const workspace = document.getElementById('crmWorkspace');
    if (!workspace) return;

    const form = document.getElementById('crmLeadForm');
    const notice = document.getElementById('crmNotice');
    const templates = JSON.parse(document.getElementById('crmTemplatesData').textContent);
    const templateSelect = document.getElementById('crmMessageTemplate');
    const detailTemplateSelect = document.getElementById('crmDetailTemplate');
    function updateDetailLinks() {
        workspace.querySelectorAll('[data-detail]').forEach(link => {
            const url = new URL(link.href);
            url.searchParams.set('template_id', templateSelect.value);
            link.href = url.href;
        });
    }

    function updateMessage(link, templateId) {
        const template = templates.find(item => String(item.id) === templateId);
        const message = (template?.body || '').replace(/\{\{(nama_bisnis|nama_projek)\}\}/g,
            (_, key) => key === 'nama_bisnis' ? link.dataset.business : link.dataset.project);
        externalLink(link, /^[1-9][0-9]{7,14}$/.test(link.dataset.phone || '')
            ? `https://wa.me/${link.dataset.phone}?text=${encodeURIComponent(message)}` : '');
        if (link.dataset.manualHidden === '1') link.hidden = true;
        return message;
    }
    templateSelect?.addEventListener('change', () => {
        updateDetailLinks();
        workspace.querySelectorAll('[data-whatsapp]').forEach(link => updateMessage(link, templateSelect.value));
    });
    detailTemplateSelect?.addEventListener('change', () => {
        document.getElementById('crmMessagePreview').value = updateMessage(document.getElementById('crmLeadWhatsApp'), detailTemplateSelect.value);
    });
    if (templateSelect) updateDetailLinks();
    if (detailTemplateSelect) document.getElementById('crmMessagePreview').value = updateMessage(document.getElementById('crmLeadWhatsApp'), detailTemplateSelect.value);
    if (templateSelect) workspace.querySelectorAll('[data-whatsapp]').forEach(link => updateMessage(link, templateSelect.value));

    const gatewayButton = document.getElementById('crmGatewaySend');
    gatewayButton?.addEventListener('click', async () => {
        if (gatewayButton.disabled) return;
        const link = document.getElementById('crmLeadWhatsApp');
        const message = document.getElementById('crmMessagePreview').value;
        if (!window.confirm(`Kirim pesan ini ke ${link.dataset.business} (${link.dataset.phone}) melalui gateway?\n\n${message}`)) return;
        const status = document.getElementById('crmGatewayNotice');
        gatewayButton.disabled = true;
        detailTemplateSelect.disabled = true;
        gatewayButton.textContent = 'Mengirim...';
        status.hidden = false;
        status.textContent = 'Menunggu hasil dari gateway...';
        try {
            const result = await request('sendWhatsApp', {
                id: link.dataset.whatsapp, template_id: detailTemplateSelect.value,
                request_token: gatewayButton.dataset.token, message
            });
            status.textContent = result.message;
            status.textContent += result.delivery_status === 'accepted'
                ? ' Muat ulang halaman jika ingin menyiapkan pesan berikutnya.'
                : ' Setelah memeriksa hasil di dashboard gateway, muat ulang halaman jika perlu mencoba lagi.';
            gatewayButton.textContent = result.delivery_status === 'accepted' ? 'Diterima gateway' : 'Permintaan diproses';
            try {
                const data = await request(`getLead?id=${encodeURIComponent(link.dataset.whatsapp)}`);
                const list = document.getElementById('crmGatewayHistory');
                list.replaceChildren();
                const labels = { pending: 'Menunggu hasil / perlu diperiksa', accepted: 'Diterima gateway', failed: 'Ditolak gateway', unknown: 'Hasil belum pasti', skipped: 'Dilewati karena data prospek berubah' };
                (data.deliveries || []).forEach(delivery => {
                    const item = document.createElement('li');
                    const title = document.createElement('strong');
                    title.textContent = labels[delivery.status] || labels.unknown;
                    const date = document.createElement('small');
                    date.textContent = `${delivery.created_at} · ${delivery.recipient}`;
                    const body = document.createElement('p');
                    body.textContent = delivery.message;
                    item.append(title, date, body);
                    list.append(item);
                });
            } catch (_) { status.textContent += ' Muat ulang halaman untuk melihat riwayat terbaru.'; }
        } catch (error) {
            status.textContent = `${error.message} Jika permintaan sudah terkirim, periksa riwayat di dashboard gateway sebelum mengirim ulang.`;
            gatewayButton.textContent = 'Periksa hasil pengiriman';
        } finally {
            detailTemplateSelect.disabled = false;
        }
    });

    function showError(message, state = 'error') {
        const target = notice;
        target.dataset.state = state;
        target.textContent = message;
        target.hidden = false;
        target.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
    }

    async function request(action, values = null) {
        const options = { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } };
        if (values !== null) {
            options.method = 'POST';
            options.body = values instanceof FormData ? values : new URLSearchParams(values);
            options.headers['X-CSRF-Token'] = workspace.dataset.csrf;
        }
        const response = await fetch(`${workspace.dataset.api}/${action}`, options);
        if (!(response.headers.get('content-type') || '').includes('application/json')) {
            throw new Error('Sesi mungkin telah berakhir. Muat ulang halaman dan masuk kembali.');
        }
        const data = await response.json();
        if (!response.ok || !data.success) throw new Error(data.message || 'Perubahan belum berhasil disimpan. Silakan coba lagi.');
        return data;
    }

    function externalLink(element, value) {
        let url;
        try { url = new URL(value); } catch (_) { url = null; }
        const valid = url && ['https:', 'http:'].includes(url.protocol);
        element.hidden = !valid;
        if (valid) element.href = url.href;
        else element.removeAttribute('href');
    }

    function renderHistory(history) {
        const list = document.getElementById('crmHistory');
        list.replaceChildren();
        const actions = {
            contact_preference: 'Preferensi kontak diperbarui', attachment_added: 'Lampiran ditambahkan', attachment_removed: 'Lampiran dihapus',
            moved_to_crm: 'Ditambahkan ke CRM', status_change: 'Status diperbarui',
            profile_updated: 'PIC & label diperbarui', task_scheduled: 'Follow-up dijadwalkan', task_completed: 'Aktivitas selesai', order_recorded: 'Pembelian dicatat',
            note_added: 'Catatan', deal_updated: 'Nilai deal diperbarui',
            whatsapp_opened: 'Tautan WhatsApp dibuka', whatsapp_outreach: 'Aktivitas WhatsApp (historis)'
        };
        if (!history.length) {
            const item = document.createElement('li');
            item.textContent = 'Belum ada aktivitas tercatat.';
            list.append(item);
            return;
        }
        history.forEach(activity => {
            const item = document.createElement('li');
            const title = document.createElement('strong');
            title.textContent = actions[activity.action_type] || 'Aktivitas prospek';
            const date = document.createElement('small');
            date.textContent = activity.created_at || '';
            const note = document.createElement('p');
            note.textContent = activity.notes || 'Perubahan disimpan.';
            item.append(title, date, note);
            list.append(item);
        });
    }

    document.addEventListener('click', async event => {
        const waLink = event.target.closest('[data-whatsapp]');
        if (waLink) {
            // Let the browser open the link normally; this never changes the lead status.
            try { await request('logContact', { id: waLink.dataset.whatsapp }); }
            catch (_) { showError('WhatsApp dibuka, tetapi aktivitas belum tercatat. Status prospek tidak berubah.'); }
            return;
        }
        const remove = event.target.closest('[data-remove]');
        if (!remove) return;
        if (!window.confirm(`Hapus permanen "${remove.dataset.name}" beserta catatan, riwayat, dan lampirannya? Prospek juga dihapus dari proyek. Tindakan ini tidak dapat dibatalkan.`)) return;
        remove.disabled = true;
        try {
            await request('removeFromCrm', { id: remove.dataset.remove });
            window.location.reload();
        } catch (error) {
            remove.disabled = false;
            showError(error.message);
        }
    });

    const pipeline = document.getElementById('crmPipeline');
    const pipelineStage = document.getElementById('crmPipelineStage');
    const pipelineMobile = window.matchMedia('(max-width: 767px)');
    function showPipelineStage() {
        if (!pipeline || !pipelineStage) return;
        pipeline.querySelectorAll('[data-pipeline-stage]').forEach(column => {
            column.hidden = pipelineMobile.matches && column.dataset.pipelineStage !== pipelineStage.value;
        });
    }
    if (pipeline && pipelineStage) {
        const hashStage = location.hash.replace('#pipeline-', '');
        if ([...pipelineStage.options].some(option => option.value === hashStage)) pipelineStage.value = hashStage;
        pipelineStage.addEventListener('change', showPipelineStage);
        pipelineMobile.addEventListener('change', showPipelineStage);
        showPipelineStage();
        let dragged = null;
        function clearDropTargets() {
            pipeline.querySelectorAll('.is-drop-target').forEach(column => column.classList.remove('is-drop-target'));
        }
        pipeline.addEventListener('dragstart', event => {
            const handle = event.target.closest('[data-pipeline-drag]');
            if (!handle || pipelineMobile.matches || pipeline.getAttribute('aria-busy') === 'true') {
                event.preventDefault();
                return;
            }
            dragged = handle.closest('[data-pipeline-card]');
            dragged.classList.add('is-dragging');
            event.dataTransfer.effectAllowed = 'move';
            event.dataTransfer.setData('text/plain', handle.dataset.pipelineDrag);
        });
        pipeline.addEventListener('dragover', event => {
            const column = event.target.closest('[data-pipeline-stage]');
            if (!dragged || !column || column.dataset.pipelineStage === dragged.dataset.stage) return;
            event.preventDefault();
            event.dataTransfer.dropEffect = 'move';
            clearDropTargets();
            column.classList.add('is-drop-target');
        });
        pipeline.addEventListener('drop', event => {
            const column = event.target.closest('[data-pipeline-stage]');
            if (!dragged || !column || pipeline.getAttribute('aria-busy') === 'true') return;
            event.preventDefault();
            clearDropTargets();
            const select = dragged.querySelector('[data-status]');
            if (select.value !== column.dataset.pipelineStage) {
                select.value = column.dataset.pipelineStage;
                select.dispatchEvent(new Event('change', { bubbles: true }));
            }
        });
        pipeline.addEventListener('dragend', () => {
            dragged?.classList.remove('is-dragging');
            dragged = null;
            clearDropTargets();
        });
    }

    workspace.addEventListener('change', async event => {
        const select = event.target.closest('[data-status]');
        if (!select) return;
        const previous = select.dataset.original;
        select.dataset.stage = select.value;
        select.disabled = true;
        if (pipeline) {
            pipeline.setAttribute('aria-busy', 'true');
            pipeline.querySelectorAll('[data-status]').forEach(input => { input.disabled = true; });
        }
        try {
            await request('updateStatus', { id: select.dataset.status, status: select.value });
            if (pipeline && pipelineMobile.matches && pipeline.querySelector(`[data-pipeline-stage="${select.value}"]`)) {
                const next = new URL(location.href);
                next.hash = 'pipeline-' + select.value;
                history.replaceState(null, '', next);
            }
            // Requery so filtering, pagination and summary counts stay consistent.
            window.location.reload();
        } catch (error) {
            select.value = previous;
            select.dataset.stage = previous;
            select.disabled = false;
            if (pipeline) {
                pipeline.setAttribute('aria-busy', 'false');
                pipeline.querySelectorAll('[data-status]').forEach(input => { input.disabled = false; });
            }
            showError(error.message);
        }
    });

    document.getElementById('crmLeadStatus')?.addEventListener('change', event => {
        event.target.dataset.stage = event.target.value;
    });

    form?.addEventListener('submit', async event => {
        event.preventDefault();
        const button = document.getElementById('crmSaveButton');
        button.disabled = true;
        button.textContent = 'Menyimpan...';
        notice.hidden = true;
        try {
            await request('updateStatus', new FormData(form));
            showError('Perubahan prospek berhasil disimpan.', 'success');
            const badge = document.getElementById('crmStatusBadge');
            if (badge) {
                badge.textContent = form.elements.status.selectedOptions[0].textContent;
                badge.dataset.stage = form.elements.status.value;
            }
            try {
                const data = await request(`getLead?id=${encodeURIComponent(form.elements.id.value)}`);
                renderHistory(data.history || []);
            } catch (_) {
                showError('Perubahan tersimpan. Muat ulang halaman untuk memperbarui riwayat aktivitas.');
            }
        } catch (error) {
            showError(error.message);
        } finally {
            button.disabled = false;
            button.textContent = 'Simpan perubahan';
        }
    });

})();
