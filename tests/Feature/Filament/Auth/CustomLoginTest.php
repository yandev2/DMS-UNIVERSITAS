<?php

declare(strict_types=1);

namespace Tests\Feature\Filament\Auth;

use App\Filament\Pages\Auth\Login;
use App\Models\LandingPageSetting;
use App\Models\User;
use App\Support\Security\RuntimeIntegrityManifest;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class CustomLoginTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_login_page_renders_successfully_with_custom_split_layout_and_univbi_branding(): void
    {
        $response = $this->get('/admin/login');

        $response->assertSuccessful();

        // Verify University Branding & Hero Elements
        $response->assertSee('Universitas Bina Insan');
        $response->assertSee('UNIVBI');
        $response->assertSee('Sistem E-Arsip & Bank Data Borang', false);
        $response->assertSee('Selamat Datang di');
        $response->assertSee('Archiver-Sistem');
        $response->assertDontSee('E-Arsip & Borang UNBI');

        // Verify Trust Badges replacing "Login as"
        $response->assertSee('TTE Digital Sah');
        $response->assertSee('Standar 9 Kriteria');
        $response->assertSee('Audit Trail Aktif');

        // Verify Custom Form Inputs & Action Button
        $response->assertSee('Alamat Email atau NIDN/NIP');
        $response->assertSee('Kata Sandi');
        $response->assertSee('Masuk ke Sistem');

        // Verify Tailwind CSS compilation and styling assets are injected
        $response->assertSee('https://cdn.tailwindcss.com');
        $response->assertSee('custom-login-container');
        $response->assertSee('custom-login-hero');
        $response->assertSee('custom-login-card');

        // Verify Developer Seal and Copyright Notice
        $response->assertSee(RuntimeIntegrityManifest::getDeveloperName());
        $response->assertSee(RuntimeIntegrityManifest::getDeveloperEmail());
        $response->assertSee(RuntimeIntegrityManifest::getDeveloperWebsite());
        $response->assertSee(RuntimeIntegrityManifest::getCopyrightNotice());

        // Verify Developer Seal and Copyright Notice
        $response->assertSee(RuntimeIntegrityManifest::getDeveloperName());
        $response->assertSee(RuntimeIntegrityManifest::getDeveloperEmail());
        $response->assertSee(RuntimeIntegrityManifest::getDeveloperWebsite());
        $response->assertSee(RuntimeIntegrityManifest::getCopyrightNotice());
    }

    public function test_login_page_displays_cms_logo_when_available_and_fallback_monogram_when_not(): void
    {
        // 1. Without logo configured: fallback monogram UNIVBI is rendered
        $response = $this->get('/admin/login');
        $response->assertSuccessful();
        $response->assertSee('UNIVBI');

        // 2. With logo uploaded via CMS and exists in public storage
        Storage::fake('public');
        $file = UploadedFile::fake()->image('kampus-logo.png', 200, 200);
        $path = $file->store('cms/logos', 'public');

        $setting = LandingPageSetting::current();
        $setting->update(['university_logo_path' => $path]);
        Cache::forget(LandingPageSetting::CACHE_KEY);

        $responseWithLogo = $this->get('/admin/login');
        $responseWithLogo->assertSuccessful();
        $responseWithLogo->assertSee(Storage::disk('public')->url($path));
        $responseWithLogo->assertSee('Archiver-Sistem');
        $responseWithLogo->assertDontSee('E-Arsip & Borang UNBI');
    }

    public function test_login_page_does_not_contain_login_as_or_social_logins(): void
    {
        $response = $this->get('/admin/login');

        $response->assertSuccessful();

        // Explicitly assert absence of excluded elements requested by user
        $response->assertDontSee('Login as');
        $response->assertDontSee('Sign in with Google');
        $response->assertDontSee('Sign in with Facebook');
        $response->assertDontSee('Sign in with Apple');
        $response->assertDontSee('Sign in with GitHub');
        $response->assertDontSee('No Account?');
    }

    public function test_user_can_authenticate_via_email(): void
    {
        $user = User::factory()->create([
            'email' => 'dosen.teladan@univbi.ac.id',
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);

        Livewire::test(Login::class)
            ->fillForm([
                'email' => 'dosen.teladan@univbi.ac.id',
                'password' => 'password123',
            ])
            ->call('authenticate')
            ->assertHasNoFormErrors()
            ->assertRedirect(Filament::getPanel('admin')->getUrl());

        $this->assertAuthenticatedAs($user);
    }

    public function test_user_can_authenticate_via_nidn_nip(): void
    {
        $user = User::factory()->create([
            'email' => 'pejabat.kampus@univbi.ac.id',
            'nidn_nip' => '198701012015041002',
            'password' => Hash::make('rahasia_nidn_nip'),
            'is_active' => true,
        ]);

        Livewire::test(Login::class)
            ->fillForm([
                'email' => '198701012015041002',
                'password' => 'rahasia_nidn_nip',
            ])
            ->call('authenticate')
            ->assertHasNoFormErrors()
            ->assertRedirect(Filament::getPanel('admin')->getUrl());

        $this->assertAuthenticatedAs($user);
    }

    public function test_user_cannot_authenticate_with_invalid_password(): void
    {
        User::factory()->create([
            'email' => 'pegawai.keuangan@univbi.ac.id',
            'password' => Hash::make('password_yang_benar'),
            'is_active' => true,
        ]);

        Livewire::test(Login::class)
            ->fillForm([
                'email' => 'pegawai.keuangan@univbi.ac.id',
                'password' => 'password_salah_total',
            ])
            ->call('authenticate')
            ->assertHasFormErrors(['email']);

        $this->assertGuest();
    }

    public function test_inactive_user_cannot_authenticate(): void
    {
        User::factory()->create([
            'email' => 'nonaktif@univbi.ac.id',
            'password' => Hash::make('password123'),
            'is_active' => false,
        ]);

        Livewire::test(Login::class)
            ->fillForm([
                'email' => 'nonaktif@univbi.ac.id',
                'password' => 'password123',
            ])
            ->call('authenticate')
            ->assertHasFormErrors(['email']);

        $this->assertGuest();
    }

    public function test_authenticated_user_visiting_login_page_is_redirected_to_dashboard(): void
    {
        $user = User::factory()->create([
            'is_active' => true,
        ]);

        $this->actingAs($user);

        $response = $this->get('/admin/login');

        $response->assertRedirect(Filament::getPanel('admin')->getUrl());
    }
}
