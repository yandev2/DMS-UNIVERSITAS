<?php

declare(strict_types=1);

namespace Tests\Feature\Filament;

use App\Enums\DegreeLevel;
use App\Enums\DocumentStatus;
use App\Enums\ReminderInterval;
use App\Enums\StampingPosition;
use App\Enums\UnitType;
use App\Enums\VisibilityLevel;
use App\Filament\Resources\Documents\Pages\CreateDocument;
use App\Filament\Resources\Documents\Pages\EditDocument;
use App\Filament\Resources\Documents\Pages\ListDocuments;
use App\Filament\Resources\Documents\Pages\TrashDocuments;
use App\Filament\Resources\Documents\Pages\ViewDocument;
use App\Models\AcademicYear;
use App\Models\CategoryApprovalFlow;
use App\Models\Document;
use App\Models\DocumentCategory;
use App\Models\DocumentFile;
use App\Models\Permission;
use App\Models\ReminderLog;
use App\Models\Role;
use App\Models\Unit;
use App\Models\User;
use App\Services\DocumentApprovalService;
use Filament\Tables\Enums\PaginationMode;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class DocumentResourceTest extends TestCase
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
            'reminder.manage_recipients',
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
            'restore:document',
            'force_delete:document',
            'document.download_private',
            'reminder.manage_recipients',
        ]);

        $this->roleRektor = Role::findOrCreate('rektor', 'web');
        $this->roleRektor->syncPermissions([
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
        ]);
    }

    private function createDocument(array $attributes = []): Document
    {
        $defaultUploader = User::factory()->create([
            'unit_id' => $attributes['unit_id'] ?? $this->unitA->id,
            'is_active' => true,
        ]);

        return Document::create(array_merge([
            'title' => 'Dokumen Uji Sistem',
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

    public function test_unauthenticated_user_cannot_access_documents_list(): void
    {
        $response = $this->get('/admin/documents');

        $response->assertRedirect('/admin/login');
    }

    public function test_user_with_permission_can_render_document_list_page(): void
    {
        $user = User::factory()->create([
            'unit_id' => $this->unitA->id,
            'is_active' => true,
        ]);
        $user->assignRole($this->roleStaff);

        $this->actingAs($user);

        $response = $this->get('/admin/documents');
        $response->assertSuccessful();

        Livewire::test(ListDocuments::class)
            ->assertSuccessful();
    }

    public function test_document_list_only_shows_unit_scoped_documents_for_regular_staff(): void
    {
        $userA = User::factory()->create([
            'unit_id' => $this->unitA->id,
            'is_active' => true,
        ]);
        $userA->assignRole($this->roleStaff);

        $docUnitA = $this->createDocument([
            'title' => 'Dokumen Internal Unit A',
            'unit_id' => $this->unitA->id,
            'visibility' => VisibilityLevel::Internal,
        ]);

        $docUnitB = $this->createDocument([
            'title' => 'Dokumen Internal Unit B',
            'unit_id' => $this->unitB->id,
            'visibility' => VisibilityLevel::Internal,
        ]);

        $docPublicB = $this->createDocument([
            'title' => 'Dokumen Publik Unit B',
            'unit_id' => $this->unitB->id,
            'visibility' => VisibilityLevel::Public,
        ]);

        $this->actingAs($userA);

        Livewire::test(ListDocuments::class)
            ->assertCanSeeTableRecords([$docUnitA, $docPublicB])
            ->assertCanNotSeeTableRecords([$docUnitB]);
    }

    public function test_documents_table_renders_row_index_and_columns_correctly(): void
    {
        $user = User::factory()->create([
            'unit_id' => $this->unitA->id,
            'is_active' => true,
        ]);
        $user->assignRole($this->roleStaff);

        $this->createDocument([
            'title' => 'Surat Pengantar Magang Mahasiswa',
            'document_number' => '005/UNIVBI.TI/SK/XI/2026',
            'unit_id' => $this->unitA->id,
            'visibility' => VisibilityLevel::Internal,
            'status' => DocumentStatus::Approved,
            'has_expiry' => false,
            'is_borang' => true,
        ]);

        $this->createDocument([
            'title' => 'MoU Mitra Industri Berlaku Terbatas',
            'document_number' => '006/UNIVBI.TI/MOU/XI/2026',
            'unit_id' => $this->unitA->id,
            'visibility' => VisibilityLevel::Internal,
            'status' => DocumentStatus::Approved,
            'has_expiry' => true,
            'expired_date' => '2027-12-15',
            'is_borang' => false,
        ]);

        $this->actingAs($user);

        Livewire::test(ListDocuments::class)
            ->assertCanRenderTableColumn('index')
            ->assertCanRenderTableColumn('document_number')
            ->assertCanRenderTableColumn('title')
            ->assertCanRenderTableColumn('unit.name')
            ->assertCanRenderTableColumn('visibility')
            ->assertCanRenderTableColumn('status')
            ->assertCanRenderTableColumn('expired_date')
            ->assertCanRenderTableColumn('is_borang')
            ->assertSee('Permanen')
            ->assertSee('15 Dec 2027')
            ->assertTableColumnExists('index')
            ->assertTableFilterExists('unit_id')
            ->assertTableFilterExists('document_category_id')
            ->assertTableFilterExists('academic_year_id')
            ->assertTableFilterExists('status')
            ->assertTableFilterExists('visibility')
            ->assertTableFilterExists('is_borang')
            ->assertTableFilterExists('has_expiry');
    }

    public function test_documents_table_uses_simple_pagination_with_10_default_per_page_and_eager_loads_relations(): void
    {
        $user = User::factory()->create([
            'unit_id' => $this->unitA->id,
            'is_active' => true,
        ]);
        $user->assignRole($this->roleStaff);

        for ($i = 1; $i <= 12; $i++) {
            $this->createDocument([
                'title' => "Dokumen Uji Simple Pagination {$i}",
                'unit_id' => $this->unitA->id,
                'visibility' => VisibilityLevel::Internal,
                'status' => DocumentStatus::Pending,
            ]);
        }

        $this->actingAs($user);

        $component = Livewire::test(ListDocuments::class)
            ->assertSuccessful();

        $instance = $component->instance();
        $table = $instance->getTable();
        $records = $instance->getTableRecords();

        $this->assertSame(PaginationMode::Simple, $table->getPaginationMode());
        $this->assertSame(10, $table->getDefaultPaginationPageOption());
        $this->assertSame([5, 10, 25, 50], $table->getPaginationPageOptions());
        $this->assertSame('500ms', $table->getSearchDebounce());

        $this->assertInstanceOf(Paginator::class, $records);
        $this->assertNotInstanceOf(LengthAwarePaginator::class, $records);
        $this->assertCount(10, $records->items());

        $firstRecord = $records->items()[0];
        $this->assertTrue($firstRecord->relationLoaded('unit'));
        $this->assertTrue($firstRecord->relationLoaded('category'));
        $this->assertTrue($firstRecord->relationLoaded('academicYear'));
        $this->assertTrue($firstRecord->relationLoaded('primaryFile'));
        $this->assertTrue($firstRecord->relationLoaded('approvals'));
        $this->assertArrayHasKey('files_exists', $firstRecord->getAttributes());
    }

    public function test_user_with_bypass_scope_can_see_all_documents(): void
    {
        $userRektor = User::factory()->create([
            'unit_id' => $this->unitA->id,
            'is_active' => true,
        ]);
        $userRektor->assignRole($this->roleRektor);

        $docUnitA = $this->createDocument([
            'title' => 'Dokumen Internal Unit A',
            'unit_id' => $this->unitA->id,
            'visibility' => VisibilityLevel::Internal,
        ]);

        $docUnitB = $this->createDocument([
            'title' => 'Dokumen Internal Unit B',
            'unit_id' => $this->unitB->id,
            'visibility' => VisibilityLevel::Internal,
        ]);

        $this->actingAs($userRektor);

        Livewire::test(ListDocuments::class)
            ->assertCanSeeTableRecords([$docUnitA, $docUnitB]);
    }

    public function test_user_can_create_document_with_file_upload_and_auto_document_file_created(): void
    {
        Storage::fake('private');

        $user = User::factory()->create([
            'unit_id' => $this->unitA->id,
            'is_active' => true,
        ]);
        $user->assignRole($this->roleStaff);

        $this->actingAs($user);

        $fakeFile = UploadedFile::fake()->create('sk_dosen.pdf', 1024, 'application/pdf');

        Livewire::test(CreateDocument::class)
            ->fillForm([
                'title' => 'SK Dosen Pembimbing Skripsi 2026',
                'unit_id' => $this->unitA->id,
                'document_category_id' => $this->category->id,
                'academic_year_id' => $this->academicYear->id,
                'visibility' => VisibilityLevel::Internal,
                'attachment' => $fakeFile,
                'issued_date' => '2026-11-10',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $document = Document::where('title', 'SK Dosen Pembimbing Skripsi 2026')->first();
        $this->assertNotNull($document);
        $this->assertSame($this->unitA->id, $document->unit_id);
        $this->assertSame($user->id, $document->uploaded_by);

        // Check DocumentFile record automatically generated
        $primaryFile = $document->primaryFile;
        $this->assertNotNull($primaryFile);
        $this->assertTrue($primaryFile->is_primary);
        $this->assertSame('private', $primaryFile->disk->value);
        $this->assertSame('pdf', $primaryFile->file_extension);
        $this->assertStringContainsString('2026-TI-SK-sk-dosen-pembimbing-skripsi-2026.pdf', $primaryFile->standardized_file_name);

        Storage::disk('private')->assertExists($primaryFile->original_file_path);
    }

    public function test_user_can_create_document_with_dropdown_reminder_recipients_from_registered_users(): void
    {
        Storage::fake('private');

        $user = User::factory()->create([
            'name' => 'Budi Staf',
            'email' => 'budi@univbi.ac.id',
            'unit_id' => $this->unitA->id,
            'is_active' => true,
        ]);
        $user->assignRole($this->roleStaff);

        $recipientUser = User::factory()->create([
            'name' => 'Dr. Dekan',
            'email' => 'dekan@univbi.ac.id',
            'position_title' => 'Dekan Fakultas',
            'unit_id' => $this->unitA->id,
            'is_active' => true,
        ]);

        $expiryCategory = DocumentCategory::create([
            'code' => 'MOU_TEST',
            'name' => 'Nota Kesepahaman Uji',
            'slug' => 'nota-kesepahaman-uji',
            'stamping_position' => StampingPosition::TopRight,
            'requires_approval' => true,
            'has_expiry' => true,
            'is_active' => true,
        ]);

        $this->actingAs($user);

        $fakeFile = UploadedFile::fake()->create('dokumen_mou.pdf', 500, 'application/pdf');

        Livewire::test(CreateDocument::class)
            ->fillForm([
                'title' => 'Perjanjian Kerjasama Industri 2026',
                'unit_id' => $this->unitA->id,
                'document_category_id' => $expiryCategory->id,
                'academic_year_id' => $this->academicYear->id,
                'visibility' => VisibilityLevel::Internal,
                'attachment' => $fakeFile,
                'issued_date' => '2026-11-10',
                'expired_date' => '2028-11-10',
                'reminder_intervals' => ['h60', 'h30'],
                'reminder_recipients' => [$recipientUser->id],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $document = Document::where('title', 'Perjanjian Kerjasama Industri 2026')->first();
        $this->assertNotNull($document);
        $this->assertTrue($document->has_expiry);
        $this->assertSame(['h60', 'h30'], $document->reminder_intervals);
        $this->assertSame([$recipientUser->id], $document->reminder_recipients);

        // Verifikasi bahwa pemanggilan helper email pengingat selalu sinkron saat user berganti email
        $this->assertSame([$recipientUser->email], $document->getReminderRecipientEmails());
        $recipientUser->update(['email' => 'dekan-baru@univbi.ac.id']);
        $this->assertSame(['dekan-baru@univbi.ac.id'], $document->fresh()->getReminderRecipientEmails());
    }

    public function test_user_can_edit_document_and_upload_new_version(): void
    {
        Storage::fake('private');

        $user = User::factory()->create([
            'unit_id' => $this->unitA->id,
            'is_active' => true,
        ]);
        $user->assignRole($this->roleStaff);

        $document = $this->createDocument([
            'unit_id' => $this->unitA->id,
            'uploaded_by' => $user->id,
            'title' => 'Dokumen Versi Awal',
        ]);

        $initialPath = 'documents/2026/11/TI/initial.pdf';
        Storage::disk('private')->put($initialPath, 'dummy content version 1');

        $fileV1 = DocumentFile::create([
            'document_id' => $document->id,
            'uploaded_by' => $user->id,
            'is_primary' => true,
            'version' => 1,
            'original_file_name' => 'initial.pdf',
            'standardized_file_name' => '2026-TI-SK-dokumen-versi-awal.pdf',
            'original_file_path' => $initialPath,
            'disk' => 'private',
            'mime_type' => 'application/pdf',
            'file_extension' => 'pdf',
            'file_size' => 100,
            'is_active' => true,
        ]);

        $this->actingAs($user);

        $newFile = UploadedFile::fake()->create('revised.pdf', 200, 'application/pdf');

        Livewire::test(EditDocument::class, ['record' => $document->getKey()])
            ->fillForm([
                'title' => 'Dokumen Versi Revisi',
                'attachment' => $newFile,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $fileV1->refresh();
        $this->assertFalse($fileV1->is_active);

        $document->refresh();
        $this->assertSame('Dokumen Versi Revisi', $document->title);

        $fileV2 = $document->primaryFile;
        $this->assertNotNull($fileV2);
        $this->assertSame(2, $fileV2->version);
        $this->assertTrue($fileV2->is_active);
    }

    public function test_user_can_update_document_reminder_recipients_and_preserve_user_ids(): void
    {
        $user = User::factory()->create([
            'unit_id' => $this->unitA->id,
            'is_active' => true,
        ]);
        $user->assignRole($this->roleStaff);

        $recipient1 = User::factory()->create([
            'name' => 'Kajur TI',
            'email' => 'kajur@univbi.ac.id',
            'unit_id' => $this->unitA->id,
            'is_active' => true,
        ]);

        $recipient2 = User::factory()->create([
            'name' => 'Sekjur TI',
            'email' => 'sekjur@univbi.ac.id',
            'unit_id' => $this->unitA->id,
            'is_active' => true,
        ]);

        $expiryCategory = DocumentCategory::create([
            'code' => 'MOU_EDIT',
            'name' => 'MoU Edit Uji',
            'slug' => 'mou-edit-uji',
            'requires_approval' => true,
            'has_expiry' => true,
            'is_active' => true,
        ]);

        $document = $this->createDocument([
            'unit_id' => $this->unitA->id,
            'document_category_id' => $expiryCategory->id,
            'uploaded_by' => $user->id,
            'has_expiry' => true,
            'expired_date' => '2028-12-31',
            'reminder_intervals' => ['h30'],
            'reminder_recipients' => [$recipient1->id],
        ]);

        $this->actingAs($user);

        Livewire::test(EditDocument::class, ['record' => $document->getKey()])
            ->assertSchemaStateSet([
                'reminder_recipients' => [$recipient1->id],
            ])
            ->fillForm([
                'reminder_recipients' => [$recipient1->id, $recipient2->id],
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $document->refresh();
        $this->assertSame([$recipient1->id, $recipient2->id], $document->reminder_recipients);
        $this->assertSame(['kajur@univbi.ac.id', 'sekjur@univbi.ac.id'], $document->getReminderRecipientEmails());
    }

    public function test_user_can_soft_delete_and_restore_document(): void
    {
        $user = User::factory()->create([
            'unit_id' => $this->unitA->id,
            'is_active' => true,
        ]);
        $user->assignRole($this->roleStaff);

        $document = $this->createDocument([
            'unit_id' => $this->unitA->id,
            'uploaded_by' => $user->id,
        ]);

        $this->actingAs($user);

        // Soft delete from table
        Livewire::test(ListDocuments::class)
            ->callTableAction('delete', $document);

        $this->assertSoftDeleted('documents', ['id' => $document->id]);

        // Access trash page
        $response = $this->get('/admin/documents/trash');
        $response->assertSuccessful();

        // Restore from trash
        Livewire::test(TrashDocuments::class)
            ->callTableAction('restore', $document);

        $this->assertNotSoftDeleted('documents', ['id' => $document->id]);
    }

    public function test_document_with_replacement_relation_is_blocked_from_deletion(): void
    {
        $user = User::factory()->create([
            'unit_id' => $this->unitA->id,
            'is_active' => true,
        ]);
        $user->assignRole($this->roleStaff);

        $docA = $this->createDocument([
            'title' => 'Dokumen Induk Lama',
            'unit_id' => $this->unitA->id,
            'uploaded_by' => $user->id,
        ]);

        $docB = $this->createDocument([
            'title' => 'Dokumen Baru Pengganti A',
            'unit_id' => $this->unitA->id,
            'uploaded_by' => $user->id,
            'replaces_document_id' => $docA->id,
        ]);

        $this->actingAs($user);

        // Attempting to delete docA must be blocked by DeletionGuard
        Livewire::test(ListDocuments::class)
            ->callTableAction('delete', $docA);

        $this->assertNotSoftDeleted('documents', ['id' => $docA->id]);
    }

    public function test_manual_archive_action_is_removed_from_edit_and_view_document_pages(): void
    {
        $user = User::factory()->create([
            'unit_id' => $this->unitA->id,
            'is_active' => true,
        ]);
        $user->assignRole($this->roleRektor);

        $document = $this->createDocument([
            'title' => 'SK Yudisium Sah',
            'unit_id' => $this->unitA->id,
            'uploaded_by' => $user->id,
            'document_number' => '001/UNIVBI.TI/SK/XI/2026',
            'status' => DocumentStatus::Approved,
            'approved_at' => now(),
        ]);

        $this->actingAs($user);

        Livewire::test(EditDocument::class, ['record' => $document->getRouteKey()])
            ->assertActionDoesNotExist('archive');

        Livewire::test(ViewDocument::class, ['record' => $document->getKey()])
            ->assertActionDoesNotExist('archive');
    }

    public function test_replaced_document_auto_archives_only_when_replacement_is_approved(): void
    {
        $rektor = User::factory()->create([
            'unit_id' => $this->unitA->id,
            'is_active' => true,
            'position_title' => 'Rektor Universitas Bina Insan',
        ]);
        $rektor->assignRole($this->roleRektor);

        $approvalCategory = DocumentCategory::create([
            'name' => 'Keputusan Pengesahan Berjenjang',
            'slug' => 'keputusan-pengesahan-berjenjang',
            'code' => 'KEP',
            'number_format' => '{seq}/UNIVBI.{unit}/{cat}/{roman_month}/{year}',
            'requires_approval' => true,
            'has_expiry' => false,
            'is_active' => true,
        ]);

        CategoryApprovalFlow::create([
            'document_category_id' => $approvalCategory->id,
            'step_order' => 1,
            'approver_role' => 'rektor',
            'step_label' => 'Pengesahan Rektor',
        ]);

        $oldDoc = $this->createDocument([
            'title' => 'SK Pedoman Lama 2024',
            'unit_id' => $this->unitA->id,
            'document_category_id' => $approvalCategory->id,
            'uploaded_by' => $rektor->id,
            'document_number' => '001/UNIVBI.TI/KEP/XI/2024',
            'status' => DocumentStatus::Approved,
            'approved_at' => now()->subYear(),
        ]);

        $replacementDoc = $this->createDocument([
            'title' => 'SK Pedoman Baru 2026',
            'unit_id' => $this->unitA->id,
            'document_category_id' => $approvalCategory->id,
            'uploaded_by' => $rektor->id,
            'replaces_document_id' => $oldDoc->id,
            'status' => DocumentStatus::Draft,
            'is_manual_number' => false,
        ]);

        $approvalService = app(DocumentApprovalService::class);
        $approvalService->initializeApprovalFlow($replacementDoc);

        // Selama pengganti masih Pending, dokumen lama wajib tetap Approved
        $this->assertSame(DocumentStatus::Approved, $oldDoc->fresh()->status);

        $this->actingAs($rektor);
        $approvalService->approve($replacementDoc->fresh(), $rektor, 'Disahkan sebagai pengganti');

        $replacementFresh = $replacementDoc->fresh();
        $oldFresh = $oldDoc->fresh();

        $this->assertSame(DocumentStatus::Approved, $replacementFresh->status);
        $this->assertNotEmpty($replacementFresh->document_number);
        $this->assertSame(DocumentStatus::Archived, $oldFresh->status);

        $this->assertDatabaseHas('activity_log', [
            'subject_type' => Document::class,
            'subject_id' => $oldDoc->id,
            'event' => 'archived',
        ]);
    }

    public function test_replaced_document_auto_archives_when_zero_step_replacement_is_auto_approved(): void
    {
        $user = User::factory()->create([
            'unit_id' => $this->unitA->id,
            'is_active' => true,
        ]);
        $user->assignRole($this->roleRektor);
        $this->actingAs($user);

        $autoCategory = DocumentCategory::create([
            'name' => 'Surat Edaran Tanpa Antrean',
            'slug' => 'surat-edaran-tanpa-antrean',
            'code' => 'SE0',
            'number_format' => '{seq}/UNIVBI.{unit}/{cat}/{roman_month}/{year}',
            'requires_approval' => false,
            'has_expiry' => false,
            'is_active' => true,
        ]);

        $oldDoc = $this->createDocument([
            'title' => 'SE Lama 2025',
            'unit_id' => $this->unitA->id,
            'document_category_id' => $autoCategory->id,
            'uploaded_by' => $user->id,
            'document_number' => '001/UNIVBI.TI/SE0/XI/2025',
            'status' => DocumentStatus::Approved,
            'approved_at' => now()->subMonth(),
        ]);

        $newDoc = $this->createDocument([
            'title' => 'SE Baru 2026',
            'unit_id' => $this->unitA->id,
            'document_category_id' => $autoCategory->id,
            'uploaded_by' => $user->id,
            'replaces_document_id' => $oldDoc->id,
            'status' => DocumentStatus::Draft,
            'is_manual_number' => false,
        ]);

        app(DocumentApprovalService::class)->initializeApprovalFlow($newDoc);

        $this->assertSame(DocumentStatus::Approved, $newDoc->fresh()->status);
        $this->assertSame(DocumentStatus::Archived, $oldDoc->fresh()->status);
    }

    public function test_list_documents_archived_expired_tab_has_expiry_filter_and_category_sync_work(): void
    {
        $user = User::factory()->create([
            'unit_id' => $this->unitA->id,
            'is_active' => true,
        ]);
        $user->assignRole($this->roleRektor);

        $docActive = $this->createDocument([
            'title' => 'Dokumen Aktif Sah',
            'unit_id' => $this->unitA->id,
            'status' => DocumentStatus::Approved,
            'has_expiry' => true,
            'expired_date' => now()->addMonths(3)->toDateString(),
        ]);

        $docArchived = $this->createDocument([
            'title' => 'Dokumen Pasif Arsip',
            'unit_id' => $this->unitA->id,
            'status' => DocumentStatus::Archived,
            'has_expiry' => false,
        ]);

        $docExpired = $this->createDocument([
            'title' => 'Dokumen Kedaluwarsa',
            'unit_id' => $this->unitA->id,
            'status' => DocumentStatus::Expired,
            'has_expiry' => true,
            'expired_date' => now()->subDay()->toDateString(),
        ]);

        $this->actingAs($user);

        Livewire::test(ListDocuments::class)
            ->set('activeTab', 'archived_expired')
            ->assertCanSeeTableRecords([$docArchived, $docExpired])
            ->assertCanNotSeeTableRecords([$docActive])
            ->set('activeTab', 'all')
            ->filterTable('has_expiry', false)
            ->assertCanSeeTableRecords([$docArchived])
            ->assertCanNotSeeTableRecords([$docActive, $docExpired]);

        // Test sinkronisasi penuh has_expiry saat berganti kategori di CreateDocument
        $expiryCategory = DocumentCategory::create([
            'name' => 'MoU Sementara',
            'slug' => 'mou-sementara',
            'code' => 'MOUS',
            'has_expiry' => true,
            'is_active' => true,
        ]);

        $permanentCategory = DocumentCategory::create([
            'name' => 'Pedoman Tetap',
            'slug' => 'pedoman-tetap',
            'code' => 'PED',
            'has_expiry' => false,
            'is_active' => true,
        ]);

        Livewire::test(CreateDocument::class)
            ->fillForm(['document_category_id' => $expiryCategory->id])
            ->assertSchemaStateSet(['has_expiry' => true])
            ->fillForm(['document_category_id' => $permanentCategory->id])
            ->assertSchemaStateSet(['has_expiry' => false]);
    }

    public function test_view_document_renders_expiry_reminder_tab_when_document_has_expiry(): void
    {
        $user = User::factory()->create([
            'unit_id' => $this->unitA->id,
            'is_active' => true,
        ]);
        $user->assignRole($this->roleRektor);

        $document = $this->createDocument([
            'title' => 'MoU Kerja Sama Mitra Industri',
            'unit_id' => $this->unitA->id,
            'uploaded_by' => $user->id,
            'document_number' => '005/UNIVBI.TI/MOU/XI/2026',
            'status' => DocumentStatus::Approved,
            'has_expiry' => true,
            'issued_date' => now()->toDateString(),
            'expired_date' => now()->addDays(25)->toDateString(),
            'reminder_intervals' => ['h30'],
            'reminder_recipients' => [$user->id],
        ]);

        ReminderLog::create([
            'document_id' => $document->id,
            'recipient_user_id' => $user->id,
            'recipient_email' => $user->email,
            'interval_type' => ReminderInterval::H30,
            'sent_date' => now()->toDateString(),
            'expired_date_snapshot' => now()->addDays(25)->toDateString(),
            'sent_at' => now(),
            'is_success' => true,
        ]);

        $this->actingAs($user);

        Livewire::test(ViewDocument::class, ['record' => $document->getKey()])
            ->assertSuccessful()
            ->assertSee('Pengingat Masa Berlaku')
            ->assertSee('Jadwal Interval Notifikasi')
            ->assertSee('Tersisa 25 Hari')
            ->assertSee($user->email)
            ->assertSee('Terkirim Sukses');
    }

    public function test_reminder_recipients_field_is_disabled_for_user_without_manage_recipients_permission(): void
    {
        $limitedRole = Role::findOrCreate('staf_biasa', 'web');
        $limitedRole->syncPermissions([
            'view_any:document',
            'view:document',
            'create:document',
            'update:document',
        ]);

        $user = User::factory()->create([
            'unit_id' => $this->unitA->id,
            'is_active' => true,
        ]);
        $user->assignRole($limitedRole);

        $expiryCategory = DocumentCategory::create([
            'code' => 'MOU_PERM',
            'name' => 'MoU Izin Uji',
            'slug' => 'mou-izin-uji',
            'has_expiry' => true,
            'is_active' => true,
        ]);

        $this->actingAs($user);

        Livewire::test(CreateDocument::class)
            ->fillForm([
                'document_category_id' => $expiryCategory->id,
            ])
            ->assertFormFieldIsDisabled('reminder_recipients');
    }

    public function test_category_strictly_enforces_expiry_visibility_requirement_and_server_side_guard(): void
    {
        Storage::fake('private');

        $user = User::factory()->create([
            'unit_id' => $this->unitA->id,
            'is_active' => true,
        ]);
        $user->assignRole($this->roleStaff);

        $expiryCategory = DocumentCategory::create([
            'code' => 'MOU_STRICT',
            'name' => 'MoU Wajib Masa Berlaku',
            'slug' => 'mou-wajib-masa-berlaku',
            'has_expiry' => true,
            'is_active' => true,
        ]);

        $permanentCategory = DocumentCategory::create([
            'code' => 'SK_STRICT',
            'name' => 'SK Tetap Tanpa Kedaluwarsa',
            'slug' => 'sk-tetap-tanpa-kedaluwarsa',
            'has_expiry' => false,
            'is_active' => true,
        ]);

        $this->actingAs($user);

        // 1. Kategori has_expiry = true -> toggle has_expiry dikunci, expired_date tampil & wajib diisi
        Livewire::test(CreateDocument::class)
            ->fillForm([
                'title' => 'MoU Tanpa Tanggal Berakhir',
                'unit_id' => $this->unitA->id,
                'document_category_id' => $expiryCategory->id,
                'visibility' => VisibilityLevel::Internal,
                'issued_date' => '2026-11-16',
                'attachment' => UploadedFile::fake()->create('mou.pdf', 200, 'application/pdf'),
            ])
            ->assertFormFieldIsDisabled('has_expiry')
            ->assertFormFieldIsVisible('expired_date')
            ->call('create')
            ->assertHasFormErrors(['expired_date' => 'required']);

        // 2. Kategori has_expiry = false -> toggle dikunci, expired_date disembunyikan & server memaksa false/null
        Livewire::test(CreateDocument::class)
            ->fillForm([
                'title' => 'SK Tetap Tanpa Masa Berlaku',
                'unit_id' => $this->unitA->id,
                'document_category_id' => $permanentCategory->id,
                'visibility' => VisibilityLevel::Internal,
                'issued_date' => '2026-11-16',
                'has_expiry' => true,
                'expired_date' => '2028-11-16',
                'attachment' => UploadedFile::fake()->create('sk.pdf', 200, 'application/pdf'),
            ])
            ->assertFormFieldIsDisabled('has_expiry')
            ->assertFormFieldIsHidden('expired_date')
            ->call('create')
            ->assertHasNoFormErrors();

        $savedPermanent = Document::where('title', 'SK Tetap Tanpa Masa Berlaku')->firstOrFail();
        $this->assertFalse($savedPermanent->has_expiry);
        $this->assertNull($savedPermanent->expired_date);
    }

    public function test_replaces_document_select_uses_lazy_search_without_preload_and_excludes_self(): void
    {
        $user = User::factory()->create([
            'unit_id' => $this->unitA->id,
            'is_active' => true,
        ]);
        $user->assignRole($this->roleRektor);

        $docOne = $this->createDocument([
            'title' => 'SK Rektor Kalender Akademik 2025',
            'document_number' => '101/UNIVBI.TI/SK/XI/2025',
            'unit_id' => $this->unitA->id,
            'status' => DocumentStatus::Approved,
        ]);

        $docTwo = $this->createDocument([
            'title' => 'SK Rektor Perubahan Kalender 2026',
            'document_number' => '102/UNIVBI.TI/SK/XI/2026',
            'unit_id' => $this->unitA->id,
            'status' => DocumentStatus::Approved,
        ]);

        $this->actingAs($user);

        $editTest = Livewire::test(EditDocument::class, ['record' => $docTwo->getRouteKey()]);
        $select = $editTest->instance()->form->getFlatFields(withHidden: true)['replaces_document_id'];

        $this->assertFalse($select->isPreloaded());
        $this->assertSame(25, $select->getOptionsLimit());

        $resultsByNumber = $select->getSearchResults('101/UNIVBI');
        $this->assertArrayHasKey($docOne->id, $resultsByNumber);
        $this->assertArrayNotHasKey($docTwo->id, $resultsByNumber);

        $resultsSelfSearch = $select->getSearchResults('102/UNIVBI');
        $this->assertArrayNotHasKey($docTwo->id, $resultsSelfSearch);
    }

    public function test_documents_table_search_and_expired_date_sorting_work(): void
    {
        $user = User::factory()->create([
            'unit_id' => $this->unitA->id,
            'is_active' => true,
        ]);
        $user->assignRole($this->roleRektor);

        $docAlpha = $this->createDocument([
            'title' => 'Pedoman Penjaminan Mutu Internal',
            'document_number' => '701/UNIVBI.TI/PED/XI/2026',
            'unit_id' => $this->unitA->id,
            'status' => DocumentStatus::Approved,
            'has_expiry' => true,
            'expired_date' => '2027-01-15',
        ]);

        $docBeta = $this->createDocument([
            'title' => 'Peraturan Praktikum Laboratorium',
            'document_number' => '702/UNIVBI.SI/PER/XI/2026',
            'unit_id' => $this->unitB->id,
            'status' => DocumentStatus::Approved,
            'has_expiry' => true,
            'expired_date' => '2028-06-30',
        ]);

        $this->actingAs($user);

        Livewire::test(ListDocuments::class)
            ->searchTable('Penjaminan Mutu')
            ->assertCanSeeTableRecords([$docAlpha])
            ->assertCanNotSeeTableRecords([$docBeta])
            ->searchTable('702/UNIVBI.SI')
            ->assertCanSeeTableRecords([$docBeta])
            ->assertCanNotSeeTableRecords([$docAlpha])
            ->searchTable('Sistem Informasi')
            ->assertCanSeeTableRecords([$docBeta])
            ->assertCanNotSeeTableRecords([$docAlpha])
            ->searchTable('')
            ->sortTable('expired_date', 'asc')
            ->assertCanSeeTableRecords([$docAlpha, $docBeta], inOrder: true);
    }

    public function test_selecting_category_in_create_document_displays_copyable_flags_for_uploader(): void
    {
        $user = User::factory()->create([
            'unit_id' => $this->unitA->id,
            'is_active' => true,
        ]);
        $user->assignRole($this->roleStaff);

        $customCategory = DocumentCategory::create([
            'code' => 'STG',
            'name' => 'Surat Tugas',
            'slug' => 'surat-tugas',
            'number_format' => '{seq}/UNIVBI.{unit}/{cat}/{roman_month}/{year}',
            'stamping_position' => StampingPosition::TopRight,
            'requires_approval' => true,
            'is_active' => true,
        ]);

        $this->actingAs($user);

        Livewire::test(CreateDocument::class)
            ->assertSuccessful()
            ->assertDontSee('Daftar Flag Otomatis untuk Naskah Word')
            ->set('data.document_category_id', $customCategory->id)
            ->assertSee('Daftar Flag Otomatis untuk Naskah Word')
            ->assertSee('{seq}/UNIVBI.{unit}/{cat}/{roman_month}/{year}')
            ->assertSee('${NOMOR_SURAT}')
            ->assertSee('${TANGGAL_SAH}')
            ->assertSee('${PEJABAT}')
            ->assertSee('${VERIFY_URL}')
            ->assertSee('${UUID}')
            ->assertSee('command="--copy"', false)
            ->assertSee("commandfor=\"doc-flag-{$customCategory->id}-pattern-category\"", false);
    }

    public function test_create_document_filters_categories_by_selected_unit_and_validates_server_side(): void
    {
        Storage::fake('private');

        // Kategori eksklusif Unit B (SI) dan eksklusif Unit A (TI)
        $onlyUnitBCategory = DocumentCategory::create([
            'code' => 'KHSI',
            'name' => 'Dokumen Khusus Prodi SI',
            'slug' => 'dokumen-khusus-prodi-si',
            'stamping_position' => StampingPosition::TopRight,
            'requires_approval' => false,
            'is_active' => true,
        ]);
        $onlyUnitBCategory->allowedUnits()->attach($this->unitB->id);

        $onlyUnitACategory = DocumentCategory::create([
            'code' => 'KHTI',
            'name' => 'Dokumen Khusus Prodi TI',
            'slug' => 'dokumen-khusus-prodi-ti',
            'stamping_position' => StampingPosition::TopRight,
            'requires_approval' => false,
            'is_active' => true,
        ]);
        $onlyUnitACategory->allowedUnits()->attach($this->unitA->id);

        $staffUnitA = User::factory()->create([
            'unit_id' => $this->unitA->id,
            'is_active' => true,
        ]);
        $staffUnitA->assignRole($this->roleStaff);

        $this->actingAs($staffUnitA);

        // Dropdown untuk unit A harus menampilkan kategori global ($this->category) & khusus unit A, bukan unit B
        Livewire::test(CreateDocument::class)
            ->assertSuccessful()
            ->assertSee($this->category->name)
            ->assertSee($onlyUnitACategory->name)
            ->assertDontSee($onlyUnitBCategory->name);

        // Upaya paksa mengisi ID kategori milik Unit B harus ditolak validasi server-side
        $fakeFile = UploadedFile::fake()->create('test-unit-mismatch.pdf', 512, 'application/pdf');
        Livewire::test(CreateDocument::class)
            ->fillForm([
                'title' => 'Uji Kategori Lintas Unit Ditolak',
                'unit_id' => $this->unitA->id,
                'document_category_id' => $onlyUnitBCategory->id,
                'academic_year_id' => $this->academicYear->id,
                'visibility' => VisibilityLevel::Internal,
                'attachment' => $fakeFile,
                'issued_date' => '2026-11-19',
            ])
            ->call('create')
            ->assertHasFormErrors(['document_category_id']);

        // User dengan bypass_unit_scope ketika berpindah unit dari A ke B: kategori eksklusif A otomatis di-reset
        $userRektor = User::factory()->create([
            'unit_id' => $this->unitA->id,
            'is_active' => true,
        ]);
        $userRektor->assignRole($this->roleRektor);

        $this->actingAs($userRektor);

        Livewire::test(CreateDocument::class)
            ->set('data.document_category_id', $onlyUnitACategory->id)
            ->set('data.unit_id', $this->unitB->id)
            ->assertSet('data.document_category_id', null)
            ->assertSee($onlyUnitBCategory->name)
            ->assertDontSee($onlyUnitACategory->name);
    }

    public function test_category_default_visibility_and_disallowed_manual_number_policy_are_enforced(): void
    {
        Storage::fake('private');

        $strictAutoCategory = DocumentCategory::create([
            'code' => 'SEAUTO',
            'name' => 'Surat Edaran Penomoran Otomatis Wajib',
            'slug' => 'se-penomoran-otomatis-wajib',
            'default_visibility' => VisibilityLevel::Public,
            'allow_manual_number' => false,
            'requires_wet_signature' => true,
            'requires_approval' => true,
            'is_active' => true,
        ]);

        CategoryApprovalFlow::create([
            'document_category_id' => $strictAutoCategory->id,
            'step_order' => 1,
            'approver_role' => 'rektor',
            'step_label' => 'Pengesahan Rektor',
            'is_mandatory' => true,
        ]);

        $userRektor = User::factory()->create([
            'unit_id' => $this->unitA->id,
            'is_active' => true,
        ]);
        $userRektor->assignRole($this->roleRektor);

        $this->actingAs($userRektor);

        $fakeFile = UploadedFile::fake()->create('se-publik.pdf', 512, 'application/pdf');

        Livewire::test(CreateDocument::class)
            ->set('data.document_category_id', $strictAutoCategory->id)
            ->assertSet('data.visibility', VisibilityLevel::Public->value)
            ->assertSet('data.requires_wet_signature', true)
            ->assertSet('data.is_manual_number', false)
            ->fillForm([
                'title' => 'Surat Edaran Kebijakan Publik 2026',
                'unit_id' => $this->unitA->id,
                'academic_year_id' => $this->academicYear->id,
                'is_manual_number' => true,
                'document_number' => '999/ILLEGAL/2026',
                'attachment' => $fakeFile,
                'issued_date' => '2026-11-19',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $doc = Document::where('title', 'Surat Edaran Kebijakan Publik 2026')->firstOrFail();
        $this->assertFalse($doc->is_manual_number);
        $this->assertNull($doc->document_number);
        $this->assertSame(VisibilityLevel::Public, $doc->visibility);
        $this->assertTrue($doc->requires_wet_signature);
    }
}
