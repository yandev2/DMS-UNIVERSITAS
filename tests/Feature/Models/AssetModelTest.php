<?php

namespace Tests\Feature\Models;

use App\Enums\AssetCondition;
use App\Enums\AssetEventType;
use App\Enums\AssetMaintenanceStatus;
use App\Enums\AssetMaintenanceType;
use App\Enums\AssetOwnership;
use App\Enums\AssetStatus;
use App\Enums\DegreeLevel;
use App\Enums\UnitType;
use App\Models\ActivityLog;
use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\AssetLocation;
use App\Models\AssetMaintenance;
use App\Models\AssetMovement;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class AssetModelTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_creates_fixed_asset_with_uuid_and_tracks_movement_and_maintenance_history(): void
    {
        $unitIf = Unit::create([
            'code' => 'IF',
            'name' => 'Prodi Informatika',
            'slug' => 'prodi-informatika',
            'type' => UnitType::Prodi,
            'degree_level' => DegreeLevel::S1,
            'is_active' => true,
        ]);

        $unitSi = Unit::create([
            'code' => 'SI',
            'name' => 'Prodi Sistem Informasi',
            'slug' => 'prodi-sistem-informasi',
            'type' => UnitType::Prodi,
            'degree_level' => DegreeLevel::S1,
            'is_active' => true,
        ]);

        $user = User::factory()->create(['unit_id' => $unitIf->id]);

        $category = AssetCategory::create([
            'code' => 'IT',
            'name' => 'Perangkat IT & Komputer',
            'useful_life_years' => 5,
            'maintenance_interval_days' => 180,
            'is_it_asset' => true,
            'is_active' => true,
        ]);

        $location = AssetLocation::create([
            'unit_id' => $unitIf->id,
            'code' => 'GD-A-201',
            'name' => 'Lab Komputer 1',
            'building_name' => 'Gedung A',
            'floor' => 'Lantai 2',
            'pic_user_id' => $user->id,
            'is_active' => true,
        ]);

        $asset = Asset::create([
            'asset_code' => 'INV/UNBI/IF/IT/2026/0001',
            'sequence_number' => 1,
            'category_id' => $category->id,
            'initial_unit_id' => $unitIf->id,
            'current_unit_id' => $unitIf->id,
            'current_location_id' => $location->id,
            'assigned_to_user_id' => $user->id,
            'name' => 'PC Workstation Lab AI #01',
            'brand' => 'Dell',
            'model_name' => 'Precision 3680',
            'serial_number' => 'SN-DELL-0001',
            'status' => AssetStatus::InUse,
            'condition' => AssetCondition::Good,
            'ownership_status' => AssetOwnership::Owned,
            'acquisition_year' => 2026,
            'acquisition_date' => '2026-01-15',
            'acquisition_cost' => '18500000.00',
            'created_by' => $user->id,
        ]);

        AssetMovement::create([
            'asset_id' => $asset->id,
            'event_type' => AssetEventType::InitialPlacement,
            'to_unit_id' => $unitIf->id,
            'to_location_id' => $location->id,
            'new_status' => AssetStatus::InUse,
            'new_condition' => AssetCondition::Good,
            'recorded_by' => $user->id,
            'event_date' => '2026-01-15 09:00:00',
        ]);

        AssetMovement::create([
            'asset_id' => $asset->id,
            'event_type' => AssetEventType::Borrow,
            'from_unit_id' => $unitIf->id,
            'to_unit_id' => $unitSi->id,
            'previous_status' => AssetStatus::InUse,
            'new_status' => AssetStatus::Borrowed,
            'previous_condition' => AssetCondition::Good,
            'new_condition' => AssetCondition::Good,
            'recorded_by' => $user->id,
            'event_date' => '2026-06-10 10:00:00',
        ]);

        AssetMaintenance::create([
            'asset_id' => $asset->id,
            'unit_id' => $unitIf->id,
            'reported_by' => $user->id,
            'handled_by' => $user->id,
            'maintenance_type' => AssetMaintenanceType::RoutineService,
            'issue_description' => 'Pembersihan thermal paste & fan',
            'action_taken' => 'Repaste thermal grizzly & cleaning dust filter',
            'reported_date' => '2026-07-01 08:00:00',
            'completed_date' => '2026-07-01',
            'cost' => '150000.00',
            'status' => AssetMaintenanceStatus::Completed,
            'condition_after' => AssetCondition::Good,
        ]);

        $asset->update([
            'status' => AssetStatus::Borrowed,
            'current_unit_id' => $unitSi->id,
        ]);

        $this->assertNotEmpty($asset->uuid);
        $this->assertSame(AssetStatus::Borrowed, $asset->status);
        $this->assertSame(AssetCondition::Good, $asset->condition);
        $this->assertSame(AssetOwnership::Owned, $asset->ownership_status);
        $this->assertSame('18500000.00', $asset->acquisition_cost);

        $movements = $asset->movements;
        $this->assertCount(2, $movements);
        $this->assertSame(AssetEventType::Borrow, $movements[0]->event_type);
        $this->assertSame(AssetEventType::InitialPlacement, $movements[1]->event_type);

        $maintenances = $asset->maintenances;
        $this->assertCount(1, $maintenances);
        $this->assertSame(AssetMaintenanceStatus::Completed, $maintenances[0]->status);
        $this->assertSame('150000.00', $maintenances[0]->cost);

        $updatedLog = ActivityLog::query()
            ->where('subject_type', Asset::class)
            ->where('subject_id', $asset->id)
            ->where('event', 'updated')
            ->first();

        $this->assertNotNull($updatedLog);
        $this->assertSame('asset_audit', $updatedLog->log_name);
    }
}
