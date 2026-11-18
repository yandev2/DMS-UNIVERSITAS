<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\DegreeLevel;
use App\Enums\DocumentStatus;
use App\Enums\StampingPosition;
use App\Enums\StorageDisk;
use App\Enums\UnitType;
use App\Enums\VisibilityLevel;
use App\Filament\Resources\Documents\Pages\ListDocuments;
use App\Filament\Resources\Documents\Pages\ViewDocument;
use App\Models\AcademicYear;
use App\Models\Document;
use App\Models\DocumentCategory;
use App\Models\DocumentFile;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DocumentStreamControllerTest extends TestCase
{
    use RefreshDatabase;

    private Unit $unitA;

    private Unit $unitB;

    private DocumentCategory $category;

    private AcademicYear $academicYear;

    private Role $roleStaff;

    private Role $roleRektor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->unitA = Unit::create([
            'code' => 'TI',
            'name' => 'Program Studi Informatika',
            'slug' => 'program-studi-informatika',
            'type' => UnitType::Prodi,
            'degree_level' => DegreeLevel::S1,
            'is_active' => true,
        ]);

        $this->unitB = Unit::create([
            'code' => 'SI',
            'name' => 'Program Studi Sistem Informasi',
            'slug' => 'program-studi-sistem-informasi',
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

    private function createDocumentWithFile(array $attributes = [], string $extension = 'pdf', string $disk = 'private'): array
    {
        Storage::fake($disk);

        $uploader = User::factory()->create([
            'unit_id' => $attributes['unit_id'] ?? $this->unitA->id,
            'is_active' => true,
        ]);

        $document = Document::create(array_merge([
            'title' => 'Dokumen Kerjasama Industri 2026',
            'unit_id' => $this->unitA->id,
            'document_category_id' => $this->category->id,
            'academic_year_id' => $this->academicYear->id,
            'uploaded_by' => $uploader->id,
            'issued_date' => '2026-11-10',
            'visibility' => VisibilityLevel::Internal,
            'status' => DocumentStatus::Approved,
            'is_borang' => false,
            'has_expiry' => false,
            'views_count' => 0,
            'downloads_count' => 0,
        ], $attributes));

        $fileName = 'test-file.'.$extension;
        $filePath = 'documents/2026/11/TI/'.$fileName;
        $fileContent = 'Dummy PDF or Word document content for testing';

        Storage::disk($disk)->put($filePath, $fileContent);

        $file = DocumentFile::create([
            'document_id' => $document->id,
            'uploaded_by' => $uploader->id,
            'is_primary' => true,
            'version' => 1,
            'original_file_name' => $fileName,
            'standardized_file_name' => '2026-TI-SK-dokumen-kerjasama.'.$extension,
            'original_file_path' => $filePath,
            'disk' => StorageDisk::from($disk),
            'mime_type' => $extension === 'pdf' ? 'application/pdf' : 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'file_extension' => $extension,
            'file_size' => strlen($fileContent),
            'is_active' => true,
        ]);

        return [$document, $file];
    }

    public function test_unauthenticated_user_is_redirected_to_login_on_stream_and_download(): void
    {
        [$document] = $this->createDocumentWithFile();

        $this->get(route('admin.documents.stream', $document))
            ->assertRedirect('/admin/login');

        $this->get(route('admin.documents.download', $document))
            ->assertRedirect('/admin/login');
    }

    public function test_user_cannot_stream_internal_document_of_another_unit(): void
    {
        [$document] = $this->createDocumentWithFile(['unit_id' => $this->unitA->id, 'visibility' => VisibilityLevel::Internal]);

        $userUnitB = User::factory()->create([
            'unit_id' => $this->unitB->id,
            'is_active' => true,
        ]);
        $userUnitB->assignRole($this->roleStaff);

        $this->actingAs($userUnitB);

        $this->get(route('admin.documents.stream', $document))
            ->assertForbidden();

        $this->get(route('admin.documents.download', $document))
            ->assertForbidden();
    }

    public function test_user_cannot_stream_confidential_document_without_permission(): void
    {
        [$document] = $this->createDocumentWithFile(['unit_id' => $this->unitA->id, 'visibility' => VisibilityLevel::Confidential]);

        $userStaffUnitA = User::factory()->create([
            'unit_id' => $this->unitA->id,
            'is_active' => true,
        ]);
        $userStaffUnitA->assignRole($this->roleStaff);

        $this->actingAs($userStaffUnitA);

        $this->get(route('admin.documents.stream', $document))
            ->assertForbidden();

        $this->get(route('admin.documents.download', $document))
            ->assertForbidden();
    }

    public function test_authorized_user_can_stream_document_and_view_count_increments(): void
    {
        [$document, $file] = $this->createDocumentWithFile();

        $userStaffUnitA = User::factory()->create([
            'unit_id' => $this->unitA->id,
            'is_active' => true,
        ]);
        $userStaffUnitA->assignRole($this->roleStaff);

        $this->actingAs($userStaffUnitA);

        $response = $this->get(route('admin.documents.stream', $document));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringContainsString('inline', (string) $response->headers->get('Content-Disposition'));
        $this->assertStringContainsString($file->standardized_file_name, (string) $response->headers->get('Content-Disposition'));

        $document->refresh();
        $this->assertSame(1, $document->views_count);
    }

    public function test_authorized_user_can_download_document_and_download_count_increments(): void
    {
        [$document, $file] = $this->createDocumentWithFile();

        $userStaffUnitA = User::factory()->create([
            'unit_id' => $this->unitA->id,
            'is_active' => true,
        ]);
        $userStaffUnitA->assignRole($this->roleStaff);

        $this->actingAs($userStaffUnitA);

        $response = $this->get(route('admin.documents.download', $document));

        $response->assertOk();
        $this->assertStringContainsString('attachment', (string) $response->headers->get('Content-Disposition'));
        $this->assertStringContainsString($file->standardized_file_name, (string) $response->headers->get('Content-Disposition'));

        $document->refresh();
        $this->assertSame(1, $document->downloads_count);
    }

    public function test_user_without_download_permission_cannot_download_document(): void
    {
        [$document] = $this->createDocumentWithFile();

        $userWithoutDownload = User::factory()->create([
            'unit_id' => $this->unitA->id,
            'is_active' => true,
        ]);
        // Beri izin view tapi cabut document.download_private
        $roleViewOnly = Role::create(['name' => 'view_only', 'guard_name' => 'web']);
        $roleViewOnly->syncPermissions(['view_any:document', 'view:document']);
        $userWithoutDownload->assignRole($roleViewOnly);

        $this->actingAs($userWithoutDownload);

        // Boleh stream inline
        $this->get(route('admin.documents.stream', $document))->assertOk();

        // Dilarang unduh
        $this->get(route('admin.documents.download', $document))->assertForbidden();
    }

    public function test_stream_and_download_return_404_if_file_record_or_physical_file_is_missing(): void
    {
        Storage::fake('private');

        $userStaffUnitA = User::factory()->create([
            'unit_id' => $this->unitA->id,
            'is_active' => true,
        ]);
        $userStaffUnitA->assignRole($this->roleStaff);

        // Dokumen tanpa DocumentFile
        $documentNoFile = Document::create([
            'title' => 'Dokumen Tanpa File',
            'unit_id' => $this->unitA->id,
            'document_category_id' => $this->category->id,
            'academic_year_id' => $this->academicYear->id,
            'uploaded_by' => $userStaffUnitA->id,
            'issued_date' => '2026-11-10',
            'visibility' => VisibilityLevel::Internal,
            'status' => DocumentStatus::Approved,
            'is_borang' => false,
            'has_expiry' => false,
        ]);

        $this->actingAs($userStaffUnitA);

        $this->get(route('admin.documents.stream', $documentNoFile))->assertNotFound();
        $this->get(route('admin.documents.download', $documentNoFile))->assertNotFound();

        // Dokumen dengan DocumentFile tapi file fisik di disk dihapus
        [$documentMissingDisk, $fileMissing] = $this->createDocumentWithFile();
        Storage::disk('private')->delete($fileMissing->original_file_path);

        $this->get(route('admin.documents.stream', $documentMissingDisk))->assertNotFound();
        $this->get(route('admin.documents.download', $documentMissingDisk))->assertNotFound();
    }

    public function test_preview_action_exists_in_table_and_view_page(): void
    {
        [$document] = $this->createDocumentWithFile();

        $userStaffUnitA = User::factory()->create([
            'unit_id' => $this->unitA->id,
            'is_active' => true,
        ]);
        $userStaffUnitA->assignRole($this->roleStaff);

        $this->actingAs($userStaffUnitA);

        Livewire::test(ListDocuments::class)
            ->assertTableActionExists('preview');

        Livewire::test(ViewDocument::class, ['record' => $document->getKey()])
            ->assertActionExists('preview')
            ->assertActionExists('download');
    }

    public function test_document_preview_modal_renders_pdf_iframe_when_docx_has_stamped_pdf(): void
    {
        [$document, $file] = $this->createDocumentWithFile([], 'docx');

        $stampedPdfPath = 'documents/2026/11/TI/stamped-test.pdf';
        Storage::disk('private')->put($stampedPdfPath, '%PDF-1.4 dummy stamped pdf');
        $file->update(['stamped_file_path' => $stampedPdfPath]);

        $userStaffUnitA = User::factory()->create([
            'unit_id' => $this->unitA->id,
            'is_active' => true,
        ]);
        $userStaffUnitA->assignRole($this->roleStaff);
        $this->actingAs($userStaffUnitA);

        $html = view('filament.components.document-preview-modal', [
            'document' => $document->fresh('primaryFile'),
            'streamUrl' => route('admin.documents.stream', $document),
            'downloadUrl' => route('admin.documents.download', $document),
        ])->render();

        $this->assertStringContainsString('<iframe', $html);
        $this->assertStringContainsString('DOCX &rarr; PDF', $html);
        $this->assertStringNotContainsString('data-word-preview-root', $html);
    }

    public function test_document_preview_modal_renders_docx_preview_stage_directly_without_pdf_conversion(): void
    {
        [$document] = $this->createDocumentWithFile([], 'docx');

        $userStaffUnitA = User::factory()->create([
            'unit_id' => $this->unitA->id,
            'is_active' => true,
        ]);
        $userStaffUnitA->assignRole($this->roleStaff);
        $this->actingAs($userStaffUnitA);

        $html = view('filament.components.document-preview-modal', [
            'document' => $document->fresh('primaryFile'),
            'streamUrl' => route('admin.documents.stream', $document),
            'downloadUrl' => route('admin.documents.download', $document),
        ])->render();

        $this->assertStringContainsString('data-word-preview-root', $html);
        $this->assertStringNotContainsString('<iframe', $html);
    }

    public function test_letter_preview_modal_renders_docx_preview_stage_directly(): void
    {
        $html = view('filament.components.letter-preview-modal', [
            'filePath' => 'outgoing-letters/draft-surat.docx',
            'fileName' => 'draft-surat.docx',
            'streamUrl' => '/admin/outgoing-letters-file/uuid/stream',
            'downloadUrl' => '/admin/outgoing-letters-file/uuid/download',
        ])->render();

        $this->assertStringContainsString('data-word-preview-root', $html);
        $this->assertStringNotContainsString('<iframe', $html);
    }
}
