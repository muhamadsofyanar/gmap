# Patch Starsender Format Webhook v1.2.2

## Penyebab dari log akun nyata

Webhook mengirim device_id string 36 karakter, sedangkan environment menggunakan ID numerik halaman dashboard. ID tersebut berbeda. Payload juga tidak menyertakan timestamp. Receiver awal menolak identitas dan akan menolak waktu yang hilang setelah identitas diperbaiki.

## Pemasangan

1. Ekstrak ZIP ini.
2. Di GitHub, ganti tiga file berikut pada lokasi yang sama:
   - public/starsender-webhook.php
   - app/Services/StarsenderWebhook.php
   - app/Views/inbox/index.php
3. Folder tests dalam patch berisi tes pengembang, dapat ikut diperbarui. Tidak diperlukan untuk operasional server.
4. Commit dan Redeploy resource Coolify yang sama. Tidak ada perubahan tabel database, akun, password, volume, atau token webhook.
5. Di aplikasi buka Pengaturan gateway. Pastikan provider Starsender dipilih, API key device tersimpan, dan gateway aktif. Jika key sudah benar jangan mengganti kunci enkripsi environment.
6. Kirim satu pesan baru dari nomor lain ke device tersebut. Buka Kotak masuk dan Muat ulang.

URL webhook Premium dan token tetap sama. Pesan yang sebelumnya ditolak tidak otomatis dipulihkan; lakukan tes dengan pesan baru.

## Cara verifikasi perangkat

- Tetap menerima kecocokan persis STARSENDER_DEVICE_ID dengan identitas event.
- Bila event berupa UUID dan tidak cocok dengan ID numerik, receiver membandingkan secara persis dengan API key gateway Starsender aktif yang sudah disimpan admin. Ini bukan asumsi bahwa semua UUID adalah API key: jika nilainya berbeda maka verifikasi tetap gagal.
- UUID disimpan sebagai hash sha256 di kolom device_id, agar data yang mungkin merupakan key tidak muncul mentah dalam database atau UI.
- Jika UUID webhook berbeda dari key gateway, log penolakan kini menyertakan device_fingerprint berbentuk sha256:diikuti64karakterhex. Salin nilai utuh device_fingerprint itu ke STARSENDER_DEVICE_ID di Coolify, Save dan Redeploy. Gunakan fingerprint dari callback tes device yang sedang dihubungkan; jangan menggunakan nilai dari perangkat lain. Tidak perlu mengirim key mentah ke chat.
- Perangkat tak dikenal tetap ditolak. Token callback tetap wajib dan diperiksa sebelum pemrosesan.
- Jika database atau dekripsi key bermasalah, server merespons503; jangan menyamakannya dengan identitas salah.

## Waktu pesan

Jika timestamp tidak ada atau null, waktu yang dicatat adalah waktu penerimaan server dalam UTC dan ditampilkan sebagai Asia/Jakarta. Ini bukan klaim waktu kirim asli. Halaman inbox memberi penjelasan tersebut. Timestamp yang disertakan tetap divalidasi, tidak diabaikan bila tidak valid.

## Verifikasi

PHP8.3.6 lokal:44 pemeriksaan repository,9 tes integrasiHTTP,8 tes batas callback,58 pemeriksaan tim,7 pemeriksaan instalasi lulus.99 filePHP lolos sintaks. TesHTTP mereproduksi identitas36karakter tanpa timestamp, kecocokan key gateway, penyimpanan hash, penolakanUUID asing, dan503 saat lookup gagal. Tidak menghubungi akun Starsender nyata. Setelah deploy, pastikan POST callback berubah200 dan pesan terlihat.

Jika masih422, kirim satu baris Starsender webhook rejected terbaru. Jangan mengirim API key, token, atau URLcallback lengkap.
