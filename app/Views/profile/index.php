<section class="crm-workspace profile-workspace">
    <header class="page-heading"><div><p class="eyebrow">AKUN SAYA</p><h1>Profil</h1><p>Kelola identitas akun dan keamanan password Anda.</p></div></header>
    <?php if(\App\Services\PlatformContext::enabled()): ?>
    <p class="crm-footnote">Alamat email terdaftar dipertahankan untuk keamanan akun. Untuk akun lama, <a href="<?= e(base_url('account/resend')) ?>">verifikasi tujuan pemulihan</a> selagi Anda masih masuk, agar fitur lupa password dapat digunakan.</p>
    <?php endif; ?>
    <div class="profile-grid">
        <section class="crm-panel crm-detail-card">
            <h2>Informasi profil</h2>
            <p class="crm-footnote">Nama Anda ditampilkan pada avatar dan profil pengguna.</p>
            <form class="crm-edit-form" method="post" action="<?= e(base_url('profile/save')) ?>">
                <?= csrf_field() ?>
                <label>Nama lengkap<input name="name" value="<?= e($draft['name']) ?>" maxlength="100" autocomplete="name" required></label>
                <label>Email<input type="email" name="email" value="<?= e($draft['email']) ?>" maxlength="100" autocomplete="email" required></label>
                <label>Username<input value="<?= e($account['username']) ?>" readonly aria-describedby="usernameHint"></label>
                <p class="crm-footnote" id="usernameHint">Username digunakan untuk masuk dan tidak dapat diubah.</p>
                <label>Password saat ini<input type="password" name="current_password" autocomplete="current-password" aria-describedby="emailPasswordHint"></label>
                <p class="crm-footnote" id="emailPasswordHint">Isi hanya jika Anda mengganti alamat email.</p>
                <div class="crm-form-actions"><button class="primary-action" type="submit">Simpan profil</button></div>
            </form>
        </section>
        <section class="crm-panel crm-detail-card" id="ubah-password">
            <h2>Ubah password</h2>
            <p class="crm-footnote">Gunakan password yang unik dan berbeda dari password saat ini.</p>
            <form class="crm-edit-form" method="post" action="<?= e(base_url('profile/password')) ?>">
                <?= csrf_field() ?>
                <label>Password saat ini<input type="password" name="current_password" autocomplete="current-password" required></label>
                <label>Password baru<input type="password" name="password" minlength="8" maxlength="72" autocomplete="new-password" aria-describedby="newPasswordHint" required></label>
                <p class="crm-footnote" id="newPasswordHint">Gunakan minimal 8 karakter dengan kombinasi huruf, angka, atau simbol.</p>
                <label>Konfirmasi password baru<input type="password" name="password_confirmation" minlength="8" maxlength="72" autocomplete="new-password" required></label>
                <div class="crm-form-actions"><button class="primary-action" type="submit">Ubah password</button></div>
            </form>
        </section>
    </div>
</section>
