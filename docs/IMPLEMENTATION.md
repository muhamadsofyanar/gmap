# Catatan implementasi

Sumber awal: ZIP gmap-prospek-v1.0.0 milik pengguna. Keputusan pengguna: satu workspace tim; admin melihat seluruh data; anggota hanya prospek penugasannya. Tidak mengubah desain utama dashboard admin. Halaman tim baru memakai controller/repository terpisah dan controller lama dibatasi admin di AuthGuard. Workspace kedua dan isolasi lintas perusahaan belum menjadi fitur paket ini.

## Pemeriksaan independen

Reviewer memeriksa source dan perubahan terhadap baseline. Tidak menemukan bypass lintas prospek pada query anggota. Tiga temuan penting diperbaiki dengan pengujian regresi: binding SQL riwayat admin, pemeriksaan session_version dalam transaksi, dan pemasukan prospek penugasan ke CRM agar laporan admin konsisten.

Temuan minor yang dipertahankan: formulir perubahan password profil bawaan menerima minimal delapan karakter; akun awal dan reset oleh admin memakai minimal 12 byte. Hal ini disebutkan dalam README agar kebijakannya tidak disalahartikan.

Build image, proxy Coolify aktual, persistence volume pada VPS, Chromium dan gateway belum diverifikasi langsung. Unit/integrasi lokal bukan pengganti smoke test setelah deployment. Installer, schema, SQL dan HTTP memakai MariaDB nyata dalam pengujian, sedangkan pengujian repository cepat memakai SQLite.
