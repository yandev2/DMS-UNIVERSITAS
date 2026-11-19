<?php

namespace Tests\Unit\Services;

use App\Enums\AccreditationInstrument;
use App\Enums\DocumentStatus;
use App\Enums\PostPeriod;
use App\Enums\PostStatus;
use App\Models\AcademicYear;
use App\Models\AccreditationCriterion;
use App\Models\Document;
use App\Models\DocumentCategory;
use App\Models\DocumentFile;
use App\Models\Post;
use App\Models\PostCategory;
use App\Models\Unit;
use App\Models\User;
use App\Services\BorangExportService;
use App\Services\PostExportService;
use App\Support\SpreadsheetSanitizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use Tests\Support\LegacyMasterDataSeeder;
use Tests\TestCase;

class BorangExportServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_builds_informative_multi_sheet_spreadsheet_with_borang_documents(): void
    {
        $this->seed(LegacyMasterDataSeeder::class);

        $unitTi = Unit::where('code', 'TI')->firstOrFail();
        $unitTi->update([
            'is_assessor_access_active' => true,
            'assessor_access_start' => now()->subDay(),
            'assessor_access_end' => now()->addDays(14),
            'assessor_passcode' => 'TI2026',
        ]);

        $academicYear = AcademicYear::firstOrFail();
        $category = DocumentCategory::firstOrFail();
        $uploader = User::firstOrFail();
        $criterionK6 = $unitTi->getApplicableCriteria()->firstWhere('code', 'K6')
            ?? AccreditationCriterion::where('code', 'K6')->firstOrFail();

        $document = Document::create([
            'title' => 'Laporan Evaluasi Kurikulum Berbasis OBE Informatika Tahun Akademik 2026',
            'description' => 'Dokumen kompilasi evaluasi kurikulum program studi beserta berita acara peninjauan mitra industri.',
            'document_number' => '012/UNIVBI.TI/SK/XI/2026',
            'unit_id' => $unitTi->id,
            'document_category_id' => $category->id,
            'academic_year_id' => $academicYear->id,
            'is_borang' => true,
            'status' => DocumentStatus::Approved,
            'issued_date' => '2026-11-10',
            'has_expiry' => true,
            'expired_date' => '2030-11-10',
            'signer_name_snapshot' => 'Dr. Ahmad Fauzi, M.Kom.',
            'signer_position_snapshot' => 'Dekan Fakultas Teknik',
            'uploaded_by' => $uploader->id,
        ]);

        $document->accreditationCriteria()->attach($criterionK6->id, [
            'indicator_code' => '6.1.a',
            'note' => 'Telah diverifikasi tim penjaminan mutu internal',
            'order_index' => 1,
        ]);

        DocumentFile::create([
            'document_id' => $document->id,
            'uploaded_by' => $uploader->id,
            'is_primary' => true,
            'order_index' => 1,
            'version' => 1,
            'original_file_name' => 'kurikulum.pdf',
            'standardized_file_name' => '012-kurikulum.pdf',
            'original_file_path' => 'documents/012-kurikulum.pdf',
            'stamped_file_path' => 'documents/stamped/012-kurikulum.pdf',
            'mime_type' => 'application/pdf',
            'file_extension' => 'pdf',
            'file_size' => 1572864,
            'is_active' => true,
        ]);

        $documents = Document::query()
            ->whereKey($document->id)
            ->with(['unit', 'category', 'academicYear', 'accreditationCriteria', 'primaryFile', 'finalApprover'])
            ->get();

        $service = app(BorangExportService::class);
        $spreadsheet = $service->buildSpreadsheet(
            $documents,
            $unitTi,
            $academicYear,
            $criterionK6,
            AccreditationInstrument::LamInfokom->value
        );

        $this->assertSame(2, $spreadsheet->getSheetCount());
        $this->assertSame(0, $spreadsheet->getActiveSheetIndex());

        // Sheet 1 assertions
        $mainSheet = $spreadsheet->getSheet(0);
        $this->assertSame('Rekapitulasi Borang', $mainSheet->getTitle());
        $this->assertSame('UNIVERSITAS BINA INSAN (UNIVBI)', $mainSheet->getCell('A1')->getValue());
        $this->assertStringContainsString($unitTi->name, (string) $mainSheet->getCell('C3')->getValue());
        $this->assertSame($academicYear->name, $mainSheet->getCell('G3')->getValue());
        $this->assertStringContainsString('LAM-INFOKOM', (string) $mainSheet->getCell('J3')->getValue());
        $this->assertStringContainsString('K6', (string) $mainSheet->getCell('M3')->getValue());
        $this->assertSame('1 Naskah', $mainSheet->getCell('G4')->getValue());
        $this->assertSame('1 Baris Butir', $mainSheet->getCell('J4')->getValue());
        $this->assertSame('1/1 Berkas (100%)', $mainSheet->getCell('M4')->getValue());

        // Headers
        $this->assertSame('No', $mainSheet->getCell('A6')->getValue());
        $this->assertSame('Kriteria Akreditasi', $mainSheet->getCell('B6')->getValue());
        $this->assertSame('Kategori Naskah', $mainSheet->getCell('D6')->getValue());
        $this->assertSame('Nomor Surat Resmi', $mainSheet->getCell('E6')->getValue());
        $this->assertSame('Pejabat Pengesah', $mainSheet->getCell('J6')->getValue());
        $this->assertSame('Berkas Fisik', $mainSheet->getCell('K6')->getValue());
        $this->assertSame('Status Akses Asesor', $mainSheet->getCell('L6')->getValue());
        $this->assertSame('Tautan Bukti Asesor', $mainSheet->getCell('M6')->getValue());
        $this->assertSame('Verifikasi QR', $mainSheet->getCell('N6')->getValue());

        // Row 7 data
        $this->assertSame(1, $mainSheet->getCell('A7')->getValue());
        $this->assertStringContainsString('K6', (string) $mainSheet->getCell('B7')->getValue());
        $this->assertStringContainsString('6.1.a', (string) $mainSheet->getCell('C7')->getValue());
        $this->assertStringContainsString('Telah diverifikasi tim penjaminan mutu internal', (string) $mainSheet->getCell('C7')->getValue());
        $this->assertSame($category->name, $mainSheet->getCell('D7')->getValue());
        $this->assertSame('012/UNIVBI.TI/SK/XI/2026', $mainSheet->getCell('E7')->getValue());
        $this->assertStringContainsString('Laporan Evaluasi Kurikulum Berbasis OBE', (string) $mainSheet->getCell('F7')->getValue());
        $this->assertStringContainsString($unitTi->name, (string) $mainSheet->getCell('G7')->getValue());
        $this->assertSame($academicYear->name, $mainSheet->getCell('H7')->getValue());
        $this->assertStringContainsString('10/11/2026', (string) $mainSheet->getCell('I7')->getValue());
        $this->assertStringContainsString('Dr. Ahmad Fauzi, M.Kom.', (string) $mainSheet->getCell('J7')->getValue());
        $this->assertStringContainsString('PDF • 1.50 MB', (string) $mainSheet->getCell('K7')->getValue());
        $this->assertStringContainsString('Resmi (QR)', (string) $mainSheet->getCell('K7')->getValue());
        $this->assertSame('Akses Terbuka', $mainSheet->getCell('L7')->getValue());
        $this->assertStringStartsWith('=HYPERLINK("', (string) $mainSheet->getCell('M7')->getValue());
        $this->assertStringContainsString("/bukti-borang/{$document->uuid}", (string) $mainSheet->getCell('M7')->getValue());
        $this->assertStringContainsString("/verify/{$document->uuid}", (string) $mainSheet->getCell('N7')->getValue());

        // Dynamic row height is expanded for multi-line content
        $this->assertGreaterThan(24.0, $mainSheet->getRowDimension(7)->getRowHeight());

        // Summary footer row (Row 8)
        $this->assertSame('TOTAL REKAPITULASI (1 Dokumen Unik)', $mainSheet->getCell('A8')->getValue());
        $this->assertSame('=COUNTA(F7:F7)', $mainSheet->getCell('F8')->getValue());
        $this->assertSame('=COUNTIF(K7:K7,"*•*")', $mainSheet->getCell('K8')->getValue());
        $this->assertSame('=COUNTIF(L7:L7,"Akses Terbuka")', $mainSheet->getCell('L8')->getValue());
        $this->assertSame('A6:N7', $mainSheet->getAutoFilter()->getRange());

        // Sheet 2 assertions
        $summarySheet = $spreadsheet->getSheet(1);
        $this->assertSame('Ringkasan & Portal Asesor', $summarySheet->getTitle());
        $this->assertSame('K6', $summarySheet->getCell('A6')->getValue());
        $this->assertSame(1, $summarySheet->getCell('D6')->getValue());
        $this->assertSame(1, $summarySheet->getCell('E6')->getValue());
        $this->assertSame(1, $summarySheet->getCell('F6')->getValue());
        $this->assertSame('=IF(E6>0,F6/E6,0)', $summarySheet->getCell('G6')->getValue());

        // Unit Portal Assessor section row
        $this->assertSame('TI', $summarySheet->getCell('A11')->getValue());
        $this->assertSame($unitTi->name, $summarySheet->getCell('B11')->getValue());
        $this->assertSame(1, $summarySheet->getCell('C11')->getValue());
        $this->assertSame('Akses Terbuka', $summarySheet->getCell('D11')->getValue());
        $this->assertSame('TI2026', $summarySheet->getCell('F11')->getValue());
        $this->assertStringContainsString("/bukti-borang/unit/{$unitTi->code}", (string) $summarySheet->getCell('G11')->getValue());
    }

    public function test_scopes_multi_criteria_document_when_criterion_filter_is_given_and_handles_empty_collection(): void
    {
        $this->seed(LegacyMasterDataSeeder::class);

        $unitTi = Unit::where('code', 'TI')->firstOrFail();
        $academicYear = AcademicYear::firstOrFail();
        $category = DocumentCategory::firstOrFail();
        $uploader = User::firstOrFail();

        $criterionK1 = $unitTi->getApplicableCriteria()->firstWhere('code', 'K1')
            ?? AccreditationCriterion::where('code', 'K1')->firstOrFail();
        $criterionK6 = $unitTi->getApplicableCriteria()->firstWhere('code', 'K6')
            ?? AccreditationCriterion::where('code', 'K6')->firstOrFail();

        $document = Document::create([
            'title' => 'Dokumen Lintas Kriteria K1 dan K6',
            'document_number' => '099/UNIVBI.TI/SK/XI/2026',
            'unit_id' => $unitTi->id,
            'document_category_id' => $category->id,
            'academic_year_id' => $academicYear->id,
            'is_borang' => true,
            'status' => DocumentStatus::Approved,
            'issued_date' => now()->toDateString(),
            'uploaded_by' => $uploader->id,
        ]);

        $document->accreditationCriteria()->attach([
            $criterionK1->id => ['indicator_code' => '1.1', 'order_index' => 1],
            $criterionK6->id => ['indicator_code' => '6.2', 'order_index' => 2],
        ]);

        $service = app(BorangExportService::class);

        // With K6 filter, only K6 mapping is emitted in Sheet 1
        $filteredSpreadsheet = $service->buildSpreadsheet(
            collect([$document->load(['unit', 'category', 'academicYear', 'accreditationCriteria', 'primaryFile'])]),
            $unitTi,
            null,
            $criterionK6
        );
        $mainSheet = $filteredSpreadsheet->getSheet(0);
        $this->assertSame('1 Baris Butir', $mainSheet->getCell('J4')->getValue());
        $this->assertStringContainsString('K6', (string) $mainSheet->getCell('B7')->getValue());
        $this->assertSame('TOTAL REKAPITULASI (1 Dokumen Unik)', $mainSheet->getCell('A8')->getValue());
        $this->assertSame('Belum Ada Berkas', $mainSheet->getCell('K7')->getValue());

        // Empty collection renders empty state cleanly
        $emptySpreadsheet = $service->buildSpreadsheet(collect());
        $emptyMainSheet = $emptySpreadsheet->getSheet(0);
        $this->assertSame(
            'Tidak ada dokumen bukti borang yang sesuai dengan filter saat ini.',
            $emptyMainSheet->getCell('A7')->getValue()
        );
    }

    public function test_spreadsheet_exports_neutralize_formula_injection_payloads(): void
    {
        $this->seed(LegacyMasterDataSeeder::class);

        $unitTi = Unit::where('code', 'TI')->firstOrFail();
        $academicYear = AcademicYear::firstOrFail();
        $category = DocumentCategory::firstOrFail();
        $uploader = User::firstOrFail();
        $criterionK6 = $unitTi->getApplicableCriteria()->firstWhere('code', 'K6')
            ?? AccreditationCriterion::where('code', 'K6')->firstOrFail();

        $maliciousDoc = Document::create([
            'title' => "=CMD|'/C calc'!A0",
            'description' => '+SUM(1,1)',
            'document_number' => '@INJECTION-001',
            'unit_id' => $unitTi->id,
            'document_category_id' => $category->id,
            'academic_year_id' => $academicYear->id,
            'is_borang' => true,
            'status' => DocumentStatus::Approved,
            'issued_date' => now()->toDateString(),
            'signer_name_snapshot' => '-2+3',
            'uploaded_by' => $uploader->id,
        ]);

        $maliciousDoc->accreditationCriteria()->attach($criterionK6->id, [
            'indicator_code' => "\t=HYPERLINK(\"http://evil.test\")",
            'order_index' => 1,
        ]);

        $borangSheet = app(BorangExportService::class)->buildSpreadsheet(
            collect([$maliciousDoc->load(['unit', 'category', 'academicYear', 'accreditationCriteria', 'primaryFile'])]),
            $unitTi,
            $academicYear,
            $criterionK6
        )->getSheet(0);

        $this->assertSame(DataType::TYPE_STRING, $borangSheet->getCell('C7')->getDataType());
        $this->assertTrue($borangSheet->getStyle('C7')->getQuotePrefix());
        $this->assertStringStartsWith("'", (string) $borangSheet->getCell('C7')->getValue());

        $this->assertSame(DataType::TYPE_STRING, $borangSheet->getCell('E7')->getDataType());
        $this->assertSame("'@INJECTION-001", $borangSheet->getCell('E7')->getValue());

        $this->assertSame(DataType::TYPE_STRING, $borangSheet->getCell('F7')->getDataType());
        $this->assertSame("'=CMD|'/C calc'!A0\n+SUM(1,1)", $borangSheet->getCell('F7')->getValue());

        $this->assertSame(DataType::TYPE_STRING, $borangSheet->getCell('J7')->getDataType());
        $this->assertSame("'-2+3", $borangSheet->getCell('J7')->getValue());

        // Uji PostExportService juga menetralkan judul berbahaya
        $postCategory = PostCategory::create([
            'name' => 'Warta',
            'slug' => 'warta',
            'is_active' => true,
        ]);
        $author = User::firstOrFail();

        $maliciousPost = Post::create([
            'title' => '=IMPORTXML("https://evil.test", "//a")',
            'slug' => 'artikel-uji-injeksi',
            'post_category_id' => $postCategory->id,
            'period' => PostPeriod::Daily,
            'unit_id' => $unitTi->id,
            'author_id' => $author->id,
            'content' => '<p>Konten.</p>',
            'status' => PostStatus::Published,
            'published_at' => now(),
        ]);

        $postSheet = app(PostExportService::class)->buildSpreadsheetFromQuery(
            Post::query()->whereKey($maliciousPost->id),
            PostPeriod::Daily
        )->getSheet(0);

        $this->assertSame(DataType::TYPE_STRING, $postSheet->getCell('D5')->getDataType());
        $this->assertTrue($postSheet->getStyle('D5')->getQuotePrefix());
        $this->assertSame("'=IMPORTXML(\"https://evil.test\", \"//a\")", $postSheet->getCell('D5')->getValue());
        // Rumus SUM internal tetap valid
        $this->assertSame(DataType::TYPE_FORMULA, $postSheet->getCell('I6')->getDataType());

        // Uji placeholder '-' tetap utuh dan skema URL non-http/https ditolak menjadi teks biasa
        $scratch = new Spreadsheet;
        $ws = $scratch->getActiveSheet();
        SpreadsheetSanitizer::setSafeStringCell($ws, 'A1', '-');
        SpreadsheetSanitizer::setSafeHyperlinkCell($ws, 'A2', 'javascript:alert(1)', 'Klik');

        $this->assertSame('-', $ws->getCell('A1')->getValue());
        $this->assertFalse($ws->getStyle('A1')->getQuotePrefix());
        $this->assertSame(DataType::TYPE_STRING, $ws->getCell('A2')->getDataType());
        $this->assertSame('Klik', $ws->getCell('A2')->getValue());
    }
}
