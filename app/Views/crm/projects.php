<section id="crmProjectsWorkspace" class="crm-workspace" data-api="<?= e(base_url('crm')) ?>" data-csrf="<?= e(csrf_token()) ?>">
    <header class="page-heading">
        <div><p class="eyebrow">CUSTOMER RELATIONSHIPS</p><h1>CRM</h1><p>Pilih proyek, lalu lanjutkan percakapan dan kelola prospek di dalamnya.</p></div>
        <a class="primary-action" href="<?= base_url('projects') ?>">+ Cari prospek baru</a>
    </header>
    <a class="crm-text-button" href="<?= e(base_url('reports')) ?>">Laporan penjualan &rarr;</a>
    <div class="crm-workflow" aria-label="Alur kerja CRM"><span><b>1</b> Pilih proyek</span><span><b>2</b> Hubungi prospek</span><span><b>3</b> Perbarui status &amp; catatan</span></div>
    <p id="crmProjectNotice" class="crm-notice" role="alert" hidden></p>
    <section class="crm-panel">
        <form id="crmProjectSearch" class="crm-filters" method="GET" action="<?= base_url('crm') ?>" role="search">
            <label>Cari proyek<input type="search" name="search" value="<?= e($searchQuery) ?>" placeholder="Nama proyek, kategori bisnis, atau lokasi"></label>
        </form>
        <div id="crmProjectResults" class="crm-project-list" aria-live="polite">
            <?php foreach ($projects as $campaign):
                $hasLeads = (int)$campaign['crm_total'] > 0;
                $target = base_url('crm?' . http_build_query(['project_id' => $campaign['id']]));
            ?>
            <article class="crm-project-card">
                <div class="crm-project-title"><span class="crm-project-icon" aria-hidden="true"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M3 7h6l2-3h4l2 3h4v13H3zM3 10h18"/></svg></span><div><h2><a href="<?= e($target) ?>"><?= e($campaign['name']) ?></a></h2><p><?= e($campaign['keyword']) ?> &middot; <?= e($campaign['location']) ?></p></div><button type="button" class="crm-remove crm-project-delete" data-remove-project="<?= (int)$campaign['id'] ?>" data-name="<?= e($campaign['name']) ?>" aria-label="Hapus <?= e($campaign['name']) ?> dari CRM" title="Hapus dari CRM"><svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path d="M3 6h18M9 6V3h6v3M5 6l1 15h12l1-15M10 10v7m4-7v7"/></svg><span>Hapus</span></button></div>
                <div class="crm-project-metrics"><div><strong><?= (int)$campaign['crm_total'] ?></strong><span>Prospek CRM</span></div><div><strong><?= (int)$campaign['crm_new'] ?></strong><span>Belum dihubungi</span></div><div><strong><?= (int)$campaign['crm_active'] ?></strong><span>Dalam proses</span></div><div><strong><?= (int)$campaign['crm_deals'] ?></strong><span>Deal</span></div></div>
                <footer><span><?= $hasLeads ? 'Terakhir diperbarui ' . e(date('d M Y', strtotime($campaign['last_activity']))) : 'Belum ada prospek dipilih untuk CRM' ?></span><a class="<?= $hasLeads ? 'primary-action' : 'crm-secondary' ?>" href="<?= e($hasLeads ? $target : base_url('projects/viewProject/' . $campaign['id'])) ?>"><?= $hasLeads ? 'Buka CRM &rarr;' : 'Pilih prospek' ?></a></footer>
            </article>
            <?php endforeach; ?>
            <?php if (!$projects): ?><div class="crm-empty"><h2><?= $searchQuery !== '' ? 'Tidak ada proyek yang sesuai' : 'Belum ada proyek di CRM' ?></h2><p><?= $searchQuery !== '' ? 'Ubah atau kosongkan kolom cari untuk melihat proyek lainnya.' : 'Buat proyek pencarian, lalu pilih prospek yang ingin Anda kelola di CRM.' ?></p><?php if ($searchQuery === ''): ?><a class="primary-action" href="<?= base_url('projects') ?>">Buka proyek</a><?php endif; ?></div><?php endif; ?>
        </div>
    </section>
</section>
<script src="<?= base_url('assets/js/crm-projects.js?v=delete-1') ?>" defer></script>
