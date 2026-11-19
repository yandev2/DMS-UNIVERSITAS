<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Enums\DocumentStatus;
use App\Enums\StorageDisk;
use App\Models\AcademicYear;
use App\Models\AccreditationCriterion;
use App\Models\Document;
use App\Models\DocumentCategory;
use App\Models\DocumentFile;
use App\Models\Unit;
use App\Models\User;
use App\Services\BorangZipExportService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Tests\Support\LegacyMasterDataSeeder;
use Tests\TestCase;

class BorangZipExportServiceTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_downloads_zip_response_with_bundled_files(): void
    {
        $this->seed(LegacyMasterDataSeeder::class);
        Storage::fake(StorageDisk::Private->value);

        $unitTi = Unit::where('code', 'TI')->firstOrFail();
        $criterionK6 = AccreditationCriterion::where('code', 'K6')->firstOrFail();
        $category = DocumentCategory::firstOrFail();
        $academicYear = AcademicYear::firstOrFail();
        $uploader = User::firstOrFail();

        $document = Document::create([
            'title' => 'SK Kurikulum Informatika 2026',
            'document_number' => '001/UNIVBI.TI/SK/XI/2026',
            'unit_id' => $unitTi->id,
            'document_category_id' => $category->id,
            'academic_year_id' => $academicYear->id,
            'is_borang' => true,
            'status' => DocumentStatus::Approved,
            'issued_date' => now()->toDateString(),
            'uploaded_by' => $uploader->id,
        ]);

        $document->accreditationCriteria()->attach($criterionK6->id, [
            'indicator_code' => 'Butir 6.a',
            'order_index' => 1,
        ]);

        $dummyPdfPath = 'documents/2026/11/TI/test-sk-kurikulum.pdf';
        Storage::disk(StorageDisk::Private->value)->put($dummyPdfPath, '%PDF-1.4 dummy content');

        DocumentFile::create([
            'document_id' => $document->id,
            'disk' => StorageDisk::Private,
            'original_file_path' => $dummyPdfPath,
            'original_file_name' => 'test-sk-kurikulum.pdf',
            'standardized_file_name' => '2026-TI-SK-test.pdf',
            'file_extension' => 'pdf',
            'file_hash_sha256' => hash('sha256', 'dummy'),
            'file_size' => 1024,
            'mime_type' => 'application/pdf',
            'is_primary' => true,
            'uploaded_by' => $uploader->id,
        ]);

        $service = new BorangZipExportService;
        $response = $service->downloadZipResponse(
            Document::where('id', $document->id)->with(['accreditationCriteria', 'primaryFile'])->get(),
            'Paket-Test.zip'
        );

        $this->assertInstanceOf(StreamedResponse::class, $response);
        $this->assertSame('application/zip', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('Paket-Test.zip', (string) $response->headers->get('Content-Disposition'));

        // Capture streamed output
        ob_start();
        $response->sendContent();
        $content = ob_get_clean();

        $this->assertNotEmpty($content);
        // Standard ZIP header signature PK\x03\x04
        $this->assertStringStartsWith("PK\x03\x04", $content);
    }
}
