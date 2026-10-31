<?php
$status = isset($stages[$lead['lead_status']]) ? $lead['lead_status'] : 'new';
$phone = format_whatsapp($lead['whatsapp'] ?: $lead['phone']);
$contactBlocked = $contactBlocked ?? false;
$canContact = !$contactBlocked && preg_match('/^[1-9][0-9]{7,14}$/', $phone);
$templateMap = array_column($templates, null, 'id');
$selectedTemplate = isset($templateMap[$selectedTemplate]) ? $selectedTemplate : 0;
$message = \App\Models\MessageTemplateModel::render($templateMap[$selectedTemplate]['body'] ?? '', $lead['shop_name'], $lead['project_name']);
$validUrl = static fn($url) => is_string($url) && filter_var($url, FILTER_VALIDATE_URL) && in_array(strtolower(parse_url($url, PHP_URL_SCHEME) ?? ''), ['http', 'https'], true);
$actions = ['profile_updated'=>'PIC & label diperbarui','task_scheduled'=>'Follow-up dijadwalkan','task_completed'=>'Aktivitas selesai','order_recorded'=>'Pembelian dicatat','moved_to_crm' => 'Ditambahkan ke CRM', 'status_change' => 'Status diperbarui', 'note_added' => 'Catatan', 'deal_updated' => 'Nilai deal diperbarui', 'whatsapp_opened' => 'Tautan WhatsApp dibuka', 'whatsapp_outreach' => 'Aktivitas WhatsApp (historis)'];
$actions += ['contact_preference'=>'Preferensi kontak diperbarui','attachment_added'=>'Lampiran ditambahkan','attachment_removed'=>'Lampiran dihapus'];
?>
<section id="crmWorkspace" class="crm-workspace crm-detail-page" data-api="<?= e(base_url('crm')) ?>" data-csrf="<?= e(csrf_token()) ?>">
    <a class="crm-back" href="<?= e($backUrl) ?>">&larr; Kembali ke daftar prospek</a>
    <header class="prospect-heading">
        <div><p class="eyebrow">DETAIL PROSPEK</p><h1><?= e($lead['shop_name']) ?></h1><p class="prospect-project"><?= e($lead['project_name']) ?><?php if (!empty($lead['category'])): ?> &middot; <?= e($lead['category']) ?><?php endif; ?></p></div>
        <span id="crmStatusBadge" class="prospect-status" data-stage="<?= e($status) ?>"><?= e($stages[$status]['label']) ?></span>
    </header>
    <a class="mobile-profile-link crm-text-button" href="#prospectProfile">Lihat profil bisnis &amp; kontak PIC &darr;</a>
    <p id="crmNotice" class="crm-notice" role="status" hidden></p>
    <div class="prospect-layout">
        <aside id="prospectProfile" class="prospect-sidebar" aria-label="Profil dan kontak bisnis">
            <a class="mobile-profile-link crm-text-button" href="#prospectFollowup">&uarr; Kembali ke tindak lanjut</a>
<section class="crm-panel crm-detail-card">
                <h2>Profil bisnis</h2>
                <dl class="crm-business-info">
                    <div class="crm-info-wide"><dt>Alamat lengkap</dt><dd><?= e($lead['address'] ?: 'Alamat belum tersedia') ?></dd></div>
                    <div><dt>Kategori bisnis</dt><dd><?= e($lead['category'] ?: 'Belum tersedia') ?></dd></div>
                    <div><dt>Telepon / WhatsApp</dt><dd><?= e($lead['phone'] ?: ($lead['whatsapp'] ?: 'Nomor belum tersedia')) ?></dd></div>
                    <div><dt>Rating Google Maps</dt><dd><?= (float)$lead['rating'] > 0 ? e(number_format((float)$lead['rating'], 1, ',', '.') . ' / 5') : 'Belum tersedia' ?></dd></div>
                    <div><dt>Jumlah ulasan</dt><dd><?= (int)$lead['reviews_count'] > 0 ? e(number_format((int)$lead['reviews_count'], 0, ',', '.') . ' ulasan') : 'Belum tersedia' ?></dd></div>
                    <div class="crm-info-wide"><dt>Website</dt><dd><?php if ($validUrl($lead['website'])): ?><a href="<?= e($lead['website']) ?>" target="_blank" rel="noopener noreferrer"><?= e($lead['website']) ?></a><?php else: ?>Belum terdeteksi<?php endif; ?></dd></div>
                </dl>
                <?php if ($validUrl($lead['gmap_url'])): ?><a class="crm-secondary" href="<?= e($lead['gmap_url']) ?>" target="_blank" rel="noopener noreferrer">Buka Google Maps &#8599;</a><?php endif; ?>
            </section>
            <?php if (isset($sales)): ?>
                <?php $profileTags=json_decode($sales['profile']['tags'],true)?:[]; if ($profileTags): ?>
                <div class="prospect-tags" aria-label="Label prospek"><?php foreach ($profileTags as $tag): ?><span><?= e($tag) ?></span><?php endforeach; ?></div>
                <?php endif; ?>
                <?php $salesSection='profile'; require __DIR__.'/sales-detail.php'; ?>
                <?php $salesSection='duplicates'; require __DIR__.'/sales-detail.php'; ?>
            <?php endif; ?>
        </aside>
        <div class="prospect-main">
            <nav class="prospect-tabs" aria-label="Bagian detail prospek">
                <a id="detailTabFollowup" href="#prospectFollowup" data-detail-tab>Tindak lanjut</a>
                <a id="detailTabWhatsApp" href="#prospectWhatsApp" data-detail-tab>WhatsApp</a>
                <?php if (isset($sales)): ?><a id="detailTabOrders" href="#prospectOrders" data-detail-tab>Pembelian</a><?php endif; ?>
                <a id="detailTabHistory" href="#prospectHistory" data-detail-tab>Riwayat</a>
                <a id="detailTabAttachments" href="#prospectAttachments" data-detail-tab>Lampiran</a>
            </nav>
            <div id="prospectFollowup" class="prospect-tab-panel" data-detail-panel>
