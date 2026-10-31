# LegaOne v1.7.0 — akun klien, undangan tim, dan pemulihan password

Paket pembaruan ini ditujukan untuk sumber v1.6.0. Tersedia patch perubahan dan sumber lengkap. Tidak ada kredensial produksi di dalam paket.

## Fitur

- Daftar klien dari halaman login. Setelah memverifikasi tujuan dan, jika diwajibkan, disetujui administrator, klien memiliki database workspace sendiri.
- Pemilik workspace mengundang anggota. Anggota menerima akses pengguna, bukan administrator. Email, kanal, dan workspace tujuan dikunci di undangan.
- Verifikasi pendaftaran, kirim ulang verifikasi, lupa/reset password, undangan anggota, serta pemberitahuan pendaftaran ke administrator.
- Kanal akun dapat menggunakan Mailketing (email) atau Starsender (WhatsApp), diatur oleh administrator platform melalui Pengaturan → Akun platform.
- Pengaturan API key terenkripsi; kolom kosong mempertahankan kunci lama. Template pesan dapat disesuaikan. Riwayat 50 notifikasi terakhir hanya menampilkan metadata dan tujuan yang disamarkan.
- Tautan sekali pakai: verifikasi 30 menit, pemulihan 20 menit, undangan 48 jam. Membuka tautan belum menjalankan perubahan; pengguna harus menekan tombol konfirmasi.
- Reset password mencabut sesi lama. CSRF, pembatasan permintaan, dan pemeriksaan status akun berlaku di alur akun.

Status `accepted` berarti API provider menerima permintaan, bukan bukti pesan terkirim/dibaca. Pengujian dalam paket menggunakan pengirim tiruan; pengiriman API produksi perlu diuji dari pengaturan sesudah instalasi.

## 1. Cadangkan sebelum pembaruan

Cadangkan database asli, kode v1.6.0, volume `db_data`, volume `app_storage`, konfigurasi Coolify, dan nilai `XSENDER_ENCRYPTION_KEY`. Pertahankan kunci enkripsi yang sama. Kehilangan atau mengganti kunci membuat API key dan kredensial database klien tidak dapat didekripsi.

Patch berisi hanya file baru/berubah dibanding v1.6.0. Ekstrak patch ke akar repository yang berisi Dockerfile/compose.yaml, lalu commit dan deploy melalui Coolify. Jangan mengekstrak ZIP luar atau ZIP di dalamnya langsung ke direktori public.

**Pertahankan volume database dan storage yang sudah terpasang. Jangan menjalankan `docker compose down -v`, reset database, atau membuat ulang volume sebagai langkah pembaruan.**

## 2. Siapkan provisioning database

Secara bawaan `PLATFORM_ACCOUNTS_ENABLED=0` sehingga login lama tetap digunakan. Mode klien memerlukan satu akun MariaDB khusus untuk membuat database dan pengguna database klien. Akun aplikasi `gmap` tetap dibatasi pada database asli.

Pada terminal database Coolify, masuk sebagai administrator database. Untuk DB_NAME bawaan `gmap`, jalankan SQL berikut dengan mengganti placeholder password kuat secara lokal:

```sql
CREATE USER 'legaone_provision'@'%' IDENTIFIED BY 'GANTI_DENGAN_PASSWORD_ACAK_KUAT';
GRANT CREATE USER ON *.* TO 'legaone_provision'@'%';
GRANT ALL PRIVILEGES ON `gmap\_client\_%`.*
  TO 'legaone_provision'@'%' WITH GRANT OPTION;
```

Database baru dinamai `gmap_client_ID`, dengan pengguna dan password acak masing-masing. Pola grant di atas membatasi hak data pada database klien. Hak global CREATE USER tetap merupakan hak administratif sensitif untuk membuat/mengubah akun database: simpan kredensial provisioning sebagai secret server, batasi akses server, dan jangan menggunakannya untuk koneksi aplikasi sehari-hari. Tidak perlu memberikan root database kepada aplikasi.

Jika DB_NAME berbeda, sesuaikan pola dengan 36 karakter pertama nama database diikuti `_client_%`, dengan underscore literal di-escape seperti contoh. Ini harus dilakukan oleh pengelola server. Nilai environment baru pada volume MariaDB yang sudah ada tidak otomatis membuat pengguna SQL baru.

## 3. Environment dan deployment

Tambahkan environment berikut di Coolify. compose.yaml versi ini sudah meneruskannya ke aplikasi:

```dotenv
PLATFORM_ACCOUNTS_ENABLED=1
PLATFORM_PROVISION_USER=legaone_provision
PLATFORM_PROVISION_PASSWORD=PASSWORD_YANG_DIBUAT_DI_DATABASE
```

Pastikan `APP_URL` adalah URL HTTPS publik yang benar dan `XSENDER_ENCRYPTION_KEY` tetap memakai nilai lama. Environment DB, admin awal, dan webhook lama dipertahankan. Jangan memakai placeholder contoh sebagai password.

Deploy ulang. Entrypoint menjalankan `scripts/install.php`: skema platform ditambahkan dan akun asli didaftarkan tanpa menyalin atau mereset password. Data proyek/kontak asli tetap di database asli. Sesi lama mungkin meminta login ulang.

Administrator lokal paling awal yang masih aktif menjadi administrator platform. Akun asli tetap masuk memakai username/email dan password lama. Akun lain yang sudah ada tetap menjadi anggota workspace asli. Username dan email pada registri akun harus unik di seluruh platform.

## 4. Aktifkan kanal di Pengaturan

