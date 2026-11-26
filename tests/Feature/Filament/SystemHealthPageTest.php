<?php

declare(strict_types=1);

namespace Tests\Feature\Filament;

use App\Data\System\SystemHealthSnapshotData;
use App\Filament\Pages\SystemHealthPage;
use App\Filament\Widgets\SystemExtensionsWidget;
use App\Filament\Widgets\SystemHealthOverviewWidget;
use App\Filament\Widgets\SystemRuntimeConfigWidget;
use App\Filament\Widgets\SystemStorageAndDiskWidget;
use App\Models\Role;
use App\Models\User;
use App\Services\System\SystemHealthService;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class SystemHealthPageTest extends TestCase
{
    use LazilyRefreshDatabase;

    private User $superAdmin;

    private User $staffUser;

    protected function setUp(): void
    {
        parent::setUp();

        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $superAdminRole = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        $staffRole = Role::firstOrCreate(['name' => 'staf_unit', 'guard_name' => 'web']);

        $this->superAdmin = User::factory()->create(['is_active' => true]);
        $this->superAdmin->assignRole($superAdminRole);

        $this->staffUser = User::factory()->create(['is_active' => true]);
        $this->staffUser->assignRole($staffRole);

        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_system_health_service_returns_memoized_snapshot_and_supports_fresh_check(): void
    {
        $service = app(SystemHealthService::class);

        $first = $service->check();
        $second = $service->check();
        $refreshed = $service->freshCheck();
        $afterRefresh = $service->check();

        $this->assertInstanceOf(SystemHealthSnapshotData::class, $first);
        $this->assertSame($first, $second);
        $this->assertNotSame($first, $refreshed);
        $this->assertSame($refreshed, $afterRefresh);

        $this->assertTrue($first->databaseHealthy);
        $this->assertTrue($first->cacheHealthy);
        $this->assertTrue($first->storageWritable);
        $this->assertNotEmpty($first->phpVersion);
        $this->assertNotEmpty($first->laravelVersion);
        $this->assertIsArray($first->requiredExtensions);
        $this->assertTrue($first->isOverallHealthy());
    }

    public function test_system_health_widgets_are_not_auto_discovered_on_main_dashboard(): void
    {
        $discoveredWidgets = Filament::getPanel('admin')->getWidgets();

        $this->assertNotContains(SystemHealthOverviewWidget::class, $discoveredWidgets);
        $this->assertNotContains(SystemStorageAndDiskWidget::class, $discoveredWidgets);
        $this->assertNotContains(SystemRuntimeConfigWidget::class, $discoveredWidgets);
        $this->assertNotContains(SystemExtensionsWidget::class, $discoveredWidgets);
    }

    public function test_super_admin_can_access_system_health_page_and_non_admin_is_forbidden(): void
    {
        $url = SystemHealthPage::getUrl();

        $this->actingAs($this->staffUser)
            ->get($url)
            ->assertForbidden();

        $this->actingAs($this->superAdmin)
            ->get($url)
            ->assertOk()
            ->assertSee('Kesehatan Sistem & Informasi Server')
            ->assertSee('Status Keseluruhan Server')
            ->assertSee('Penyimpanan & Kapasitas Disk')
            ->assertSee('Konfigurasi Runtime & PHP')
            ->assertSee('Ekstensi PHP Wajib & Modul Runtime');
    }

    public function test_system_health_page_header_action_refreshes_health_and_dispatches_event(): void
    {
        $this->actingAs($this->superAdmin);

        Livewire::test(SystemHealthPage::class)
            ->callAction('refresh_health')
            ->assertDispatched('system-health-refreshed')
            ->assertNotified('Pemeriksaan kesehatan sistem selesai');
    }

    public function test_overview_widget_supports_auto_poll_toggle_and_manual_probe(): void
    {
        $this->actingAs($this->superAdmin);

        Livewire::test(SystemHealthOverviewWidget::class)
            ->assertSet('autoPoll', false)
            ->assertSee('Auto-Poll: Mati')
            ->call('toggleAutoPoll')
            ->assertSet('autoPoll', true)
            ->assertSee('Auto-Poll: Aktif')
            ->call('probeNow')
            ->assertDispatched('system-health-refreshed')
            ->assertNotified('Probe kesehatan sistem selesai');
    }

    public function test_storage_widget_can_run_write_probe_and_renders_disk_metrics(): void
    {
        $this->actingAs($this->superAdmin);

        Livewire::test(SystemStorageAndDiskWidget::class)
            ->assertSee('Penyimpanan & Kapasitas Disk')
            ->assertSee('Uji Tulis Storage')
            ->call('testStorageWrite')
            ->assertDispatched('system-health-refreshed')
            ->assertNotified('Uji tulis storage berhasil');
    }

    public function test_runtime_config_and_extensions_widgets_render_correctly(): void
    {
        $this->actingAs($this->superAdmin);

        Livewire::test(SystemRuntimeConfigWidget::class)
            ->assertSee('Konfigurasi Runtime & PHP')
            ->assertSee('Salin JSON')
            ->assertSee('Zend OPcache');

        Livewire::test(SystemExtensionsWidget::class)
            ->assertSee('Ekstensi PHP Wajib & Modul Runtime')
            ->assertSee('openssl')
            ->assertSee('mbstring');
    }
}
