<?php require __DIR__.'/top.php'; ?>
<section class="stats">
<div><span><?= $isAdmin?'Prospek tim':'Prospek ditugaskan' ?></span><strong><?= (int)$summary['total'] ?></strong></div>
<div><span>Belum dihubungi</span><strong><?= (int)$summary['new_total'] ?></strong></div>
<div><span>Deal</span><strong><?= (int)$summary['closing_total'] ?></strong></div>
<div><span>Nilai deal</span><strong>Rp <?= number_format((float)$summary['deal_total'],0,',','.') ?></strong></div>
</section>
<section class="card"><div class="toolbar"><h2><?= $isAdmin?'Pembagian prospek':'Daftar prospek saya' ?></h2><a class="button secondary" href="<?= e(base_url('team/export')) ?>">Ekspor CSV</a></div>
<?php if(!$isAdmin): ?><p>Anda hanya dapat membuka prospek yang ditugaskan kepada Anda. Hubungi admin untuk penugasan baru.</p><?php endif ?>
<form method="get" action="<?= e(base_url('team')) ?>" class="toolbar"><label class="grow">Cari nama usaha, telepon, atau proyek<input name="q" value="<?= e($search) ?>" maxlength="100" placeholder="Cari prospek..."></label><button>Cari</button></form>
<form method="post" action="<?= e(base_url('team/assign')) ?>"><?= csrf_field() ?>
<?php if($isAdmin): ?><div class="toolbar assignment"><label>Penanggung jawab<select name="member_id"><option value="">Belum ditugaskan</option><?php foreach($members as $member): if($member['deleted_at']!==null) continue; ?><option value="<?= (int)$member['id'] ?>"><?= e($member['name']) ?></option><?php endforeach ?></select></label><button>Terapkan ke prospek terpilih</button></div><?php endif ?>
<div class="table-wrap"><table><thead><tr><?php if($isAdmin): ?><th>Pilih</th><?php endif ?><th>Usaha</th><th>Proyek</th><th>Telepon</th><th>Status</th><?php if($isAdmin): ?><th>Penanggung jawab</th><?php endif ?></tr></thead><tbody>
<?php foreach($leads as $lead): ?><tr><?php if($isAdmin): ?><td><input type="checkbox" name="lead_ids[]" value="<?= (int)$lead['id'] ?>" aria-label="Pilih <?= e($lead['shop_name']) ?>"></td><?php endif ?><td><a href="<?= e(base_url('team/detail/'.(int)$lead['id'])) ?>"><?= e($lead['shop_name']) ?></a></td><td><?= e($lead['project_name']) ?></td><td><?= e($lead['phone'] ?? '') ?></td><td><?= e(\App\Models\CrmModel::STAGES[$lead['lead_status']]['label'] ?? $lead['lead_status']) ?></td><?php if($isAdmin): ?><td><?= e($lead['assigned_name'] ?? 'Belum ditugaskan') ?></td><?php endif ?></tr><?php endforeach ?>
<?php if(!$leads): ?><tr><td colspan="<?= $isAdmin?6:4 ?>">Belum ada prospek yang sesuai.</td></tr><?php endif ?>
</tbody></table></div></form>
<div class="toolbar pagination"><?php if($page>1): ?><a href="<?= e(base_url('team').'?'.http_build_query(['q'=>$search,'page'=>$page-1])) ?>">Sebelumnya</a><?php endif ?><span>Halaman <?= $page ?></span><?php if(count($leads)===50): ?><a href="<?= e(base_url('team').'?'.http_build_query(['q'=>$search,'page'=>$page+1])) ?>">Berikutnya</a><?php endif ?></div>
</section>
<?php if($isAdmin): ?>
<?php if(\App\Services\PlatformContext::enabled()): ?><section class="card"><h2>Undang anggota</h2><p>Anggota membuat kata sandinya sendiri setelah menerima undangan terverifikasi.</p><a class="button" href="<?= e(base_url('account')) ?>">Buka akun &amp; undangan</a></section><?php else: ?>
<section class="card"><h2>Tambah anggota</h2><p>Akun anggota hanya dapat mengakses prospek penugasannya. Password minimal 12 karakter.</p>
<form method="post" action="<?= e(base_url('team/createMember')) ?>" class="grid-form"><?= csrf_field() ?>
<label>Nama<input name="name" required maxlength="100" autocomplete="off"></label><label>Username<input name="username" required pattern="[a-zA-Z0-9_.-]{3,50}" maxlength="50" autocomplete="off"></label>
<label>Email<input type="email" name="email" required maxlength="100" autocomplete="off"></label><label>Password awal<input type="password" name="password" minlength="12" maxlength="72" required autocomplete="new-password"></label><button>Buat akun anggota</button></form></section>
<?php endif ?>
<section class="card"><h2>Akun anggota</h2><p>Menonaktifkan akun akan mencabut sesi dan mengembalikan prospeknya ke antrean admin.</p>
<?php foreach($members as $member): ?><article class="member"><div><strong><?= e($member['name']) ?></strong><p><?= e($member['username']) ?> · <?= e($member['email']) ?> · <?= $member['deleted_at']===null?'Aktif':'Nonaktif' ?></p></div>
<?php if($member['deleted_at']===null): ?><div class="member-actions"><form method="post" action="<?= e(base_url('team/resetPassword')) ?>"><?= csrf_field() ?><input type="hidden" name="member_id" value="<?= (int)$member['id'] ?>"><label>Password baru<input type="password" name="password" minlength="12" maxlength="72" required autocomplete="new-password"></label><button class="secondary">Reset password</button></form>
<form method="post" action="<?= e(base_url('team/disableMember')) ?>"><?= csrf_field() ?><input type="hidden" name="member_id" value="<?= (int)$member['id'] ?>"><button class="danger">Nonaktifkan akun</button></form></div><?php endif ?></article><?php endforeach ?>
<?php if(!$members): ?><p>Belum ada anggota. Buat akun melalui formulir di atas.</p><?php endif ?></section>
<?php endif ?>
<?php require __DIR__.'/bottom.php'; ?>
