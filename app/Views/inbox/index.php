<?php
$link=static fn(array $params):string=>base_url('inbox').'?'.http_build_query(array_filter($params,static fn($v)=>$v!==null && $v!==''));
$selection=$context?($context['lead_id']?['lead'=>$context['lead_id']]:['conversation'=>$context['anchor']]):[];
$state=['filter'=>$filter,'q'=>$search,'page'=>$page];
$prettyTime=static fn(string $time):string=>(new DateTimeImmutable($time,new DateTimeZone('UTC')))->setTimezone(new DateTimeZone('Asia/Jakarta'))->format('d/m H:i');
?>
<section class="wa-workspace">
<header class="wa-page-heading"><div><p class="eyebrow">WHATSAPP & CRM</p><h1>Kotak masuk</h1><p>Satu percakapan, satu konteks pelanggan.</p></div><a class="wa-button wa-button-secondary" href="<?= e($link($state+$selection+['history_page'=>$historyPage])) ?>">Muat ulang</a></header>
<nav class="wa-filters" aria-label="Filter percakapan">
<?php $filters=['all'=>'Percakapan','unread'=>'Perlu ditinjau'];if($isAdmin)$filters+=['unmatched'=>'Belum di CRM','unknown'=>'Penerima belum diketahui'];foreach($filters as $value=>$label): ?>
<a href="<?= e($link(['filter'=>$value])) ?>" class="<?= $filter===$value?'wa-filter-active':'' ?>" <?= $filter===$value?'aria-current="page"':'' ?>><?= e($label) ?></a>
<?php endforeach ?></nav>
<?php if($filter==='unknown'): ?><p class="wa-notice">Pesan keluar ini belum memiliki penerima yang terverifikasi. Pesan disimpan terpisah agar tidak masuk ke percakapan pelanggan yang salah.</p><?php endif ?>
<div class="wa-columns">
<aside class="wa-contact-panel" aria-label="Daftar percakapan" data-conversation-list>
<form class="wa-search" method="get" action="<?= e(base_url('inbox')) ?>"><input type="hidden" name="filter" value="<?= e($filter) ?>"><label class="wa-field">Cari kontak, nomor, atau proyek<input name="q" value="<?= e($search) ?>" maxlength="100" placeholder="Cari percakapan..."></label><button class="wa-button wa-button-secondary">Cari</button></form>
<div class="wa-contact-list">
<?php foreach($groups as $group): $selected=$context && ($group['lead_id']!==null?(int)$group['lead_id']===$context['lead_id']:(int)$group['id']===$context['anchor']);$target=$group['lead_id']!==null?['lead'=>(int)$group['lead_id']]:['conversation'=>(int)$group['id']];$title=$group['shop_name'] ?: ($group['push_name'] ?: ($group['contact_phone'] ?: 'Penerima belum diketahui')); ?>
<a class="wa-contact <?= $selected?'wa-contact-active':'' ?>" href="<?= e($link($state+$target)) ?>" data-conversation-link <?= $selected?'aria-current="true"':'' ?>>
<span class="wa-contact-avatar" aria-hidden="true"><?= e(mb_strtoupper(mb_substr($title,0,1))) ?></span><span class="wa-contact-copy"><span class="wa-contact-title"><?= e($title) ?></span><span class="wa-contact-project"><?= e($group['project_name'] ?: ($group['contact_phone'] ?: 'Antrean admin')) ?></span><span class="wa-contact-preview"><?= $group['direction']==='outgoing'?'Keluar: ':'' ?><?= e(mb_substr($group['message'],0,85)) ?></span></span><span class="wa-contact-meta"><time><?= e($prettyTime($group['occurred_at'])) ?></time><?php if((int)$group['unread_count']): ?><span class="wa-count"><?= (int)$group['unread_count'] ?></span><?php endif ?></span>
</a><?php endforeach ?>
<?php if(!$groups): ?><div class="wa-empty"><strong>Belum ada percakapan yang sesuai</strong><p>Kontak baru muncul saat pesan personal diterima. Coba filter lain atau hapus pencarian.</p></div><?php endif ?>
</div>
<div class="wa-pagination"><?php if($page>1): ?><a href="<?= e($link(['filter'=>$filter,'q'=>$search,'page'=>$page-1])) ?>">Sebelumnya</a><?php endif ?><span><?= (int)$page ?></span><?php if(count($groups)===50): ?><a href="<?= e($link(['filter'=>$filter,'q'=>$search,'page'=>$page+1])) ?>">Berikutnya</a><?php endif ?></div>
</aside>
<section class="wa-thread-panel" aria-label="Percakapan terpilih" data-conversation-thread>
<?php if($context): ?>
<header class="wa-thread-heading"><div><h2><?= e($context['name']) ?></h2><p><?= e($context['phone'] ?: 'Nomor penerima belum tersedia') ?></p></div><span class="wa-pill"><?= $context['lead_id']?((int)$context['lead']['in_crm']===1?'Terhubung ke CRM':'Prospek ditemukan'):'Belum terhubung' ?></span></header>
<div class="wa-time-note">Waktu Asia/Jakarta · Jika waktu pesan tidak dikirim Starsender, digunakan waktu penerimaan.</div>
<?php if($thread['hasOlder'] || $historyPage>1): ?><nav class="wa-history-nav" aria-label="Halaman riwayat"><?php if($thread['hasOlder']): ?><a href="<?= e($link($state+$selection+['history_page'=>$historyPage+1])) ?>">Pesan lebih lama</a><?php endif ?><?php if($historyPage>1): ?><a href="<?= e($link($state+$selection+['history_page'=>$historyPage-1])) ?>">Pesan lebih baru</a><?php endif ?></nav><?php endif ?>
<?php $compact=false;require __DIR__.'/messages.php'; ?>
<footer class="wa-thread-footer"><p>Ditinjau adalah penanda internal tim. Status gateway belum membuktikan pesan sampai atau dibaca.</p>
<?php if($context['lead_id'] && $isAdmin && (int)$context['lead']['in_crm']===1): ?>
<?php if($replyBlocked): ?><p class="wa-notice">Kontak ditandai Jangan dihubungi. Pengiriman dinonaktifkan.</p>
<?php elseif($replyGateway && $replyGateway['enabled'] && $replyGateway['has_key']): ?>
<form class="message-composer wa-composer" data-message-composer data-api="<?= e(base_url('crm/sendWhatsApp')) ?>" data-lead="<?= (int)$context['lead_id'] ?>" data-phone="<?= e($context['phone']) ?>" data-name="<?= e($context['name']) ?>" data-token="<?= bin2hex(random_bytes(16)) ?>"><?= csrf_field() ?><input type="hidden" name="template_id" value="0"><label class="wa-field">Balas pesan<textarea name="message" rows="3" maxlength="4000" placeholder="Tulis pesan untuk pelanggan ini..." required></textarea></label><div class="wa-composer-actions"><small>Pesan bebas · tanpa template</small><button class="wa-button" type="submit">Kirim pesan <span aria-hidden="true">↗</span></button></div><p data-send-notice class="wa-send-notice" role="status" hidden></p></form>
<?php else: ?><p class="wa-muted">Aktifkan gateway pada Pengaturan untuk membalas dari inbox.</p><?php endif ?>
<a class="wa-context-link" href="<?= e(base_url('crm/detail').'?id='.$context['lead_id'].'#prospectWhatsApp') ?>">Buka pengirim & template CRM →</a>
<?php elseif(!$context['lead_id'] && $context['phone']): ?><p>Hubungkan kontak ke prospek untuk menyimpan percakapan bersama aktivitas CRM.</p><?php endif ?></footer>
<?php else: ?><div class="wa-empty wa-empty-thread"><svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1" aria-hidden="true"><path d="M4 4h16v12H9l-5 4z"/><path d="M8 8h8M8 12h5"/></svg><h2>Pilih percakapan</h2><p>Buka kontak di sebelah kiri untuk melihat pesan dan kaitannya dengan CRM.</p></div><?php endif ?>
</section>
<aside class="wa-context-panel" aria-label="Konteks CRM">
<?php if($context && $context['lead_id']): $lead=$context['lead']; ?>
<p class="eyebrow">PROSPEK TERHUBUNG</p><h2><?= e($lead['shop_name']) ?></h2><dl class="wa-context-data"><div><dt>Proyek</dt><dd><?= e($lead['project_name']) ?></dd></div><div><dt>Penanggung jawab</dt><dd><?= e($lead['assigned_name'] ?: 'Belum ditugaskan') ?></dd></div><div><dt>Status</dt><dd><?= e(\App\Models\CrmModel::STAGES[$lead['lead_status']]['label'] ?? $lead['lead_status']) ?></dd></div><div><dt>Nilai deal</dt><dd>Rp <?= number_format((float)$lead['deal_value'],0,',','.') ?></dd></div></dl>
<a class="wa-button wa-button-block" href="<?= e(($isAdmin && (int)$lead['in_crm']===1?base_url('crm/detail').'?id='.(int)$lead['id']:base_url('team/detail/'.(int)$lead['id']))) ?>">Buka detail prospek</a>
<?php if($isAdmin && !(int)$lead['in_crm'] && $context['anchor']): ?><form method="post" action="<?= e(base_url('inbox/link')) ?>" class="wa-form"><?= csrf_field() ?><input type="hidden" name="message_id" value="<?= (int)$context['anchor'] ?>"><input type="hidden" name="lead_id" value="<?= (int)$lead['id'] ?>"><p class="wa-muted">Nomor cocok dengan prospek proyek ini. Masukkan ke CRM untuk mengelola tindak lanjut.</p><button class="wa-button">Masukkan ke CRM</button></form><?php endif ?>
<?php if($isAdmin): ?><a class="wa-context-link" href="<?= e(base_url('crm').'?project_id='.(int)$lead['project_id']) ?>">Lihat CRM proyek</a><?php endif ?><p class="wa-muted">Status, catatan, dan jadwal tindak lanjut dikelola melalui detail prospek.</p>
<?php elseif($context && $context['phone'] && $isAdmin): ?>
<p class="eyebrow">HUBUNGKAN KE CRM</p><h2>Kontak baru</h2><p class="wa-muted">Pesan tetap tersimpan. Tentukan prospek yang menangani kontak ini.</p>
<?php if($candidates): ?><form method="post" action="<?= e(base_url('inbox/link')) ?>" class="wa-form"><?= csrf_field() ?><input type="hidden" name="message_id" value="<?= (int)$context['anchor'] ?>"><label class="wa-field">Prospek dengan nomor yang sama<select name="lead_id" required><option value="">Pilih prospek...</option><?php foreach($candidates as $candidate): ?><option value="<?= (int)$candidate['id'] ?>"><?= e($candidate['shop_name'].' · '.$candidate['project_name']) ?></option><?php endforeach ?></select></label><button class="wa-button">Hubungkan ke prospek</button></form>
<?php else: ?><h3>Buat prospek</h3><form method="post" action="<?= e(base_url('inbox/createProspect')) ?>" class="wa-form"><?= csrf_field() ?><input type="hidden" name="message_id" value="<?= (int)$context['anchor'] ?>"><label class="wa-field">Nama bisnis / kontak<input name="name" value="<?= e($context['name']) ?>" maxlength="200" required></label><label class="wa-field">Nomor WhatsApp<input value="<?= e($context['phone']) ?>" readonly></label><label class="wa-field">Proyek tujuan<select name="project_id" required><option value="">Pilih proyek...</option><?php foreach($options['projects'] as $project): ?><option value="<?= (int)$project['id'] ?>"><?= e($project['name']) ?></option><?php endforeach ?></select></label><label class="wa-field">Penanggung jawab<select name="member_id"><option value="">Tentukan nanti</option><?php foreach($options['members'] as $member): ?><option value="<?= (int)$member['id'] ?>"><?= e($member['name']) ?></option><?php endforeach ?></select></label><button class="wa-button" <?= !$options['projects']?'disabled':'' ?>>Simpan & hubungkan</button></form><?php if(!$options['projects']): ?><p class="wa-muted">Buat proyek terlebih dahulu melalui menu Proyek.</p><?php endif ?><?php endif ?>
<?php else: ?><p class="eyebrow">KONTEKS PELANGGAN</p><h2><?= $context?'Penerima belum diketahui':'Percakapan & prospek' ?></h2><p class="wa-muted"><?= $context?'Webhook pesan keluar belum memberikan penerima yang terverifikasi. Pesan ini tidak dihubungkan otomatis ke kontak lain.':'Pilih percakapan untuk melihat proyek, penanggung jawab, status, dan tindak lanjutnya.' ?></p><?php endif ?>
</aside>
</div></section>
<script src="<?= e(base_url('assets/js/inbox.js?v=1.3.0')) ?>" defer></script>
