<?php

declare(strict_types=1);

namespace Tests\Feature\Filament;

use App\Filament\Resources\AuditLogs\AuditLogResource;
use App\Filament\Resources\AuditLogs\Pages\ListAuditLogs;
use App\Models\ActivityLog;
use App\Models\User;
use Filament\Tables\Enums\PaginationMode;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AuditLogResourceTest extends TestCase
{
    use RefreshDatabase;

    private User $superAdmin;

    private User $regularUser;

    protected function setUp(): void
    {
        parent::setUp();

        $roleSuperAdmin = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        $permissionViewAny = Permission::firstOrCreate(['name' => 'view_any:activity_log', 'guard_name' => 'web']);
        $permissionView = Permission::firstOrCreate(['name' => 'view:activity_log', 'guard_name' => 'web']);

        $roleSuperAdmin->givePermissionTo([$permissionViewAny, $permissionView]);

        $this->superAdmin = User::factory()->create([
            'name' => 'Super Administrator',
            'email' => 'admin@univbi.ac.id',
            'is_active' => true,
        ]);
        $this->superAdmin->assignRole($roleSuperAdmin);

        $this->regularUser = User::factory()->create([
            'name' => 'Pengguna Reguler',
            'email' => 'user@univbi.ac.id',
            'is_active' => true,
        ]);
    }

    public function test_super_admin_can_access_audit_log_page_and_see_records(): void
    {
        $log = ActivityLog::create([
            'log_name' => 'documents',
            'description' => 'Dokumen baru telah dibuat',
            'event' => 'created',
            'causer_type' => User::class,
            'causer_id' => $this->superAdmin->id,
            'properties' => ['attributes' => ['title' => 'Surat Keputusan']],
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Mozilla/5.0 Test Suite',
        ]);

        $response = $this->actingAs($this->superAdmin)->get('/admin/audit-logs');
        $response->assertSuccessful();

        Livewire::actingAs($this->superAdmin)
            ->test(ListAuditLogs::class)
            ->assertCanSeeTableRecords([$log])
            ->assertSee('Dokumen baru telah dibuat')
            ->assertSee('Pembuatan');
    }

    public function test_unauthorized_user_is_forbidden_from_accessing_audit_logs(): void
    {
        $response = $this->actingAs($this->regularUser)->get('/admin/audit-logs');
        $response->assertForbidden();
    }

    public function test_audit_log_table_filters_by_event_and_log_name(): void
    {
        $loginLog = ActivityLog::create([
            'log_name' => 'auth_audit',
            'description' => 'Pengguna berhasil masuk',
            'event' => 'login',
            'causer_type' => User::class,
            'causer_id' => $this->superAdmin->id,
            'ip_address' => '127.0.0.1',
        ]);

        $documentLog = ActivityLog::create([
            'log_name' => 'documents',
            'description' => 'Dokumen nomor 001/SK disetujui',
            'event' => 'approved',
            'causer_type' => User::class,
            'causer_id' => $this->superAdmin->id,
            'ip_address' => '127.0.0.1',
        ]);

        Livewire::actingAs($this->superAdmin)
            ->test(ListAuditLogs::class)
            ->filterTable('event', 'login')
            ->assertCanSeeTableRecords([$loginLog])
            ->assertCanNotSeeTableRecords([$documentLog])
            ->filterTable('event', 'approved')
            ->assertCanSeeTableRecords([$documentLog])
            ->assertCanNotSeeTableRecords([$loginLog]);

        Livewire::actingAs($this->superAdmin)
            ->test(ListAuditLogs::class)
            ->filterTable('log_name', 'auth_audit')
            ->assertCanSeeTableRecords([$loginLog])
            ->assertCanNotSeeTableRecords([$documentLog]);
    }

    public function test_diff_modal_view_renders_attribute_diff_correctly(): void
    {
        $log = ActivityLog::create([
            'log_name' => 'documents',
            'description' => 'Perubahan status dokumen',
            'event' => 'updated',
            'causer_type' => User::class,
            'causer_id' => $this->superAdmin->id,
            'properties' => [
                'old' => [
                    'status' => 'pending',
                    'title' => 'Judul Lama',
                ],
                'attributes' => [
                    'status' => 'approved',
                    'title' => 'Judul Baru',
                ],
            ],
            'ip_address' => '192.168.1.100',
            'user_agent' => 'Mozilla/5.0 Agent Test',
        ]);

        $view = $this->view('filament.components.audit-log-diff-modal', ['record' => $log]);

        $view->assertSee('status');
        $view->assertSee('pending');
        $view->assertSee('approved');
        $view->assertSee('Judul Lama');
        $view->assertSee('Judul Baru');
        $view->assertSee('192.168.1.100');
    }

    public function test_successful_login_and_logout_events_are_recorded_in_audit_log(): void
    {
        // Simulasi Login
        Event::dispatch(new Login('web', $this->superAdmin, false));

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'auth_audit',
            'event' => 'login',
            'causer_id' => $this->superAdmin->id,
        ]);

        $this->superAdmin->refresh();
        $this->assertNotNull($this->superAdmin->last_login_at);

        // Simulasi Logout
        Event::dispatch(new Logout('web', $this->superAdmin));

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'auth_audit',
            'event' => 'logout',
            'causer_id' => $this->superAdmin->id,
        ]);
    }

    public function test_failed_login_events_are_recorded_in_audit_log(): void
    {
        Event::dispatch(new Failed('web', null, ['email' => 'intruder@unknown.com']));

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'auth_audit',
            'event' => 'failed_login',
            'causer_id' => null,
        ]);

        $failedLog = ActivityLog::where('event', 'failed_login')->firstOrFail();
        $this->assertStringContainsString('intruder@unknown.com', $failedLog->description);
    }

    public function test_audit_log_uses_simple_pagination_eager_loads_async_causer_filter_and_indexes(): void
    {
        $otherUser = User::factory()->create([
            'name' => 'Operator Akademik',
            'email' => 'operator@univbi.ac.id',
            'is_active' => true,
        ]);

        $adminLog = ActivityLog::create([
            'log_name' => 'system_backup',
            'description' => 'Backup harian dibuat',
            'event' => 'backup_created',
            'causer_type' => User::class,
            'causer_id' => $this->superAdmin->id,
        ]);
        $adminLog->forceFill(['created_at' => now()->subDays(2)])->saveQuietly();

        $operatorLog = ActivityLog::create([
            'log_name' => 'cms_moderation',
            'description' => 'Berita baru dipublikasikan',
            'event' => 'published',
            'causer_type' => User::class,
            'causer_id' => $otherUser->id,
        ]);

        $eloquentQuery = AuditLogResource::getEloquentQuery();
        $this->assertArrayHasKey('causer', $eloquentQuery->getEagerLoads());
        $this->assertArrayHasKey('subject', $eloquentQuery->getEagerLoads());

        $component = Livewire::actingAs($this->superAdmin)
            ->test(ListAuditLogs::class);

        $table = $component->instance()->getTable();
        $this->assertSame(PaginationMode::Simple, $table->getPaginationMode());
        $this->assertSame(25, $table->getDefaultPaginationPageOption());
        $this->assertSame('500ms', $table->getSearchDebounce());

        // Filter causer_id & rentang waktu bekerja presisi
        $component
            ->filterTable('causer_id', $otherUser->id)
            ->assertCanSeeTableRecords([$operatorLog])
            ->assertCanNotSeeTableRecords([$adminLog])
            ->resetTableFilters()
            ->filterTable('created_at', [
                'from' => now()->toDateString(),
                'until' => now()->toDateString(),
            ])
            ->assertCanSeeTableRecords([$operatorLog])
            ->assertCanNotSeeTableRecords([$adminLog]);

        // Index skalabilitas tabel activity_log terpasang
        $this->assertTrue(Schema::hasIndex('activity_log', 'idx_activity_log_logname_created'));
        $this->assertTrue(Schema::hasIndex('activity_log', 'idx_activity_log_event_created'));
        $this->assertTrue(Schema::hasIndex('activity_log', 'idx_activity_log_logname_event_created'));
    }
}
