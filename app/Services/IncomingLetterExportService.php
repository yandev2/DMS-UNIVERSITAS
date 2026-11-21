<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\IncomingLetterStatus;
use App\Enums\IncomingRecipientStatus;
use App\Enums\LetterPriority;
use App\Models\IncomingLetter;
use App\Models\IncomingLetterRecipient;
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

class IncomingLetterExportService
{
    use BuildsStyledSpreadsheet;

    /**
     * Membangun workbook Excel rekapitulasi Surat Masuk (2 sheet) secara streaming menggunakan `chunkById`.
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
            ->setTitle('Rekapitulasi Surat Masuk (Eksternal)')
            ->setSubject('Laporan Agenda & Distribusi Surat Masuk Universitas Bina Insan')
            ->setDescription('Laporan ekspor terfilter agenda surat masuk eksternal Universitas Bina Insan.');

        $mainSheet = $spreadsheet->getActiveSheet();
        $columns = $this->initializeMainSheet($mainSheet, $metadata);

        $startDataRow = 7;
        $currentRow = $startDataRow;
        $counter = 1;
        $totalLetters = 0;
        $withFileCount = 0;
        $stampedCount = 0;
        $completedCount = 0;
        $statusStats = [];
        $priorityStats = [];
        $unitStats = [];

        foreach (IncomingLetterStatus::cases() as $statusCase) {
            $statusStats[$statusCase->value] = [
                'label' => $statusCase->getLabel() ?? $statusCase->value,
                'count' => 0,
                'colors' => $this->resolveStatusBadgeColors($statusCase),
            ];
        }

        foreach (LetterPriority::cases() as $priorityCase) {
            $priorityStats[$priorityCase->value] = [
                'label' => $priorityCase->getLabel() ?? $priorityCase->value,
                'count' => 0,
                'colors' => $this->resolvePriorityBadgeColors($priorityCase),
            ];
        }

        (clone $query)
            ->with([
                'unit:id,name,code',
                'academicYear:id,name',
                'registrar:id,name',
                'recipients' => fn ($q) => $q->orderBy('step_order')->with('recipient:id,name'),
            ])
            ->chunkById(250, function (Collection $chunk) use (
                $mainSheet,
                $columns,
                &$currentRow,
                &$counter,
                &$totalLetters,
                &$withFileCount,
                &$stampedCount,
                &$completedCount,
                &$statusStats,
                &$priorityStats,
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
                    $completedCount,
                    $statusStats,
                    $priorityStats,
                    $unitStats
                );
            }, 'incoming_letters.id', 'id');

        $exportedCount = $totalLetters;
        $this->finalizeMainSheet($mainSheet, $startDataRow, $currentRow, $totalLetters, $withFileCount, $completedCount);

        $summarySheet = $spreadsheet->createSheet();
        $this->buildSummarySheet(
            $summarySheet,
            $metadata,
            $totalLetters,
            $withFileCount,
            $stampedCount,
            $completedCount,
            $statusStats,
            $priorityStats,
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
        $sheet->setTitle('Rekapitulasi Surat Masuk');
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
        $sheet->setCellValue('A2', 'REKAPITULASI AGENDA & DISTRIBUSI SURAT MASUK (EKSTERNAL)');
        $sheet->getStyle('A2:N2')->applyFromArray([
            'font' => ['bold' => true, 'size' => 11, 'color' => ['argb' => 'FF1E3A8A']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFEFF6FF']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER, 'indent' => 1],
            'borders' => ['bottom' => ['borderStyle' => Border::BORDER_MEDIUM, 'color' => ['argb' => 'FF1E3A8A']]],
        ]);
        $sheet->getRowDimension(2)->setRowHeight(22);

        $this->renderMetadataPair($sheet, 'A3:B3', 'C3:E3', 'Periode Diterima', $periodLabel);
        $this->renderMetadataPair($sheet, 'F3:G3', 'H3:K3', 'Filter Aktif', $filterSummary);
        $this->renderMetadataPair($sheet, 'L3:L3', 'M3:N3', 'Diminta Oleh', $requesterName);

        $this->renderMetadataPair($sheet, 'A4:B4', 'C4:E4', 'Waktu Ekspor', $downloadTime);
        $this->renderMetadataPair($sheet, 'F4:G4', 'H4:K4', 'Total Surat Masuk', '0 Surat');
        $this->renderMetadataPair($sheet, 'L4:L4', 'M4:N4', 'Kelengkapan Berkas', '0/0 Berkas (0%)');

        $sheet->getRowDimension(3)->setRowHeight(20);
        $sheet->getRowDimension(4)->setRowHeight(20);
        $sheet->getRowDimension(5)->setRowHeight(8);

        $columns = [
            'A' => ['No', 6],
            'B' => ['Nomor Agenda Humas', 24],
            'C' => ['Tanggal Diterima', 16],
            'D' => ['Nomor Surat Pengirim', 24],
            'E' => ['Tgl Surat Pengirim', 16],
            'F' => ['Asal Surat / Pengirim', 28],
            'G' => ['Perihal & Ringkasan', 38],
            'H' => ['Unit Tujuan', 26],
            'I' => ['Tahun Akademik', 16],
            'J' => ['Sifat Surat', 16],
            'K' => ['Status Distribusi', 18],
            'L' => ['Posisi & Riwayat Disposisi', 36],
            'M' => ['Pendaftar (Humas)', 22],
            'N' => ['Berkas & Tautan', 22],
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
     * @param  iterable<int, IncomingLetter>  $letters
     * @param  array<string, array{0: string, 1: int}>  $columns
     * @param  array<string, array{label: string, count: int, colors: array{0: string, 1: string}}>  $statusStats
     * @param  array<string, array{label: string, count: int, colors: array{0: string, 1: string}}>  $priorityStats
     * @param  array<int, array{code: string, name: string, total: int, completed: int, with_file: int}>  $unitStats
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
        int &$completedCount,
        array &$statusStats,
        array &$priorityStats,
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

            $statusEnum = $letter->status instanceof IncomingLetterStatus
                ? $letter->status
                : IncomingLetterStatus::tryFrom((string) $letter->status);

            if ($statusEnum === IncomingLetterStatus::Completed) {
                $completedCount++;
            }
            if ($statusEnum && isset($statusStats[$statusEnum->value])) {
                $statusStats[$statusEnum->value]['count']++;
            }

            $priorityEnum = $letter->priority instanceof LetterPriority
                ? $letter->priority
                : LetterPriority::tryFrom((string) $letter->priority);

            if ($priorityEnum && isset($priorityStats[$priorityEnum->value])) {
                $priorityStats[$priorityEnum->value]['count']++;
            }

            $unitId = (int) ($letter->unit_id ?? 0);
            if ($unitId > 0) {
                if (! isset($unitStats[$unitId])) {
                    $unitStats[$unitId] = [
                        'code' => (string) ($letter->unit?->code ?? '-'),
                        'name' => (string) ($letter->unit?->name ?? 'Unit Tidak Diketahui'),
                        'total' => 0,
                        'completed' => 0,
                        'with_file' => 0,
                    ];
                }
                $unitStats[$unitId]['total']++;
                if ($statusEnum === IncomingLetterStatus::Completed) {
                    $unitStats[$unitId]['completed']++;
                }
                if ($hasFile) {
                    $unitStats[$unitId]['with_file']++;
                }
            }

            $agendaNumber = filled($letter->agenda_number) ? (string) $letter->agenda_number : '[Otomatis]';
            $receivedDateText = $letter->received_date ? $letter->received_date->format('d/m/Y') : '-';
            $externalNumber = filled($letter->external_letter_number) ? (string) $letter->external_letter_number : '-';
            $externalDateText = $letter->external_letter_date ? $letter->external_letter_date->format('d/m/Y') : '-';
            $senderName = filled($letter->sender_name) ? (string) $letter->sender_name : '-';

            $subjectAndSummary = (string) $letter->subject;
            if (filled($letter->summary)) {
                $cleanSummary = trim((string) preg_replace('/\s+/', ' ', strip_tags((string) $letter->summary)));
                if ($cleanSummary !== '') {
                    $subjectAndSummary .= "\nRingkasan: ".Str::limit($cleanSummary, 140);
                }
            }

            $unitName = $letter->unit
                ? (filled($letter->unit->code) ? "{$letter->unit->name} ({$letter->unit->code})" : $letter->unit->name)
                : '-';
            $academicYearName = $letter->academicYear?->name ?? '-';

            $priorityLabel = $priorityEnum?->getLabel() ?? '-';
            [$prioTextColor, $prioFillColor] = $priorityEnum
                ? $this->resolvePriorityBadgeColors($priorityEnum)
                : ['FF334155', 'FFF1F5F9'];

            $statusLabel = $statusEnum?->getLabel() ?? '-';
            [$statusTextColor, $statusFillColor] = $statusEnum
                ? $this->resolveStatusBadgeColors($statusEnum)
                : ['FF334155', 'FFF1F5F9'];

            $dispositionText = $this->formatDispositionTrail($letter);
            $registrarName = $letter->registrar?->name ?? '-';

            $sheet->setCellValue("A{$currentRow}", $counter);
            SpreadsheetSanitizer::setSafeStringCell($sheet, "B{$currentRow}", $agendaNumber);
            SpreadsheetSanitizer::setSafeStringCell($sheet, "C{$currentRow}", $receivedDateText);
            SpreadsheetSanitizer::setSafeStringCell($sheet, "D{$currentRow}", $externalNumber);
            SpreadsheetSanitizer::setSafeStringCell($sheet, "E{$currentRow}", $externalDateText);
            SpreadsheetSanitizer::setSafeStringCell($sheet, "F{$currentRow}", $senderName);
            SpreadsheetSanitizer::setSafeStringCell($sheet, "G{$currentRow}", $subjectAndSummary);
            SpreadsheetSanitizer::setSafeStringCell($sheet, "H{$currentRow}", $unitName);
            SpreadsheetSanitizer::setSafeStringCell($sheet, "I{$currentRow}", $academicYearName);
            SpreadsheetSanitizer::setSafeStringCell($sheet, "J{$currentRow}", $priorityLabel);
            SpreadsheetSanitizer::setSafeStringCell($sheet, "K{$currentRow}", $statusLabel);
            SpreadsheetSanitizer::setSafeStringCell($sheet, "L{$currentRow}", $dispositionText);
            SpreadsheetSanitizer::setSafeStringCell($sheet, "M{$currentRow}", $registrarName);

            if ($hasFile) {
                $linkLabel = $hasStamped ? 'PDF Stempel Agenda ↗' : 'Pratinjau Berkas ↗';
                SpreadsheetSanitizer::setSafeHyperlinkCell($sheet, "N{$currentRow}", $letter->getVerificationUrl(), $linkLabel);
            } else {
                SpreadsheetSanitizer::setSafeStringCell($sheet, "N{$currentRow}", 'Belum Ada Berkas');
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
            $sheet->getStyle("D{$currentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
            $sheet->getStyle("D{$currentRow}")->getFont()->setName('Consolas')->setSize(9);
            $sheet->getStyle("E{$currentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("F{$currentRow}:H{$currentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
            $sheet->getStyle("I{$currentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

            $sheet->getStyle("J{$currentRow}")->applyFromArray([
                'font' => ['bold' => true, 'size' => 9, 'color' => ['argb' => $prioTextColor]],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => $prioFillColor]],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            ]);

            $sheet->getStyle("K{$currentRow}")->applyFromArray([
                'font' => ['bold' => true, 'size' => 9, 'color' => ['argb' => $statusTextColor]],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => $statusFillColor]],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            ]);

            $sheet->getStyle("L{$currentRow}:M{$currentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
            $sheet->getStyle("N{$currentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

            if ($hasFile) {
                $sheet->getStyle("N{$currentRow}")->getFont()->setBold(true)->setUnderline(true)->getColor()->setARGB('FF2563EB');
            } else {
                $sheet->getStyle("N{$currentRow}")->getFont()->setBold(true)->setSize(9)->getColor()->setARGB('FFDC2626');
            }

            $rowHeight = $this->calculateRowHeight([
                [$agendaNumber, $columns['B'][1]],
                [$externalNumber, $columns['D'][1]],
                [$senderName, $columns['F'][1]],
                [$subjectAndSummary, $columns['G'][1]],
                [$unitName, $columns['H'][1]],
                [$dispositionText, $columns['L'][1]],
            ]);
            $sheet->getRowDimension($currentRow)->setRowHeight($rowHeight);

            $currentRow++;
            $counter++;
        }
    }

    private function formatDispositionTrail(IncomingLetter $letter): string
    {
        $lines = ['Posisi: '.$letter->getTrackingStepLabel()];

        foreach ($letter->recipients as $recipient) {
            /** @var IncomingLetterRecipient $recipient */
            $recName = $recipient->recipient?->name ?? 'Pejabat';
            $recStatus = $recipient->status instanceof IncomingRecipientStatus
                ? ($recipient->status->getLabel() ?? $recipient->status->value)
                : (string) $recipient->status;

