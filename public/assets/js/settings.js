(() => {
    const form = document.getElementById('gatewaySettings');
    if (!form) return;
    const configuration = () => JSON.stringify(Array.from(new FormData(form).entries()));
    let savedConfiguration = configuration();
    let saving = false;
    let hasSavedSettings = form.dataset.saved === '1';
    const saveState = document.getElementById('gatewaySaveState');
    function updateSaveState() {
        if (!saveState || saving) return;
        const unchanged = configuration() === savedConfiguration;
        saveState.hidden = unchanged && !hasSavedSettings;
        saveState.dataset.state = unchanged ? 'saved' : 'dirty';
        saveState.textContent = unchanged ? 'Pengaturan tersimpan' : 'Perubahan belum disimpan';
    }
    form.addEventListener('input', updateSaveState);
    form.addEventListener('change', updateSaveState);
    const keyToggle = document.getElementById('gatewayKeyToggle');
    function showKey(visible) {
        form.elements.api_key.type = visible ? 'text' : 'password';
        if (!keyToggle) return;
        const label = visible ? 'Sembunyikan API key' : 'Tampilkan API key';
        keyToggle.setAttribute('aria-label', label);
        keyToggle.setAttribute('title', label);
        keyToggle.setAttribute('aria-pressed', String(visible));
        document.getElementById('gatewayKeySlash')?.toggleAttribute('hidden', !visible);
    }
    keyToggle?.addEventListener('click', () => showKey(form.elements.api_key.type === 'password'));
    const providers = {
        xsender: { label: 'xSender', hint: 'xSender memerlukan API key akun dan nomor WhatsApp pengirim yang terhubung.', sender: true },
        starsender: { label: 'Starsender', hint: 'Starsender memakai API key device (menu Device di app.starsender.online).', sender: false }
    };
    function showProvider() {
        const provider = providers[form.elements.provider.value] || providers.xsender;
        document.getElementById('gatewayProviderHint').textContent = provider.hint;
        // Starsender picks the sending device from its API key, so the sender number is not used.
        document.getElementById('gatewaySenderField').hidden = !provider.sender;
        form.elements.api_key.placeholder = `Masukkan API key ${provider.label}`;
    }
    form.elements.provider.addEventListener('change', showProvider);
    // Browsers restore a selected option on reload/back without firing change; sync the texts to it.
    showProvider();
    window.addEventListener('pageshow', () => { showProvider(); updateSaveState(); });
    form.addEventListener('submit', async event => {
        event.preventDefault();
        if (saving) return;
        const button = form.querySelector('button[type=submit]');
        const notice = document.getElementById('gatewaySettingsNotice');
        const body = new FormData(form);
        const controls = Array.from(form.elements).map(control => [control, control.disabled]);
        const buttonLabel = button.textContent;
        let saved = false;
        saving = true;
        controls.forEach(([control]) => { control.disabled = true; });
        button.textContent = 'Menyimpan...';
        notice.hidden = true;
        notice.dataset.state = 'error';
        try {
            const response = await fetch(form.dataset.url, {
                method: 'POST', body,
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-Token': form.dataset.csrf }
            });
            if (!(response.headers.get('content-type') || '').includes('application/json')) throw new Error('Muat ulang halaman dan masuk kembali untuk menyimpan.');
            const data = await response.json();
            if (!response.ok || !data.success) throw new Error(data.message || 'Pengaturan belum berhasil disimpan.');
            form.elements.api_key.value = data.gateway.api_key;
            showKey(false);
            form.elements.provider.value = data.gateway.provider;
            showProvider();
            form.elements.sender.value = data.gateway.sender;
            form.elements.enabled.value = data.gateway.enabled ? '1' : '0';
            saved = true;
            hasSavedSettings = true;
            document.getElementById('gatewayKeyStatus').textContent = data.gateway.has_key ? 'API key sudah tersimpan. Klik ikon mata untuk melihat atau menyembunyikannya.' : 'API key belum diisi.';
            notice.textContent = data.message;
            notice.dataset.state = 'success';
        } catch (error) { notice.textContent = error.message; }
        finally {
            controls.forEach(([control, disabled]) => { control.disabled = disabled; });
            button.textContent = buttonLabel;
            saving = false;
            if (saved) savedConfiguration = configuration();
            updateSaveState();
            notice.hidden = false;
        }
    });
    const test = document.getElementById('gatewayTest');
    test?.addEventListener('submit', async event => {
        event.preventDefault();
        const button = test.querySelector('button[type=submit]');
        if (button.disabled) return;
        const notice = document.getElementById('gatewayTestNotice');
        notice.hidden = false;
        if (saving) {
            notice.textContent = 'Tunggu sampai pengaturan gateway selesai disimpan.';
            return;
        }
        if (configuration() !== savedConfiguration) {
            notice.textContent = 'Simpan perubahan pengaturan gateway terlebih dahulu sebelum mengirim tes.';
            return;
        }
        if (!window.confirm(`Kirim pesan tes melalui gateway ke ${test.elements.number.value}?\n\n${test.elements.message.value}`)) return;
        const body = new FormData(test);
        button.disabled = true;
        test.elements.number.disabled = true;
        test.elements.message.disabled = true;
        button.textContent = 'Mengirim tes...';
        notice.textContent = 'Menunggu respons gateway...';
        try {
            const response = await fetch(test.dataset.url, {
                method: 'POST', body,
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-Token': form.dataset.csrf }
            });
            if (!(response.headers.get('content-type') || '').includes('application/json')) throw new Error('Respons server tidak dapat dibaca. Periksa pesan tujuan sebelum mencoba lagi.');
            const data = await response.json();
            if (!response.ok || !data.success) throw new Error(data.message || 'Tes belum dapat diproses.');
            notice.textContent = data.message + (data.http_code ? ` (HTTP ${data.http_code})` : ' Tidak ada respons HTTP yang dapat dikonfirmasi.');
            test.elements.request_token.value = data.next_token;
        } catch (error) {
            notice.textContent = `${error.message} Jika koneksi terputus, periksa WhatsApp tujuan dan riwayat di dashboard gateway sebelum mencoba lagi.`;
        } finally {
            button.disabled = false;
            test.elements.number.disabled = false;
            test.elements.message.disabled = false;
            button.textContent = 'Kirim pesan tes';
        }
    });
})();
