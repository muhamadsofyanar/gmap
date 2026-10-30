<?php $profile=$sales['profile']; ?>
<?php if ($salesSection==='profile'): ?>
<details class="crm-panel prospect-contact" id="salesProfile">
    <summary><span>Kontak PIC &amp; label</span><small>Kelola kontak dan label prospek</small></summary><div class="prospect-contact-body">
    <form class="crm-edit-form prospect-form-grid" method="post" action="<?= e(base_url('sales/save')) ?>">
        <?= csrf_field() ?><input type="hidden" name="action" value="profile"><input type="hidden" name="lead_id" value="<?= (int)$lead['id'] ?>">
        <label>Nama PIC<input name="pic_name" maxlength="100" value="<?= e($profile['pic_name']) ?>"></label>
        <label>Jabatan / peran<input name="pic_role" maxlength="100" placeholder="Pemilik / Purchasing" value="<?= e($profile['pic_role']) ?>"></label>
        <label>Nomor kontak PIC<input name="pic_phone" type="tel" maxlength="30" value="<?= e($profile['pic_phone']) ?>"></label>
        <label>Email PIC<input name="pic_email" type="email" maxlength="254" value="<?= e($profile['pic_email']) ?>"></label>
        <label class="prospect-field-wide">Label (pisahkan dengan koma)<input name="tags" maxlength="600" placeholder="Prioritas, Butuh katalog, Calon reseller" value="<?= e(implode(', ',json_decode($profile['tags'],true)?:[])) ?>"></label>
        <p class="crm-footnote prospect-field-wide">Maksimal 10 label. Isi kontak yang Anda peroleh dari percakapan; nomor sumber Google Maps tetap tersimpan.</p>
        <div class="crm-form-actions prospect-field-wide"><button class="primary-action" type="submit">Simpan PIC &amp; label</button></div>
    </form>
</div></details>
<?php endif; ?>
<?php if ($salesSection==='tasks'): ?>
<section class="crm-panel crm-detail-card" id="salesTasks">
    <h2>Jadwal follow-up</h2>
    <?php if ($sales['tasks']): ?>
    <ul class="followup-tasks">
        <?php foreach ($sales['tasks'] as $task):
            $due = strtotime($task['due_at']);
            $state = $due < time() ? 'overdue' : (date('Y-m-d', $due) === date('Y-m-d') ? 'today' : 'upcoming');
            $dueLabel = ['Minggu','Senin','Selasa','Rabu','Kamis','Jumat','Sabtu'][(int)date('w', $due)] . ', ' . date('j', $due) . ' ' . ['','Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agu','Sep','Okt','Nov','Des'][(int)date('n', $due)] . ' ' . date('Y · H:i', $due) . ' WIB';
        ?>
            <li class="followup-task">
                <div class="followup-task-main">
                    <span class="activity-badge" data-state="<?= $state ?>"><?= ['overdue'=>'Terlambat','today'=>'Hari ini','upcoming'=>'Terjadwal'][$state] ?></span>
                    <strong><?= e($task['title']) ?></strong>
                    <small><?= e($dueLabel) ?></small>
                </div>
                <form method="post" action="<?= e(base_url('sales/save')) ?>"><?= csrf_field() ?><input type="hidden" name="action" value="complete"><input type="hidden" name="lead_id" value="<?= (int)$lead['id'] ?>"><input type="hidden" name="task_id" value="<?= (int)$task['id'] ?>"><button type="submit" class="crm-secondary">Tandai selesai</button></form>
            </li>
        <?php endforeach; ?>
    </ul>
    <?php else: ?><p class="followup-empty">Belum ada jadwal yang perlu dikerjakan.</p><?php endif; ?>
    <form class="crm-edit-form followup-new" method="post" action="<?= e(base_url('sales/save')) ?>">
        <h3>Tambah jadwal baru</h3>
        <?= csrf_field() ?><input type="hidden" name="action" value="task"><input type="hidden" name="lead_id" value="<?= (int)$lead['id'] ?>"><input type="hidden" name="request_token" value="<?= bin2hex(random_bytes(16)) ?>">
        <div class="followup-new-fields">
            <label>Tindakan berikutnya<input name="title" maxlength="200" placeholder="Kirim katalog / telepon purchasing" required></label>
            <label>Tanggal &amp; jam (WIB)<input type="datetime-local" name="due_at" min="2000-01-01T00:00" max="2100-12-31T23:59" required></label>
        </div>
        <div class="crm-form-actions"><button class="primary-action" type="submit">Jadwalkan follow-up</button></div>
    </form>
    <p class="crm-footnote">Pengingat tampil di dashboard dan menu Aktivitas. Tidak mengirim pesan otomatis.</p>
