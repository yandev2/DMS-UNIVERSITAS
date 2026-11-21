<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\DocumentStatus;
use App\Enums\VisibilityLevel;
use App\Models\Document;
use App\Services\Concerns\BuildsStyledSpreadsheet;
use App\Support\SpreadsheetSanitizer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class DocumentExportService
{
    use BuildsStyledSpreadsheet;

    /**
     * Membangun workbook Excel rekapitulasi dokumen arsip beserta sheet ringkasan eksekutif
     * langsung dari query Eloquent menggunakan `chunkById` agar hemat memori.
     *
     * @param  array{
     *     period_label?: string,
     *     filter_summary?: string,
     *     requester_name?: string
     * }  $metadata
     */
    public function buildSpreadsheetFromQuery(Builder $query, array $metadata = [], int &$exportedCount = 0): Spreadsheet
    {
        @ini_set('memory_limit', '512M');
        @set_time_limit(0);

        $spreadsheet = new Spreadsheet;
        $spreadsheet->getDefaultStyle()->getFont()->setName('Calibri')->setSize(10)->getColor()->setARGB('FF1E293B');

        $spreadsheet->getProperties()
            ->setCreator('Universitas Bina Insan - E-Arsip')
            ->setLastModifiedBy((string) ($metadata['requester_name'] ?? 'Sistem E-Arsip UNIVBI'))
            ->setTitle('Rekapitulasi Dokumen Arsip & Borang')
            ->setSubject('Laporan Rekapitulasi Naskah Dinas Universitas Bina Insan')
            ->setDescription('Laporan ekspor terfilter naskah dinas & arsip dokumen Universitas Bina Insan.');

        $mainSheet = $spreadsheet->getActiveSheet();
        $columns = $this->initializeMainSheet($mainSheet, $metadata);

        $startDataRow = 7;
        $currentRow = $startDataRow;
        $counter = 1;
        $totalDocuments = 0;
        $withFileCount = 0;
        $stampedFileCount = 0;
        $borangCount = 0;
        $statusStats = [];
        $unitStats = [];

        foreach (DocumentStatus::cases() as $statusCase) {
            $statusStats[$statusCase->value] = [
                'label' => $this->resolveStatusShortLabel($statusCase),
                'count' => 0,
                'colors' => $this->resolveStatusBadgeColors($statusCase),
            ];
        }

        (clone $query)
            ->with([
                'unit:id,name,code',
                'category:id,name',
                'academicYear:id,name',
                'uploader:id,name',
                'finalApprover:id,name',
                'replacedDocument:id,document_number,title',
                'primaryFile',
                'accreditationCriteria:id,code',
                'approvals',
            ])
            ->chunkById(250, function (Collection $chunk) use (
                $mainSheet,
                $columns,
                &$currentRow,
                &$counter,
                &$totalDocuments,
                &$withFileCount,
                &$stampedFileCount,
                &$borangCount,
                &$statusStats,
                &$unitStats
            ): void {
                $this->appendBatchToMainSheet(
                    $mainSheet,
                    $chunk,
                    $columns,
                    $currentRow,
                    $counter,
                    $totalDocuments,
                    $withFileCount,
                    $stampedFileCount,
                    $borangCount,
                    $statusStats,
                    $unitStats
                );
            }, 'documents.id', 'id');

        $exportedCount = $totalDocuments;
        $this->finalizeMainSheet($mainSheet, $startDataRow, $currentRow, $totalDocuments, $withFileCount, $borangCount);

        $summarySheet = $spreadsheet->createSheet();
        $this->buildSummarySheet($summarySheet, $metadata, $totalDocuments, $withFileCount, $stampedFileCount, $borangCount, $statusStats, $unitStats);

        $spreadsheet->setActiveSheetIndex(0);

        return $spreadsheet;
    }

    /**
     * Menghasilkan dan menyimpan berkas Excel (.xlsx) ke path absolut di penyimpanan privat.
     *
     * @param  array{
     *     period_label?: string,
     *     filter_summary?: string,
     *     requester_name?: string
     * }  $metadata
     */
    public function saveSpreadsheetFromQueryToFile(Builder $query, string $absolutePath, array $metadata = []): int
    {
        $exportedCount = 0;
        $spreadsheet = $this->buildSpreadsheetFromQuery($query, $metadata, $exportedCount);

        $writer = new Xlsx($spreadsheet);
        $writer->save($absolutePath);

        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet, $writer);

        return $exportedCount;
    }

    /**
     * Inisialisasi header institusional, kartu metadata filter, dan 18 kolom tabel pada Sheet 1.
     *
     * @param  array{
     *     period_label?: string,
     *     filter_summary?: string,
     *     requester_name?: string
     * }  $metadata
     * @return array<string, array{0: string, 1: int}>
     */
    private function initializeMainSheet(Worksheet $sheet, array $metadata): array
    {
        $sheet->setTitle('Rekapitulasi Dokumen');
        $sheet->setShowGridlines(true);

        $periodLabel = (string) ($metadata['period_label'] ?? '-');
        $filterSummary = (string) ($metadata['filter_summary'] ?? 'Semua Filter Opsional');
        $requesterName = (string) ($metadata['requester_name'] ?? 'Sistem E-Arsip');
        $downloadTime = now()->translatedFormat('d F Y, H:i').' WIB';

        $sheet->mergeCells('A1:R1');
        $sheet->setCellValue('A1', 'UNIVERSITAS BINA INSAN (UNIVBI)');
        $sheet->getStyle('A1:R1')->applyFromArray([
            'font' => ['bold' => true, 'size' => 14, 'color' => ['argb' => 'FFFFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF1E3A8A']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER, 'indent' => 1],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(28);

        $sheet->mergeCells('A2:R2');
        $sheet->setCellValue('A2', 'REKAPITULASI DOKUMEN ARSIP & BORANG NASKAH DINAS');
        $sheet->getStyle('A2:R2')->applyFromArray([
            'font' => ['bold' => true, 'size' => 11, 'color' => ['argb' => 'FF1E3A8A']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFEFF6FF']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER, 'indent' => 1],
            'borders' => ['bottom' => ['borderStyle' => Border::BORDER_MEDIUM, 'color' => ['argb' => 'FF1E3A8A']]],
        ]);
        $sheet->getRowDimension(2)->setRowHeight(22);

        $this->renderMetadataPair($sheet, 'A3:B3', 'C3:F3', 'Periode Terbit', $periodLabel);
        $this->renderMetadataPair($sheet, 'G3:H3', 'I3:M3', 'Filter Aktif', $filterSummary);
        $this->renderMetadataPair($sheet, 'N3:O3', 'P3:R3', 'Diminta Oleh', $requesterName);

        $this->renderMetadataPair($sheet, 'A4:B4', 'C4:F4', 'Waktu Ekspor', $downloadTime);
        $this->renderMetadataPair($sheet, 'G4:H4', 'I4:M4', 'Total Dokumen', '0 Naskah');
        $this->renderMetadataPair($sheet, 'N4:O4', 'P4:R4', 'Kelengkapan Berkas', '0/0 Berkas (0%)');

        $sheet->getRowDimension(3)->setRowHeight(20);
        $sheet->getRowDimension(4)->setRowHeight(20);
        $sheet->getRowDimension(5)->setRowHeight(8);

        $columns = [
            'A' => ['No', 6],
            'B' => ['Nomor Surat', 28],
            'C' => ['Judul Naskah Dinas', 40],
            'D' => ['Kategori Dokumen', 24],
            'E' => ['Unit Kerja / Prodi', 26],
            'F' => ['Tahun Akademik', 16],
            'G' => ['Tanggal Terbit', 15],
            'H' => ['Status Dokumen', 19],
            'I' => ['Tahap Workflow', 24],
            'J' => ['Aksesibilitas', 14],
            'K' => ['Masa Berlaku', 18],
            'L' => ['TTD Basah', 20],
            'M' => ['Bukti Borang', 20],
            'N' => ['Pejabat Pengesah', 26],
            'O' => ['Pengunggah', 22],
            'P' => ['Rantai Dokumen', 26],
            'Q' => ['Berkas Fisik', 22],
            'R' => ['Tautan Verifikasi', 19],
        ];

        foreach ($columns as $colLetter => [$label, $width]) {
            $sheet->setCellValue("{$colLetter}6", $label);
            $sheet->getColumnDimension($colLetter)->setWidth($width);
        }

        $sheet->getStyle('A6:R6')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF'], 'size' => 10],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF1E293B']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FF334155']]],
        ]);
        $sheet->getRowDimension(6)->setRowHeight(28);

        return $columns;
    }

    /**
     * Menuliskan satu batch dokumen ke Sheet 1 serta memperbarui agregasi statistik Sheet 2.
     *
     * @param  iterable<int, Document>  $documents
     * @param  array<string, array{0: string, 1: int}>  $columns
     * @param  array<string, array{label: string, count: int, colors: array{0: string, 1: string}}>  $statusStats
     * @param  array<int, array{code: string, name: string, total: int, approved: int, borang: int, with_file: int}>  $unitStats
     */
    private function appendBatchToMainSheet(
        Worksheet $sheet,
        iterable $documents,
        array $columns,
        int &$currentRow,
        int &$counter,
        int &$totalDocuments,
        int &$withFileCount,
        int &$stampedFileCount,
        int &$borangCount,
        array &$statusStats,
        array &$unitStats
    ): void {
        foreach ($documents as $document) {
            $totalDocuments++;

            $primaryFile = $document->primaryFile;
            $hasFile = $primaryFile !== null;
            $hasStamped = $hasFile && filled($primaryFile->stamped_file_path);

            if ($hasFile) {
                $withFileCount++;
            }
            if ($hasStamped) {
                $stampedFileCount++;
            }
            if ($document->is_borang) {
                $borangCount++;
            }

            $statusEnum = $document->status instanceof DocumentStatus
                ? $document->status
                : DocumentStatus::tryFrom((string) $document->status);

            if ($statusEnum && isset($statusStats[$statusEnum->value])) {
                $statusStats[$statusEnum->value]['count']++;
            }

            $unitId = (int) ($document->unit_id ?? 0);
            if ($unitId > 0) {
                if (! isset($unitStats[$unitId])) {
                    $unitStats[$unitId] = [
                        'code' => (string) ($document->unit?->code ?? '-'),
                        'name' => (string) ($document->unit?->name ?? 'Unit Tidak Diketahui'),
                        'total' => 0,
                        'approved' => 0,
                        'borang' => 0,
                        'with_file' => 0,
                    ];
                }
                $unitStats[$unitId]['total']++;
                if ($statusEnum === DocumentStatus::Approved) {
                    $unitStats[$unitId]['approved']++;
                }
                if ($document->is_borang) {
                    $unitStats[$unitId]['borang']++;
                }
                if ($hasFile) {
                    $unitStats[$unitId]['with_file']++;
                }
            }

            $docNumber = filled($document->document_number)
                ? (string) $document->document_number
                : ($document->is_manual_number ? '-' : '[Otomatis]');

            $title = (string) $document->title;
            $categoryName = $document->category?->name ?? '-';
            $unitName = $document->unit
                ? (filled($document->unit->code) ? "{$document->unit->name} ({$document->unit->code})" : $document->unit->name)
                : '-';
            $academicYearName = $document->academicYear?->name ?? '-';
            $issuedDateText = $document->issued_date ? $document->issued_date->format('d/m/Y') : '-';

            $statusLabel = $statusEnum ? $this->resolveStatusShortLabel($statusEnum) : '-';
            [$statusTextColor, $statusFillColor] = $statusEnum
                ? $this->resolveStatusBadgeColors($statusEnum)
                : ['FF334155', 'FFF1F5F9'];

            $workflowStep = $document->getCurrentStepLabel();

            $visibilityEnum = $document->visibility instanceof VisibilityLevel
                ? $document->visibility
                : VisibilityLevel::tryFrom((string) $document->visibility);
            $visibilityLabel = match ($visibilityEnum) {
                VisibilityLevel::Public => 'Publik',
                VisibilityLevel::Internal => 'Internal',
                VisibilityLevel::Confidential => 'Rahasia',
                default => '-',
            };
            [$visTextColor, $visFillColor] = match ($visibilityEnum) {
                VisibilityLevel::Public => ['FF15803D', 'FFDCFCE7'],
                VisibilityLevel::Internal => ['FF1D4ED8', 'FFDBEAFE'],
                VisibilityLevel::Confidential => ['FFB91C1C', 'FFFEE2E2'],
                default => ['FF334155', 'FFF1F5F9'],
            };

            $expiryText = match (true) {
                ! $document->has_expiry => 'Permanen',
                ! $document->expired_date => 'Belum Diatur',
                $document->expired_date->isPast() => $document->expired_date->format('d/m/Y')."\n(Kedaluwarsa)",
                default => $document->expired_date->format('d/m/Y'),
            };

            $wetSignatureText = match (true) {
                ! $document->requires_wet_signature => 'Tidak',
                $document->final_scan_uploaded_at !== null => "Ya\n(Scan: ".$document->final_scan_uploaded_at->format('d/m/Y').')',
                default => "Ya\n(Menunggu Scan)",
            };

            if ($document->is_borang) {
                $criteriaCodes = $document->accreditationCriteria
                    ->pluck('code')
                    ->filter()
                    ->unique()
                    ->implode(', ');
                $borangText = $criteriaCodes !== '' ? "Ya\n({$criteriaCodes})" : 'Ya';
            } else {
                $borangText = 'Tidak';
            }

            $signerParts = array_filter([
                $document->signer_name_snapshot ?: $document->finalApprover?->name,
                $document->signer_position_snapshot,
                $document->approved_at ? 'Sah: '.$document->approved_at->format('d/m/Y H:i') : null,
            ]);
            $signerText = $signerParts !== [] ? implode("\n", $signerParts) : '-';

            $uploaderName = $document->uploader?->name ?? '-';

            $replaced = $document->replacedDocument;
            $replacesText = '-';
            if ($replaced) {
                $replacesText = filled($replaced->document_number)
                    ? "[{$replaced->document_number}]\n{$replaced->title}"
                    : (string) $replaced->title;
            }

            if ($hasFile) {
                $ext = strtoupper((string) ($primaryFile->file_extension ?: 'FILE'));
                $size = $this->formatFileSize($primaryFile->file_size);
                $stampNote = $hasStamped ? 'Resmi (QR)' : 'Asli';
                $fileInfoText = "{$ext} • {$size}\n{$stampNote}";
            } else {
                $fileInfoText = 'Belum Ada Berkas';
            }

            $verifyUrl = $document->getVerificationUrl();

            $sheet->setCellValue("A{$currentRow}", $counter);
            SpreadsheetSanitizer::setSafeStringCell($sheet, "B{$currentRow}", $docNumber);
            SpreadsheetSanitizer::setSafeStringCell($sheet, "C{$currentRow}", $title);
            SpreadsheetSanitizer::setSafeStringCell($sheet, "D{$currentRow}", $categoryName);
            SpreadsheetSanitizer::setSafeStringCell($sheet, "E{$currentRow}", $unitName);
            SpreadsheetSanitizer::setSafeStringCell($sheet, "F{$currentRow}", $academicYearName);
            SpreadsheetSanitizer::setSafeStringCell($sheet, "G{$currentRow}", $issuedDateText);
            SpreadsheetSanitizer::setSafeStringCell($sheet, "H{$currentRow}", $statusLabel);
            SpreadsheetSanitizer::setSafeStringCell($sheet, "I{$currentRow}", $workflowStep);
            SpreadsheetSanitizer::setSafeStringCell($sheet, "J{$currentRow}", $visibilityLabel);
            SpreadsheetSanitizer::setSafeStringCell($sheet, "K{$currentRow}", $expiryText);
            SpreadsheetSanitizer::setSafeStringCell($sheet, "L{$currentRow}", $wetSignatureText);
            SpreadsheetSanitizer::setSafeStringCell($sheet, "M{$currentRow}", $borangText);
            SpreadsheetSanitizer::setSafeStringCell($sheet, "N{$currentRow}", $signerText);
            SpreadsheetSanitizer::setSafeStringCell($sheet, "O{$currentRow}", $uploaderName);
            SpreadsheetSanitizer::setSafeStringCell($sheet, "P{$currentRow}", $replacesText);
            SpreadsheetSanitizer::setSafeStringCell($sheet, "Q{$currentRow}", $fileInfoText);
            SpreadsheetSanitizer::setSafeHyperlinkCell($sheet, "R{$currentRow}", $verifyUrl, 'Verifikasi QR ↗');

            $isEven = ($counter % 2 === 0);
            $rowBgColor = $isEven ? 'FFF8FAFC' : 'FFFFFFFF';

            $sheet->getStyle("A{$currentRow}:R{$currentRow}")->applyFromArray([
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => $rowBgColor]],
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FFE2E8F0']]],
                'alignment' => ['vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
            ]);

            $sheet->getStyle("A{$currentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("B{$currentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
            $sheet->getStyle("B{$currentRow}")->getFont()->setName('Consolas')->setSize(9)->setBold(true);
            $sheet->getStyle("C{$currentRow}:E{$currentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
            $sheet->getStyle("F{$currentRow}:G{$currentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

            $sheet->getStyle("H{$currentRow}")->applyFromArray([
                'font' => ['bold' => true, 'size' => 9, 'color' => ['argb' => $statusTextColor]],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => $statusFillColor]],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            ]);

            $sheet->getStyle("I{$currentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);

            $sheet->getStyle("J{$currentRow}")->applyFromArray([
                'font' => ['bold' => true, 'size' => 9, 'color' => ['argb' => $visTextColor]],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => $visFillColor]],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            ]);

            $sheet->getStyle("K{$currentRow}:M{$currentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("N{$currentRow}:P{$currentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);

            $sheet->getStyle("Q{$currentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            if (! $hasFile) {
                $sheet->getStyle("Q{$currentRow}")->getFont()->setBold(true)->setSize(9)->getColor()->setARGB('FFDC2626');
            } else {
                $sheet->getStyle("Q{$currentRow}")->getFont()->setSize(9)->getColor()->setARGB('FF334155');
            }

            $sheet->getStyle("R{$currentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("R{$currentRow}")->getFont()->setBold(true)->setUnderline(true)->getColor()->setARGB('FF2563EB');

            $rowHeight = $this->calculateRowHeight([
                [$docNumber, $columns['B'][1]],
                [$title, $columns['C'][1]],
                [$categoryName, $columns['D'][1]],
                [$unitName, $columns['E'][1]],
                [$workflowStep, $columns['I'][1]],
                [$expiryText, $columns['K'][1]],
                [$wetSignatureText, $columns['L'][1]],
                [$borangText, $columns['M'][1]],
                [$signerText, $columns['N'][1]],
                [$replacesText, $columns['P'][1]],
                [$fileInfoText, $columns['Q'][1]],
            ]);
            $sheet->getRowDimension($currentRow)->setRowHeight($rowHeight);

            $currentRow++;
            $counter++;
        }
    }

    /**
     * Mengisi ringkasan akhir di bagian metadata atas dan baris total bawah Sheet 1.
     */
    private function finalizeMainSheet(
        Worksheet $sheet,
        int $startDataRow,
        int $currentRow,
        int $totalDocuments,
        int $withFileCount,
        int $borangCount
    ): void {
        $completenessPct = $totalDocuments > 0 ? (int) round(($withFileCount / $totalDocuments) * 100) : 0;

        $sheet->setCellValue('I4', "{$totalDocuments} Naskah ({$borangCount} Bukti Borang)");
        $sheet->setCellValue('P4', "{$withFileCount}/{$totalDocuments} Berkas ({$completenessPct}%)");

        if ($totalDocuments === 0) {
            $sheet->mergeCells("A{$currentRow}:R{$currentRow}");
            $sheet->setCellValue("A{$currentRow}", 'Tidak ada dokumen arsip yang sesuai dengan filter ekspor.');
            $sheet->getStyle("A{$currentRow}:R{$currentRow}")->applyFromArray([
                'font' => ['italic' => true, 'color' => ['argb' => 'FF64748B']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FFE2E8F0']]],
            ]);
            $sheet->getRowDimension($currentRow)->setRowHeight(32);
        } else {
            $lastDataRow = $currentRow - 1;
            $summaryRow = $currentRow;

            $sheet->mergeCells("A{$summaryRow}:B{$summaryRow}");
            $sheet->setCellValue("A{$summaryRow}", "TOTAL ({$totalDocuments} Dokumen)");
            $sheet->setCellValue("C{$summaryRow}", "=COUNTA(C{$startDataRow}:C{$lastDataRow})");
            $sheet->setCellValue("M{$summaryRow}", "=COUNTIF(M{$startDataRow}:M{$lastDataRow},\"Ya*\")");
            $sheet->setCellValue("Q{$summaryRow}", "=COUNTIF(Q{$startDataRow}:Q{$lastDataRow},\"*•*\")");

            $sheet->getStyle("A{$summaryRow}:R{$summaryRow}")->applyFromArray([
                'font' => ['bold' => true, 'size' => 10, 'color' => ['argb' => 'FF0F172A']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFF1F5F9']],
                'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
                'borders' => [
                    'top' => ['borderStyle' => Border::BORDER_MEDIUM, 'color' => ['argb' => 'FF1E293B']],
                    'bottom' => ['borderStyle' => Border::BORDER_MEDIUM, 'color' => ['argb' => 'FF1E293B']],
                    'allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FFCBD5E1']],
                ],
            ]);

            $sheet->getStyle("A{$summaryRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT)->setIndent(1);
            $sheet->getStyle("C{$summaryRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
            $sheet->getStyle("C{$summaryRow}")->getNumberFormat()->setFormatCode('0" Naskah"');
            $sheet->getStyle("M{$summaryRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("M{$summaryRow}")->getNumberFormat()->setFormatCode('0" Borang"');
            $sheet->getStyle("Q{$summaryRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("Q{$summaryRow}")->getNumberFormat()->setFormatCode('0" Berkas Siap"');
            $sheet->getRowDimension($summaryRow)->setRowHeight(24);

            $sheet->getStyle("A6:R{$lastDataRow}")->getBorders()->getOutline()->setBorderStyle(Border::BORDER_MEDIUM)->getColor()->setARGB('FF1E293B');
            $sheet->setAutoFilter("A6:R{$lastDataRow}");
        }

        $sheet->freezePane('A7');
        $this->configurePrintSetup($sheet, repeatStartRow: 6, repeatEndRow: 6);
    }

    /**
     * Membangun Sheet 2 berisi Ringkasan Eksekutif per Status dan per Unit Kerja / Program Studi.
     *
     * @param  array{
     *     period_label?: string,
     *     filter_summary?: string,
     *     requester_name?: string
     * }  $metadata
     * @param  array<string, array{label: string, count: int, colors: array{0: string, 1: string}}>  $statusStats
     * @param  array<int, array{code: string, name: string, total: int, approved: int, borang: int, with_file: int}>  $unitStats
     */
    private function buildSummarySheet(
        Worksheet $sheet,
        array $metadata,
        int $totalDocuments,
        int $withFileCount,
        int $stampedFileCount,
        int $borangCount,
        array $statusStats,
        array $unitStats
    ): void {
        $sheet->setTitle('Ringkasan Rekapitulasi');
        $sheet->setShowGridlines(true);

        $columnWidths = [
            'A' => 16,
            'B' => 38,
            'C' => 16,
            'D' => 16,
            'E' => 16,
            'F' => 18,
            'G' => 18,
        ];

        foreach ($columnWidths as $col => $width) {
            $sheet->getColumnDimension($col)->setWidth($width);
        }

        $sheet->mergeCells('A1:G1');
        $sheet->setCellValue('A1', 'RINGKASAN EKSEKUTIF REKAPITULASI DOKUMEN ARSIP');
        $sheet->getStyle('A1:G1')->applyFromArray([
            'font' => ['bold' => true, 'size' => 13, 'color' => ['argb' => 'FFFFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF1E3A8A']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER, 'indent' => 1],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(26);

        $periodLabel = (string) ($metadata['period_label'] ?? '-');
        $filterSummary = (string) ($metadata['filter_summary'] ?? 'Semua Filter Opsional');
        $infoLine = "Periode Terbit: {$periodLabel} | Filter: {$filterSummary} | Berkas QR Resmi: {$stampedFileCount}/{$withFileCount} | Bukti Borang: {$borangCount}";

        $sheet->mergeCells('A2:G2');
        SpreadsheetSanitizer::setSafeStringCell($sheet, 'A2', $infoLine);
        $sheet->getStyle('A2:G2')->applyFromArray([
            'font' => ['size' => 9, 'italic' => true, 'color' => ['argb' => 'FF1E3A8A']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFEFF6FF']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER, 'indent' => 1],
            'borders' => ['bottom' => ['borderStyle' => Border::BORDER_MEDIUM, 'color' => ['argb' => 'FF1E3A8A']]],
        ]);
        $sheet->getRowDimension(2)->setRowHeight(20);
        $sheet->getRowDimension(3)->setRowHeight(10);

        // Bagian A: Statistik per Status Dokumen
        $rowIdx = 4;
        $sheet->mergeCells("A{$rowIdx}:G{$rowIdx}");
        $sheet->setCellValue("A{$rowIdx}", 'A. REKAPITULASI BERDASARKAN STATUS DOKUMEN');
        $sheet->getStyle("A{$rowIdx}:G{$rowIdx}")->applyFromArray([
            'font' => ['bold' => true, 'size' => 10, 'color' => ['argb' => 'FF0F172A']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFE2E8F0']],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER, 'indent' => 1],
        ]);
        $sheet->getRowDimension($rowIdx)->setRowHeight(22);

        $rowIdx++;
        $sheet->setCellValue("A{$rowIdx}", 'Kode');
        $sheet->mergeCells("B{$rowIdx}:C{$rowIdx}");
        $sheet->setCellValue("B{$rowIdx}", 'Status Dokumen');
        $sheet->mergeCells("D{$rowIdx}:E{$rowIdx}");
        $sheet->setCellValue("D{$rowIdx}", 'Jumlah Naskah');
        $sheet->mergeCells("F{$rowIdx}:G{$rowIdx}");
        $sheet->setCellValue("F{$rowIdx}", 'Persentase');

        $sheet->getStyle("A{$rowIdx}:G{$rowIdx}")->applyFromArray([
            'font' => ['bold' => true, 'size' => 10, 'color' => ['argb' => 'FFFFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF1E293B']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FF334155']]],
        ]);
        $sheet->getRowDimension($rowIdx)->setRowHeight(24);

        $rowIdx++;
        $statusStartRow = $rowIdx;

        foreach ($statusStats as $statusCode => $stat) {
            [$textColor, $fillColor] = $stat['colors'];

            $sheet->mergeCells("B{$rowIdx}:C{$rowIdx}");
            $sheet->mergeCells("D{$rowIdx}:E{$rowIdx}");
            $sheet->mergeCells("F{$rowIdx}:G{$rowIdx}");

            SpreadsheetSanitizer::setSafeStringCell($sheet, "A{$rowIdx}", strtoupper((string) $statusCode));
            SpreadsheetSanitizer::setSafeStringCell($sheet, "B{$rowIdx}", $stat['label']);
            $sheet->setCellValue("D{$rowIdx}", $stat['count']);
            $sheet->setCellValue("F{$rowIdx}", $totalDocuments > 0 ? $stat['count'] / $totalDocuments : 0);

            $sheet->getStyle("A{$rowIdx}:G{$rowIdx}")->applyFromArray([
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FFE2E8F0']]],
                'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
            ]);
            $sheet->getStyle("A{$rowIdx}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("A{$rowIdx}")->getFont()->setName('Consolas')->setSize(9);
            $sheet->getStyle("B{$rowIdx}:C{$rowIdx}")->applyFromArray([
                'font' => ['bold' => true, 'size' => 9, 'color' => ['argb' => $textColor]],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => $fillColor]],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'indent' => 1],
            ]);
            $sheet->getStyle("D{$rowIdx}:G{$rowIdx}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("F{$rowIdx}")->getNumberFormat()->setFormatCode('0.0%');
            $sheet->getRowDimension($rowIdx)->setRowHeight(22);

            $rowIdx++;
        }

        $statusEndRow = $rowIdx - 1;
        $sheet->mergeCells("A{$rowIdx}:C{$rowIdx}");
        $sheet->mergeCells("D{$rowIdx}:E{$rowIdx}");
        $sheet->mergeCells("F{$rowIdx}:G{$rowIdx}");
        $sheet->setCellValue("A{$rowIdx}", 'TOTAL DOKUMEN TEREKSPOR');
        $sheet->setCellValue("D{$rowIdx}", "=SUM(D{$statusStartRow}:D{$statusEndRow})");
        $sheet->setCellValue("F{$rowIdx}", "=SUM(F{$statusStartRow}:F{$statusEndRow})");
        $sheet->getStyle("A{$rowIdx}:G{$rowIdx}")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['argb' => 'FF0F172A']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFF1F5F9']],
            'borders' => [
                'top' => ['borderStyle' => Border::BORDER_MEDIUM, 'color' => ['argb' => 'FF1E293B']],
                'bottom' => ['borderStyle' => Border::BORDER_MEDIUM, 'color' => ['argb' => 'FF1E293B']],
                'allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FFCBD5E1']],
            ],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getStyle("A{$rowIdx}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT)->setIndent(1);
        $sheet->getStyle("D{$rowIdx}:G{$rowIdx}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle("F{$rowIdx}")->getNumberFormat()->setFormatCode('0.0%');
        $sheet->getRowDimension($rowIdx)->setRowHeight(23);

        $rowIdx += 2;

        // Bagian B: Rekapitulasi per Unit Kerja / Program Studi
        $sheet->mergeCells("A{$rowIdx}:G{$rowIdx}");
        $sheet->setCellValue("A{$rowIdx}", 'B. REKAPITULASI PER UNIT KERJA / PROGRAM STUDI');
        $sheet->getStyle("A{$rowIdx}:G{$rowIdx}")->applyFromArray([
            'font' => ['bold' => true, 'size' => 10, 'color' => ['argb' => 'FF0F172A']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFE2E8F0']],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER, 'indent' => 1],
        ]);
        $sheet->getRowDimension($rowIdx)->setRowHeight(22);

        $rowIdx++;
        $unitHeaders = [
            'A' => 'Kode Unit',
            'B' => 'Unit Kerja / Program Studi',
            'C' => 'Total Naskah',
            'D' => 'Disahkan',
            'E' => 'Bukti Borang',
            'F' => 'Berkas Tersedia',
            'G' => 'Kelengkapan Berkas',
        ];
        foreach ($unitHeaders as $col => $title) {
            $sheet->setCellValue("{$col}{$rowIdx}", $title);
        }
        $sheet->getStyle("A{$rowIdx}:G{$rowIdx}")->applyFromArray([
            'font' => ['bold' => true, 'size' => 10, 'color' => ['argb' => 'FFFFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF1E293B']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FF334155']]],
        ]);
        $sheet->getRowDimension($rowIdx)->setRowHeight(24);

        uasort($unitStats, fn (array $a, array $b): int => strcmp($a['name'], $b['name']));

        $rowIdx++;
        $unitStartRow = $rowIdx;

        if ($unitStats === []) {
            $sheet->mergeCells("A{$rowIdx}:G{$rowIdx}");
            $sheet->setCellValue("A{$rowIdx}", 'Tidak ada data unit kerja pada ekspor ini.');
            $sheet->getStyle("A{$rowIdx}:G{$rowIdx}")->applyFromArray([
                'font' => ['italic' => true, 'color' => ['argb' => 'FF64748B']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FFE2E8F0']]],
            ]);
            $sheet->getRowDimension($rowIdx)->setRowHeight(24);
        } else {
            $unitNo = 0;
            foreach ($unitStats as $entry) {
                $unitNo++;
                $bgColor = ($unitNo % 2 === 0) ? 'FFF8FAFC' : 'FFFFFFFF';

                SpreadsheetSanitizer::setSafeStringCell($sheet, "A{$rowIdx}", $entry['code']);
                SpreadsheetSanitizer::setSafeStringCell($sheet, "B{$rowIdx}", $entry['name']);
                $sheet->setCellValue("C{$rowIdx}", $entry['total']);
                $sheet->setCellValue("D{$rowIdx}", $entry['approved']);
                $sheet->setCellValue("E{$rowIdx}", $entry['borang']);
                $sheet->setCellValue("F{$rowIdx}", $entry['with_file']);
                $sheet->setCellValue("G{$rowIdx}", "=IF(C{$rowIdx}>0,F{$rowIdx}/C{$rowIdx},0)");

                $sheet->getStyle("A{$rowIdx}:G{$rowIdx}")->applyFromArray([
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => $bgColor]],
                    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FFE2E8F0']]],
                    'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
                ]);
                $sheet->getStyle("A{$rowIdx}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle("A{$rowIdx}")->getFont()->setBold(true);
                $sheet->getStyle("B{$rowIdx}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT)->setWrapText(true);
                $sheet->getStyle("C{$rowIdx}:G{$rowIdx}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle("G{$rowIdx}")->getNumberFormat()->setFormatCode('0.0%');
                $sheet->getRowDimension($rowIdx)->setRowHeight(22);

                $rowIdx++;
            }

            $unitEndRow = $rowIdx - 1;
            $sheet->mergeCells("A{$rowIdx}:B{$rowIdx}");
            $sheet->setCellValue("A{$rowIdx}", 'TOTAL KESELURUHAN UNIT');
            $sheet->setCellValue("C{$rowIdx}", "=SUM(C{$unitStartRow}:C{$unitEndRow})");
            $sheet->setCellValue("D{$rowIdx}", "=SUM(D{$unitStartRow}:D{$unitEndRow})");
            $sheet->setCellValue("E{$rowIdx}", "=SUM(E{$unitStartRow}:E{$unitEndRow})");
            $sheet->setCellValue("F{$rowIdx}", "=SUM(F{$unitStartRow}:F{$unitEndRow})");
            $sheet->setCellValue("G{$rowIdx}", "=IF(C{$rowIdx}>0,F{$rowIdx}/C{$rowIdx},0)");

            $sheet->getStyle("A{$rowIdx}:G{$rowIdx}")->applyFromArray([
                'font' => ['bold' => true, 'color' => ['argb' => 'FF0F172A']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFF1F5F9']],
                'borders' => [
                    'top' => ['borderStyle' => Border::BORDER_MEDIUM, 'color' => ['argb' => 'FF1E293B']],
                    'bottom' => ['borderStyle' => Border::BORDER_MEDIUM, 'color' => ['argb' => 'FF1E293B']],
                    'allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FFCBD5E1']],
                ],
                'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
            ]);
            $sheet->getStyle("A{$rowIdx}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT)->setIndent(1);
            $sheet->getStyle("C{$rowIdx}:G{$rowIdx}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("G{$rowIdx}")->getNumberFormat()->setFormatCode('0.0%');
            $sheet->getRowDimension($rowIdx)->setRowHeight(23);
        }

        $sheet->freezePane('A4');
        $this->configurePrintSetup($sheet, repeatStartRow: 1, repeatEndRow: 2);
    }

    private function resolveStatusShortLabel(DocumentStatus $status): string
    {
        return match ($status) {
            DocumentStatus::Draft => 'Draf',
            DocumentStatus::Pending => 'Menunggu',
            DocumentStatus::Revision => 'Revisi',
            DocumentStatus::WaitingSignature => 'Menunggu TTD',
            DocumentStatus::Approved => 'Disahkan',
            DocumentStatus::Rejected => 'Ditolak',
            DocumentStatus::Expired => 'Kedaluwarsa',
            DocumentStatus::Archived => 'Arsip',
        };
    }

    /**
     * @return array{0: string, 1: string} [textColorArgb, fillColorArgb]
     */
    private function resolveStatusBadgeColors(DocumentStatus $status): array
    {
        return match ($status) {
            DocumentStatus::Approved => ['FF15803D', 'FFDCFCE7'],
            DocumentStatus::Pending, DocumentStatus::WaitingSignature => ['FFB45309', 'FFFEF3C7'],
            DocumentStatus::Revision => ['FFC2410C', 'FFFFEDD5'],
            DocumentStatus::Rejected, DocumentStatus::Expired => ['FFB91C1C', 'FFFEE2E2'],
            DocumentStatus::Archived => ['FF1D4ED8', 'FFDBEAFE'],
            DocumentStatus::Draft => ['FF475569', 'FFF1F5F9'],
        };
    }
}