<section class="crm-panel crm-detail-card">
                <h2>Status &amp; catatan</h2>
                <form id="crmLeadForm" class="crm-edit-form">
                    <input type="hidden" name="id" value="<?= (int)$lead['id'] ?>">
                    <label>Status prospek<select name="status" id="crmLeadStatus" data-stage="<?= e($status) ?>"><?php foreach ($stages as $key => $stage): ?><option value="<?= e($key) ?>" <?= $status === $key ? 'selected' : '' ?>><?= e($stage['label']) ?></option><?php endforeach; ?></select></label>
                    <label>Catatan<textarea name="notes" id="crmLeadNotes" rows="4" maxlength="10000" placeholder="Catat percakapan dan rencana tindak lanjut."><?= e($lead['notes'] ?? '') ?></textarea></label>
                    <label>Nilai deal (Rupiah, opsional)<input name="deal_value" type="number" min="0" max="9999999999999.99" step="0.01" value="<?= e($lead['deal_value'] ?? '0') ?>" required></label>
                    <div class="crm-form-actions"><button type="submit" class="primary-action" id="crmSaveButton">Simpan perubahan</button></div>
                </form>
            </section>
                <?php if (isset($sales)): $salesSection='tasks'; require __DIR__.'/sales-detail.php'; endif; ?>
                <section class="crm-panel crm-detail-card" id="contactPreference"><h2>Preferensi kontak</h2>
                    <?php if ($contactBlocked): ?><p class="contact-blocked-label">Jangan dihubungi</p><?php endif; ?>
                    <form method="post" action="<?= e(base_url('extras/contact')) ?>" class="crm-edit-form"><?= csrf_field() ?><input type="hidden" name="lead_id" value="<?= (int)$lead['id'] ?>">
                    <label>Preferensi<select name="blocked"><option value="0">Boleh dihubungi</option><option value="1" <?= $contactBlocked?'selected':'' ?>>Jangan dihubungi</option></select></label><label>Alasan (opsional)<input name="reason" maxlength="300" value="<?= e($contactPreference['reason']??'') ?>" placeholder="Contoh: pemilik meminta tidak dikontak"></label><p class="crm-footnote">Penanda berlaku juga untuk prospek dengan nomor yang sama di proyek lain. Kontak akan dilewati dari broadcast dan tombol WhatsApp dinonaktifkan.</p><button class="crm-secondary">Simpan preferensi kontak</button></form>
                </section>
            </div>
            <div id="prospectWhatsApp" class="prospect-tab-panel" data-detail-panel>
