<?php $loginBrand = APP_NAME; ?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#056b71">
    <title><?= e('Masuk - '.$loginBrand) ?></title>
    <meta name="robots" content="noindex, nofollow">
    <link rel="stylesheet" href="<?= e(base_url('assets/css/auth.css?v=panel-1')) ?>">
</head>
<body class="auth-page login-page">
    <a class="skip-link" href="#identifier">Langsung ke formulir masuk</a>
    <main class="auth-layout">
        <section class="auth-story" aria-label="Tentang <?= e($loginBrand) ?>">
            <span class="auth-brand"><?php require __DIR__.'/../layouts/brand-mark.php'; ?><span><?= e($loginBrand) ?><small>Workspace bisnis</small></span></span>
            <div class="auth-message">
                <p class="auth-eyebrow">Workspace prospek B2B</p>
                <h1>Prospek yang tepat. <span>Pertumbuhan yang nyata.</span></h1>
                <p>Satu workspace untuk menemukan bisnis potensial dan membangun hubungan hingga menjadi pelanggan.</p>
                <ol class="auth-steps">
                    <li><span>01</span><div><strong>Temukan peluang</strong><small>Cari bisnis berdasarkan kategori dan lokasi.</small></div></li>
                    <li><span>02</span><div><strong>Bangun percakapan</strong><small>Kelola penawaran dan tindak lanjut terarah.</small></div></li>
                    <li><span>03</span><div><strong>Kembangkan bisnis</strong><small>Pantau setiap tahap hingga kesepakatan.</small></div></li>
                </ol>
            </div>
            <p class="auth-story-footer">Terorganisir. Terukur. Siap bertumbuh.</p>
        </section>

        <section class="auth-panel">
            <div class="auth-mobile-brand"><span class="auth-brand"><?php require __DIR__.'/../layouts/brand-mark.php'; ?><span><?= e($loginBrand) ?></span></span></div>
            <div class="auth-card login-form-content" id="login-form">
                <h2>Selamat datang kembali</h2>
                <p class="auth-description">Masuk untuk melanjutkan aktivitas bisnis Anda.</p>
                <?php foreach (get_flashes() as $flash): ?><div role="status" class="login-flash <?= $flash['type'] === 'error' ? 'is-error' : '' ?>"><?= e($flash['message']) ?></div><?php endforeach; ?>

                <form action="<?= e(base_url('login')) ?>" method="POST" class="auth-form">
                    <?= csrf_field() ?>
                    <div class="auth-field">
                        <label for="identifier">Email atau username</label>
                        <div class="auth-input">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/></svg>
                            <input id="identifier" name="identifier" type="text" autocomplete="username" maxlength="100" required placeholder="nama@email.com" autofocus>
                        </div>
                    </div>
                    <div class="auth-field">
                        <label for="password">Kata sandi</label>
                        <div class="auth-input">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="4" y="10" width="16" height="11" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/></svg>
                            <input id="password" name="password" type="password" autocomplete="current-password" required placeholder="Masukkan kata sandi">
                            <button type="button" id="login-password-toggle" class="auth-toggle" aria-controls="password" aria-pressed="false" aria-label="Tampilkan kata sandi">
                                <svg id="eyeIcon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/></svg>
                            </button>
                        </div>
                    </div>
                    <button type="submit" class="auth-submit">Masuk ke workspace <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14m-6-6 6 6-6 6"/></svg></button>
                </form>
                <?php if (\App\Services\PlatformContext::enabled()): ?>
                <p class="auth-description"><a href="<?= e(base_url('account/forgot')) ?>">Lupa kata sandi?</a> · <a href="<?= e(base_url('account/register')) ?>">Daftar workspace</a></p>
                <p class="auth-description"><a href="<?= e(base_url('account/resend')) ?>">Kirim ulang verifikasi</a></p>
                <?php endif; ?>
            </div>
            <p class="login-copyright">&copy; <?= date('Y') ?> <?= e($loginBrand) ?>. Workspace bisnis.</p>
        </section>
    </main>
    <script nonce="<?= e(csp_nonce()) ?>">
    (() => {
        const toggle = document.getElementById('login-password-toggle');
        const pwd = document.getElementById('password');
        const icon = document.getElementById('eyeIcon');
        const shown = '<path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/>';
        const hidden = '<path d="M10.6 5.1A10 10 0 0 1 12 5c6.4 0 10 7 10 7a17 17 0 0 1-2.7 3.6M6.6 6.6C3.7 8.4 2 12 2 12s3.6 7 10 7a9.7 9.7 0 0 0 5.4-1.6M9.9 9.9a3 3 0 0 0 4.2 4.2M3 3l18 18"/>';
        toggle.addEventListener('click', () => {
            const reveal = pwd.type === 'password';
            pwd.type = reveal ? 'text' : 'password';
            icon.innerHTML = reveal ? hidden : shown;
            toggle.setAttribute('aria-pressed', String(reveal));
            toggle.setAttribute('aria-label', reveal ? 'Sembunyikan kata sandi' : 'Tampilkan kata sandi');
        });
        const form = document.querySelector('.auth-form');
        form.addEventListener('submit', () => form.querySelector('.auth-submit').classList.add('is-loading'));
    })();
    </script>
</body>
</html>
