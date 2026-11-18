<?php

declare(strict_types=1);

namespace Tests\Feature\Filament\Pages;

use App\Enums\DegreeLevel;
use App\Enums\DocumentStatus;
use App\Enums\UnitType;
use App\Enums\VisibilityLevel;
use App\Filament\Pages\Documents\ConfidentialDocuments;
use App\Filament\Pages\Documents\InternalDocuments;
use App\Filament\Pages\Documents\PublicDocuments;
use App\Filament\Resources\Documents\DocumentResource;
use App\Filament\Resources\Documents\Pages\ListDocuments;
use App\Models\AcademicYear;
use App\Models\Document;
use App\Models\DocumentCategory;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class VisibilityDocumentsPagesTest extends TestCase
{
    use LazilyRefreshDatabase;

    private Unit $unit;

    private DocumentCategory $category;

    private AcademicYear $academicYear;

    private User $adminUser;

    private User $staffUser;

    protected function setUp(): void
    {
        parent::setUp();

        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $this->unit = Unit::create([
            'code' => 'TI',
            'name' => 'Prodi Informatika',
            'slug' => 'prodi-informatika',
            'type' => UnitType::Prodi,
            'degree_level' => DegreeLevel::S1,
            'is_active' => true,
        ]);

        $this->category = DocumentCategory::create([
            'code' => 'SK',
            'name' => 'Surat Keputusan',
            'slug' => 'surat-keputusan',
            'requires_approval' => false,
            'is_active' => true,
        ]);

        $this->academicYear = AcademicYear::create([
            'name' => '2026/2027',
            'start_date' => '2026-09-01',
            'end_date' => '2027-08-31',
            'is_active' => true,
        ]);

        $permissions = [
            'view_any:document',
            'view:document',
            'create:document',
            'update:document',
            'delete:document',
            'document.view_confidential',
            'document.bypass_unit_scope',
        ];

        foreach ($permissions as $perm) {
            Permission::findOrCreate($perm, 'web');
        }

        $adminRole = Role::findOrCreate('super_admin', 'web');
        $adminRole->syncPermissions($permissions);

        $staffRole = Role::findOrCreate('staf_prodi', 'web');
        $staffRole->syncPermissions([
            'view_any:document',
            'view:document',
            'create:document',
        ]);

        $this->adminUser = User::factory()->create([
            'unit_id' => $this->unit->id,
            'is_active' => true,
        ]);
        $this->adminUser->assignRole($adminRole);

        $this->staffUser = User::factory()->create([
            'unit_id' => $this->unit->id,
            'is_active' => true,
        ]);
        $this->staffUser->assignRole($staffRole);
    }

    private function createDoc(VisibilityLevel $visibility, string $title): Document
    {
        return Document::create([
            'unit_id' => $this->unit->id,
            'document_category_id' => $this->category->id,
            'academic_year_id' => $this->academicYear->id,
            'uploaded_by' => $this->adminUser->id,
            'title' => $title,
            'status' => DocumentStatus::Approved,
            'visibility' => $visibility,
            'is_borang' => false,
            'total_steps' => 0,
            'current_step' => 0,
            'issued_date' => now()->toDateString(),
            'approved_at' => now(),
        ]);
    }

    public function test_sub_menu_pages_can_be_rendered_successfully_for_authorized_users(): void
    {
        $this->actingAs($this->adminUser);

        Livewire::test(InternalDocuments::class)->assertSuccessful();
        Livewire::test(PublicDocuments::class)->assertSuccessful();
        Livewire::test(ConfidentialDocuments::class)->assertSuccessful();
    }

    public function test_internal_documents_page_displays_only_internal_documents(): void
    {
        $this->actingAs($this->adminUser);

        $docInternal = $this->createDoc(VisibilityLevel::Internal, 'Dokumen Internal Kebijakan Prodi');
        $docPublic = $this->createDoc(VisibilityLevel::Public, 'Dokumen Publik Kalender Akademik');
        $docConfidential = $this->createDoc(VisibilityLevel::Confidential, 'Dokumen Rahasia Honor Tim');

        Livewire::test(InternalDocuments::class)
            ->assertCanSeeTableRecords([$docInternal])
            ->assertCanNotSeeTableRecords([$docPublic, $docConfidential]);
    }

    public function test_public_documents_page_displays_only_public_documents(): void
    {
        $this->actingAs($this->adminUser);

        $docInternal = $this->createDoc(VisibilityLevel::Internal, 'Dokumen Internal Kebijakan Prodi');
        $docPublic = $this->createDoc(VisibilityLevel::Public, 'Dokumen Publik Kalender Akademik');
        $docConfidential = $this->createDoc(VisibilityLevel::Confidential, 'Dokumen Rahasia Honor Tim');

        Livewire::test(PublicDocuments::class)
            ->assertCanSeeTableRecords([$docPublic])
            ->assertCanNotSeeTableRecords([$docInternal, $docConfidential]);
    }

    public function test_confidential_documents_page_displays_only_confidential_documents(): void
    {
        $this->actingAs($this->adminUser);

        $docInternal = $this->createDoc(VisibilityLevel::Internal, 'Dokumen Internal Kebijakan Prodi');
        $docPublic = $this->createDoc(VisibilityLevel::Public, 'Dokumen Publik Kalender Akademik');
        $docConfidential = $this->createDoc(VisibilityLevel::Confidential, 'Dokumen Rahasia Honor Tim');

        Livewire::test(ConfidentialDocuments::class)
            ->assertCanSeeTableRecords([$docConfidential])
            ->assertCanNotSeeTableRecords([$docInternal, $docPublic]);
    }

    public function test_regular_staff_without_permission_cannot_access_confidential_documents_page(): void
    {
        $this->actingAs($this->staffUser);

        $this->assertFalse(ConfidentialDocuments::canAccess());

        $this->get('/admin/documents/confidential')
            ->assertForbidden();
    }

    public function test_navigation_menu_contains_all_three_visibility_submenus(): void
    {
        $this->actingAs($this->adminUser);

        $response = $this->get('/admin');
        $response->assertSuccessful();

        $response->assertSee('Dokumen Arsip & Borang');
        $response->assertSee('Dokumen Internal');
        $response->assertSee('Dokumen Publik');
        $response->assertSee('Dokumen Rahasia');
    }

    public function test_actions_point_to_document_resource(): void
    {
        $this->actingAs($this->adminUser);

        $doc = $this->createDoc(VisibilityLevel::Internal, 'Uji Tautan Aksi Resource');

        $viewUrl = DocumentResource::getUrl('view', ['record' => $doc]);
        $editUrl = DocumentResource::getUrl('edit', ['record' => $doc]);

        $this->assertStringContainsString('/admin/documents/'.$doc->id, $viewUrl);
        $this->assertStringContainsString('/admin/documents/'.$doc->id.'/edit', $editUrl);
    }

    public function test_visibility_pages_do_not_have_create_action_or_visibility_filter(): void
    {
        $this->actingAs($this->adminUser);

        // Sub-menu pages must NOT have create action and must NOT have visibility filter
        $internalComponent = Livewire::test(InternalDocuments::class);
        $internalComponent->assertActionDoesNotExist('create');
        $this->assertArrayNotHasKey('visibility', $internalComponent->instance()->getTable()->getFilters());

        $publicComponent = Livewire::test(PublicDocuments::class);
        $publicComponent->assertActionDoesNotExist('create');
        $this->assertArrayNotHasKey('visibility', $publicComponent->instance()->getTable()->getFilters());

        $confidentialComponent = Livewire::test(ConfidentialDocuments::class);
        $confidentialComponent->assertActionDoesNotExist('create');
        $this->assertArrayNotHasKey('visibility', $confidentialComponent->instance()->getTable()->getFilters());

        // Main DocumentResource page MUST retain create action and visibility filter
        $listDocsComponent = Livewire::test(ListDocuments::class);
        $listDocsComponent->assertActionExists('create');
        $this->assertArrayHasKey('visibility', $listDocsComponent->instance()->getTable()->getFilters());
    }
}
