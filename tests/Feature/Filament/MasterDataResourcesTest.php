<?php

namespace Tests\Feature\Filament;

use App\Enums\UnitType;
use App\Enums\VisibilityLevel;
use App\Filament\Resources\AcademicYears\Pages\CreateAcademicYear;
use App\Filament\Resources\AcademicYears\Pages\ListAcademicYears;
use App\Filament\Resources\AcademicYears\Tables\AcademicYearsTable;
use App\Filament\Resources\AccreditationCriteria\Pages\ListAccreditationCriteria;
use App\Filament\Resources\AccreditationCriteria\Tables\AccreditationCriteriaTable;
use App\Filament\Resources\DocumentCategories\Pages\CreateDocumentCategory;
use App\Filament\Resources\DocumentCategories\Pages\ListDocumentCategories;
use App\Filament\Resources\DocumentCategories\Tables\DocumentCategoriesTable;
use App\Filament\Resources\PostCategories\Pages\CreatePostCategory;
use App\Filament\Resources\PostCategories\Pages\ListPostCategories;
use App\Filament\Resources\PostCategories\Tables\PostCategoriesTable;
use App\Filament\Resources\Units\Pages\CreateUnit;
use App\Filament\Resources\Units\Pages\ListUnits;
use App\Filament\Resources\Units\Tables\UnitsTable;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Filament\Resources\Users\Tables\UsersTable;
use App\Models\AcademicYear;
use App\Models\DocumentCategory;
use App\Models\PostCategory;
use App\Models\Unit;
use App\Models\User;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Table;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Livewire\Livewire;
use Tests\Support\LegacyMasterDataSeeder;
use Tests\TestCase;

class MasterDataResourcesTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_super_admin_can_render_all_master_data_resource_index_and_create_pages(): void
    {
        $this->seed(LegacyMasterDataSeeder::class);

        $superAdmin = User::where('email', 'admin@univbi.ac.id')->firstOrFail();

        $routes = [
            '/admin/units',
            '/admin/units/create',
            '/admin/document-categories',
            '/admin/document-categories/create',
            '/admin/accreditation-criteria',
            '/admin/accreditation-criteria/create',
            '/admin/academic-years',
            '/admin/academic-years/create',
            '/admin/post-categories',
            '/admin/post-categories/create',
            '/admin/users',
            '/admin/users/create',
        ];

        foreach ($routes as $route) {
            $this->actingAs($superAdmin)
                ->get($route)
                ->assertOk();
        }
    }

    public function test_forbids_staf_unit_from_creating_master_data_or_accessing_user_management(): void
    {
        $this->seed(LegacyMasterDataSeeder::class);

        $stafUnit = User::where('email', 'staf.ti@univbi.ac.id')->firstOrFail();

        $this->actingAs($stafUnit)
            ->get('/admin/units')
            ->assertOk();

        $this->actingAs($stafUnit)
            ->get('/admin/units/create')
            ->assertForbidden();

        $this->actingAs($stafUnit)
            ->get('/admin/document-categories/create')
            ->assertForbidden();

        $this->actingAs($stafUnit)
            ->get('/admin/users')
            ->assertForbidden();
    }

    public function test_creates_unit_record_via_filament_create_page(): void
    {
        $this->seed(LegacyMasterDataSeeder::class);

        $superAdmin = User::where('email', 'admin@univbi.ac.id')->firstOrFail();
        $this->actingAs($superAdmin);

        Livewire::test(CreateUnit::class)
            ->fillForm([
                'name' => 'UPT Perpustakaan & Pusat Bahasa',
                'code' => 'PERPUS',
                'type' => UnitType::Upt->value,
                'is_active' => true,
                'description' => 'Unit layanan perpustakaan digital UNBI',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $createdUnit = Unit::where('code', 'PERPUS')->first();

        $this->assertNotNull($createdUnit);
        $this->assertSame('UPT Perpustakaan & Pusat Bahasa', $createdUnit->name);
        $this->assertSame(UnitType::Upt, $createdUnit->type);
    }

    public function test_creates_document_category_with_multi_level_approval_flows_via_filament_create_page(): void
    {
        $this->seed(LegacyMasterDataSeeder::class);

        $superAdmin = User::where('email', 'admin@univbi.ac.id')->firstOrFail();
        $this->actingAs($superAdmin);

        Livewire::test(CreateDocumentCategory::class)
            ->fillForm([
                'name' => 'Surat Tugas Dosen',
                'slug' => 'surat-tugas-dosen',
                'code' => 'STD',
                'number_format' => '{seq}/UNIVBI.{unit}/{cat}/{roman_month}/{year}',
                'default_visibility' => VisibilityLevel::Internal->value,
                'requires_approval' => true,
                'allow_manual_number' => true,
                'has_expiry' => false,
                'is_active' => true,
                'approvalFlows' => [
                    [
                        'step_order' => 1,
                        'approver_role' => 'kaprodi',
                        'step_label' => 'Verifikasi Kaprodi',
                        'is_mandatory' => true,
                    ],
                    [
                        'step_order' => 2,
                        'approver_role' => 'dekan',
                        'step_label' => 'Pengesahan Dekan Fakultas',
                        'is_mandatory' => true,
                    ],
                ],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $category = DocumentCategory::where('slug', 'surat-tugas-dosen')->with('approvalFlows')->first();

        $this->assertNotNull($category);
        $this->assertSame('STD', $category->code);
        $this->assertTrue($category->requires_approval);
        $this->assertCount(2, $category->approvalFlows);
        $this->assertSame('kaprodi', $category->approvalFlows[0]->approver_role);
        $this->assertSame('dekan', $category->approvalFlows[1]->approver_role);
    }

    public function test_activating_new_academic_year_deactivates_previously_active_academic_year(): void
    {
        $this->seed(LegacyMasterDataSeeder::class);

        $superAdmin = User::where('email', 'admin@univbi.ac.id')->firstOrFail();
        $this->actingAs($superAdmin);

        $previousActive = AcademicYear::where('is_active', true)->firstOrFail();

        Livewire::test(CreateAcademicYear::class)
            ->fillForm([
                'name' => '2027/2028 Ganjil',
                'semester' => 'ganjil',
                'start_date' => '2027-09-01',
                'end_date' => '2028-02-28',
                'is_active' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $previousActive->refresh();
        $newActive = AcademicYear::where('name', '2027/2028 Ganjil')->firstOrFail();

        $this->assertFalse($previousActive->is_active);
        $this->assertTrue($newActive->is_active);
        $this->assertSame(1, AcademicYear::where('is_active', true)->count());
    }

    public function test_creates_post_category_with_color_via_filament_create_page(): void
    {
        $this->seed(LegacyMasterDataSeeder::class);

        $superAdmin = User::where('email', 'admin@univbi.ac.id')->firstOrFail();
        $this->actingAs($superAdmin);

        Livewire::test(CreatePostCategory::class)
            ->fillForm([
                'name' => 'Kabar Alumni & Karir',
                'slug' => 'kabar-alumni-karir',
                'color' => 'purple',
                'order_index' => 10,
                'is_active' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $category = PostCategory::where('slug', 'kabar-alumni-karir')->first();

        $this->assertNotNull($category);
        $this->assertSame('purple', $category->color);

        $this->get('/admin/post-categories')
            ->assertOk()
            ->assertSeeText('Ungu (Purple)');
    }

    public function test_prevents_single_and_bulk_deletion_of_unit_with_dependent_records_and_displays_alert(): void
    {
        $this->seed(LegacyMasterDataSeeder::class);

        $superAdmin = User::where('email', 'admin@univbi.ac.id')->firstOrFail();
        $this->actingAs($superAdmin);

        $unitFik = Unit::where('code', 'FIK')->firstOrFail();

        // 1. Single Delete
        Livewire::test(ListUnits::class)
            ->callTableAction('delete', $unitFik)
            ->assertNotified();

        $this->assertDatabaseHas('units', [
            'id' => $unitFik->id,
            'deleted_at' => null,
        ]);

        // 2. Bulk Delete
        Livewire::test(ListUnits::class)
            ->callTableBulkAction('delete', [$unitFik->id])
            ->assertNotified();

        $this->assertDatabaseHas('units', [
            'id' => $unitFik->id,
            'deleted_at' => null,
        ]);
    }

    public function test_allows_deletion_of_standalone_unit_without_dependent_records(): void
    {
        $this->seed(LegacyMasterDataSeeder::class);

        $superAdmin = User::where('email', 'admin@univbi.ac.id')->firstOrFail();
        $this->actingAs($superAdmin);

        $emptyUnit = Unit::create([
            'name' => 'Unit Sementara Tanpa Relasi',
            'code' => 'TEMP_EMPTY',
            'type' => UnitType::UnitPendukung,
            'is_active' => true,
        ]);

        Livewire::test(ListUnits::class)
            ->callTableAction('delete', $emptyUnit);

        $this->assertSoftDeleted('units', [
            'id' => $emptyUnit->id,
        ]);
    }

    public function test_all_master_tables_use_modal_filters_layout(): void
    {
        $this->seed(LegacyMasterDataSeeder::class);

        $tables = [
            UnitsTable::configure(Table::make(new ListUnits)),
            UsersTable::configure(Table::make(new ListUsers)),
            DocumentCategoriesTable::configure(Table::make(new ListDocumentCategories)),
            AcademicYearsTable::configure(Table::make(new ListAcademicYears)),
            PostCategoriesTable::configure(Table::make(new ListPostCategories)),
            AccreditationCriteriaTable::configure(Table::make(new ListAccreditationCriteria)),
        ];

        foreach ($tables as $table) {
            $this->assertSame(FiltersLayout::Modal, $table->getFiltersLayout());
        }
    }

    public function test_document_category_approval_flows_step_order_increments_and_updates_on_reorder(): void
    {
        $this->seed(LegacyMasterDataSeeder::class);

        $superAdmin = User::where('email', 'admin@univbi.ac.id')->firstOrFail();
        $this->actingAs($superAdmin);

        $component = Livewire::test(CreateDocumentCategory::class)
            ->fillForm([
                'requires_approval' => true,
            ])
            ->callFormComponentAction('approvalFlows', 'add')
            ->callFormComponentAction('approvalFlows', 'add');

        $flows = $component->get('data.approvalFlows');
        $this->assertCount(2, $flows);

        $keys = array_keys($flows);
        $this->assertSame(1, (int) $flows[$keys[0]]['step_order']);
        $this->assertSame(2, (int) $flows[$keys[1]]['step_order']);

        // Reorder the items
        $component->callFormComponentAction('approvalFlows', 'reorder', arguments: [
            'items' => [$keys[1], $keys[0]],
        ]);

        $reorderedFlows = $component->get('data.approvalFlows');
        $reorderedKeys = array_keys($reorderedFlows);

        $this->assertSame($keys[1], $reorderedKeys[0]);
        $this->assertSame($keys[0], $reorderedKeys[1]);
        $this->assertSame(1, (int) $reorderedFlows[$reorderedKeys[0]]['step_order']);
        $this->assertSame(2, (int) $reorderedFlows[$reorderedKeys[1]]['step_order']);

        // Add 3rd item
        $component->callFormComponentAction('approvalFlows', 'add');
        $threeFlows = $component->get('data.approvalFlows');
        $threeKeys = array_keys($threeFlows);
        $this->assertCount(3, $threeFlows);
        $this->assertSame(1, (int) $threeFlows[$threeKeys[0]]['step_order']);
        $this->assertSame(2, (int) $threeFlows[$threeKeys[1]]['step_order']);
        $this->assertSame(3, (int) $threeFlows[$threeKeys[2]]['step_order']);

        // Delete the middle item (index 1)
        $component->callFormComponentAction('approvalFlows', 'delete', arguments: [
            'item' => $threeKeys[1],
        ]);
        $remainingFlows = $component->get('data.approvalFlows');
        $remainingKeys = array_keys($remainingFlows);
        $this->assertCount(2, $remainingFlows);
        $this->assertSame(1, (int) $remainingFlows[$remainingKeys[0]]['step_order']);
        $this->assertSame(2, (int) $remainingFlows[$remainingKeys[1]]['step_order']);
    }

    public function test_units_table_groups_hierarchically_by_parent_unit(): void
    {
        $this->seed(LegacyMasterDataSeeder::class);

        $superAdmin = User::where('email', 'admin@univbi.ac.id')->firstOrFail();
        $this->actingAs($superAdmin);

        $rektorat = Unit::where('code', 'REKTORAT')->firstOrFail();
        $fik = Unit::where('code', 'FIK')->firstOrFail();
        $ti = Unit::where('code', 'TI')->firstOrFail();

        Livewire::test(ListUnits::class)
            ->assertSuccessful()
            ->assertCanSeeTableRecords([$rektorat, $fik, $ti], inOrder: true)
            ->assertSeeInOrder([
                'Tingkat Universitas (Pimpinan / Tanpa Induk)',
                'Rektorat Universitas Bina Insan (REKTORAT)',
                'REKTORAT -&gt; Fakultas Ilmu Komputer (FIK)',
            ], false);
    }
}
