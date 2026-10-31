<?php
$pageLink = function (int $number) use ($selectedProject, $selectedStatus, $searchQuery, $dueFilter): string {
    return base_url('crm?' . http_build_query(['project_id' => $selectedProject, 'status' => $selectedStatus, 'search' => $searchQuery, 'due'=>$dueFilter, 'page' => $number]));
};
$hasFilters = $selectedStatus !== 'all' || $searchQuery !== '' || $dueFilter !== 'all';
$viewLink = static fn(string $mode) => base_url('crm?' . http_build_query(['project_id' => $selectedProject, 'status' => $selectedStatus, 'search' => $searchQuery, 'due'=>$dueFilter, 'view' => $mode]));
$filterLink = static fn(string $status, string $due) => base_url('crm?' . http_build_query(['project_id' => $selectedProject, 'view' => $viewMode, 'search' => $searchQuery, 'status' => $status, 'due' => $due]));
// Quick filters: one click instead of a filter form. [label, count, status, due, tone]
$chips = [['Semua', (int)($stats['total_leads'] ?? 0), 'all', 'all', '']];
if ($dueCounts['overdue']) $chips[] = ['Follow-up terlambat', $dueCounts['overdue'], 'all', 'overdue', 'red'];
$chips[] = ['Follow-up hari ini', $dueCounts['today'], 'all', 'today', 'yellow'];
foreach ($stages as $key => $stage) $chips[] = [$stage['label'], (int)($stats['stage_' . $key] ?? 0), $key, 'all', $key];
?>
<section id="crmWorkspace" class="crm-workspace" data-api="<?= e(base_url('crm')) ?>" data-csrf="<?= e(csrf_token()) ?>">
    <a class="crm-back" href="<?= base_url('crm') ?>">&larr; Semua proyek CRM</a>
    <header class="page-heading">
        <div><p class="eyebrow">CRM PROYEK</p><h1><?= e($project['name']) ?></h1><p><?= e($project['keyword']) ?> &middot; <?= e($project['location']) ?> &middot; <?= number_format($stats['total_leads'] ?? 0) ?> prospek di CRM</p></div>
        <button type="button" id="crmBroadcastOpen" class="primary-action" <?= !(int)($stats['total_leads'] ?? 0) ? 'disabled title="Tambahkan prospek ke CRM terlebih dahulu"' : '' ?>>Broadcast WhatsApp</button>
    </header>
    <p id="crmNotice" class="crm-notice" role="status" hidden></p>
    <nav class="crm-view-switch" aria-label="Tampilan CRM">
        <a href="<?= e($viewLink('list')) ?>" <?= $viewMode === 'list' ? 'aria-current="page"' : '' ?>>Daftar</a>
        <a href="<?= e($viewLink('pipeline')) ?>" <?= $viewMode === 'pipeline' ? 'aria-current="page"' : '' ?>>Pipeline</a>
    </nav>
    <dialog id="crmBroadcastDialog" class="crm-dialog broadcast-dialog" aria-labelledby="crmBroadcastTitle">
        <header class="crm-dialog-header">
            <div><p class="eyebrow">BROADCAST WHATSAPP</p><h2 id="crmBroadcastTitle">CRM <?= e($project['name']) ?></h2><p><?= e($project['keyword']) ?> &middot; <?= e($project['location']) ?> &middot; <?= number_format($stats['total_leads'] ?? 0) ?> prospek di CRM</p></div>
            <button type="button" class="broadcast-dialog-close" data-broadcast-close aria-label="Tutup">&times;</button>
        </header>
        <div class="crm-dialog-body">
            <section id="crmBroadcastCompose" class="broadcast-compose">
                <section class="broadcast-recipients" aria-labelledby="broadcastRecipientsTitle"><div class="broadcast-selection-heading"><div><h3 id="broadcastRecipientsTitle">Pilih penerima</h3><p>Centang kontak yang akan menerima pesan.</p></div><strong id="broadcastSelectionCount" aria-live="polite">0 dipilih</strong></div><fieldset class="broadcast-stage-picker"><legend>Tahap pipeline</legend><p class="crm-footnote">Centang satu atau beberapa tahap. Tanpa centang menampilkan semua tahap dalam proyek ini. Mengubah tahap mengosongkan pilihan penerima.</p><div id="broadcastStageFilters"><?php foreach ($stages as $stageCode=>$stage): ?><label><input type="checkbox" name="broadcast_stage" value="<?= e($stageCode) ?>"> <?= e($stage['label']) ?></label><?php endforeach; ?></div></fieldset><p id="broadcastEligibilitySummary" class="crm-footnote" role="status"></p><input type="search" id="broadcastRecipientSearch" placeholder="Cari nama atau nomor..." aria-label="Cari penerima"><div class="broadcast-selection-tools"><button type="button" id="broadcastSelectVisible">Pilih hasil pencarian</button><button type="button" id="broadcastClearSelection">Kosongkan pilihan</button><button type="button" id="broadcastReloadRecipients">Muat ulang daftar</button></div><div id="broadcastRecipientList" class="broadcast-recipient-list" aria-live="polite"><p>Buka dialog untuk memuat kontak.</p></div></section>
                <label for="crmMessageTemplate">Gunakan template (opsional)</label>
                <select id="crmMessageTemplate"><option value="0">Pesan bebas — tulis sendiri</option><?php foreach ($templates as $template): ?><option value="<?= (int)$template['id'] ?>"><?= e($template['name']) ?></option><?php endforeach; ?></select>
                <label for="crmBroadcastBody">Isi pesan <small>periksa sekali lagi sebelum dikirim</small></label>
                <textarea id="crmBroadcastBody" rows="8" maxlength="4000" spellcheck="true"></textarea>
                <p class="crm-footnote">{{nama_bisnis}} dan {{nama_projek}} otomatis diganti untuk setiap penerima.</p>
                <label class="broadcast-save" id="crmBroadcastSaveRow" hidden><input type="checkbox" id="crmBroadcastSave"> Simpan perubahan ini ke template</label>
                <label class="broadcast-save"><input type="checkbox" id="crmBroadcastMarkContacted" checked> Ubah status prospek <strong>Belum dihubungi</strong> menjadi <strong>Dihubungi</strong> setelah pesan diterima gateway</label>
                <p id="crmBroadcastComposeNotice" class="crm-notice" data-state="error" role="alert" hidden></p>
                <div class="crm-detail-actions">
                    <button type="button" id="crmBroadcastPrepare" class="primary-action" data-project="<?= (int)$selectedProject ?>" data-token="<?= bin2hex(random_bytes(16)) ?>">Lanjut: periksa penerima</button>
                    <button type="button" class="crm-secondary" data-broadcast-close>Batal</button>
                </div>
            </section>
            <section id="crmBroadcastPanel" class="broadcast-progress" aria-label="Progres broadcast" hidden>
                <p class="crm-footnote">Hanya kontak yang Anda pilih yang disiapkan untuk broadcast. Nomor tidak valid dilewati dan nomor duplikat hanya menerima satu pesan.</p>
                <p id="crmBroadcastSummary" role="status"></p>
                <details><summary>Contoh pesan penerima pertama</summary><p id="crmBroadcastPreview" class="template-preview"></p></details>
                <progress id="crmBroadcastProgress" value="0" max="1" aria-label="Progres broadcast"></progress>
                <p id="crmBroadcastNotice" class="crm-footnote" role="status"></p>
                <div class="crm-detail-actions">
                    <button type="button" id="crmBroadcastStart" class="primary-action">Mulai pengiriman</button>
                    <button type="button" id="crmBroadcastPause" class="crm-secondary" hidden>Jeda setelah pesan ini</button>
                    <button type="button" id="crmBroadcastCancel" class="crm-secondary">Tutup</button>
                </div>
                <p class="crm-footnote">Tetap buka halaman selama pengiriman. Jika halaman ditutup, lanjutkan kembali dari proyek ini. “Diterima gateway” belum berarti pesan sampai ke penerima.</p>
            </section>
        </div>
    </dialog>
    <section class="crm-panel" aria-label="Daftar prospek CRM">
        <div class="crm-quick-filters">
            <form class="crm-search" method="GET" action="<?= base_url('crm') ?>" role="search">
                <label class="sr-only" for="crmSearch">Cari prospek</label>
                <input id="crmSearch" type="search" name="search" value="<?= e($searchQuery) ?>" placeholder="Cari nama bisnis, nomor, atau catatan">
                <input type="hidden" name="project_id" value="<?= (int)$selectedProject ?>">
                <input type="hidden" name="view" value="<?= e($viewMode) ?>">
                <input type="hidden" name="status" value="<?= e($selectedStatus) ?>">
                <input type="hidden" name="due" value="<?= e($dueFilter) ?>">
                <button class="crm-secondary" type="submit">Cari</button>
                <?php if ($hasFilters): ?><a class="crm-text-button" href="<?= e(base_url('crm?project_id=' . $selectedProject . '&view=' . $viewMode)) ?>">Reset</a><?php endif; ?>
            </form>
            <nav class="crm-chips" aria-label="Saring prospek">
                <?php foreach ($chips as [$label, $count, $chipStatus, $chipDue, $tone]): ?>
                    <a href="<?= e($filterLink($chipStatus, $chipDue)) ?>" data-tone="<?= e($tone) ?>" <?= $selectedStatus === $chipStatus && $dueFilter === $chipDue ? 'aria-current="true"' : '' ?>><?= e($label) ?><span><?= (int)$count ?></span></a>
                <?php endforeach; ?>
            </nav>
        </div>
        <?php if ($viewMode === 'pipeline'): ?>
            <?php require __DIR__ . '/pipeline.php'; ?>
        <?php elseif (!$leads): ?>
            <div class="crm-empty"><span class="crm-empty-icon" aria-hidden="true">&#9776;</span><h2><?= $hasFilters || $page > 1 ? 'Tidak ada prospek pada hasil ini' : 'Mulai dari prospek pilihan Anda' ?></h2><p><?= $hasFilters || $page > 1 ? 'Ubah filter atau kembali ke halaman pertama untuk melihat prospek lain.' : 'Buka proyek, pilih bisnis yang ingin dihubungi, lalu tambahkan ke CRM.' ?></p><a class="primary-action" href="<?= $hasFilters || $page > 1 ? base_url('crm?project_id=' . $selectedProject) : base_url('projects/viewProject/' . $selectedProject) ?>"><?= $hasFilters || $page > 1 ? 'Lihat semua prospek' : 'Buka proyek' ?></a></div>
        <?php else: ?>
            <div class="crm-table-scroll custom-scrollbar" tabindex="0" role="region" aria-label="Tabel prospek, geser untuk melihat seluruh kolom">
                <table class="crm-table"><thead><tr><th>Prospek</th><th>Status</th><th>Follow-up</th><th>Catatan</th><th>Aksi</th></tr></thead><tbody>
                <?php foreach ($leads as $lead):
                    $id = (int)$lead['id'];
                    $detailUrl = base_url('crm/detail?' . http_build_query(['id' => $id, 'status' => $selectedStatus, 'search' => $searchQuery, 'due'=>$dueFilter, 'page' => $page]));
                    $status = isset($stages[$lead['lead_status']]) ? $lead['lead_status'] : 'new';
                    if ($lead['next_followup']) {
                        $due = strtotime($lead['next_followup']);
                        $dueTone = $due < time() ? 'overdue' : (date('Y-m-d', $due) === date('Y-m-d') ? 'today' : 'upcoming');
                        $dueDay = date('j', $due) . ' ' . ['', 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'][(int)date('n', $due)];
                        $dueText = ['overdue' => 'Terlambat · ' . $dueDay, 'today' => 'Hari ini, ' . date('H:i', $due), 'upcoming' => $dueDay . ', ' . date('H:i', $due)][$dueTone];
                    }
                ?>
                    <tr data-lead-id="<?= $id ?>">
                        <td><a class="crm-business" data-detail href="<?= e($detailUrl) ?>"><?= e($lead['shop_name']) ?></a><?php if (!empty($lead['contact_blocked'])): ?><small class="contact-blocked-label">Jangan dihubungi</small><?php endif; ?></td>
                        <td><select class="crm-status" data-stage="<?= e($status) ?>" data-status="<?= $id ?>" data-original="<?= e($status) ?>" aria-label="Status <?= e($lead['shop_name']) ?>"><?php foreach ($stages as $key => $stage): ?><option value="<?= e($key) ?>" <?= $status === $key ? 'selected' : '' ?>><?= e($stage['label']) ?></option><?php endforeach; ?></select></td>
                        <td><?php if ($lead['next_followup']): ?><a class="crm-followup" data-detail data-tone="<?= $dueTone ?>" href="<?= e($detailUrl) ?>#salesTasks" title="Follow-up: <?= e($lead['next_followup_title']) ?>"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" aria-hidden="true"><rect x="4" y="5" width="16" height="16" rx="2"/><path d="M8 3v4M16 3v4M4 10h16"/></svg><span class="sr-only">Follow-up </span><?= e($dueText) ?></a><?php else: ?><span class="crm-followup-none">&mdash;</span><?php endif; ?></td>
                        <td><a class="crm-note-preview <?= empty($lead['notes']) ? 'is-empty' : '' ?>" data-detail href="<?= e($detailUrl) ?>#crmLeadNotes" title="Edit catatan"><span><?= e($lead['notes'] ?: '+ Tambahkan catatan') ?></span></a></td>
                        <td><div class="crm-row-actions"><a class="crm-secondary" data-detail href="<?= e($detailUrl) ?>">Detail</a><button class="crm-remove" type="button" data-remove="<?= $id ?>" data-name="<?= e($lead['shop_name']) ?>" aria-label="Hapus <?= e($lead['shop_name']) ?> dari CRM" title="Hapus dari CRM"><svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path d="M3 6h18M9 6V3h6v3M5 6l1 15h12l1-15M10 10v7m4-7v7"/></svg><span>Hapus</span></button></div></td>
                    </tr>
                <?php endforeach; ?>
                </tbody></table>
            </div>
        <?php endif; ?>
        <?php if ($viewMode === 'list'): ?>
        <footer class="crm-pagination"><span><?= $leads ? 'Menampilkan ' . (($page - 1) * $pageSize + 1) . ' - ' . (($page - 1) * $pageSize + count($leads)) : '0 hasil' ?> &middot; Halaman <?= $page ?></span><nav aria-label="Halaman CRM"><?php if ($page > 1): ?><a class="crm-secondary" href="<?= e($pageLink($page - 1)) ?>">Sebelumnya</a><?php endif; ?><?php if ($hasNext): ?><a class="crm-secondary" href="<?= e($pageLink($page + 1)) ?>">Berikutnya</a><?php endif; ?></nav></footer>
        <?php endif; ?>
    </section>
    <p class="crm-footnote">Klik tombol Follow-up untuk membuka jadwal tindak lanjut prospek. WhatsApp tetap bisa dibuka dari halaman Detail; ubah status setelah Anda benar-benar menghubungi bisnis tersebut.</p>
</section>

<script id="crmTemplatesData" type="application/json"><?= json_encode($templates, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?></script>
<script src="<?= base_url('assets/js/crm.js?v=free-1.4') ?>" defer></script>
<script src="<?= base_url('assets/js/crm-broadcast.js?v=1.5.0') ?>" defer></script>
