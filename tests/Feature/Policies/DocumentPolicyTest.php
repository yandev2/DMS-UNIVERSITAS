<?php

declare(strict_types=1);

namespace Tests\Feature\Policies;

use App\Enums\DegreeLevel;
use App\Enums\DocumentStatus;
use App\Enums\StampingPosition;
use App\Enums\UnitType;
use App\Enums\VisibilityLevel;
use App\Models\AcademicYear;
use App\Models\Document;
use App\Models\DocumentCategory;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class DocumentPolicyTest extends TestCase
{
    use LazilyRefreshDatabase;

    private Unit $unitA;

    private Unit $unitB;

    private DocumentCategory $category;

    private AcademicYear $academicYear;

    private Role $roleStaff;

    private Role $roleRektor;

    protected function setUp(): void
    {
        parent::setUp();

        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $this->unitA = Unit::create([
            'code' => 'TI',
            'name' => 'Prodi Informatika',
            'slug' => 'prodi-informatika',
            'type' => UnitType::Prodi,
            'degree_level' => DegreeLevel::S1,
            'is_active' => true,
        ]);

        $this->unitB = Unit::create([
            'code' => 'SI',
            'name' => 'Prodi Sistem Informasi',
            'slug' => 'prodi-sistem-informasi',
            'type' => UnitType::Prodi,
            'degree_level' => DegreeLevel::S1,
            'is_active' => true,
        ]);

        $this->category = DocumentCategory::create([
            'code' => 'SK',
            'name' => 'Surat Keputusan',
            'slug' => 'surat-keputusan',
            'stamping_position' => StampingPosition::TopRight,
            'requires_approval' => true,
            'is_active' => true,
        ]);

        $this->academicYear = AcademicYear::create([
            'code' => '2026/2027-1',
            'name' => '2026/2027 Ganjil',
            'semester' => 'Ganjil',
            'start_date' => '2026-09-01',
            'end_date' => '2027-02-28',
            'is_active' => true,
        ]);

        // Create standard permissions
        $permissions = [
            'view_any:document',
            'view:document',
            'create:document',
            'update:document',
            'delete:document',
            'restore:document',
            'force_delete:document',
            'document.view_confidential',
            'document.download_private',
            'document.approve',
            'document.bypass_unit_scope',
        ];

        foreach ($permissions as $perm) {
            Permission::findOrCreate($perm, 'web');
        }

        $this->roleStaff = Role::findOrCreate('staf_prodi', 'web');
        $this->roleStaff->syncPermissions([
            'view_any:document',
            'view:document',
            'create:document',
            'update:document',
            'delete:document',
            'document.download_private',
        ]);

        $this->roleRektor = Role::findOrCreate('rektor', 'web');
        $this->roleRektor->syncPermissions([
            'view_any:document',
            'view:document',
            'create:document',
            'update:document',
            'delete:document',
            'document.view_confidential',
            'document.download_private',
            'document.approve',
            'document.bypass_unit_scope',
        ]);
    }

    private function createDocument(array $attributes = []): Document
    {
        $defaultUploader = User::factory()->create([
            'unit_id' => $attributes['unit_id'] ?? $this->unitA->id,
            'is_active' => true,
        ]);

        return Document::create(array_merge([
            'title' => 'Dokumen Uji Kebijakan',
            'unit_id' => $this->unitA->id,
            'document_category_id' => $this->category->id,
            'academic_year_id' => $this->academicYear->id,
            'uploaded_by' => $defaultUploader->id,
            'issued_date' => '2026-11-10',
            'visibility' => VisibilityLevel::Internal,
            'status' => DocumentStatus::Draft,
            'is_borang' => false,
            'has_expiry' => false,
        ], $attributes));
    }

    public function test_user_without_permission_cannot_view_any(): void
    {
        $user = User::factory()->create(['unit_id' => $this->unitA->id, 'is_active' => true]);

        $this->assertFalse($user->can('viewAny', Document::class));
    }

    public function test_user_with_permission_can_view_any(): void
    {
        $user = User::factory()->create(['unit_id' => $this->unitA->id, 'is_active' => true]);
        $user->assignRole($this->roleStaff);

        $this->assertTrue($user->can('viewAny', Document::class));
    }

    public function test_regular_staff_cannot_view_internal_document_of_other_unit(): void
    {
        $userA = User::factory()->create(['unit_id' => $this->unitA->id, 'is_active' => true]);
        $userA->assignRole($this->roleStaff);

        $docUnitB = $this->createDocument([
            'unit_id' => $this->unitB->id,
            'visibility' => VisibilityLevel::Internal,
            'is_borang' => false,
        ]);

        $this->assertFalse($userA->can('view', $docUnitB));
    }

    public function test_regular_staff_can_view_internal_document_of_own_unit(): void
    {
        $userA = User::factory()->create(['unit_id' => $this->unitA->id, 'is_active' => true]);
        $userA->assignRole($this->roleStaff);

        $docUnitA = $this->createDocument([
            'unit_id' => $this->unitA->id,
            'visibility' => VisibilityLevel::Internal,
            'is_borang' => false,
        ]);

        $this->assertTrue($userA->can('view', $docUnitA));
    }

    public function test_public_and_borang_documents_can_be_viewed_across_units(): void
    {
        $userA = User::factory()->create(['unit_id' => $this->unitA->id, 'is_active' => true]);
        $userA->assignRole($this->roleStaff);

        $publicDocUnitB = $this->createDocument([
            'unit_id' => $this->unitB->id,
            'visibility' => VisibilityLevel::Public,
            'is_borang' => false,
        ]);

        $borangDocUnitB = $this->createDocument([
            'unit_id' => $this->unitB->id,
            'visibility' => VisibilityLevel::Internal,
            'is_borang' => true,
        ]);

        $this->assertTrue($userA->can('view', $publicDocUnitB));
        $this->assertTrue($userA->can('view', $borangDocUnitB));
    }

    public function test_confidential_document_requires_special_permission_or_ownership(): void
    {
        $userStaff = User::factory()->create(['unit_id' => $this->unitA->id, 'is_active' => true]);
        $userStaff->assignRole($this->roleStaff);

        $userRektor = User::factory()->create(['unit_id' => $this->unitA->id, 'is_active' => true]);
        $userRektor->assignRole($this->roleRektor);

        $confidentialDoc = $this->createDocument([
            'unit_id' => $this->unitA->id,
            'visibility' => VisibilityLevel::Confidential,
            'uploaded_by' => $userStaff->id,
        ]);

        // Staff is the uploader, so can view own confidential doc
        $this->assertTrue($userStaff->can('view', $confidentialDoc));

        // Rektor has document.view_confidential permission
        $this->assertTrue($userRektor->can('view', $confidentialDoc));

        // Another staff in unit A who is NOT the uploader cannot view
        $otherStaff = User::factory()->create(['unit_id' => $this->unitA->id, 'is_active' => true]);
        $otherStaff->assignRole($this->roleStaff);
        $this->assertFalse($otherStaff->can('view', $confidentialDoc));
    }

    public function test_user_with_bypass_unit_scope_can_view_all_units(): void
    {
        $userRektor = User::factory()->create(['unit_id' => $this->unitA->id, 'is_active' => true]);
        $userRektor->assignRole($this->roleRektor);

        $docUnitB = $this->createDocument([
            'unit_id' => $this->unitB->id,
            'visibility' => VisibilityLevel::Internal,
        ]);

        $this->assertTrue($userRektor->can('view', $docUnitB));
        $this->assertTrue($userRektor->can('update', $docUnitB));
        $this->assertTrue($userRektor->can('delete', $docUnitB));
    }

    public function test_staff_cannot_update_approved_document(): void
    {
        $userStaff = User::factory()->create(['unit_id' => $this->unitA->id, 'is_active' => true]);
        $userStaff->assignRole($this->roleStaff);

        $approvedDoc = $this->createDocument([
            'unit_id' => $this->unitA->id,
            'status' => DocumentStatus::Approved,
        ]);

        $this->assertFalse($userStaff->can('update', $approvedDoc));
    }

    public function test_staff_cannot_delete_document_from_different_unit(): void
    {
        $userStaff = User::factory()->create(['unit_id' => $this->unitA->id, 'is_active' => true]);
        $userStaff->assignRole($this->roleStaff);

        $docUnitB = $this->createDocument([
            'unit_id' => $this->unitB->id,
            'status' => DocumentStatus::Draft,
        ]);

        $this->assertFalse($userStaff->can('delete', $docUnitB));
    }

    public function test_download_private_permission_check(): void
    {
        $userStaff = User::factory()->create(['unit_id' => $this->unitA->id, 'is_active' => true]);
        $userStaff->assignRole($this->roleStaff);

        $docOwn = $this->createDocument([
            'unit_id' => $this->unitA->id,
            'visibility' => VisibilityLevel::Internal,
        ]);

        $this->assertTrue($userStaff->can('downloadPrivate', $docOwn));

        // If user cannot view the document, cannot downloadPrivate
        $docOther = $this->createDocument([
            'unit_id' => $this->unitB->id,
            'visibility' => VisibilityLevel::Internal,
        ]);

        $this->assertFalse($userStaff->can('downloadPrivate', $docOther));
    }

    public function test_staff_cannot_update_pending_rejected_archived_or_expired_documents(): void
    {
        $userStaff = User::factory()->create(['unit_id' => $this->unitA->id, 'is_active' => true]);
        $userStaff->assignRole($this->roleStaff);

        foreach ([DocumentStatus::Draft, DocumentStatus::Revision] as $editableStatus) {
            $doc = $this->createDocument([
                'unit_id' => $this->unitA->id,
                'uploaded_by' => $userStaff->id,
                'status' => $editableStatus,
            ]);

            $this->assertTrue($userStaff->can('update', $doc), "Staff should be able to update {$editableStatus->value} document.");
        }

        foreach ([DocumentStatus::Pending, DocumentStatus::Approved, DocumentStatus::Rejected, DocumentStatus::Archived, DocumentStatus::Expired] as $lockedStatus) {
            $doc = $this->createDocument([
                'unit_id' => $this->unitA->id,
                'uploaded_by' => $userStaff->id,
                'status' => $lockedStatus,
            ]);

            $this->assertFalse($userStaff->can('update', $doc), "Staff must NOT update {$lockedStatus->value} document.");
        }
    }

    public function test_super_admin_can_update_documents_across_all_statuses(): void
    {
        $roleSuperAdmin = Role::findOrCreate('super_admin', 'web');
        $superAdmin = User::factory()->create(['unit_id' => $this->unitA->id, 'is_active' => true]);
        $superAdmin->assignRole($roleSuperAdmin);

        foreach (DocumentStatus::cases() as $status) {
            $doc = $this->createDocument([
                'unit_id' => $this->unitB->id,
                'status' => $status,
            ]);

            $this->assertTrue($superAdmin->can('update', $doc), "Super Admin should be able to update {$status->value} document.");
        }
    }
}
