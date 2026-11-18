<?php

namespace Tests\Feature;

use App\Enums\UnitType;
use App\Filament\Resources\Units\Pages\ListUnits;
use App\Filament\Resources\Units\Pages\TrashUnits;
use App\Filament\Resources\Units\Tables\UnitsTable;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Filament\Resources\Users\Tables\UsersTable;
use App\Models\Unit;
use App\Models\User;
use Database\Seeders\DummyTestingSeeder;
use Database\Seeders\MasterDataSeeder;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SoftDeleteTrashPageTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_trashed_filter_is_not_present_in_units_and_users_tables(): void
    {
        $this->seed(MasterDataSeeder::class);

        $superAdmin = User::where('email', 'admin@univbi.ac.id')->firstOrFail();
        $this->actingAs($superAdmin);

        $unitsTable = UnitsTable::configure(Table::make(new ListUnits));
        $usersTable = UsersTable::configure(Table::make(new ListUsers));

        foreach ($unitsTable->getFilters() as $filter) {
            $this->assertNotInstanceOf(TrashedFilter::class, $filter);
        }

        foreach ($usersTable->getFilters() as $filter) {
            $this->assertNotInstanceOf(TrashedFilter::class, $filter);
        }
    }

    public function test_super_admin_can_access_trash_pages_for_units_and_users(): void
    {
        $this->seed(MasterDataSeeder::class);

        $superAdmin = User::where('email', 'admin@univbi.ac.id')->firstOrFail();
        $this->actingAs($superAdmin);

        $this->get('/admin/units/trash')
            ->assertOk()
            ->assertSeeText('Keranjang Sampah');

        $this->get('/admin/users/trash')
            ->assertOk()
            ->assertSeeText('Keranjang Sampah');
    }

    public function test_trash_action_is_configured_on_list_pages_header_actions(): void
    {
        $this->seed(MasterDataSeeder::class);

        $superAdmin = User::where('email', 'admin@univbi.ac.id')->firstOrFail();
        $this->actingAs($superAdmin);

        Livewire::test(ListUnits::class)
            ->assertActionExists('trash')
            ->assertActionVisible('trash');

        Livewire::test(ListUsers::class)
            ->assertActionExists('trash')
            ->assertActionVisible('trash');
    }

    public function test_trash_page_only_renders_soft_deleted_records(): void
    {
        $this->seed(MasterDataSeeder::class);

        $superAdmin = User::where('email', 'admin@univbi.ac.id')->firstOrFail();
        $this->actingAs($superAdmin);

        $activeUnit = Unit::where('code', 'FIK')->firstOrFail();

        $trashedUnit = Unit::create([
            'name' => 'Unit Terhapus Sementara',
            'code' => 'UNIT_TRASH_1',
            'type' => UnitType::UnitPendukung,
            'is_active' => true,
        ]);
        $trashedUnit->delete();

        $this->assertSoftDeleted('units', ['id' => $trashedUnit->id]);

        Livewire::test(TrashUnits::class)
            ->assertCanSeeTableRecords([$trashedUnit])
            ->assertCanNotSeeTableRecords([$activeUnit]);
    }

    public function test_restore_action_on_trash_page_restores_soft_deleted_record(): void
    {
        $this->seed(MasterDataSeeder::class);

        $superAdmin = User::where('email', 'admin@univbi.ac.id')->firstOrFail();
        $this->actingAs($superAdmin);

        $unit = Unit::create([
            'name' => 'Unit Akan Dipulihkan',
            'code' => 'UNIT_RESTORE',
            'type' => UnitType::UnitPendukung,
            'is_active' => true,
        ]);
        $unit->delete();

        $this->assertSoftDeleted('units', ['id' => $unit->id]);

        Livewire::test(TrashUnits::class)
            ->callTableAction('restore', $unit);

        $unit->refresh();
        $this->assertNull($unit->deleted_at);
        $this->assertDatabaseHas('units', [
            'id' => $unit->id,
            'deleted_at' => null,
        ]);
    }

    public function test_force_delete_action_on_trash_page_is_blocked_by_deletion_guard_when_dependent_records_exist(): void
    {
        $this->seed([
            MasterDataSeeder::class,
            DummyTestingSeeder::class,
        ]);

        $superAdmin = User::where('email', 'admin@univbi.ac.id')->firstOrFail();
        $this->actingAs($superAdmin);

        $unitFik = Unit::where('code', 'FIK')->firstOrFail();

        // Bypass booted deleting hook to simulate record that was already soft-deleted in past
        Unit::withoutEvents(function () use ($unitFik): void {
            $unitFik->delete();
        });

        $this->assertSoftDeleted('units', ['id' => $unitFik->id]);

        // Force delete should be intercepted by DeletionGuard and cancelled with notification
        Livewire::test(TrashUnits::class)
            ->callTableAction('forceDelete', $unitFik)
            ->assertNotified();

        $this->assertDatabaseHas('units', [
            'id' => $unitFik->id,
        ]);
    }

    public function test_force_delete_action_on_trash_page_purges_standalone_record(): void
    {
        $this->seed(MasterDataSeeder::class);

        $superAdmin = User::where('email', 'admin@univbi.ac.id')->firstOrFail();
        $this->actingAs($superAdmin);

        $standaloneUnit = Unit::create([
            'name' => 'Unit Bersih Siap Purge',
            'code' => 'UNIT_PURGE',
            'type' => UnitType::UnitPendukung,
            'is_active' => true,
        ]);
        $standaloneUnit->delete();

        $this->assertSoftDeleted('units', ['id' => $standaloneUnit->id]);

        Livewire::test(TrashUnits::class)
            ->callTableAction('forceDelete', $standaloneUnit);

        $this->assertDatabaseMissing('units', [
            'id' => $standaloneUnit->id,
        ]);
    }

    public function test_bulk_restore_and_bulk_force_delete_on_trash_page(): void
    {
        $this->seed(MasterDataSeeder::class);

        $superAdmin = User::where('email', 'admin@univbi.ac.id')->firstOrFail();
        $this->actingAs($superAdmin);

        $unitA = Unit::create([
            'name' => 'Unit Bulk Restore A',
            'code' => 'BULK_A',
            'type' => UnitType::UnitPendukung,
            'is_active' => true,
        ]);
        $unitA->delete();

        $unitB = Unit::create([
            'name' => 'Unit Bulk Purge B',
            'code' => 'BULK_B',
            'type' => UnitType::UnitPendukung,
            'is_active' => true,
        ]);
        $unitB->delete();

        // Bulk restore unitA
        Livewire::test(TrashUnits::class)
            ->callTableBulkAction('restore', [$unitA->id]);

        $this->assertDatabaseHas('units', [
            'id' => $unitA->id,
            'deleted_at' => null,
        ]);

        // Bulk force delete unitB
        Livewire::test(TrashUnits::class)
            ->callTableBulkAction('forceDelete', [$unitB->id]);

        $this->assertDatabaseMissing('units', [
            'id' => $unitB->id,
        ]);
    }
}
