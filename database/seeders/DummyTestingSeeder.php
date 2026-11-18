<?php

namespace Database\Seeders;

use App\Enums\ApprovalStatus;
use App\Enums\AssetCondition;
use App\Enums\AssetEventType;
use App\Enums\AssetMaintenanceStatus;
use App\Enums\AssetMaintenanceType;
use App\Enums\AssetOwnership;
use App\Enums\AssetStatus;
use App\Enums\DocumentStatus;
use App\Enums\PostPeriod;
use App\Enums\PostStatus;
use App\Enums\StorageDisk;
use App\Enums\VisibilityLevel;
use App\Models\AcademicYear;
use App\Models\AccreditationCriterion;
use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\AssetLocation;
use App\Models\AssetMaintenance;
use App\Models\AssetMovement;
use App\Models\Document;
use App\Models\DocumentApproval;
use App\Models\DocumentCategory;
use App\Models\DocumentFile;
use App\Models\Post;
use App\Models\PostCategory;
use App\Models\PostPhoto;
use App\Models\Unit;
use App\Models\User;
use FPDF;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;

/**
 * ⚠️ PERINGATAN PENTING (STRICT ENVIRONMENT GUARD):
 * Seeder ini HANYA untuk kebutuhan TESTING & DEMO di environment LOCAL / TESTING.
 * Seeder ini otomatis men-generate file fisik simulasi (.pdf, .docx, .jpg) ke dalam folder storage/
 * agar pengujian Preview PDF, Stamping QR Code, Download ZIP, dan Tracking Aset bisa berjalan
 * tanpa harus mengunggah file manual satu per satu.
 * Seeder ini DIBLOKIR OTOMATIS apabila dijalankan pada APP_ENV=production.
 * Seeder ini hanya untuk kebutuhan testing dan demo.
 */
class DummyTestingSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            $this->command?->warn('⚠️ [ABORTED] DummyTestingSeeder HANYA diizinkan pada environment LOCAL atau TESTING!');

            return;
        }

        $this->command?->info('🧪 Menjalankan DummyTestingSeeder (Khusus Environment Local/Testing)...');

        $documents = $this->seedDummyDocumentsWithPhysicalFiles();
        $this->seedDummyPostsAndGalleryPhotos($documents);
        $this->seedDummyAssetsAndMovements($documents);
    }

    /**
     * @return array<string, Document>
     */
    private function seedDummyDocumentsWithPhysicalFiles(): array
    {
        $unitTi = Unit::where('code', 'TI')->firstOrFail();
        $unitFik = Unit::where('code', 'FIK')->firstOrFail();
        $unitRektorat = Unit::where('code', 'REKTORAT')->firstOrFail();
        $unitBauk = Unit::where('code', 'BAUK')->firstOrFail();

        $activeYear = AcademicYear::where('is_active', true)->firstOrFail();

        $userStafTi = User::where('email', 'staf.ti@univbi.ac.id')->firstOrFail();
        $userKaprodiTi = User::where('email', 'kaprodi.ti@univbi.ac.id')->firstOrFail();
        $userDekanFik = User::where('email', 'dekan.fik@univbi.ac.id')->firstOrFail();
        $userWarek1 = User::where('email', 'warek1@univbi.ac.id')->firstOrFail();
        $userRektor = User::where('email', 'rektor@univbi.ac.id')->firstOrFail();
        $userBauk = User::where('email', 'bauk@univbi.ac.id')->firstOrFail();

        $catSkDekan = DocumentCategory::where('slug', 'sk-dekan')->firstOrFail();
        $catSeRektorat = DocumentCategory::where('slug', 'se-rektorat')->firstOrFail();
        $catMou = DocumentCategory::where('slug', 'mou')->firstOrFail();
        $catRps = DocumentCategory::where('slug', 'rps')->firstOrFail();
        $catBast = DocumentCategory::where('slug', 'bast-aset')->firstOrFail();

        $critK2 = AccreditationCriterion::where('code', 'K2')->firstOrFail();
        $critK5 = AccreditationCriterion::where('code', 'K5')->firstOrFail();
        $critK6 = AccreditationCriterion::where('code', 'K6')->firstOrFail();

        $docs = [];

        // 1. Dokumen APPROVED (Internal & Borang K6): SK Dekan Penetapan Kurikulum OBE Prodi TI
        $docs['sk_kurikulum'] = Document::updateOrCreate(
            ['slug' => 'penetapan-kurikulum-obe-prodi-s1-informatika-2026'],
            [
                'title' => 'SK Dekan Penetapan Kurikulum Berbasis OBE Program Studi S1 Informatika Tahun 2026',
                'unit_id' => $unitTi->id,
                'document_category_id' => $catSkDekan->id,
                'academic_year_id' => $activeYear->id,
                'uploaded_by' => $userStafTi->id,
                'is_manual_number' => false,
                'sequence_year' => 2026,
                'sequence_number' => 1,
                'document_number' => '001/UNIVBI.FIK.TI/SK/XI/2026',
                'visibility' => VisibilityLevel::Public,
                'status' => DocumentStatus::Approved,
                'current_step' => 2,
                'total_steps' => 2,
                'final_approved_by' => $userDekanFik->id,
                'approved_at' => now()->subDays(5),
                'signer_name_snapshot' => $userDekanFik->name,
                'signer_position_snapshot' => $userDekanFik->position_title,
                'is_borang' => true,
                'has_expiry' => false,
                'issued_date' => now()->subDays(5)->toDateString(),
                'description' => 'Dokumen contoh pengujian (Dummy Local) untuk SK Kurikulum OBE yang telah disahkan Dekan FIK.',
            ]
        );

        $this->attachGeneratedPdfFile(
            $docs['sk_kurikulum'],
            $userStafTi,
            '2026-TI-SK-penetapan-kurikulum-obe-prodi-s1-informatika-2026.pdf',
            StorageDisk::Private,
            true
        );

        DocumentApproval::updateOrCreate(
            ['document_id' => $docs['sk_kurikulum']->id, 'revision_round' => 1, 'step_order' => 1],
            [
                'approver_role' => 'kaprodi',
                'target_unit_id' => $unitTi->id,
                'status' => ApprovalStatus::Approved,
                'approved_by' => $userKaprodiTi->id,
                'approver_position_snapshot' => $userKaprodiTi->position_title,
                'approved_at' => now()->subDays(6),
                'note' => 'Draf kurikulum OBE telah sesuai hasil rapat prodi.',
            ]
        );

        DocumentApproval::updateOrCreate(
            ['document_id' => $docs['sk_kurikulum']->id, 'revision_round' => 1, 'step_order' => 2],
            [
                'approver_role' => 'dekan',
                'target_unit_id' => $unitFik->id,
                'status' => ApprovalStatus::Approved,
                'approved_by' => $userDekanFik->id,
                'approver_position_snapshot' => $userDekanFik->position_title,
                'approved_at' => now()->subDays(5),
                'note' => 'Disetujui dan disahkan untuk diberlakukan pada Semester Ganjil 2026/2027.',
            ]
        );

        $docs['sk_kurikulum']->accreditationCriteria()->syncWithoutDetaching([
            $critK6->id => ['indicator_code' => 'Butir 6.a.1', 'order_index' => 1, 'note' => 'Bukti SK Kurikulum OBE'],
        ]);

        // 2. Dokumen APPROVED (Public): Surat Edaran Rektor tentang Kalender Akademik
        $docs['se_kalender'] = Document::updateOrCreate(
            ['slug' => 'surat-edaran-kalender-akademik-ganjil-2026-2027'],
            [
                'title' => 'Surat Edaran Rektor tentang Kalender Akademik Semester Ganjil 2026/2027',
                'unit_id' => $unitRektorat->id,
                'document_category_id' => $catSeRektorat->id,
                'academic_year_id' => $activeYear->id,
                'uploaded_by' => $userWarek1->id,
                'is_manual_number' => false,
                'sequence_year' => 2026,
                'sequence_number' => 1,
                'document_number' => '001/UNIVBI.REKTORAT/SE/XI/2026',
                'visibility' => VisibilityLevel::Public,
                'status' => DocumentStatus::Approved,
                'current_step' => 2,
                'total_steps' => 2,
                'final_approved_by' => $userRektor->id,
                'approved_at' => now()->subDays(3),
                'signer_name_snapshot' => $userRektor->name,
                'signer_position_snapshot' => $userRektor->position_title,
                'is_borang' => true,
                'has_expiry' => false,
                'issued_date' => now()->subDays(3)->toDateString(),
                'description' => 'Surat edaran publik resmi jadwal perkuliahan dan UTS/UAS Semester Ganjil 2026/2027.',
            ]
        );

        $this->attachGeneratedPdfFile(
            $docs['se_kalender'],
            $userWarek1,
            '2026-REKTORAT-SE-surat-edaran-kalender-akademik-ganjil-2026-2027.pdf',
            StorageDisk::Public,
            true
        );

        $docs['se_kalender']->accreditationCriteria()->syncWithoutDetaching([
            $critK6->id => ['indicator_code' => 'Butir 6.b', 'order_index' => 2, 'note' => 'Kalender Akademik Resmi'],
        ]);

        // 3. Dokumen APPROVED dengan Masa Berlaku (MoU Kerja Sama Industri - H-30 Reminder Testing)
        $docs['mou_industri'] = Document::updateOrCreate(
            ['slug' => 'mou-kerjasama-magang-mbkm-pt-telkom-indonesia-2026'],
            [
                'title' => 'MoU Kerja Sama Magang Industri & Riset Terapan dengan PT Telkom Indonesia',
                'unit_id' => $unitFik->id,
                'document_category_id' => $catMou->id,
                'academic_year_id' => $activeYear->id,
                'uploaded_by' => $userDekanFik->id,
                'is_manual_number' => false,
                'sequence_year' => 2026,
                'sequence_number' => 1,
                'document_number' => '001/UNIVBI.FIK/MOU/XI/2026',
                'visibility' => VisibilityLevel::Public,
                'status' => DocumentStatus::Approved,
                'current_step' => 3,
                'total_steps' => 3,
                'final_approved_by' => $userRektor->id,
                'approved_at' => now()->subDays(7),
                'signer_name_snapshot' => $userRektor->name,
                'signer_position_snapshot' => $userRektor->position_title,
                'is_borang' => true,
                'has_expiry' => true,
                'issued_date' => now()->subDays(7)->toDateString(),
                'expired_date' => now()->addDays(30)->toDateString(),
                'reminder_intervals' => ['h60', 'h30', 'h7'],
                'reminder_recipients' => [$userDekanFik->id, $userKaprodiTi->id],
                'description' => 'Contoh dokumen MoU dengan masa berlaku aktif (tersisa 30 hari) untuk pengujian pengingat otomatis.',
            ]
        );

        $this->attachGeneratedPdfFile(
            $docs['mou_industri'],
            $userDekanFik,
            '2026-FIK-MOU-mou-kerjasama-magang-mbkm-pt-telkom-indonesia-2026.pdf',
            StorageDisk::Private,
            true
        );

        $docs['mou_industri']->accreditationCriteria()->syncWithoutDetaching([
            $critK2->id => ['indicator_code' => 'Tabel 2.a', 'order_index' => 1, 'note' => 'Bukti MoU Kerja Sama Industri'],
        ]);

        // 4. Dokumen PENDING (Menunggu Persetujuan Kaprodi TI - Berformat Word .DOCX untuk uji konversi & stamping)
        $docs['rps_pending'] = Document::updateOrCreate(
            ['slug' => 'rps-mata-kuliah-kecerdasan-buatan-ganjil-2026'],
            [
                'title' => 'RPS Mata Kuliah Kecerdasan Buatan (Artificial Intelligence) Semester Ganjil 2026/2027',
                'unit_id' => $unitTi->id,
                'document_category_id' => $catRps->id,
                'academic_year_id' => $activeYear->id,
                'uploaded_by' => $userStafTi->id,
                'is_manual_number' => false,
                'sequence_year' => 2026,
                'visibility' => VisibilityLevel::Internal,
                'status' => DocumentStatus::Pending,
                'current_step' => 1,
                'total_steps' => 1,
                'is_borang' => true,
                'has_expiry' => false,
                'issued_date' => now()->toDateString(),
                'description' => 'Draf dokumen berformat Word (.docx) yang sedang menunggu persetujuan Kaprodi TI.',
            ]
        );

        $this->attachGeneratedDocxFile(
            $docs['rps_pending'],
            $userStafTi,
            '2026-TI-RPS-rps-mata-kuliah-kecerdasan-buatan-ganjil-2026.docx'
        );

        DocumentApproval::updateOrCreate(
            ['document_id' => $docs['rps_pending']->id, 'revision_round' => 1, 'step_order' => 1],
            [
                'approver_role' => 'kaprodi',
                'target_unit_id' => $unitTi->id,
                'status' => ApprovalStatus::Pending,
            ]
        );

        // 5. Dokumen BAST Aset (Untuk ditautkan ke Modul Manajemen Aset Tetap /assets)
        $docs['bast_lab'] = Document::updateOrCreate(
            ['slug' => 'bast-pengadaan-perangkat-laboratorium-komputer-ti-2026'],
            [
                'title' => 'Berita Acara Serah Terima (BAST) Pengadaan PC & Proyektor Laboratorium Prodi S1 Informatika',
                'unit_id' => $unitBauk->id,
                'document_category_id' => $catBast->id,
                'academic_year_id' => $activeYear->id,
                'uploaded_by' => $userBauk->id,
                'is_manual_number' => false,
                'sequence_year' => 2026,
                'sequence_number' => 1,
                'document_number' => '001/UNIVBI.BAUK/BAST/XI/2026',
                'visibility' => VisibilityLevel::Internal,
                'status' => DocumentStatus::Approved,
                'current_step' => 0,
                'total_steps' => 0,
                'final_approved_by' => $userBauk->id,
                'approved_at' => now()->subDays(10),
                'signer_name_snapshot' => $userBauk->name,
                'signer_position_snapshot' => $userBauk->position_title,
                'is_borang' => true,
                'has_expiry' => false,
                'issued_date' => now()->subDays(10)->toDateString(),
                'description' => 'Dokumen BAST resmi serah terima aset tetap laboratorium komputer dari BAUK ke Prodi S1 Informatika.',
            ]
        );

        $this->attachGeneratedPdfFile(
            $docs['bast_lab'],
            $userBauk,
            '2026-BAUK-BAST-bast-pengadaan-perangkat-laboratorium-komputer-ti-2026.pdf',
            StorageDisk::Private,
            true
        );

        $docs['bast_lab']->accreditationCriteria()->syncWithoutDetaching([
            $critK5->id => ['indicator_code' => 'Tabel 5.b', 'order_index' => 1, 'note' => 'Bukti BAST Sarana Laboratorium'],
        ]);

        return $docs;
    }

    private function attachGeneratedPdfFile(
        Document $document,
        User $uploader,
        string $standardizedFileName,
        StorageDisk $disk,
        bool $isStamped
    ): void {
        $unitCode = $document->unit->code ?? 'UNBI';
        $relativeDir = 'documents/2026/11/'.$unitCode;
        $originalRelativePath = $relativeDir.'/draft-'.$standardizedFileName;
        $stampedRelativePath = $isStamped ? ($relativeDir.'/'.$standardizedFileName) : null;

        $pdfBinary = $this->generateSamplePdfBinary($document, false);
        Storage::disk($disk->value)->put($originalRelativePath, $pdfBinary);

        $hash = hash('sha256', $pdfBinary);

        if ($isStamped && $stampedRelativePath !== null) {
            $stampedBinary = $this->generateSamplePdfBinary($document, true);
            Storage::disk($disk->value)->put($stampedRelativePath, $stampedBinary);
            $hash = hash('sha256', $stampedBinary);
        }

        DocumentFile::updateOrCreate(
            [
                'document_id' => $document->id,
                'version' => 1,
                'is_primary' => true,
            ],
            [
                'uploaded_by' => $uploader->id,
                'label' => 'Naskah Utama Resmi',
                'order_index' => 1,
                'original_file_name' => $standardizedFileName,
                'standardized_file_name' => $standardizedFileName,
                'original_file_path' => $originalRelativePath,
                'stamped_file_path' => $stampedRelativePath,
                'disk' => $disk,
                'mime_type' => 'application/pdf',
                'file_extension' => 'pdf',
                'file_size' => strlen($pdfBinary),
                'page_count' => 1,
                'file_hash_sha256' => $hash,
                'is_active' => true,
                'google_drive_id' => $isStamped ? 'gdrive_sim_'.substr($hash, 0, 16) : null,
                'google_drive_url' => $isStamped ? 'https://drive.google.com/file/d/gdrive_sim_'.substr($hash, 0, 16).'/view' : null,
                'is_synced' => $isStamped,
                'synced_at' => $isStamped ? now() : null,
            ]
        );
    }

    private function attachGeneratedDocxFile(Document $document, User $uploader, string $standardizedFileName): void
    {
        $unitCode = $document->unit->code ?? 'UNBI';
        $relativeDir = 'documents/2026/11/'.$unitCode;
        $originalRelativePath = $relativeDir.'/'.$standardizedFileName;

        $phpWord = new PhpWord;
        $section = $phpWord->addSection();
        $section->addText('UNIVERSITAS BINA INSAN (UNIVBI)', ['bold' => true, 'size' => 14]);
        $section->addText('BERKAS SIMULASI TESTING LOKAL (AUTO-GENERATED DOCX)', ['italic' => true, 'size' => 10]);
        $section->addTextBreak(1);
        $section->addText('Judul Dokumen: '.$document->title, ['bold' => true, 'size' => 12]);
        $section->addText('Nomor Surat: {{nomor_surat}}');
        $section->addText('Tanggal Pengesahan: {{tanggal_surat}}');
        $section->addTextBreak(1);
        $section->addText($document->description ?? 'Dokumen contoh untuk pengujian alur persetujuan dan injeksi nomor otomatis.');

        $tempFile = tempnam(sys_get_temp_dir(), 'unbi_docx_');
        $writer = IOFactory::createWriter($phpWord, 'Word2007');
        $writer->save($tempFile);
        $docxBinary = (string) file_get_contents($tempFile);
        @unlink($tempFile);

        Storage::disk('private')->put($originalRelativePath, $docxBinary);

        DocumentFile::updateOrCreate(
            [
                'document_id' => $document->id,
                'version' => 1,
                'is_primary' => true,
            ],
            [
                'uploaded_by' => $uploader->id,
                'label' => 'Draf Naskah Word (.docx)',
                'order_index' => 1,
                'original_file_name' => $standardizedFileName,
                'standardized_file_name' => $standardizedFileName,
                'original_file_path' => $originalRelativePath,
                'stamped_file_path' => null,
                'disk' => StorageDisk::Private,
                'mime_type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'file_extension' => 'docx',
                'file_size' => strlen($docxBinary),
                'page_count' => 1,
                'file_hash_sha256' => hash('sha256', $docxBinary),
                'is_active' => true,
            ]
        );
    }

    private function generateSamplePdfBinary(Document $document, bool $withOfficialStamp): string
    {
        $pdf = new FPDF('P', 'mm', 'A4');
        $pdf->AddPage();

        if ($withOfficialStamp && $document->document_number) {
            $pdf->SetDrawColor(30, 64, 175);
            $pdf->SetFillColor(248, 250, 252);
            $pdf->SetLineWidth(0.25);
            $pdf->Rect(142, 8, 58, 16.5, 'DF');
            $pdf->SetXY(143.8, 9.2);
            $pdf->SetFont('Arial', 'B', 5.8);
            $pdf->SetTextColor(30, 64, 175);
            $pdf->Cell(38, 2.8, 'DOKUMEN SAH E-ARSIP UNIVBI', 0, 1, 'L');
            $pdf->SetX(143.8);
            $pdf->SetFont('Arial', 'B', 5.0);
            $pdf->SetTextColor(15, 23, 42);
            $pdf->Cell(38, 2.6, 'No: '.$document->document_number, 0, 1, 'L');
            $pdf->SetX(143.8);
            $pdf->SetFont('Arial', '', 4.8);
            $pdf->SetTextColor(51, 65, 85);
            $pdf->Cell(38, 2.4, 'Sah: '.($document->issued_date ? $document->issued_date->translatedFormat('d M Y') : date('d M Y')), 0, 1, 'L');
            $pdf->SetX(143.8);
            $pdf->Cell(38, 2.4, 'Oleh: '.($document->signer_name_snapshot ?? 'Pejabat UNBI'), 0, 1, 'L');
            $pdf->SetX(143.8);
            $pdf->SetFont('Arial', 'I', 4.2);
            $pdf->SetTextColor(100, 116, 139);
            $pdf->Cell(38, 2.2, 'Scan QR verifikasi keaslian', 0, 1, 'L');
        }

        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetY(40);
        $pdf->SetFont('Arial', 'B', 14);
        $pdf->Cell(0, 8, 'UNIVERSITAS BINA INSAN (UNIVBI)', 0, 1, 'C');
        $pdf->SetFont('Arial', '', 10);
        $pdf->Cell(0, 6, 'Jl. HM. Soeharto KM. 13 Lubuklinggau - Sumatera Selatan', 0, 1, 'C');
        $pdf->Line(15, 56, 195, 56);

        $pdf->Ln(10);
        $pdf->SetFont('Arial', 'B', 12);
        $pdf->MultiCell(0, 7, strtoupper($document->title), 0, 'C');

        if ($document->document_number) {
            $pdf->Ln(3);
            $pdf->SetFont('Arial', 'B', 10);
            $pdf->Cell(0, 6, 'NOMOR: '.$document->document_number, 0, 1, 'C');
        }

        $pdf->Ln(8);
        $pdf->SetFont('Arial', '', 11);
        $pdf->MultiCell(
            0,
            6,
            'Berkas PDF ini di-generate secara otomatis oleh DummyTestingSeeder (Khusus Environment Local/Testing) '
            .'untuk keperluan pengujian fitur Pratinjau PDF, Stamping Nomor Otomatis, Tautan Bukti Borang Akreditasi, '
            ."dan Pengunduhan Paket ZIP tanpa perlu mengunggah berkas manual.\n\n"
            .'Ringkasan Isi: '.($document->description ?? '-')
        );

        return $pdf->Output('S');
    }

    /**
     * @param  array<string, Document>  $documents
     */
    /**
     * @param  array<string, Document>  $documents
     */
    private function seedDummyPostsAndGalleryPhotos(array $documents): void
    {
        $unitTi = Unit::where('code', 'TI')->firstOrFail();
        $unitFik = Unit::where('code', 'FIK')->firstOrFail();
        $activeYear = AcademicYear::where('is_active', true)->firstOrFail();
        $userHumas = User::where('email', 'humas@univbi.ac.id')->firstOrFail();
        $userStafTi = User::where('email', 'staf.ti@univbi.ac.id')->firstOrFail();

        $catBerita = PostCategory::where('slug', 'berita-kegiatan')->firstOrFail();
        $catKerjasama = PostCategory::where('slug', 'kerjasama-karir')->firstOrFail();
        $catPrestasi = PostCategory::where('slug', 'prestasi-kampus')->firstOrFail();

        // 1. BERITA UTAMA (Featured & Pinned): Workshop Kurikulum OBE
        $post1 = Post::updateOrCreate(
            ['slug' => 'workshop-penyusunan-kurikulum-obe-fakultas-ilmu-komputer-2026'],
            [
                'title' => 'Workshop Penyusunan Kurikulum Berbasis OBE Fakultas Ilmu Komputer Tahun 2026',
                'post_category_id' => $catBerita->id,
                'period' => PostPeriod::Daily,
                'unit_id' => $unitTi->id,
                'academic_year_id' => $activeYear->id,
                'author_id' => $userStafTi->id,
                'event_date' => now()->subDays(5)->toDateString(),
                'event_location' => 'Aula Utama Gedung A Universitas Bina Insan',
                'excerpt' => 'Fakultas Ilmu Komputer UNBI menyelenggarakan Workshop Kurikulum OBE bersama pakar kurikulum nasional dan mitra industri.',
                'content' => '<p>Lubuklinggau — Fakultas Ilmu Komputer Universitas Bina Insan sukses menyelenggarakan kegiatan <strong>Workshop Penyusunan Kurikulum Berbasis Outcome-Based Education (OBE)</strong> yang diikuti oleh seluruh dosen Program Studi S1 Informatika, S1 Sistem Informasi, S1 Sistem Komputer, dan S1 Rekayasa Perangkat Lunak.</p><p>Kegiatan ini bertujuan menyelaraskan profil lulusan dan capaian pembelajaran (CPL) dengan kebutuhan industri digital 4.0 dan standar akreditasi internasional.</p><p>Seluruh foto dokumentasi kegiatan dan lampiran SK Kurikulum dapat diunduh langsung pada halaman ini.</p>',
                'status' => PostStatus::Published,
                'submitted_at' => now()->subDays(5),
                'approved_by' => $userHumas->id,
                'approved_at' => now()->subDays(4),
                'published_at' => now()->subDays(4),
                'photos_count' => 3,
                'allow_public_zip_download' => true,
                'is_featured' => true,
                'is_pinned' => true,
            ]
        );

        if (isset($documents['sk_kurikulum'])) {
            $post1->documents()->syncWithoutDetaching([
                $documents['sk_kurikulum']->id => [
                    'custom_button_label' => 'Unduh SK Penetapan Kurikulum OBE (.PDF)',
                    'order_index' => 1,
                ],
            ]);
        }

        $this->seedPhotosForPost($post1, [
            1 => 'Sesi pembukaan Workshop Kurikulum OBE oleh Dekan FIK',
            2 => 'Pemaparan materi penyelarasan CPL oleh narasumber ahli kurikulum',
            3 => 'Foto bersama seluruh dosen peserta workshop dan pimpinan fakultas',
        ], '2026-TI-workshop-kurikulum-obe', $userStafTi->id);

        // 2. BERITA KEMITRAAN (MoU Industri PT Telkom)
        $post2 = Post::updateOrCreate(
            ['slug' => 'penandatanganan-mou-kemitraan-strategis-pt-telkom-indonesia-2026'],
            [
                'title' => 'Penandatanganan Nota Kesepahaman (MoU) Kemitraan Strategis & Magang MBKM Bersama PT Telkom Indonesia',
                'post_category_id' => $catKerjasama->id,
                'period' => PostPeriod::Weekly,
                'unit_id' => $unitFik->id,
                'academic_year_id' => $activeYear->id,
                'author_id' => $userHumas->id,
                'event_date' => now()->subDays(3)->toDateString(),
                'event_location' => 'Auditorium Kampus Terpadu UNIVBI',
                'excerpt' => 'Universitas Bina Insan resmi memperluas jalinan kemitraan strategis dengan PT Telkom Indonesia guna mendukung program magang industri bersertifikat dan hilirisasi riset terapan.',
                'content' => '<p>Universitas Bina Insan (UNIVBI) resmi menandatangani Nota Kesepahaman (MoU) kemitraan strategis dengan PT Telkom Indonesia (Persero) Tbk. Kerja sama ini mencakup program magang bersertifikat MBKM, praktisi mengajar, serta pengembangan laboratorium riset teknologi jaringan cerdas.</p><p>Rektor UNIVBI menyampaikan bahwa kolaborasi ini merupakan langkah konkret untuk memastikan lulusan siap kerja dan adaptif terhadap transformasi digital nasional.</p>',
                'status' => PostStatus::Published,
                'submitted_at' => now()->subDays(3),
                'approved_by' => $userHumas->id,
                'approved_at' => now()->subDays(2),
                'published_at' => now()->subDays(2),
                'photos_count' => 3,
                'allow_public_zip_download' => true,
                'is_featured' => false,
                'is_pinned' => false,
            ]
        );

        if (isset($documents['mou_industri'])) {
            $post2->documents()->syncWithoutDetaching([
                $documents['mou_industri']->id => [
                    'custom_button_label' => 'Unduh Salinan Lembar Piagam MoU (.PDF)',
                    'order_index' => 1,
                ],
            ]);
        }

        $this->seedPhotosForPost($post2, [
            1 => 'Penandatanganan nota kesepahaman oleh Rektor UNIVBI dan VP Enterprise Telkom',
            2 => 'Penyerahan plakat kehormatan kerja sama riset dan magang industri',
            3 => 'Sesi foto bersama jajaran pimpinan universitas dan delegasi industri',
        ], '2026-FIK-mou-telkom-indonesia', $userHumas->id);

        // 3. BERITA PRESTASI (Mahasiswa Juara 1 Hackathon Nasional)
        $post3 = Post::updateOrCreate(
            ['slug' => 'mahasiswa-s1-informatika-univbi-raih-juara-1-hackathon-nasional-2026'],
            [
                'title' => 'Mahasiswa S1 Informatika UNIVBI Raih Juara 1 Hackathon Nasional Inovasi Digital 2026',
                'post_category_id' => $catPrestasi->id,
                'period' => PostPeriod::Monthly,
                'unit_id' => $unitTi->id,
                'academic_year_id' => $activeYear->id,
                'author_id' => $userStafTi->id,
                'event_date' => now()->subDays(1)->toDateString(),
                'event_location' => 'Jakarta Convention Center (JCC) Senayan',
                'excerpt' => 'Tim mahasiswa Program Studi Informatika Universitas Bina Insan berhasil menyabet medali emas dan trofi utama pada ajang kompetisi nasional pengembangan teknologi terapan.',
                'content' => '<p>Kabar membanggakan datang dari mahasiswa Program Studi S1 Informatika Universitas Bina Insan. Tim yang dipimpin oleh mahasiswa tingkat akhir berhasil meraih Juara 1 Nasional pada ajang Hackathon Inovasi Digital 2026 yang diselenggarakan oleh Kementerian Komunikasi dan Informatika bersama Asosiasi Industri Perangkat Lunak Indonesia.</p><p>Inovasi yang diusung berupa sistem pemantauan kualitas lingkungan cerdas berbasis Internet of Things (IoT) dan algoritma kecerdasan buatan.</p>',
                'status' => PostStatus::Published,
                'submitted_at' => now()->subDays(1),
                'approved_by' => $userHumas->id,
                'approved_at' => now()->subHours(12),
                'published_at' => now()->subHours(12),
                'photos_count' => 3,
                'allow_public_zip_download' => true,
                'is_featured' => false,
                'is_pinned' => false,
            ]
        );

        if (isset($documents['se_kalender'])) {
            $post3->documents()->syncWithoutDetaching([
                $documents['se_kalender']->id => [
                    'custom_button_label' => 'Lihat Pengumuman Resmi Universitas (.PDF)',
                    'order_index' => 1,
                ],
            ]);
        }

        $this->seedPhotosForPost($post3, [
            1 => 'Penyerahan trofi juara 1 hackathon nasional oleh dewan juri kehormatan',
            2 => 'Demonstrasi prototipe teknologi pemantauan cerdas berbasis AI',
            3 => 'Foto tim mahasiswa peraih medali emas bersama dosen pembimbing',
        ], '2026-TI-prestasi-hackathon-nasional', $userStafTi->id);
    }

    /**
     * @param  array<int, string>  $photoCaptions
     */
    private function seedPhotosForPost(Post $post, array $photoCaptions, string $prefix, int $userId): void
    {
        foreach ($photoCaptions as $index => $caption) {
            $stdName = sprintf('%s-Foto-%02d.jpg', $prefix, $index);
            $origPath = 'posts/photos/2026/11/ORIGINAL/'.$stdName;
            $thumbPath = 'posts/photos/2026/11/THUMB/'.$stdName;

            $jpgBinary = $this->generateSampleJpegBinary($caption, 1280, 720, $index);
            $thumbBinary = $this->generateSampleJpegBinary($caption, 480, 270, $index);

            Storage::disk('public')->put($origPath, $jpgBinary);
            Storage::disk('public')->put($thumbPath, $thumbBinary);

            if ($index === 1) {
                $post->update(['thumbnail_url' => $thumbPath, 'thumbnail_caption' => $caption]);
            }

            PostPhoto::updateOrCreate(
                ['post_id' => $post->id, 'order_index' => $index],
                [
                    'uploaded_by' => $userId,
                    'original_file_name' => sprintf('DSC_00%d.JPG', $index),
                    'standardized_file_name' => $stdName,
                    'file_path' => $origPath,
                    'thumbnail_path' => $thumbPath,
                    'disk' => StorageDisk::Public,
                    'caption' => $caption,
                    'alt_text' => $caption,
                    'mime_type' => 'image/jpeg',
                    'file_extension' => 'jpg',
                    'file_size' => strlen($jpgBinary),
                    'width' => 1280,
                    'height' => 720,
                    'is_cover' => $index === 1,
                ]
            );
        }
    }

    private function generateSampleJpegBinary(string $title, int $width, int $height, int $variant): string
    {
        $image = imagecreatetruecolor($width, $height);
        $palettes = [
            1 => [15, 32, 66],    // Navy UNIVBI
            2 => [6, 78, 59],     // Deep Emerald
            3 => [88, 28, 135],   // Regal Purple
            4 => [30, 41, 59],    // Slate Dark
        ];
        $rgb = $palettes[$variant % 4] ?? [15, 32, 66];

        $bgColor = imagecolorallocate($image, $rgb[0], $rgb[1], $rgb[2]);
        $white = imagecolorallocate($image, 255, 255, 255);
        $subtext = imagecolorallocate($image, 203, 213, 225);
        $accent = imagecolorallocate($image, 59, 130, 246);

        imagefilledrectangle($image, 0, 0, $width, $height, $bgColor);

        // Header Bar Institusi
        imagefilledrectangle($image, 0, 0, $width, 50, imagecolorallocate($image, max(0, $rgb[0] - 10), max(0, $rgb[1] - 10), max(0, $rgb[2] - 10)));
        imageline($image, 0, 50, $width, 50, $accent);
        imagestring($image, 5, 30, 16, 'UNIVERSITAS BINA INSAN - DOKUMENTASI KEGIATAN KAMPUS RESMI', $white);

        // Frame Border Elegan
        imagerectangle($image, 15, 15, $width - 15, $height - 15, imagecolorallocate($image, min(255, $rgb[0] + 30), min(255, $rgb[1] + 30), min(255, $rgb[2] + 30)));

        // Teks Judul & Resolusi
        imagestring($image, 5, 35, 75, substr($title, 0, 75), $white);
        imagestring($image, 4, 35, 115, 'Dokumentasi Resmi e-Arsip | Resolusi: '.$width.'x'.$height.' px', $subtext);

        ob_start();
        imagejpeg($image, null, 90);
        $binary = (string) ob_get_clean();
        imagedestroy($image);

        return $binary;
    }

    /**
     * @param  array<string, Document>  $documents
     */
    private function seedDummyAssetsAndMovements(array $documents): void
    {
        $unitTi = Unit::where('code', 'TI')->firstOrFail();
        $unitSi = Unit::where('code', 'SI')->firstOrFail();
        $unitIct = Unit::where('code', 'ICT')->firstOrFail();
        $userBauk = User::where('email', 'bauk@univbi.ac.id')->firstOrFail();
        $userIct = User::where('email', 'ict@univbi.ac.id')->firstOrFail();
        $userKaprodiTi = User::where('email', 'kaprodi.ti@univbi.ac.id')->firstOrFail();

        $catKom = AssetCategory::where('code', 'KOM')->firstOrFail();
        $catElk = AssetCategory::where('code', 'ELK')->firstOrFail();
        $catAc = AssetCategory::where('code', 'AC')->firstOrFail();

        $locLab1 = AssetLocation::where('code', 'GD-A-LAB01')->firstOrFail();
        $locDosenFik = AssetLocation::where('code', 'GD-A-R201')->firstOrFail();
        $locGudang = AssetLocation::where('code', 'GD-BAUK-GDG')->firstOrFail();

        $bastDocId = $documents['bast_lab']->id ?? null;
        $batchUuid = (string) Str::uuid();

        // 1. Aset PC Laboratorium Pemrograman 1 (Aktif Dipakai / Baik)
        $pcLab = Asset::updateOrCreate(
            ['asset_code' => 'INV/UNBI/TI/KOM/2026/0001'],
            [
                'batch_uuid' => $batchUuid,
                'sequence_number' => 1,
                'category_id' => $catKom->id,
                'initial_unit_id' => $unitTi->id,
                'current_unit_id' => $unitTi->id,
                'current_location_id' => $locLab1->id,
                'assigned_to_user_id' => $userKaprodiTi->id,
                'name' => 'PC All-in-One Lenovo IdeaCentre AIO 3 24IAP7 Core i7',
                'brand' => 'Lenovo',
                'model_name' => 'IdeaCentre AIO 3 24IAP7',
                'serial_number' => 'SN-LNV-2026-0001',
                'specifications' => 'Intel Core i7-13620H, RAM 16GB DDR5, SSD 512GB NVMe, Layar 23.8" FHD IPS',
                'status' => AssetStatus::InUse,
                'condition' => AssetCondition::Good,
                'ownership_status' => AssetOwnership::Owned,
                'acquisition_year' => 2026,
                'acquisition_date' => '2026-09-15',
                'acquisition_cost' => 14500000,
                'funding_source' => 'Yayasan Bina Insan',
                'vendor_name' => 'PT Bhinneka Mentari Dimensi',
                'warranty_expired_date' => '2028-09-15',
                'next_maintenance_date' => now()->addDays(150)->toDateString(),
                'bast_document_id' => $bastDocId,
                'last_verified_at' => now()->subDays(2),
                'last_verified_by' => $userBauk->id,
                'created_by' => $userBauk->id,
            ]
        );

        AssetMovement::updateOrCreate(
            ['asset_id' => $pcLab->id, 'event_type' => AssetEventType::InitialPlacement],
            [
                'from_unit_id' => null,
                'to_unit_id' => $unitTi->id,
                'from_location_id' => $locGudang->id,
                'to_location_id' => $locLab1->id,
                'to_user_id' => $userKaprodiTi->id,
                'recipient_name_snapshot' => $userKaprodiTi->name,
                'previous_status' => AssetStatus::Available,
                'new_status' => AssetStatus::InUse,
                'previous_condition' => AssetCondition::Good,
                'new_condition' => AssetCondition::Good,
                'reference_document_id' => $bastDocId,
                'notes' => 'Penempatan awal pengadaan PC Laboratorium Pemrograman 1 Prodi S1 Informatika.',
                'recorded_by' => $userBauk->id,
                'event_date' => now()->subDays(10),
                'created_at' => now()->subDays(10),
            ]
        );

        // 2. Aset Proyektor yang Pernah Dimutasi dari TI ke SI (Contoh Tracking Riwayat Mutasi Lintas Prodi)
        $proyektor = Asset::updateOrCreate(
            ['asset_code' => 'INV/UNBI/TI/ELK/2026/0001'],
            [
                'sequence_number' => 1,
                'category_id' => $catElk->id,
                'initial_unit_id' => $unitTi->id,
                'current_unit_id' => $unitSi->id,
                'current_location_id' => $locDosenFik->id,
                'name' => 'Smart Proyektor Epson EB-X500 XGA 3600 Lumens',
                'brand' => 'Epson',
                'model_name' => 'EB-X500',
                'serial_number' => 'SN-EPS-2026-0089',
                'specifications' => '3600 ANSI Lumens, XGA 1024x768, HDMI/VGA, Lamp Life 12.000 Jam',
                'status' => AssetStatus::InUse,
                'condition' => AssetCondition::Good,
                'ownership_status' => AssetOwnership::Owned,
                'acquisition_year' => 2026,
                'acquisition_date' => '2026-08-10',
                'acquisition_cost' => 6850000,
                'funding_source' => 'Yayasan Bina Insan',
                'vendor_name' => 'CV Mitra Teknologi Sriwijaya',
                'warranty_expired_date' => '2028-08-10',
                'bast_document_id' => $bastDocId,
                'created_by' => $userBauk->id,
            ]
        );

        AssetMovement::updateOrCreate(
            ['asset_id' => $proyektor->id, 'event_type' => AssetEventType::InitialPlacement],
            [
                'from_unit_id' => null,
                'to_unit_id' => $unitTi->id,
                'from_location_id' => $locGudang->id,
                'to_location_id' => $locLab1->id,
                'previous_status' => AssetStatus::Available,
                'new_status' => AssetStatus::InUse,
                'previous_condition' => AssetCondition::Good,
                'new_condition' => AssetCondition::Good,
                'reference_document_id' => $bastDocId,
                'notes' => 'Penempatan awal di Laboratorium Pemrograman 1 Prodi S1 Informatika.',
                'recorded_by' => $userBauk->id,
                'event_date' => now()->subDays(20),
                'created_at' => now()->subDays(20),
            ]
        );

        AssetMovement::updateOrCreate(
            ['asset_id' => $proyektor->id, 'event_type' => AssetEventType::TransferUnit],
            [
                'from_unit_id' => $unitTi->id,
                'to_unit_id' => $unitSi->id,
                'from_location_id' => $locLab1->id,
                'to_location_id' => $locDosenFik->id,
                'previous_status' => AssetStatus::InUse,
                'new_status' => AssetStatus::InUse,
                'previous_condition' => AssetCondition::Good,
                'new_condition' => AssetCondition::Good,
                'reference_document_id' => $bastDocId,
                'notes' => 'Mutasi penggunaan ke Prodi S1 Sistem Informasi untuk menunjang sidang skripsi.',
                'recorded_by' => $userBauk->id,
                'event_date' => now()->subDays(4),
                'created_at' => now()->subDays(4),
            ]
        );

        // 3. Aset AC Split yang Sedang Diperbaiki (in_maintenance)
        $acLab = Asset::updateOrCreate(
            ['asset_code' => 'INV/UNBI/TI/AC/2026/0001'],
            [
                'sequence_number' => 1,
                'category_id' => $catAc->id,
                'initial_unit_id' => $unitTi->id,
                'current_unit_id' => $unitTi->id,
                'current_location_id' => $locLab1->id,
                'name' => 'AC Split Inverter Daikin 2 PK Flash Inverter FTKQ50UVM4',
                'brand' => 'Daikin',
                'model_name' => 'FTKQ50UVM4',
                'serial_number' => 'SN-DKN-2026-0412',
                'specifications' => '2 PK (17.100 BTU/h), R-32 Eco Refrigerant, Low Watt Inverter',
                'status' => AssetStatus::InMaintenance,
                'condition' => AssetCondition::MinorDamage,
                'ownership_status' => AssetOwnership::Owned,
                'acquisition_year' => 2026,
                'acquisition_date' => '2026-05-10',
                'acquisition_cost' => 9200000,
                'funding_source' => 'Yayasan Bina Insan',
                'vendor_name' => 'CV Sejuk Teknik Lubuklinggau',
                'maintenances_count' => 1,
                'total_maintenance_cost' => 350000,
                'created_by' => $userBauk->id,
            ]
        );

        AssetMaintenance::updateOrCreate(
            ['asset_id' => $acLab->id, 'reported_by' => $userKaprodiTi->id],
            [
                'unit_id' => $unitTi->id,
                'handled_by' => $userIct->id,
                'maintenance_type' => AssetMaintenanceType::Repair,
                'issue_description' => 'Hembusan udara AC kurang dingin dan indikator lampu timer berkedip.',
                'action_taken' => 'Pembersihan evaporator, pengecekan tekanan freon R-32, dan penggantian kapasitor fan.',
                'vendor_or_technician' => 'CV Sejuk Teknik Lubuklinggau',
                'reported_date' => now()->subDays(2),
                'start_date' => now()->subDay()->toDateString(),
                'cost' => 350000,
                'status' => AssetMaintenanceStatus::InProgress,
                'notes' => 'Sedang dalam proses pengerjaan teknisi.',
            ]
        );
    }
}
