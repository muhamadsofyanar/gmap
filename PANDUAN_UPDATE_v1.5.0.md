# LegaOne v1.5.0 — Impor Proyek, Broadcast Pipeline & Tema

Pembaruan 31 Oktober 2026. Dibangun dari paket sumber v1.2.0, pembaruan v1.3.0/v1.4.0 dan perbaikan webhook Starsender terbaru yang diberikan.

## Pilih paket
- **LegaOne_Update_v1.5.0.zip**: untuk aplikasi yang sudah terpasang dari sumber yang diberikan. Patch kumulatif: mencakup perubahan CRM Inbox v1.3, pesan bebas v1.4, receiver/service webhook terbaru dan fitur v1.5. Struktur file langsung app/, public/, config/, tests/, docs/.
- **LegaOne_Source_Lengkap_v1.5.0.zip**: seluruh sumber untuk cadangan atau instalasi baru. Jangan menjalankan database.sql pada database operasional yang sudah berisi data.

## Cara memperbarui aplikasi yang sudah ada
1. Cadangkan database dan sumber aplikasi yang sedang berjalan.
2. Ekstrak **LegaOne_Update_v1.5.0.zip**. Salin folder app, public, config, tests dan docs ke root repository aplikasi, sejajar dengan Dockerfile. Timpa file yang sama; jangan membuat folder app di dalam app.
3. Pertahankan environment Coolify, Compose, volume database, API key, kunci enkripsi, URL dan token webhook yang sudah berjalan. Patch tidak membawa file .env maupun data sesi pengguna.
4. Commit perubahan ke repository yang digunakan Coolify, lalu Redeploy resource yang sama.
5. Buka kembali aplikasi dan tekan Ctrl+F5. Versi aset tampilan dan broadcast sudah dinaikkan untuk menghindari cache lama.
6. Uji impor beberapa kontak terlebih dahulu, pilih tahap pipeline, lalu periksa tema terang/gelap dan layar HP sebelum broadcast operasional.

Tidak ada tabel baru atau migrasi manual khusus v1.5.0. Tabel impor/CRM sudah tersedia dalam sumber dasar dan mekanisme instalasi sebelumnya. Jangan menghapus volume atau melakukan reset database.

## 1. Impor dimulai dari proyek
- Daftar Proyek → **Tambah / impor prospek**, atau buka proyek → **Impor kontak**.
- Pilih proyek yang sudah ada, atau buat proyek baru melalui formulir impor.
- Unduh contoh CSV bila perlu. Format yang didukung CSV dan XLSX, maksimal 5 MB, 1.000 baris data, 32 kolom. XLSX membaca sheet pertama; berkas berisi rumus harus diubah menjadi nilai terlebih dahulu. XLS lama perlu disimpan sebagai XLSX.
- Unggah → Cocokkan kolom → Tinjau data → Konfirmasi impor pilihan.
- Nama bisnis wajib; kolom lain opsional. Simpan nomor telepon sebagai teks dalam Excel agar angka nol di depan tidak hilang. Nomor dinormalisasi ke format 62.
- Baris tidak valid/duplikat tidak dimasukkan; hasil pemeriksaan dapat diunduh sebagai CSV. Ini laporan tinjauan, bukan bukti semua baris disimpan.
- Hasil masuk **proyek**, belum masuk CRM. Dari tabel proyek pilih kontak → **Tambahkan ke CRM** → **Buka pipeline** untuk mengatur tahapnya.
- Duplikat diperiksa berdasarkan kontak, identitas Maps, atau nama dan alamat dalam proyek/file. Tidak menimpa data lama atau menggabungkan otomatis antarproyek.
- Impor 1.000 baris memakai satu payload JSON untuk pilihan agar tidak terpotong max_input_vars PHP. Draf impor berlaku 1 jam. Jangan membagikan tautan draf.

