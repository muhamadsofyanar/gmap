<?php
namespace App\Services;

/** Shared starting points for searches and editable outreach, never automatic sends. */
class BusinessPresets
{
    public static function all(): array
    {
        $segments = [
            'general' => ['Penjualan B2B umum', 'Toko', 'produk atau jasa untuk kebutuhan usaha', 'Cari usaha yang sesuai dengan produk atau jasa Anda.'],
            'website' => ['Web developer', 'Klinik', 'jasa pembuatan website', 'Tinjau profil dan status website sebelum menawarkan layanan.'],
            'agency' => ['Agensi digital', 'Salon', 'layanan pengelolaan media sosial dan iklan', 'Cari bisnis lokal yang sesuai dengan layanan agensi Anda.'],
            'supplier' => ['Supplier kemasan', 'Restoran', 'kemasan makanan dan minuman', 'Temukan restoran, katering, atau kedai yang relevan.'],
            'distributor' => ['Distributor perlengkapan', 'Laundry', 'bahan dan perlengkapan operasional usaha', 'Cari calon pelanggan yang menggunakan perlengkapan Anda.'],
            'pos' => ['Aplikasi kasir / POS', 'Cafe', 'aplikasi kasir dan pencatatan penjualan', 'Tinjau kebutuhan operasional sebelum menawarkan demo.'],
            'printing' => ['Percetakan', 'Tempat kursus', 'layanan cetak banner, brosur, dan materi promosi', 'Temukan bisnis yang berpotensi membutuhkan materi promosi.'],
        ];
        $presets = [];
        foreach ($segments as $id => [$label, $keyword, $offering, $hint]) {
            $presets[$id] = [
                'label' => $label, 'keyword' => $keyword, 'offering' => $offering, 'hint' => $hint,
                'messages' => [
                    "Halo tim {{nama_bisnis}}, salam kenal. Kami menemukan profil usaha Anda di Google Maps. Kami menawarkan {$offering}. Boleh kami mengirimkan informasi singkat untuk Anda pertimbangkan?",
                    "Halo tim {{nama_bisnis}}, kami ingin memperkenalkan {$offering}. Apakah kami boleh berdiskusi mengenai kebutuhan usaha Anda agar penawaran yang kami sampaikan sesuai?",
                    "Halo tim {{nama_bisnis}}, kami ingin menindaklanjuti perkenalan sebelumnya mengenai {$offering}. Apakah ada informasi yang ingin Anda tanyakan? Jika belum dibutuhkan, silakan beri tahu kami. Terima kasih.",
                ],
            ];
        }
        return $presets;
    }
}
