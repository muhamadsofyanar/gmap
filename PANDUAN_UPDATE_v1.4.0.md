# LegaOne Update v1.4.0 — Pesan Bebas & Pilih Penerima

Patch ini diterapkan di atas v1.3.0 CRM Inbox yang sudah digunakan. Isinya hanya file baru/berubah; jangan mengganti seluruh repository dengan isi ZIP ini.

## Cara memasang
1. Ekstrak ZIP dan salin folder app, public, tests, docs ke root repository gmap, mengikuti struktur yang sama. Timpa file terkait.
2. Commit ke branch main yang dipakai Coolify, kemudian Redeploy.
3. Setelah aplikasi aktif, refresh browser dengan Ctrl+F5 agar JavaScript/CSS versi baru dimuat.
4. Tidak perlu mengubah Compose, environment, API key, token, fingerprint, URL webhook, atau skema database. Jangan menghapus volume database.

## 1. Kirim pesan bebas
- Admin dapat membalas langsung di Kotak masuk pada percakapan yang sudah terhubung dengan prospek CRM. Gateway harus aktif dan kontak boleh dihubungi.
- Detail prospek → WhatsApp: pilih Pesan bebas, lalu tulis isi pesan. Tidak perlu membuat template terlebih dahulu.
- Template tetap tersedia sebagai isi awal; teks hasil template bisa diedit sebelum dikirim, tanpa mengubah template tersimpan.
- Pesan maksimal 4.000 karakter. Tombol Kirim menampilkan konfirmasi nama, nomor tujuan dan teks.
- Setelah diterima gateway, tulis pesan baru untuk pengiriman berikutnya. Hasil gagal/tidak pasti harus diperiksa di dashboard gateway sebelum mengulang.
- Pesan keluar muncul pada riwayat percakapan CRM/inbox. Status Diterima gateway bukan bukti sampai/dibaca.
- Fitur ini mengedit draf sebelum pengiriman; tidak mengubah pesan yang sudah dikirim.
- Anggota tim tetap memiliki izin sebelumnya; pengiriman melalui gateway tetap untuk admin.

## 2. Pilih penerima broadcast
- Buka CRM proyek → Broadcast WhatsApp.
- Cari nama atau nomor dan centang kontak yang dipilih. Tidak ada penerima yang dipilih otomatis.
- Pilih hasil pencarian mencentang kontak valid dalam hasil pencarian saat ini; pilihan sebelumnya tetap tersimpan saat kata pencarian berubah. Kosongkan pilihan menghapus semua pilihan.
- Muat ulang daftar memperbarui nomor/status kontak sebelum menyiapkan broadcast.
- Pilih Pesan bebas atau template opsional, lalu tulis/edit pesan. Variabel {{nama_bisnis}} dan {{nama_projek}} tetap tersedia.
- Penyimpanan perubahan ke template harus dicentang secara sengaja dan hanya tersedia jika memilih template.
- Klik Lanjut: periksa penerima, tinjau jumlah dan contoh, lalu mulai pengiriman.
- Maksimal 5.000 pilihan per broadcast. Pilihan dikirim sebagai satu data JSON agar tidak terpotong oleh max_input_vars PHP.
- Kontak Jangan dihubungi/nomor tidak valid tidak ikut dikirim. Nomor duplikat hanya menerima satu pesan. Server memvalidasi ulang pilihan/proyek dan status kontak sebelum mengirim.
- Penerima dan draf dikunci saat broadcast disiapkan. Untuk mengubahnya, tutup dan mulai broadcast baru. Antrean lama tetap memakai snapshot pesannya.
- Broadcast lama sebelum v1.4.0 tetap bisa dibaca/dilanjutkan. Pilihan baru tidak dapat mengubah penerima broadcast lama.

## 3. Tampilan
Inbox mendapat header dengan gradasi lembut, latar percakapan berpola, bubble dengan kontras lebih jelas, ukuran teks yang disesuaikan, composer, panel konteks dan tombol dengan respons hover/fokus. Dialog broadcast mendapat daftar kontak dan pencarian. Animasi masuk bubble/dialog dan transisi tombol menghormati pengaturan reduced motion perangkat.

## Verifikasi
154 pemeriksaan PHP (tim, instalasi, inbox, percakapan, messaging) + 29 tes HTTP/normalisasi webhook lulus. Pemeriksaan sintaks 95 file PHP dan 3 JavaScript lulus. Pengiriman pengujian memakai respons gateway lokal; tidak mengirim WhatsApp sungguhan. Dua temuan review diperbaiki: pemotongan daftar pilihan besar dan pengikatan token broadcast terhadap pilihan/draf aslinya.

Pengujian database memakai SQLite; penguncian MariaDB dan visual browser desktop/mobile belum diuji langsung. Runtime browser gagal diunduh di lingkungan pengembangan. Setelah redeploy, periksa composer, pemilihan penerima, dan satu pengiriman percobaan sebelum broadcast operasional.

Webhook pesan keluar tanpa penerima terverifikasi tetap berada di antrean admin terpisah, sesuai v1.3.0. Receiver Starsender tidak diubah.
