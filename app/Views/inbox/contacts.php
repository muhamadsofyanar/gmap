<?php
$link=static fn(array $params):string=>base_url('inbox').'?'.http_build_query(array_filter($params,static fn($v)=>$v!==null && $v!==''));
$state=['filter'=>$filter,'q'=>$search,'page'=>$page];
$prettyTime=static fn(string $time):string=>(new DateTimeImmutable($time,new DateTimeZone('UTC')))->setTimezone(new DateTimeZone('Asia/Jakarta'))->format('d/m H:i');
?>
<div class="wa-contact-list">
<?php foreach($groups as $group): $selected=$context && ($group['lead_id']!==null?(int)$group['lead_id']===$context['lead_id']:(int)$group['id']===$context['anchor']);$target=$group['lead_id']!==null?['lead'=>(int)$group['lead_id']]:['conversation'=>(int)$group['id']];$title=\App\Services\ContactLabel::name($group['shop_name'],$group['push_name'],$group['contact_phone']); ?>
<a class="wa-contact <?= $selected?'wa-contact-active':'' ?>" href="<?= e($link($state+$target)) ?>" data-conversation-link <?= $selected?'aria-current="true"':'' ?>>
<span class="wa-contact-avatar" aria-hidden="true"><?= e(mb_strtoupper(mb_substr($title,0,1))) ?></span><span class="wa-contact-copy"><span class="wa-contact-title"><?= e($title) ?></span><span class="wa-contact-project"><?= e($group['project_name'] ?: ($group['contact_phone'] ?: 'Antrean admin')) ?></span><span class="wa-contact-preview"><?= $group['direction']==='outgoing'?'Keluar: ':'' ?><?= e(mb_substr($group['message'],0,85)) ?></span></span><span class="wa-contact-meta"><time><?= e($prettyTime($group['occurred_at'])) ?></time><?php if((int)$group['unread_count']): ?><span class="wa-count"><?= (int)$group['unread_count'] ?></span><?php endif ?></span>
</a><?php endforeach ?>
<?php if(!$groups): ?><div class="wa-empty"><strong>Belum ada percakapan yang sesuai</strong><p>Kontak baru muncul saat pesan personal diterima. Coba filter lain atau hapus pencarian.</p></div><?php endif ?>
</div>
