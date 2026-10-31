# LegaOne v1.7.0

Pembaruan akun klien dengan database terpisah, undangan tim, verifikasi dan pemulihan melalui Starsender/Mailketing. Baca `PANDUAN_UPDATE_v1.7.0.md` sebelum mengaktifkan mode platform. Default tetap nonaktif.

# LegaOne v1.6.0

Perbaikan tema, layout Inbox, pembaruan pesan otomatis, penghubungan ulang nomor, tindak lanjut dan riwayat broadcast. Baca **PANDUAN_UPDATE_v1.6.0.md**.

---

# LegaOne v1.5.0

Impor proyek CSV/XLSX, pilihan tahap pipeline untuk broadcast, dan tema terang/gelap/perangkat. Baca **PANDUAN_UPDATE_v1.5.0.md** untuk pemasangan dan batasan pengujian.

---

# Pembaruan 1.2.0 — Kotak Masuk Starsender Premium

Paket ini melanjutkan versi tim Coolify di bawah. Panduan fitur baru dan pembaruan instalasi: [docs/STARSENDER_INBOX.md](docs/STARSENDER_INBOX.md). Baca bagian tersebut terlebih dahulu jika database versi tim sudah berisi data. Untuk instalasi baru, gunakan panduan dasar di bawah lalu aktifkan webhook opsional.

# Gmap Prospek Web Team untuk Coolify

Versi modifikasi dari paket 1.0.0 yang diberikan pengguna. Satu instalasi untuk satu workspace tim. PHP tetap memakai login; aktivasi lisensi perangkat dan koneksi ke server aktivasi telah dihapus. Gunakan source sesuai hak penggunaan Anda. Paket ini tidak menyertakan license key, konfigurasi Laragon, database lokal, atau kredensial lokal Anda.

## Perilaku akun

| Peran | Akses |
| --- | --- |
| Admin | Dashboard lama, pencarian/scraping, proyek, seluruh CRM, laporan, gateway, membuat akun, reset password anggota, penugasan |
| Anggota | Halaman Prospek saya, pencarian daftar sendiri, ringkasan sendiri, detail/status/catatan/nilai deal, tugas, lampiran, ekspor CSV, profil sendiri |

Anggota tidak dapat membuka halaman admin melalui URL langsung. Daftar, pencarian, hitungan, ekspor dan unduhan anggota memakai filter penugasan di SQL. Anggota tidak bisa mengambil semua data sebuah proyek hanya karena memegang satu prospek proyek tersebut. Lead tanpa penanggung jawab hanya terlihat admin. Penugasan otomatis memasukkan prospek ke CRM agar pekerjaan anggota muncul dalam laporan admin.

Penugasan ulang mencabut akses penanggung jawab sebelumnya pada permintaan berikutnya. Riwayat, tugas, dan lampiran tetap mengikuti prospek dan terlihat penanggung jawab baru. Unduhan yang sudah disimpan di komputer pengguna tidak dapat ditarik kembali. Menonaktifkan anggota mencabut sesi dan mengembalikan prospeknya ke antrean admin. Reset password juga mencabut sesi akun tersebut. Admin dapat mengakses semua data workspace ini. Tidak ada pendaftaran publik atau fitur membuat workspace kedua.

## 1. Upload manual ke GitHub

1. Buat repository **private** baru agar source tidak dibagikan secara publik.
2. Ekstrak ZIP ini.
3. Upload **isi folder `gmap-prospek-web`**, sehingga `Dockerfile`, `compose.yaml`, `database.sql`, dan folder `app` berada langsung di root repository.
4. Sertakan `.htaccess`, `public/.htaccess`, `.gitignore`, `.dockerignore`, dan `.env.example`. File bertitik dapat tersembunyi di beberapa aplikasi ekstrak.
5. Jangan upload file `.env`, `.venv`, folder `storage`, database lokal atau file `config/license.json`/`config/gateway_key.php` dari Laragon. Paket distribusi ini sudah mengecualikannya.

File bernama `database.sql` di paket ini hanya skema instalasi baru tanpa password/akun admin bawaan. Akun contoh di `tests` hanya untuk pengujian, tidak dimasukkan ke image produksi.

## 2. Buat resource di Coolify

1. Di VPS, siapkan Coolify dan domain/subdomain yang mengarah ke IP VPS.
2. Buat project/environment, lalu resource aplikasi dari repository GitHub tersebut. Jika repo private, hubungkan GitHub App/deploy key yang memiliki izin membaca repo itu.
3. Pilih build pack **Docker Compose**. Gunakan base directory `/` dan Compose location `/compose.yaml`.
4. Pada layanan **app**, atur domain seperti `https://crm.domainanda.com`. Port internal aplikasi adalah **80**, bukan 8000. Jika UI Coolify meminta port tujuan, pilih 80.
5. Jangan beri domain atau public port pada layanan **db**. Compose sengaja hanya memakai jaringan internal.
6. Isi environment variable pada tabel berikut sebelum deploy. Simpan, lalu deploy.

Coolify dapat mengubah nama/menu sesuai versinya. Prinsipnya adalah build Compose dari repository, arahkan domain ke service `app` port 80, dan simpan secret sebagai runtime environment.

