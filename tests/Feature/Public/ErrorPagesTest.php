<?php

declare(strict_types=1);

namespace Tests\Feature\Public;

use App\Models\LandingPageSetting;
use App\Support\Security\RuntimeIntegrityManifest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ErrorPagesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        LandingPageSetting::current()->update([
            'university_name' => 'Universitas Bina Insan',
            'university_acronym' => 'UNIVBI',
            'system_name' => 'Sistem Informasi e-Arsip Terpadu & Publikasi Kampus',
        ]);
    }

    public function test_404_error_page_renders_cleanly_with_institutional_brand_and_actions(): void
    {
        $response = $this->get('/halaman-acak-yang-pasti-tidak-ada-404');

        $response->assertStatus(404);
        $response->assertSee('404 - Halaman Tidak Ditemukan');
        $response->assertSee('HTTP 404 • TIDAK DITEMUKAN');
        $response->assertSee('Halaman atau Naskah Dinas Tidak Ditemukan');
        $response->assertSee('Beranda Utama');
        $response->assertSee('Cari Dokumen Publik');
        $response->assertSee('Kembali Sebelumnya');

        // Verifikasi Segel Pengembang Resmi & Hak Cipta
        $response->assertSee(RuntimeIntegrityManifest::getDeveloperName());
        $response->assertSee(RuntimeIntegrityManifest::getDeveloperEmail());
        $response->assertSee(RuntimeIntegrityManifest::getDeveloperWebsite());
        $response->assertSee(RuntimeIntegrityManifest::getCopyrightNotice());
    }

    public function test_403_error_page_renders_with_custom_message_and_staff_portal_button(): void
    {
        Route::get('/_test-forbidden-route', function () {
            abort(403, 'Akses naskah ini khusus didelegasikan kepada pimpinan fakultas.');
        });

        $response = $this->get('/_test-forbidden-route');

        $response->assertStatus(403);
        $response->assertSee('403 - Akses Dibatasi');
        $response->assertSee('HTTP 403 • AKSES DITOLAK');
        $response->assertSee('Akses Dibatasi atau Memerlukan Hak Akses Khusus');
        $response->assertSee('Akses naskah ini khusus didelegasikan kepada pimpinan fakultas.');
        $response->assertSee('Masuk Portal Staf / Admin');
        $response->assertSee('Beranda Utama');

        // Verifikasi Segel Pengembang Resmi
        $response->assertSee(RuntimeIntegrityManifest::getDeveloperName());
        $response->assertSee(RuntimeIntegrityManifest::getDeveloperEmail());
    }

    public function test_500_error_page_renders_resiliently_without_secondary_database_failure(): void
    {
        Route::get('/_test-server-error-route', function () {
            abort(500, 'Simulasi kendala server internal');
        });

        $response = $this->get('/_test-server-error-route');

        $response->assertStatus(500);
        $response->assertSee('500 - Kendala Teknis Server');
        $response->assertSee('HTTP 500 • KENDALA TEKNIS SERVER');
        $response->assertSee('Terjadi Kendala Teknis Sementara pada Server');
        $response->assertSee('Muat Ulang Halaman');
        $response->assertSee('Beranda Utama');

        // Verifikasi Segel Pengembang Resmi
        $response->assertSee(RuntimeIntegrityManifest::getDeveloperName());
        $response->assertSee(RuntimeIntegrityManifest::getDeveloperEmail());
    }

    public function test_419_error_page_renders_session_security_guidance(): void
    {
        $viewContent = view('errors.419')->render();

        $this->assertStringContainsString('419 - Sesi Formulir Berakhir', $viewContent);
        $this->assertStringContainsString('HTTP 419 • SESI KEDALUWARSA', $viewContent);
        $this->assertStringContainsString('Sesi Keamanan Formulir Telah Berakhir', $viewContent);
        $this->assertStringContainsString('Muat Ulang Halaman', $viewContent);
        $this->assertStringContainsString(RuntimeIntegrityManifest::getDeveloperName(), $viewContent);
    }

    public function test_503_error_page_renders_maintenance_status(): void
    {
        $viewContent = view('errors.503')->render();

        $this->assertStringContainsString('503 - Pemeliharaan Sistem', $viewContent);
        $this->assertStringContainsString('HTTP 503 • PEMELIHARAAN SISTEM', $viewContent);
        $this->assertStringContainsString('Sistem Sedang Dalam Pemeliharaan Berkala', $viewContent);
        $this->assertStringContainsString('Cek Status / Muat Ulang', $viewContent);
        $this->assertStringContainsString(RuntimeIntegrityManifest::getDeveloperName(), $viewContent);
    }
}
