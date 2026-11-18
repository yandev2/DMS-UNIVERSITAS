<?php

declare(strict_types=1);

namespace Tests\Feature\Filament;

use App\Enums\DegreeLevel;
use App\Enums\DocumentStatus;
use App\Enums\StampingPosition;
use App\Enums\StorageDisk;
use App\Enums\UnitType;
use App\Enums\VisibilityLevel;
use App\Filament\Resources\Documents\Pages\ListDocuments;
use App\Filament\Resources\Documents\Pages\ViewDocument;
use App\Models\AcademicYear;
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

class DocumentApprovalFlowFeatureTest extends TestCase
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
            'code' => 'TI',
            'name' => 'Program Studi S1 Informatika',
            'slug' => 's1-informatika',
            'type' => UnitType::Prodi,
            'degree_level' => DegreeLevel::S1,
            'parent_id' => $this->facultyFik->id,
            'is_active' => true,
        ]);

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

        $roleStaff = Role::findOrCreate('staf_prodi', 'web');
        $roleStaff->syncPermissions(['view_any:document', 'view:document', 'create:document', 'update:document', 'document.download_private']);

        $this->userKaprodiTi = User::factory()->create([
            'name' => 'Kaprodi TI',
            'unit_id' => $this->prodiTi->id,
            'position_title' => 'Ketua Program Studi S1 Informatika',
            'is_active' => true,
        ]);
        $this->userKaprodiTi->assignRole($roleKaprodi);

        $this->userDekanFik = User::factory()->create([
            'name' => 'Dr. Dekan FIK, M.Kom.',
            'unit_id' => $this->facultyFik->id,
            'position_title' => 'Dekan Fakultas Ilmu Komputer',
            'is_active' => true,
        ]);
        $this->userDekanFik->assignRole($roleDekan);

        $this->userStaffTi = User::factory()->create([
            'name' => 'Staf Tata Usaha TI',
            'unit_id' => $this->prodiTi->id,
            'position_title' => 'Staf Administrasi',
            'is_active' => true,
        ]);
        $this->userStaffTi->assignRole($roleStaff);
    }

    public function test_pimpinan_sees_pending_tab_with_badge_in_list_documents(): void
    {
        $doc = Document::create([
            'title' => 'Surat Pengajuan SK Kurikulum',
            'unit_id' => $this->prodiTi->id,
            'document_category_id' => $this->categorySk->id,
            'academic_year_id' => $this->academicYear->id,
            'uploaded_by' => $this->userStaffTi->id,
            'issued_date' => '2026-11-10',
            'status' => DocumentStatus::Draft,
        ]);

        app(DocumentApprovalService::class)->initializeApprovalFlow($doc);

        $this->actingAs($this->userKaprodiTi);

        Livewire::test(ListDocuments::class)
            ->assertSuccessful()
            ->set('activeTab', 'my_pending_approval')
            ->assertCanSeeTableRecords([$doc]);
    }

    public function test_full_two_level_approval_workflow_generates_number_and_stamp(): void
    {
        Storage::fake('private');

        $doc = Document::create([
            'title' => 'SK Dosen Pembimbing Skripsi',
            'unit_id' => $this->prodiTi->id,
            'document_category_id' => $this->categorySk->id,
            'academic_year_id' => $this->academicYear->id,
            'uploaded_by' => $this->userStaffTi->id,
            'issued_date' => '2026-11-10',
            'visibility' => VisibilityLevel::Internal,
            'status' => DocumentStatus::Draft,
        ]);

        $fpdf = new FPDF;
        $fpdf->AddPage();
        $fpdf->SetFont('Arial', '', 12);
        $fpdf->Cell(0, 10, 'Naskah Asli Surat', 0, 1);
        $binary = $fpdf->Output('S');

        $filePath = 'documents/2026/11/TI/sk-pembimbing.pdf';
        Storage::disk('private')->put($filePath, $binary);

        $file = DocumentFile::create([
            'document_id' => $doc->id,
            'uploaded_by' => $this->userStaffTi->id,
            'is_primary' => true,
            'original_file_name' => 'sk-pembimbing.pdf',
            'standardized_file_name' => '2026-TI-SK-sk-pembimbing.pdf',
            'original_file_path' => $filePath,
            'disk' => StorageDisk::Private,
            'mime_type' => 'application/pdf',
            'file_extension' => 'pdf',
            'file_size' => strlen($binary),
            'is_active' => true,
        ]);

        app(DocumentApprovalService::class)->initializeApprovalFlow($doc);

        // 1. Kaprodi TI membuka ViewDocument dan menyetujui Step 1
        $this->actingAs($this->userKaprodiTi);

        Livewire::test(ViewDocument::class, ['record' => $doc->getKey()])
            ->assertActionExists('approve')
            ->callAction('approve', [
                'note' => 'Disetujui oleh Kaprodi TI.',
            ])
            ->assertNotified('Dokumen Berhasil Disetujui');

        $doc->refresh();
        $this->assertSame(DocumentStatus::Pending, $doc->status);
        $this->assertSame(2, $doc->current_step);
        $this->assertNull($doc->document_number); // Belum terbit sebelum final

        // 2. Dekan FIK membuka ViewDocument dan menyetujui Step 2 (Final)
        $this->actingAs($this->userDekanFik);

        Livewire::test(ViewDocument::class, ['record' => $doc->getKey()])
            ->assertActionExists('approve')
            ->callAction('approve', [
                'note' => 'Disahkan secara resmi oleh Dekan FIK.',
            ])
            ->assertNotified('Dokumen Berhasil Disetujui');

        $doc->refresh();
        $this->assertSame(DocumentStatus::Approved, $doc->status);
        $this->assertSame($this->userDekanFik->id, $doc->final_approved_by);
        $this->assertSame('Dr. Dekan FIK, M.Kom.', $doc->signer_name_snapshot);

        // Nomor resmi otomatis terbit!
        $this->assertSame('001/UNIVBI.TI/SK/XI/2026', $doc->document_number);

        // Berkas terstempel otomatis!
        $file->refresh();
        $this->assertNotNull($file->stamped_file_path);
        Storage::disk('private')->assertExists($file->stamped_file_path);
    }

    public function test_leader_can_request_revision_and_author_can_resubmit(): void
    {
        $doc = Document::create([
            'title' => 'Surat Tugas Seminar Nasional',
            'unit_id' => $this->prodiTi->id,
            'document_category_id' => $this->categorySk->id,
            'academic_year_id' => $this->academicYear->id,
            'uploaded_by' => $this->userStaffTi->id,
            'issued_date' => '2026-11-10',
            'status' => DocumentStatus::Draft,
        ]);

        app(DocumentApprovalService::class)->initializeApprovalFlow($doc);

        // 1. Kaprodi meminta revisi
        $this->actingAs($this->userKaprodiTi);

        Livewire::test(ViewDocument::class, ['record' => $doc->getKey()])
            ->assertActionExists('requestRevision')
            ->callAction('requestRevision', [
                'note' => 'Lampiran susunan kepanitiaan mohon dilengkapi.',
            ])
            ->assertNotified('Permintaan Revisi Dikirim');

        $doc->refresh();
        $this->assertSame(DocumentStatus::Revision, $doc->status);

        // 2. Staf pengunggah melihat aksi Ajukan Kembali
        $this->actingAs($this->userStaffTi);

        Livewire::test(ViewDocument::class, ['record' => $doc->getKey()])
            ->assertActionExists('resubmit')
            ->callAction('resubmit')
            ->assertNotified('Dokumen Berhasil Diajukan Kembali');

        $doc->refresh();
        $this->assertSame(DocumentStatus::Pending, $doc->status);
        $this->assertSame(1, $doc->current_step);
    }

    public function test_leader_can_reject_document(): void
    {
        $doc = Document::create([
            'title' => 'Surat Pengajuan Tidak Valid',
            'unit_id' => $this->prodiTi->id,
            'document_category_id' => $this->categorySk->id,
            'academic_year_id' => $this->academicYear->id,
            'uploaded_by' => $this->userStaffTi->id,
            'issued_date' => '2026-11-10',
            'status' => DocumentStatus::Draft,
        ]);

        app(DocumentApprovalService::class)->initializeApprovalFlow($doc);

        $this->actingAs($this->userKaprodiTi);

        Livewire::test(ViewDocument::class, ['record' => $doc->getKey()])
            ->assertActionExists('reject')
            ->callAction('reject', [
                'note' => 'Kegiatan tidak sesuai dengan kalender akademik kampus.',
            ])
            ->assertNotified('Dokumen Ditolak');

        $doc->refresh();
        $this->assertSame(DocumentStatus::Rejected, $doc->status);
    }
}
