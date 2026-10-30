# Patch diagnostik webhook 422 — untuk Starsender Inbox v1.2.0

Patch ini tidak mengubah akun, database, token, atau aturan validasi penerimaan pesan. Patch mencatat alasan penolakan dan bentuk field yang diperlukan agar adapter bisa diperbaiki berdasarkan payload akun nyata.

Pemasangan:
1. Ekstrak ZIP.
2. Di repository GitHub aplikasi, buka folder public.
3. Ganti starsender-webhook.php dengan file public/starsender-webhook.php dari ZIP ini. Pastikan tidak membuat public/public atau menaruh ZIP sebagai source aplikasi.
4. Commit perubahan, lalu Redeploy resource Coolify yang sama. Tidak perlu mengubah environment, volume, atau database.
5. Kirim satu pesan baru ke device Starsender.
6. Buka Logs layanan app di Coolify. Cari baris dengan teks Starsender webhook rejected: lalu salin baris itu untuk pemeriksaan lanjutan.

Log diagnostik mencatat alasan, kecocokan device, tipe/ukuran field, serta klasifikasi format penanda dan nomor. Ia tidak mencatat nilai API key, token, isi pesan, nama kontak, ID pesan, nomor telepon, URL media, atau body mentah. Tetap tutupi informasi rahasia dari baris log lain sebelum membagikan screenshot.

Status 422 sebelumnya membuktikan callback mencapai penerima dan melewati pemeriksaan konfigurasi/token, tetapi belum menunjukkan field mana yang gagal. Patch ini alat diagnosis, belum merupakan klaim bahwa format aktual Starsender sudah berhasil diproses.

Verifikasi lokal PHP 8.3.6: 8 tes callback (termasuk log dan redaksi) lulus, 39 pemeriksaan repository lulus, 6 tes integrasi HTTP lulus, pemeriksaan sintaks file receiver lulus. Akun Starsender nyata tidak diakses.