## 3. Environment wajib

| Variable | Isi |
| --- | --- |
| `APP_URL` | URL domain final, contoh `https://crm.domainanda.com`, tanpa path dan tanpa `:8000` |
| `APP_NAME` | Nama aplikasi, boleh `Gmap Prospek` |
| `DB_PASSWORD` | Password acak kuat untuk user database aplikasi |
| `DB_ROOT_PASSWORD` | Password acak kuat lain untuk root MariaDB |
| `XSENDER_ENCRYPTION_KEY` | Base64 dari 32 byte acak untuk menyimpan secret gateway |
| `ADMIN_USERNAME` | Username admin awal, 3–50 huruf/angka/titik/underscore/minus |
| `ADMIN_EMAIL` | Email admin awal |
| `ADMIN_NAME` | Nama admin awal |
| `ADMIN_PASSWORD` | Password admin awal, minimal 12 dan maksimal 72 byte |

Jangan isi `DB_HOST`, `DB_PORT`, `DB_NAME`, atau `DB_USER` secara terpisah untuk Compose ini. Sudah ditetapkan menjadi `db`, `3306`, `gmap`, dan `gmap`. Password root dan user database sebaiknya berbeda.

Untuk membuat nilai acak, jalankan **satu per satu** di terminal VPS:

```sh
openssl rand -hex 24
```

Gunakan hasil pertama untuk `DB_PASSWORD`. Jalankan lagi untuk `DB_ROOT_PASSWORD`, dan lagi jika ingin password admin acak.

Untuk kunci enkripsi gateway:

```sh
openssl rand -base64 32
```

Masukkan hasilnya sebagai `XSENDER_ENCRYPTION_KEY`. Simpan kunci ini bersama backup Anda. Jangan menggantinya setiap redeploy; gateway yang tersimpan tidak bisa didekripsi dengan kunci berbeda. Jangan kirim nilai rahasia atau screenshot environment ke percakapan.

`APP_ENV=production`, zona waktu `Asia/Jakarta`, `PYTHON_BIN`, `CHROME_BIN` dan `CHROMEDRIVER_BIN` sudah disiapkan oleh paket.

## 4. Deployment pertama dan penggunaan

Build pertama mengunduh PHP, Chromium, driver Chromium, Python dan Selenium. Siapkan koneksi keluar VPS ke registry Docker, repositori Debian, PyPI, dan Google Maps. Tidak perlu memasang Chrome/Python di host VPS karena ada di image aplikasi.

Setelah service `db` sehat, entrypoint `app` memeriksa runtime, menginisialisasi skema, dan membuat akun admin **hanya jika tabel akun masih kosong**. Jika environment wajib tidak valid, aplikasi berhenti dengan pesan di log; tidak ada mode tanpa login sebagai fallback.

1. Buka domain HTTPS Anda dan login dengan akun admin dari environment.
2. Buka **Tim & penugasan** untuk membuat akun anggota. Password awal/reset oleh admin minimal 12 byte. Anggota dapat mengubah password melalui Profil; formulir profil bawaan masih memakai batas minimal delapan karakter.
3. Dari dashboard admin, lakukan pencarian prospek dengan target 5 untuk uji pertama.
4. Kembali ke **Tim & penugasan**, cari prospek/proyek, centang prospek, pilih anggota, lalu terapkan.
5. Coba login sebagai anggota melalui browser/incognito lain. Anggota akan masuk ke halaman **Prospek saya**.
6. Verifikasi bahwa anggota lain tidak dapat membuka detail atau lampiran prospek tersebut.

Admin mengambil/mengimpor data dan membagikannya; anggota tidak diberi akses scraper atau pengaturan gateway. Lampiran baru melalui halaman anggota menerima PDF/JPG/PNG maksimal 5 MB. Upload Office melalui fitur lama admin tetap mengikuti validasi bawaan aplikasi.

## 5. Redeploy dan penyimpanan

Compose menggunakan dua named volume:

- `db_data`: database, termasuk proyek, akun, penugasan, aktivitas dan lampiran yang disimpan dalam database.
- `app_storage`: sesi login dan direktori cache browser.

Restart/redeploy biasa mempertahankan data selama resource dan volume yang sama digunakan. **Jangan menghapus volume, memakai `docker compose down -v`, atau menghapus resource beserta storage.** Membuat resource Coolify baru menghasilkan volume berbeda; pindahkan backup jika ingin memindahkan instalasi.

Mengubah `ADMIN_PASSWORD` pada environment setelah akun dibuat **tidak mereset password**. Ini disengaja. Ubah password sendiri lewat Profil, atau gunakan CLI pemulihan di bawah. Kredensial bootstrap tetap diperlukan oleh definisi Compose saat redeploy; simpan secara rahasia. Mengubah `DB_PASSWORD` setelah database terbentuk juga tidak otomatis mengubah password user MariaDB. Lakukan rotasi password database melalui prosedur MariaDB dan update environment secara terkoordinasi.

Backup database melalui Coolify jika tersedia, atau dari terminal service **db**:

