<?php

declare(strict_types=1);

namespace Tests\Feature\Filament;

use App\Enums\DegreeLevel;
use App\Enums\DocumentStatus;
use App\Enums\StampingPosition;
use App\Enums\StorageDisk;
use App\Enums\UnitType;
use App\Enums\VisibilityLevel;
use App\Filament\Resources\Documents\Pages\ViewDocument;
use App\Models\AcademicYear;
use App\Models\Document;
use App\Models\DocumentCategory;
use App\Models\DocumentFile;
use App\Models\Unit;
use App\Models\User;
use FPDF;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DocumentStampingActionTest extends TestCase
{
    use RefreshDatabase;

    private Unit $unit;

    private DocumentCategory $category;

    private AcademicYear $academicYear;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->unit = Unit::create([
            'code' => 'TI',
            'name' => 'Program Studi Informatika',
            'slug' => 'program-studi-informatika',
            'type' => UnitType::Prodi,
            'degree_level' => DegreeLevel::S1,
            'is_active' => true,
        ]);

        $this->category = DocumentCategory::create([
            'code' => 'SK',
            'name' => 'Surat Keputusan',
            'slug' => 'surat-keputusan',
            'stamp_position' => StampingPosition::TopRight,
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
            'document.download_private',
            'document.approve',
        ];

        foreach ($permissions as $perm) {
            Permission::findOrCreate($perm, 'web');
        }

        $role = Role::findOrCreate('admin_prodi', 'web');
        $role->syncPermissions($permissions);

        $this->user = User::factory()->create([
            'unit_id' => $this->unit->id,
            'is_active' => true,
        ]);
        $this->user->assignRole($role);
    }

    public function test_stamp_action_is_visible_for_approved_document_and_hidden_for_draft(): void
    {
        Storage::fake('private');

        $approvedDoc = Document::create([
            'title' => 'SK Disahkan',
            'unit_id' => $this->unit->id,
            'document_category_id' => $this->category->id,
            'academic_year_id' => $this->academicYear->id,
            'uploaded_by' => $this->user->id,
            'document_number' => '001/UNIVBI.TI/SK/XI/2026',
            'issued_date' => '2026-11-10',
            'visibility' => VisibilityLevel::Internal,
            'status' => DocumentStatus::Approved,
        ]);

        $draftDoc = Document::create([
            'title' => 'SK Draf',
            'unit_id' => $this->unit->id,
            'document_category_id' => $this->category->id,
            'academic_year_id' => $this->academicYear->id,
            'uploaded_by' => $this->user->id,
            'issued_date' => '2026-11-10',
            'visibility' => VisibilityLevel::Internal,
            'status' => DocumentStatus::Draft,
        ]);

        DocumentFile::create([
            'document_id' => $approvedDoc->id,
            'uploaded_by' => $this->user->id,
            'is_primary' => true,
            'original_file_name' => 'doc.pdf',
            'standardized_file_name' => '2026-TI-SK-approved.pdf',
            'original_file_path' => 'documents/2026/11/TI/doc.pdf',
            'disk' => StorageDisk::Private,
            'mime_type' => 'application/pdf',
            'file_extension' => 'pdf',
            'file_size' => 100,
            'is_active' => true,
        ]);

        $this->actingAs($this->user);

        // Pada ViewDocument untuk Dokumen Disahkan, aksi stamp ada
        Livewire::test(ViewDocument::class, ['record' => $approvedDoc->getKey()])
            ->assertActionExists('stamp');

        // Pada ViewDocument untuk Dokumen Draf, aksi stamp tersembunyi
        Livewire::test(ViewDocument::class, ['record' => $draftDoc->getKey()])
            ->assertActionHidden('stamp');
    }

    public function test_stamp_action_executes_stamping_and_updates_file_path(): void
    {
        Storage::fake('private');

        $document = Document::create([
            'title' => 'SK Yudisium Mahasiswa',
            'unit_id' => $this->unit->id,
            'document_category_id' => $this->category->id,
            'academic_year_id' => $this->academicYear->id,
            'uploaded_by' => $this->user->id,
            'document_number' => '005/UNIVBI.TI/SK/XI/2026',
            'issued_date' => '2026-11-10',
            'visibility' => VisibilityLevel::Internal,
            'status' => DocumentStatus::Approved,
        ]);

        $fpdf = new FPDF;
        $fpdf->AddPage();
        $fpdf->SetFont('Arial', 'B', 12);
        $fpdf->Cell(0, 10, 'Naskah Asli Yudisium', 0, 1);
        $binary = $fpdf->Output('S');

        $origPath = 'documents/2026/11/TI/yudisium.pdf';
        Storage::disk('private')->put($origPath, $binary);

        $file = DocumentFile::create([
            'document_id' => $document->id,
            'uploaded_by' => $this->user->id,
            'is_primary' => true,
            'original_file_name' => 'yudisium.pdf',
            'standardized_file_name' => '2026-TI-SK-yudisium.pdf',
            'original_file_path' => $origPath,
            'disk' => StorageDisk::Private,
            'mime_type' => 'application/pdf',
            'file_extension' => 'pdf',
            'file_size' => strlen($binary),
            'is_active' => true,
        ]);

        $this->actingAs($this->user);

        Livewire::test(ViewDocument::class, ['record' => $document->getKey()])
            ->callAction('stamp')
            ->assertNotified('Stempel Berhasil Dibubuhkan');

        $file->refresh();
        $this->assertNotNull($file->stamped_file_path);
        Storage::disk('private')->assertExists($file->stamped_file_path);
    }
}
