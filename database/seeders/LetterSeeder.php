<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\LetterPriority;
use App\Enums\OutgoingLetterStatus;
use App\Models\AcademicYear;
use App\Models\IncomingLetter;
use App\Models\IncomingLetterRecipient;
use App\Models\OutgoingLetter;
use App\Models\Unit;
use App\Models\User;
use App\Services\LetterManagementService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use setasign\Fpdi\Fpdi;

class LetterSeeder extends Seeder
{
    public function run(): void
    {
        $this->command?->info('📬 Menjalankan LetterSeeder (Surat Masuk & Surat Keluar)...');

        $activeYear = AcademicYear::where('is_active', true)->first();
        if (! $activeYear) {
            $activeYear = AcademicYear::create([
                'name' => '2026/2027 Ganjil',
                'start_date' => '2026-09-01',
                'end_date' => '2027-02-28',
                'is_active' => true,
            ]);
        }

        $humas = User::where('email', 'humas@univbi.ac.id')->firstOrFail();
        $rektor = User::where('email', 'rektor@univbi.ac.id')->firstOrFail();
        $warek1 = User::where('email', 'warek1@univbi.ac.id')->firstOrFail();
        $dekanFik = User::where('email', 'dekan.fik@univbi.ac.id')->firstOrFail();
        $kaprodiTi = User::where('email', 'kaprodi.ti@univbi.ac.id')->firstOrFail();
        $stafTi = User::where('email', 'staf.ti@univbi.ac.id')->firstOrFail();
        $bauk = User::where('email', 'bauk@univbi.ac.id')->firstOrFail();
        $uptIct = User::where('email', 'ict@univbi.ac.id')->firstOrFail();

        $unitRektorat = Unit::where('code', 'REKTORAT')->firstOrFail();
        $unitTi = Unit::where('code', 'TI')->firstOrFail();
        $unitFik = Unit::where('code', 'FIK')->firstOrFail();
        $unitHumas = Unit::where('code', 'HUMAS')->firstOrFail();
        $unitBauk = Unit::where('code', 'BAUK')->firstOrFail();
        $unitIct = Unit::where('code', 'ICT')->firstOrFail();

        $service = app(LetterManagementService::class);

        // =========================================================================
        // A. SURAT MASUK (EKSTERNAL KE KAMPUS)
        // =========================================================================

        // 1. Surat Masuk LLDIKTI (Unit: REKTORAT) -> Sedang berjalan (Tahap 1: Warek 1 unread/dibuka)
        $pathSm1 = 'incoming-letters/2026/11/sm-lldikti-rakor-pimpinan.pdf';
        Storage::disk('private')->put($pathSm1, $this->makeSamplePdf(
            instansi: 'LEMBAGA LAYANAN PENDIDIKAN TINGGI (LLDIKTI) WILAYAH II',
            nomorSurat: 'B/1420/LL2/KP.02/2026',
            perihal: 'Undangan Rapat Koordinasi Pimpinan Perguruan Tinggi Swasta Semester Ganjil 2026/2027',
            isi: "Sehubungan dengan pelaksanaan evaluasi mutu pembelajaran dan akreditasi program studi di lingkungan LLDIKTI Wilayah II, kami mengharapkan kehadiran Bapak/Ibu Pimpinan Perguruan Tinggi pada:\n\nHari/Tanggal : Kamis, 26 November 2026\nWaktu        : 09.00 WIB s.d. Selesai\nTempat       : Grand Ballroom Hotel Novotel Palembang\nAgenda       : Koordinasi Akselerasi Akreditasi Unggul & Kesiapan Borang 2027.\n\nDemikian undangan ini kami sampaikan, atas kehadiran dan kerja sama yang baik diucapkan terima kasih."
        ));

        $sm1 = IncomingLetter::updateOrCreate(
            ['sender_name' => 'LLDIKTI Wilayah II', 'external_letter_number' => 'B/1420/LL2/KP.02/2026'],
            [
                'unit_id' => $unitRektorat->id,
                'academic_year_id' => $activeYear->id,
                'registered_by' => $humas->id,
                'external_letter_date' => now()->subDays(3)->toDateString(),
                'received_date' => now()->subDays(2)->toDateString(),
                'subject' => 'Undangan Rapat Koordinasi Pimpinan Perguruan Tinggi Swasta Semester Ganjil 2026/2027',
                'summary' => 'Undangan koordinasi tatap muka evaluasi mutu perguruan tinggi di Novotel Palembang.',
                'priority' => LetterPriority::Penting,
                'original_file_path' => $pathSm1,
                'file_name' => 'sm-lldikti-rakor-pimpinan.pdf',
            ]
        );

        $sm1->recipients()->delete();
        IncomingLetterRecipient::create([
            'incoming_letter_id' => $sm1->id,
            'step_order' => 1,
            'recipient_user_id' => $warek1->id,
            'recipient_position_snapshot' => $warek1->position_title,
            'instruction_note' => 'Mohon telaah awal dan kesiapan jadwal Bapak Warek I',
        ]);
        IncomingLetterRecipient::create([
            'incoming_letter_id' => $sm1->id,
            'step_order' => 2,
            'recipient_user_id' => $rektor->id,
            'recipient_position_snapshot' => $rektor->position_title,
            'instruction_note' => 'Untuk arahan akhir dan konfirmasi kehadiran Bapak Rektor',
        ]);

        $service->initializeIncomingDistribution($sm1);
        $sm1->refresh();
        // Simulasi: Warek sudah membuka surat
        $service->recordOpened($sm1, $warek1);

        // 2. Surat Masuk Kementerian (Unit: REKTORAT) -> Selesai Dibaca (Completed)
        $pathSm2 = 'incoming-letters/2026/11/sm-kemdiktisaintek-hibah-riset.pdf';
        Storage::disk('private')->put($pathSm2, $this->makeSamplePdf(
            instansi: 'KEMENTERIAN PENDIDIKAN TINGGI, SAINS, DAN TEKNOLOGI REPUBLIK INDONESIA',
            nomorSurat: '0892/E.E1/KS.01/2026',
            perihal: 'Pemberitahuan Penetapan Kuota Hibah Penelitian & Pengabdian Masyarakat 2027',
            isi: "Diberitahukan dengan hormat bahwa berdasarkan hasil seleksi proposal dan rekam jejak luaran perguruan tinggi tahun 2026, Universitas Bina Insan ditetapkan sebagai penerima klaster kuota hibah kompetitif nasional tahun anggaran 2027.\n\nPerguruan tinggi diharapkan segera menyosialisasikan panduan pengusulan hibah kepada seluruh dosen tetap melalui sistem BIMA. Batas akhir penerimaan proposal tahap awal dibuka sampai dengan 15 Januari 2027."
        ));

        $sm2 = IncomingLetter::updateOrCreate(
            ['sender_name' => 'Kementerian Pendidikan Tinggi, Sains, dan Teknologi RI', 'external_letter_number' => '0892/E.E1/KS.01/2026'],
            [
                'unit_id' => $unitRektorat->id,
                'academic_year_id' => $activeYear->id,
                'registered_by' => $humas->id,
                'external_letter_date' => now()->subDays(6)->toDateString(),
                'received_date' => now()->subDays(5)->toDateString(),
                'subject' => 'Pemberitahuan Penetapan Kuota Hibah Penelitian & Pengabdian Masyarakat 2027',
                'summary' => 'Surat resmi kementerian perihal penetapan klaster kuota hibah riset dan PKM nasional tahun 2027.',
                'priority' => LetterPriority::Segera,
                'original_file_path' => $pathSm2,
                'file_name' => 'sm-kemdiktisaintek-hibah-riset.pdf',
            ]
        );

        $sm2->recipients()->delete();
        IncomingLetterRecipient::create([
            'incoming_letter_id' => $sm2->id,
            'step_order' => 1,
            'recipient_user_id' => $warek1->id,
            'recipient_position_snapshot' => $warek1->position_title,
            'instruction_note' => 'Mohon ditelaah bersama LPPM',
        ]);
        IncomingLetterRecipient::create([
            'incoming_letter_id' => $sm2->id,
            'step_order' => 2,
            'recipient_user_id' => $rektor->id,
            'recipient_position_snapshot' => $rektor->position_title,
            'instruction_note' => 'Untuk diketahui dan arahan Bapak Rektor',
        ]);

        $service->initializeIncomingDistribution($sm2);
        $sm2->refresh();

        // Simulasi Warek membaca dan memberikan catatan disposisi
        $service->markAsRead($sm2, $warek1, 'Sudah ditelaah bersama tim LPPM. Skema hibah sangat menguntungkan dosen pemula dan terapan.');
        $sm2->refresh();

        // Simulasi Rektor membaca dan menyelesaikan alur distribusi
        $service->markAsRead($sm2, $rektor, 'Disetujui. Segera instruksikan LPPM untuk mengadakan klinik proposal.');
        $sm2->refresh();

        // 3. Surat Masuk PT Telkom (Unit: TI) -> In Progress (Tahap 1: Kaprodi TI, belum dibaca)
        $pathSm3 = 'incoming-letters/2026/11/sm-telkom-magang-ti.pdf';
        Storage::disk('private')->put($pathSm3, $this->makeSamplePdf(
            instansi: 'PT TELEKOMUNIKASI INDONESIA (PERSERO) TBK - REGIONAL SUMSEL',
            nomorSurat: 'TEL.045/HR.01/REG-1/2026',
            perihal: 'Penawaran Program Beasiswa & Praktik Kerja Industri (Magang Bersertifikat) Mahasiswa TI',
            isi: 'Dalam rangka mendukung link and match antara perguruan tinggi dan industri telekomunikasi digital, Telkom Regional Witel Sumsel membuka kuota magang industri bersertifikat bagi 15 mahasiswa Program Studi S1 Informatika Universitas Bina Insan untuk periode Semester Genap 2026/2027.'
        ));

        $sm3 = IncomingLetter::updateOrCreate(
            ['sender_name' => 'PT Telkom Indonesia Witel Sumsel', 'external_letter_number' => 'TEL.045/HR.01/REG-1/2026'],
            [
                'unit_id' => $unitTi->id,
                'academic_year_id' => $activeYear->id,
                'registered_by' => $humas->id,
                'external_letter_date' => now()->subDay()->toDateString(),
                'received_date' => now()->toDateString(),
                'subject' => 'Penawaran Program Beasiswa & Praktik Kerja Industri Mahasiswa Informatika',
                'summary' => 'Kuota magang bersertifikat Telkom bagi 15 mahasiswa S1 Informatika.',
                'priority' => LetterPriority::Biasa,
                'original_file_path' => $pathSm3,
                'file_name' => 'sm-telkom-magang-ti.pdf',
            ]
        );

        $sm3->recipients()->delete();
        IncomingLetterRecipient::create([
            'incoming_letter_id' => $sm3->id,
            'step_order' => 1,
            'recipient_user_id' => $kaprodiTi->id,
            'recipient_position_snapshot' => $kaprodiTi->position_title,
            'instruction_note' => 'Mohon diseleksi mahasiswa yang memenuhi syarat konversi SKS OBE',
        ]);
        IncomingLetterRecipient::create([
            'incoming_letter_id' => $sm3->id,
            'step_order' => 2,
            'recipient_user_id' => $dekanFik->id,
            'recipient_position_snapshot' => $dekanFik->position_title,
            'instruction_note' => 'Untuk laporan dekanat',
        ]);

        $service->initializeIncomingDistribution($sm3);

        // 4. Surat Masuk KPP Pratama (Unit: BAUK) -> In Progress (Tahap 1: Kepala Biro BAUK)
        $pathSm4 = 'incoming-letters/2026/11/sm-kpp-sosialisasi-pajak.pdf';
        Storage::disk('private')->put($pathSm4, $this->makeSamplePdf(
            instansi: 'KANTOR PELAYANAN PAJAK (KPP) PRATAMA LUBUKLINGGAU',
            nomorSurat: 'S-320/WPJ.03/KP.04/2026',
            perihal: 'Sosialisasi Pembaruan Tarif Pajak Penghasilan (PPh 21) Tenaga Pendidik & Tendik',
            isi: 'Sehubungan dengan implementasi peraturan menteri keuangan terbaru mengenai pemotongan PPh Pasal 21 bagi tenaga pendidik tetap dan tidak tetap, kami mengundang bagian keuangan Universitas Bina Insan untuk hadir dalam sesi pendampingan e-Bupot unifikasi.'
        ));

        $sm4 = IncomingLetter::updateOrCreate(
            ['sender_name' => 'KPP Pratama Lubuklinggau', 'external_letter_number' => 'S-320/WPJ.03/KP.04/2026'],
            [
                'unit_id' => $unitBauk->id,
                'academic_year_id' => $activeYear->id,
                'registered_by' => $humas->id,
                'external_letter_date' => now()->subDays(4)->toDateString(),
                'received_date' => now()->subDays(3)->toDateString(),
                'subject' => 'Sosialisasi Pembaruan Tarif Pajak Penghasilan (PPh 21) Tenaga Pendidik & Tendik',
                'summary' => 'Pendampingan e-Bupot unifikasi pemotongan PPh 21 tenaga kependidikan.',
                'priority' => LetterPriority::Biasa,
                'original_file_path' => $pathSm4,
                'file_name' => 'sm-kpp-sosialisasi-pajak.pdf',
            ]
        );

        $sm4->recipients()->delete();
        IncomingLetterRecipient::create([
            'incoming_letter_id' => $sm4->id,
            'step_order' => 1,
            'recipient_user_id' => $bauk->id,
            'recipient_position_snapshot' => $bauk->position_title,
            'instruction_note' => 'Harap bagian keuangan mengutus staf pajak hadir',
        ]);

        $service->initializeIncomingDistribution($sm4);

        // =========================================================================
        // B. SURAT KELUAR (INTERNAL KE EKSTERNAL VIA HUMAS)
        // =========================================================================

        // 1. Surat Keluar Prodi TI ke Telkomsel -> Disahkan & Terbit Nomor Resmi oleh Humas (Published)
        $pathSk1 = 'outgoing-letters/2026/11/sk-ti-kunjungan-industri-telkomsel.pdf';
        Storage::disk('private')->put($pathSk1, $this->makeSamplePdf(
            instansi: 'UNIVERSITAS BINA INSAN - PROGRAM STUDI S1 INFORMATIKA',
            nomorSurat: '001/UNIVBI.TI/SKL/XI/2026',
            perihal: 'Permohonan Izin Kunjungan Industri Mahasiswa Informatika Semester Ganjil',
            isi: "Kepada Yth.\nBranch Manager PT Telkomsel Branch Lubuklinggau\n\nDalam rangka meningkatkan wawasan mahasiswa mengenai infrastruktur jaringan telekomunikasi dan pusat data, kami bermaksud mengajukan permohonan kunjungan industri bagi 40 mahasiswa Semester V pada:\n\nHari/Tanggal : Rabu, 02 Desember 2026\nWaktu        : 09.30 - 12.00 WIB\n\nBesar harapan kami permohonan ini dapat disetujui. Atas perhatian dan perkenannya kami ucapkan terima kasih."
        ));

        $sk1 = OutgoingLetter::updateOrCreate(
            ['subject' => 'Permohonan Izin Kunjungan Industri Mahasiswa Informatika Semester Ganjil'],
            [
                'unit_id' => $unitTi->id,
                'academic_year_id' => $activeYear->id,
                'created_by' => $stafTi->id,
                'destination_organization' => 'PT Telekomunikasi Selular (Telkomsel) Branch Lubuklinggau',
                'description' => 'Kunjungan industri matakuliah Jaringan Komputer & Cloud Computing.',
                'letter_date' => now()->subDays(4)->toDateString(),
                'original_file_path' => $pathSk1,
                'file_name' => 'sk-ti-kunjungan-industri-telkomsel.pdf',
            ]
        );

        $service->approveOutgoingLetter($sk1, $humas, 'Nomor surat resmi telah diterbitkan oleh Humas.');

        // 2. Surat Keluar UPT ICT ke ICE Institute -> Menunggu Tinjauan Humas (PendingHumas) dalam format Word .docx
        $pathSk2 = 'outgoing-letters/2026/11/sk-ict-integrasi-sso-ice-institute.docx';
        Storage::disk('private')->put($pathSk2, $this->makeSampleDocx(
            title: 'Pengajuan Integrasi Single Sign-On (SSO) Portal Pembelajaran Digital Kampus',
            destination: 'Indonesia Cyber Education (ICE) Institute Jakarta',
            content: 'Sehubungan dengan partisipasi konsorsium perguruan tinggi, UPT ICT Universitas Bina Insan mengajukan permohonan penerbitan kredensial API dan OAuth2 SSO untuk integrasi LMS kampus.'
        ));

        OutgoingLetter::updateOrCreate(
            ['subject' => 'Pengajuan Integrasi Single Sign-On (SSO) Portal Pembelajaran Digital Kampus'],
            [
                'unit_id' => $unitIct->id,
                'academic_year_id' => $activeYear->id,
                'created_by' => $uptIct->id,
                'destination_organization' => 'Indonesia Cyber Education (ICE) Institute Jakarta',
                'description' => 'Integrasi OAuth2 SSO mata kuliah daring konsorsium kampus.',
                'letter_date' => now()->toDateString(),
                'status' => OutgoingLetterStatus::PendingHumas,
                'original_file_path' => $pathSk2,
                'file_name' => 'sk-ict-integrasi-sso-ice-institute.docx',
            ]
        );

        // 3. Surat Keluar FIK ke APTIKOM -> Perlu Revisi dari Humas (Revision / NeedsRevision)
        $pathSk3 = 'outgoing-letters/2026/11/sk-fik-usulan-tuan-rumah-aptikom.pdf';
        Storage::disk('private')->put($pathSk3, $this->makeSamplePdf(
            instansi: 'UNIVERSITAS BINA INSAN - FAKULTAS ILMU KOMPUTER',
            nomorSurat: '[DRAF BELUM DINOMORI]',
            perihal: 'Usulan Tuan Rumah Workshop Kurikulum Akreditasi Internasional 2027',
            isi: "Kepada Yth. Ketua APTIKOM Wilayah II Sumatera Bagian Selatan\n\nFakultas Ilmu Komputer Universitas Bina Insan mengajukan kesediaan menjadi tuan rumah pelaksanaan Workshop Kurikulum OBE dan Akreditasi Internasional ASIIN/IABEE tahun 2027."
        ));

        $sk3 = OutgoingLetter::updateOrCreate(
            ['subject' => 'Usulan Tuan Rumah Workshop Kurikulum Akreditasi Internasional 2027'],
            [
                'unit_id' => $unitFik->id,
                'academic_year_id' => $activeYear->id,
                'created_by' => $dekanFik->id,
                'destination_organization' => 'Asosiasi Pendidikan Tinggi Informatika dan Komputer (APTIKOM) Wilayah II',
                'description' => 'Pengajuan kesiapan tuan rumah workshop kurikulum internasional.',
                'letter_date' => now()->subDays(2)->toDateString(),
                'original_file_path' => $pathSk3,
                'file_name' => 'sk-fik-usulan-tuan-rumah-aptikom.pdf',
            ]
        );

        $service->requestRevisionOutgoing(
            $sk3,
            $humas,
            'Mohon lampirkan estimasi jadwal ketersediaan auditorium utama dan kepanitiaan lokal sebelum nomor surat resmi diterbitkan.'
        );

        // 4. Surat Keluar Bagian Humas ke Pers -> Langsung Diterbitkan oleh Humas (Published)
        $pathSk4 = 'outgoing-letters/2026/11/sk-humas-liputan-wisuda.pdf';
        Storage::disk('private')->put($pathSk4, $this->makeSamplePdf(
            instansi: 'UNIVERSITAS BINA INSAN - BAGIAN HUBUNGAN MASYARAKAT (HUMAS)',
            nomorSurat: '001/UNIVBI.HUMAS/SKL/XI/2026',
            perihal: 'Undangan Konferensi Pers & Liputan Media Wisuda Sarjana Universitas Bina Insan',
            isi: "Kepada Yth. Pimpinan Redaksi Media Cetak & Siber Sumatera Selatan\n\nBagian Humas mengundang rekan-rekan jurnalis media cetak, televisi, dan siber untuk meliput rangkaian Sidang Senat Terbuka Wisuda Sarjana Ke-XX yang akan dilaksanakan di Gedung Serbaguna Kampus."
        ));

        $sk4 = OutgoingLetter::updateOrCreate(
            ['subject' => 'Undangan Konferensi Pers & Liputan Media Wisuda Sarjana Universitas Bina Insan'],
            [
                'unit_id' => $unitHumas->id,
                'academic_year_id' => $activeYear->id,
                'created_by' => $humas->id,
                'destination_organization' => 'Redaksi Harian Linggau Pos & Media Siber Sumatera Selatan',
                'description' => 'Undangan peliputan media massa untuk wisuda akbar sarjana.',
                'letter_date' => now()->toDateString(),
                'original_file_path' => $pathSk4,
                'file_name' => 'sk-humas-liputan-wisuda.pdf',
            ]
        );

        $service->processNewOutgoingLetter($sk4, $humas);

        $this->command?->info('✅ LetterSeeder berhasil diselesaikan: 4 Surat Masuk & 4 Surat Keluar dengan file berstempel QR.');
    }

