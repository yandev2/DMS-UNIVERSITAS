<?php

declare(strict_types=1);

namespace App\Services\Concerns;

use App\Support\SpreadsheetSanitizer;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

trait BuildsStyledSpreadsheet
{
    /**
     * Merender blok pasangan label–nilai pada bagian metadata atas worksheet.
     */
    protected function renderMetadataPair(
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
            'font' => ['bold' => true, 'size' => 9, 'color' => ['argb' => 'FF475569']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFF8FAFC']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER, 'indent' => 1],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FFCBD5E1']]],
        ]);

        $sheet->getStyle($valueRange)->applyFromArray([
            'font' => ['bold' => false, 'size' => 9, 'color' => ['argb' => 'FF0F172A']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFFFFFFF']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER, 'indent' => 1],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FFCBD5E1']]],
        ]);
    }

    /**
     * Memformat ukuran berkas byte ke representasi singkat yang mudah dibaca.
     */
    protected function formatFileSize(?int $bytes): string
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
     * Memperkirakan tinggi baris yang nyaman dibaca berdasarkan panjang teks dan lebar kolom.
     *
     * @param  array<int, array{0: string, 1: int|float}>  $cellTextsAndWidths
     */
    protected function calculateRowHeight(array $cellTextsAndWidths): float
    {
        $maxLines = 1;

        foreach ($cellTextsAndWidths as [$text, $colWidth]) {
            $effectiveCharsPerLine = max(8, (int) floor($colWidth * 0.9));
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

        return min(150.0, max(24.0, ($maxLines * 14.5) + 8.0));
    }

    /**
     * Mengatur orientasi cetak A4 Landscape beserta pengulangan baris header tabel.
     */
    protected function configurePrintSetup(Worksheet $sheet, int $repeatStartRow, int $repeatEndRow): void
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