<?php if(!empty($conversationPreview)): ?><section class="crm-panel crm-detail-card"><h2>Percakapan pelanggan</h2><p class="crm-footnote">Pesan masuk dan pengiriman CRM untuk nomor prospek ini. Waktu Asia/Jakarta; waktu penerimaan digunakan bila timestamp tidak tersedia.</p><?php $thread=$conversationPreview;$compact=true;require __DIR__.'/../inbox/messages.php'; ?><a class="wa-preview-link" href="<?= e(base_url('inbox').'?lead='.(int)$lead['id']) ?>">Buka percakapan lengkap →</a></section><?php endif ?>
<section class="crm-panel crm-detail-card">
                <h2>Tulis pesan WhatsApp</h2><p class="crm-footnote">Ketik pesan langsung atau gunakan template sebagai awal. Perubahan hanya berlaku untuk pengiriman ini.</p><form class="message-composer" data-message-composer data-api="<?= e(base_url('crm/sendWhatsApp')) ?>" data-lead="<?= (int)$lead['id'] ?>" data-phone="<?= e($phone) ?>" data-name="<?= e($lead['shop_name']) ?>" data-token="<?= bin2hex(random_bytes(16)) ?>"><?= csrf_field() ?>
                <div class="crm-edit-form">
                    <label>Template pesan<select id="crmDetailTemplate" name="template_id"><option value="0">Pesan bebas — tulis sendiri</option><?php foreach ($templates as $template): ?><option value="<?= (int)$template['id'] ?>" <?= (int)$template['id'] === $selectedTemplate ? 'selected' : '' ?>><?= e($template['name']) ?></option><?php endforeach; ?></select></label>
                    <label>Isi pesan<textarea id="crmMessagePreview" name="message" rows="5" maxlength="4000" placeholder="Tulis pesan Anda..." required><?= e($message) ?></textarea></label>
                </div>
                <div class="crm-detail-actions">
                    <?php if ($gateway['enabled']): ?>
                    <button type="submit" id="crmComposerSend" class="primary-action" data-token="<?= bin2hex(random_bytes(16)) ?>" <?= !$canContact || !$gateway['enabled'] || !$gateway['has_key'] ? 'disabled' : '' ?>>Kirim melalui gateway</button>
                    <?php endif; ?>
                    <a id="crmLeadWhatsApp" class="crm-wa" <?= $canContact ? 'href="' . e('https://wa.me/' . $phone . '?text=' . rawurlencode($message)) . '"' : '' ?> <?= $gateway['enabled'] || !$canContact ? 'hidden' : '' ?> data-manual-hidden="<?= $gateway['enabled'] || $contactBlocked ? '1' : '0' ?>" target="_blank" rel="noopener noreferrer" data-whatsapp="<?= (int)$lead['id'] ?>" data-business="<?= e($lead['shop_name']) ?>" data-project="<?= e($lead['project_name']) ?>" data-phone="<?= e($canContact?$phone:'') ?>">Kirim WhatsApp manual &#8599;</a>
                </div>
                <p data-send-notice class="crm-notice" role="status" hidden></p></form>
                <?php if (!$gateway['enabled'] || !$gateway['has_key']): ?><p class="crm-footnote">Untuk mengirim langsung, lengkapi dan aktifkan <a class="crm-text-button" href="<?= base_url('settings') ?>">WhatsApp Gateway</a>.</p><?php else: ?><p class="crm-footnote">Pengirim gateway: <?= e($gateway['sender']) ?></p><?php endif; ?>
                <?php if (!$canContact): ?><p class="crm-footnote">Nomor WhatsApp belum tersedia atau tidak valid.</p><?php endif; ?>
                <p class="crm-footnote"><?= $gateway['enabled'] ? 'Pengiriman melalui gateway' : 'Membuka WhatsApp' ?> tidak otomatis mengubah status prospek. Perbarui status setelah menghubungi bisnis.</p>
            </section>
            </div>
            <?php if (isset($sales)): ?>
            <div id="prospectOrders" class="prospect-tab-panel" data-detail-panel>
                <?php $salesSection='orders'; require __DIR__.'/sales-detail.php'; ?>
            </div>
            <?php endif; ?>
            <div id="prospectAttachments" class="prospect-tab-panel" data-detail-panel>
                <?php require __DIR__.'/attachments.php'; ?>
            </div>
            <div id="prospectHistory" class="prospect-tab-panel" data-detail-panel>
<section class="crm-panel crm-detail-card crm-history">
                <h2>Riwayat aktivitas</h2>
                <ol id="crmHistory">
                    <?php foreach ($history as $activity): ?><li><strong><?= e($actions[$activity['action_type']] ?? 'Aktivitas prospek') ?></strong><small><?= e($activity['created_at']) ?></small><p><?= e($activity['notes'] ?: 'Perubahan disimpan.') ?></p></li><?php endforeach; ?>
                    <?php if (!$history): ?><li>Belum ada aktivitas tercatat.</li><?php endif; ?>
                </ol>
            </section>
<section class="crm-panel crm-detail-card">
                <h2>Riwayat pengiriman WhatsApp</h2>
                <ol id="crmGatewayHistory" class="gateway-log">
                    <?php $deliveryLabels = ['pending' => 'Menunggu hasil / perlu diperiksa', 'accepted' => 'Diterima gateway', 'failed' => 'Ditolak gateway', 'unknown' => 'Hasil belum pasti', 'skipped' => 'Dilewati karena data prospek berubah']; ?>
                    <?php foreach ($deliveries as $delivery): ?><li><strong><?= e($deliveryLabels[$delivery['status']] ?? 'Hasil belum pasti') ?></strong><small><?= e($delivery['created_at'] . ' · ' . $delivery['recipient']) ?></small><p><?= e($delivery['message']) ?></p></li><?php endforeach; ?>
                    <?php if (!$deliveries): ?><li>Belum ada pengiriman melalui gateway.</li><?php endif; ?>
                </ol>
            </section>
            </div>
        </div>
    </div>
</section>
<script id="crmTemplatesData" type="application/json"><?= json_encode($templates, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?></script>
<script src="<?= base_url('assets/js/crm-detail.js?v=1') ?>" defer></script>
<script src="<?= base_url('assets/js/crm.js?v=free-1-delete-2') ?>" defer></script>
