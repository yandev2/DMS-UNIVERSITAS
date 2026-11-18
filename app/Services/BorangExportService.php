<?php

namespace App\Services;

use App\Enums\AccreditationInstrument;
use App\Models\AcademicYear;
use App\Models\AccreditationCriterion;
use App\Models\Document;
use App\Models\Unit;
use App\Support\SpreadsheetSanitizer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BorangExportService
{
    /**
     * Build a PhpSpreadsheet instance containing the compiled Borang documents
     * and an executive summary sheet for assessors and quality assurance teams.
     *
     * @param  Collection<int, Document>  $documents
     */
    public function buildSpreadsheet(
        Collection $documents,
        ?Unit $unit = null,
        ?AcademicYear $academicYear = null,
        ?AccreditationCriterion $criterion = null,
        ?string $instrumentSource = null
    ): Spreadsheet {
        return $this->assembleSpreadsheet(function (
            Worksheet $mainSheet,
            array $columns,
            int &$currentRow,
            int &$counter,
            int &$totalDocuments,
            int &$withFileCount,
            array &$criteriaStats,
            array &$unitStats
        ) use ($documents, $criterion): void {
            $this->appendMainRecapBatch(
                $mainSheet,
                $documents,
                $columns,
                $criterion,
                $currentRow,
                $counter,
                $totalDocuments,
                $withFileCount,
                $criteriaStats,
                $unitStats
            );
        }, $unit, $academicYear, $criterion, $instrumentSource);
    }

    /**
     * Build a PhpSpreadsheet instance directly from an Eloquent query using chunkById
     * to keep Eloquent object memory bounded on large exports (> 5,000 documents).
     */
    public function buildSpreadsheetFromQuery(
        Builder $query,
        ?Unit $unit = null,
        ?AcademicYear $academicYear = null,
        ?AccreditationCriterion $criterion = null,
        ?string $instrumentSource = null
    ): Spreadsheet {
        return $this->assembleSpreadsheet(function (
            Worksheet $mainSheet,
            array $columns,
            int &$currentRow,
            int &$counter,
            int &$totalDocuments,
            int &$withFileCount,
            array &$criteriaStats,
            array &$unitStats
        ) use ($query, $criterion): void {
            (clone $query)
                ->with([
                    'unit',
                    'category',
                    'academicYear',
                    'accreditationCriteria',
                    'primaryFile',
                    'finalApprover',
                ])
                ->chunkById(250, function (Collection $chunk) use (
                    $mainSheet,
                    $columns,
                    $criterion,
                    &$currentRow,
                    &$counter,
                    &$totalDocuments,
                    &$withFileCount,
                    &$criteriaStats,
                    &$unitStats
                ): void {
                    $this->appendMainRecapBatch(
                        $mainSheet,
                        $chunk,
                        $columns,
                        $criterion,
                        $currentRow,
                        $counter,
                        $totalDocuments,
                        $withFileCount,
                        $criteriaStats,
                        $unitStats
                    );
                }, 'documents.id', 'id');
        }, $unit, $academicYear, $criterion, $instrumentSource);
    }

    /**
     * Download as streamed HTTP response.
     *
     * @param  Collection<int, Document>  $documents
     */
    public function downloadResponse(
        Collection $documents,
        string $filename = 'Rekapitulasi-Borang-Akreditasi.xlsx',
        ?Unit $unit = null,
        ?AcademicYear $academicYear = null,
        ?AccreditationCriterion $criterion = null,
        ?string $instrumentSource = null
    ): StreamedResponse {
        $spreadsheet = $this->buildSpreadsheet($documents, $unit, $academicYear, $criterion, $instrumentSource);
        $writer = new Xlsx($spreadsheet);

        return response()->streamDownload(function () use ($writer): void {
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'max-age=0',
        ]);
    }

    /**
     * Download as streamed HTTP response directly from an Eloquent Builder query.
     */
    public function downloadResponseFromQuery(
        Builder $query,
        string $filename = 'Rekapitulasi-Borang-Akreditasi.xlsx',
        ?Unit $unit = null,
        ?AcademicYear $academicYear = null,
        ?AccreditationCriterion $criterion = null,
        ?string $instrumentSource = null
    ): StreamedResponse {
        @set_time_limit(0);
        $spreadsheet = $this->buildSpreadsheetFromQuery($query, $unit, $academicYear, $criterion, $instrumentSource);
        $writer = new Xlsx($spreadsheet);

        return response()->streamDownload(function () use ($writer): void {
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'max-age=0',
        ]);
    }

    /**
     * Save generated Excel workbook from an Eloquent query to disk (used by queued background exports).
     */
    public function saveSpreadsheetFromQueryToFile(
        Builder $query,
        string $absolutePath,
        ?Unit $unit = null,
        ?AcademicYear $academicYear = null,
        ?AccreditationCriterion $criterion = null,
        ?string $instrumentSource = null
    ): void {
        @set_time_limit(0);
        $spreadsheet = $this->buildSpreadsheetFromQuery($query, $unit, $academicYear, $criterion, $instrumentSource);
        $writer = new Xlsx($spreadsheet);
        $writer->save($absolutePath);
        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet, $writer);
    }

    /**
     * Assemble both worksheets using a unified streaming callback.
     *
     * @param  callable(Worksheet, array<string, array{0: string, 1: int}>, int&, int&, int&, int&, array<string, array<string, mixed>>&, array<int, array<string, mixed>>&): void  $batchFeeder
     */
    private function assembleSpreadsheet(
        callable $batchFeeder,
        ?Unit $unit,
        ?AcademicYear $academicYear,
        ?AccreditationCriterion $criterion,
        ?string $instrumentSource
    ): Spreadsheet {
        @ini_set('memory_limit', '512M');

        $spreadsheet = new Spreadsheet;
        $spreadsheet->getDefaultStyle()->getFont()->setName('Calibri')->setSize(10)->getColor()->setARGB('FF1E293B');

        $spreadsheet->getProperties()
            ->setCreator('Universitas Bina Insan - E-Arsip')
            ->setLastModifiedBy('Lembaga Penjaminan Mutu (LPM)')
            ->setTitle('Rekapitulasi Dokumen Bukti Borang Akreditasi')
            ->setSubject('Instrumen Akreditasi BAN-PT / LAM')
            ->setDescription('Kompilasi naskah dinas resmi bukti fisik borang akreditasi program studi.');

        $mainSheet = $spreadsheet->getActiveSheet();
        $columns = $this->initializeMainRecapSheet($mainSheet, $unit, $academicYear, $criterion, $instrumentSource);

        $startDataRow = 7;
        $currentRow = $startDataRow;
        $counter = 1;
        $totalDocuments = 0;
        $withFileCount = 0;
        $criteriaStats = [];
        $unitStats = [];

        $batchFeeder(
            $mainSheet,
            $columns,
            $currentRow,
            $counter,
            $totalDocuments,
            $withFileCount,
            $criteriaStats,
            $unitStats
        );

        $totalMappedRows = $currentRow - $startDataRow;
        $this->finalizeMainRecapSheet($mainSheet, $startDataRow, $currentRow, $totalDocuments, $totalMappedRows, $withFileCount);

        $summarySheet = $spreadsheet->createSheet();
        $this->buildSummarySheetFromStats($summarySheet, $criteriaStats, $unitStats, $unit, $academicYear, $criterion, $instrumentSource);

        $spreadsheet->setActiveSheetIndex(0);

        return $spreadsheet;
    }

    /**
     * Initialize Sheet 1 banner, metadata pairs, and column headers.
     *
     * @return array<string, array{0: string, 1: int}>
     */
    private function initializeMainRecapSheet(
        Worksheet $sheet,
        ?Unit $unit,
        ?AcademicYear $academicYear,
        ?AccreditationCriterion $criterion,
        ?string $instrumentSource
    ): array {
        $sheet->setTitle('Rekapitulasi Borang');
        $sheet->setShowGridlines(true);

        $unitLabel = $this->resolveUnitLabel($unit);
        $academicYearLabel = $academicYear ? $academicYear->name : 'Semua Tahun Akademik';
        $criterionLabel = $criterion ? "{$criterion->code} [{$criterion->instrument_source}] - {$criterion->name}" : 'Seluruh Kriteria (K1–K9)';
        $instrumentLabel = $this->resolveInstrumentLabel($instrumentSource);
        $downloadDate = now()->translatedFormat('d F Y, H:i').' WIB';

        // 1. Institutional Banner (Rows 1 - 2)
        $sheet->mergeCells('A1:N1');
        $sheet->setCellValue('A1', 'UNIVERSITAS BINA INSAN (UNIVBI)');
        $sheet->getStyle('A1:N1')->applyFromArray([
            'font' => [
                'bold' => true,
                'size' => 14,
                'color' => ['argb' => 'FFFFFFFF'],
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['argb' => 'FF1E3A8A'],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_LEFT,
                'vertical' => Alignment::VERTICAL_CENTER,
                'indent' => 1,
            ],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(28);

        $sheet->mergeCells('A2:N2');
        $sheet->setCellValue('A2', 'REKAPITULASI DOKUMEN BUKTI FISIK BORANG AKREDITASI (BAN-PT / LAM)');
        $sheet->getStyle('A2:N2')->applyFromArray([
            'font' => [
                'bold' => true,
                'size' => 11,
                'color' => ['argb' => 'FF1E3A8A'],
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['argb' => 'FFEFF6FF'],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_LEFT,
                'vertical' => Alignment::VERTICAL_CENTER,
                'indent' => 1,
            ],
            'borders' => [
                'bottom' => [
                    'borderStyle' => Border::BORDER_MEDIUM,
                    'color' => ['argb' => 'FF1E3A8A'],
                ],
            ],
        ]);
        $sheet->getRowDimension(2)->setRowHeight(22);

        // 2. Structured Filter & KPI Metadata Card (Rows 3 - 4)
        $this->renderMetadataPair($sheet, 'A3:B3', 'C3:E3', 'Unit / Program Studi', $unitLabel);
        $this->renderMetadataPair($sheet, 'F3:F3', 'G3:H3', 'Tahun Akademik', $academicYearLabel);
        $this->renderMetadataPair($sheet, 'I3:I3', 'J3:K3', 'Instrumen', $instrumentLabel);
        $this->renderMetadataPair($sheet, 'L3:L3', 'M3:N3', 'Kriteria Filter', $criterionLabel);

        $this->renderMetadataPair($sheet, 'A4:B4', 'C4:E4', 'Waktu Unduh', $downloadDate);
        $this->renderMetadataPair($sheet, 'F4:F4', 'G4:H4', 'Dokumen Unik', '0 Naskah');
        $this->renderMetadataPair($sheet, 'I4:I4', 'J4:K4', 'Pemetaan Butir', '0 Baris Butir');
        $this->renderMetadataPair($sheet, 'L4:L4', 'M4:N4', 'Kelengkapan Berkas', '0/0 Berkas (0%)');

        $sheet->getRowDimension(3)->setRowHeight(20);
        $sheet->getRowDimension(4)->setRowHeight(20);
        $sheet->getRowDimension(5)->setRowHeight(8);

        // 3. Table Column Headers (Row 6)
        $columns = [
            'A' => ['No', 6],
            'B' => ['Kriteria Akreditasi', 24],
            'C' => ['Indikator / Butir & Catatan', 28],
            'D' => ['Kategori Naskah', 20],
            'E' => ['Nomor Surat Resmi', 28],
            'F' => ['Judul Dokumen & Ringkasan', 44],
            'G' => ['Program Studi / Unit', 24],
            'H' => ['Tahun Akademik', 16],
            'I' => ['Tgl Sah & Masa Berlaku', 20],
            'J' => ['Pejabat Pengesah', 24],
            'K' => ['Berkas Fisik', 21],
            'L' => ['Status Akses Asesor', 18],
            'M' => ['Tautan Bukti Asesor', 22],
            'N' => ['Verifikasi QR', 19],
        ];

        foreach ($columns as $colLetter => [$label, $width]) {
            $sheet->setCellValue("{$colLetter}6", $label);
            $sheet->getColumnDimension($colLetter)->setWidth($width);
        }

        $sheet->getStyle('A6:N6')->applyFromArray([
            'font' => [
                'bold' => true,
                'color' => ['argb' => 'FFFFFFFF'],
                'size' => 10,
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['argb' => 'FF1E293B'],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
                'wrapText' => true,
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['argb' => 'FF334155'],
                ],
            ],
        ]);
        $sheet->getRowDimension(6)->setRowHeight(30);

        return $columns;
    }

    /**
     * Stream a batch of documents directly into Sheet 1 and update compact aggregates for Sheet 2.
     *
     * @param  iterable<int, Document>  $documents
     * @param  array<string, array{0: string, 1: int}>  $columns
     * @param  array<string, array<string, mixed>>  $criteriaStats
     * @param  array<int, array<string, mixed>>  $unitStats
     */
    private function appendMainRecapBatch(
        Worksheet $sheet,
        iterable $documents,
        array $columns,
        ?AccreditationCriterion $filterCriterion,
        int &$currentRow,
        int &$counter,
        int &$totalDocuments,
        int &$withFileCount,
        array &$criteriaStats,
        array &$unitStats
    ): void {
        foreach ($documents as $document) {
            $totalDocuments++;
            $primaryFile = $document->primaryFile;
            $hasFile = $primaryFile !== null;
            if ($hasFile) {
                $withFileCount++;
            }

            if ($document->unit) {
                $u = $document->unit;
                if (! isset($unitStats[$u->id])) {
                    $unitStats[$u->id] = [
                        'unit' => $u,
                        'count' => 0,
                    ];
                }
                $unitStats[$u->id]['count']++;
            }

            $criteria = $document->accreditationCriteria;
            if ($filterCriterion !== null && $criteria->contains('id', $filterCriterion->id)) {
                $criteria = $criteria->where('id', $filterCriterion->id);
            }
            if ($criteria->isEmpty()) {
                $criteria = collect([null]);
            }

            $categoryName = $document->category?->name ?? '-';
            $docNumber = filled($document->document_number) ? (string) $document->document_number : 'Belum Bernomor';

            $titleAndSummary = (string) $document->title;
            if (filled($document->description)) {
                $cleanDesc = trim((string) preg_replace('/\s+/', ' ', strip_tags((string) $document->description)));
                if ($cleanDesc !== '') {
                    $titleAndSummary .= "\n".Str::limit($cleanDesc, 110);
                }
            }

            $unitName = $document->unit
                ? (filled($document->unit->code) ? "{$document->unit->name} ({$document->unit->code})" : $document->unit->name)
                : '-';
            $academicYearName = $document->academicYear?->name ?? '-';

            $issuedText = $document->issued_date ? $document->issued_date->format('d/m/Y') : '-';
            if ($document->has_expiry && $document->expired_date) {
                $expiryNote = $document->expired_date->isPast() ? ' (Kedaluwarsa)' : '';
                $issuedText .= "\ns.d. ".$document->expired_date->format('d/m/Y').$expiryNote;
            }

            $signerText = $document->signer_name_snapshot ?: ($document->finalApprover?->name ?? '-');
            if (filled($document->signer_position_snapshot)) {
                $signerText .= "\n".$document->signer_position_snapshot;
            }

            if ($hasFile) {
                $ext = strtoupper((string) ($primaryFile->file_extension ?: 'FILE'));
                $size = $this->formatFileSize($primaryFile->file_size);
                $stampNote = filled($primaryFile->stamped_file_path) ? 'Resmi (QR)' : 'Asli';
                $fileInfoText = "{$ext} • {$size}\n{$stampNote}";
            } else {
                $fileInfoText = 'Belum Ada Berkas';
            }

            $accessStatusKey = $document->unit?->getAssessorAccessStatus() ?? 'inactive';
            [$accessStatusLabel, $statusTextColor, $statusFillColor] = $this->resolveAccessStatusBadge($accessStatusKey);

            $assessorUrl = $document->getAssessorUrl();
            $verifyUrl = $document->getVerificationUrl();

            foreach ($criteria as $docCriterion) {
                $indicatorText = filled($docCriterion?->pivot?->indicator_code)
                    ? (string) $docCriterion->pivot->indicator_code
                    : '-';

                if (filled($docCriterion?->pivot?->note)) {
                    $indicatorText .= "\nCatatan: ".$docCriterion->pivot->note;
                }

                $criterionCode = $docCriterion?->code ?? 'Umum';
                $criterionInstrument = $docCriterion?->instrument_source ?? '-';
                $criterionName = $docCriterion?->name ?? 'Belum Dipetakan ke Kriteria';
                $criterionLabel = $docCriterion
                    ? "{$docCriterion->code} [{$docCriterion->instrument_source}] - {$docCriterion->name}"
                    : 'Umum (Belum Dipetakan)';
                $criterionKey = $docCriterion ? (string) $docCriterion->id : 'unmapped';

                if (! isset($criteriaStats[$criterionKey])) {
                    $criteriaStats[$criterionKey] = [
                        'code' => $criterionCode,
                        'instrument' => $criterionInstrument,
                        'name' => $criterionName,
                        'order_index' => $docCriterion?->order_index ?? 9999,
                        'mapped_rows' => 0,
                        'doc_ids' => [],
                        'with_file_doc_ids' => [],
                    ];
                }
                $criteriaStats[$criterionKey]['mapped_rows']++;
                $criteriaStats[$criterionKey]['doc_ids'][$document->id] = true;
                if ($hasFile) {
                    $criteriaStats[$criterionKey]['with_file_doc_ids'][$document->id] = true;
                }

                $sheet->setCellValue("A{$currentRow}", $counter);
                SpreadsheetSanitizer::setSafeStringCell($sheet, "B{$currentRow}", $criterionLabel);
                SpreadsheetSanitizer::setSafeStringCell($sheet, "C{$currentRow}", $indicatorText);
                SpreadsheetSanitizer::setSafeStringCell($sheet, "D{$currentRow}", $categoryName);
                SpreadsheetSanitizer::setSafeStringCell($sheet, "E{$currentRow}", $docNumber);
                SpreadsheetSanitizer::setSafeStringCell($sheet, "F{$currentRow}", $titleAndSummary);
                SpreadsheetSanitizer::setSafeStringCell($sheet, "G{$currentRow}", $unitName);
                SpreadsheetSanitizer::setSafeStringCell($sheet, "H{$currentRow}", $academicYearName);
                SpreadsheetSanitizer::setSafeStringCell($sheet, "I{$currentRow}", $issuedText);
                SpreadsheetSanitizer::setSafeStringCell($sheet, "J{$currentRow}", $signerText);
                SpreadsheetSanitizer::setSafeStringCell($sheet, "K{$currentRow}", $fileInfoText);
                SpreadsheetSanitizer::setSafeStringCell($sheet, "L{$currentRow}", $accessStatusLabel);
                SpreadsheetSanitizer::setSafeHyperlinkCell($sheet, "M{$currentRow}", $assessorUrl, 'Buka Bukti Asesor ↗');
                SpreadsheetSanitizer::setSafeHyperlinkCell($sheet, "N{$currentRow}", $verifyUrl, 'Verifikasi QR ↗');

                $isEven = ($counter % 2 === 0);
                $rowBgColor = $isEven ? 'FFF8FAFC' : 'FFFFFFFF';

                $sheet->getStyle("A{$currentRow}:N{$currentRow}")->applyFromArray([
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['argb' => $rowBgColor],
                    ],
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => Border::BORDER_THIN,
                            'color' => ['argb' => 'FFE2E8F0'],
                        ],
                    ],
                    'alignment' => [
                        'vertical' => Alignment::VERTICAL_CENTER,
                        'wrapText' => true,
                    ],
                ]);

                $sheet->getStyle("A{$currentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle("B{$currentRow}:D{$currentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
                $sheet->getStyle("E{$currentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle("E{$currentRow}")->getFont()->setName('Consolas')->setSize(9);
                $sheet->getStyle("F{$currentRow}:G{$currentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
                $sheet->getStyle("H{$currentRow}:I{$currentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle("J{$currentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);

                $sheet->getStyle("K{$currentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                if (! $hasFile) {
                    $sheet->getStyle("K{$currentRow}")->getFont()->setBold(true)->setSize(9)->getColor()->setARGB('FFDC2626');
                } else {
                    $sheet->getStyle("K{$currentRow}")->getFont()->setSize(9)->getColor()->setARGB('FF334155');
                }

                $sheet->getStyle("L{$currentRow}")->applyFromArray([
                    'font' => [
                        'bold' => true,
                        'size' => 9,
                        'color' => ['argb' => $statusTextColor],
                    ],
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['argb' => $statusFillColor],
                    ],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                        'vertical' => Alignment::VERTICAL_CENTER,
                    ],
                ]);

                $sheet->getStyle("M{$currentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle("M{$currentRow}")->getFont()->setBold(true)->setUnderline(true)->getColor()->setARGB('FF2563EB');
                $sheet->getStyle("N{$currentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle("N{$currentRow}")->getFont()->setUnderline(true)->getColor()->setARGB('FF0369A1');

                $rowHeight = $this->calculateRowHeight([
                    [$criterionLabel, $columns['B'][1]],
                    [$indicatorText, $columns['C'][1]],
                    [$categoryName, $columns['D'][1]],
                    [$docNumber, $columns['E'][1]],
                    [$titleAndSummary, $columns['F'][1]],
                    [$unitName, $columns['G'][1]],
                    [$issuedText, $columns['I'][1]],
                    [$signerText, $columns['J'][1]],
                    [$fileInfoText, $columns['K'][1]],
                ]);
                $sheet->getRowDimension($currentRow)->setRowHeight($rowHeight);

                $currentRow++;
                $counter++;
            }
        }
    }

    /**
     * Populate final KPI counters and footer summary row for Sheet 1.
     */
    private function finalizeMainRecapSheet(
        Worksheet $sheet,
        int $startDataRow,
        int $currentRow,
        int $totalDocuments,
        int $totalMappedRows,
        int $withFileCount
    ): void {
        $completenessPct = $totalDocuments > 0 ? (int) round(($withFileCount / $totalDocuments) * 100) : 0;

        $sheet->setCellValue('G4', "{$totalDocuments} Naskah");
        $sheet->setCellValue('J4', "{$totalMappedRows} Baris Butir");
        $sheet->setCellValue('M4', "{$withFileCount}/{$totalDocuments} Berkas ({$completenessPct}%)");

        if ($totalMappedRows === 0) {
            $sheet->mergeCells("A{$currentRow}:N{$currentRow}");
            $sheet->setCellValue("A{$currentRow}", 'Tidak ada dokumen bukti borang yang sesuai dengan filter saat ini.');
            $sheet->getStyle("A{$currentRow}:N{$currentRow}")->applyFromArray([
                'font' => [
                    'italic' => true,
                    'color' => ['argb' => 'FF64748B'],
                ],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical' => Alignment::VERTICAL_CENTER,
                ],
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => Border::BORDER_THIN,
                        'color' => ['argb' => 'FFE2E8F0'],
                    ],
                ],
            ]);
            $sheet->getRowDimension($currentRow)->setRowHeight(32);
        } else {
            $lastDataRow = $currentRow - 1;
            $summaryRow = $currentRow;

            $sheet->mergeCells("A{$summaryRow}:E{$summaryRow}");
            $sheet->setCellValue("A{$summaryRow}", "TOTAL REKAPITULASI ({$totalDocuments} Dokumen Unik)");
            $sheet->setCellValue("F{$summaryRow}", "=COUNTA(F{$startDataRow}:F{$lastDataRow})");
            $sheet->setCellValue("K{$summaryRow}", "=COUNTIF(K{$startDataRow}:K{$lastDataRow},\"*•*\")");
            $sheet->setCellValue("L{$summaryRow}", "=COUNTIF(L{$startDataRow}:L{$lastDataRow},\"Akses Terbuka\")");

            $sheet->getStyle("A{$summaryRow}:N{$summaryRow}")->applyFromArray([
                'font' => [
                    'bold' => true,
                    'size' => 10,
                    'color' => ['argb' => 'FF0F172A'],
                ],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['argb' => 'FFF1F5F9'],
                ],
                'alignment' => [
                    'vertical' => Alignment::VERTICAL_CENTER,
                ],
                'borders' => [
                    'top' => [
                        'borderStyle' => Border::BORDER_MEDIUM,
                        'color' => ['argb' => 'FF1E293B'],
                    ],
                    'bottom' => [
                        'borderStyle' => Border::BORDER_MEDIUM,
                        'color' => ['argb' => 'FF1E293B'],
                    ],
                    'allBorders' => [
                        'borderStyle' => Border::BORDER_THIN,
                        'color' => ['argb' => 'FFCBD5E1'],
                    ],
                ],
            ]);

            $sheet->getStyle("A{$summaryRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT)->setIndent(1);
            $sheet->getStyle("F{$summaryRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
            $sheet->getStyle("F{$summaryRow}")->getNumberFormat()->setFormatCode('0" Baris Pemetaan"');
            $sheet->getStyle("K{$summaryRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("K{$summaryRow}")->getNumberFormat()->setFormatCode('0" Berkas Siap"');
            $sheet->getStyle("L{$summaryRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("L{$summaryRow}")->getNumberFormat()->setFormatCode('0" Akses Terbuka"');
            $sheet->getRowDimension($summaryRow)->setRowHeight(24);

            $sheet->getStyle("A6:N{$lastDataRow}")->getBorders()->getOutline()->setBorderStyle(Border::BORDER_MEDIUM)->getColor()->setARGB('FF1E293B');
            $sheet->setAutoFilter("A6:N{$lastDataRow}");
        }

        $sheet->freezePane('A7');
        $this->configurePrintSetup($sheet, repeatStartRow: 6, repeatEndRow: 6);
    }

    /**
     * Build Sheet 2: Executive breakdown by Criterion & Unit Assessor Portal status from streamed aggregates.
     *
     * @param  array<string, array<string, mixed>>  $criteriaStats
     * @param  array<int, array<string, mixed>>  $unitStats
     */
    private function buildSummarySheetFromStats(
        Worksheet $sheet,
        array $criteriaStats,
        array $unitStats,
        ?Unit $unit,
        ?AcademicYear $academicYear,
        ?AccreditationCriterion $criterion,
        ?string $instrumentSource
    ): void {
        $sheet->setTitle('Ringkasan & Portal Asesor');
        $sheet->setShowGridlines(true);

        $columnWidths = [
            'A' => 14,
            'B' => 30,
            'C' => 36,
            'D' => 20,
            'E' => 24,
            'F' => 20,
            'G' => 24,
        ];

        foreach ($columnWidths as $colLetter => $width) {
            $sheet->getColumnDimension($colLetter)->setWidth($width);
        }

        // Banner
        $sheet->mergeCells('A1:G1');
        $sheet->setCellValue('A1', 'RINGKASAN KELENGKAPAN BORANG & PORTAL AKSES ASESOR');
        $sheet->getStyle('A1:G1')->applyFromArray([
            'font' => [
                'bold' => true,
                'size' => 13,
                'color' => ['argb' => 'FFFFFFFF'],
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['argb' => 'FF1E3A8A'],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_LEFT,
                'vertical' => Alignment::VERTICAL_CENTER,
                'indent' => 1,
            ],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(26);

        $unitLabel = $this->resolveUnitLabel($unit);
        $yearLabel = $academicYear ? $academicYear->name : 'Semua Tahun Akademik';
        $instrLabel = $this->resolveInstrumentLabel($instrumentSource);
        $critLabel = $criterion ? "{$criterion->code} - {$criterion->name}" : 'Seluruh Kriteria';

        $sheet->mergeCells('A2:G2');
        SpreadsheetSanitizer::setSafeStringCell($sheet, 'A2', "Filter: {$unitLabel} | {$yearLabel} | {$instrLabel} | {$critLabel} — Diunduh ".now()->translatedFormat('d F Y, H:i').' WIB');
        $sheet->getStyle('A2:G2')->applyFromArray([
            'font' => [
                'size' => 9,
                'italic' => true,
                'color' => ['argb' => 'FF1E3A8A'],
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['argb' => 'FFEFF6FF'],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_LEFT,
                'vertical' => Alignment::VERTICAL_CENTER,
                'indent' => 1,
            ],
            'borders' => [
                'bottom' => [
                    'borderStyle' => Border::BORDER_MEDIUM,
                    'color' => ['argb' => 'FF1E3A8A'],
                ],
            ],
        ]);
        $sheet->getRowDimension(2)->setRowHeight(20);
        $sheet->getRowDimension(3)->setRowHeight(10);

        // Section A: Criteria Summary
        $rowIdx = 4;
        $sheet->mergeCells("A{$rowIdx}:G{$rowIdx}");
        $sheet->setCellValue("A{$rowIdx}", 'A. REKAPITULASI KELENGKAPAN PER KRITERIA AKREDITASI');
        $sheet->getStyle("A{$rowIdx}:G{$rowIdx}")->applyFromArray([
            'font' => ['bold' => true, 'size' => 10, 'color' => ['argb' => 'FF0F172A']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFE2E8F0']],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER, 'indent' => 1],
        ]);
        $sheet->getRowDimension($rowIdx)->setRowHeight(22);

        $rowIdx++;
        $critHeaders = [
            'A' => 'Kode',
            'B' => 'Instrumen',
            'C' => 'Nama Kriteria Akreditasi',
            'D' => 'Baris Pemetaan',
            'E' => 'Dokumen Unik',
            'F' => 'Berkas Tersedia',
            'G' => 'Kelengkapan Berkas',
        ];
        foreach ($critHeaders as $col => $title) {
            $sheet->setCellValue("{$col}{$rowIdx}", $title);
        }
        $sheet->getStyle("A{$rowIdx}:G{$rowIdx}")->applyFromArray([
            'font' => ['bold' => true, 'size' => 10, 'color' => ['argb' => 'FFFFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF1E293B']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FF334155']]],
        ]);
        $sheet->getRowDimension($rowIdx)->setRowHeight(24);

        uasort($criteriaStats, fn (array $a, array $b): int => $a['order_index'] <=> $b['order_index']);

        $rowIdx++;
        $critStartRow = $rowIdx;

        if ($criteriaStats === []) {
            $sheet->mergeCells("A{$rowIdx}:G{$rowIdx}");
            $sheet->setCellValue("A{$rowIdx}", 'Belum ada pemetaan kriteria untuk dokumen yang diekspor.');
            $sheet->getStyle("A{$rowIdx}:G{$rowIdx}")->applyFromArray([
                'font' => ['italic' => true, 'color' => ['argb' => 'FF64748B']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FFE2E8F0']]],
            ]);
            $sheet->getRowDimension($rowIdx)->setRowHeight(24);
            $rowIdx++;
        } else {
            $itemNo = 0;
            foreach ($criteriaStats as $stat) {
                $itemNo++;
                $uniqueDocCount = count($stat['doc_ids']);
                $withFileDocCount = count($stat['with_file_doc_ids']);
                $bgColor = ($itemNo % 2 === 0) ? 'FFF8FAFC' : 'FFFFFFFF';

                SpreadsheetSanitizer::setSafeStringCell($sheet, "A{$rowIdx}", $stat['code']);
                SpreadsheetSanitizer::setSafeStringCell($sheet, "B{$rowIdx}", $stat['instrument']);
                SpreadsheetSanitizer::setSafeStringCell($sheet, "C{$rowIdx}", $stat['name']);
                $sheet->setCellValue("D{$rowIdx}", $stat['mapped_rows']);
                $sheet->setCellValue("E{$rowIdx}", $uniqueDocCount);
                $sheet->setCellValue("F{$rowIdx}", $withFileDocCount);
                $sheet->setCellValue("G{$rowIdx}", "=IF(E{$rowIdx}>0,F{$rowIdx}/E{$rowIdx},0)");

                $sheet->getStyle("A{$rowIdx}:G{$rowIdx}")->applyFromArray([
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => $bgColor]],
                    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FFE2E8F0']]],
                    'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
                ]);
                $sheet->getStyle("A{$rowIdx}:B{$rowIdx}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle("A{$rowIdx}")->getFont()->setBold(true);
                $sheet->getStyle("C{$rowIdx}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT)->setWrapText(true);
                $sheet->getStyle("D{$rowIdx}:G{$rowIdx}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle("G{$rowIdx}")->getNumberFormat()->setFormatCode('0.0%');
                $sheet->getRowDimension($rowIdx)->setRowHeight(22);

                $rowIdx++;
            }

            $critEndRow = $rowIdx - 1;
            $sheet->mergeCells("A{$rowIdx}:C{$rowIdx}");
            $sheet->setCellValue("A{$rowIdx}", 'TOTAL KESELURUHAN PEMETAAN KRITERIA');
            $sheet->setCellValue("D{$rowIdx}", "=SUM(D{$critStartRow}:D{$critEndRow})");
            $sheet->setCellValue("E{$rowIdx}", "=SUM(E{$critStartRow}:E{$critEndRow})");
            $sheet->setCellValue("F{$rowIdx}", "=SUM(F{$critStartRow}:F{$critEndRow})");
            $sheet->setCellValue("G{$rowIdx}", "=IF(E{$rowIdx}>0,F{$rowIdx}/E{$rowIdx},0)");

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
            $sheet->getStyle("G{$rowIdx}")->getNumberFormat()->setFormatCode('0.0%');
            $sheet->getRowDimension($rowIdx)->setRowHeight(23);

            $rowIdx++;
        }

        // Spacer
        $sheet->getRowDimension($rowIdx)->setRowHeight(14);
        $rowIdx++;

        // Section B: Assessor Portal Status per Unit
        $sheet->mergeCells("A{$rowIdx}:G{$rowIdx}");
        $sheet->setCellValue("A{$rowIdx}", 'B. STATUS PORTAL AKSES ASESOR PER UNIT / PROGRAM STUDI');
        $sheet->getStyle("A{$rowIdx}:G{$rowIdx}")->applyFromArray([
            'font' => ['bold' => true, 'size' => 10, 'color' => ['argb' => 'FF0F172A']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFE2E8F0']],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER, 'indent' => 1],
        ]);
        $sheet->getRowDimension($rowIdx)->setRowHeight(22);

        $rowIdx++;
        $unitHeaders = [
            'A' => 'Kode Unit',
            'B' => 'Program Studi / Unit Kerja',
            'C' => 'Dokumen Bukti',
            'D' => 'Status Portal Asesor',
            'E' => 'Periode Akses Asesmen',
            'F' => 'PIN / Passcode',
            'G' => 'Tautan Portal Unit',
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

        if ($unitStats === [] && $unit !== null) {
            $unitStats[$unit->id] = [
                'unit' => $unit,
                'count' => 0,
            ];
        }

        $rowIdx++;
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
                /** @var Unit $entryUnit */
                $entryUnit = $entry['unit'];
                $bgColor = ($unitNo % 2 === 0) ? 'FFF8FAFC' : 'FFFFFFFF';

                $statusKey = $entryUnit->getAssessorAccessStatus();
                [$statusLabel, $statusTextColor, $statusFillColor] = $this->resolveAccessStatusBadge($statusKey);

                $periodText = 'Belum Dijadwalkan';
                if ($entryUnit->is_assessor_access_active) {
                    if ($entryUnit->assessor_access_start || $entryUnit->assessor_access_end) {
                        $startStr = $entryUnit->assessor_access_start?->format('d/m/Y') ?? 'Sekarang';
                        $endStr = $entryUnit->assessor_access_end?->format('d/m/Y') ?? 'Tanpa Batas';
                        $periodText = "{$startStr} – {$endStr}";
                    } else {
                        $periodText = 'Aktif (Tanpa Batas Waktu)';
                    }
                }

                $passcodeText = filled($entryUnit->assessor_passcode) ? (string) $entryUnit->assessor_passcode : 'Tanpa PIN';
                $portalUrl = $entryUnit->getAssessorPortalUrl();

                SpreadsheetSanitizer::setSafeStringCell($sheet, "A{$rowIdx}", $entryUnit->code ?? '-');
                SpreadsheetSanitizer::setSafeStringCell($sheet, "B{$rowIdx}", $entryUnit->name);
                $sheet->setCellValue("C{$rowIdx}", $entry['count']);
                SpreadsheetSanitizer::setSafeStringCell($sheet, "D{$rowIdx}", $statusLabel);
                SpreadsheetSanitizer::setSafeStringCell($sheet, "E{$rowIdx}", $periodText);
                SpreadsheetSanitizer::setSafeStringCell($sheet, "F{$rowIdx}", $passcodeText);
                SpreadsheetSanitizer::setSafeHyperlinkCell($sheet, "G{$rowIdx}", $portalUrl, 'Buka Portal Unit ↗');

                $sheet->getStyle("A{$rowIdx}:G{$rowIdx}")->applyFromArray([
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => $bgColor]],
                    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FFE2E8F0']]],
                    'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
                ]);
                $sheet->getStyle("A{$rowIdx}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle("A{$rowIdx}")->getFont()->setBold(true);
                $sheet->getStyle("B{$rowIdx}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT)->setWrapText(true);
                $sheet->getStyle("C{$rowIdx}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle("C{$rowIdx}")->getNumberFormat()->setFormatCode('0" Dokumen"');

                $sheet->getStyle("D{$rowIdx}")->applyFromArray([
                    'font' => ['bold' => true, 'size' => 9, 'color' => ['argb' => $statusTextColor]],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => $statusFillColor]],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                ]);

                $sheet->getStyle("E{$rowIdx}:F{$rowIdx}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle("F{$rowIdx}")->getFont()->setName('Consolas')->setSize(9);
                $sheet->getStyle("G{$rowIdx}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle("G{$rowIdx}")->getFont()->setBold(true)->setUnderline(true)->getColor()->setARGB('FF2563EB');

                $sheet->getRowDimension($rowIdx)->setRowHeight(23);
                $rowIdx++;
            }
        }

        $sheet->freezePane('A4');
        $this->configurePrintSetup($sheet, repeatStartRow: 1, repeatEndRow: 2);
    }

    private function renderMetadataPair(
        Worksheet $sheet,
        string $labelRange,
        string $valueRange,
        string $label,
        string $value
    ): void {
        [$labelStart, $labelEnd] = explode(':', $labelRange);
        [$valueStart, $valueEnd] = explode(':', $valueRange);

        if ($labelStart !== $labelEnd) {
            $sheet->mergeCells($labelRange);
        }
        if ($valueStart !== $valueEnd) {
            $sheet->mergeCells($valueRange);
        }

        SpreadsheetSanitizer::setSafeStringCell($sheet, $labelStart, $label);
        SpreadsheetSanitizer::setSafeStringCell($sheet, $valueStart, $value);

        $sheet->getStyle($labelRange)->applyFromArray([
            'font' => [
                'bold' => true,
                'size' => 9,
                'color' => ['argb' => 'FF475569'],
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['argb' => 'FFF8FAFC'],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_LEFT,
                'vertical' => Alignment::VERTICAL_CENTER,
                'indent' => 1,
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['argb' => 'FFCBD5E1'],
                ],
            ],
        ]);

        $sheet->getStyle($valueRange)->applyFromArray([
            'font' => [
                'bold' => false,
                'size' => 9,
                'color' => ['argb' => 'FF0F172A'],
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['argb' => 'FFFFFFFF'],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_LEFT,
                'vertical' => Alignment::VERTICAL_CENTER,
                'indent' => 1,
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['argb' => 'FFCBD5E1'],
                ],
            ],
        ]);
    }

    /**
     * @return array{0: string, 1: string, 2: string} [label, textColorArgb, fillColorArgb]
     */
    private function resolveAccessStatusBadge(string $statusKey): array
    {
        return match ($statusKey) {
            'open' => ['Akses Terbuka', 'FF15803D', 'FFDCFCE7'],
            'scheduled' => ['Terjadwal', 'FFB45309', 'FFFEF3C7'],
            'expired' => ['Kedaluwarsa', 'FFB91C1C', 'FFFEE2E2'],
            default => ['Akses Terkunci', 'FFB91C1C', 'FFFEE2E2'],
        };
    }

    private function resolveUnitLabel(?Unit $unit): string
    {
        if (! $unit) {
            return 'Semua Unit Kerja / Program Studi';
        }

        return filled($unit->code) ? "{$unit->name} ({$unit->code})" : $unit->name;
    }

    private function resolveInstrumentLabel(?string $instrumentSource): string
    {
        if (blank($instrumentSource)) {
            return 'Semua Instrumen (BAN-PT / LAM)';
        }

        $enum = AccreditationInstrument::tryFrom($instrumentSource);

        return $enum?->getLabel() ?? $instrumentSource;
    }

    private function formatFileSize(?int $bytes): string
    {
        if (! $bytes || $bytes <= 0) {
            return '-';
        }

        if ($bytes < 1024) {
            return "{$bytes} B";
        }

        if ($bytes < 1048576) {
            return number_format($bytes / 1024, 0).' KB';
        }

        return number_format($bytes / 1048576, 2).' MB';
    }

    /**
     * Estimate comfortable row height from wrapped text lengths across columns.
     *
     * @param  array<int, array{0: string, 1: int|float}>  $cellTextsAndWidths
     */
    private function calculateRowHeight(array $cellTextsAndWidths): float
    {
        $maxLines = 1;

        foreach ($cellTextsAndWidths as [$text, $colWidth]) {
            $effectiveCharsPerLine = max(8, (int) floor($colWidth * 0.92));
            $lines = explode("\n", $text);
            $lineCount = 0;

            foreach ($lines as $line) {
                $len = mb_strlen(trim($line));
                $lineCount += max(1, (int) ceil($len / $effectiveCharsPerLine));
            }

            if ($lineCount > $maxLines) {
                $maxLines = $lineCount;
            }
        }

        return min(160.0, max(24.0, ($maxLines * 14.5) + 8.0));
    }

    private function configurePrintSetup(Worksheet $sheet, int $repeatStartRow, int $repeatEndRow): void
    {
        $pageSetup = $sheet->getPageSetup();
        $pageSetup->setOrientation(PageSetup::ORIENTATION_LANDSCAPE);
        $pageSetup->setPaperSize(PageSetup::PAPERSIZE_A4);
        $pageSetup->setFitToWidth(1);
        $pageSetup->setFitToHeight(0);
        $pageSetup->setRowsToRepeatAtTopByStartAndEnd($repeatStartRow, $repeatEndRow);

        $margins = $sheet->getPageMargins();
        $margins->setTop(0.4);
        $margins->setBottom(0.4);
        $margins->setLeft(0.35);
        $margins->setRight(0.35);
    }
}