1. Login sebagai administrator platform asli.
2. Buka **Pengaturan → Akun platform** (atau `/account/settings`).
3. Pilih Mailketing atau Starsender, isi API key dan identitas pengirim yang diperlukan, lalu simpan.
4. Mailketing: gunakan email/domain pengirim yang telah diizinkan oleh Mailketing. Starsender: gunakan API key perangkat yang dapat mengirim pesan.
5. Isi email/WhatsApp administrator bila ingin menerima pemberitahuan pendaftaran baru. Tujuan mengikuti kanal utama.
6. Sesuaikan template dengan `{name}`, `{app_name}`, dan `{link}`. Tautan tindakan akan ditambahkan jika template tidak memuatnya.
7. Gunakan **Uji notifikasi**, lalu periksa pesan pada tujuan uji dan riwayat status.
8. Centang **Buka pendaftaran workspace baru**. Aktifkan persetujuan administrator jika ingin memeriksa klien sebelum database disiapkan.

Pendaftaran tetap ditolak bila kredensial provisioning atau konfigurasi kanal belum tersedia. Pemeriksaan awal ini tidak membuktikan izin server benar; uji satu pendaftaran lengkap sebelum membuka untuk publik.

Dokumentasi provider: https://docs.starsender.online/docs/message-api/kirim-pesan dan https://api.mailketing.co.id/docs/ .

## 5. Alur klien dan tim

**Klien baru:** halaman login → Daftar workspace → isi identitas dan password → buka pesan → konfirmasi verifikasi → jika perlu, tunggu administrator menekan Setujui di Akun & undangan → masuk. Data proyek, CRM, broadcast, dan inbox klien berada di database klien tersebut.

**Tim:** pemilik workspace → Akun & undangan → isi email (serta WhatsApp bila memakai Starsender) → kirim undangan. Penerima membuka tautan dan menentukan nama, username, dan password. Tidak tersedia pendaftaran bebas yang langsung menggabungkan seseorang ke tim lain. Satu email/username hanya dapat memiliki satu akun platform pada versi ini.

**Akun lama:** email/WhatsApp belum dianggap terverifikasi hanya karena ada di data lama. Selagi masih login, buka Profil → Verifikasi tujuan pemulihan atau tombol yang sama pada Akun & undangan. Isi identitas akun sendiri, terima tautan, lalu konfirmasi. Sesudah itu fitur Lupa password dapat digunakan. Akun lama tanpa nomor WhatsApp perlu memakai kanal email; perubahan tujuan pemulihan dan perpindahan antarworkspace belum disediakan di UI.

**Lupa password:** tautan dikirim ke tujuan dan kanal yang sudah diverifikasi, bukan nomor/email baru yang dimasukkan pemohon. Respons umum tetap sama untuk akun yang tidak ditemukan. Jika mengganti kanal utama platform, pertahankan konfigurasi kanal lama agar akun yang sebelumnya diverifikasi di kanal tersebut tetap dapat memulihkan password.

Administrator platform dapat menonaktifkan akun klien. Menonaktifkan pemilik workspace klien menangguhkan seluruh workspace beserta login anggotanya. UI v1.7.0 belum menyediakan aktivasi kembali akun yang sudah dinonaktifkan; gunakan tindakan ini dengan pertimbangan.

## 6. Starsender inbox untuk tiap klien

Pengaturan provider notifikasi akun dikelola pusat. Pengaturan gateway broadcast dan inbox bisnis tetap milik workspace masing-masing.

Pemilik klien membuka **Pengaturan → Callback Starsender workspace**, mengisi Device ID/fingerprint, lalu menyalin URL callback ke perangkat Starsender miliknya. URL mengandung token rahasia: jangan dibagikan. Rotasi URL membuat token lama tidak berlaku. Jangan memakai callback workspace asli untuk perangkat klien lain. Endpoint asli dan environment webhook lama tetap dipakai untuk workspace asli bila sudah dikonfigurasi sebelumnya.

## Pemulihan gangguan dan operasional

- DNS `Could not resolve host: github.com` terjadi sebelum build dan bukan masalah kode akun. Periksa DNS/koneksi server lalu retry deployment.
- Gagal verifikasi/provisioning: periksa izin SQL, kapasitas database, host, dan kunci enkripsi. Minta tautan baru jika tautan sudah kedaluwarsa. Penyiapan ulang tidak menghapus database; kegagalan di tengah proses DDL dapat meninggalkan database/pengguna yang belum terhubung. Pemeriksaan dan pembersihan orphan memerlukan administrator database; jangan menghapus database yang sudah berisi data.
- Reset memakai transaksi pada registri dan database klien yang terpisah. Bila token sudah dikonsumsi tetapi perubahan password gagal karena database tidak tersedia, minta tautan pemulihan baru.
- Backup setelah mode klien aktif harus mencakup database asli **dan seluruh** `gmap_client_*`, pengguna/grant database terkait, storage, serta secret enkripsi. Pembaruan berikutnya juga perlu memperhitungkan skema seluruh database klien.
- Mematikan mode platform mengembalikan login lokal workspace asli; klien tidak dapat masuk lewat mode lokal. Jangan melakukan rollback kode sementara klien masih menggunakannya tanpa rencana pemulihan.

## Validasi paket

Pengujian akun 61 pemeriksaan; notifikasi 37 pemeriksaan tanpa API langsung; database MariaDB nyata 27 pemeriksaan; HTTP platform 7 skenario; serta rangkaian regresi impor, pipeline, broadcast, inbox, tema, dan webhook versi sebelumnya. Pengujian database memakai server sementara terisolasi. Tata letak browser langsung dan pengiriman provider produksi belum diverifikasi.
