<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Models\DocumentCategory;
use App\Models\Unit;
use App\Services\DocumentNamingService;
use Tests\TestCase;

class DocumentNamingServiceTest extends TestCase
{
    private DocumentNamingService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new DocumentNamingService;
    }

    public function test_generates_sharded_directory_path(): void
    {
        $dir = $this->service->generateDirectory('TI', 2026, 11);
        $this->assertSame('documents/2026/11/TI', $dir);

        $dirSingleDigitMonth = $this->service->generateDirectory('FIK', 2026, 5);
        $this->assertSame('documents/2026/05/FIK', $dirSingleDigitMonth);

        $dirFallback = $this->service->generateDirectory(null, 2026, 11);
        $this->assertSame('documents/2026/11/UMUM', $dirFallback);
    }

    public function test_generates_standard_file_name(): void
    {
        $fileName = $this->service->generateStandardFileName(
            'Surat Keputusan Pengangkatan Dosen Pembimbing Skripsi',
            'TI',
            'SK',
            'pdf',
            2026
        );

        $this->assertSame('2026-TI-SK-surat-keputusan-pengangkatan-dosen-pembimbing-skripsi.pdf', $fileName);
    }

    public function test_generates_standard_file_name_with_docx_extension(): void
    {
        $fileName = $this->service->generateStandardFileName(
            'Format Berita Acara Rapat',
            'BAAK',
            'BA',
            '.DOCX',
            2026
        );

        $this->assertSame('2026-BAAK-BA-format-berita-acara-rapat.docx', $fileName);
    }

    public function test_handles_empty_title_and_special_characters(): void
    {
        $fileName = $this->service->generateStandardFileName(
            '   ',
            'LPPM',
            'SURAT',
            'pdf',
            2026
        );

        $this->assertSame('2026-LPPM-SURAT-dokumen-arsip.pdf', $fileName);

        $fileNameSpecial = $this->service->generateStandardFileName(
            'Laporan Keuangan & Audit 100% Valid!',
            'BAUK',
            'KEU',
            'pdf',
            2026
        );

        $this->assertSame('2026-BAUK-KEU-laporan-keuangan-audit-100-valid.pdf', $fileNameSpecial);
    }

    public function test_generates_combined_full_storage_path(): void
    {
        $fullPath = $this->service->generateStoragePath(
            'Surat Tugas Asesor Borang',
            'LPM',
            'ST',
            'pdf',
            2026,
            11
        );

        $this->assertSame('documents/2026/11/LPM/2026-LPM-ST-surat-tugas-asesor-borang.pdf', $fullPath);
    }

    public function test_resolves_code_from_model_instances(): void
    {
        $unit = new Unit(['code' => 'si']);
        $category = new DocumentCategory(['code' => 'edaran']);

        $fileName = $this->service->generateStandardFileName(
            'Surat Edaran Ujian',
            $unit,
            $category,
            'pdf',
            2026
        );

        $this->assertSame('2026-SI-EDARAN-surat-edaran-ujian.pdf', $fileName);
    }
}
