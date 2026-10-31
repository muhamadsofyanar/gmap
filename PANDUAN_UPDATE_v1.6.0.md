# LegaOne v1.6.0 — Perbaikan Tema, Inbox dan Tindak Lanjut

Pembaruan 31 Oktober 2026, berdasarkan v1.5.0 dan screenshot aplikasi pengguna.

## Paket dan pemasangan
Gunakan **LegaOne_Update_v1.6.0.zip** untuk aplikasi yang sudah diperbarui ke v1.5.0. Ini patch perubahan sejak v1.5.0, bukan paket instalasi baru. **LegaOne_Source_Lengkap_v1.6.0.zip** adalah seluruh sumber untuk cadangan/instalasi baru.

1. Cadangkan sumber aplikasi dan database.
2. Ekstrak patch v1.6.0 lalu salin folder app, public, config, tests dan docs ke root repository, sejajar Dockerfile. Timpa file yang sama.
3. Pertahankan Compose, environment, API key, kunci enkripsi, token webhook dan volume database yang sedang digunakan.
4. Commit ke repository yang digunakan Coolify, lalu Redeploy resource yang sama.
5. Buka aplikasi dan Ctrl+F5. CSS, Inbox dan broadcast menggunakan versi aset baru.

Tidak diperlukan tabel baru atau migrasi manual v1.6.0. Jangan menjalankan database.sql pada database operasional atau menghapus volume. Paket tidak membawa file .env, data kontak operasional, atau sesi pengguna.

## 1. Tema dan keterbacaan
- Memperbaiki latar kartu proyek CRM, label, angka, kotak aktivitas dashboard, status dan baris tabel dalam tema gelap.
- Tema Terang/Gelap/Perangkat dan pengaturan reduced motion dari v1.5 tetap tersedia.
- Inbox memakai dua kolom utama. Klik **Detail kontak** untuk membuka panel konteks. Di layar menengah/HP, panel tampil sebagai drawer agar percakapan tidak terpotong.
- Di HP, pilih percakapan untuk membuka thread; tombol **← Percakapan** kembali ke daftar tanpa mengganti draf dalam halaman yang sama.
- Nama kosong, titik atau tanda baca tidak lagi menyembunyikan nomor: urutannya nama bisnis yang layak → nama kontak yang layak → nomor → Penerima belum diketahui.

## 2. Penghubungan percakapan lama
Untuk kontak **Belum terhubung**, buka Detail kontak → **Cocokkan ulang nomor ke prospek**.
- Hanya admin yang dapat menjalankan tindakan ini, dengan perlindungan CSRF.
- Nomor diperiksa secara lengkap setelah normalisasi 08/+62/62; tidak mencocokkan berdasarkan kemiripan nama.
- Jika tepat satu prospek cocok, percakapan tersimpan untuk nomor/device tersebut dihubungkan dan prospek masuk CRM.
- Jika beberapa prospek memakai nomor yang sama, pilih prospek/proyek secara manual dari daftar. Tidak dipilih otomatis.
- Jika tidak cocok, perbaiki nomor di proyek yang tepat atau buat prospek dari panel kontak. Jangan membuat prospek baru bila bisnisnya sudah ada hanya untuk mengatasi kesalahan nomor.
- Pesan dengan penerima tidak diketahui tetap di antrean terpisah. Kontak/percakapan workspace lain tidak dapat dihubungkan melalui tebakan ID.

Penghubungan menyatukan **data yang sudah tersimpan**. Aplikasi tidak mengunduh seluruh riwayat WhatsApp lama. Jika pesan dikirim dari HP/WhatsApp Web tetapi Starsender tidak mengirim webhook pesan keluar, pesan tersebut belum dapat muncul hanya dengan pembaruan ini. Receiver dan aturan verifikasi device dari patch sebelumnya dipertahankan.

## 3. Pembaruan Inbox otomatis
- Daftar percakapan dan pesan diperiksa setiap 10 detik selama tab terlihat.
- Kotak penulisan tidak diganti, sehingga draf tetap ada. Saat pesan sedang dikirim, pembaruan ditunda.
- Jika sedang membaca bagian atas/riwayat lama, pesan tidak diganti otomatis. Tombol **Tampilkan pesan terbaru** muncul ketika data berubah.
- Pada halaman riwayat lama, tombol itu membuka halaman pesan terbaru. Navigasi dengan draf yang belum dikirim memunculkan konfirmasi bawaan browser bila didukung browser.
- Saat koneksi bermasalah, percobaan dijeda bertahap hingga 60 detik dan status kesalahan ditampilkan. Saat sesi habis, aplikasi meminta muat ulang.
- Pembaruan hanya membaca data yang boleh diakses akun. Anggota tetap hanya melihat percakapan prospek yang ditugaskan kepadanya.
- Keterangan waktu/kelengkapan riwayat diringkas ke panel yang dapat dibuka, agar area pesan lebih lapang.

