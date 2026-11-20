<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\DocumentStatus;
use App\Enums\OutgoingLetterStatus;
use App\Enums\StampingPosition;
use App\Enums\StorageDisk;
use App\Enums\UnitType;
use App\Enums\VisibilityLevel;
use App\Filament\Resources\Documents\Pages\EditDocument;
use App\Filament\Resources\Documents\Pages\ListDocuments;
use App\Filament\Resources\Documents\Pages\ViewDocument;
use App\Filament\Resources\OutgoingLetters\Pages\CreateOutgoingLetter;
use App\Filament\Resources\OutgoingLetters\Pages\ListOutgoingLetters;
use App\Filament\Resources\OutgoingLetters\Pages\ViewOutgoingLetter;
use App\Models\AcademicYear;
use App\Models\ActivityLog;
use App\Models\CategoryApprovalFlow;
use App\Models\Document;
use App\Models\DocumentCategory;
use App\Models\DocumentFile;
use App\Models\OutgoingLetter;
use App\Models\Unit;
use App\Models\User;
use App\Rules\PdfDocumentFileRule;
use App\Services\DocumentApprovalService;
use App\Services\LetterManagementService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Livewire\Livewire;
use setasign\Fpdi\Fpdi;
use Tests\Support\LegacyMasterDataSeeder;
use Tests\TestCase;

class WetSignatureWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private Unit $sdmUnit;

    private Unit $otherUnit;

    private AcademicYear $academicYear;

    private User $sdmStaff;

    private User $otherUnitStaff;

    private User $humasUser;

    private User $warekUser;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('private');
        $this->seed(LegacyMasterDataSeeder::class);

        $this->academicYear = AcademicYear::where('is_active', true)->firstOrFail();

        $this->sdmUnit = Unit::firstOrCreate(
            ['code' => 'SDM'],
            [
                'name' => 'Biro Sumber Daya Manusia',
                'slug' => 'biro-sdm',
                'type' => UnitType::Biro,
                'is_active' => true,
            ]
        );

        $this->otherUnit = Unit::where('code', 'TI')->firstOrFail();

        $this->sdmStaff = User::factory()->create([
            'name' => 'Staf Biro SDM',
            'unit_id' => $this->sdmUnit->id,
            'position_title' => 'Staf Administrasi SDM',
            'is_active' => true,
        ]);
        $this->sdmStaff->assignRole('staf_unit');

        $this->otherUnitStaff = User::factory()->create([
            'name' => 'Staf Unit Lain',
            'unit_id' => $this->otherUnit->id,
            'position_title' => 'Staf Administrasi TI',
            'is_active' => true,
        ]);
        $this->otherUnitStaff->assignRole('staf_unit');

        $this->humasUser = User::where('email', 'humas@univbi.ac.id')->firstOrFail();
        $this->warekUser = User::where('email', 'warek1@univbi.ac.id')->firstOrFail();
    }

    private function makeValidPdfBinary(string $text = 'Naskah Resmi Universitas'): string
    {
        $pdf = new Fpdi;
        $pdf->AddPage('P', [210, 297]);
        $pdf->SetFont('Helvetica', '', 12);
        $pdf->Cell(0, 10, $text);

        return $pdf->Output('S');
    }

    private function createCategoryWithFlow(bool $requiresWetSignature, array $steps): DocumentCategory
    {
        $category = DocumentCategory::create([
            'code' => $requiresWetSignature ? 'SKWET' : 'SKREG',
            'name' => $requiresWetSignature ? 'SK Wajib TTD Basah' : 'SK Reguler Tanpa TTD Basah',
            'slug' => $requiresWetSignature ? 'sk-wajib-ttd-basah' : 'sk-reguler-tanpa-ttd-basah',
            'number_format' => '{seq}/UNIVBI.{unit}/{cat}/{roman_month}/{year}',
            'stamping_position' => StampingPosition::TopRight,
            'requires_approval' => true,
            'requires_wet_signature' => $requiresWetSignature,
            'allow_manual_number' => false,
            'has_expiry' => false,
            'is_active' => true,
        ]);

        foreach ($steps as $index => $step) {
            CategoryApprovalFlow::create([
                'document_category_id' => $category->id,
                'step_order' => $index + 1,
                'approver_role' => $step['role'],
                'step_label' => $step['label'],
                'is_mandatory' => true,
            ]);
        }

        return $category;
    }

    private function createDraftDocumentWithFile(DocumentCategory $category, array $attributes = []): Document
    {
        $uuid = (string) Str::uuid();
        $document = Document::create(array_merge([
            'uuid' => $uuid,
            'title' => 'SK Pengangkatan Tenaga Kependidikan',
            'unit_id' => $this->sdmUnit->id,
            'document_category_id' => $category->id,
            'academic_year_id' => $this->academicYear->id,
            'uploaded_by' => $this->sdmStaff->id,
            'issued_date' => '2026-11-19',
            'visibility' => VisibilityLevel::Public,
            'status' => DocumentStatus::Draft,
            'requires_wet_signature' => $category->requires_wet_signature,
        ], $attributes));

        $binary = $this->makeValidPdfBinary($document->title);
        $path = "documents/2026/11/SDM/{$uuid}.pdf";
        Storage::disk('private')->put($path, $binary);

        DocumentFile::create([
            'document_id' => $document->id,
            'uploaded_by' => $this->sdmStaff->id,
            'is_primary' => true,
            'version' => 1,
            'original_file_name' => 'draf-naskah.pdf',
            'standardized_file_name' => '2026-SDM-draf-naskah.pdf',
            'original_file_path' => $path,
            'disk' => StorageDisk::Private,
            'mime_type' => 'application/pdf',
            'file_extension' => 'pdf',
            'file_size' => strlen($binary),
            'file_hash_sha256' => hash('sha256', $binary),
            'is_active' => true,
        ]);

        return $document;
    }

    public function test_non_wet_signature_category_still_transitions_directly_to_approved(): void
    {
        $category = $this->createCategoryWithFlow(false, [
            ['role' => 'warek', 'label' => 'Persetujuan Wakil Rektor'],
        ]);

        $doc = $this->createDraftDocumentWithFile($category, [
            'title' => 'SK Reguler Langsung Sah',
        ]);

        $service = app(DocumentApprovalService::class);
        $service->initializeApprovalFlow($doc);

        $this->assertTrue($service->approve($doc->fresh(), $this->warekUser, 'Disetujui langsung tanpa TTD basah.'));

        $doc->refresh();
        $this->assertSame(DocumentStatus::Approved, $doc->status);
        $this->assertNotNull($doc->document_number);
        $this->assertNotNull($doc->approved_at);
        $this->assertFalse($doc->requires_wet_signature);
        $this->assertNull($doc->final_scan_uploaded_by);
    }

    public function test_multistep_sdm_humas_warek_wet_signature_workflow_end_to_end(): void
    {
        $category = $this->createCategoryWithFlow(true, [
            ['role' => 'humas', 'label' => 'Verifikasi Redaksi Humas'],
            ['role' => 'warek', 'label' => 'Pengesahan Wakil Rektor'],
        ]);

        // Dokumen lama yang digantikan: harus tetap Approved sampai scan TTD basah diunggah
        $oldApprovedDoc = Document::create([
            'uuid' => (string) Str::uuid(),
            'title' => 'SK Lama Tahun Lalu',
            'document_number' => '001/UNIVBI.SDM/SKWET/I/2025',
            'unit_id' => $this->sdmUnit->id,
            'document_category_id' => $category->id,
            'academic_year_id' => $this->academicYear->id,
            'uploaded_by' => $this->sdmStaff->id,
            'issued_date' => '2025-01-10',
            'visibility' => VisibilityLevel::Public,
            'status' => DocumentStatus::Approved,
            'approved_at' => now()->subYear(),
            'requires_wet_signature' => false,
        ]);

        $doc = $this->createDraftDocumentWithFile($category, [
            'title' => 'SK Pengangkatan Pegawai Baru 2026',
            'replaces_document_id' => $oldApprovedDoc->id,
        ]);

        $service = app(DocumentApprovalService::class);
        $service->initializeApprovalFlow($doc);

        $doc->refresh();
        $this->assertSame(DocumentStatus::Pending, $doc->status);
        $this->assertSame(1, $doc->current_step);
        $this->assertNull($doc->document_number);

        // 1. Pastikan Humas (tanpa document.approve global) bisa melihat & menyetujui Step 1 lintas unit
        $this->assertFalse($this->humasUser->can('document.approve'));
        $this->assertTrue($service->canUserApprove($this->humasUser, $doc));
        $this->assertTrue(
            $service->getPendingApprovalsQueryForUser($this->humasUser)->whereKey($doc->id)->exists()
        );
        $this->assertFalse($service->canUploadFinalScan($this->sdmStaff, $doc));

        $this->assertTrue($service->approve($doc, $this->humasUser, 'Redaksi dan tata naskah sudah sesuai.'));

        $doc->refresh();
        $this->assertSame(DocumentStatus::Pending, $doc->status);
        $this->assertSame(2, $doc->current_step);
        // Nomor surat resmi BELUM terbit sebelum final ACC berjenjang selesai
        $this->assertNull($doc->document_number);

        // 2. Warek melakukan ACC final (Step 2)
        $this->assertTrue($service->canUserApprove($this->warekUser, $doc));
        $this->assertTrue($service->approve($doc, $this->warekUser, 'Disetujui, silakan cetak untuk TTD basah & cap.'));

        $doc->refresh();
        $doc->load('primaryFile');

        $this->assertSame(DocumentStatus::WaitingSignature, $doc->status);
        $this->assertNotNull($doc->document_number);
        $this->assertSame($this->warekUser->id, $doc->final_approved_by);
        $this->assertNotNull($doc->approved_at);
        $this->assertNotNull($doc->primaryFile?->stamped_file_path);
        Storage::disk('private')->assertExists((string) $doc->primaryFile->stamped_file_path);

        // Dokumen lama yang digantikan TIDAK boleh diarsipkan sebelum scan TTD basah selesai
        $this->assertSame(DocumentStatus::Approved, $oldApprovedDoc->fresh()->status);

        // 3. Notifikasi database terkirim ke unit pengaju dan Humas
        $this->assertTrue(
            $this->sdmStaff->notifications()->where('data->title', 'like', '%Siap TTD Basah%')->exists()
        );
        $this->assertTrue(
            $this->humasUser->notifications()->where('data->title', 'like', '%Siap Cap & Scan Final%')->exists()
        );

        // 4. Verifikasi UI ListDocuments tab waiting_signature & ViewDocument banner
        $this->actingAs($this->sdmStaff);
        Livewire::test(ListDocuments::class)
            ->assertSuccessful()
            ->set('activeTab', 'waiting_signature')
            ->assertCanSeeTableRecords([$doc]);

        Livewire::test(ViewDocument::class, ['record' => $doc->getKey()])
            ->assertSuccessful()
            ->assertSee('Unduh Berkas Bernomor untuk Dicetak')
            ->assertActionExists('uploadFinalScan');

        // Edit umum (EditDocument) dikunci selama WaitingSignature bagi non-super_admin
        $this->assertFalse($this->sdmStaff->can('update', $doc));
        Livewire::test(EditDocument::class, ['record' => $doc->getKey()])
            ->assertForbidden();

        // 5. Portal verifikasi publik saat WaitingSignature: tampil info tunggu & tolak stream/download (403)
        $verifyWaiting = $this->get(route('document.verify.show', $doc->uuid));
        $verifyWaiting->assertSuccessful();
        $verifyWaiting->assertSee('Menunggu TTD Basah & Cap', false);
        $verifyWaiting->assertSee($doc->document_number);

        $this->get(route('document.verify.stream', $doc->uuid))->assertForbidden();
        $this->get(route('document.verify.download', $doc->uuid))->assertForbidden();

        // 6. Pengujian fleksibilitas upload scan final: Pengaju SDM, rekan satu unit, & Humas berhak; unit lain ditolak
        $this->assertTrue($service->canUploadFinalScan($this->sdmStaff, $doc));
        $this->assertTrue($service->canUploadFinalScan($this->humasUser, $doc));
        $this->assertFalse($service->canUploadFinalScan($this->otherUnitStaff, $doc));

        // 7. Pengunggahan scan final oleh Humas (atau unit pengaju) dengan PDF valid
        $preSignedFile = $doc->fresh()->primaryFile;
        $this->assertNotNull($preSignedFile);
        $oldOriginalPath = $preSignedFile->original_file_path;
        $oldStampedPath = $preSignedFile->stamped_file_path;
        $this->assertNotNull($oldStampedPath);
        Storage::disk('private')->assertExists($oldOriginalPath);
        Storage::disk('private')->assertExists($oldStampedPath);

        $scannedPdfBinary = $this->makeValidPdfBinary('HASIL SCAN RESMI BER-TTD BASAH DAN CAP REKTOR');
        $scannedRelativePath = "documents/2026/11/SDM/scan-final-{$doc->uuid}.pdf";
        Storage::disk('private')->put($scannedRelativePath, $scannedPdfBinary);
        $expectedScanSha256 = hash('sha256', $scannedPdfBinary);

        $finalizedDoc = $service->uploadFinalSignedScan(
            document: $doc,
            actor: $this->humasUser,
            storedFilePath: $scannedRelativePath,
            originalFileName: 'sk-pegawai-ttd-basah-cap.pdf',
            note: 'Telah ditandatangani basah Warek & dicap basah Humas.'
        );

        $this->assertSame(DocumentStatus::Approved, $finalizedDoc->status);
        $this->assertSame($this->humasUser->id, $finalizedDoc->final_scan_uploaded_by);
        $this->assertNotNull($finalizedDoc->final_scan_uploaded_at);

        // Versi lama (v1) tetap tersimpan non-aktif dengan original_file_path dipertahankan,
        // sedangkan file stamped pra-TTD basah lama telah dihapus otomatis (Opsi A: sisa 2 file).
        $v1File = DocumentFile::where('document_id', $finalizedDoc->id)->where('version', 1)->firstOrFail();
        $v2File = DocumentFile::where('document_id', $finalizedDoc->id)->where('version', 2)->firstOrFail();

        $this->assertFalse((bool) $v1File->is_active);
        $this->assertFalse((bool) $v1File->is_primary);
        $this->assertNull($v1File->stamped_file_path);
        $this->assertNull($v1File->stamped_file_hash_sha256);
        Storage::disk('private')->assertExists($oldOriginalPath);
        Storage::disk('private')->assertMissing($oldStampedPath);

        $this->assertTrue((bool) $v2File->is_active);
        $this->assertTrue((bool) $v2File->is_primary);
        $this->assertNull($v2File->stamped_file_path);
        $this->assertSame($expectedScanSha256, $v2File->getEffectiveSha256Hash());
        $this->assertSame($scannedRelativePath, $v2File->getEffectiveFilePath());
        Storage::disk('private')->assertExists($scannedRelativePath);

        // Dokumen yang digantikan BARU diarsipkan otomatis saat arsip scan final disahkan
        $this->assertSame(DocumentStatus::Archived, $oldApprovedDoc->fresh()->status);

        // Audit log terekam
        $this->assertTrue(
            ActivityLog::where('event', 'final_scan_uploaded')
                ->where('subject_type', Document::class)
                ->where('subject_id', $finalizedDoc->id)
                ->exists()
        );

        // 8. Portal verifikasi publik kini sah dan menampilkan SHA-256 file scan final serta membuka unduhan
        $verifyValid = $this->get(route('document.verify.show', $finalizedDoc->uuid));
        $verifyValid->assertSuccessful();
        $verifyValid->assertSee('DOKUMEN RESMI TERVERIFIKASI');
        $verifyValid->assertSee($expectedScanSha256);

        $downloadResponse = $this->get(route('document.verify.download', $finalizedDoc->uuid));
        $downloadResponse->assertOk();

        // 9. Force delete permanen menghapus sisa berkas fisik (original & scan final) agar storage bersih
        $finalizedDoc->approvals()->delete();
        $finalizedDoc->forceDelete();

        Storage::disk('private')->assertMissing($oldOriginalPath);
        Storage::disk('private')->assertMissing($scannedRelativePath);
    }

    public function test_wet_signature_upload_rejects_unauthorized_spoofed_pdf_and_duplicate_race(): void
    {
        $category = $this->createCategoryWithFlow(true, [
            ['role' => 'warek', 'label' => 'Persetujuan Pimpinan'],
        ]);

        $doc = $this->createDraftDocumentWithFile($category, [
            'title' => 'SK Uji Keamanan Scan TTD',
        ]);

        $service = app(DocumentApprovalService::class);
        $service->initializeApprovalFlow($doc);
        $service->approve($doc->fresh(), $this->warekUser);
        $doc->refresh();
        $this->assertSame(DocumentStatus::WaitingSignature, $doc->status);

        // 1. Validasi form rule menolak file palsu (txt berekstensi .pdf)
        $spoofedUpload = UploadedFile::fake()->createWithContent('palsu.pdf', 'BUKAN FILE PDF ASLI');
        $validator = Validator::make(['file' => $spoofedUpload], ['file' => [new PdfDocumentFileRule]]);
        $this->assertTrue($validator->fails());

        // 2. Service menolak file palsu di storage
        $badPath = 'documents/2026/11/SDM/spoofed.pdf';
        Storage::disk('private')->put($badPath, 'PLAINTEXT MALICIOUS CONTENT');
        try {
            $service->uploadFinalSignedScan($doc, $this->sdmStaff, $badPath, 'spoofed.pdf');
            $this->fail('Seharusnya melempar InvalidArgumentException untuk file non-PDF.');
        } catch (InvalidArgumentException) {
            $this->addToAssertionCount(1);
        }

        // 3. User unit lain tidak berhak mengunggah & file yatim otomatis dibersihkan
        $validPdf1 = $this->makeValidPdfBinary('PDF Valid Dari Unit Lain');
        $unauthorizedPath = 'documents/2026/11/SDM/unauthorized-upload.pdf';
        Storage::disk('private')->put($unauthorizedPath, $validPdf1);

        try {
            $service->uploadFinalSignedScan($doc, $this->otherUnitStaff, $unauthorizedPath, 'unauthorized.pdf');
            $this->fail('Seharusnya melempar AuthorizationException untuk unit lain.');
        } catch (AuthorizationException) {
            $this->addToAssertionCount(1);
        }
        Storage::disk('private')->assertMissing($unauthorizedPath);

        // 4. Upload pertama oleh SDM berhasil
        $validPdf2 = $this->makeValidPdfBinary('PDF Scan Final Valid SDM');
        $firstPath = 'documents/2026/11/SDM/valid-sdm-scan.pdf';
        Storage::disk('private')->put($firstPath, $validPdf2);

        $service->uploadFinalSignedScan($doc, $this->sdmStaff, $firstPath, 'valid-sdm-scan.pdf');
        $this->assertSame(DocumentStatus::Approved, $doc->fresh()->status);

        // 5. Upload kedua (simulasi race condition) oleh Humas harus ditolak dan membersihkan file kedua
        $secondPath = 'documents/2026/11/SDM/race-second-scan.pdf';
        Storage::disk('private')->put($secondPath, $this->makeValidPdfBinary('PDF Kedua Race Condition'));

        try {
            $service->uploadFinalSignedScan($doc, $this->humasUser, $secondPath, 'race-second-scan.pdf');
            $this->fail('Seharusnya melempar InvalidArgumentException saat upload ganda.');
        } catch (InvalidArgumentException) {
            $this->addToAssertionCount(1);
        }

        Storage::disk('private')->assertMissing($secondPath);
        Storage::disk('private')->assertExists($firstPath);
    }

    public function test_waiting_signature_document_can_be_returned_to_revision_on_print_mistake(): void
    {
        $category = $this->createCategoryWithFlow(true, [
            ['role' => 'warek', 'label' => 'Persetujuan Pimpinan'],
        ]);

        $doc = $this->createDraftDocumentWithFile($category, [
            'title' => 'SK Salah Ketik Saat Cetak',
        ]);

        $service = app(DocumentApprovalService::class);
        $service->initializeApprovalFlow($doc);
        $service->approve($doc->fresh(), $this->warekUser);
        $doc->refresh();

        $this->assertSame(DocumentStatus::WaitingSignature, $doc->status);
        $this->assertFalse($service->canReturnWaitingSignatureToRevision($this->sdmStaff, $doc));
        $this->assertTrue($service->canReturnWaitingSignatureToRevision($this->humasUser, $doc));

        $returned = $service->returnWaitingSignatureToRevision($doc, $this->humasUser, 'Typo pada jabatan lampiran sebelum TTD.');
        $this->assertSame(DocumentStatus::Revision, $returned->status);

        $this->assertTrue(
            $this->sdmStaff->notifications()->where('data->title', 'like', '%Dikembalikan ke Revisi%')->exists()
        );
    }

    public function test_outgoing_letter_wet_signature_workflow_flexible_upload_by_humas_or_creator(): void
    {
        $draftPath = 'outgoing-letters/draft-kerjasama.pdf';
        Storage::disk('private')->put($draftPath, $this->makeValidPdfBinary('Draf Surat Keluar Kerjasama'));

        $letter = OutgoingLetter::create([
            'unit_id' => $this->sdmUnit->id,
            'created_by' => $this->sdmStaff->id,
            'destination_organization' => 'Universitas Mitra Nasional',
            'subject' => 'Permohonan Penandatanganan MoU',
            'letter_date' => '2026-11-19',
            'original_file_path' => $draftPath,
            'file_name' => 'draft-kerjasama.pdf',
            'status' => OutgoingLetterStatus::PendingHumas,
        ]);

        $letterService = app(LetterManagementService::class);
        $letterService->processNewOutgoingLetter($letter, $this->sdmStaff);
        $letter->refresh();
        $this->assertSame(OutgoingLetterStatus::PendingHumas, $letter->status);

        // Humas ACC & menerbitkan nomor dengan opsi TTD basah & cap diaktifkan
        $letterService->approveOutgoingLetter(
            letter: $letter,
            humasUser: $this->humasUser,
            note: 'Silakan cetak, TTD basah Rektor & cap Humas.',
            requiresWetSignature: true,
        );

        $letter->refresh();
        $this->assertSame(OutgoingLetterStatus::WaitingSignature, $letter->status);
        $this->assertTrue($letter->requires_wet_signature);
        $this->assertNotNull($letter->letter_number);
        $this->assertNotNull($letter->stamped_file_path);
        Storage::disk('private')->assertExists((string) $letter->stamped_file_path);

        // Cek fleksibilitas izin unggah scan final
        $this->assertTrue($letter->canUploadFinalScan($this->sdmStaff));
        $this->assertTrue($letter->canUploadFinalScan($this->humasUser));
        $this->assertFalse($letter->canUploadFinalScan($this->otherUnitStaff));

        // Verifikasi tab waiting_signature & UI ViewOutgoingLetter
        $this->actingAs($this->humasUser);
        Livewire::test(ListOutgoingLetters::class)
            ->assertSuccessful()
            ->set('activeTab', 'waiting_signature')
            ->assertCanSeeTableRecords([$letter]);

        Livewire::test(ViewOutgoingLetter::class, ['record' => $letter->getKey()])
            ->assertSuccessful()
            ->assertActionExists('uploadFinalScan');

        // Pengunggahan scan final oleh unit pengaju (creator)
        $scanBinary = $this->makeValidPdfBinary('Surat Keluar Final Ber-TTD Basah & Cap');
        $scanPath = 'outgoing-letters/final-scan-mou.pdf';
        Storage::disk('private')->put($scanPath, $scanBinary);

        $published = $letterService->uploadOutgoingFinalScan(
            letter: $letter,
            actor: $this->sdmStaff,
            storedFilePath: $scanPath,
            originalFileName: 'mou-final-ttd-cap.pdf',
            note: 'Scan akhir diunggah oleh Biro SDM.'
        );

        $this->assertSame(OutgoingLetterStatus::Published, $published->status);
        $this->assertSame($this->sdmStaff->id, $published->final_scan_uploaded_by);
        $this->assertNotNull($published->final_scan_uploaded_at);
        $this->assertSame($scanPath, $published->getActiveFilePath());
        $this->assertNull($published->stamped_file_path);

        // Upload kedua harus gagal dan menghapus file yatim
        $secondScanPath = 'outgoing-letters/race-second-mou.pdf';
        Storage::disk('private')->put($secondScanPath, $this->makeValidPdfBinary('Duplikat Upload'));
        try {
            $letterService->uploadOutgoingFinalScan($published, $this->humasUser, $secondScanPath, 'race.pdf');
            $this->fail('Seharusnya melempar InvalidArgumentException untuk surat keluar yang sudah Terbit.');
        } catch (InvalidArgumentException) {
            $this->addToAssertionCount(1);
        }

        Storage::disk('private')->assertMissing($secondScanPath);
    }

    public function test_create_outgoing_letter_form_renders_and_applies_requires_wet_signature_toggle(): void
    {
        $pdfFile = UploadedFile::fake()->createWithContent(
            'surat-keluar-undangan.pdf',
            $this->makeValidPdfBinary('Surat Keluar Undangan Resmi')
        );

        // 1. Humas membuat langsung dengan toggle requires_wet_signature aktif -> langsung terbit nomor & masuk WaitingSignature
        $this->actingAs($this->humasUser);

        Livewire::test(CreateOutgoingLetter::class)
            ->assertSuccessful()
            ->assertSee('Perlu TTD Basah, Cap &amp; Scan Final', false)
            ->fillForm([
                'unit_id' => $this->sdmUnit->id,
                'academic_year_id' => $this->academicYear->id,
                'letter_date' => '2026-11-19',
                'destination_organization' => 'Kementerian Pendidikan',
                'subject' => 'Permohonan Audiensi Pimpinan',
                'requires_wet_signature' => true,
                'original_file_path' => $pdfFile,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $humasLetter = OutgoingLetter::where('subject', 'Permohonan Audiensi Pimpinan')->firstOrFail();
        $this->assertTrue($humasLetter->requires_wet_signature);
        $this->assertNotNull($humasLetter->letter_number);
        $this->assertSame(OutgoingLetterStatus::WaitingSignature, $humasLetter->status);

        // 2. Unit pengaju membuat surat keluar dengan toggle requires_wet_signature aktif -> menunggu Humas dengan flag tersimpan
        $this->actingAs($this->sdmStaff);
        $pdfUnitFile = UploadedFile::fake()->createWithContent(
            'surat-keluar-unit.pdf',
            $this->makeValidPdfBinary('Surat Keluar dari Unit SDM')
        );

        Livewire::test(CreateOutgoingLetter::class)
            ->assertSuccessful()
            ->assertSee('Perlu TTD Basah, Cap &amp; Scan Final', false)
            ->fillForm([
                'academic_year_id' => $this->academicYear->id,
                'letter_date' => '2026-11-19',
                'destination_organization' => 'BPJS Ketenagakerjaan',
                'subject' => 'Pengajuan Kepesertaan Pegawai Baru',
                'requires_wet_signature' => true,
                'original_file_path' => $pdfUnitFile,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $unitLetter = OutgoingLetter::where('subject', 'Pengajuan Kepesertaan Pegawai Baru')->firstOrFail();
        $this->assertTrue($unitLetter->requires_wet_signature);
        $this->assertSame(OutgoingLetterStatus::PendingHumas, $unitLetter->status);
    }
}
