<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\LandingPageSetting;
use Illuminate\Database\Seeder;

class LandingPageSettingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        LandingPageSetting::updateOrCreate(
            ['id' => 1],
            [
                'university_name' => 'Universitas Bina Insan',
                'university_acronym' => 'UNIVBI',
                'system_name' => 'Sistem Informasi e-Arsip Terpadu & Publikasi Kampus',
                'system_tagline' => 'Pangkalan Data Naskah Dinas Sah, Akreditasi Borang & Portal Warta Kampus',
                'hero_badge' => 'Pusat Publikasi & Arsip Resmi Kampus',
                'hero_title' => 'Satu Gerbang Naskah Resmi, Dokumentasi & Akreditasi',
                'hero_subtitle' => 'Layanan terpadu penelusuran naskah dinas bertaraf akses publik, verifikasi keabsahan dokumen ber-QR Code, serta warta kegiatan sivitas akademika Universitas Bina Insan.',
                'hero_cta_primary_label' => 'Jelajahi Dokumen Publik',
                'hero_cta_primary_url' => '#dokumen-publik',
                'hero_cta_secondary_label' => 'Warta & Kegiatan Kampus',
                'hero_cta_secondary_url' => '/berita',
                'docs_section_badge' => 'Transparansi Informasi Publik',
                'docs_section_title' => 'Daftar Naskah Dinas & Dokumen Publik',
                'docs_section_description' => 'Telusuri surat keputusan rektor, surat edaran, panduan akademik, dan naskah dinas institusi yang sah dan terbuka untuk umum.',
                'docs_per_page' => 10,
                'news_section_badge' => 'Kabar & Kegiatan',
                'news_section_title' => 'Warta & Publikasi Kampus Terbaru',
                'news_section_description' => 'Liputan kegiatan akademik, kerja sama strategis, prestasi mahasiswa, dan pengumuman resmi Universitas Bina Insan.',
                'news_max_items' => 5,
                'news_view_all_label' => 'Lihat Semua Warta Kampus',
                'campus_address' => 'Jl. Jend. Besar Dr. A.H. Nasution No. 298, Lubuklinggau, Sumatera Selatan 31626',
                'campus_phone' => '(0733) 322442',
                'campus_email' => 'humas@univbi.ac.id',
                'campus_maps_url' => 'https://maps.google.com',
                'social_instagram' => 'https://instagram.com/univbi_official',
                'social_youtube' => 'https://youtube.com/@univbi_official',
                'social_facebook' => 'https://facebook.com/univbi.official',
                'social_linkedin' => 'https://linkedin.com/school/universitas-bina-insan',
                'developer_name' => 'Tim Pengembang IT & Pangkalan Data UNIVBI',
                'developer_email' => 'it-dev@univbi.ac.id',
                'developer_website' => 'https://univbi.ac.id',
                'copyright_text' => '© '.date('Y').' Universitas Bina Insan. Hak Cipta Dilindungi Undang-Undang.',
            ]
        );
    }
}
