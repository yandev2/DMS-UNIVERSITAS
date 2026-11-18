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
use App\Models\ActivityLog;
use App\Models\CategoryApprovalFlow;
use App\Models\Document;
use App\Models\DocumentCategory;
use App\Models\DocumentFile;
use App\Models\Unit;
use App\Models\User;
use App\Services\DocumentApprovalService;
use FPDF;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DocumentAuditTrailTest extends TestCase
{
    use RefreshDatabase;

    private Unit $facultyFik;

    private Unit $prodiTi;

    private DocumentCategory $categorySk;

    private AcademicYear $academicYear;

    private User $userKaprodiTi;

    private User $userDekanFik;

    private User $userStaffTi;

    protected function setUp(): void
    {
        parent::setUp();

        $this->facultyFik = Unit::create([
            'code' => 'FIK',
            'name' => 'Fakultas Ilmu Komputer',
            'slug' => 'fakultas-ilmu-komputer',
            'type' => UnitType::Fakultas,
            'is_active' => true,
        ]);

        $this->prodiTi = Unit::create([
            'parent_id' => $this->facultyFik->id,
            'code' => 'TI',
            'name' => 'Program Studi S1 Teknik Informatika',
            'slug' => 'prodi-s1-ti',
            'type' => UnitType::Prodi,
            'degree_level' => DegreeLevel::S1,
            'is_active' => true,
        ]);

        $this->academicYear = AcademicYear::create([
            'name' => '2026/2027 Ganjil',
            'start_date' => '2026-09-01',
            'end_date' => '2027-01-31',
            'is_active' => true,
        ]);

        $this->categorySk = DocumentCategory::create([
            'code' => 'SK',
            'name' => 'Surat Keputusan',
            'slug' => 'surat-keputusan',
            'requires_approval' => true,
            'has_expiry' => false,
            'stamp_position' => StampingPosition::TopRight,
            'is_active' => true,
        ]);

        CategoryApprovalFlow::create([
            'document_category_id' => $this->categorySk->id,
            'step_order' => 1,
            'approver_role' => 'kaprodi',
        ]);

        CategoryApprovalFlow::create([
            'document_category_id' => $this->categorySk->id,
            'step_order' => 2,
            'approver_role' => 'dekan',
        ]);

        $permissions = [
            'view_any:document',
            'view:document',
            'create:document',
            'update:document',
            'delete:document',
            'document.approve',
            'document.download_private',
        ];

        foreach ($permissions as $perm) {
            Permission::findOrCreate($perm, 'web');
        }

        $roleKaprodi = Role::findOrCreate('kaprodi', 'web');
        $roleKaprodi->syncPermissions(['view_any:document', 'view:document', 'update:document', 'document.approve', 'document.download_private']);

        $roleDekan = Role::findOrCreate('dekan', 'web');
        $roleDekan->syncPermissions(['view_any:document', 'view:document', 'update:document', 'document.approve', 'document.download_private']);

        $roleStaff = Role::findOrCreate('staf', 'web');
        $roleStaff->syncPermissions(['view_any:document', 'view:document', 'create:document', 'update:document', 'document.download_private']);

        $this->userKaprodiTi = User::factory()->create([
            'name' => 'Dr. Kaprodi TI, M.Kom.',
            'email' => 'kaprodi.ti@univbi.ac.id',
            'unit_id' => $this->prodiTi->id,
            'position_title' => 'Ketua Program Studi S1 Teknik Informatika',
        ]);
        $this->userKaprodiTi->assignRole('kaprodi');

        $this->userDekanFik = User::factory()->create([
            'name' => 'Prof. Dr. Dekan FIK, M.T.',
            'email' => 'dekan.fik@univbi.ac.id',
            'unit_id' => $this->facultyFik->id,
            'position_title' => 'Dekan Fakultas Ilmu Komputer',
        ]);
        $this->userDekanFik->assignRole('dekan');

        $this->userStaffTi = User::factory()->create([
            'name' => 'Staf Tata Usaha TI',
            'email' => 'staff.ti@univbi.ac.id',
            'unit_id' => $this->prodiTi->id,
            'position_title' => 'Staf Administrasi Prodi TI',
        ]);
        $this->userStaffTi->assignRole('staf');
    }

    private function createValidSamplePdf(string $path): void
    {
        $fpdf = new FPDF('P', 'mm', 'A4');
        $fpdf->AddPage();
        $fpdf->SetFont('Helvetica', 'B', 14);
        $fpdf->Cell(0, 10, 'UNIVERSITAS BINA INSAN (UNIVBI)', 0, 1, 'C');
        $fpdf->SetFont('Helvetica', '', 11);
        $fpdf->Cell(0, 10, 'Naskah Dinas Pengujian E-Arsip', 0, 1, 'C');

        Storage::disk('private')->put($path, $fpdf->Output('S'));
    }

    public function test_view_document_renders_approval_stepper_and_tabs_for_pending_document(): void
    {
        $document = Document::create([
            'title' => 'SK Dosen Pembimbing Skripsi TA 2026/2027',
            'unit_id' => $this->prodiTi->id,
            'document_category_id' => $this->categorySk->id,
            'academic_year_id' => $this->academicYear->id,
            'uploaded_by' => $this->userStaffTi->id,
            'visibility' => VisibilityLevel::Internal,
            'issued_date' => now(),
            'status' => DocumentStatus::Draft,
        ]);

        app(DocumentApprovalService::class)->initializeApprovalFlow($document);
        $document->refresh();

        $this->actingAs($this->userKaprodiTi);

        Livewire::test(ViewDocument::class, ['record' => $document->getKey()])
            ->assertSuccessful()
            ->assertSee('Status Pengesahan Naskah Dinas')
            ->assertSee('Menunggu Persetujuan')
            ->assertSee('Staf Tata Usaha TI')
            ->assertSee('Tingkat 1 (Kaprodi)')
            ->assertSee('Tingkat 2 (Dekan)')
            ->assertSee('Informasi Naskah Dinas')
            ->assertSee('Riwayat Alur Persetujuan')
            ->assertSee('Catatan Keamanan');
    }

    public function test_view_document_renders_revision_alert_with_note_when_revision_requested(): void
    {
        $document = Document::create([
            'title' => 'SK Kurikulum Berbasis OBE 2026',
            'unit_id' => $this->prodiTi->id,
            'document_category_id' => $this->categorySk->id,
            'academic_year_id' => $this->academicYear->id,
            'uploaded_by' => $this->userStaffTi->id,
            'visibility' => VisibilityLevel::Internal,
            'issued_date' => now(),
            'status' => DocumentStatus::Draft,
        ]);

        app(DocumentApprovalService::class)->initializeApprovalFlow($document);
        $document->refresh();

        // Kaprodi meminta revisi
        app(DocumentApprovalService::class)->requestRevision(
            $document,
            $this->userKaprodiTi,
            'Harap perbaiki tabel lampiran susunan mata kuliah semester 5.'
        );
        $document->refresh();

        $this->actingAs($this->userStaffTi);

        Livewire::test(ViewDocument::class, ['record' => $document->getKey()])
            ->assertSuccessful()
            ->assertSee('Naskah Dinas Memerlukan Revisi')
            ->assertSee('Harap perbaiki tabel lampiran susunan mata kuliah semester 5.')
            ->assertSee('Dr. Kaprodi TI, M.Kom.');
    }

    public function test_resubmit_advances_round_and_displays_new_evaluation_round_in_history(): void
    {
        $document = Document::create([
            'title' => 'SK Praktikum Laboratorium Komputer',
            'unit_id' => $this->prodiTi->id,
            'document_category_id' => $this->categorySk->id,
            'academic_year_id' => $this->academicYear->id,
            'uploaded_by' => $this->userStaffTi->id,
            'visibility' => VisibilityLevel::Internal,
            'issued_date' => now(),
            'status' => DocumentStatus::Draft,
        ]);

        app(DocumentApprovalService::class)->initializeApprovalFlow($document);
        $document->refresh();

        // Minta revisi
        app(DocumentApprovalService::class)->requestRevision(
            $document,
            $this->userKaprodiTi,
            'Mohon lengkapi daftar asisten lab.'
        );
        $document->refresh();

        // Ajukan kembali
        $this->actingAs($this->userStaffTi);
        app(DocumentApprovalService::class)->resubmit($document);
        $document->refresh();

        $this->assertSame(DocumentStatus::Pending, $document->status);
        $this->assertSame(2, $document->approvals()->max('revision_round'));

        Livewire::test(ViewDocument::class, ['record' => $document->getKey()])
            ->assertSuccessful()
            ->assertSee('Putaran Evaluasi Ke-2')
            ->assertSee('Putaran Evaluasi Ke-1');
    }

    public function test_view_document_renders_approved_state_with_official_number_and_signers(): void
    {
        Storage::fake('private');
        $pdfPath = 'documents/ti/sk_approved_test.pdf';
        $this->createValidSamplePdf($pdfPath);

        $document = Document::create([
            'title' => 'SK Dosen Wali Akademik Semester Ganjil',
            'unit_id' => $this->prodiTi->id,
            'document_category_id' => $this->categorySk->id,
            'academic_year_id' => $this->academicYear->id,
            'uploaded_by' => $this->userStaffTi->id,
            'visibility' => VisibilityLevel::Internal,
            'issued_date' => now(),
            'status' => DocumentStatus::Draft,
        ]);

        DocumentFile::create([
            'document_id' => $document->id,
            'version' => 1,
            'is_primary' => true,
            'is_active' => true,
            'disk' => StorageDisk::Private,
            'original_file_path' => $pdfPath,
            'original_file_name' => 'sk_dosen_wali.pdf',
            'file_extension' => 'pdf',
            'standardized_file_name' => '2026-TI-SK-dosen-wali.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 2048,
        ]);

        app(DocumentApprovalService::class)->initializeApprovalFlow($document);
        $document->refresh();

        // Step 1: Kaprodi approves
        app(DocumentApprovalService::class)->approve($document, $this->userKaprodiTi, 'Disetujui dari tingkat Kaprodi.');
        $document->refresh();

        // Step 2: Dekan approves (final approval)
        app(DocumentApprovalService::class)->approve($document, $this->userDekanFik, 'Sah dan disetujui penuh.');
        $document->refresh();

        $this->assertSame(DocumentStatus::Approved, $document->status);
        $this->assertNotNull($document->document_number);

        $this->actingAs($this->userDekanFik);

        Livewire::test(ViewDocument::class, ['record' => $document->getKey()])
            ->assertSuccessful()
            ->assertSee('Resmi Disahkan')
            ->assertSee($document->document_number)
            ->assertSee('Prof. Dr. Dekan FIK, M.T.');
    }

    public function test_security_activity_log_records_and_displays_events_with_causer_and_ip(): void
    {
        Storage::fake('private');
        $pdfPath = 'documents/ti/sk_audit_test.pdf';
        $this->createValidSamplePdf($pdfPath);

        $document = Document::create([
            'title' => 'SK Penetapan Yudisium Mahasiswa TI',
            'unit_id' => $this->prodiTi->id,
            'document_category_id' => $this->categorySk->id,
            'academic_year_id' => $this->academicYear->id,
            'uploaded_by' => $this->userStaffTi->id,
            'visibility' => VisibilityLevel::Internal,
            'issued_date' => now(),
            'status' => DocumentStatus::Draft,
        ]);

        $file = DocumentFile::create([
            'document_id' => $document->id,
            'version' => 1,
            'is_primary' => true,
            'is_active' => true,
            'disk' => StorageDisk::Private,
            'original_file_path' => $pdfPath,
            'original_file_name' => 'sk_yudisium.pdf',
            'file_extension' => 'pdf',
            'standardized_file_name' => '2026-TI-SK-yudisium.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 2048,
        ]);

        app(DocumentApprovalService::class)->initializeApprovalFlow($document);
        $document->refresh();

        // Tindakan 1: Kaprodi menyetujui
        app(DocumentApprovalService::class)->approve($document, $this->userKaprodiTi, 'Catatan persetujuan audit.');
        $document->refresh();

        // Tindakan 2: Download berkas
        $this->actingAs($this->userKaprodiTi);
        $this->get(route('admin.documents.download', $document));

        // Verifikasi log tercatat di database
        $logs = ActivityLog::where('subject_type', Document::class)
            ->where('subject_id', $document->id)
            ->get();

        $this->assertTrue($logs->isNotEmpty());
        $this->assertTrue($logs->contains('event', 'approved'));
        $this->assertTrue($logs->contains('event', 'downloaded'));

        // Akses halaman detail dokumen
        Livewire::test(ViewDocument::class, ['record' => $document->getKey()])
            ->assertSuccessful()
            ->assertSee('Catatan Keamanan')
            ->assertSee('Rekam Jejak Sistem')
            ->assertSee('Berkas Diunduh')
            ->assertSee('Dr. Kaprodi TI, M.Kom.')
            ->assertSee('127.0.0.1');
    }
}
