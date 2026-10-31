<?php
$copy=[
 'register'=>['Buat workspace Anda','Daftarkan akun pemilik. Anggota tim bergabung melalui undangan pemilik.','Daftar & kirim verifikasi'],
 'forgot'=>['Pulihkan akses akun','Tautan dikirim hanya ke email atau WhatsApp yang telah diverifikasi.','Kirim tautan pemulihan'],
 'resend'=>['Verifikasi akun Anda','Masukkan email atau username yang digunakan saat mendaftar.','Kirim ulang verifikasi'],
 'verify'=>['Konfirmasi verifikasi','Tekan tombol di bawah untuk memverifikasi tujuan pendaftaran Anda.','Verifikasi akun'],
 'reset'=>['Buat kata sandi baru','Gunakan kata sandi yang kuat. Sesi lama akan berakhir setelah perubahan.','Simpan kata sandi'],
 'accept'=>['Bergabung dengan tim','Lengkapi identitas masuk Anda untuk menerima undangan workspace.','Terima undangan'],
];
[$title,$description,$button]=$copy[$mode];
$field=static function(string $name,string $label,string $type='text',int $max=100,string $autocomplete=''){
?><div class="auth-field"><label for="<?= e($name) ?>"><?= e($label) ?></label><div class="auth-input"><input id="<?= e($name) ?>" name="<?= e($name) ?>" type="<?= e($type) ?>" maxlength="<?= $max ?>" autocomplete="<?= e($autocomplete) ?>" required></div></div><?php
};
?>
<!DOCTYPE html><html lang="id"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><meta name="referrer" content="no-referrer"><title><?= e($title.' - '.APP_NAME) ?></title><link rel="stylesheet" href="<?= e(base_url('assets/css/auth.css?v=panel-1')) ?>"></head>
<body class="auth-page login-page"><main class="auth-layout">
<section class="auth-story" aria-label="Workspace bisnis"><span class="auth-brand"><?php require __DIR__.'/../layouts/brand-mark.php'; ?><span><?= e(APP_NAME) ?><small>Workspace bisnis</small></span></span><div class="auth-message"><p class="auth-eyebrow">Akun & akses workspace</p><h1>Ruang untuk tim. <span>Akses yang terkelola.</span></h1><p>Mulai workspace bisnis Anda, undang anggota tim, dan kelola peluang bersama.</p><ol class="auth-steps"><li><span>01</span><div><strong>Daftarkan pemilik</strong><small>Gunakan identitas dan tujuan notifikasi Anda.</small></div></li><li><span>02</span><div><strong>Konfirmasikan akses</strong><small>Verifikasi melalui tautan yang dikirimkan.</small></div></li><li><span>03</span><div><strong>Mulai bersama tim</strong><small>Undang anggota dari workspace Anda.</small></div></li></ol></div><p class="auth-story-footer">Satu akun. Workspace bisnis Anda.</p></section>
<section class="auth-panel"><div class="auth-mobile-brand"><span class="auth-brand"><?php require __DIR__.'/../layouts/brand-mark.php'; ?><span><?= e(APP_NAME) ?></span></span></div><div class="auth-card login-form-content"><h2><?= e($title) ?></h2><p class="auth-description"><?= e($description) ?></p>
<?php foreach(get_flashes() as $flash): ?><div role="status" class="login-flash <?= $flash['type']==='error'?'is-error':'' ?>"><?= e($flash['message']) ?></div><?php endforeach; ?>
<?php if(!empty($error)): ?><div role="alert" class="login-flash is-error"><?= e($error) ?></div><?php endif; ?>
<?php if(!isset($valid)||$valid): ?>
<?php if($mode==='accept'): ?><p class="auth-description">Undangan untuk <strong><?= e($link['email']) ?></strong>. Email dan workspace ditentukan oleh pemilik.</p><?php endif; ?>
<form method="POST" action="<?= e(base_url('account/'.$mode)) ?>" class="auth-form"><?= csrf_field() ?>
<?php if(in_array($mode,['verify','reset','accept'],true)): ?><input type="hidden" name="token" value="<?= e($token) ?>"><?php endif; ?>
<?php if(in_array($mode,['register','accept'],true)){$field('name','Nama lengkap','text',100,'name');$field('username','Username','text',50,'username');} ?>
<?php if($mode==='register'){$field('email','Email','email',100,'email'); ?><div class="auth-field"><label for="phone">WhatsApp (wajib bila verifikasi memakai WhatsApp)</label><div class="auth-input"><input id="phone" name="phone" type="tel" maxlength="30" autocomplete="tel" placeholder="081234567890"></div></div><?php } ?>
<?php if(in_array($mode,['forgot','resend'],true))$field('identifier','Email atau username','text',100,'username'); ?>
<?php if(in_array($mode,['register','reset','accept'],true)){$field('password','Kata sandi (8–72 karakter)','password',72,'new-password');$field('password_confirmation','Konfirmasi kata sandi','password',72,'new-password');} ?>
<button class="auth-submit" type="submit"><?= e($button) ?></button></form>
<?php endif; ?>
<p class="auth-description"><a href="<?= e(base_url('login')) ?>">Kembali ke halaman masuk</a></p>
<?php if(in_array($mode,['register','verify'],true)): ?><p class="auth-description"><a href="<?= e(base_url('account/resend')) ?>">Belum menerima tautan verifikasi?</a></p><?php endif; ?>
</div><p class="login-copyright">&copy; <?= date('Y') ?> <?= e(APP_NAME) ?>. Workspace bisnis.</p></section></main></body></html>
