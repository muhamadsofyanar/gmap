<section class="crm-workspace intake-workspace">
    <a class="crm-back" href="<?= e(base_url('projects')) ?>">← Daftar proyek</a>
    <header class="page-heading"><div><p class="eyebrow">PROSPEK DARI BERBAGAI SUMBER</p><h1>Tambah prospek</h1><p>Catat kontak dari referensi, pameran, media sosial, atau daftar bisnis Anda.</p></div></header>
    <?php if (isset($error)): ?><p class="crm-notice" data-state="error" role="alert"><?= e($error) ?></p><?php endif; ?>
    <div class="intake-grid is-single">
    <section class="crm-panel crm-detail-card" id="intake-manual"><h2>Tambah satu prospek</h2>
    <form class="crm-edit-form" method="post" action="<?= e(base_url('intake/manual')) ?>">
        <?= csrf_field() ?><input type="hidden" name="token" value="<?= bin2hex(random_bytes(16)) ?>">
        <label>Proyek tujuan<select name="project_id" id="intake-project"><option value="0">Buat proyek baru</option><?php foreach ($projects as $p): ?><option value="<?= (int)$p['id'] ?>" <?= $selectedProject===(int)$p['id']?'selected':'' ?>><?= e($p['name']) ?></option><?php endforeach; ?></select></label>
        <label id="intake-project-name" <?= $selectedProject>0?'hidden':'' ?>>Nama proyek baru<input name="project_name" maxlength="200" value="<?= e($old['project_name']??'') ?>"></label>
        <?php // Category and notes stay in the import model but are not asked for here; they are saved empty.
        foreach (array_diff_key(\App\Models\IntakeModel::FIELDS, ['category'=>1,'notes'=>1]) as $key=>$label): ?><label><?= e($label) ?><?= $key==='shop_name'?' *':'' ?><input name="<?= e($key) ?>" <?= $key==='shop_name'?'required':'' ?> maxlength="<?= $key==='notes'?4000:(in_array($key,['website','gmap_url'],true)?2048:($key==='category'?100:($key==='address'?500:200))) ?>" value="<?= e(is_string($old[$key]??null)?$old[$key]:'') ?>" <?= $key==='phone'?'type="tel"':(in_array($key,['website','gmap_url'],true)?'type="url"':'') ?>></label><?php endforeach; ?>
        <button class="primary-action" type="submit">Simpan prospek</button>
    </form></section>
    </div>
</section>
<script nonce="<?= e(csp_nonce()) ?>">
(() => {
    const project = document.getElementById('intake-project'), name = document.getElementById('intake-project-name');
    if (!project || !name) return;
    project.addEventListener('change', () => { name.hidden = project.value !== '0'; });
})();
</script>
