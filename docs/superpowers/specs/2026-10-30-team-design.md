# Gmap Prospek Web untuk tim

Disetujui dalam percakapan: satu workspace tim per instalasi, admin mengatur akun dan penugasan, anggota hanya mengakses prospeknya sendiri. Login wajib; pemeriksaan lisensi perangkat dihapus. Paket diunggah manual ke GitHub lalu dibangun dengan Docker Compose di Coolify.

## Pendekatan

Menambah filter ke semua model lama akan menyebarkan aturan akses ke banyak jalur. Memisahkan database per akun akan menyulitkan penugasan. Pilihan implementasi: admin tetap memakai dashboard lama; anggota memakai TeamController dan TeamRepository dengan query terikat akun. Guard menolak semua controller lama untuk anggota kecuali login/logout dan profil sendiri. Tim bukan multitenant SaaS; satu instalasi hanya memiliki satu workspace.

Admin dapat membuat/menonaktifkan akun anggota, mereset password anggota, mencari dan menugaskan prospek. Anggota melihat daftar, detail, status, catatan, tugas, lampiran, ekspor CSV dan ringkasan hanya untuk prospek penugasannya. Anggota tidak dapat scraping, mengubah gateway, menugaskan ulang, menghapus proyek atau mengakses cache global. Penugasan, perubahan status/catatan, tugas dan lampiran dicatat bersama actor. Riwayat prospek mengikuti prospek ketika dialihkan ke anggota lain; pemilik lama kehilangan akses.

## Deployment

PHP Apache, Python/Selenium, Chromium dan driver seversi dari paket Debian. Database MariaDB berada di jaringan internal Compose. Environment wajib untuk URL HTTPS, password database, kunci enkripsi dan akun admin awal. Schema diinisialisasi oleh CLI sebelum Apache menerima trafik. Tidak menyertakan akun/password default. Bootstrap admin hanya ketika tabel users kosong, bukan setiap redeploy. Data database, sesi dan storage memakai volume. Tidak mempublikasikan port database atau PHP diagnostik.

## Batas dan verifikasi

Instalasi baru menjadi jalur utama. Data Laragon lama harus dicadangkan dan dipindahkan melalui prosedur khusus, tidak diimpor otomatis. Pengujian perilaku harus membuktikan member A tidak membaca/mengubah/mengunduh/mengekspor data B, URL legacy tertolak, anggota tidak dapat mengelola akun atau assignments, sesi akun nonaktif dicabut, serta admin dapat menugaskan ulang. Build Docker dan scraping Google Maps langsung dilaporkan terpisah jika runtime lingkungan tidak tersedia.
