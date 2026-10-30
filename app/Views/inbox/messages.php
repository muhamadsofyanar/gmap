<?php
$compact=$compact ?? false;
$deliveryLabels=['pending'=>'Menunggu hasil','sending'=>'Sedang dikirim','accepted'=>'Diterima gateway','failed'=>'Ditolak gateway','unknown'=>'Hasil belum pasti','skipped'=>'Dilewati'];
$localTime=static fn(string $time): string=>(new DateTimeImmutable($time,new DateTimeZone('UTC')))->setTimezone(new DateTimeZone('Asia/Jakarta'))->format('d/m/Y H:i');
?>
<div class="wa-messages <?= $compact?'wa-messages-compact':'' ?>" data-message-scroll>
<?php if(!$thread['events']): ?><div class="wa-empty"><strong>Belum ada percakapan</strong><p>Pesan masuk dan pengiriman dari CRM untuk nomor prospek ini akan muncul di sini.</p></div><?php endif ?>
<?php foreach($thread['events'] as $event): $incoming=$event['direction']==='incoming'; ?>
<article class="wa-bubble <?= $incoming?'wa-bubble-in':'wa-bubble-out' ?>">
<div class="wa-bubble-label"><strong><?= $incoming?'Masuk':($event['source']==='crm'?'Keluar · CRM':'Keluar · Starsender') ?></strong><time><?= e($localTime($event['occurred_at'])) ?></time></div>
<div class="wa-message-text"><?= nl2br(e($event['message'])) ?></div>
<?php if($event['file_url']): ?><p class="wa-muted">Memiliki lampiran · buka melalui Starsender.</p><?php endif ?>
<?php if($event['source']==='crm'): ?><span class="wa-delivery"><?= e($deliveryLabels[$event['delivery_status']] ?? 'Hasil belum pasti') ?> · <?= e($event['contact_phone']) ?></span><?php endif ?>
<?php if($incoming): ?><div class="wa-review"><?php if($event['reviewed_at']): ?><span>Sudah ditinjau</span><?php elseif(!$compact): ?><form method="post" action="<?= e(base_url('inbox/review')) ?>"><?= csrf_field() ?><input type="hidden" name="message_id" value="<?= (int)$event['id'] ?>"><button type="submit">Tandai ditinjau</button></form><?php else: ?><span>Perlu ditinjau</span><?php endif ?></div><?php endif ?>
</article>
<?php endforeach ?>
</div>
