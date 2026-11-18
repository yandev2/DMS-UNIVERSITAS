<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

class LandingPageSetting extends Model
{
    use HasFactory;

    public const CACHE_KEY = 'univbi_landing_page_settings';

    protected $guarded = ['id'];

    protected static function booted(): void
    {
        static::saved(function (): void {
            Cache::forget(self::CACHE_KEY);
        });

        static::deleted(function (): void {
            Cache::forget(self::CACHE_KEY);
        });
    }

    protected function casts(): array
    {
        return [
            'docs_per_page' => 'integer',
            'news_max_items' => 'integer',
        ];
    }

    /**
     * Ambil record pengaturan landing page aktif dari cache singleton.
     * Menggunakan serialisasi array atribut untuk mencegah __PHP_Incomplete_Class
     * dan menjamin kompatibilitas penuh antar proses CLI, FPM, dan HTTP workers.
     */
    public static function current(): self
    {
        try {
            $cachedAttributes = Cache::get(self::CACHE_KEY);

            if (is_array($cachedAttributes)) {
                $record = new static;
                $record->setRawAttributes($cachedAttributes, sync: true);
                $record->exists = true;

                return $record;
            }
        } catch (\Throwable) {
            // Abaikan kegagalan de-serialisasi cache lama
        }

        Cache::forget(self::CACHE_KEY);

        $record = static::query()->first() ?? static::createDefault();

        Cache::rememberForever(self::CACHE_KEY, fn (): array => $record->getAttributes());

        return $record;
    }

    /**
     * Buat record default jika pangkalan data belum memiliki data pengaturan.
     */
    public static function createDefault(): self
    {
        return static::create([
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
            'developer_name' => 'Tim Pengembang IT UNIVBI',
            'developer_email' => 'developer@univbi.ac.id',
            'developer_website' => 'https://univbi.ac.id',
            'copyright_text' => '© '.date('Y').' Universitas Bina Insan. Hak Cipta Dilindungi Undang-Undang.',
        ]);
    }

    /**
     * Mendapatkan URL gambar logo universitas jika file fisik tersedia di disk storage.
     */
    public function getLogoUrl(): ?string
    {
        if (filled($this->university_logo_path) && Storage::disk('public')->exists($this->university_logo_path)) {
            return Storage::disk('public')->url($this->university_logo_path);
        }

        return null;
    }

    /**
     * Mendapatkan URL favicon tab browser dari CMS, dengan fallback ke logo universitas jika tersedia.
     */
    public function getFaviconUrl(): ?string
    {
        if (filled($this->favicon_path) && Storage::disk('public')->exists($this->favicon_path)) {
            return Storage::disk('public')->url($this->favicon_path);
        }

        return $this->getLogoUrl();
    }

    /**
     * Mendapatkan URL gambar banner hero jika file fisik tersedia di disk storage.
     */
    public function getBannerUrl(): ?string
    {
        if (filled($this->hero_banner_path) && Storage::disk('public')->exists($this->hero_banner_path)) {
            return Storage::disk('public')->url($this->hero_banner_path);
        }

        return null;
    }
}
