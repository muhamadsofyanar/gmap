<section class="crm-workspace">
<header class="page-heading"><div><p class="eyebrow">IMPOR · LANGKAH 2 DARI 3</p><h1>Cocokkan kolom</h1><p><?= count($payload['rows']) ?> baris dibaca. Pilih kolom sumber untuk setiap jenis data.</p></div></header>
<section class="crm-panel crm-detail-card"><form class="crm-edit-form" method="post" action="<?= e(base_url('intake/preview')) ?>"><?= csrf_field() ?><input type="hidden" name="token" value="<?= e($batch['token']) ?>">
<?php $oldMap=json_decode($batch['mapping']??'null',true)?:[]; foreach (\App\Models\IntakeModel::FIELDS as $key=>$label):
    $selectedIndex=$oldMap[$key]??null;
    if (!$oldMap) foreach ($payload['headers'] as $i=>$header) if (mb_strtolower(trim($header))===mb_strtolower($label) || trim($header)===$key) { $selectedIndex=$i; break; }
?>
<label><?= e($label) ?><?= $key==='shop_name'?' *':'' ?><select name="mapping[<?= e($key) ?>]" <?= $key==='shop_name'?'required':'' ?>><option value="">Tidak diimpor</option><?php foreach ($payload['headers'] as $index=>$header): ?><option value="<?= $index ?>" <?= $selectedIndex===$index?'selected':'' ?>><?= e(($index+1).'. '.($header?:'Kolom tanpa judul')) ?></option><?php endforeach; ?></select><small>Contoh: <?= $selectedIndex===null?'—':e(mb_substr($payload['rows'][0][$selectedIndex]??'',0,100)) ?></small></label>
<?php endforeach; ?><button class="primary-action" type="submit">Tinjau data &amp; duplikat</button><a class="crm-text-button" href="<?= e(base_url('intake')) ?>">Mulai ulang</a>
</form></section></section>