    private function makeSamplePdf(string $instansi, string $nomorSurat, string $perihal, string $isi): string
    {
        $pdf = new Fpdi;
        $pdf->SetAutoPageBreak(true, 15);
        $pdf->AddPage('P', [210, 297]);

        // Header Instansi
        $pdf->SetFont('Helvetica', 'B', 12);
        $pdf->SetTextColor(15, 23, 42);
        $pdf->Cell(0, 7, $this->sanitizeText($instansi), 0, 1, 'C');
        $pdf->SetFont('Helvetica', '', 8);
        $pdf->SetTextColor(71, 85, 105);
        $pdf->Cell(0, 5, 'KOTA LUBUKLINGGAU, SUMATERA SELATAN - INDONESIA', 0, 1, 'C');

        // Garis Kop
        $pdf->SetDrawColor(15, 23, 42);
        $pdf->SetLineWidth(0.6);
        $pdf->Line(15, 24, 195, 24);
        $pdf->SetLineWidth(0.2);
        $pdf->Line(15, 25, 195, 25);
        $pdf->Ln(10);

        // Nomor & Perihal
        $pdf->SetFont('Helvetica', 'B', 9);
        $pdf->SetTextColor(15, 23, 42);
        $pdf->Cell(28, 6, 'Nomor Surat :', 0, 0);
        $pdf->SetFont('Helvetica', '', 9);
        $pdf->Cell(0, 6, $this->sanitizeText($nomorSurat), 0, 1);

        $pdf->SetFont('Helvetica', 'B', 9);
        $pdf->Cell(28, 6, 'Perihal         :', 0, 0);
        $pdf->SetFont('Helvetica', 'B', 9);
        $pdf->MultiCell(0, 6, $this->sanitizeText($perihal), 0, 'L');
        $pdf->Ln(4);

        // Isi Surat
        $pdf->SetFont('Helvetica', '', 9.5);
        $pdf->SetTextColor(30, 41, 59);
        $pdf->MultiCell(0, 6, $this->sanitizeText($isi), 0, 'J');
        $pdf->Ln(10);

        // Penutup
        $pdf->SetFont('Helvetica', 'I', 8.5);
        $pdf->SetTextColor(100, 116, 139);
        $pdf->Cell(0, 5, 'Dokumen ini dibuat otomatis oleh Sistem Manajemen Surat & E-Arsip Universitas Bina Insan.', 0, 1, 'L');

        return (string) $pdf->Output('S');
    }

