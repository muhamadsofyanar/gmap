# Kotak Masuk Starsender Premium — versi 1.2.0

## Yang ditambahkan

- Menu Kotak masuk untuk admin dan anggota; filter semua pesan, perlu ditinjau, dan antrean admin untuk pesan belum terhubung.
- Pesan personal masuk dicocokkan ke nomor prospek yang sudah ada. Format 08 dan +62 dinormalisasi.
- Jika beberapa prospek memiliki nomor sama, admin memilih prospek yang benar. Pilihan ini digunakan untuk pesan berikutnya selama nomor prospek masih cocok.
- Anggota hanya melihat pesan prospek yang saat ini ditugaskan kepadanya. Pengalihan tugas mencabut akses anggota lama.
- Riwayat webhook dapat dibuka dari detail prospek. Tombol Detail & tindak lanjut mengarah ke tugas dan catatan yang sudah tersedia.
- Penanda sudah ditinjau berlaku bersama untuk tim, terpisah dari status dibaca pada WhatsApp.
- Identitas device dan message_id mencegah penyimpanan ulang pesan yang sama.
- Penghapusan prospek menghapus pesan dan pemetaan percakapan yang terhubung dengannya.

## Batas tahap pertama

Satu device Starsender per instalasi, sesuai gateway sumber awal. Paket akun dapat memuat banyak device, tetapi versi aplikasi ini belum mengelola semuanya. Pesan grup diabaikan. Tidak ada balasan otomatis atau pengiriman baru oleh webhook; pengiriman CRM lama tetap digunakan.

Dokumentasi webhook Premium menyediakan `from` dan `is_me`, tetapi tidak menjelaskan field nomor tujuan untuk pesan keluar. Pesan keluar disimpan dalam antrean admin dengan keterangan penerima belum tersedia, tidak ditebak sebagai kontak `from`. Karena itu riwayat webhook prospek pada tahap ini terutama menampilkan pesan masuk. Riwayat pengiriman lama tetap tersedia pada detail CRM admin; belum digabung dengan pesan webhook atau diimpor ulang.

Media hanya dicatat sebagai metadata HTTPS. Tidak diunduh atau ditampilkan otomatis. Kutipan pesan belum ditampilkan. Tidak ada klaim delivered/read. Sinkronisasi mulai saat callback diaktifkan; pesan sebelum itu tidak diambil. Muat ulang halaman untuk melihat pesan baru. Nomor tak dikenal tidak dibuat otomatis menjadi prospek: masukkan melalui alur prospek yang tersedia lalu hubungkan dari antrean admin.

## Pemasangan pada versi tim Coolify yang menjadi sumber ZIP

1. Cadangkan database dan simpan konfigurasi lama, termasuk `XSENDER_ENCRYPTION_KEY`. Gunakan salinan lingkungan uji untuk pemasangan pertama.
2. Upload isi folder paket ke repository yang digunakan Coolify. Pertahankan environment dan volume database yang sama. Jangan menghapus volume atau membuat database baru untuk pembaruan instalasi tim ini.
3. Buat token webhook terpisah melalui terminal: `openssl rand -hex 32`. Hasilnya 64 karakter heksadesimal huruf kecil; jangan memakai API key Starsender sebagai token ini.
4. Di environment Coolify tambahkan `STARSENDER_WEBHOOK_TOKEN` dengan token tadi dan `STARSENDER_DEVICE_ID` dengan ID device yang tepat dari Starsender. Kedua nilai diteruskan oleh `compose.yaml`. Jangan mengubah API key pengiriman yang sudah bekerja.
5. Redeploy. Installer yang sudah ada menjalankan skema idempotent dan menambahkan tabel `whatsapp_inbox` serta `whatsapp_inbox_contacts`. Ia tidak mereset akun atau menghapus tabel lama. Alternatif pembaruan manual: jalankan hanya `database-inbox.sql` pada database aplikasi melalui alat database. Jangan mengganti database aktif dengan file SQL kosong.
6. Pada pengaturan webhook Premium device Starsender masukkan URL:

   `https://DOMAIN-APLIKASI/starsender-webhook.php?token=TOKEN-WEBHOOK`

   Ganti domain dan token dengan nilai milik instalasi Anda. Receiver mendukung juga header `X-Webhook-Token` bila penyedia/proxy memungkinkan pengaturannya; dukungan pengaturan header oleh Starsender belum diverifikasi.
7. Pastikan device terhubung. Kirim satu pesan personal dari nomor Anda yang lain ke device tersebut. Buka menu **Kotak masuk**, lalu tekan **Muat ulang**.
8. Untuk uji pencocokan, gunakan nomor yang terdaftar pada satu prospek. Uji login admin dan anggota yang ditugaskan. Anggota lain tidak boleh melihat pesan tersebut.

Jangan kirim API key, token callback, atau URL callback lengkap ke chat. Token di URL adalah rahasia akses bersama, bukan verifikasi tanda tangan Starsender. Apache paket ini menyamarkan query dan referrer callback pada log akses, tetapi proxy Coolify atau layanan depan lain juga perlu dikonfigurasi agar tidak menyimpan query callback. Gunakan HTTPS. Jika token bocor, ganti environment token lalu perbarui URL Starsender bersamaan.

Instalasi Laragon lama yang bukan versi tim sumber ZIP membutuhkan migrasi terpisah. Panduan ini tidak menyatakan kompatibilitas dengan skema lama tersebut.

## Diagnostik respons callback

| HTTP | Arti |
| --- | --- |
| 200, duplicate=false | Pesan tersimpan |
| 200, duplicate=true | Pesan pernah tersimpan, tidak digandakan |
| 200, ignored=true | Pesan grup diabaikan |
| 403 | Token tidak cocok |
| 405 | Permintaan bukan POST |
| 413 | Isi melebihi 256 KiB |
| 422 | JSON, ID device, jenis data, atau field pesan tidak sesuai |
| 503 | Environment belum lengkap atau database/tabel belum tersedia |

Receiver mengharapkan boolean JSON `is_me`/`is_group`, `chat_type=personal`, message_id string, dan timestamp Unix detik/milidetik yang wajar. Bila payload akun nyata berbeda, gunakan contoh yang sudah disamarkan untuk menyesuaikan adapter; jangan menonaktifkan validasi. Dokumentasi tidak menjanjikan kebijakan retry tertentu, jadi respons 503 tidak berarti penyedia pasti mencoba ulang.

## Pengujian pengembang

PHP 8.3 dengan PDO SQLite, mbstring, fileinfo, ctype dan Python 3:

```sh
php tests/inbox.php
php tests/team.php
php tests/install.php
python3 tests/test_webhook.py
python3 tests/test_inbox_http.py
```

Tes HTTP menggunakan server lokal dan SQLite sementara; tidak menghubungi Starsender atau mengirim pesan. Router dan seeder tes berada di `tests/`, diblokir oleh konfigurasi web dan dikecualikan dari image Docker.

Pengujian yang dilakukan bukan pengganti uji deploy MariaDB/Apache/Coolify dan callback akun Starsender nyata.

## Referensi kontrak penyedia

- https://docs.starsender.online/docs/webhook/webhook_premium/
- https://docs.starsender.online/docs/message-api/kirim-pesan/
- https://docs.starsender.online/docs/message-api/detail-pesan/
