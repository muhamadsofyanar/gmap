(() => {
    const prepare = document.getElementById('crmBroadcastPrepare');
    if (!prepare) return;
    const workspace = document.getElementById('crmWorkspace');
    const dialog = document.getElementById('crmBroadcastDialog');
    const openButton = document.getElementById('crmBroadcastOpen');
    const compose = document.getElementById('crmBroadcastCompose');
    const template = document.getElementById('crmMessageTemplate');
    const body = document.getElementById('crmBroadcastBody');
    const saveRow = document.getElementById('crmBroadcastSaveRow');
    const save = document.getElementById('crmBroadcastSave');
    const markContacted = document.getElementById('crmBroadcastMarkContacted');
    const composeNotice = document.getElementById('crmBroadcastComposeNotice');
    const panel = document.getElementById('crmBroadcastPanel');
    const start = document.getElementById('crmBroadcastStart');
    const pause = document.getElementById('crmBroadcastPause');
    const close = document.getElementById('crmBroadcastCancel');
    const notice = document.getElementById('crmBroadcastNotice');
    const templates = JSON.parse(document.getElementById('crmTemplatesData').textContent);
    const storageKey = `crm-broadcast:${document.documentElement.dataset.workspace || 1}:${workspace.dataset.api}:${prepare.dataset.project}`;
    let batch = null, running = false, stop = false;
    const selected=new Set();let recipients=[],loaded=false,loading=false,composeInitialized=false;
    const recipientList=document.getElementById('broadcastRecipientList');
    const recipientSearch=document.getElementById('broadcastRecipientSearch');
    const stageInputs=[...document.querySelectorAll('#broadcastStageFilters input')];
    const chosenStages=()=>stageInputs.filter(input=>input.checked).map(input=>input.value);
    function filteredRecipients(){const q=recipientSearch.value.trim().toLocaleLowerCase();const stages=chosenStages();return recipients.filter(r=>(!stages.length || stages.includes(r.stage)) && `${r.name} ${r.number}`.toLocaleLowerCase().includes(q));}
    function renderRecipients(){
        recipientList.replaceChildren();
        const stages=chosenStages();
        const scoped=recipients.filter(r=>!stages.length || stages.includes(r.stage));
        const valid=scoped.filter(r=>r.eligible);const numbers=new Set(valid.map(r=>r.number));
        document.getElementById('broadcastEligibilitySummary').textContent=`${scoped.length} kontak pada tahap terpilih · ${numbers.size} nomor unik valid · ${valid.length-numbers.size} duplikat · ${scoped.length-valid.length} tidak dapat dihubungi. ${filteredRecipients().length} cocok dengan pencarian.`;
        for(const item of filteredRecipients()){
            const label=document.createElement('label');label.className='broadcast-recipient';
            const checkbox=document.createElement('input');checkbox.type='checkbox';checkbox.value=String(item.id);checkbox.checked=selected.has(item.id);checkbox.disabled=!item.eligible;
            checkbox.addEventListener('change',()=>{checkbox.checked?selected.add(item.id):selected.delete(item.id);document.getElementById('broadcastSelectionCount').textContent=`${selected.size} dipilih`;});
            const copy=document.createElement('span');const name=document.createElement('strong');name.textContent=item.name;
            const number=document.createElement('small');number.textContent=item.number || 'Nomor belum tersedia';copy.append(name,number);
            label.append(checkbox,copy);if(item.reason){const reason=document.createElement('em');reason.textContent=item.reason;label.append(reason);}recipientList.append(label);
        }
        if(!recipientList.children.length){const empty=document.createElement('p');empty.textContent='Tidak ada kontak sesuai pencarian.';recipientList.append(empty);}
        document.getElementById('broadcastSelectionCount').textContent=`${selected.size} dipilih`;
    }
    async function loadRecipients(){
        if(loading || batch)return;loading=true;loaded=false;recipientList.setAttribute('aria-busy','true');recipientList.textContent='Memuat kontak...';
        try{
            const response=await fetch(`${workspace.dataset.api}/broadcastRecipients?project_id=${encodeURIComponent(prepare.dataset.project)}`,{headers:{Accept:'application/json'}});
            const data=await response.json();if(!response.ok || !data.success)throw new Error(data.message || 'Daftar kontak gagal dimuat.');
            recipients=data.recipients;const available=new Set(recipients.filter(r=>r.eligible).map(r=>r.id));for(const id of selected)if(!available.has(id))selected.delete(id);
            loaded=true;renderRecipients();
        }catch(error){recipientList.textContent=error.message;}finally{loading=false;recipientList.removeAttribute('aria-busy');}
    }
    stageInputs.forEach(input=>input.addEventListener('change',()=>{if(batch)return;selected.clear();renderRecipients();}));
    recipientSearch.addEventListener('input',renderRecipients);
    document.getElementById('broadcastSelectVisible').addEventListener('click',()=>{filteredRecipients().filter(r=>r.eligible).forEach(r=>selected.add(r.id));renderRecipients();});
    document.getElementById('broadcastClearSelection').addEventListener('click',()=>{selected.clear();renderRecipients();});
    document.getElementById('broadcastReloadRecipients').addEventListener('click',loadRecipients);

    function remember(token) { try { token ? localStorage.setItem(storageKey, token) : localStorage.removeItem(storageKey); } catch (_) {} }
    async function request(action, values) {
        const response = await fetch(`${workspace.dataset.api}/${action}`, {
            method: 'POST', body: new URLSearchParams(values),
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-Token': workspace.dataset.csrf }
        });
        if (!(response.headers.get('content-type') || '').includes('application/json')) throw new Error('Sesi berakhir atau server tidak merespons. Muat ulang dan masuk kembali.');
        const data = await response.json();
        if (!response.ok || !data.success) throw new Error(data.message || 'Progres belum dapat dibaca.');
        return data.broadcast;
    }

    // Compose step: review and correct the template text before any recipient is prepared.
    const selectedTemplate = () => templates.find(item => String(item.id) === template.value);
    // Offer to save only when the text differs from the stored template.
    function showSaveOption() {
        saveRow.hidden = !selectedTemplate() || body.value.trim() === (selectedTemplate()?.body || '').trim();
    }
    function loadTemplate() {
        body.value = selectedTemplate()?.body || '';
        composeNotice.hidden = true;
        showSaveOption();
    }
    template.addEventListener('change', () => {
        if (batch) return;
        if (!saveRow.hidden && !window.confirm('Ganti template? Perubahan pada isi pesan akan hilang.')) {
            template.value = template.dataset.current;
            return;
        }
        template.dataset.current = template.value;
        loadTemplate();
    });
    body.addEventListener('input', showSaveOption);
    openButton?.addEventListener('click', () => {
        if (!batch && !composeInitialized) {
            template.dataset.current = template.value;
            loadTemplate();composeInitialized=true;
        }
        dialog.showModal();
        if(!batch && !loaded)loadRecipients();
        (batch ? start : body).focus();
    });

    function showProgress() {
        stageInputs.forEach(input=>{input.disabled=true;});
        compose.hidden = true;
        panel.hidden = false;
        if (!dialog.open) dialog.showModal();
    }
    function render(value) {
        batch = value;
        showProgress();
        document.getElementById('crmBroadcastSummary').textContent = `Template: ${batch.template_name}. ${batch.eligible} penerima dari ${batch.total} prospek. ${batch.invalid} nomor tidak valid, ${batch.duplicates} duplikat. Diproses ${batch.processed}/${batch.eligible}: ${batch.accepted} diterima gateway, ${batch.failed} ditolak, ${batch.unknown} belum pasti, ${batch.skipped} dilewati karena data berubah.`;
        document.getElementById('crmBroadcastSummary').textContent += ` ${batch.blocked || 0} prospek Jangan dihubungi dikecualikan.`;
        if (batch.mark_contacted) document.getElementById('crmBroadcastSummary').textContent += ' Prospek Belum dihubungi yang pesannya diterima gateway otomatis diubah menjadi Dihubungi.';
        markContacted.checked = batch.mark_contacted;
        markContacted.disabled = true;
        document.getElementById('crmBroadcastPreview').textContent = batch.example;
        const progress = document.getElementById('crmBroadcastProgress');
        progress.max = Math.max(1, batch.eligible);
        progress.value = batch.processed;
        start.disabled = running || !batch.remaining;
        start.textContent = batch.processed ? `Lanjutkan ${batch.remaining} penerima tersisa` : `Kirim ke ${batch.eligible} penerima`;
        template.value = String(batch.template_id);
        template.dispatchEvent(new Event('change'));
        template.disabled = true;
    }
    prepare.addEventListener('click', async () => {
        if (running || prepare.disabled) return;
        if(!loaded || !selected.size){composeNotice.textContent='Pilih minimal satu penerima dari daftar yang berhasil dimuat.';composeNotice.hidden=false;return;}
        if (!body.value.trim()) {
            composeNotice.textContent = 'Isi pesan tidak boleh kosong.';
            composeNotice.hidden = false;
            return;
        }
        const values = { project_id: prepare.dataset.project, template_id: template.value, token: prepare.dataset.token, body: body.value };
        if (!saveRow.hidden && save.checked) values.save_template = '1';
        if (markContacted.checked) values.mark_contacted = '1';
        prepare.disabled = true;
        template.disabled = true;
        body.readOnly = true;
        markContacted.disabled = true;
        composeNotice.hidden = true;
        prepare.textContent = 'Menyiapkan penerima...';
        try {
            const payload=new URLSearchParams(values);payload.set('recipient_ids_json',JSON.stringify([...selected]));payload.set('selection_count',String(selected.size));payload.set('stage_filters_json',JSON.stringify(chosenStages()));
            const value = await request('prepareBroadcast', payload);
            // Keep the local copy in sync so the CRM table links use the saved text.
            if (values.save_template) selectedTemplate().body = body.value.trim();
            render(value);
            remember(batch.token);
            notice.textContent = batch.eligible ? 'Periksa jumlah penerima dan contoh pesan, lalu klik Kirim untuk memulai.' : 'Tidak ada nomor WhatsApp valid untuk dikirim.';
        } catch (error) {
            composeNotice.textContent = error.message;
            composeNotice.hidden = false;
            prepare.disabled = false;
            template.disabled = false;
            body.readOnly = false;
            markContacted.disabled = false;
            prepare.textContent = 'Lanjut: periksa penerima';
        }
    });

    start.addEventListener('click', async () => {
        if (running || !batch?.remaining) return;
        running = true; stop = false;
        start.disabled = true; close.disabled = true;
        pause.hidden = false; pause.disabled = false;
        try {
            while (!stop && batch.remaining) {
                notice.textContent = `Mengirim pesan berikutnya... ${batch.remaining} penerima tersisa.`;
                const previousIssues = batch.failed + batch.unknown;
                render(await request('broadcastStep', { token: batch.token, send: '1' }));
                if (batch.failed + batch.unknown > previousIssues) {
                    stop = true;
                    notice.textContent = 'Broadcast dijeda karena ada penolakan atau hasil belum pasti. Periksa dashboard gateway; Lanjutkan hanya mengirim penerima yang belum diproses.';
                    break;
                }
                if (!stop && batch.remaining) await new Promise(resolve => setTimeout(resolve, 1000));
            }
            if (!batch.remaining) notice.textContent = 'Broadcast selesai diproses. Periksa ringkasan hasil dan riwayat pengiriman pada detail prospek.';
            else if (!stop) notice.textContent = 'Broadcast dijeda.';
        } catch (error) {
            notice.textContent = `${error.message} Broadcast dijeda. Lanjutkan untuk memeriksa progres dan meneruskan penerima yang belum diproses.`;
        } finally {
            running = false;
            pause.hidden = true;
            close.disabled = false;
            render(batch);
        }
    });
    pause.addEventListener('click', () => {
        stop = true;
        pause.disabled = true;
        notice.textContent = 'Jeda diminta. Menunggu pesan yang sedang diproses selesai.';
    });

    // Before preparing, closing simply hides the dialog. Once prepared, closing ends this broadcast.
    function closeDialog() {
        if (running) return;
        if (!batch) { dialog.close(); return; }
        if (batch.processed && batch.remaining && !window.confirm('Tutup progres broadcast ini? Penerima yang tersisa tidak akan dikirim. Broadcast baru dapat mengirim ulang penerima sebelumnya.')) return;
        remember(null);
        // A fresh page provides a new server-generated token for a deliberate new broadcast.
        window.location.reload();
    }
    close.addEventListener('click', closeDialog);
    dialog.querySelectorAll('[data-broadcast-close]').forEach(button => button.addEventListener('click', closeDialog));
    dialog.addEventListener('cancel', event => { event.preventDefault(); closeDialog(); });
    window.addEventListener('beforeunload', event => {
        if (running) { event.preventDefault(); event.returnValue = ''; }
    });

    let saved;
    try { saved = localStorage.getItem(storageKey); } catch (_) {}
    const requested=new URL(window.location.href).searchParams.get('resume');
    if(requested && /^[a-f0-9]{32}$/.test(requested)){
        saved=requested;
        const clean=new URL(window.location.href);clean.searchParams.delete('resume');
        window.history.replaceState(null,'',clean.href);
    }
    if (saved && /^[a-f0-9]{32}$/.test(saved)) {
        request('broadcastStep', { token: saved, send: '0' }).then(value => {
            if (value.project_id !== Number(prepare.dataset.project)) throw new Error('Proyek broadcast tidak cocok.');
            render(value);
            notice.textContent = value.remaining ? 'Progres sebelumnya ditemukan. Klik Lanjutkan untuk meneruskan pengiriman.' : 'Broadcast sebelumnya selesai diproses.';
        }).catch(error => {
            showProgress(); start.disabled = true;
            batch = batch || { processed: 0, remaining: 0 };
            notice.textContent = `${error.message} Muat ulang untuk mencoba memulihkan progres. Tutup untuk menyiapkan broadcast baru.`;
        });
    }
})();