## 2. Broadcast berdasarkan tahap pipeline
- Buka CRM proyek → **Broadcast WhatsApp**.
- Centang satu atau beberapa tahap pipeline. Nama tahap mengikuti pengaturan proyek. Tanpa centang berarti semua tahap pada proyek tersebut.
- Mengubah tahap mengosongkan pilihan kontak sebelumnya. Cari nama/nomor, lalu centang kontak atau pilih hasil pencarian.
- Ringkasan menampilkan kontak dalam tahap, nomor unik valid, duplikat, dan kontak yang tidak dapat dihubungi. Ringkasan tahap berbeda dari jumlah kontak yang Anda centang.
- Tulis pesan bebas atau gunakan template → Lanjut: periksa penerima → tinjau jumlah dan contoh → Kirim.
- Server memvalidasi tahap dan kontak lagi saat persiapan. Penerima, tahap dan pesan terkunci setelah disiapkan. Kontak yang berpindah keluar tahap pilihan sebelum pengiriman dilewati.
- Nomor duplikat menerima satu pesan; kontak Jangan dihubungi tidak dikirimi. Antrean lama tetap bisa dibaca/dilanjutkan.
- Pengiriman tetap berlangsung dari halaman browser. Jeda tersedia; muat ulang dapat memulihkan progres dari proyek yang sama. Tidak ada penjadwalan/background worker baru.
- Status **Diterima gateway** belum membuktikan pesan sampai atau dibaca. Hasil gagal/tidak pasti perlu diperiksa di gateway sebelum mengulang.

## 3. Tampilan dan tema
- Pemilih **Tema** berada di bar atas: Perangkat, Terang, Gelap.
- Default mengikuti perangkat. Pilihan tersimpan per browser dan disinkronkan antar-tab; perubahan tema perangkat diikuti saat mode Perangkat aktif.
- Aksen teal, ruang antarbaris dan kontras tabel diperjelas. Tabel CRM/impor memakai header tetap pada area gulir. Di HP tersedia petunjuk geser tabel, dialog menyesuaikan layar dan kontrol sentuh lebih besar.
- Animasi masuk halaman/dialog dan respons tombol dibuat ringan. Pengaturan reduced motion perangkat dihormati.
- Formulir menampilkan keadaan sibuk dan menahan klik ganda saat menyimpan/mengunggah. Unggahan tidak menampilkan persentase palsu; progres pengiriman memakai jumlah penerima yang telah diproses.

## 4. Kompatibilitas Starsender
Receiver dan service memakai patch Format Webhook v1.2.2. Halaman Inbox tetap memakai fitur percakapan/pesan v1.4.0, ditambah keterangan waktu WIB dan fallback waktu penerimaan server. Tidak perlu memasang ulang ZIP patch lama setelah v1.5.0 karena dapat menimpa halaman Inbox terbaru.

## Verifikasi dan batasan
- 178 pemeriksaan PHP: tim, instalasi, inbox, percakapan, pesan/broadcast, filter pipeline, parser CSV/XLSX dan alur impor termasuk 1.000 baris.
- 33 tes HTTP/webhook; termasuk unggah multipart → pemetaan → tinjau → simpan ke proyek, laporan tinjauan, validasi tahap serta penolakan akses anggota.
- 22 assertion JavaScript untuk tema dan DOM pemilihan pipeline/serialisasi 1.000 pilihan impor.
- 110 file PHP dan 14 aset JavaScript lolos pemeriksaan sintaks.
- Review kode terpisah tidak menemukan masalah kritis/penting.
- Tes database menggunakan SQLite; alur impor memakai penerjemahan SQL khusus pengujian. Penguncian dan konkurensi MariaDB belum diuji langsung.
- Browser pengujian gagal dijalankan (SIGSEGV); tampilan visual desktop/HP belum diverifikasi dalam browser sungguhan. Tes DOM tidak menggantikan pemeriksaan visual.
- Tidak ada pengiriman WhatsApp nyata, perubahan akun gateway, atau deployment ke server pengguna.

## Menjalankan tes pengembang
Dari root sumber, jalankan `sh tests/run.sh` (PHP 8.3 dengan PDO SQLite, mbstring, XML, ZIP, cURL; Node.js; Python 3).
Tes DOM tambahan: pasang jsdom di lingkungan pengujian, lalu `node tests/frontend.test.cjs`. Atau tentukan JSDOM_MODULE ke lokasi modul jsdom. Dependensi ini hanya untuk pengujian, tidak ditambahkan ke aplikasi produksi.
