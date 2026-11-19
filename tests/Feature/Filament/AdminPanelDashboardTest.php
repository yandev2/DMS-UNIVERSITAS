<?php

namespace Tests\Feature\Filament;

use App\Enums\DegreeLevel;
use App\Enums\UnitType;
use App\Models\LandingPageSetting;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Unit;
use App\Models\User;
use BezhanSalleh\FilamentShield\Resources\Roles\RoleResource;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class AdminPanelDashboardTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_unauthenticated_visitor_is_redirected_to_admin_login_page(): void
    {
        $response = $this->get('/admin');

        $response->assertRedirect('/admin/login');
    }

    public function test_renders_dashboard_with_logged_in_user_unit_role_and_both_custom_and_shield_permissions(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $unit = Unit::create([
            'code' => 'TI',
            'name' => 'Program Studi S1 Informatika',
            'slug' => 'prodi-s1-informatika',
            'type' => UnitType::Prodi,
            'degree_level' => DegreeLevel::S1,
            'is_active' => true,
        ]);

        $widgetPermission = Permission::findOrCreate('view:dashboard_welcome_banner_widget', 'web');
        $viewAnyDocPermission = Permission::findOrCreate('view_any:document', 'web');
        $createDocPermission = Permission::findOrCreate('create:document', 'web');
        $approvePermission = Permission::findOrCreate('document.approve', 'web');
        $borangPermission = Permission::findOrCreate('borang.export_excel', 'web');

        $role = Role::findOrCreate('kaprodi', 'web');
        $role->givePermissionTo([
            $widgetPermission,
            $viewAnyDocPermission,
            $createDocPermission,
            $approvePermission,
            $borangPermission,
        ]);

        $user = User::factory()->create([
            'name' => 'Dr. Budi Santoso, M.Kom.',
            'email' => 'kaprodi.ti@univbi.ac.id',
            'nidn_nip' => '0211088901',
            'position_title' => 'Ketua Program Studi S1 Informatika',
            'unit_id' => $unit->id,
            'is_active' => true,
        ]);
        $user->assignRole($role);

        $response = $this->actingAs($user)->get('/admin');

        $response->assertOk();
        $response->assertSeeText('Selamat Datang');
        $response->assertSeeText('Dr. Budi Santoso, M.Kom.');
        $response->assertSeeText('0211088901');
        $response->assertSeeText('Ketua Program Studi S1 Informatika');
        $response->assertSeeText('Program Studi S1 Informatika');
        $response->assertSeeText('Kaprodi');
        $response->assertSeeText('Waktu Sistem');
    }

    public function test_allows_super_admin_and_forbids_unauthorized_role_on_shield_role_resource(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $viewAnyRolePermission = Permission::findOrCreate('view_any:role', 'web');
        $viewRolePermission = Permission::findOrCreate('view:role', 'web');

        $superAdminRole = Role::findOrCreate('super_admin', 'web');
        $superAdminRole->givePermissionTo([$viewAnyRolePermission, $viewRolePermission]);

        $kaprodiRole = Role::findOrCreate('kaprodi', 'web');

        $superAdmin = User::factory()->create(['is_active' => true]);
        $superAdmin->assignRole($superAdminRole);

        $kaprodi = User::factory()->create(['is_active' => true]);
        $kaprodi->assignRole($kaprodiRole);

        $this->actingAs($superAdmin)
            ->get('/admin/shield/roles')
            ->assertOk();

        $this->actingAs($kaprodi)
            ->get('/admin/shield/roles')
            ->assertForbidden();
    }

    public function test_shield_role_resource_belongs_to_manajemen_akses_dan_pengguna_navigation_group(): void
    {
        $this->assertSame(
            'Manajemen Akses & Pengguna',
            RoleResource::getNavigationGroup()
        );
        $this->assertSame(
            2,
            RoleResource::getNavigationSort()
        );

        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $viewAnyRolePermission = Permission::findOrCreate('view_any:role', 'web');
        $superAdminRole = Role::findOrCreate('super_admin', 'web');
        $superAdminRole->givePermissionTo($viewAnyRolePermission);

        $superAdmin = User::factory()->create(['is_active' => true]);
        $superAdmin->assignRole($superAdminRole);

        $response = $this->actingAs($superAdmin)->get('/admin');
        $response->assertOk();
        $response->assertSeeText('Manajemen Akses & Pengguna');
        $response->assertSeeText('Peran');
        $response->assertDontSeeText('Filament Shield');
    }

    public function test_forbids_inactive_user_from_accessing_admin_panel(): void
    {
        $inactiveUser = User::factory()->create([
            'name' => 'Akun Nonaktif',
            'email' => 'nonaktif@univbi.ac.id',
            'is_active' => false,
        ]);

        $response = $this->actingAs($inactiveUser)->get('/admin');

        $response->assertForbidden();
    }

    public function test_admin_panel_has_collapsible_sidebar_on_desktop_and_archiver_sistem_branding(): void
    {
        $panel = filament()->getPanel('admin');

        $this->assertTrue($panel->isSidebarCollapsibleOnDesktop());
        $this->assertSame('Archiver-Sistem', $panel->getBrandName());

        $superAdminRole = Role::findOrCreate('super_admin', 'web');
        $superAdmin = User::factory()->create(['is_active' => true]);
        $superAdmin->assignRole($superAdminRole);

        $response = $this->actingAs($superAdmin)->get('/admin');

        $response->assertOk();
        $response->assertSee('Archiver-Sistem');
    }

    public function test_admin_panel_displays_cms_logo_when_present_and_hides_image_when_null(): void
    {
        Storage::fake('public');
        Cache::forget(LandingPageSetting::CACHE_KEY);

        $setting = LandingPageSetting::current();
        $setting->update(['university_logo_path' => null]);
        Cache::forget(LandingPageSetting::CACHE_KEY);

        $superAdminRole = Role::findOrCreate('super_admin', 'web');
        $superAdmin = User::factory()->create(['is_active' => true]);
        $superAdmin->assignRole($superAdminRole);

        // 1. When logo is null, fallback hidden (no <img class="fi-custom-brand-logo...">)
        $responseWithoutLogo = $this->actingAs($superAdmin)->get('/admin');
        $responseWithoutLogo->assertOk();
        $responseWithoutLogo->assertSee('Archiver-Sistem');
        $responseWithoutLogo->assertDontSee('class="fi-custom-brand-logo', false);

        // 2. When logo is uploaded in CMS, img with url is rendered
        $file = UploadedFile::fake()->image('univbi-logo.png', 120, 120);
        $path = $file->store('cms/logos', 'public');
        $setting->update(['university_logo_path' => $path]);
        Cache::forget(LandingPageSetting::CACHE_KEY);

        $responseWithLogo = $this->actingAs($superAdmin)->get('/admin');
        $responseWithLogo->assertOk();
        $responseWithLogo->assertSee(Storage::disk('public')->url($path));
        $responseWithLogo->assertSee('class="fi-custom-brand-logo', false);
        $responseWithLogo->assertSee('Archiver-Sistem');
    }

    public function test_admin_panel_uses_cms_logo_or_favicon_for_browser_icon_with_laravel_fallback(): void
    {
        Storage::fake('public');
        Cache::forget(LandingPageSetting::CACHE_KEY);

        $setting = LandingPageSetting::current();
        $setting->update([
            'university_logo_path' => null,
            'favicon_path' => null,
        ]);
        Cache::forget(LandingPageSetting::CACHE_KEY);

        $superAdminRole = Role::findOrCreate('super_admin', 'web');
        $superAdmin = User::factory()->create(['is_active' => true]);
        $superAdmin->assignRole($superAdminRole);

        // 1. Both null -> falls back to Laravel default favicon.ico
        $fallbackResponse = $this->actingAs($superAdmin)->get('/admin');
        $fallbackResponse->assertOk();
        $fallbackResponse->assertSee('<link rel="icon" href="'.asset('favicon.ico').'" />', false);

        // 2. Only CMS university_logo_path set -> uses CMS logo URL as favicon
        $logoPath = UploadedFile::fake()->image('univbi-logo.png', 120, 120)->store('cms/logos', 'public');
        $setting->update(['university_logo_path' => $logoPath]);
        Cache::forget(LandingPageSetting::CACHE_KEY);

        $logoResponse = $this->actingAs($superAdmin)->get('/admin');
        $logoResponse->assertOk();
        $logoResponse->assertSee('<link rel="icon" href="'.Storage::disk('public')->url($logoPath).'" />', false);

        // 3. Dedicated CMS favicon_path set -> takes priority over logo
        $faviconPath = UploadedFile::fake()->image('univbi-favicon.png', 64, 64)->store('cms/favicons', 'public');
        $setting->update(['favicon_path' => $faviconPath]);
        Cache::forget(LandingPageSetting::CACHE_KEY);

        $faviconResponse = $this->actingAs($superAdmin)->get('/admin');
        $faviconResponse->assertOk();
        $faviconResponse->assertSee('<link rel="icon" href="'.Storage::disk('public')->url($faviconPath).'" />', false);
    }
}
