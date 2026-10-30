# Verifikasi paket 2026-10-30

## Lolos

- 58 pemeriksaan perilaku repository dengan PDO SQLite nyata: isolasi daftar, pencarian, detail, hitungan, ekspor, tugas, lampiran, assignment, akun, password hash, pencabutan akses dan pencabutan sesi untuk mutasi.
- 7 pemeriksaan instalasi: validasi HTTPS/secret, pembuatan admin awal, dan startup berulang yang tidak mereset akun.
- 64 pemeriksaan HTTP melalui PHP 8.3.6 dan **MariaDB 10.11.14 nyata**: login, session cookies, CSRF, halaman admin/anggota, data lintas anggota, mixed task IDs, unduhan, ekspor, reset password, akun nonaktif dan perubahan role database yang mengalahkan role di sesi.
- Skema SQL penuh dieksekusi dua kali pada database uji kosong/terisi. Tidak ada reset password pada pemanggilan kedua.
- Regresi riwayat CRM admin: insert/read riwayat dengan native prepared statements dan actor_user_id.
- Lint seluruh 91 file PHP tanpa kesalahan sintaks.
- Sintaks Python scraper dan HTTP test valid.
- Sintaks shell entrypoint dan test runner valid.
- Struktur Compose: database tidak memiliki public port, app memakai port internal 80, dependency menunggu DB sehat, kedua volume terpasang.
- Wheel Selenium 4.52.0 berhasil diunduh dari package index; metadata mendukung Python >=3.10. Image menggunakan Python Debian Bookworm 3.11.
- Pemeriksaan source independen dilakukan; tiga temuan penting diperbaiki dan diuji regresinya. Reviewer tidak menjalankan tes sendiri karena PHP tidak ada pada PATH reviewer; eksekusi tes di atas dilakukan oleh implementer dengan runtime lokal sementara.

## Belum diuji langsung

- Build Docker image: Docker daemon tidak tersedia pada lingkungan penyusunan.
- MariaDB 11.4 dalam Compose: verifikasi SQL dilakukan pada MariaDB 10.11.14, bukan image 11.4.
- Reverse proxy HTTPS, routing domain dan siklus redeploy dengan named volume di Coolify VPS pengguna.
- Startup Chromium/driver dalam image serta scraping Google Maps langsung.
- Pengiriman dan pemeriksaan WhatsApp melalui penyedia gateway.

Paket ini adalah kandidat deployment yang telah diuji pada lapisan aplikasi/database, bukan bukti bahwa resource VPS tertentu sudah berhasil dibangun. Ikuti README dan lakukan smoke test deployment pertama.

## Smoke test VPS

1. Pastikan build selesai, service db/app sehat dan domain HTTPS berfungsi.
2. Login admin, buat dua anggota, dan cari 5 prospek.
3. Bagi prospek ke dua anggota; cek daftar, pencarian, ekspor dan lampiran masing-masing.
4. Alihkan satu prospek; cek akses pemilik lama tertolak.
5. Nonaktifkan anggota uji; pastikan sesi berikutnya meminta login dan tidak bisa masuk kembali.
6. Redeploy resource yang sama; pastikan data dan akun tetap ada.
7. Buat dan uji backup sebelum dipakai rutin oleh tim.
