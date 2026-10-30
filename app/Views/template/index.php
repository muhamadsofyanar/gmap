<section id="templateWorkspace" class="crm-workspace" data-save-url="<?= e(base_url('templates/save')) ?>" data-csrf="<?= e(csrf_token()) ?>">
    <header class="page-heading"><div><p class="eyebrow">PESAN WHATSAPP</p><h1>Template</h1><p>Siapkan salam pembuka dan pesan follow up untuk prospek CRM.</p></div><a class="primary-action" href="#newTemplate">+ Tambah template</a></header>
    <p class="template-help">Gunakan <code>{{nama_bisnis}}</code> untuk nama prospek dan <code>{{nama_projek}}</code> untuk nama proyek. Pilih template di CRM sebelum membuka WhatsApp.</p>
    <div class="template-grid">
        <div class="template-library" aria-label="Template tersimpan">
        <?php foreach ($templates as $template): ?>
        <section class="crm-panel template-card">
            <details class="template-editor" name="saved-message-template"><summary><strong><?= e($template['name']) ?></strong><span>Edit template &rarr;</span></summary>
            <form class="crm-edit-form template-form">
                <input type="hidden" name="id" value="<?= (int)$template['id'] ?>">
                <label>Nama template<input name="name" value="<?= e($template['name']) ?>" maxlength="100" required></label>
                <label>Isi pesan<textarea name="body" rows="6" maxlength="4000" required><?= e($template['body']) ?></textarea></label>
                <details><summary>Pratinjau pesan</summary><p class="template-preview"></p><small>Contoh: Laundry Bekasi · Proyek Laundry</small></details>
                <p class="crm-notice" role="status" hidden></p>
                <button type="submit" class="primary-action">Simpan template</button>
            </form>
            </details>
        </section>
        <?php endforeach; ?>
        <?php if (!$templates): ?><div class="crm-panel crm-detail-card"><h2>Belum ada template tersimpan</h2><p class="crm-footnote">Pilih contoh pesan atau tulis penawaran Anda pada form tambah template.</p></div><?php endif; ?>
        </div>
        <section id="newTemplate" class="crm-panel template-card">
            <h2>Tambah template</h2>
            <div class="crm-edit-form">
            <label for="templateBusinessPreset">Contoh berdasarkan bidang penawaran</label>
            <select id="templateBusinessPreset">
                <option value="">Pilih bidang usaha Anda</option>
                <?php foreach (\App\Services\BusinessPresets::all() as $id => $preset): ?>
                <optgroup label="<?= e($preset['label']) ?>">
                    <?php foreach ($preset['messages'] as $step => $body): $stage = ['Perkenalan', 'Diskusi kebutuhan', 'Follow-up'][$step]; ?>
                    <option value="<?= e($id . '-' . $step) ?>" data-name="<?= e($preset['label'] . ' - ' . $stage) ?>" data-body="<?= e($body) ?>"><?= e($stage) ?></option>
                    <?php endforeach; ?>
                </optgroup>
                <?php endforeach; ?>
            </select>
            <button type="button" id="useBusinessTemplate" class="crm-text-button">Gunakan contoh</button>
            <p class="crm-footnote">Contoh mengisi form di bawah. Sesuaikan identitas dan penawaran, lalu simpan untuk digunakan di CRM.</p>
            </div>
            <form class="crm-edit-form template-form">
                <input type="hidden" name="id" value="0">
                <label>Nama template<input name="name" placeholder="Contoh: Follow up 4" maxlength="100" required></label>
                <label>Isi pesan<textarea name="body" rows="6" placeholder="Halo tim {{nama_bisnis}}, ..." maxlength="4000" required></textarea></label>
                <details><summary>Pratinjau pesan</summary><p class="template-preview"></p><small>Contoh: Laundry Bekasi · Proyek Laundry</small></details>
                <p class="crm-notice" role="status" hidden></p>
                <button type="submit" class="primary-action">Tambah template</button>
            </form>
        </section>
    </div>
</section>
<script src="<?= base_url('assets/js/templates.js?v=panel-3') ?>" defer></script>
