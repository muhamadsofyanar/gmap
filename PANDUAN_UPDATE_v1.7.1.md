# LegaOne v1.7.1 — perbaikan alamat Pengaturan Akun Platform

Pembaruan kompatibilitas untuk LegaOne v1.7.0.

## Perubahan
- Menambahkan alias route `/account/setting`.
- URL tunggal tersebut otomatis diarahkan ke URL kanonis `/account/settings`.
- Fungsi dan tampilan pengaturan yang sudah ada tidak diubah.
- Tidak ada perubahan skema database, environment variable, kredensial, atau data workspace.

## Cara memasang melalui GitHub dan Coolify
1. Cadangkan source/repository dan konfigurasi Coolify.
2. Pada repository yang digunakan Coolify, ganti file `app/Controllers/AccountController.php` dengan file dari paket pembaruan ini.
3. Commit dan push perubahan ke branch yang dipakai deployment (pada deployment saat ini: `main`).
4. Jalankan redeploy melalui Coolify.
5. Setelah deployment berhasil, buka:
   - `https://legaone.id/account/setting` (alias lama; diarahkan otomatis)
   - `https://legaone.id/account/settings` (URL kanonis)
6. Login sebagai Platform Administrator. Jika akun bukan Platform Administrator, halaman dapat menampilkan HTTP 403.

## Keamanan dan data
- Jangan menghapus volume database atau storage.
- Jangan menjalankan reset database atau `docker compose down -v`.
- Tidak perlu mengubah `PLATFORM_ACCOUNTS_ENABLED`, `PLATFORM_PROVISION_USER`, atau `PLATFORM_PROVISION_PASSWORD` untuk memasang patch ini.
- Patch ini hanya mengubah routing kompatibilitas pada `AccountController.php`.

## Validasi
Pastikan PHP lint pada file `app/Controllers/AccountController.php` lolos, kemudian uji kedua URL di atas setelah redeploy.