```sh
mariadb-dump -u root -p --single-transaction --routines --triggers gmap > /tmp/gmap-backup.sql
```

Perintah meminta password root. Salin hasil dump keluar container segera, karena `/tmp` bukan tempat backup permanen. Simpan juga `XSENDER_ENCRYPTION_KEY` dan konfigurasi environment secara terpisah. Uji proses restore sebelum mengandalkan backup.

## 6. Pemulihan akun admin

Buka terminal service **app**, direktori `/var/www/html`, lalu jalankan:

```sh
php scripts/create_user.php admin admin@domainanda.com "Admin Tim" admin
```

Gunakan username dan email **persis sama dengan akun yang ingin direset**. Password baru diminta secara interaktif, 12–72 byte. Input terlihat di terminal, jadi jangan membagikan rekaman/screenshot terminal tersebut. Skrip ini hanya dapat dijalankan melalui CLI; tidak ada endpoint reset tanpa autentikasi.

## 7. Instalasi lama dari Laragon

Paket ini memakai skema tim baru. Untuk deployment pertama, gunakan database baru dari Compose. Jangan menimpa database Laragon atau mengimpor `database.sql` versi lama ke volume produksi baru.

Installer menolak tabel akun versi lama yang belum memiliki kolom workspace, bukan diam-diam mengubah hak akses akun lama. Jika perlu membawa data prospek lama, backup dulu dan rencanakan migrasi terpisah. Jangan membawa `license.json` ke VPS; paket ini tidak memakainya. Jika membawa gateway dari instalasi lama melalui migrasi, kunci enkripsi instalasi lama juga diperlukan untuk dekripsi sebelum enkripsi ulang.

## 8. Kebutuhan VPS dan batas scraper

Saran kapasitas awal, bukan hasil benchmark: VPS 2 vCPU dan RAM 4 GB untuk pemakaian tim kecil, lalu pantau penggunaan Chromium saat scraping. Image memakai `/dev/shm` 512 MB. Setiap pencarian masih sinkron dengan batas subprocess 120 detik; mulai dari target kecil dan hindari banyak pencarian serentak.

Pastikan timeout proxy Coolify setidaknya 180 detik jika pencarian sering terputus. Apache/PHP sudah memakai batas 180 detik. Google Maps dapat mengubah tampilan, membatasi akses, atau menampilkan CAPTCHA; instalasi berhasil tidak menjamin hasil scraper. Tidak ada bypass CAPTCHA atau jaminan jumlah hasil. Paket mengambil data Maps melalui browser Selenium, bukan Google Maps API berbayar.

Chromium berjalan di container dengan flag bawaan scraper `--no-sandbox`. Jalankan hanya pada halaman yang ditentukan aplikasi, jangan beri container mode privileged atau mount Docker socket. Port aplikasi hanya diekspos ke proxy Coolify; database tidak dipublikasikan. HTTPS diteruskan melalui `X-Forwarded-Proto` dari proxy. Jangan mempublikasikan port container langsung ke internet sebagai pengganti proxy.

## 9. Pengujian pengembang

Jalankan dari root proyek dengan PHP 8.3, PDO SQLite dan Python 3:

```sh
php tests/team.php
php tests/install.php
```

Untuk integrasi nyata, buat database **sementara** bernama `gmap_test` di MariaDB/MySQL. Jangan memakai database produksi. Atur environment di terminal pengujian, misalnya:

```sh
export APP_ENV=development
export APP_URL=http://localhost
export DB_HOST=127.0.0.1
export DB_PORT=3306
export DB_NAME=gmap_test
export DB_USER=user_pengujian
export DB_PASS=password_database_pengujian
export ADMIN_USERNAME=owner
export ADMIN_EMAIL=owner@example.test
export ADMIN_NAME=Owner
export ADMIN_PASSWORD='LongPassword123!'
export ALLOW_TEST_RESET=1
python3 tests/test_http.py
php tests/legacy.php
```

`tests/test_http.py` menghapus dan membuat ulang tabel dalam database `*_test` sebelum memulai. Ia menolak nama database tanpa akhiran `_test`. Memerlukan ekstensi PDO MySQL, mbstring, fileinfo serta izin membuat/menghapus tabel pada database uji. `PHP_BIN` opsional untuk memilih executable PHP. Semua kredensial di contoh ini khusus pengujian dan tidak menjadi akun produksi.

## Hasil verifikasi paket

Lihat `VALIDATION.md` untuk hasil pengujian dan batas verifikasi. Build image Docker, koneksi proxy Coolify, pengiriman gateway, dan scraping Google Maps di VPS belum dijalankan dari lingkungan penyusunan paket ini. Lakukan deployment pertama dan uji 5 prospek sebelum dipakai tim.

## Referensi deployment

- https://coolify.io/docs/applications/builds/docker-compose
- https://coolify.io/docs/applications/configuration/environment-variables
- https://hub.docker.com/_/php
- https://docs.docker.com/reference/compose-file/services/


## Pembaruan v1.7.1
Perbaikan kompatibilitas URL pengaturan akun platform: `/account/setting` diarahkan ke `/account/settings`. Lihat `PANDUAN_UPDATE_v1.7.1.md`.
