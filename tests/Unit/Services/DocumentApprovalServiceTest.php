<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Enums\ApprovalStatus;
use App\Enums\DegreeLevel;
use App\Enums\DocumentStatus;
use App\Enums\StampingPosition;
use App\Enums\StorageDisk;
use App\Enums\UnitType;
use App\Models\AcademicYear;
use App\Models\CategoryApprovalFlow;
use App\Models\Document;
use App\Models\DocumentCategory;
use App\Models\DocumentFile;
use App\Models\Unit;
use App\Models\User;
use App\Services\DocumentApprovalService;
use App\Services\DocumentNamingService;
use App\Services\DocumentNumberingService;
use App\Services\DocumentStampingService;
use FPDF;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DocumentApprovalServiceTest extends TestCase
{
    use RefreshDatabase;

    private DocumentApprovalService $service;

    private Unit $facultyFik;

    private Unit $prodiTi;

    private Unit $prodiSi;

    private DocumentCategory $categorySk;

    private DocumentCategory $categoryNoApproval;

    private AcademicYear $academicYear;

    private User $userKaprodiTi;

    private User $userKaprodiSi;

    private User $userDekan;

    private User $userStaff;

    protected function setUp(): void
    {
        parent::setUp();

        $namingService = new DocumentNamingService;
        $numberingService = new DocumentNumberingService($namingService);
        $stampingService = new DocumentStampingService($namingService);
        $this->service = new DocumentApprovalService($numberingService, $stampingService);

        // Unit Organisasi
        $this->facultyFik = Unit::create([
            'code' => 'FIK',
            'name' => 'Fakultas Ilmu Komputer',
            'slug' => 'fakultas-ilmu-komputer',
            'type' => UnitType::Fakultas,
            'is_active' => true,
        ]);

        $this->prodiTi = Unit::create([
            'code' => 'TI',
            'name' => 'Program Studi S1 Informatika',
            'slug' => 's1-informatika',
            'type' => UnitType::Prodi,
            'degree_level' => DegreeLevel::S1,
            'parent_id' => $this->facultyFik->id,
            'is_active' => true,
        ]);

        $this->prodiSi = Unit::create([
            'code' => 'SI',
            'name' => 'Program Studi S1 Sistem Informasi',
            'slug' => 's1-sistem-informasi',
            'type' => UnitType::Prodi,
            'degree_level' => DegreeLevel::S1,
            'parent_id' => $this->facultyFik->id,
            'is_active' => true,
        ]);

        // Kategori Dokumen dengan Alur Approval 2 Tingkat: Kaprodi (Step 1) -> Dekan (Step 2)
        $this->categorySk = DocumentCategory::create([
            'code' => 'SK',
            'name' => 'Surat Keputusan',
            'slug' => 'surat-keputusan',
            'number_format' => '{seq}/UNIVBI.{unit}/{cat}/{roman_month}/{year}',
            'requires_approval' => true,
            'stamp_position' => StampingPosition::TopRight,
            'is_active' => true,
        ]);

        CategoryApprovalFlow::create([
            'document_category_id' => $this->categorySk->id,
            'step_order' => 1,
            'approver_role' => 'kaprodi',
            'step_label' => 'Verifikasi Kaprodi',
            'is_mandatory' => true,
        ]);

        CategoryApprovalFlow::create([
            'document_category_id' => $this->categorySk->id,
            'step_order' => 2,
            'approver_role' => 'dekan',
            'step_label' => 'Pengesahan Dekan',
            'is_mandatory' => true,
        ]);

        // Kategori Tanpa Alur Approval
        $this->categoryNoApproval = DocumentCategory::create([
            'code' => 'MOU',
            'name' => 'Kerjasama Langsung',
            'slug' => 'kerjasama-langsung',
            'number_format' => '{seq}/UNIVBI.{unit}/{cat}/{month}/{year}',
            'requires_approval' => false,
            'stamp_position' => StampingPosition::None,
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

        // Permissions & Roles
        Permission::findOrCreate('document.approve', 'web');
        Permission::findOrCreate('document.bypass_unit_scope', 'web');

        $roleKaprodi = Role::findOrCreate('kaprodi', 'web');
        $roleKaprodi->syncPermissions(['document.approve']);

        $roleDekan = Role::findOrCreate('dekan', 'web');
        $roleDekan->syncPermissions(['document.approve']);

        $roleStaff = Role::findOrCreate('staf_prodi', 'web');

        // Pengguna
        $this->userKaprodiTi = User::factory()->create([
            'name' => 'Kaprodi TI',
            'unit_id' => $this->prodiTi->id,
            'position_title' => 'Ketua Program Studi S1 Informatika',
            'is_active' => true,
        ]);
        $this->userKaprodiTi->assignRole($roleKaprodi);

        $this->userKaprodiSi = User::factory()->create([
            'name' => 'Kaprodi SI',
            'unit_id' => $this->prodiSi->id,
            'position_title' => 'Ketua Program Studi S1 Sistem Informasi',
            'is_active' => true,
        ]);
        $this->userKaprodiSi->assignRole($roleKaprodi);

        $this->userDekan = User::factory()->create([
            'name' => 'Dr. Dekan FIK, M.Kom.',
            'unit_id' => $this->facultyFik->id,
            'position_title' => 'Dekan Fakultas Ilmu Komputer',
            'is_active' => true,
        ]);
        $this->userDekan->assignRole($roleDekan);

        $this->userStaff = User::factory()->create([
            'name' => 'Staf TU TI',
            'unit_id' => $this->prodiTi->id,
            'is_active' => true,
        ]);
        $this->userStaff->assignRole($roleStaff);
    }

    public function test_initialize_approval_flow_creates_steps_when_required(): void
    {
        $doc = Document::create([
            'title' => 'Surat Pengangkatan Asisten Dosen',
            'unit_id' => $this->prodiTi->id,
            'document_category_id' => $this->categorySk->id,
            'academic_year_id' => $this->academicYear->id,
            'uploaded_by' => $this->userStaff->id,
            'issued_date' => '2026-11-10',
            'status' => DocumentStatus::Draft,
        ]);

        $this->service->initializeApprovalFlow($doc);

        $doc->refresh();
        $this->assertSame(DocumentStatus::Pending, $doc->status);
        $this->assertSame(1, $doc->current_step);
        $this->assertSame(2, $doc->total_steps);

        $this->assertCount(2, $doc->approvals);

        $step1 = $doc->approvals()->where('step_order', 1)->first();
        $this->assertNotNull($step1);
        $this->assertSame('kaprodi', $step1->approver_role);
        $this->assertSame(ApprovalStatus::Pending, $step1->status);

        $step2 = $doc->approvals()->where('step_order', 2)->first();
        $this->assertNotNull($step2);
        $this->assertSame('dekan', $step2->approver_role);
        $this->assertSame(ApprovalStatus::Waiting, $step2->status);
    }

    public function test_initialize_approval_flow_auto_approves_when_category_has_no_approval(): void
    {
        $doc = Document::create([
            'title' => 'Dokumen Kerjasama Langsung',
            'unit_id' => $this->prodiTi->id,
            'document_category_id' => $this->categoryNoApproval->id,
            'academic_year_id' => $this->academicYear->id,
            'uploaded_by' => $this->userStaff->id,
            'issued_date' => '2026-11-10',
            'status' => DocumentStatus::Draft,
        ]);

        $this->service->initializeApprovalFlow($doc);

        $doc->refresh();
        $this->assertSame(DocumentStatus::Approved, $doc->status);
        $this->assertSame(0, $doc->current_step);
        $this->assertSame(0, $doc->total_steps);
        $this->assertNotNull($doc->document_number);
        $this->assertSame('001/UNIVBI.TI/MOU/11/2026', $doc->document_number);
    }

    public function test_can_user_approve_validates_role_and_unit_scope(): void
    {
        $doc = Document::create([
            'title' => 'SK Yudisium TI',
            'unit_id' => $this->prodiTi->id,
            'document_category_id' => $this->categorySk->id,
            'academic_year_id' => $this->academicYear->id,
            'uploaded_by' => $this->userStaff->id,
            'issued_date' => '2026-11-10',
            'status' => DocumentStatus::Draft,
        ]);
        $this->service->initializeApprovalFlow($doc);

        // Kaprodi TI boleh approve Step 1
        $this->assertTrue($this->service->canUserApprove($this->userKaprodiTi, $doc));

        // Kaprodi SI TIDAK boleh approve dokumen milik TI
        $this->assertFalse($this->service->canUserApprove($this->userKaprodiSi, $doc));

        // Dekan TIDAK boleh approve saat masih di Step 1 (karena Step 1 khusus role kaprodi)
        $this->assertFalse($this->service->canUserApprove($this->userDekan, $doc));

        // Staf biasa TIDAK boleh approve
        $this->assertFalse($this->service->canUserApprove($this->userStaff, $doc));
    }

    public function test_multi_level_approval_advances_steps_until_final_approval(): void
    {
        Storage::fake('private');

        $doc = Document::create([
            'title' => 'SK Penetapan Kurikulum',
            'unit_id' => $this->prodiTi->id,
            'document_category_id' => $this->categorySk->id,
            'academic_year_id' => $this->academicYear->id,
            'uploaded_by' => $this->userStaff->id,
            'issued_date' => '2026-11-10',
            'status' => DocumentStatus::Draft,
        ]);

        // Siapkan file fisik
        $fpdf = new FPDF;
        $fpdf->AddPage();
        $fpdf->SetFont('Arial', '', 10);
        $fpdf->Cell(0, 10, 'Konten Dokumen Asli', 0, 1);
        $pdfBinary = $fpdf->Output('S');

        $filePath = 'documents/2026/11/TI/sk-kurikulum.pdf';
        Storage::disk('private')->put($filePath, $pdfBinary);

        DocumentFile::create([
            'document_id' => $doc->id,
            'uploaded_by' => $this->userStaff->id,
            'is_primary' => true,
            'original_file_name' => 'sk.pdf',
            'standardized_file_name' => '2026-TI-SK-penetapan-kurikulum.pdf',
            'original_file_path' => $filePath,
            'disk' => StorageDisk::Private,
            'mime_type' => 'application/pdf',
            'file_extension' => 'pdf',
            'file_size' => strlen($pdfBinary),
            'is_active' => true,
        ]);

        $this->service->initializeApprovalFlow($doc);

        // --- TINGKAT 1: Kaprodi Menyetujui ---
        $resStep1 = $this->service->approve($doc, $this->userKaprodiTi, 'Disetujui di tingkat Program Studi.');
        $this->assertTrue($resStep1);

        $doc->refresh();
        $this->assertSame(DocumentStatus::Pending, $doc->status);
        $this->assertSame(2, $doc->current_step);
        $this->assertNull($doc->document_number); // Belum terbit nomor karena belum final

        $step1Record = $doc->approvals()->where('step_order', 1)->first();
        $this->assertSame(ApprovalStatus::Approved, $step1Record->status);
        $this->assertSame($this->userKaprodiTi->id, $step1Record->approved_by);
        $this->assertSame('Disetujui di tingkat Program Studi.', $step1Record->note);

        $step2Record = $doc->approvals()->where('step_order', 2)->first();
        $this->assertSame(ApprovalStatus::Pending, $step2Record->status);

        // Sekarang Dekan berhak menyetujui di Step 2
        $this->assertTrue($this->service->canUserApprove($this->userDekan, $doc));

        // --- TINGKAT 2 (FINAL): Dekan Menyetujui ---
        $resStep2 = $this->service->approve($doc, $this->userDekan, 'Disahkan secara resmi oleh Dekan.');
        $this->assertTrue($resStep2);

        $doc->refresh();
        $this->assertSame(DocumentStatus::Approved, $doc->status);
        $this->assertSame($this->userDekan->id, $doc->final_approved_by);
        $this->assertSame('Dr. Dekan FIK, M.Kom.', $doc->signer_name_snapshot);
        $this->assertSame('Dekan Fakultas Ilmu Komputer', $doc->signer_position_snapshot);

        // Nomor resmi terbit secara otomatis!
        $this->assertSame('001/UNIVBI.TI/SK/XI/2026', $doc->document_number);

        // Berkas terstempel otomatis!
        $file = $doc->primaryFile;
        $this->assertNotNull($file->stamped_file_path);
        Storage::disk('private')->assertExists($file->stamped_file_path);
    }

    public function test_request_revision_changes_status_to_revision_with_mandatory_note(): void
    {
        $doc = Document::create([
            'title' => 'Surat Tugas Perlu Koreksi',
            'unit_id' => $this->prodiTi->id,
            'document_category_id' => $this->categorySk->id,
            'academic_year_id' => $this->academicYear->id,
            'uploaded_by' => $this->userStaff->id,
            'issued_date' => '2026-11-10',
            'status' => DocumentStatus::Draft,
        ]);
        $this->service->initializeApprovalFlow($doc);

        $this->service->requestRevision($doc, $this->userKaprodiTi, 'Lampiran bukti pengabdian belum disertakan.');

        $doc->refresh();
        $this->assertSame(DocumentStatus::Revision, $doc->status);

        $step1 = $doc->approvals()->where('step_order', 1)->first();
        $this->assertSame(ApprovalStatus::Revision, $step1->status);
        $this->assertSame('Lampiran bukti pengabdian belum disertakan.', $step1->note);
    }

    public function test_reject_changes_status_to_rejected_with_mandatory_note(): void
    {
        $doc = Document::create([
            'title' => 'Surat Tidak Sesuai Ketentuan',
            'unit_id' => $this->prodiTi->id,
            'document_category_id' => $this->categorySk->id,
            'academic_year_id' => $this->academicYear->id,
            'uploaded_by' => $this->userStaff->id,
            'issued_date' => '2026-11-10',
            'status' => DocumentStatus::Draft,
        ]);
        $this->service->initializeApprovalFlow($doc);

        $this->service->reject($doc, $this->userKaprodiTi, 'Format kegiatan melanggar panduan akademik.');

        $doc->refresh();
        $this->assertSame(DocumentStatus::Rejected, $doc->status);

        $step1 = $doc->approvals()->where('step_order', 1)->first();
        $this->assertSame(ApprovalStatus::Rejected, $step1->status);
        $this->assertSame('Format kegiatan melanggar panduan akademik.', $step1->note);
    }

    public function test_resubmit_creates_new_revision_round(): void
    {
        $doc = Document::create([
            'title' => 'Surat Revisi Ulang',
            'unit_id' => $this->prodiTi->id,
            'document_category_id' => $this->categorySk->id,
            'academic_year_id' => $this->academicYear->id,
            'uploaded_by' => $this->userStaff->id,
            'issued_date' => '2026-11-10',
            'status' => DocumentStatus::Draft,
        ]);
        $this->service->initializeApprovalFlow($doc);
        $this->service->requestRevision($doc, $this->userKaprodiTi, 'Perbaiki nama dosen.');

        // Staf memperbaiki dokumen dan mengajukan kembali
        $this->service->resubmit($doc);

        $doc->refresh();
        $this->assertSame(DocumentStatus::Pending, $doc->status);
        $this->assertSame(1, $doc->current_step);

        // Putaran revisi kedua (revision_round = 2) dibuat
        $this->assertCount(4, $doc->approvals); // 2 dari round 1 + 2 dari round 2

        $round2Step1 = $doc->approvals()->where('revision_round', 2)->where('step_order', 1)->first();
        $this->assertNotNull($round2Step1);
        $this->assertSame(ApprovalStatus::Pending, $round2Step1->status);

        $round2Step2 = $doc->approvals()->where('revision_round', 2)->where('step_order', 2)->first();
        $this->assertNotNull($round2Step2);
        $this->assertSame(ApprovalStatus::Waiting, $round2Step2->status);
    }

    public function test_get_pending_approvals_query_for_user_filters_correctly(): void
    {
        $docTi = Document::create([
            'title' => 'SK TI Menunggu',
            'unit_id' => $this->prodiTi->id,
            'document_category_id' => $this->categorySk->id,
            'academic_year_id' => $this->academicYear->id,
            'uploaded_by' => $this->userStaff->id,
            'issued_date' => '2026-11-10',
            'status' => DocumentStatus::Draft,
        ]);
        $this->service->initializeApprovalFlow($docTi);

        // Kaprodi TI memiliki 1 dokumen pending
        $countTi = $this->service->countPendingApprovalsForUser($this->userKaprodiTi);
        $this->assertSame(1, $countTi);

        // Kaprodi SI memiliki 0 dokumen pending (karena dokumen milik TI)
        $countSi = $this->service->countPendingApprovalsForUser($this->userKaprodiSi);
        $this->assertSame(0, $countSi);

        // Dekan memiliki 0 pending saat ini (karena masih di step 1 kaprodi)
        $countDekan = $this->service->countPendingApprovalsForUser($this->userDekan);
        $this->assertSame(0, $countDekan);
    }
}