</section>
<?php endif; ?>
<?php if ($salesSection==='orders'): ?>
<section class="crm-panel crm-detail-card" id="salesOrders">
    <h2>Pembelian &amp; repeat order</h2>
    <?php foreach ($sales['orders'] as $order): ?><article class="sales-item"><strong><?= e($order['description']) ?> &middot; Rp <?= number_format((float)$order['amount'],0,',','.') ?></strong><small><?= e($order['ordered_at']) ?><?= $order['repeat_at'] ? ' | Repeat: '.e($order['repeat_at']).' WIB' : '' ?></small></article><?php endforeach; ?>
    <?php if (!$sales['orders']): ?><p class="crm-footnote prospect-field-wide">Belum ada pembelian tercatat.</p><?php endif; ?>
    <form class="crm-edit-form prospect-form-grid" method="post" action="<?= e(base_url('sales/save')) ?>">
        <?= csrf_field() ?><input type="hidden" name="action" value="order"><input type="hidden" name="lead_id" value="<?= (int)$lead['id'] ?>"><input type="hidden" name="request_token" value="<?= bin2hex(random_bytes(16)) ?>">
        <label class="prospect-field-wide">Produk / jasa yang dibeli<input name="description" maxlength="200" required></label>
        <label>Tanggal pembelian<input type="date" name="ordered_at" value="<?= date('Y-m-d') ?>" required></label>
        <label>Nilai pembelian (Rp)<input type="number" name="amount" min="0.01" max="9999999999999.99" step="0.01" required></label>
        <label class="prospect-field-wide">Ingatkan penawaran ulang (WIB, opsional)<input type="datetime-local" name="repeat_at"></label>
        <p class="crm-footnote prospect-field-wide">Pembelian tercatat menjadikan prospek pelanggan dengan status <?= e($stages['closing']['label']) ?>. Nilai pembelian dicatat terpisah dari estimasi nilai deal.</p>
        <div class="crm-form-actions prospect-field-wide"><button class="primary-action" type="submit">Catat pembelian</button></div>
    </form>
</section>
<?php endif; ?>
<?php if ($salesSection==='duplicates' && $sales['duplicates']): ?>
<details class="crm-panel prospect-contact"><summary><span>Bisnis terkait</span><small><?= count($sales['duplicates']) ?> kecocokan di proyek lain</small></summary><div class="prospect-contact-body"><p class="crm-footnote">Maksimal 10 kecocokan, dengan ID Google Maps yang sama ditampilkan lebih dahulu. Nomor yang sama dapat dipakai beberapa cabang; konfirmasi sebelum menghubungi.</p>
    <?php foreach ($sales['duplicates'] as $related): ?>
    <article class="sales-item"><a class="crm-text-button" href="<?= e(base_url((int)$related['in_crm'] ? 'crm/detail?id='.(int)$related['id'] : 'projects/viewProject/'.(int)$related['project_id'].'#leadRow-'.(int)$related['id'])) ?>"><?= e($related['shop_name']) ?> &middot; <?= e($related['project_name']) ?></a><small><?= e($related['reason']) ?></small><p>Status: <?= e(\App\Models\CrmModel::STAGES[$related['lead_status']]['label']??$related['lead_status']) ?> | Aktivitas terakhir: <?= e($related['last_activity']??'Belum tercatat') ?></p></article>
    <?php endforeach; ?>
</div></details>
<?php endif; ?>