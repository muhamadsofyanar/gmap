<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <meta name="theme-color" content="#056b71">
    <title><?= e('Halaman tidak ditemukan | ' . APP_NAME) ?></title>
    <style>
        body { margin: 0; font-family: system-ui, -apple-system, "Segoe UI", sans-serif; color: #183247; background: #f3f7fa; }
        .nf { min-height: 100vh; display: grid; place-items: center; padding: 32px 16px; box-sizing: border-box; }
        .nf-card { width: min(480px, 100%); padding: 40px 32px; border: 1px solid #d8e1e8; border-radius: 16px; background: #fff; box-shadow: 0 10px 30px rgba(24, 50, 71, .08); text-align: center; box-sizing: border-box; }
        .nf-code { display: inline-block; padding: 4px 12px; border-radius: 999px; background: #e6f3f2; color: #056b71; font-weight: 700; font-size: 13px; letter-spacing: .08em; }
        .nf h1 { margin: 16px 0 0; font-size: 26px; }
        .nf p { margin: 12px 0 0; color: #526779; }
        .nf a { display: inline-block; margin-top: 24px; padding: 11px 20px; border-radius: 10px; background: #056b71; color: #fff; font-weight: 600; text-decoration: none; }
    </style>
</head>
<body>
<main class="nf">
    <div class="nf-card">
        <span class="nf-code">404</span>
        <h1>Halaman tidak ditemukan</h1>
        <p>Alamat yang Anda tuju tidak tersedia atau sudah dipindahkan.</p>
        <a href="<?= e(base_url('dashboard')) ?>">Kembali ke dashboard</a>
    </div>
</main>
</body>
</html>
