# Verifikasi lokal Starsender Inbox 1.2.0

Runtime pengujian: PHP 8.3.6 CLI, PDO SQLite, Python 3.12. Tidak ada koneksi ke akun Starsender atau pengiriman pesan selama pengujian.

- `php tests/inbox.php`: 39 pemeriksaan lulus, termasuk normalisasi nomor, validasi waktu dan jenis data, deduplikasi per device, pencocokan ambigu, pemetaan manual untuk balasan berikutnya, perubahan nomor, penghapusan prospek, hak akses dan pengalihan penugasan.
- `php tests/team.php`: 58 pemeriksaan regresi lulus.
- `php tests/install.php`: 7 pemeriksaan instalasi lulus.
- `python3 tests/test_webhook.py`: 7 pengujian HTTP/batas callback lulus.
- `python3 tests/test_inbox_http.py`: 6 pengujian integrasi lokal lulus; callback benar-benar menulis SQLite lalu kotak masuk menampilkan pesan, replay tidak menggandakan, anggota lain ditolak, CSRF diverifikasi dan teks HTML di-escape.
- Seluruh 99 file PHP lolos pemeriksaan sintaks.

Review independen menemukan masalah batas waktu setelah konversi milidetik dan pemetaan manual yang tidak berlanjut ke pesan baru. Kedua masalah diperbaiki dengan uji regresi gagal-lalu-lulus. Normalisasi nomor berformat dan penghapusan pesan saat prospek dihapus juga diperiksa.

Belum diverifikasi: build Docker, migrasi pada MariaDB nyata, konfigurasi Apache/Coolify, bentuk payload aktual akun Starsender, koneksi perangkat dan callback publik. Pemeriksaan visual browser belum selesai karena binary Chromium tidak tersedia; pengujian tampilan sejauh ini memeriksa respons HTML dan escaping melalui HTTP.

Uji deploy pada salinan lingkungan sebelum pembaruan produksi. Dokumentasi pemasangan: docs/STARSENDER_INBOX.md.
