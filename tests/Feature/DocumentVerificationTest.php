<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\DegreeLevel;
use App\Enums\DocumentStatus;
use App\Enums\StampingPosition;
use App\Enums\StorageDisk;
use App\Enums\UnitType;
use App\Enums\VisibilityLevel;
use App\Models\AcademicYear;
use App\Models\ActivityLog;
use App\Models\Document;
use App\Models\DocumentCategory;
use App\Models\DocumentFile;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class DocumentVerificationTest extends TestCase
{
    use LazilyRefreshDatabase;

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
            'name' => 'Surat Keputusan Rektor',
            'slug' => 'surat-keputusan-rektor',
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

        $this->user = User::factory()->create([
            'name' => 'Dr. H. Sardiyo, M.M.',
            'unit_id' => $this->unit->id,
            'is_active' => true,
        ]);
    }

    private function createDocument(array $attributes = [], bool $withFile = true): Document
    {
        Storage::fake(StorageDisk::Private->value);

        $document = Document::create(array_merge([
            'uuid' => (string) Str::uuid(),
            'title' => 'SK Penetapan Dosen Pembimbing Skripsi Tahun 2026',
            'document_number' => '001/UNIVBI.TI/SK/XI/2026',
            'unit_id' => $this->unit->id,
            'document_category_id' => $this->category->id,
            'academic_year_id' => $this->academicYear->id,
            'uploaded_by' => $this->user->id,
            'visibility' => VisibilityLevel::Public,
            'status' => DocumentStatus::Approved,
            'approved_at' => now(),
            'issued_date' => now()->toDateString(),
            'signer_name_snapshot' => 'Dr. H. Sardiyo, M.M.',
            'signer_position_snapshot' => 'Rektor Universitas Bina Insan',
            'is_borang' => false,
            'has_expiry' => false,
        ], $attributes));

        if ($withFile) {
            $dummyPdfPath = "documents/2026/11/TI/{$document->uuid}.pdf";
            Storage::disk(StorageDisk::Private->value)->put($dummyPdfPath, '%PDF-1.4 dummy valid institutional pdf content');

            DocumentFile::create([
                'document_id' => $document->id,
                'disk' => StorageDisk::Private,
                'original_file_path' => $dummyPdfPath,
                'stamped_file_path' => $dummyPdfPath,
                'original_file_name' => 'sk-pembimbing-2026.pdf',
                'standardized_file_name' => '2026-TI-SK-penetapan-dosen.pdf',
                'file_extension' => 'pdf',
                'file_hash_sha256' => hash('sha256', '%PDF-1.4 dummy valid institutional pdf content'),
                'file_size' => 2048,
                'mime_type' => 'application/pdf',
                'is_primary' => true,
                'uploaded_by' => $this->user->id,
            ]);
        }

        return $document;
    }

    public function test_public_verification_page_renders_successfully_for_approved_document(): void
    {
        $document = $this->createDocument();

        $response = $this->get(route('document.verify.show', $document->uuid));

        $response->assertSuccessful();
        $response->assertSee('DOKUMEN RESMI TERVERIFIKASI');
        $response->assertSee('001/UNIVBI.TI/SK/XI/2026');
        $response->assertSee('SK Penetapan Dosen Pembimbing Skripsi Tahun 2026');
        $response->assertSee('Dr. H. Sardiyo, M.M.');
        $response->assertSee('Rektor Universitas Bina Insan');
        $response->assertSee('Program Studi Informatika');
        $response->assertSee('SHA-256 Otentik');

        // Views count increment
        $this->assertSame(1, $document->fresh()->views_count);

        // Audit log created
        $this->assertTrue(
            ActivityLog::where('event', 'verified')
                ->where('subject_type', Document::class)
                ->where('subject_id', $document->id)
                ->exists()
        );
    }

    public function test_public_verification_page_shows_expired_warning_when_document_is_expired(): void
    {
        $document = $this->createDocument([
            'has_expiry' => true,
            'expired_date' => now()->subDay()->toDateString(),
            'status' => DocumentStatus::Approved,
        ]);

        $response = $this->get(route('document.verify.show', $document->uuid));

        $response->assertSuccessful();
        $response->assertSee('MASA BERLAKU TELAH BERAKHIR (KEDALUWARSA)');
        $response->assertSee('Kedaluwarsa');
    }

    public function test_public_verification_page_shows_draft_notice_for_unapproved_document(): void
    {
        $document = $this->createDocument([
            'status' => DocumentStatus::Draft,
            'document_number' => null,
            'approved_at' => null,
        ]);

        $response = $this->get(route('document.verify.show', $document->uuid));

        $response->assertSuccessful();
        $response->assertSee('DOKUMEN BELUM DISAHKAN RESMI');
        $response->assertSee('Draf');

        // Views count should NOT increment for draft
        $this->assertSame(0, $document->fresh()->views_count);
    }

    public function test_public_verification_page_returns_404_and_invalid_view_for_non_existent_uuid(): void
    {
        $randomUuid = (string) Str::uuid();

        $response = $this->get(route('document.verify.show', $randomUuid));

        $response->assertNotFound();
        $response->assertSee('DOKUMEN TIDAK TERDAFTAR / TIDAK VALID');
        $response->assertSee('Waspada Potensi Pemalsuan Naskah Dinas');
        $response->assertSee($randomUuid);
    }

    public function test_public_document_allows_streaming_and_downloading_pdf_file(): void
    {
        $document = $this->createDocument([
            'visibility' => VisibilityLevel::Public,
            'status' => DocumentStatus::Approved,
        ]);

        // Stream
        $streamResponse = $this->get(route('document.verify.stream', $document->uuid));
        $streamResponse->assertSuccessful();
        $streamResponse->assertHeader('Content-Type', 'application/pdf');

        // Download
        $downloadResponse = $this->get(route('document.verify.download', $document->uuid));
        $downloadResponse->assertSuccessful();
        $this->assertSame(1, $document->fresh()->downloads_count);
    }

    public function test_internal_or_confidential_document_blocks_file_streaming_and_download(): void
    {
        $document = $this->createDocument([
            'visibility' => VisibilityLevel::Internal,
            'status' => DocumentStatus::Approved,
        ]);

        // Verification page should still be accessible and show official verification
        $viewResponse = $this->get(route('document.verify.show', $document->uuid));
        $viewResponse->assertSuccessful();
        $viewResponse->assertSee('Konten Naskah Dilindungi Kebijakan Privasi Institusi');
        $viewResponse->assertDontSee('<iframe', false);

        // Streaming and downloading must be forbidden (403)
        $streamResponse = $this->get(route('document.verify.stream', $document->uuid));
        $streamResponse->assertForbidden();

        $downloadResponse = $this->get(route('document.verify.download', $document->uuid));
        $downloadResponse->assertForbidden();
    }

    public function test_unapproved_document_blocks_file_streaming_and_download(): void
    {
        $document = $this->createDocument([
            'visibility' => VisibilityLevel::Public,
            'status' => DocumentStatus::Draft,
        ]);

        $streamResponse = $this->get(route('document.verify.stream', $document->uuid));
        $streamResponse->assertForbidden();

        $downloadResponse = $this->get(route('document.verify.download', $document->uuid));
        $downloadResponse->assertForbidden();
    }

    public function test_soft_deleted_document_returns_404_invalid_view(): void
    {
        $document = $this->createDocument([
            'status' => DocumentStatus::Approved,
        ]);

        $document->delete(); // Soft delete

        $response = $this->get(route('document.verify.show', $document->uuid));

        $response->assertNotFound();
        $response->assertSee('DOKUMEN TIDAK TERDAFTAR / TIDAK VALID');
    }

    public function test_verification_page_displays_final_stamped_sha256_and_matches_download_extension(): void
    {
        Storage::fake(StorageDisk::Private->value);

        $document = $this->createDocument([
            'status' => DocumentStatus::Approved,
            'visibility' => VisibilityLevel::Public,
            'document_number' => '055/UNIVBI.TI/SK/XI/2026',
        ], false);

        $stampedDocxPath = "documents/2026/11/TI/stamped-{$document->uuid}.docx";
        Storage::disk(StorageDisk::Private->value)->put($stampedDocxPath, 'FINAL_STAMPED_DOCX_BINARY');

        $draftHash = str_repeat('a', 64);
        $finalHash = hash('sha256', 'FINAL_STAMPED_DOCX_BINARY');

        DocumentFile::create([
            'document_id' => $document->id,
            'disk' => StorageDisk::Private,
            'original_file_path' => "documents/2026/11/TI/{$document->uuid}.docx",
            'stamped_file_path' => $stampedDocxPath,
            'original_file_name' => 'surat.docx',
            'standardized_file_name' => 'surat-resmi.docx',
            'file_extension' => 'docx',
            'file_hash_sha256' => $draftHash,
            'stamped_file_hash_sha256' => $finalHash,
            'file_size' => 1024,
            'mime_type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'is_primary' => true,
            'uploaded_by' => $this->user->id,
        ]);

        $viewResponse = $this->get(route('document.verify.show', $document->uuid));
        $viewResponse->assertSuccessful();
        $viewResponse->assertSee($finalHash);
        $viewResponse->assertDontSee($draftHash);
        $viewResponse->assertSee('Uji Keaslian File di Perangkat Anda');

        $downloadResponse = $this->get(route('document.verify.download', $document->uuid));
        $downloadResponse->assertOk();
        $this->assertStringContainsString('.docx', (string) $downloadResponse->headers->get('content-disposition'));
    }
}