    private function makeSampleDocx(string $title, string $destination, string $content): string
    {
        $phpWord = new PhpWord;
        $section = $phpWord->addSection();

        $section->addText('UNIVERSITAS BINA INSAN', ['bold' => true, 'size' => 14]);
        $section->addText('SURAT DINAS KELUAR UNIT KERJA', ['bold' => true, 'size' => 11, 'color' => '1e3a8a']);
        $section->addTextBreak(1);

        $section->addText('Nomor Surat : ${NOMOR_SURAT}', ['bold' => true]);
        $section->addText('Tanggal     : ${TANGGAL_SAH}');
        $section->addText('Tujuan      : '.$destination);
        $section->addText('Perihal     : '.$title, ['bold' => true]);
        $section->addTextBreak(1);

        $section->addText($content, ['size' => 10]);
        $section->addTextBreak(2);

        $section->addText('Hormat kami,');
        $section->addText('Pimpinan Unit Pengaju');

        $tempFile = tempnam(sys_get_temp_dir(), 'ltr_docx_');
        $writer = IOFactory::createWriter($phpWord, 'Word2007');
        $writer->save($tempFile);
        $docxBinary = (string) file_get_contents($tempFile);
        @unlink($tempFile);

        return $docxBinary;
    }

    private function sanitizeText(string $text): string
    {
        $converted = @iconv('UTF-8', 'ISO-8859-1//TRANSLIT', $text);

        return $converted !== false ? $converted : $text;
    }
}