## 4. Dashboard yang dapat ditindaklanjuti
- **Prospek aktif tanpa jadwal** menghitung prospek CRM tahap Dihubungi/Berminat/Negosiasi yang tidak memiliki aktivitas terbuka. Klik untuk melihat daftar dan membuat jadwal.
- **Pesan masuk perlu balasan** membuka filter Inbox **Perlu balasan**.
- Perlu balasan berarti waktu pesan masuk terakhir lebih baru daripada pesan keluar yang tercatat diterima gateway/webhook, atau belum ada balasan keluar tercatat. Pesan keluar yang ditolak tidak menghapus kebutuhan balasan. Ini bukan status baca WhatsApp, dan hasil bisa tidak lengkap jika webhook pesan keluar tidak tersedia.
- Angka Menunggu follow-up lama tetap memakai definisi sebelumnya, sehingga tidak disamakan dengan aktivitas hari ini.
- Panel konteks Inbox menyediakan tautan langsung **Tahap & catatan** dan **Jadwalkan follow-up** ke formulir CRM yang sudah ada; ini bukan formulir penyuntingan inline baru.

## 5. Riwayat broadcast
CRM proyek → **Riwayat broadcast**.
- Menampilkan waktu pembuatan, template/pesan bebas, filter tahap, jumlah diproses/belum diproses, diterima gateway, ditolak, belum pasti, dilewati dan duplikat.
- **Buka progres** hanya membaca progres. Pengiriman baru berjalan setelah tombol Kirim/Lanjutkan ditekan secara sengaja.
- Menutup progres dari riwayat tidak membukanya kembali terus-menerus; parameter resume dikonsumsi sekali.
- Maksimal 10 kampanye per halaman. Riwayat memakai snapshot dan pencatatan pengiriman yang sudah tersedia; tidak mengirim atau membuat reservasi pesan saat dibuka.
- Diterima gateway belum membuktikan pesan sampai atau dibaca. Antrean masih dijalankan dari browser, bukan background worker.

## Batasan yang masih perlu ditindaklanjuti
- Penghapusan duplikat lintas catatan webhook dan CRM berdasarkan ID pesan provider belum ditambahkan: sumber pengiriman yang ada belum menyimpan identitas provider yang dapat dipadankan secara pasti. Tidak menghapus pesan hanya karena teks dan waktunya mirip, agar pesan sah tidak hilang.
- Filter otomatis lintas kampanye "belum pernah menerima pesan ini" dan pengecualian otomatis setelah balasan belum ditambahkan. Pilih penerima dan periksa riwayat sebelum kampanye berikutnya.
- Balasan seperti "saat ini belum bisa" tidak otomatis menandai Jangan dihubungi; penilaian tetap dilakukan petugas.
- Riwayat WhatsApp yang tidak pernah diterima aplikasi membutuhkan pemeriksaan log/payload server. Tidak ada panggilan ke akun Starsender pengguna atau pengiriman nyata selama pengembangan.

## Verifikasi
- Suite PHP untuk tim, instalasi, Inbox, percakapan, impor CSV/XLSX/1.000 baris, broadcast, label kontak, penghubungan ulang, pembatasan workspace, daftar perlu balasan dan dashboard lulus.
- 28 tes HTTP aplikasi dan 8 tes webhook lulus.
- Tes JavaScript tema dan DOM untuk pemilihan broadcast, serialisasi impor, polling, draf, jeda tab tersembunyi, backoff, posisi baca dan resume riwayat lulus.
- 119 file PHP dan 14 aset JavaScript lolos pemeriksaan sintaks.
- Reviewer terpisah menemukan dua masalah posisi baca dan parameter resume. Keduanya direproduksi dengan tes gagal, diperbaiki, lalu tes khusus dan suite lulus.
- Database pengujian memakai SQLite dengan penerjemahan SQL khusus tes; penguncian/konkurensi MariaDB belum diverifikasi langsung.
- Pemeriksaan DOM tidak menggantikan pemeriksaan tampilan browser nyata. Screenshot hasil render desktop/HP dan integrasi gateway langsung belum terverifikasi dalam lingkungan ini.

## Tes pengembang
Dari root sumber: `sh tests/run.sh` (PHP 8.3 beserta PDO SQLite, mbstring, XML, ZIP, cURL; Python 3; Node.js).
Tes DOM membutuhkan jsdom hanya di lingkungan pengujian: jalankan `node tests/frontend.test.cjs`, `node tests/inbox_frontend.test.cjs`, `node tests/inbox_reading.test.cjs`, `node tests/resume_frontend.test.cjs`. JSDOM_MODULE dapat menunjuk lokasi modul jsdom. Tidak ada dependensi frontend produksi baru.

## Pemeriksaan setelah redeploy
1. Pilih tema gelap, buka Dashboard dan daftar proyek CRM: pastikan kartu dan baris tabel terbaca.
2. Buka Inbox dan Detail kontak; periksa juga ukuran layar HP.
3. Pilih percakapan belum terhubung yang nomor prospeknya sudah benar, lalu Cocokkan ulang nomor ke prospek.
4. Tulis draf, tunggu pembaruan otomatis, dan pastikan draf tetap utuh. Saat membaca pesan lama, periksa tombol Tampilkan pesan terbaru.
5. Buka riwayat broadcast, lihat progres lalu tutup. Pastikan bisa kembali menyiapkan broadcast baru tanpa otomatis mengirim pesan.
