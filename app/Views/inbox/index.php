<?php $inboxPage=true; require __DIR__.'/../team/top.php'; ?>
<section class="card">
<div class="toolbar"><div><h2>Percakapan Starsender</h2><p>Pesan personal dari webhook · Waktu Asia/Jakarta</p></div><a class="button secondary" href="<?= e(base_url('inbox').'?'.http_build_query(['filter'=>$filter,'lead'=>$leadFilter,'page'=>$page])) ?>">Muat ulang</a></div>
<p>Penanda ditinjau berlaku bersama untuk tim. Penanda ini tidak menunjukkan pesan telah dibaca di WhatsApp. Untuk mencatat tindak lanjut, buka detail prospek.</p>
<form method="get" action="<?= e(base_url('inbox')) ?>" class="toolbar">
<label>Tampilkan<select name="filter"><option value="all" <?= $filter==='all'?'selected':'' ?>>Semua pesan</option><option value="unread" <?= $filter==='unread'?'selected':'' ?>>Perlu ditinjau</option><?php if($isAdmin): ?><option value="unmatched" <?= $filter==='unmatched'?'selected':'' ?>>Belum terhubung ke prospek</option><?php endif ?></select></label>
<?php if($leadFilter): ?><input type="hidden" name="lead" value="<?= (int)$leadFilter ?>"><?php endif ?><button>Terapkan</button><a href="<?= e(base_url('inbox')) ?>">Hapus filter</a></form>
<?php if(!$messages): ?><div class="inbox-empty"><h3>Belum ada pesan yang sesuai</h3><p>Pesan baru muncul setelah webhook diaktifkan dan pesan personal diterima. Anggota hanya melihat pesan prospek yang ditugaskan kepadanya.</p></div><?php endif ?>
<?php foreach($messages as $message): $incoming=$message['direction']==='incoming'; ?>
<article class="inbox-message <?= $incoming?'inbox-incoming':'inbox-outgoing' ?>">
<div class="toolbar"><strong><?= $incoming?'Masuk':'Keluar' ?> · <?= e($message['shop_name'] ?? ($message['push_name'] ?: 'Kontak belum teridentifikasi')) ?></strong><span class="inbox-badge"><?= $incoming && !$message['reviewed_at']?'Perlu ditinjau':'Tercatat' ?></span></div>
<p class="inbox-meta"><?= e($message['contact_phone'] ?? 'Penerima belum tersedia dari webhook') ?> · <?= e((new DateTimeImmutable($message['sent_at'],new DateTimeZone('UTC')))->setTimezone(new DateTimeZone('Asia/Jakarta'))->format('d/m/Y H:i:s')) ?> · Device <?= e($message['device_id']) ?></p>
<div class="inbox-body"><?= nl2br(e($message['message'])) ?></div>
<?php if($message['file_url']): ?><p class="notice">Pesan memiliki media. Periksa media di Starsender; aplikasi ini tidak mengunduh atau membuka file secara otomatis.</p><?php endif ?>
<div class="toolbar">
<?php if($message['lead_id']): ?><a class="button secondary" href="<?= e(base_url('team/detail/'.(int)$message['lead_id'])) ?>">Detail & tindak lanjut</a><a href="<?= e(base_url('inbox').'?lead='.(int)$message['lead_id']) ?>">Riwayat prospek</a><?php endif ?>
<?php if($incoming && !$message['reviewed_at']): ?><form method="post" action="<?= e(base_url('inbox/review')) ?>"><?= csrf_field() ?><input type="hidden" name="message_id" value="<?= (int)$message['id'] ?>"><button>Tandai ditinjau</button></form><?php endif ?>
</div>
<?php if($isAdmin && !$message['lead_id'] && $message['contact_phone'] && !empty($message['candidates'])): ?><form method="post" action="<?= e(base_url('inbox/link')) ?>" class="toolbar"><?= csrf_field() ?><input type="hidden" name="message_id" value="<?= (int)$message['id'] ?>"><label>Prospek dengan nomor yang cocok<select name="lead_id" required><option value="">Pilih prospek...</option><?php foreach($message['candidates'] as $candidate): ?><option value="<?= (int)$candidate['id'] ?>"><?= e($candidate['shop_name'].' · '.$candidate['project_name']) ?></option><?php endforeach ?></select></label><button class="secondary">Hubungkan percakapan</button></form><?php elseif($isAdmin && !$message['lead_id'] && $message['contact_phone']): ?><p>Belum ada prospek dengan nomor ini. Tambahkan prospek melalui alur yang tersedia, lalu muat ulang untuk menghubungkannya.</p><?php endif ?>
</article>
<?php endforeach ?>
<div class="toolbar pagination"><?php if($page>1): ?><a href="<?= e(base_url('inbox').'?'.http_build_query(['filter'=>$filter,'lead'=>$leadFilter,'page'=>$page-1])) ?>">Sebelumnya</a><?php endif ?><span>Halaman <?= (int)$page ?></span><?php if(count($messages)===50): ?><a href="<?= e(base_url('inbox').'?'.http_build_query(['filter'=>$filter,'lead'=>$leadFilter,'page'=>$page+1])) ?>">Berikutnya</a><?php endif ?></div>
</section>
<?php require __DIR__.'/../team/bottom.php'; ?>
