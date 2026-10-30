<section class="crm-workspace">
    <header class="page-heading"><div><p class="eyebrow">KONFIGURASI APLIKASI</p><h1>Pengaturan</h1><p>Sesuaikan nama tahapan penjualan untuk alur bisnis Anda.</p></div></header>
    <?php $settingsTab='funnel'; require __DIR__.'/tabs.php'; ?>
    <section class="crm-panel crm-detail-card settings-card">
        <h2>Sales Funnel</h2>
        <?php if ($projects): ?>
        <form method="get" action="<?= e(base_url('settings/funnel')) ?>" class="crm-edit-form">
            <label>Proyek<select id="funnelProject" name="project_id" data-autosubmit>
                <?php foreach ($projects as $project): ?><option value="<?= (int)$project['id'] ?>" <?= (int)$project['id']===$selectedProject ? 'selected' : '' ?>><?= e($project['name']) ?></option><?php endforeach; ?>
            </select></label>
            <div class="crm-form-actions"><button type="submit" class="crm-secondary">Tampilkan proyek</button></div>
        </form>
        <p class="crm-footnote">Nama tahapan berlaku untuk proyek yang dipilih. Urutan enam tahapan tetap: tahap kelima dihitung sebagai closing dan tahap keenam sebagai tidak lanjut.</p>
        <form id="funnelSettings" method="post" action="<?= e(base_url('sales/save')) ?>" class="crm-edit-form">
            <?= csrf_field() ?><input type="hidden" name="action" value="pipeline"><input type="hidden" name="project_id" value="<?= (int)$selectedProject ?>">
            <?php $number=0; foreach ($stages as $code=>$stage): $number++; ?><label><?= $number ?>. <?= e(\App\Models\CrmModel::STAGES[$code]['label']) ?><input name="<?= e($code) ?>" value="<?= e($stage['label']) ?>" maxlength="60" required></label><?php endforeach; ?>
            <div class="crm-form-actions"><button class="primary-action" type="submit">Simpan nama tahapan</button></div>
        </form>
        <?php else: ?>
        <p class="crm-footnote">Belum ada proyek. Buat proyek terlebih dahulu untuk menyesuaikan nama tahapan sales funnel.</p>
        <a class="primary-action" href="<?= e(base_url('projects')) ?>">Buka proyek</a>
        <?php endif; ?>
    </section>
</section>
