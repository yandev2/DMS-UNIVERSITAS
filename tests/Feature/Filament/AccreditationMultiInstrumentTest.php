<?php

namespace Tests\Feature\Filament;

use App\Enums\AccreditationInstrument;
use App\Enums\UnitType;
use App\Filament\Resources\AccreditationCriteria\Pages\CreateAccreditationCriterion;
use App\Filament\Resources\AccreditationCriteria\Pages\ListAccreditationCriteria;
use App\Filament\Resources\Units\Pages\CreateUnit;
use App\Filament\Resources\Units\Pages\ListUnits;
use App\Models\AccreditationCriterion;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Livewire\Livewire;
use Tests\Support\LegacyMasterDataSeeder;
use Tests\TestCase;

class AccreditationMultiInstrumentTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(LegacyMasterDataSeeder::class);
    }

    public function test_composite_unique_allows_same_code_across_different_instruments(): void
    {
        $banPtK1 = AccreditationCriterion::where('code', 'K1')
            ->where('instrument_source', AccreditationInstrument::BanPt->value)
            ->first();

        $lamInfokomK1 = AccreditationCriterion::where('code', 'K1')
            ->where('instrument_source', AccreditationInstrument::LamInfokom->value)
            ->first();

        $lamembaK1 = AccreditationCriterion::where('code', 'K1')
            ->where('instrument_source', AccreditationInstrument::Lamemba->value)
            ->first();

        $this->assertNotNull($banPtK1);
        $this->assertNotNull($lamInfokomK1);
        $this->assertNotNull($lamembaK1);

        $this->assertNotEquals($banPtK1->id, $lamInfokomK1->id);
        $this->assertNotEquals($lamInfokomK1->id, $lamembaK1->id);
    }

    public function test_composite_unique_rejects_duplicate_code_within_same_instrument(): void
    {
        $this->expectException(QueryException::class);

        AccreditationCriterion::create([
            'code' => 'K1',
            'name' => 'Duplikat K1 BAN-PT',
            'instrument_source' => AccreditationInstrument::BanPt->value,
            'order_index' => 10,
            'is_active' => true,
        ]);
    }

    public function test_unit_manages_effective_accreditation_instrument_and_criteria(): void
    {
        $unitTi = Unit::where('code', 'TI')->firstOrFail();
        $this->assertEquals(AccreditationInstrument::LamInfokom->value, $unitTi->effective_accreditation_instrument);

        $tiCriteria = $unitTi->getApplicableCriteria();
        $this->assertCount(9, $tiCriteria);
        foreach ($tiCriteria as $criterion) {
            $this->assertEquals(AccreditationInstrument::LamInfokom->value, $criterion->instrument_source);
        }

        // Test fallback inheritance from parent
        $fik = Unit::where('code', 'FIK')->firstOrFail();
        $subUnit = Unit::create([
            'name' => 'Lab AI Terapan',
            'code' => 'LAB-AI',
            'type' => UnitType::Prodi,
            'parent_id' => $fik->id,
            'accreditation_instrument' => null,
            'is_active' => true,
        ]);

        $this->assertEquals(AccreditationInstrument::LamInfokom->value, $subUnit->effective_accreditation_instrument);
    }

    public function test_custom_instrument_can_be_registered_dynamically(): void
    {
        $customInstrumentName = 'ASIIN Internasional';

        $customCriterion = AccreditationCriterion::create([
            'code' => 'K1',
            'name' => 'Kriteria 1 ASIIN - Educational Objectives',
            'instrument_source' => $customInstrumentName,
            'order_index' => 1,
            'is_active' => true,
        ]);

        $this->assertDatabaseHas('accreditation_criteria', [
            'id' => $customCriterion->id,
            'code' => 'K1',
            'instrument_source' => $customInstrumentName,
        ]);

        $unitAsiin = Unit::create([
            'name' => 'Program Studi S1 Informatika Kelas Internasional',
            'code' => 'TI-INT',
            'type' => UnitType::Prodi,
            'accreditation_instrument' => $customInstrumentName,
            'is_active' => true,
        ]);

        $this->assertEquals($customInstrumentName, $unitAsiin->effective_accreditation_instrument);
        $applicable = $unitAsiin->getApplicableCriteria();
        $this->assertCount(1, $applicable);
        $this->assertEquals('K1', $applicable->first()->code);
    }

    public function test_accreditation_criteria_filament_list_and_create_pages(): void
    {
        $admin = User::where('email', 'admin@univbi.ac.id')->firstOrFail();
        $this->actingAs($admin);

        Livewire::test(ListAccreditationCriteria::class)
            ->assertSuccessful()
            ->assertCanRenderTableColumn('code')
            ->assertCanRenderTableColumn('name')
            ->assertCanRenderTableColumn('instrument_source');

        Livewire::test(CreateAccreditationCriterion::class)
            ->fillForm([
                'code' => 'K10',
                'name' => 'Kriteria Tambahan SPMI Mutu',
                'instrument_source' => AccreditationInstrument::BanPt->value,
                'order_index' => 10,
                'is_active' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('accreditation_criteria', [
            'code' => 'K10',
            'instrument_source' => AccreditationInstrument::BanPt->value,
        ]);
    }

    public function test_unit_filament_create_with_accreditation_instrument(): void
    {
        $admin = User::where('email', 'admin@univbi.ac.id')->firstOrFail();
        $this->actingAs($admin);

        Livewire::test(ListUnits::class)
            ->assertSuccessful()
            ->assertCanRenderTableColumn('code')
            ->assertCanRenderTableColumn('accreditation_instrument');

        Livewire::test(CreateUnit::class)
            ->fillForm([
                'name' => 'Program Studi S1 Bisnis Digital',
                'code' => 'BD',
                'type' => UnitType::Prodi->value,
                'accreditation_body' => 'LAMEMBA',
                'accreditation_instrument' => AccreditationInstrument::Lamemba->value,
                'is_active' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('units', [
            'code' => 'BD',
            'accreditation_instrument' => AccreditationInstrument::Lamemba->value,
        ]);
    }
}
