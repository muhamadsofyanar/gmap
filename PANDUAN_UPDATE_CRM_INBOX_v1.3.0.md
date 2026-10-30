# LegaOne — Update CRM & Inbox v1.3.0

Patch untuk sumber v1.2.2 yang webhook Starsender-nya sudah berjalan. Paket berisi file yang berubah; bukan pengganti seluruh repository.

## Pemasangan di GitHub / Coolify
1. Ekstrak ZIP. Salin folder app, public, tests, dan docs ke root repository gmap dengan struktur yang sama, timpa file terkait. Jangan mengganti seluruh repository dengan isi patch ini.
2. Commit perubahan ke main yang dipakai Coolify.
3. Klik Redeploy di Coolify agar kode baru dibangun dan dijalankan. Tidak perlu mengubah Compose, kredensial, fingerprint perangkat, token webhook, atau URL webhook.
4. Setelah container aktif, buka /inbox dan refresh browser. Pastikan pesan personal baru muncul, lalu coba alur penghubungan di bawah.

Tidak ada perubahan skema atau migrasi database dalam patch ini. Jangan menghapus volume database atau menjalankan reset akun. Receiver Starsender dan konfigurasi yang telah berhasil tidak diubah.

## Mengaitkan pesan dengan CRM
- Buka Kotak masuk → Belum di CRM. Pilih kontak.
- Bila nomor cocok dengan prospek yang ambigu, pilih prospek/proyek yang benar lalu Hubungkan ke prospek.
- Bila nomor belum tersimpan, isi nama bisnis/kontak, proyek tujuan, dan penanggung jawab opsional, lalu Simpan & hubungkan. Nomor berasal dari pesan masuk dan tidak bisa diedit melalui formulir ini.
- Bila sistem sudah mengenali nomor prospek proyek tetapi belum masuk CRM, pilih Masukkan ke CRM.
- Sesudah terhubung, panel kanan menampilkan proyek, penanggung jawab, status, dan nilai deal. Buka detail prospek untuk mengelola status/catatan/tindak lanjut.
- Detail CRM → WhatsApp menampilkan enam pesan terbaru serta tautan ke riwayat lengkap. Riwayat lengkap dibagi 50 pesan per halaman; gunakan Pesan lebih lama / lebih baru.
- Anggota tim hanya melihat percakapan prospek yang saat ini ditugaskan kepadanya. Membuka percakapan tidak menandainya ditinjau secara otomatis.

## Pesan keluar
Tombol Kirim pesan melalui CRM membuka pengirim WhatsApp yang sudah ada pada detail CRM. Pembaruan ini tidak menambah editor balasan langsung di inbox, pengiriman otomatis, atau polling. Gunakan Muat ulang untuk memeriksa pesan baru.

Riwayat pengiriman CRM yang memiliki lead dan nomor penerima yang cocok ditampilkan dalam percakapan bersama pesan masuk. Waktu pengiriman CRM lama dikonversi dari Asia/Jakarta; waktu webhook dari UTC. Status Diterima gateway tidak membuktikan pesan sampai atau dibaca.

Webhook pesan keluar yang belum membawa penerima terverifikasi tetap masuk antrean admin Penerima belum diketahui. Balasan bot/Starsender semacam ini belum dapat disatukan dengan percakapan; sistem tidak menebak penerimanya berdasarkan urutan waktu. Pesan grup tetap mengikuti aturan receiver sebelumnya. Pesan yang dahulu ditolak HTTP422 tidak otomatis dipulihkan oleh patch ini.

## Validasi pengembangan
- 44 pemeriksaan inbox, 27 percakapan/CRM, 58 tim, 7 instalasi: lulus dengan PHP dan SQLite.
- 15 tes HTTP inbox dan 8 tes normalisasi webhook: lulus, tanpa mengirim pesan WhatsApp nyata.
- 93 file PHP aplikasi/tes: pemeriksaan sintaks lulus.
- Review independen: URL detail CRM dan urutan penguncian pembuatan prospek diperbaiki.
- Pengujian database dilakukan dengan SQLite; jalur penguncian MariaDB dan tampilan browser desktop/mobile belum diuji langsung di lingkungan pengembangan ini. CSS memiliki tata letak responsif.

## File dalam patch
- app/Controllers/CrmController.php
- app/Controllers/InboxController.php
- app/Models/ConversationRepository.php
- app/Models/InboxRepository.php
- app/Views/crm/detail.php
- app/Views/inbox/index.php
- app/Views/inbox/messages.php
- app/Views/layouts/header.php
- docs/superpowers/plans/2026-10-30-crm-conversations.md
- docs/superpowers/specs/2026-10-30-crm-conversations-design.md
- public/assets/css/inbox.css
- public/assets/js/inbox.js
- tests/conversations.php
- tests/inbox_seed.php
- tests/test_inbox_http.py
