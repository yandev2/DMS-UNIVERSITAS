<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Document;
use App\Models\User;
use App\Support\Security\RuntimeIntegrityManifest;
use Database\Seeders\MasterDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UniversalFooterTest extends TestCase
{
    use RefreshDatabase;

    private User $superAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(MasterDataSeeder::class);

        $this->superAdmin = User::where('email', 'admin@univbi.ac.id')->firstOrFail();
    }

    public function test_runtime_integrity_manifest_resolves_developer_descriptor_correctly(): void
    {
        $this->assertTrue(RuntimeIntegrityManifest::verifyIntegrity());

        $this->assertSame('RIAN DINATA', RuntimeIntegrityManifest::getDeveloperName());
        $this->assertSame('ryanxxjr@gmail.com', RuntimeIntegrityManifest::getDeveloperEmail());
        $this->assertSame('https://ryandev.cloud', RuntimeIntegrityManifest::getDeveloperWebsite());
        $this->assertSame('© 2026 Universitas Bina Insan. Hak Cipta Dilindungi Undang-Undang.', RuntimeIntegrityManifest::getCopyrightNotice());

        $descriptor = RuntimeIntegrityManifest::getDescriptor();
        $this->assertIsArray($descriptor);
        $this->assertSame('RIAN DINATA', $descriptor['name']);
        $this->assertSame('ryanxxjr@gmail.com', $descriptor['email']);
        $this->assertSame('https://ryandev.cloud', $descriptor['website']);
    }

    public function test_public_pages_render_obfuscated_patent_footer(): void
    {
        // 1. Landing Page Home
        $responseHome = $this->get('/');
        $responseHome->assertSuccessful();
        $responseHome->assertSee('RIAN DINATA');
        $responseHome->assertSee('ryanxxjr@gmail.com');
        $responseHome->assertSee('https://ryandev.cloud');
        $responseHome->assertSee('© 2026 Universitas Bina Insan. Hak Cipta Dilindungi Undang-Undang.');

        // 2. Portal Warta Berita
        $responseNews = $this->get('/berita');
        $responseNews->assertSuccessful();
        $responseNews->assertSee('RIAN DINATA');
        $responseNews->assertSee('ryanxxjr@gmail.com');
        $responseNews->assertSee('https://ryandev.cloud');
        $responseNews->assertSee('© 2026 Universitas Bina Insan. Hak Cipta Dilindungi Undang-Undang.');

        // 3. Halaman Verifikasi Dokumen (Invalid UUID)
        $responseVerifyInvalid = $this->get('/verify/invalid-test-uuid');
        $responseVerifyInvalid->assertSee('RIAN DINATA');
        $responseVerifyInvalid->assertSee('ryanxxjr@gmail.com');
        $responseVerifyInvalid->assertSee('https://ryandev.cloud');
        $responseVerifyInvalid->assertSee('© 2026 Universitas Bina Insan. Hak Cipta Dilindungi Undang-Undang.');

        // 4. Halaman Verifikasi Dokumen Sah (Valid Document)
        $doc = Document::first();
        if ($doc) {
            $responseVerify = $this->get("/verify/{$doc->uuid}");
            $responseVerify->assertSuccessful();
            $responseVerify->assertSee('RIAN DINATA');
            $responseVerify->assertSee('ryanxxjr@gmail.com');
            $responseVerify->assertSee('https://ryandev.cloud');
            $responseVerify->assertSee('© 2026 Universitas Bina Insan. Hak Cipta Dilindungi Undang-Undang.');
        }
    }

    public function test_filament_admin_panel_renders_universal_footer_on_pages(): void
    {
        // 1. Halaman Masuk Panel Admin (Login Page - Tamu / Guest)
        $responseLogin = $this->get('/admin/login');
        $responseLogin->assertSuccessful();
        $responseLogin->assertSee('RIAN DINATA');
        $responseLogin->assertSee('ryanxxjr@gmail.com');
        $responseLogin->assertSee('https://ryandev.cloud');
        $responseLogin->assertSee('© 2026 Universitas Bina Insan. Hak Cipta Dilindungi Undang-Undang.');

        // 2. Halaman Dasbor Admin
        $responseDashboard = $this->actingAs($this->superAdmin)->get('/admin');
        $responseDashboard->assertSuccessful();
        $responseDashboard->assertSee('RIAN DINATA');
        $responseDashboard->assertSee('ryanxxjr@gmail.com');
        $responseDashboard->assertSee('https://ryandev.cloud');
        $responseDashboard->assertSee('© 2026 Universitas Bina Insan. Hak Cipta Dilindungi Undang-Undang.');

        // 3. Halaman Kelola Naskah Dinas (Resource Table)
        $responseDocs = $this->actingAs($this->superAdmin)->get('/admin/documents');
        $responseDocs->assertSuccessful();
        $responseDocs->assertSee('RIAN DINATA');
        $responseDocs->assertSee('ryanxxjr@gmail.com');
        $responseDocs->assertSee('https://ryandev.cloud');
        $responseDocs->assertSee('© 2026 Universitas Bina Insan. Hak Cipta Dilindungi Undang-Undang.');
    }

    public function test_codebase_contains_zero_plaintext_developer_strings_in_app_and_resources(): void
    {
        $patterns = [
            'RIAN DINATA',
            'ryanxxjr@gmail.com',
            'ryandev.cloud',
        ];

        $pathsToScan = [
            app_path(),
            resource_path('views'),
        ];

        foreach ($pathsToScan as $scanPath) {
            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($scanPath));
            /** @var \SplFileInfo $file */
            foreach ($iterator as $file) {
                if ($file->isDir() || ! in_array($file->getExtension(), ['php', 'blade', 'json'])) {
                    continue;
                }

                $content = file_get_contents($file->getRealPath());

                foreach ($patterns as $pattern) {
                    $this->assertStringNotContainsString(
                        $pattern,
                        $content,
                        "Found plaintext developer string '{$pattern}' in file: ".$file->getRealPath()
                    );
                }
            }
        }
    }
}
