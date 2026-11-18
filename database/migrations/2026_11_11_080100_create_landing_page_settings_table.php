<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('landing_page_settings', function (Blueprint $table): void {
            $table->id();

            // 1. Identitas Institusi & Header
            $table->string('university_name')->default('Universitas Bina Insan');
            $table->string('university_acronym', 20)->default('UNIVBI');
            $table->string('system_name')->default('Sistem Informasi e-Arsip Terpadu & Publikasi Kampus');
            $table->string('system_tagline')->default('Pangkalan Data Naskah Dinas Sah, Akreditasi Borang & Portal Warta Kampus');
            $table->string('university_logo_path')->nullable();
            $table->string('favicon_path')->nullable();

            // 2. Hero Section & Banner Beranda
            $table->string('hero_badge')->default('Pusat Publikasi & Arsip Resmi Kampus');
            $table->string('hero_title')->default('Satu Gerbang Naskah Resmi, Dokumentasi & Akreditasi');
            $table->text('hero_subtitle')->nullable();
            $table->string('hero_banner_path')->nullable();
            $table->string('hero_cta_primary_label')->default('Jelajahi Dokumen Publik');
            $table->string('hero_cta_primary_url')->default('#dokumen-publik');
            $table->string('hero_cta_secondary_label')->default('Verifikasi Naskah QR');
            $table->string('hero_cta_secondary_url')->default('/verify');

            // 3. Konfigurasi Seksi Dokumen Publik
            $table->string('docs_section_badge')->default('Transparansi Informasi Publik');
            $table->string('docs_section_title')->default('Daftar Naskah Dinas & Dokumen Publik');
            $table->text('docs_section_description')->nullable();
            $table->unsignedSmallInteger('docs_per_page')->default(10);

            // 4. Konfigurasi Seksi Berita & Pengumuman
            $table->string('news_section_badge')->default('Kabar & Kegiatan');
            $table->string('news_section_title')->default('Warta & Publikasi Kampus Terbaru');
            $table->text('news_section_description')->nullable();
            $table->unsignedSmallInteger('news_max_items')->default(5);
            $table->string('news_view_all_label')->default('Lihat Semua Warta Kampus');

            // 5. Kontak & Alamat Institusi (Footer)
            $table->text('campus_address')->nullable();
            $table->string('campus_phone')->nullable();
            $table->string('campus_email')->nullable();
            $table->string('campus_maps_url')->nullable();

            // 6. Media Sosial Resmi Universitas
            $table->string('social_instagram')->nullable();
            $table->string('social_youtube')->nullable();
            $table->string('social_facebook')->nullable();
            $table->string('social_linkedin')->nullable();
            $table->string('social_twitter')->nullable();

            // 7. Informasi Developer & Hak Cipta (Footer)
            $table->string('developer_name')->default('Tim Pengembang IT UNIVBI');
            $table->string('developer_email')->default('developer@univbi.ac.id');
            $table->string('developer_website')->nullable();
            $table->string('copyright_text')->default('© 2026 Universitas Bina Insan. Hak Cipta Dilindungi Undang-Undang.');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('landing_page_settings');
    }
};
