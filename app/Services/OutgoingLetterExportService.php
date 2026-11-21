<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\OutgoingLetterStatus;
use App\Models\OutgoingLetter;
use App\Services\Concerns\BuildsStyledSpreadsheet;
use App\Support\SpreadsheetSanitizer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class OutgoingLetterExportService
{
    use BuildsStyledSpreadsheet;

    /**
     * Membangun workbook Excel rekapitulasi Surat Keluar (2 sheet) secara streaming menggunakan `chunkById`.
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
            ->setLastModifiedBy((string) ($metadata['requester_name'] ?? 'Tim Humas & E-Arsip UNIVBI'))
            ->setTitle('Rekapitulasi Surat Keluar (Humas)')
            ->setSubject('Laporan Penomoran & Arsip Surat Keluar Universitas Bina Insan')
            ->setDescription('Laporan ekspor terfilter pengajuan & penomoran surat keluar Universitas Bina Insan.');

        $mainSheet = $spreadsheet->getActiveSheet();
        $columns = $this->initializeMainSheet($mainSheet, $metadata);

        $startDataRow = 7;
        $currentRow = $startDataRow;
        $counter = 1;
        $totalLetters = 0;
        $withFileCount = 0;
        $stampedCount = 0;
        $publishedCount = 0;
        $wetSigCount = 0;
        $statusStats = [];
        $unitStats = [];

        foreach (OutgoingLetterStatus::cases() as $statusCase) {
            $statusStats[$statusCase->value] = [
                'label' => $statusCase->getLabel() ?? $statusCase->value,
                'count' => 0,
                'colors' => $this->resolveStatusBadgeColors($statusCase),
            ];
        }

        (clone $query)
            ->with([
                'unit:id,name,code',
                'academicYear:id,name',
                'creator:id,name',
                'reviewer:id,name',
                'finalScanUploader:id,name',
            ])
            ->chunkById(250, function (Collection $chunk) use (
                $mainSheet,
                $columns,
                &$currentRow,
                &$counter,
                &$totalLetters,
                &$withFileCount,
                &$stampedCount,
                &$publishedCount,
                &$wetSigCount,
                &$statusStats,
                &$unitStats
            ): void {
                $this->appendBatchToMainSheet(
                    $mainSheet,
                    $chunk,
                    $columns,
                    $currentRow,
                    $counter,
                    $totalLetters,
                    $withFileCount,
                    $stampedCount,
                    $publishedCount,
                    $wetSigCount,
                    $statusStats,
                    $unitStats
                );
            }, 'outgoing_letters.id', 'id');

        $exportedCount = $totalLetters;
        $this->finalizeMainSheet($mainSheet, $startDataRow, $currentRow, $totalLetters, $withFileCount, $publishedCount);

        $summarySheet = $spreadsheet->createSheet();
        $this->buildSummarySheet(
            $summarySheet,
            $metadata,
            $totalLetters,
            $withFileCount,
            $stampedCount,
            $publishedCount,
            $wetSigCount,
            $statusStats,
            $unitStats
        );

        $spreadsheet->setActiveSheetIndex(0);

        return $spreadsheet;
    }

    /**
     * Menyimpan berkas Excel (.xlsx) hasil query ke path absolut di disk privat.
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
     * @param  array{
     *     period_label?: string,
     *     filter_summary?: string,
     *     requester_name?: string
     * }  $metadata
     * @return array<string, array{0: string, 1: int}>
     */
    private function initializeMainSheet(Worksheet $sheet, array $metadata): array
    {
        $sheet->setTitle('Rekapitulasi Surat Keluar');
        $sheet->setShowGridlines(true);

        $periodLabel = (string) ($metadata['period_label'] ?? '-');
        $filterSummary = (string) ($metadata['filter_summary'] ?? 'Semua Filter Opsional');
        $requesterName = (string) ($metadata['requester_name'] ?? 'Sistem E-Arsip');
        $downloadTime = now()->translatedFormat('d F Y, H:i').' WIB';

        $sheet->mergeCells('A1:N1');
        $sheet->setCellValue('A1', 'UNIVERSITAS BINA INSAN (UNIVBI)');
        $sheet->getStyle('A1:N1')->applyFromArray([
            'font' => ['bold' => true, 'size' => 14, 'color' => ['argb' => 'FFFFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF1E3A8A']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER, 'indent' => 1],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(28);

        $sheet->mergeCells('A2:N2');
        $sheet->setCellValue('A2', 'REKAPITULASI PENOMORAN & ARSIP SURAT KELUAR (HUMAS)');
        $sheet->getStyle('A2:N2')->applyFromArray([
            'font' => ['bold' => true, 'size' => 11, 'color' => ['argb' => 'FF1E3A8A']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFEFF6FF']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER, 'indent' => 1],
            'borders' => ['bottom' => ['borderStyle' => Border::BORDER_MEDIUM, 'color' => ['argb' => 'FF1E3A8A']]],
        ]);
        $sheet->getRowDimension(2)->setRowHeight(22);

        $this->renderMetadataPair($sheet, 'A3:B3', 'C3:E3', 'Periode Surat', $periodLabel);
        $this->renderMetadataPair($sheet, 'F3:G3', 'H3:K3', 'Filter Aktif', $filterSummary);
        $this->renderMetadataPair($sheet, 'L3:L3', 'M3:N3', 'Diminta Oleh', $requesterName);

        $this->renderMetadataPair($sheet, 'A4:B4', 'C4:E4', 'Waktu Ekspor', $downloadTime);
        $this->renderMetadataPair($sheet, 'F4:G4', 'H4:K4', 'Total Surat Keluar', '0 Surat');
        $this->renderMetadataPair($sheet, 'L4:L4', 'M4:N4', 'Kelengkapan Berkas', '0/0 Berkas (0%)');

        $sheet->getRowDimension(3)->setRowHeight(20);
        $sheet->getRowDimension(4)->setRowHeight(20);
        $sheet->getRowDimension(5)->setRowHeight(8);

        $columns = [
            'A' => ['No', 6],
            'B' => ['Nomor Surat Keluar', 26],
            'C' => ['Tanggal Surat', 16],
            'D' => ['Perihal & Ringkasan', 38],
            'E' => ['Tujuan / Instansi Penerima', 28],
            'F' => ['Unit Pengaju', 26],
            'G' => ['Tahun Akademik', 16],
            'H' => ['Pemohon / Pembuat', 22],
            'I' => ['Status Penomoran', 22],
            'J' => ['TTD Basah & Scan Final', 24],
            'K' => ['Peninjau Humas', 24],
            'L' => ['Catatan Humas / Revisi', 30],
            'M' => ['Status Berkas', 22],
            'N' => ['Tautan Pratinjau', 20],
        ];

        foreach ($columns as $colLetter => [$label, $width]) {
            $sheet->setCellValue("{$colLetter}6", $label);
            $sheet->getColumnDimension($colLetter)->setWidth($width);
        }

        $sheet->getStyle('A6:N6')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF'], 'size' => 10],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF1E293B']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FF334155']]],
        ]);
        $sheet->getRowDimension(6)->setRowHeight(28);

        return $columns;
    }

    /**
     * @param  iterable<int, OutgoingLetter>  $letters
     * @param  array<string, array{0: string, 1: int}>  $columns
     * @param  array<string, array{label: string, count: int, colors: array{0: string, 1: string}}>  $statusStats
     * @param  array<int, array{code: string, name: string, total: int, published: int, wet_sig: int, with_file: int}>  $unitStats
     */
    private function appendBatchToMainSheet(
        Worksheet $sheet,
        iterable $letters,
        array $columns,
        int &$currentRow,
        int &$counter,
        int &$totalLetters,
        int &$withFileCount,
        int &$stampedCount,
        int &$publishedCount,
        int &$wetSigCount,
        array &$statusStats,
        array &$unitStats
    ): void {
        foreach ($letters as $letter) {
            $totalLetters++;

            $activePath = $letter->getActiveFilePath();
            $hasFile = filled($activePath);
            $hasStamped = filled($letter->stamped_file_path);

            if ($hasFile) {
                $withFileCount++;
            }
            if ($hasStamped) {
                $stampedCount++;
            }
            if ($letter->requires_wet_signature) {
                $wetSigCount++;
            }

            $statusEnum = $letter->status instanceof OutgoingLetterStatus
                ? $letter->status
                : OutgoingLetterStatus::tryFrom((string) $letter->status);

            if ($statusEnum === OutgoingLetterStatus::Published) {
                $publishedCount++;
            }
            if ($statusEnum && isset($statusStats[$statusEnum->value])) {
                $statusStats[$statusEnum->value]['count']++;
            }

            $unitId = (int) ($letter->unit_id ?? 0);
            if ($unitId > 0) {
                if (! isset($unitStats[$unitId])) {
                    $unitStats[$unitId] = [
                        'code' => (string) ($letter->unit?->code ?? '-'),
                        'name' => (string) ($letter->unit?->name ?? 'Unit Tidak Diketahui'),
                        'total' => 0,
                        'published' => 0,
                        'wet_sig' => 0,
                        'with_file' => 0,
                    ];
                }
                $unitStats[$unitId]['total']++;
                if ($statusEnum === OutgoingLetterStatus::Published) {
                    $unitStats[$unitId]['published']++;
                }
                if ($letter->requires_wet_signature) {
                    $unitStats[$unitId]['wet_sig']++;
                }
                if ($hasFile) {
                    $unitStats[$unitId]['with_file']++;
                }
            }

            $letterNumber = filled($letter->letter_number) ? (string) $letter->letter_number : '[Menunggu Humas]';
            $letterDateText = $letter->letter_date ? $letter->letter_date->format('d/m/Y') : '-';

            $subjectAndDesc = (string) $letter->subject;
            if (filled($letter->description)) {
                $cleanDesc = trim((string) preg_replace('/\s+/', ' ', strip_tags((string) $letter->description)));
                if ($cleanDesc !== '') {
                    $subjectAndDesc .= "\nRingkasan: ".Str::limit($cleanDesc, 140);
                }
            }

            $destination = filled($letter->destination_organization) ? (string) $letter->destination_organization : '-';
            $unitName = $letter->unit
                ? (filled($letter->unit->code) ? "{$letter->unit->name} ({$letter->unit->code})" : $letter->unit->name)
                : '-';
            $academicYearName = $letter->academicYear?->name ?? '-';
            $creatorName = $letter->creator?->name ?? '-';

            $statusLabel = $statusEnum?->getLabel() ?? '-';
            [$statusTextColor, $statusFillColor] = $statusEnum
                ? $this->resolveStatusBadgeColors($statusEnum)
                : ['FF334155', 'FFF1F5F9'];

            if (! $letter->requires_wet_signature) {
                $wetSigText = 'Tidak';
            } elseif ($letter->final_scan_uploaded_at !== null) {
                $uploader = $letter->finalScanUploader?->name;
                $wetSigText = 'Ya (Scan: '.$letter->final_scan_uploaded_at->format('d/m/Y').')'
                    .($uploader ? "\nOleh: {$uploader}" : '');
            } else {
                $wetSigText = "Ya\n(Menunggu Scan)";
            }

            $reviewerParts = array_filter([
                $letter->reviewer?->name,
                $letter->reviewed_at ? 'Tinjau: '.$letter->reviewed_at->format('d/m/Y H:i') : null,
            ]);
            $reviewerText = $reviewerParts !== [] ? implode("\n", $reviewerParts) : '-';

            $reviewNote = filled($letter->review_note)
                ? trim((string) preg_replace('/\s+/', ' ', (string) $letter->review_note))
                : '-';

            if ($hasFile) {
                $ext = strtoupper((string) (pathinfo((string) ($letter->file_name ?: $activePath), PATHINFO_EXTENSION) ?: 'PDF'));
                $stampLabel = $hasStamped ? 'Resmi (QR/Stempel)' : 'Berkas Asli';
                $fileStatusText = "{$ext} • {$stampLabel}";
            } else {
                $fileStatusText = 'Belum Ada Berkas';
            }

            $sheet->setCellValue("A{$currentRow}", $counter);
            SpreadsheetSanitizer::setSafeStringCell($sheet, "B{$currentRow}", $letterNumber);
            SpreadsheetSanitizer::setSafeStringCell($sheet, "C{$currentRow}", $letterDateText);
            SpreadsheetSanitizer::setSafeStringCell($sheet, "D{$currentRow}", $subjectAndDesc);
            SpreadsheetSanitizer::setSafeStringCell($sheet, "E{$currentRow}", $destination);
            SpreadsheetSanitizer::setSafeStringCell($sheet, "F{$currentRow}", $unitName);
            SpreadsheetSanitizer::setSafeStringCell($sheet, "G{$currentRow}", $academicYearName);
            SpreadsheetSanitizer::setSafeStringCell($sheet, "H{$currentRow}", $creatorName);
            SpreadsheetSanitizer::setSafeStringCell($sheet, "I{$currentRow}", $statusLabel);
            SpreadsheetSanitizer::setSafeStringCell($sheet, "J{$currentRow}", $wetSigText);
            SpreadsheetSanitizer::setSafeStringCell($sheet, "K{$currentRow}", $reviewerText);
            SpreadsheetSanitizer::setSafeStringCell($sheet, "L{$currentRow}", $reviewNote);
            SpreadsheetSanitizer::setSafeStringCell($sheet, "M{$currentRow}", $fileStatusText);

            if ($hasFile) {
                SpreadsheetSanitizer::setSafeHyperlinkCell($sheet, "N{$currentRow}", $letter->getVerificationUrl(), 'Pratinjau Berkas ↗');
            } else {
                SpreadsheetSanitizer::setSafeStringCell($sheet, "N{$currentRow}", '-');
            }

            $isEven = ($counter % 2 === 0);
            $rowBgColor = $isEven ? 'FFF8FAFC' : 'FFFFFFFF';

            $sheet->getStyle("A{$currentRow}:N{$currentRow}")->applyFromArray([
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => $rowBgColor]],
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FFE2E8F0']]],
                'alignment' => ['vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
            ]);

            $sheet->getStyle("A{$currentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("B{$currentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
            $sheet->getStyle("B{$currentRow}")->getFont()->setName('Consolas')->setSize(9)->setBold(true);
            $sheet->getStyle("C{$currentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("D{$currentRow}:F{$currentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
            $sheet->getStyle("G{$currentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("H{$currentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);

            $sheet->getStyle("I{$currentRow}")->applyFromArray([
                'font' => ['bold' => true, 'size' => 9, 'color' => ['argb' => $statusTextColor]],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => $statusFillColor]],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            ]);

            $sheet->getStyle("J{$currentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("K{$currentRow}:L{$currentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
            $sheet->getStyle("M{$currentRow}:N{$currentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

            if (! $hasFile) {
                $sheet->getStyle("M{$currentRow}")->getFont()->setBold(true)->setSize(9)->getColor()->setARGB('FFDC2626');
            } else {
                $sheet->getStyle("M{$currentRow}")->getFont()->setSize(9)->getColor()->setARGB('FF334155');
                $sheet->getStyle("N{$currentRow}")->getFont()->setBold(true)->setUnderline(true)->getColor()->setARGB('FF2563EB');
            }

            $rowHeight = $this->calculateRowHeight([
                [$letterNumber, $columns['B'][1]],
                [$subjectAndDesc, $columns['D'][1]],
                [$destination, $columns['E'][1]],
                [$unitName, $columns['F'][1]],
                [$wetSigText, $columns['J'][1]],
                [$reviewerText, $columns['K'][1]],
                [$reviewNote, $columns['L'][1]],
            ]);
            $sheet->getRowDimension($currentRow)->setRowHeight($rowHeight);

            $currentRow++;
            $counter++;
        }
    }

    private function finalizeMainSheet(
        Worksheet $sheet,
        int $startDataRow,
        int $currentRow,
        int $totalLetters,
        int $withFileCount,
        int $publishedCount
    ): void {
        $completenessPct = $totalLetters > 0 ? (int) round(($withFileCount / $totalLetters) * 100) : 0;

        $sheet->setCellValue('H4', "{$totalLetters} Surat ({$publishedCount} Resmi Terbit)");
        $sheet->setCellValue('M4', "{$withFileCount}/{$totalLetters} Berkas ({$completenessPct}%)");

        if ($totalLetters === 0) {
            $sheet->mergeCells("A{$currentRow}:N{$currentRow}");
            $sheet->setCellValue("A{$currentRow}", 'Tidak ada surat keluar yang sesuai dengan filter ekspor.');
            $sheet->getStyle("A{$currentRow}:N{$currentRow}")->applyFromArray([
                'font' => ['italic' => true, 'color' => ['argb' => 'FF64748B']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FFE2E8F0']]],
            ]);
            $sheet->getRowDimension($currentRow)->setRowHeight(32);
        } else {
            $lastDataRow = $currentRow - 1;
            $summaryRow = $currentRow;

            $sheet->mergeCells("A{$summaryRow}:C{$summaryRow}");
            $sheet->setCellValue("A{$summaryRow}", "TOTAL ({$totalLetters} Surat Keluar)");
            $sheet->setCellValue("D{$summaryRow}", "=COUNTA(D{$startDataRow}:D{$lastDataRow})");
            $sheet->setCellValue("I{$summaryRow}", "=COUNTIF(I{$startDataRow}:I{$lastDataRow},\"Terbit*\")");

            $sheet->getStyle("A{$summaryRow}:N{$summaryRow}")->applyFromArray([
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
            $sheet->getStyle("D{$summaryRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
            $sheet->getStyle("D{$summaryRow}")->getNumberFormat()->setFormatCode('0" Surat"');
            $sheet->getStyle("I{$summaryRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("I{$summaryRow}")->getNumberFormat()->setFormatCode('0" Terbit"');
            $sheet->getRowDimension($summaryRow)->setRowHeight(24);

            $sheet->getStyle("A6:N{$lastDataRow}")->getBorders()->getOutline()->setBorderStyle(Border::BORDER_MEDIUM)->getColor()->setARGB('FF1E293B');
            $sheet->setAutoFilter("A6:N{$lastDataRow}");
        }

        $sheet->freezePane('A7');
        $this->configurePrintSetup($sheet, repeatStartRow: 6, repeatEndRow: 6);
    }

    /**
     * @param  array{
     *     period_label?: string,
     *     filter_summary?: string,
     *     requester_name?: string
     * }  $metadata
     * @param  array<string, array{label: string, count: int, colors: array{0: string, 1: string}}>  $statusStats
     * @param  array<int, array{code: string, name: string, total: int, published: int, wet_sig: int, with_file: int}>  $unitStats
     */
    private function buildSummarySheet(
        Worksheet $sheet,
        array $metadata,
        int $totalLetters,
        int $withFileCount,
        int $stampedCount,
        int $publishedCount,
        int $wetSigCount,
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
        $sheet->setCellValue('A1', 'RINGKASAN EKSEKUTIF PENOMORAN SURAT KELUAR');
        $sheet->getStyle('A1:G1')->applyFromArray([
            'font' => ['bold' => true, 'size' => 13, 'color' => ['argb' => 'FFFFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF1E3A8A']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER, 'indent' => 1],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(26);

        $periodLabel = (string) ($metadata['period_label'] ?? '-');
        $filterSummary = (string) ($metadata['filter_summary'] ?? 'Semua Filter Opsional');
        $infoLine = "Periode Surat: {$periodLabel} | Filter: {$filterSummary} | Resmi Terbit: {$publishedCount}/{$totalLetters} | TTD Basah: {$wetSigCount} | Berkas QR/Stempel: {$stampedCount}/{$withFileCount}";

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

        // Bagian A: Statistik per Status Penomoran
        $rowIdx = 4;
        $sheet->mergeCells("A{$rowIdx}:G{$rowIdx}");
        $sheet->setCellValue("A{$rowIdx}", 'A. REKAPITULASI BERDASARKAN STATUS PENOMORAN SURAT KELUAR');
        $sheet->getStyle("A{$rowIdx}:G{$rowIdx}")->applyFromArray([
            'font' => ['bold' => true, 'size' => 10, 'color' => ['argb' => 'FF0F172A']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFE2E8F0']],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER, 'indent' => 1],
        ]);
        $sheet->getRowDimension($rowIdx)->setRowHeight(22);

        $rowIdx++;
        $sheet->setCellValue("A{$rowIdx}", 'Kode');
        $sheet->mergeCells("B{$rowIdx}:C{$rowIdx}");
        $sheet->setCellValue("B{$rowIdx}", 'Status Penomoran');
        $sheet->mergeCells("D{$rowIdx}:E{$rowIdx}");
        $sheet->setCellValue("D{$rowIdx}", 'Jumlah Surat');
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
            $sheet->setCellValue("F{$rowIdx}", $totalLetters > 0 ? $stat['count'] / $totalLetters : 0);

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
        $sheet->setCellValue("A{$rowIdx}", 'TOTAL SURAT KELUAR TEREKSPOR');
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

        // Bagian B: Rekapitulasi per Unit Pengaju
        $sheet->mergeCells("A{$rowIdx}:G{$rowIdx}");
        $sheet->setCellValue("A{$rowIdx}", 'B. REKAPITULASI PER UNIT PENGAJU');
        $sheet->getStyle("A{$rowIdx}:G{$rowIdx}")->applyFromArray([
            'font' => ['bold' => true, 'size' => 10, 'color' => ['argb' => 'FF0F172A']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFE2E8F0']],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER, 'indent' => 1],
        ]);
        $sheet->getRowDimension($rowIdx)->setRowHeight(22);

        $rowIdx++;
        $unitHeaders = [
            'A' => 'Kode Unit',
            'B' => 'Unit Pengaju',
            'C' => 'Total Pengajuan',
            'D' => 'Resmi Terbit',
            'E' => 'Wajib TTD Basah',
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
            $sheet->setCellValue("A{$rowIdx}", 'Tidak ada data unit pengaju pada ekspor ini.');
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
                $sheet->setCellValue("D{$rowIdx}", $entry['published']);
                $sheet->setCellValue("E{$rowIdx}", $entry['wet_sig']);
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

    /**
     * @return array{0: string, 1: string}
     */
    private function resolveStatusBadgeColors(OutgoingLetterStatus $status): array
    {
        return match ($status) {
            OutgoingLetterStatus::PendingHumas => ['FFB45309', 'FFFEF3C7'],
            OutgoingLetterStatus::Revision => ['FFC2410C', 'FFFFEDD5'],
            OutgoingLetterStatus::WaitingSignature => ['FF1D4ED8', 'FFDBEAFE'],
            OutgoingLetterStatus::Published => ['FF15803D', 'FFDCFCE7'],
            OutgoingLetterStatus::Rejected => ['FFB91C1C', 'FFFEE2E2'],
        };
    }
}