            $line = "#{$recipient->step_order} {$recName} [{$recStatus}]";
            if ($recipient->read_at) {
                $line .= ' ('.$recipient->read_at->format('d/m/Y H:i').')';
            }
            if (filled($recipient->disposition_note)) {
                $cleanNote = trim((string) preg_replace('/\s+/', ' ', (string) $recipient->disposition_note));
                $line .= ' — '.Str::limit($cleanNote, 80);
            }

            $lines[] = $line;
        }

        return implode("\n", $lines);
    }

    private function finalizeMainSheet(
        Worksheet $sheet,
        int $startDataRow,
        int $currentRow,
        int $totalLetters,
        int $withFileCount,
        int $completedCount
    ): void {
        $completenessPct = $totalLetters > 0 ? (int) round(($withFileCount / $totalLetters) * 100) : 0;

        $sheet->setCellValue('H4', "{$totalLetters} Surat ({$completedCount} Selesai Dibaca)");
        $sheet->setCellValue('M4', "{$withFileCount}/{$totalLetters} Berkas ({$completenessPct}%)");

        if ($totalLetters === 0) {
            $sheet->mergeCells("A{$currentRow}:N{$currentRow}");
            $sheet->setCellValue("A{$currentRow}", 'Tidak ada surat masuk yang sesuai dengan filter ekspor.');
            $sheet->getStyle("A{$currentRow}:N{$currentRow}")->applyFromArray([
                'font' => ['italic' => true, 'color' => ['argb' => 'FF64748B']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FFE2E8F0']]],
            ]);
            $sheet->getRowDimension($currentRow)->setRowHeight(32);
        } else {
            $lastDataRow = $currentRow - 1;
            $summaryRow = $currentRow;

            $sheet->mergeCells("A{$summaryRow}:E{$summaryRow}");
            $sheet->setCellValue("A{$summaryRow}", "TOTAL ({$totalLetters} Surat Masuk)");
            $sheet->setCellValue("G{$summaryRow}", "=COUNTA(G{$startDataRow}:G{$lastDataRow})");
            $sheet->setCellValue("K{$summaryRow}", "=COUNTIF(K{$startDataRow}:K{$lastDataRow},\"Selesai Dibaca\")");

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
            $sheet->getStyle("G{$summaryRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
            $sheet->getStyle("G{$summaryRow}")->getNumberFormat()->setFormatCode('0" Surat"');
            $sheet->getStyle("K{$summaryRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("K{$summaryRow}")->getNumberFormat()->setFormatCode('0" Selesai"');
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
     * @param  array<string, array{label: string, count: int, colors: array{0: string, 1: string}}>  $priorityStats
     * @param  array<int, array{code: string, name: string, total: int, completed: int, with_file: int}>  $unitStats
     */
    private function buildSummarySheet(
        Worksheet $sheet,
        array $metadata,
        int $totalLetters,
        int $withFileCount,
        int $stampedCount,
        int $completedCount,
        array $statusStats,
        array $priorityStats,
        array $unitStats
    ): void {
        $sheet->setTitle('Ringkasan Rekapitulasi');
        $sheet->setShowGridlines(true);

        $columnWidths = [
            'A' => 16,
            'B' => 38,
            'C' => 18,
            'D' => 18,
            'E' => 18,
            'F' => 20,
        ];

        foreach ($columnWidths as $col => $width) {
            $sheet->getColumnDimension($col)->setWidth($width);
        }

        $sheet->mergeCells('A1:F1');
        $sheet->setCellValue('A1', 'RINGKASAN EKSEKUTIF AGENDA SURAT MASUK');
        $sheet->getStyle('A1:F1')->applyFromArray([
            'font' => ['bold' => true, 'size' => 13, 'color' => ['argb' => 'FFFFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF1E3A8A']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER, 'indent' => 1],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(26);

        $periodLabel = (string) ($metadata['period_label'] ?? '-');
        $filterSummary = (string) ($metadata['filter_summary'] ?? 'Semua Filter Opsional');
        $infoLine = "Periode Diterima: {$periodLabel} | Filter: {$filterSummary} | Selesai Dibaca: {$completedCount}/{$totalLetters} | Berkas Stempel Agenda: {$stampedCount}/{$withFileCount}";

        $sheet->mergeCells('A2:F2');
        SpreadsheetSanitizer::setSafeStringCell($sheet, 'A2', $infoLine);
        $sheet->getStyle('A2:F2')->applyFromArray([
            'font' => ['size' => 9, 'italic' => true, 'color' => ['argb' => 'FF1E3A8A']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFEFF6FF']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER, 'indent' => 1],
            'borders' => ['bottom' => ['borderStyle' => Border::BORDER_MEDIUM, 'color' => ['argb' => 'FF1E3A8A']]],
        ]);
        $sheet->getRowDimension(2)->setRowHeight(20);
        $sheet->getRowDimension(3)->setRowHeight(10);

        $rowIdx = 4;
        $rowIdx = $this->renderEnumSummaryTable($sheet, $rowIdx, 'A. REKAPITULASI BERDASARKAN STATUS DISTRIBUSI', 'Status Distribusi', $statusStats, $totalLetters);
        $rowIdx++;
        $rowIdx = $this->renderEnumSummaryTable($sheet, $rowIdx, 'B. REKAPITULASI BERDASARKAN SIFAT SURAT', 'Sifat Surat', $priorityStats, $totalLetters);
        $rowIdx++;

        // Bagian C: Rekapitulasi per Unit Tujuan
        $sheet->mergeCells("A{$rowIdx}:F{$rowIdx}");
        $sheet->setCellValue("A{$rowIdx}", 'C. REKAPITULASI PER UNIT TUJUAN');
        $sheet->getStyle("A{$rowIdx}:F{$rowIdx}")->applyFromArray([
            'font' => ['bold' => true, 'size' => 10, 'color' => ['argb' => 'FF0F172A']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFE2E8F0']],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER, 'indent' => 1],
        ]);
        $sheet->getRowDimension($rowIdx)->setRowHeight(22);

        $rowIdx++;
        $unitHeaders = [
            'A' => 'Kode Unit',
            'B' => 'Unit Tujuan',
            'C' => 'Total Surat',
            'D' => 'Selesai Dibaca',
            'E' => 'Berkas Tersedia',
            'F' => 'Kelengkapan Berkas',
        ];
        foreach ($unitHeaders as $col => $title) {
            $sheet->setCellValue("{$col}{$rowIdx}", $title);
        }
        $sheet->getStyle("A{$rowIdx}:F{$rowIdx}")->applyFromArray([
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
            $sheet->mergeCells("A{$rowIdx}:F{$rowIdx}");
            $sheet->setCellValue("A{$rowIdx}", 'Tidak ada data unit tujuan pada ekspor ini.');
            $sheet->getStyle("A{$rowIdx}:F{$rowIdx}")->applyFromArray([
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
                $sheet->setCellValue("D{$rowIdx}", $entry['completed']);
                $sheet->setCellValue("E{$rowIdx}", $entry['with_file']);
                $sheet->setCellValue("F{$rowIdx}", "=IF(C{$rowIdx}>0,E{$rowIdx}/C{$rowIdx},0)");

                $sheet->getStyle("A{$rowIdx}:F{$rowIdx}")->applyFromArray([
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => $bgColor]],
                    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FFE2E8F0']]],
                    'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
                ]);
                $sheet->getStyle("A{$rowIdx}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle("A{$rowIdx}")->getFont()->setBold(true);
                $sheet->getStyle("B{$rowIdx}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT)->setWrapText(true);
                $sheet->getStyle("C{$rowIdx}:F{$rowIdx}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle("F{$rowIdx}")->getNumberFormat()->setFormatCode('0.0%');
                $sheet->getRowDimension($rowIdx)->setRowHeight(22);

                $rowIdx++;
            }

            $unitEndRow = $rowIdx - 1;
            $sheet->mergeCells("A{$rowIdx}:B{$rowIdx}");
            $sheet->setCellValue("A{$rowIdx}", 'TOTAL KESELURUHAN UNIT');
            $sheet->setCellValue("C{$rowIdx}", "=SUM(C{$unitStartRow}:C{$unitEndRow})");
            $sheet->setCellValue("D{$rowIdx}", "=SUM(D{$unitStartRow}:D{$unitEndRow})");
            $sheet->setCellValue("E{$rowIdx}", "=SUM(E{$unitStartRow}:E{$unitEndRow})");
            $sheet->setCellValue("F{$rowIdx}", "=IF(C{$rowIdx}>0,E{$rowIdx}/C{$rowIdx},0)");

            $sheet->getStyle("A{$rowIdx}:F{$rowIdx}")->applyFromArray([
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
            $sheet->getStyle("C{$rowIdx}:F{$rowIdx}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("F{$rowIdx}")->getNumberFormat()->setFormatCode('0.0%');
            $sheet->getRowDimension($rowIdx)->setRowHeight(23);
        }

        $sheet->freezePane('A4');
        $this->configurePrintSetup($sheet, repeatStartRow: 1, repeatEndRow: 2);
    }

    /**
     * @param  array<string, array{label: string, count: int, colors: array{0: string, 1: string}}>  $stats
     */
    private function renderEnumSummaryTable(
        Worksheet $sheet,
        int $rowIdx,
        string $sectionHeading,
        string $columnHeading,
        array $stats,
        int $totalCount
    ): int {
        $sheet->mergeCells("A{$rowIdx}:F{$rowIdx}");
        $sheet->setCellValue("A{$rowIdx}", $sectionHeading);
        $sheet->getStyle("A{$rowIdx}:F{$rowIdx}")->applyFromArray([
            'font' => ['bold' => true, 'size' => 10, 'color' => ['argb' => 'FF0F172A']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFE2E8F0']],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER, 'indent' => 1],
        ]);
        $sheet->getRowDimension($rowIdx)->setRowHeight(22);

        $rowIdx++;
        $sheet->setCellValue("A{$rowIdx}", 'Kode');
        $sheet->mergeCells("B{$rowIdx}:C{$rowIdx}");
        $sheet->setCellValue("B{$rowIdx}", $columnHeading);
        $sheet->setCellValue("D{$rowIdx}", 'Jumlah Surat');
        $sheet->mergeCells("E{$rowIdx}:F{$rowIdx}");
        $sheet->setCellValue("E{$rowIdx}", 'Persentase');

        $sheet->getStyle("A{$rowIdx}:F{$rowIdx}")->applyFromArray([
            'font' => ['bold' => true, 'size' => 10, 'color' => ['argb' => 'FFFFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF1E293B']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FF334155']]],
        ]);
        $sheet->getRowDimension($rowIdx)->setRowHeight(24);

        $rowIdx++;
        $startRow = $rowIdx;

        foreach ($stats as $code => $stat) {
            [$textColor, $fillColor] = $stat['colors'];

            $sheet->mergeCells("B{$rowIdx}:C{$rowIdx}");
            $sheet->mergeCells("E{$rowIdx}:F{$rowIdx}");

            SpreadsheetSanitizer::setSafeStringCell($sheet, "A{$rowIdx}", strtoupper((string) $code));
            SpreadsheetSanitizer::setSafeStringCell($sheet, "B{$rowIdx}", $stat['label']);
            $sheet->setCellValue("D{$rowIdx}", $stat['count']);
            $sheet->setCellValue("E{$rowIdx}", $totalCount > 0 ? $stat['count'] / $totalCount : 0);

            $sheet->getStyle("A{$rowIdx}:F{$rowIdx}")->applyFromArray([
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
            $sheet->getStyle("D{$rowIdx}:F{$rowIdx}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("E{$rowIdx}")->getNumberFormat()->setFormatCode('0.0%');
            $sheet->getRowDimension($rowIdx)->setRowHeight(22);

            $rowIdx++;
        }

        $endRow = $rowIdx - 1;
        $sheet->mergeCells("A{$rowIdx}:C{$rowIdx}");
        $sheet->mergeCells("E{$rowIdx}:F{$rowIdx}");
        $sheet->setCellValue("A{$rowIdx}", 'TOTAL');
        $sheet->setCellValue("D{$rowIdx}", "=SUM(D{$startRow}:D{$endRow})");
        $sheet->setCellValue("E{$rowIdx}", "=SUM(E{$startRow}:E{$endRow})");
        $sheet->getStyle("A{$rowIdx}:F{$rowIdx}")->applyFromArray([
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
        $sheet->getStyle("D{$rowIdx}:F{$rowIdx}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle("E{$rowIdx}")->getNumberFormat()->setFormatCode('0.0%');
        $sheet->getRowDimension($rowIdx)->setRowHeight(23);

        return $rowIdx + 1;
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function resolveStatusBadgeColors(IncomingLetterStatus $status): array
    {
        return match ($status) {
            IncomingLetterStatus::InProgress => ['FFB45309', 'FFFEF3C7'],
            IncomingLetterStatus::Completed => ['FF15803D', 'FFDCFCE7'],
        };
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function resolvePriorityBadgeColors(LetterPriority $priority): array
    {
        return match ($priority) {
            LetterPriority::Biasa => ['FF475569', 'FFF1F5F9'],
            LetterPriority::Penting => ['FF1D4ED8', 'FFDBEAFE'],
            LetterPriority::Segera => ['FFB45309', 'FFFEF3C7'],
            LetterPriority::Rahasia => ['FFB91C1C', 'FFFEE2E2'],
        };
    }
}
