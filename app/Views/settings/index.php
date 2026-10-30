<section class="crm-workspace">
    <header class="page-heading"><div><p class="eyebrow">KONFIGURASI APLIKASI</p><h1>Pengaturan</h1><p>Kelola koneksi layanan untuk aktivitas CRM.</p></div></header>
    <?php $settingsTab='gateway'; require __DIR__.'/tabs.php'; ?>
    <section class="crm-panel crm-detail-card settings-card">
        <h2>WhatsApp Gateway</h2>
        <p class="crm-footnote">Hubungkan akun xSender atau Starsender untuk mengirim pesan langsung dari detail prospek CRM. Pastikan perangkat WhatsApp pengirim terhubung di dashboard penyedia.</p>
        <form id="gatewaySettings" class="crm-edit-form" method="post" action="<?= e(base_url('settings/saveGateway')) ?>" data-url="<?= e(base_url('settings/saveGateway')) ?>" data-csrf="<?= e(csrf_token()) ?>" data-saved="<?= $gateway['has_key'] || $gateway['sender'] !== '' ? '1' : '0' ?>">
            <?= csrf_field() ?>
            <p id="gatewaySaveState" class="gateway-save-state" data-state="saved" role="status" <?= !$gateway['has_key'] && $gateway['sender'] === '' ? 'hidden' : '' ?>>Pengaturan tersimpan</p>
            <label>Penyedia gateway<select name="provider" id="gatewayProvider"><?php foreach (\App\Models\GatewayModel::PROVIDERS as $value => $label): ?><option value="<?= e($value) ?>" <?= $gateway['provider'] === $value ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></label>
            <p id="gatewayProviderHint" class="crm-footnote"><?= $gateway['provider'] === 'starsender' ? 'Starsender memakai API key device (menu Device di app.starsender.online).' : 'xSender memerlukan API key akun dan nomor WhatsApp pengirim yang terhubung.' ?></p>
            <div class="gateway-key-field">
                <label for="gatewayApiKey">API key</label>
                <div class="gateway-key-input">
                    <input id="gatewayApiKey" type="password" name="api_key" value="<?= e($gateway['api_key'] ?? '') ?>" maxlength="2048" autocomplete="new-password" spellcheck="false" placeholder="Masukkan API key <?= e(\App\Models\GatewayModel::label($gateway['provider'])) ?>">
                    <button id="gatewayKeyToggle" type="button" aria-label="Tampilkan API key" title="Tampilkan API key" aria-controls="gatewayApiKey" aria-pressed="false"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/><path id="gatewayKeySlash" d="m3 3 18 18" hidden/></svg></button>
                </div>
            </div>
            <p id="gatewayKeyStatus" class="crm-footnote"><?= $gateway['has_key'] ? 'API key sudah tersimpan. Klik ikon mata untuk melihat atau menyembunyikannya.' : 'API key belum diisi.' ?></p>
            <label id="gatewaySenderField" <?= $gateway['provider'] === 'starsender' ? 'hidden' : '' ?>>Nomor WhatsApp pengirim<input type="tel" name="sender" value="<?= e($gateway['sender']) ?>" maxlength="30" placeholder="628123456789 atau 08123456789" autocomplete="tel"></label>
            <label>Status gateway<select name="enabled"><option value="0" <?= !$gateway['enabled'] ? 'selected' : '' ?>>Nonaktif</option><option value="1" <?= $gateway['enabled'] ? 'selected' : '' ?>>Aktif</option></select></label>
            <p id="gatewaySettingsNotice" class="crm-notice" role="status" hidden></p>
            <div class="crm-form-actions"><button type="submit" class="primary-action">Simpan pengaturan</button></div>
        </form>
    </section>
    <section class="crm-panel crm-detail-card settings-card">
        <h2>Tes kirim WhatsApp</h2>
        <p class="crm-footnote">Gunakan nomor WhatsApp yang dapat Anda periksa. Tes memakai API key dan nomor pengirim yang sudah disimpan. Simpan perubahan pengaturan dan aktifkan gateway sebelum mengirim tes.</p>
        <form id="gatewayTest" class="crm-edit-form" data-url="<?= e(base_url('settings/testGateway')) ?>">
            <input type="hidden" name="request_token" value="<?= bin2hex(random_bytes(16)) ?>">
            <label>Nomor WhatsApp tujuan tes<input type="tel" name="number" maxlength="30" placeholder="628123456789 atau 08123456789" required></label>
            <label>Pesan tes<textarea name="message" rows="4" maxlength="1000" required>Halo, ini pesan tes WhatsApp Gateway dari Gmap Prospek.</textarea></label>
            <p id="gatewayTestNotice" class="crm-notice" role="status" hidden></p>
            <div class="crm-form-actions"><button type="submit" class="primary-action">Kirim pesan tes</button></div>
        </form>
    </section>
</section>
<script src="<?= base_url('assets/js/settings.js?v=key-7') ?>" defer></script>
