<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Enums\DegreeLevel;
use App\Enums\DocumentStatus;
use App\Enums\StampingPosition;
use App\Enums\StorageDisk;
use App\Enums\UnitType;
use App\Enums\VisibilityLevel;
use App\Exceptions\DocumentStampingException;
use App\Models\AcademicYear;
use App\Models\Document;
use App\Models\DocumentCategory;
use App\Models\DocumentFile;
use App\Models\Unit;
use App\Models\User;
use App\Services\DocumentNamingService;
use App\Services\DocumentStampingService;
use FPDF;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use setasign\Fpdi\Fpdi;
use Tests\TestCase;

class DocumentStampingServiceTest extends TestCase
{
    use RefreshDatabase;

    private DocumentStampingService $service;

    private Unit $unit;

    private DocumentCategory $category;

    private AcademicYear $academicYear;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new DocumentStampingService(new DocumentNamingService);

        $this->unit = Unit::create([
            'code' => 'TI',
            'name' => 'Teknik Informatika',
            'slug' => 'teknik-informatika',
            'type' => UnitType::Prodi,
            'degree_level' => DegreeLevel::S1,
            'is_active' => true,
        ]);

        $this->category = DocumentCategory::create([
            'code' => 'SK',
            'name' => 'Surat Keputusan',
            'slug' => 'surat-keputusan',
            'stamp_position' => StampingPosition::TopRight,
            'stamp_x_offset' => 0,
            'stamp_y_offset' => 0,
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
            'name' => 'Dr. Bambang Wijaya, M.Kom.',
            'unit_id' => $this->unit->id,
            'is_active' => true,
        ]);
    }

    public function test_it_calculates_coordinates_for_all_stamping_positions(): void
    {
        // A4 Portrait = 210 x 297 mm, Kotak = 84 x 24 mm
        $pageW = 210.0;
        $pageH = 297.0;
        $boxW = 84.0;
        $boxH = 24.0;

        // 1. TopRight: x = 210 - 84 - 10 = 116, y = 8
        $coordsTR = $this->service->calculateStampCoordinates($pageW, $pageH, $boxW, $boxH, StampingPosition::TopRight);
        $this->assertSame(116.0, $coordsTR['x']);
        $this->assertSame(8.0, $coordsTR['y']);

        // 2. TopLeft: x = 10, y = 10
        $coordsTL = $this->service->calculateStampCoordinates($pageW, $pageH, $boxW, $boxH, StampingPosition::TopLeft);
        $this->assertSame(10.0, $coordsTL['x']);
        $this->assertSame(10.0, $coordsTL['y']);

        // 3. BottomCenter: x = (210 - 84)/2 = 63, y = 297 - 24 - 15 = 258
        $coordsBC = $this->service->calculateStampCoordinates($pageW, $pageH, $boxW, $boxH, StampingPosition::BottomCenter);
        $this->assertSame(63.0, $coordsBC['x']);
        $this->assertSame(258.0, $coordsBC['y']);

        // 4. Footer (standar pojok kanan bawah: x = 210 - 84 - 7 = 119, y = 297 - 24 - 3.5 = 269.5)
        $coordsFooter = $this->service->calculateStampCoordinates($pageW, $pageH, $boxW, $boxH, StampingPosition::Footer);
        $this->assertSame(119.0, $coordsFooter['x']);
        $this->assertSame(269.5, $coordsFooter['y']);

        // 5. Clamping saat offset berlebihan
        $coordsClampedMin = $this->service->calculateStampCoordinates($pageW, $pageH, $boxW, $boxH, StampingPosition::TopLeft, -50, -50);
        $this->assertSame(0.0, $coordsClampedMin['x']);
        $this->assertSame(0.0, $coordsClampedMin['y']);

        $coordsClampedMax = $this->service->calculateStampCoordinates($pageW, $pageH, $boxW, $boxH, StampingPosition::TopRight, 100, 500);
        $this->assertSame(126.0, $coordsClampedMax['x']); // 210 - 84 = 126
        $this->assertSame(273.0, $coordsClampedMax['y']); // 297 - 24 = 273
    }

    public function test_it_places_18mm_barcode_accurately_on_portrait_and_landscape_pages(): void
    {
        $qrW = 18.0;
        $qrH = 18.0;

        // 1. A4 Portrait (210 x 297 mm)
        $pW = 210.0;
        $pH = 297.0;

        $tr = $this->service->calculateStampCoordinates($pW, $pH, $qrW, $qrH, StampingPosition::TopRight);
        $this->assertSame(182.0, $tr['x']); // 210 - 18 - 10
        $this->assertSame(8.0, $tr['y']);

        $tl = $this->service->calculateStampCoordinates($pW, $pH, $qrW, $qrH, StampingPosition::TopLeft);
        $this->assertSame(10.0, $tl['x']);
        $this->assertSame(10.0, $tl['y']);

        $bc = $this->service->calculateStampCoordinates($pW, $pH, $qrW, $qrH, StampingPosition::BottomCenter);
        $this->assertSame(96.0, $bc['x']); // (210 - 18) / 2
        $this->assertSame(264.0, $bc['y']); // 297 - 18 - 15

        $footer = $this->service->calculateStampCoordinates($pW, $pH, $qrW, $qrH, StampingPosition::Footer);
        $this->assertSame(185.0, $footer['x']); // 210 - 18 - 7
        $this->assertSame(275.5, $footer['y']); // 297 - 18 - 3.5

        // 2. A4 Landscape (297 x 210 mm)
        $lW = 297.0;
        $lH = 210.0;

        $trLandscape = $this->service->calculateStampCoordinates($lW, $lH, $qrW, $qrH, StampingPosition::TopRight);
        $this->assertSame(269.0, $trLandscape['x']); // 297 - 18 - 10
        $this->assertSame(8.0, $trLandscape['y']);

        $bcLandscape = $this->service->calculateStampCoordinates($lW, $lH, $qrW, $qrH, StampingPosition::BottomCenter);
        $this->assertSame(139.5, $bcLandscape['x']); // (297 - 18) / 2
        $this->assertSame(177.0, $bcLandscape['y']); // 210 - 18 - 15

        // 3. Offset kustom
        $trOffset = $this->service->calculateStampCoordinates($pW, $pH, $qrW, $qrH, StampingPosition::TopRight, -5, 10);
        $this->assertSame(177.0, $trOffset['x']); // 182 - 5
        $this->assertSame(18.0, $trOffset['y']); // 8 + 10
    }

    public function test_it_generates_valid_qr_code_png(): void
    {
        $qrPath = $this->service->generateQrCodePng('https://earsip.univbi.ac.id/verify/test-uuid');

        $this->assertFileExists($qrPath);
        $this->assertGreaterThan(0, filesize($qrPath));

        $imageInfo = getimagesize($qrPath);
        $this->assertNotFalse($imageInfo);
        $this->assertSame('image/png', $imageInfo['mime']);

        @unlink($qrPath);
    }

    public function test_it_stamps_pdf_document_successfully(): void
    {
        Storage::fake('private');

        $document = Document::create([
            'title' => 'SK Penetapan Kurikulum OBE',
            'unit_id' => $this->unit->id,
            'document_category_id' => $this->category->id,
            'academic_year_id' => $this->academicYear->id,
            'uploaded_by' => $this->user->id,
            'document_number' => '001/UNIVBI.TI/SK/XI/2026',
            'signer_name_snapshot' => 'Dr. Bambang Wijaya, M.Kom.',
            'signer_position_snapshot' => 'Rektor',
            'issued_date' => '2026-11-10',
            'visibility' => VisibilityLevel::Internal,
            'status' => DocumentStatus::Approved,
            'is_borang' => false,
            'has_expiry' => false,
        ]);

        // Buat berkas PDF asli menggunakan FPDF
        $fpdf = new FPDF;
        $fpdf->AddPage();
        $fpdf->SetFont('Arial', 'B', 14);
        $fpdf->Cell(0, 10, 'UNIVERSITAS BINA INSAN', 0, 1, 'C');
        $fpdf->SetFont('Arial', '', 10);
        $fpdf->Cell(0, 8, 'Naskah Asli Surat Keputusan Rektor', 0, 1, 'C');
        $originalPdfBinary = $fpdf->Output('S');

        $originalPath = 'documents/2026/11/TI/draft-2026-TI-SK-penetapan-kurikulum.pdf';
        Storage::disk('private')->put($originalPath, $originalPdfBinary);

        $file = DocumentFile::create([
            'document_id' => $document->id,
            'uploaded_by' => $this->user->id,
            'is_primary' => true,
            'version' => 1,
            'original_file_name' => 'draft.pdf',
            'standardized_file_name' => '2026-TI-SK-penetapan-kurikulum.pdf',
            'original_file_path' => $originalPath,
            'disk' => StorageDisk::Private,
            'mime_type' => 'application/pdf',
            'file_extension' => 'pdf',
            'file_size' => strlen($originalPdfBinary),
            'is_active' => true,
        ]);

        // Eksekusi Stamping
        $stampedFile = $this->service->stamp($document, $file);

        $this->assertNotNull($stampedFile);
        $this->assertNotNull($stampedFile->stamped_file_path);
        $this->assertSame('documents/2026/11/TI/stamped-2026-TI-SK-penetapan-kurikulum.pdf', $stampedFile->stamped_file_path);

        // Berkas asli harus tetap ada dan tidak berubah
        Storage::disk('private')->assertExists($originalPath);
        $this->assertSame($originalPdfBinary, Storage::disk('private')->get($originalPath));

        // Berkas hasil stempel harus ada dan berupa PDF yang valid
        Storage::disk('private')->assertExists($stampedFile->stamped_file_path);
        $stampedContent = Storage::disk('private')->get($stampedFile->stamped_file_path);
        $this->assertStringStartsWith('%PDF-', $stampedContent);
        $this->assertGreaterThan(strlen($originalPdfBinary), strlen($stampedContent));
    }

    public function test_it_does_not_create_extra_pages_when_stamping_footer_position_with_offsets(): void
    {
        Storage::fake('private');

        $categoryFooter = DocumentCategory::create([
            'code' => 'MOU',
            'name' => 'Nota Kesepahaman',
            'slug' => 'nota-kesepahaman',
            'stamp_position' => StampingPosition::Footer,
            'stamp_x_offset' => 20,
            'stamp_y_offset' => 20,
            'is_active' => true,
        ]);

        $document = Document::create([
            'title' => 'MoU Kerja Sama Industri',
            'unit_id' => $this->unit->id,
            'document_category_id' => $categoryFooter->id,
            'academic_year_id' => $this->academicYear->id,
            'uploaded_by' => $this->user->id,
            'document_number' => '003/UNIVBI.TI/MOU/XI/2026',
            'issued_date' => '2026-11-10',
            'status' => DocumentStatus::Approved,
        ]);

        // Buat PDF 1 halaman
        $fpdf = new FPDF;
        $fpdf->AddPage();
        $fpdf->SetFont('Arial', 'B', 14);
        $fpdf->Cell(0, 10, 'MoU Kerja Sama Industri 1 Halaman', 0, 1, 'C');
        $pdfBinary = $fpdf->Output('S');

        $originalPath = 'documents/2026/11/TI/draft-mou.pdf';
        Storage::disk('private')->put($originalPath, $pdfBinary);

        $file = DocumentFile::create([
            'document_id' => $document->id,
            'uploaded_by' => $this->user->id,
            'is_primary' => true,
            'original_file_name' => 'mou.pdf',
            'standardized_file_name' => '2026-TI-MOU-mou.pdf',
            'original_file_path' => $originalPath,
            'disk' => StorageDisk::Private,
            'mime_type' => 'application/pdf',
            'file_extension' => 'pdf',
            'file_size' => strlen($pdfBinary),
            'is_active' => true,
        ]);

        $stampedFile = $this->service->stamp($document, $file);

        $this->assertNotNull($stampedFile);
        $this->assertNotNull($stampedFile->stamped_file_path);

        $tempStamped = tempnam(sys_get_temp_dir(), 'test_verify_');
        file_put_contents($tempStamped, Storage::disk('private')->get($stampedFile->stamped_file_path));

        $verifyFpdi = new Fpdi;
        $pageCount = $verifyFpdi->setSourceFile($tempStamped);
        @unlink($tempStamped);

        // Harus tetap persis 1 halaman tanpa offset overflow atau halaman kosong ekstra
        $this->assertSame(1, $pageCount);
    }

    public function test_it_always_stamps_all_pdf_pages_with_number_date_and_qr_at_bottom_right_footer(): void
    {
        Storage::fake('private');

        $categoryNone = DocumentCategory::create([
            'code' => 'SCAN',
            'name' => 'Scan Berstempel Basah',
            'slug' => 'scan-berstempel-basah',
            'stamp_position' => StampingPosition::None,
            'is_active' => true,
        ]);

        $document = Document::create([
            'title' => 'Scan Piagam Kerjasama',
            'unit_id' => $this->unit->id,
            'document_category_id' => $categoryNone->id,
            'academic_year_id' => $this->academicYear->id,
            'uploaded_by' => $this->user->id,
            'document_number' => '002/UNIVBI.TI/SCAN/XI/2026',
            'issued_date' => '2026-11-10',
            'visibility' => VisibilityLevel::Internal,
            'status' => DocumentStatus::Approved,
        ]);

        // Buat PDF 2 halaman
        $fpdf = new FPDF;
        $fpdf->AddPage();
        $fpdf->SetFont('Arial', 'B', 14);
        $fpdf->Cell(0, 10, 'Halaman 1 Dokumen Multi-Halaman', 0, 1, 'C');
        $fpdf->AddPage();
        $fpdf->Cell(0, 10, 'Halaman 2 Dokumen Multi-Halaman', 0, 1, 'C');
        $originalPdfBinary = $fpdf->Output('S');

        $originalPath = 'documents/2026/11/TI/scan-piagam.pdf';
        Storage::disk('private')->put($originalPath, $originalPdfBinary);

        $file = DocumentFile::create([
            'document_id' => $document->id,
            'uploaded_by' => $this->user->id,
            'is_primary' => true,
            'original_file_name' => 'scan.pdf',
            'standardized_file_name' => '2026-TI-SCAN-piagam.pdf',
            'original_file_path' => $originalPath,
            'disk' => StorageDisk::Private,
            'mime_type' => 'application/pdf',
            'file_extension' => 'pdf',
            'file_size' => strlen($originalPdfBinary),
            'is_active' => true,
        ]);

        $result = $this->service->stamp($document, $file);

        $this->assertNotNull($result);
        $this->assertNotNull($result->stamped_file_path);
        Storage::disk('private')->assertExists($result->stamped_file_path);

        $stampedContent = (string) Storage::disk('private')->get($result->stamped_file_path);
        $this->assertStringStartsWith('%PDF-', $stampedContent);
        $this->assertGreaterThan(strlen($originalPdfBinary), strlen($stampedContent));

        $tempStamped = tempnam(sys_get_temp_dir(), 'test_multi_');
        file_put_contents($tempStamped, $stampedContent);
        $verifyFpdi = new Fpdi;
        $pageCount = $verifyFpdi->setSourceFile($tempStamped);
        @unlink($tempStamped);
        $this->assertSame(2, $pageCount);

        // Dekompresi stream PDF dan pastikan Nomor Surat & Tanggal Sah tersemat pada kedua halaman
        $inflatedStreams = [];
        if (preg_match_all('/stream\r?\n(.*?)\r?\nendstream/s', $stampedContent, $streamMatches)) {
            foreach ($streamMatches[1] as $rawStream) {
                $decoded = @gzuncompress($rawStream);
                $inflatedStreams[] = $decoded !== false ? $decoded : $rawStream;
            }
        }
        $allDecoded = implode("\n", $inflatedStreams);
        $this->assertGreaterThanOrEqual(2, substr_count($allDecoded, 'No. 002/UNIVBI.TI/SCAN/XI/2026'));
        $this->assertGreaterThanOrEqual(2, substr_count($allDecoded, 'Disahkan:'));
    }

    public function test_it_stamps_docx_with_placeholders(): void
    {
        Storage::fake('private');

        $document = Document::create([
            'title' => 'Format Berita Acara Ujian',
            'unit_id' => $this->unit->id,
            'document_category_id' => $this->category->id,
            'academic_year_id' => $this->academicYear->id,
            'uploaded_by' => $this->user->id,
            'document_number' => '003/UNIVBI.TI/SK/XI/2026',
            'signer_name_snapshot' => 'Dr. Bambang Wijaya, M.Kom.',
            'signer_position_snapshot' => 'Rektor',
            'issued_date' => '2026-11-10',
            'visibility' => VisibilityLevel::Internal,
            'status' => DocumentStatus::Approved,
        ]);

        // Buat file docx dengan variabel template
        $phpWord = new PhpWord;
        $section = $phpWord->addSection();
        $section->addText('Surat Keputusan Resmi');
        $section->addText('Nomor: ${NOMOR_SURAT}');
        $section->addText('Tanggal: ${TANGGAL_SAH}');
        $section->addText('Pejabat: ${PEJABAT}');

        $tempDocx = tempnam(sys_get_temp_dir(), 'docx_test_').'.docx';
        $writer = IOFactory::createWriter($phpWord, 'Word2007');
        $writer->save($tempDocx);

        $originalPath = 'documents/2026/11/TI/draft-berita-acara.docx';
        Storage::disk('private')->put($originalPath, (string) file_get_contents($tempDocx));
        @unlink($tempDocx);

        $file = DocumentFile::create([
            'document_id' => $document->id,
            'uploaded_by' => $this->user->id,
            'is_primary' => true,
            'original_file_name' => 'template.docx',
            'standardized_file_name' => '2026-TI-SK-format-berita-acara.docx',
            'original_file_path' => $originalPath,
            'disk' => StorageDisk::Private,
            'mime_type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'file_extension' => 'docx',
            'file_size' => Storage::disk('private')->size($originalPath),
            'is_active' => true,
        ]);

        $stampedFile = $this->service->stamp($document, $file);

        $this->assertNotNull($stampedFile);
        $this->assertNotNull($stampedFile->stamped_file_path);
        $this->assertSame('documents/2026/11/TI/stamped-2026-TI-SK-format-berita-acara.docx', $stampedFile->stamped_file_path);
        Storage::disk('private')->assertExists($stampedFile->stamped_file_path);

        $tempCheck = tempnam(sys_get_temp_dir(), 'docx_check_tpl_').'.docx';
        file_put_contents($tempCheck, Storage::disk('private')->get($stampedFile->stamped_file_path));

        $zip = new \ZipArchive;
        $this->assertTrue($zip->open($tempCheck));
        $documentXml = $zip->getFromName('word/document.xml');
        $footerXml = $zip->getFromName('word/footer1.xml');
        $zip->close();
        @unlink($tempCheck);

        $this->assertStringContainsString('003/UNIVBI.TI/SK/XI/2026', $documentXml);
        $this->assertIsString($footerXml);
        $this->assertStringContainsString('rId', $footerXml);
    }

    public function test_it_stamps_plain_docx_document_with_footer_table(): void
    {
        Storage::fake('private');

        $document = Document::create([
            'title' => 'Naskah Tugas Akhir Mahasiswa',
            'unit_id' => $this->unit->id,
            'document_category_id' => $this->category->id,
            'academic_year_id' => $this->academicYear->id,
            'uploaded_by' => $this->user->id,
            'document_number' => '004/UNIVBI.TI/SK/XI/2026',
            'issued_date' => '2026-11-10',
            'visibility' => VisibilityLevel::Internal,
            'status' => DocumentStatus::Approved,
        ]);

        // Buat file docx polos tanpa variabel
        $phpWord = new PhpWord;
        $section = $phpWord->addSection();
        $section->addText('Isi Naskah Polos Tanpa Placeholder');

        $tempDocx = tempnam(sys_get_temp_dir(), 'docx_plain_').'.docx';
        $writer = IOFactory::createWriter($phpWord, 'Word2007');
        $writer->save($tempDocx);

        $originalPath = 'documents/2026/11/TI/naskah-polos.docx';
        Storage::disk('private')->put($originalPath, (string) file_get_contents($tempDocx));
        @unlink($tempDocx);

        $file = DocumentFile::create([
            'document_id' => $document->id,
            'uploaded_by' => $this->user->id,
            'is_primary' => true,
            'original_file_name' => 'plain.docx',
            'standardized_file_name' => '2026-TI-SK-naskah-polos.docx',
            'original_file_path' => $originalPath,
            'disk' => StorageDisk::Private,
            'mime_type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'file_extension' => 'docx',
            'file_size' => Storage::disk('private')->size($originalPath),
            'is_active' => true,
        ]);

        $stampedFile = $this->service->stamp($document, $file);

        $this->assertNotNull($stampedFile);
        $this->assertNotNull($stampedFile->stamped_file_path);
        Storage::disk('private')->assertExists($stampedFile->stamped_file_path);
    }

    public function test_it_throws_exception_when_original_file_is_missing(): void
    {
        Storage::fake('private');

        $document = Document::create([
            'title' => 'Dokumen File Hilang',
            'unit_id' => $this->unit->id,
            'document_category_id' => $this->category->id,
            'academic_year_id' => $this->academicYear->id,
            'uploaded_by' => $this->user->id,
            'issued_date' => '2026-11-10',
            'status' => DocumentStatus::Approved,
        ]);

        $file = DocumentFile::create([
            'document_id' => $document->id,
            'uploaded_by' => $this->user->id,
            'is_primary' => true,
            'original_file_name' => 'missing.pdf',
            'standardized_file_name' => '2026-TI-SK-missing.pdf',
            'original_file_path' => 'documents/2026/11/TI/missing.pdf',
            'disk' => StorageDisk::Private,
            'mime_type' => 'application/pdf',
            'file_extension' => 'pdf',
            'file_size' => 10,
            'is_active' => true,
        ]);

        $this->expectException(DocumentStampingException::class);
        $this->service->stampPdf($document, $file);
    }

    public function test_it_replaces_split_and_intact_tokens_in_docx_and_always_attaches_footer_qr(): void
    {
        Storage::fake('private');

        $categoryNone = DocumentCategory::create([
            'code' => 'SK',
            'name' => 'Surat Keputusan Standar',
            'slug' => 'surat-keputusan-standar',
            'stamp_position' => StampingPosition::None,
            'number_format' => '{seq}/UNIVBI.{unit}/{cat}/{roman_month}/{year}',
            'is_active' => true,
        ]);

        $document = Document::create([
            'title' => 'SK Yudisium Sarjana',
            'unit_id' => $this->unit->id,
            'document_category_id' => $categoryNone->id,
            'academic_year_id' => $this->academicYear->id,
            'uploaded_by' => $this->user->id,
            'document_number' => '008/UNIVBI.TI/SK/XI/2026',
            'issued_date' => '2026-11-10',
            'visibility' => VisibilityLevel::Internal,
            'status' => DocumentStatus::Approved,
        ]);

        // Buat file docx dengan teks placeholder pola dan tag variabel
        $phpWord = new PhpWord;
        $section = $phpWord->addSection();
        $section->addText('Nomor : {seq}/UNIVBI.{unit}/{cat}/{roman_month}/{year}');
        $section->addText('Ref: {nomor_surat}');
        $section->addText('Tag: ${NOMOR_SURAT}');
        $section->addText('Jakarta, {tanggal_sah}');

        $tempDocx = tempnam(sys_get_temp_dir(), 'docx_none_').'.docx';
        $writer = IOFactory::createWriter($phpWord, 'Word2007');
        $writer->save($tempDocx);

        // Simulasikan perilaku Microsoft Word yang memecah tag {tanggal_sah} ke beberapa <w:r> dan <w:proofErr/>
        $splitZip = new \ZipArchive;
        if ($splitZip->open($tempDocx) === true) {
            $rawXml = $splitZip->getFromName('word/document.xml');
            if ($rawXml) {
                $fragmentedXml = str_replace(
                    '{tanggal_sah}',
                    '</w:t></w:r><w:r><w:t>{</w:t></w:r><w:proofErr w:type="spellStart"/><w:r><w:t>tanggal_sah</w:t></w:r><w:proofErr w:type="spellEnd"/><w:r><w:t>}</w:t></w:r><w:r><w:t>',
                    $rawXml
                );
                $splitZip->addFromString('word/document.xml', $fragmentedXml);
            }
            $splitZip->close();
        }

        $originalPath = 'documents/2026/11/TI/draft-sk-tanpa-stempel.docx';
        Storage::disk('private')->put($originalPath, (string) file_get_contents($tempDocx));
        @unlink($tempDocx);

        $file = DocumentFile::create([
            'document_id' => $document->id,
            'uploaded_by' => $this->user->id,
            'is_primary' => true,
            'original_file_name' => 'sk.docx',
            'standardized_file_name' => '2026-TI-SK-sk.docx',
            'original_file_path' => $originalPath,
            'disk' => StorageDisk::Private,
            'mime_type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'file_extension' => 'docx',
            'file_size' => Storage::disk('private')->size($originalPath),
            'is_active' => true,
        ]);

        $stampedFile = $this->service->stamp($document, $file);

        $this->assertNotNull($stampedFile);
        $this->assertNotNull($stampedFile->stamped_file_path);
        Storage::disk('private')->assertExists($stampedFile->stamped_file_path);

        // Verifikasi isi XML Word telah diganti nomor & tanggal sah serta QR disematkan di footer
        $savedContent = Storage::disk('private')->get($stampedFile->stamped_file_path);
        $tempCheck = tempnam(sys_get_temp_dir(), 'docx_check_').'.docx';
        file_put_contents($tempCheck, $savedContent);

        $zip = new \ZipArchive;
        $this->assertTrue($zip->open($tempCheck));
        $documentXml = $zip->getFromName('word/document.xml');
        $footerXml = $zip->getFromName('word/footer1.xml');
        $footerRelsXml = $zip->getFromName('word/_rels/footer1.xml.rels');
        $docRelsXml = $zip->getFromName('word/_rels/document.xml.rels');
        $contentTypesXml = $zip->getFromName('[Content_Types].xml');
        $zip->close();
        @unlink($tempCheck);

        // Pastikan seluruh XML hasil injeksi valid (well-formed)
        foreach ([$documentXml, $footerXml, $footerRelsXml, $docRelsXml, $contentTypesXml] as $xmlString) {
            $this->assertIsString($xmlString);
            $dom = new \DOMDocument;
            $this->assertTrue($dom->loadXML($xmlString));
        }

        // Nomor surat resmi & tanggal sah harus muncul di dalam XML Word
        $this->assertStringContainsString('008/UNIVBI.TI/SK/XI/2026', $documentXml);
        $this->assertStringNotContainsString('tanggal_sah', $documentXml);
        // Teks pola dan placeholder lama harus bersih tergantikan
        $this->assertStringNotContainsString('{seq}/UNIVBI.{unit}/{cat}/{roman_month}/{year}', $documentXml);
        $this->assertStringNotContainsString('{nomor_surat}', $documentXml);
        $this->assertStringNotContainsString('${NOMOR_SURAT}', $documentXml);
        // QR Code footer harus selalu tersemat di word/footer1.xml menggunakan DrawingML anchor tepat di pojok kanan bawah halaman
        $this->assertStringContainsString('r:embed="rId', $footerXml);
        $this->assertStringContainsString('relativeFrom="page"', $footerXml);
        $this->assertStringContainsString('<wp:extent cx="660400" cy="660400"/>', $footerXml);
    }

    public function test_verification_and_assessor_urls_strictly_respect_app_url_config(): void
    {
        config(['app.url' => 'https://earsip.univbi.ac.id']);

        $document = Document::create([
            'uuid' => '12345678-1234-1234-1234-123456789abc',
            'title' => 'SK Uji Base URL',
            'document_number' => '099/UNIVBI.TI/SK/XI/2026',
            'unit_id' => $this->unit->id,
            'document_category_id' => $this->category->id,
            'uploaded_by' => $this->user->id,
            'issued_date' => now()->toDateString(),
            'status' => DocumentStatus::Approved,
        ]);

        $this->assertSame(
            'https://earsip.univbi.ac.id/verify/12345678-1234-1234-1234-123456789abc',
            $document->getVerificationUrl()
        );

        $this->assertSame(
            'https://earsip.univbi.ac.id/bukti-borang/12345678-1234-1234-1234-123456789abc',
            $document->getAssessorUrl()
        );

        $this->assertSame(
            'https://earsip.univbi.ac.id/bukti-borang/unit/TI',
            $this->unit->getAssessorPortalUrl()
        );

        // Uji jika APP_URL memiliki trailing slash di .env
        config(['app.url' => 'https://earsip.univbi.ac.id/']);

        $this->assertSame(
            'https://earsip.univbi.ac.id/verify/12345678-1234-1234-1234-123456789abc',
            $document->getVerificationUrl()
        );
    }

    public function test_it_computes_exact_stamped_file_sha256_on_final_docx_fallback(): void
    {
        Storage::fake('private');

        $document = Document::create([
            'title' => 'Surat Edaran Hash Fallback',
            'unit_id' => $this->unit->id,
            'document_category_id' => $this->category->id,
            'academic_year_id' => $this->academicYear->id,
            'uploaded_by' => $this->user->id,
            'document_number' => '010/UNIVBI.TI/SK/XI/2026',
            'issued_date' => '2026-11-17',
            'visibility' => VisibilityLevel::Public,
            'status' => DocumentStatus::Approved,
        ]);

        $phpWord = new PhpWord;
        $section = $phpWord->addSection();
        $section->addText('Nomor: ${NOMOR_SURAT}');
        $tempDocx = tempnam(sys_get_temp_dir(), 'docx_hash_').'.docx';
        IOFactory::createWriter($phpWord, 'Word2007')->save($tempDocx);

        $originalPath = 'documents/2026/11/TI/draft-hash.docx';
        $originalBytes = (string) file_get_contents($tempDocx);
        $originalHash = hash('sha256', $originalBytes);
        Storage::disk('private')->put($originalPath, $originalBytes);
        @unlink($tempDocx);

        $file = DocumentFile::create([
            'document_id' => $document->id,
            'uploaded_by' => $this->user->id,
            'is_primary' => true,
            'original_file_name' => 'draft-hash.docx',
            'standardized_file_name' => '2026-TI-SK-hash-fallback.docx',
            'original_file_path' => $originalPath,
            'disk' => StorageDisk::Private,
            'mime_type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'file_extension' => 'docx',
            'file_size' => strlen($originalBytes),
            'file_hash_sha256' => $originalHash,
            'is_active' => true,
        ]);

        $stampedFile = $this->service->stamp($document, $file);

        $this->assertNotNull($stampedFile);
        $this->assertSame($originalHash, $stampedFile->file_hash_sha256);
        $this->assertNotNull($stampedFile->stamped_file_hash_sha256);
        $this->assertNotSame($originalHash, $stampedFile->stamped_file_hash_sha256);

        $actualFinalBytes = (string) Storage::disk('private')->get($stampedFile->stamped_file_path);
        $this->assertSame(hash('sha256', $actualFinalBytes), $stampedFile->stamped_file_hash_sha256);
        $this->assertSame($stampedFile->stamped_file_hash_sha256, $stampedFile->getEffectiveSha256Hash());
        $this->assertSame('2026-TI-SK-hash-fallback.docx', $stampedFile->getEffectiveDownloadFileName());
    }

    public function test_it_converts_injected_docx_to_stamped_pdf_and_computes_final_sha256_when_libreoffice_succeeds(): void
    {
        Storage::fake('private');

        $document = Document::create([
            'title' => 'SK Rektor Konversi PDF Final',
            'unit_id' => $this->unit->id,
            'document_category_id' => $this->category->id,
            'academic_year_id' => $this->academicYear->id,
            'uploaded_by' => $this->user->id,
            'document_number' => '011/UNIVBI.TI/SK/XI/2026',
            'issued_date' => '2026-11-17',
            'visibility' => VisibilityLevel::Public,
            'status' => DocumentStatus::Approved,
        ]);

        $phpWord = new PhpWord;
        $section = $phpWord->addSection();
        $section->addText('Nomor Resmi: ${NOMOR_SURAT}');
        $tempDocx = tempnam(sys_get_temp_dir(), 'docx_pdf_').'.docx';
        IOFactory::createWriter($phpWord, 'Word2007')->save($tempDocx);

        $originalPath = 'documents/2026/11/TI/draft-konversi.docx';
        $originalBytes = (string) file_get_contents($tempDocx);
        Storage::disk('private')->put($originalPath, $originalBytes);
        @unlink($tempDocx);

        $file = DocumentFile::create([
            'document_id' => $document->id,
            'uploaded_by' => $this->user->id,
            'is_primary' => true,
            'original_file_name' => 'draft-konversi.docx',
            'standardized_file_name' => '2026-TI-SK-konversi-final.docx',
            'original_file_path' => $originalPath,
            'disk' => StorageDisk::Private,
            'mime_type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'file_extension' => 'docx',
            'file_size' => strlen($originalBytes),
            'file_hash_sha256' => hash('sha256', $originalBytes),
            'is_active' => true,
        ]);

        // Simulasikan converter LibreOffice yang menghasilkan PDF dari DOCX yang sudah di-inject nomor suratnya
        $injectedDocxChecked = false;
        $serviceWithConverter = new class(new DocumentNamingService, $injectedDocxChecked) extends DocumentStampingService
        {
            public function __construct(DocumentNamingService $namingService, private bool &$checkedRef)
            {
                parent::__construct($namingService);
            }

            protected function convertDocxToPdf(string $localDocxPath, string $outputDir): ?string
            {
                $zip = new \ZipArchive;
                if ($zip->open($localDocxPath) === true) {
                    $xml = $zip->getFromName('word/document.xml') ?: '';
                    $zip->close();
                    $this->checkedRef = str_contains($xml, '011/UNIVBI.TI/SK/XI/2026');
                }

                if (! is_dir($outputDir)) {
                    @mkdir($outputDir, 0755, true);
                }

                $pdf = new FPDF;
                $pdf->AddPage();
                $pdf->SetFont('Arial', '', 12);
                $pdf->Cell(0, 10, 'Rendered from DOCX');

                $outPdf = $outputDir.DIRECTORY_SEPARATOR.'converted.pdf';
                $pdf->Output('F', $outPdf);

                return $outPdf;
            }
        };

        $stampedFile = $serviceWithConverter->stamp($document, $file);

        $this->assertTrue($injectedDocxChecked, 'Nomor surat harus sudah di-inject ke .docx sebelum konversi ke PDF.');
        $this->assertNotNull($stampedFile);
        $this->assertSame('documents/2026/11/TI/stamped-2026-TI-SK-konversi-final.pdf', $stampedFile->stamped_file_path);
        $this->assertSame('2026-TI-SK-konversi-final.pdf', $stampedFile->getEffectiveDownloadFileName());
        Storage::disk('private')->assertExists($stampedFile->stamped_file_path);

        $finalPdfBytes = (string) Storage::disk('private')->get($stampedFile->stamped_file_path);
        $this->assertStringStartsWith('%PDF-', $finalPdfBytes);
        $this->assertSame(hash('sha256', $finalPdfBytes), $stampedFile->stamped_file_hash_sha256);
        $this->assertSame($stampedFile->stamped_file_hash_sha256, $stampedFile->getEffectiveSha256Hash());
    }
}
