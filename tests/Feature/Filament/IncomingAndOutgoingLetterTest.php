<?php

declare(strict_types=1);

namespace Tests\Feature\Filament;

use App\Enums\IncomingLetterStatus;
use App\Enums\IncomingRecipientStatus;
use App\Enums\LetterPriority;
use App\Enums\OutgoingLetterStatus;
use App\Filament\Resources\IncomingLetters\IncomingLetterResource;
use App\Filament\Resources\IncomingLetters\Pages\ListIncomingLetters;
use App\Filament\Resources\IncomingLetters\Pages\ViewIncomingLetter;
use App\Filament\Resources\OutgoingLetters\Pages\ListOutgoingLetters;
use App\Filament\Resources\OutgoingLetters\Pages\ViewOutgoingLetter;
use App\Filament\Widgets\PendingLettersAlertWidget;
use App\Models\IncomingLetter;
use App\Models\IncomingLetterRecipient;
use App\Models\OutgoingLetter;
use App\Models\Unit;
use App\Models\User;
use App\Services\LetterManagementService;
use Filament\Tables\Enums\PaginationMode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use setasign\Fpdi\Fpdi;
use Tests\Support\LegacyMasterDataSeeder;
use Tests\TestCase;

class IncomingAndOutgoingLetterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('private');
        $this->seed(LegacyMasterDataSeeder::class);
    }

    protected function makeValidPdfContent(string $text = 'Surat Eksternal Resmi', int $pages = 1): string
    {
        $pdf = new Fpdi;
        for ($i = 1; $i <= $pages; $i++) {
            $pdf->AddPage('P', [210, 297]);
            $pdf->SetFont('Helvetica', '', 12);
            $pdf->Cell(0, 10, "{$text} (Hal {$i})");
        }

        return $pdf->Output('S');
    }

    protected function extractPdfDecodedStreams(string $pdfBinary): string
    {
        $inflatedStreams = [];
        if (preg_match_all('/stream\r?\n(.*?)\r?\nendstream/s', $pdfBinary, $streamMatches)) {
            foreach ($streamMatches[1] as $rawStream) {
                $decoded = @gzuncompress($rawStream);
                $inflatedStreams[] = $decoded !== false ? $decoded : $rawStream;
            }
        }

        return implode("\n", $inflatedStreams);
    }

    public function test_incoming_letter_per_unit_numbering_and_bottom_right_footer_pdf_stamp(): void
    {
        $humas = User::where('email', 'humas@univbi.ac.id')->firstOrFail();
        $rektor = User::where('email', 'rektor@univbi.ac.id')->firstOrFail();

        $unitRektorat = Unit::where('code', 'REKTORAT')->firstOrFail();
        $unitTi = Unit::where('code', 'TI')->firstOrFail();

        Storage::disk('private')->put('incoming-letters/surat-lldikti.pdf', $this->makeValidPdfContent('Surat LLDIKTI', 2));
        Storage::disk('private')->put('incoming-letters/surat-industri.pdf', $this->makeValidPdfContent('Surat Industri'));

        $letterRektorat = IncomingLetter::create([
            'unit_id' => $unitRektorat->id,
            'registered_by' => $humas->id,
            'sender_name' => 'LLDIKTI Wilayah II',
            'external_letter_number' => '100/LL2/2026',
            'received_date' => '2026-11-14',
            'subject' => 'Undangan Koordinasi Pimpinan',
            'priority' => LetterPriority::Penting,
            'original_file_path' => 'incoming-letters/surat-lldikti.pdf',
            'file_name' => 'surat-lldikti.pdf',
        ]);

        IncomingLetterRecipient::create([
            'incoming_letter_id' => $letterRektorat->id,
            'step_order' => 1,
            'recipient_user_id' => $rektor->id,
        ]);

        $service = app(LetterManagementService::class);
        $service->initializeIncomingDistribution($letterRektorat);
        $letterRektorat->refresh();

        $this->assertSame('001/UNIVBI.REKTORAT/SM/XI/2026', $letterRektorat->agenda_number);
        $this->assertNotNull($letterRektorat->stamped_file_path);
        Storage::disk('private')->assertExists($letterRektorat->stamped_file_path);

        $decodedPdf = $this->extractPdfDecodedStreams((string) Storage::disk('private')->get($letterRektorat->stamped_file_path));
        $this->assertSame(2, substr_count($decodedPdf, 'Agenda: 001/UNIVBI.REKTORAT/SM/XI/2026'));
        $this->assertSame(2, substr_count($decodedPdf, 'Diterima: 14 November 2026 | e-Arsip UNIVBI'));

        // Pastikan penomoran untuk unit berbeda (TI) dimulai dari 001 (terpisah per unit, anti-ribuan)
        $letterTi = IncomingLetter::create([
            'unit_id' => $unitTi->id,
            'registered_by' => $humas->id,
            'sender_name' => 'PT Telkom Indonesia',
            'received_date' => '2026-11-14',
            'subject' => 'Penawaran Magang Mahasiswa TI',
            'priority' => LetterPriority::Biasa,
            'original_file_path' => 'incoming-letters/surat-industri.pdf',
            'file_name' => 'surat-industri.pdf',
        ]);

        IncomingLetterRecipient::create([
            'incoming_letter_id' => $letterTi->id,
            'step_order' => 1,
            'recipient_user_id' => $rektor->id,
        ]);

        $service->initializeIncomingDistribution($letterTi);
        $letterTi->refresh();

        $this->assertSame('001/UNIVBI.TI/SM/XI/2026', $letterTi->agenda_number);
    }

    public function test_incoming_letter_sequential_relay_and_read_tracking(): void
    {
        $humas = User::where('email', 'humas@univbi.ac.id')->firstOrFail();
        $warek = User::where('email', 'warek1@univbi.ac.id')->firstOrFail();
        $rektor = User::where('email', 'rektor@univbi.ac.id')->firstOrFail();
        $unitRektorat = Unit::where('code', 'REKTORAT')->firstOrFail();

        Storage::disk('private')->put('incoming-letters/surat-kementerian.pdf', $this->makeValidPdfContent());

        $letter = IncomingLetter::create([
            'unit_id' => $unitRektorat->id,
            'registered_by' => $humas->id,
            'sender_name' => 'Kementerian Pendidikan Tinggi',
            'received_date' => '2026-11-14',
            'subject' => 'Pemberitahuan Hibah Riset Kampus',
            'summary' => 'Surat pemberitahuan kuota hibah riset tahun anggaran baru.',
            'priority' => LetterPriority::Segera,
            'original_file_path' => 'incoming-letters/surat-kementerian.pdf',
            'file_name' => 'surat-kementerian.pdf',
        ]);

        // Alur berjenjang: 1. Warek -> 2. Rektor
        IncomingLetterRecipient::create([
            'incoming_letter_id' => $letter->id,
            'step_order' => 1,
            'recipient_user_id' => $warek->id,
            'instruction_note' => 'Mohon telaah awal Bapak Warek I',
        ]);

        IncomingLetterRecipient::create([
            'incoming_letter_id' => $letter->id,
            'step_order' => 2,
            'recipient_user_id' => $rektor->id,
            'instruction_note' => 'Untuk arahan akhir Bapak Rektor',
        ]);

        $service = app(LetterManagementService::class);
        $service->initializeIncomingDistribution($letter);
        $letter->refresh();

        $this->assertSame(1, $letter->current_step);
        $this->assertSame(2, $letter->total_steps);
        $this->assertSame(IncomingLetterStatus::InProgress, $letter->status);

        // Tahap 1: Warek sudah bisa mengakses dan menandai dibaca; Rektor belum karena masih di tahap 1
        $this->assertTrue($letter->canUserAccess($warek));
        $this->assertTrue($letter->canUserMarkAsRead($warek));
        $this->assertFalse($letter->canUserAccess($rektor));
        $this->assertFalse($letter->canUserMarkAsRead($rektor));

        // Warek membuka halaman detail -> opened_at tercatat
        $service->recordOpened($letter, $warek);
        $warekRecipient = $letter->recipients()->where('step_order', 1)->firstOrFail();
        $this->assertNotNull($warekRecipient->opened_at);
        $this->assertNull($warekRecipient->read_at);

        // Warek klik "Tandai Sudah Dibaca" dengan catatan pengantar untuk Rektor
        $service->markAsRead($letter->fresh(), $warek, 'Sudah ditelaah, mohon persetujuan Bapak Rektor.');
        $letter->refresh();

        $warekRecipient->refresh();
        $rektorRecipient = $letter->recipients()->where('step_order', 2)->firstOrFail();

        $this->assertSame(IncomingRecipientStatus::Read, $warekRecipient->status);
        $this->assertNotNull($warekRecipient->read_at);
        $this->assertSame('Sudah ditelaah, mohon persetujuan Bapak Rektor.', $warekRecipient->disposition_note);

        // Sekarang current_step naik ke 2 (Rektor)
        $this->assertSame(2, $letter->current_step);
        $this->assertSame(IncomingLetterStatus::InProgress, $letter->status);
        $this->assertSame(IncomingRecipientStatus::Unread, $rektorRecipient->status);
        $this->assertTrue($letter->canUserAccess($rektor));
        $this->assertTrue($letter->canUserMarkAsRead($rektor));

        // Rektor klik "Tandai Sudah Dibaca"
        $service->markAsRead($letter->fresh(), $rektor, 'Terima kasih, segera tindak lanjuti.');
        $letter->refresh();
        $rektorRecipient->refresh();

        $this->assertSame(IncomingRecipientStatus::Read, $rektorRecipient->status);
        $this->assertNotNull($rektorRecipient->read_at);
        $this->assertSame(IncomingLetterStatus::Completed, $letter->status);
        $this->assertSame('Selesai Dibaca Semua Penerima', $letter->getTrackingStepLabel());
    }

    public function test_outgoing_letter_review_and_numbering_by_humas(): void
    {
        $stafTi = User::where('email', 'staf.ti@univbi.ac.id')->firstOrFail();
        $humas = User::where('email', 'humas@univbi.ac.id')->firstOrFail();
        $unitTi = Unit::where('code', 'TI')->firstOrFail();
        $unitHumas = Unit::where('code', 'HUMAS')->firstOrFail();

        Storage::disk('private')->put('outgoing-letters/draf-surat-keluar-ti.pdf', $this->makeValidPdfContent('Surat Keluar Prodi TI'));
        Storage::disk('private')->put('outgoing-letters/surat-humas.pdf', $this->makeValidPdfContent('Surat Keluar Humas'));

        $service = app(LetterManagementService::class);

        // 1. Unit selain Humas membuat surat keluar -> status Menunggu Tinjauan Humas
        $outgoingTi = OutgoingLetter::create([
            'unit_id' => $unitTi->id,
            'created_by' => $stafTi->id,
            'destination_organization' => 'PT Garuda Cyber Indonesia',
            'subject' => 'Permohonan Kunjungan Industri Mahasiswa TI',
            'description' => 'Kunjungan industri semester ganjil.',
            'letter_date' => '2026-11-14',
            'original_file_path' => 'outgoing-letters/draf-surat-keluar-ti.pdf',
            'file_name' => 'draf-surat-keluar-ti.pdf',
        ]);

        $service->processNewOutgoingLetter($outgoingTi, $stafTi);
        $outgoingTi->refresh();

        $this->assertSame(OutgoingLetterStatus::PendingHumas, $outgoingTi->status);
        $this->assertNull($outgoingTi->letter_number);
        $this->assertTrue($outgoingTi->canBeReviewedByHumas($humas));

        // 2. Humas meninjau dan klik "Terbitkan Nomor & Sahkan"
        $service->approveOutgoingLetter($outgoingTi, $humas, 'Nomor surat resmi diterbitkan.');
        $outgoingTi->refresh();

        $this->assertSame(OutgoingLetterStatus::Published, $outgoingTi->status);
        $this->assertSame('001/UNIVBI.TI/SKL/XI/2026', $outgoingTi->letter_number);
        $this->assertNotNull($outgoingTi->stamped_file_path);
        Storage::disk('private')->assertExists($outgoingTi->stamped_file_path);

        $decodedOutgoingPdf = $this->extractPdfDecodedStreams((string) Storage::disk('private')->get($outgoingTi->stamped_file_path));
        $this->assertStringContainsString('No. 001/UNIVBI.TI/SKL/XI/2026', $decodedOutgoingPdf);
        $this->assertStringContainsString('Tanggal: 14 November 2026 | e-Arsip UNIVBI', $decodedOutgoingPdf);

        // 3. Humas membuat surat keluar langsung -> otomatis langsung terbit nomor & berstempel
        $outgoingHumas = OutgoingLetter::create([
            'unit_id' => $unitHumas->id,
            'created_by' => $humas->id,
            'destination_organization' => 'Media Partner Sumatera Selatan',
            'subject' => 'Undangan Liputan Wisuda Universitas Bina Insan',
            'letter_date' => '2026-11-14',
            'original_file_path' => 'outgoing-letters/surat-humas.pdf',
            'file_name' => 'surat-humas.pdf',
        ]);

        $service->processNewOutgoingLetter($outgoingHumas, $humas);
        $outgoingHumas->refresh();

        $this->assertSame(OutgoingLetterStatus::Published, $outgoingHumas->status);
        $this->assertSame('001/UNIVBI.HUMAS/SKL/XI/2026', $outgoingHumas->letter_number);
        $this->assertNotNull($outgoingHumas->stamped_file_path);

        // 4. Pastikan berkas dapat diunduh oleh pemohon dan Humas
        $this->actingAs($stafTi)
            ->get(route('outgoing-letters.file.download', $outgoingTi->uuid))
            ->assertOk();
    }

    public function test_outgoing_letter_docx_preserves_numbering_flags_and_attaches_bottom_right_footer_qr(): void
    {
        $humas = User::where('email', 'humas@univbi.ac.id')->firstOrFail();
        $unitTi = Unit::where('code', 'TI')->firstOrFail();

        $phpWord = new PhpWord;
        $section = $phpWord->addSection();
        $section->addText('Nomor: {seq}/UNIVBI.{unit}/SKL/{roman_month}/{year}');
        $section->addText('Alternatif: ${NOMOR_SURAT}');
        $section->addText('Ref: {nomor_surat}');
        $section->addText('Lubuklinggau, {tanggal_sah}');

        $tempDocx = tempnam(sys_get_temp_dir(), 'skl_docx_').'.docx';
        $writer = IOFactory::createWriter($phpWord, 'Word2007');
        $writer->save($tempDocx);

        // Simulasikan MS Word yang memecah tag {tanggal_sah} di antara run dan proofErr
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

        $originalPath = 'outgoing-letters/draf-skl-ti.docx';
        Storage::disk('private')->put($originalPath, (string) file_get_contents($tempDocx));
        @unlink($tempDocx);

        $outgoing = OutgoingLetter::create([
            'unit_id' => $unitTi->id,
            'created_by' => $humas->id,
            'destination_organization' => 'Kementerian Dikti',
            'subject' => 'Surat Pengantar Word Berflag',
            'letter_date' => '2026-11-14',
            'original_file_path' => $originalPath,
            'file_name' => 'draf-skl-ti.docx',
        ]);

        app(LetterManagementService::class)->processNewOutgoingLetter($outgoing, $humas);
        $outgoing->refresh();

        $this->assertSame(OutgoingLetterStatus::Published, $outgoing->status);
        $this->assertSame('001/UNIVBI.TI/SKL/XI/2026', $outgoing->letter_number);
        $this->assertNotNull($outgoing->stamped_file_path);
        Storage::disk('private')->assertExists($outgoing->stamped_file_path);

        $tempCheck = tempnam(sys_get_temp_dir(), 'skl_chk_').'.docx';
        file_put_contents($tempCheck, (string) Storage::disk('private')->get($outgoing->stamped_file_path));

        $zip = new \ZipArchive;
        $this->assertTrue($zip->open($tempCheck));
        $documentXml = $zip->getFromName('word/document.xml');
        $footerXml = $zip->getFromName('word/footer1.xml');
        $footerRelsXml = $zip->getFromName('word/_rels/footer1.xml.rels');
        $docRelsXml = $zip->getFromName('word/_rels/document.xml.rels');
        $contentTypesXml = $zip->getFromName('[Content_Types].xml');
        $zip->close();
        @unlink($tempCheck);

        foreach ([$documentXml, $footerXml, $footerRelsXml, $docRelsXml, $contentTypesXml] as $xmlPart) {
            $this->assertIsString($xmlPart);
            $dom = new \DOMDocument;
            $this->assertTrue($dom->loadXML($xmlPart));
        }

        $this->assertStringContainsString('001/UNIVBI.TI/SKL/XI/2026', $documentXml);
        $this->assertStringContainsString('14 November 2026', $documentXml);
        $this->assertStringNotContainsString('{seq}/UNIVBI.{unit}/SKL/{roman_month}/{year}', $documentXml);
        $this->assertStringNotContainsString('${NOMOR_SURAT}', $documentXml);
        $this->assertStringNotContainsString('{nomor_surat}', $documentXml);
        $this->assertStringNotContainsString('tanggal_sah', $documentXml);

        $this->assertStringContainsString('r:embed="rId', $footerXml);
        $this->assertStringContainsString('relativeFrom="page"', $footerXml);
        $this->assertStringContainsString('<wp:extent cx="660400" cy="660400"/>', $footerXml);
    }

    public function test_filament_pages_and_tracker_views_render_cleanly(): void
    {
        $humas = User::where('email', 'humas@univbi.ac.id')->firstOrFail();
        $rektor = User::where('email', 'rektor@univbi.ac.id')->firstOrFail();
        $unitRektorat = Unit::where('code', 'REKTORAT')->firstOrFail();

        Storage::disk('private')->put('incoming-letters/test-view.pdf', $this->makeValidPdfContent());

        $incoming = IncomingLetter::create([
            'unit_id' => $unitRektorat->id,
            'registered_by' => $humas->id,
            'sender_name' => 'Instansi Penguji',
            'received_date' => '2026-11-14',
            'subject' => 'Surat Masuk Pengujian View',
            'priority' => LetterPriority::Biasa,
            'original_file_path' => 'incoming-letters/test-view.pdf',
            'file_name' => 'test-view.pdf',
        ]);

        IncomingLetterRecipient::create([
            'incoming_letter_id' => $incoming->id,
            'step_order' => 1,
            'recipient_user_id' => $rektor->id,
        ]);

        app(LetterManagementService::class)->initializeIncomingDistribution($incoming);

        $outgoing = OutgoingLetter::create([
            'unit_id' => $unitRektorat->id,
            'created_by' => $humas->id,
            'destination_organization' => 'Mitra Universitas',
            'subject' => 'Surat Keluar Pengujian View',
            'letter_date' => '2026-11-14',
            'original_file_path' => 'incoming-letters/test-view.pdf',
            'file_name' => 'test-view.pdf',
        ]);

        app(LetterManagementService::class)->processNewOutgoingLetter($outgoing, $humas);

        $this->actingAs($rektor);

        Livewire::test(ListIncomingLetters::class)
            ->assertSuccessful()
            ->assertSee('Surat Masuk Pengujian View');

        Livewire::test(ViewIncomingLetter::class, ['record' => $incoming->getRouteKey()])
            ->assertSuccessful()
            ->assertSee('Pelacakan Distribusi & Status Baca Surat Masuk', false)
            ->assertSee('Berkas Fisik & Pratinjau Media Surat Masuk', false)
            ->assertSee('Pratinjau Interaktif Dokumen PDF (Langsung di Halaman)', false)
            ->assertSee('Pratinjau Layar Penuh')
            ->assertSee('001/UNIVBI.REKTORAT/SM/XI/2026')
            ->callAction('preview')
            ->assertSuccessful();

        $this->actingAs($humas);

        Livewire::test(ListOutgoingLetters::class)
            ->assertSuccessful()
            ->assertSee('Surat Keluar Pengujian View');

        Livewire::test(ViewOutgoingLetter::class, ['record' => $outgoing->getRouteKey()])
            ->assertSuccessful()
            ->assertSee('Pelacakan Penomoran & Pengesahan Surat Keluar', false)
            ->assertSee('Berkas Fisik & Pratinjau Media Surat Keluar', false)
            ->assertSee('Pratinjau Interaktif Dokumen PDF (Langsung di Halaman)', false)
            ->assertSee('Pratinjau Layar Penuh')
            ->assertSee('001/UNIVBI.REKTORAT/SKL/XI/2026')
            ->callAction('preview')
            ->assertSuccessful();
    }

    public function test_incoming_letter_physical_files_deleted_when_letter_deleted(): void
    {
        $humas = User::where('email', 'humas@univbi.ac.id')->firstOrFail();
        $rektor = User::where('email', 'rektor@univbi.ac.id')->firstOrFail();
        $unitRektorat = Unit::where('code', 'REKTORAT')->firstOrFail();

        $originalPath = 'incoming-letters/surat-akan-dihapus.pdf';
        Storage::disk('private')->put($originalPath, $this->makeValidPdfContent('Surat Yang Akan Dihapus'));

        $incoming = IncomingLetter::create([
            'unit_id' => $unitRektorat->id,
            'registered_by' => $humas->id,
            'sender_name' => 'Instansi Tertentu',
            'received_date' => '2026-11-14',
            'subject' => 'Surat Pengujian Hapus Berkas Fisik',
            'priority' => LetterPriority::Biasa,
            'original_file_path' => $originalPath,
            'file_name' => 'surat-akan-dihapus.pdf',
        ]);

        IncomingLetterRecipient::create([
            'incoming_letter_id' => $incoming->id,
            'step_order' => 1,
            'recipient_user_id' => $rektor->id,
        ]);

        app(LetterManagementService::class)->initializeIncomingDistribution($incoming);
        $incoming->refresh();

        $stampedPath = $incoming->stamped_file_path;
        $this->assertNotNull($stampedPath);
        Storage::disk('private')->assertExists($originalPath);
        Storage::disk('private')->assertExists($stampedPath);

        // Hapus data surat masuk -> kedua file fisik harus ikut terhapus dari storage
        $incoming->delete();

        Storage::disk('private')->assertMissing($originalPath);
        Storage::disk('private')->assertMissing($stampedPath);
    }

    public function test_outgoing_letter_physical_files_deleted_when_letter_deleted(): void
    {
        $humas = User::where('email', 'humas@univbi.ac.id')->firstOrFail();
        $unitHumas = Unit::where('code', 'HUMAS')->firstOrFail();

        $originalPath = 'outgoing-letters/surat-keluar-akan-dihapus.pdf';
        Storage::disk('private')->put($originalPath, $this->makeValidPdfContent('Surat Keluar Dihapus'));

        $outgoing = OutgoingLetter::create([
            'unit_id' => $unitHumas->id,
            'created_by' => $humas->id,
            'destination_organization' => 'Pihak Mitra Luar',
            'subject' => 'Surat Keluar Hapus Berkas',
            'letter_date' => '2026-11-14',
            'original_file_path' => $originalPath,
            'file_name' => 'surat-keluar-akan-dihapus.pdf',
        ]);

        app(LetterManagementService::class)->processNewOutgoingLetter($outgoing, $humas);
        $outgoing->refresh();

        $stampedPath = $outgoing->stamped_file_path;
        $this->assertNotNull($stampedPath);
        Storage::disk('private')->assertExists($originalPath);
        Storage::disk('private')->assertExists($stampedPath);

        // Hapus surat keluar -> berkas fisik asli dan berkas berstempel harus ikut terhapus
        $outgoing->delete();

        Storage::disk('private')->assertMissing($originalPath);
        Storage::disk('private')->assertMissing($stampedPath);
    }

    public function test_dashboard_pending_letters_alert_widget_behavior(): void
    {
        $humas = User::where('email', 'humas@univbi.ac.id')->firstOrFail();
        $warek = User::where('email', 'warek1@univbi.ac.id')->firstOrFail();
        $rektor = User::where('email', 'rektor@univbi.ac.id')->firstOrFail();
        $unitRektorat = Unit::where('code', 'REKTORAT')->firstOrFail();
        $unitTi = Unit::where('code', 'TI')->firstOrFail();

        // Kondisi 1: Awalnya Warek belum punya surat -> canView() false (widget hidden)
        $this->actingAs($warek);
        $this->assertFalse(PendingLettersAlertWidget::canView());

        // Buat 1 surat masuk ke Rektorat dengan penerima tahap 1: Warek
        $incoming = IncomingLetter::create([
            'unit_id' => $unitRektorat->id,
            'registered_by' => $humas->id,
            'sender_name' => 'Kementerian Dikti',
            'received_date' => '2026-11-14',
            'subject' => 'Surat Undangan Evaluasi Nasional',
            'priority' => LetterPriority::Penting,
        ]);

        IncomingLetterRecipient::create([
            'incoming_letter_id' => $incoming->id,
            'step_order' => 1,
            'recipient_user_id' => $warek->id,
            'status' => IncomingRecipientStatus::Unread,
        ]);

        $incoming->update([
            'current_step' => 1,
            'total_steps' => 1,
            'status' => IncomingLetterStatus::InProgress,
        ]);

        // Sekarang Warek memiliki 1 surat masuk yang perlu ditinjau -> canView() true
        $this->assertTrue(PendingLettersAlertWidget::canView());

        Livewire::actingAs($warek)
            ->test(PendingLettersAlertWidget::class)
            ->assertSee('Anda memiliki', false)
            ->assertSee('1 surat masuk', false)
            ->assertSee('yang perlu ditinjau, silakan lihat', false)
            ->assertSee('Lihat Surat Masuk');

        // Warek menandai sudah dibaca -> status surat selesai, widget otomatis tersembunyi kembali (hidden)
        app(LetterManagementService::class)->markAsRead($incoming, $warek, 'Sudah dibaca.');
        $incoming->refresh();

        $this->assertFalse(PendingLettersAlertWidget::canView());

        // Kondisi 2: Bagian Humas memiliki pengajuan surat keluar dari unit yang perlu dinomori
        $outgoing = OutgoingLetter::create([
            'unit_id' => $unitTi->id,
            'created_by' => $warek->id,
            'destination_organization' => 'Mitra Industri Sumsel',
            'subject' => 'Permohonan Narasumber Kuliah Umum',
            'letter_date' => '2026-11-14',
            'status' => OutgoingLetterStatus::PendingHumas,
        ]);

        // Bagi user biasa (Warek), surat keluar pending humas tidak memunculkan widget
        $this->actingAs($warek);
        $this->assertFalse(PendingLettersAlertWidget::canView());

        // Bagi Humas, widget muncul memberi tahu ada surat keluar unit yang perlu ditinjau dan dinomori
        $this->actingAs($humas);
        $this->assertTrue(PendingLettersAlertWidget::canView());

        Livewire::actingAs($humas)
            ->test(PendingLettersAlertWidget::class)
            ->assertSee('Terdapat', false)
            ->assertSee('1 surat keluar dari unit', false)
            ->assertSee('yang perlu ditinjau dan dinomori', false)
            ->assertSee('Tinjau & Beri Nomor', false);

        // Setelah Humas menyetujui dan memberi nomor, widget kembali tersembunyi
        app(LetterManagementService::class)->approveOutgoingLetter($outgoing, $humas, 'Disahkan Humas.');
        $outgoing->refresh();

        $this->assertFalse(PendingLettersAlertWidget::canView());
    }

    public function test_letters_scalability_table_config_search_and_pending_turn_scope(): void
    {
        $humas = User::where('email', 'humas@univbi.ac.id')->firstOrFail();
        $rektor = User::where('email', 'rektor@univbi.ac.id')->firstOrFail();
        $warek = User::where('email', 'warek1@univbi.ac.id')->firstOrFail();
        $unitRektorat = Unit::where('code', 'REKTORAT')->firstOrFail();

        $incomingMatch = IncomingLetter::create([
            'unit_id' => $unitRektorat->id,
            'registered_by' => $humas->id,
            'sender_name' => 'BAN-PT Jakarta',
            'received_date' => '2026-11-15',
            'subject' => 'Visitasi Akreditasi Unggul 2026',
            'priority' => LetterPriority::Segera,
        ]);

        IncomingLetterRecipient::create([
            'incoming_letter_id' => $incomingMatch->id,
            'step_order' => 1,
            'recipient_user_id' => $rektor->id,
        ]);
        IncomingLetterRecipient::create([
            'incoming_letter_id' => $incomingMatch->id,
            'step_order' => 2,
            'recipient_user_id' => $warek->id,
        ]);
        app(LetterManagementService::class)->initializeIncomingDistribution($incomingMatch);

        $incomingOther = IncomingLetter::create([
            'unit_id' => $unitRektorat->id,
            'registered_by' => $humas->id,
            'sender_name' => 'LLDIKTI Wilayah II',
            'received_date' => '2026-11-15',
            'subject' => 'Laporan Kinerja Semester Ganjil',
            'priority' => LetterPriority::Biasa,
        ]);

        IncomingLetterRecipient::create([
            'incoming_letter_id' => $incomingOther->id,
            'step_order' => 1,
            'recipient_user_id' => $rektor->id,
        ]);
        app(LetterManagementService::class)->initializeIncomingDistribution($incomingOther);

        $outgoingMatch = OutgoingLetter::create([
            'unit_id' => $unitRektorat->id,
            'created_by' => $humas->id,
            'destination_organization' => 'Kemendikbudristek',
            'subject' => 'Permohonan Penambahan Kuota Riset',
            'letter_date' => '2026-11-15',
        ]);
        app(LetterManagementService::class)->processNewOutgoingLetter($outgoingMatch, $humas);

        $outgoingOther = OutgoingLetter::create([
            'unit_id' => $unitRektorat->id,
            'created_by' => $humas->id,
            'destination_organization' => 'Mitra Industri',
            'subject' => 'Undangan Rapat Tahunan',
            'letter_date' => '2026-11-15',
        ]);
        app(LetterManagementService::class)->processNewOutgoingLetter($outgoingOther, $humas);

        // 1. Verifikasi scopePendingTurnForUser, navigation badge, & tab my_turn saat giliran Rektor
        $this->actingAs($rektor);
        $this->assertSame('2', IncomingLetterResource::getNavigationBadge());
        $this->assertSame(2, PendingLettersAlertWidget::getPendingLetterCounts($rektor)['incomingCount']);
        $this->assertSame(0, PendingLettersAlertWidget::getPendingLetterCounts($warek)['incomingCount']);

        $incomingComponent = Livewire::test(ListIncomingLetters::class)
            ->assertSuccessful()
            ->set('activeTab', 'my_turn')
            ->assertCanSeeTableRecords([$incomingMatch, $incomingOther])
            ->searchTable('Visitasi Akreditasi')
            ->assertCanSeeTableRecords([$incomingMatch])
            ->assertCanNotSeeTableRecords([$incomingOther]);

        $incomingTable = $incomingComponent->instance()->getTable();
        $this->assertSame(PaginationMode::Simple, $incomingTable->getPaginationMode());
        $this->assertSame('500ms', $incomingTable->getSearchDebounce());
        $this->assertSame(10, $incomingTable->getDefaultPaginationPageOption());

        // 2. Setelah Rektor membaca, giliran bergeser ke Warek (step 2) -> badge ter-update akurat
        app(LetterManagementService::class)->markAsRead($incomingMatch->fresh(), $rektor, 'Lanjutkan ke Warek.');
        $this->assertSame('1', IncomingLetterResource::getNavigationBadge());

        $this->actingAs($warek);
        $this->assertSame('1', IncomingLetterResource::getNavigationBadge());
        Livewire::test(ListIncomingLetters::class)
            ->assertSuccessful()
            ->set('activeTab', 'my_turn')
            ->assertCanSeeTableRecords([$incomingMatch])
            ->assertCanNotSeeTableRecords([$incomingOther]);

        // 3. Verifikasi OutgoingLettersTable pagination simple, searchDebounce 500ms, dan filter search
        $this->actingAs($humas);
        $outgoingComponent = Livewire::test(ListOutgoingLetters::class)
            ->assertSuccessful()
            ->searchTable('Kuota Riset')
            ->assertCanSeeTableRecords([$outgoingMatch])
            ->assertCanNotSeeTableRecords([$outgoingOther]);

        $outgoingTable = $outgoingComponent->instance()->getTable();
        $this->assertSame(PaginationMode::Simple, $outgoingTable->getPaginationMode());
        $this->assertSame('500ms', $outgoingTable->getSearchDebounce());
        $this->assertSame(10, $outgoingTable->getDefaultPaginationPageOption());
    }
}
