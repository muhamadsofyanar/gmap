<nav class="settings-tabs" aria-label="Bagian pengaturan">
    <a href="<?= e(base_url('settings')) ?>" <?= $settingsTab === 'gateway' ? 'aria-current="page"' : '' ?>>WhatsApp Gateway</a>
    <a href="<?= e(base_url('settings/funnel')) ?>" <?= $settingsTab === 'funnel' ? 'aria-current="page"' : '' ?>>Sales Funnel</a>
</nav>
