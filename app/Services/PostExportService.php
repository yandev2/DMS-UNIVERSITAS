<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\PostPeriod;
use App\Enums\PostStatus;
use App\Models\Post;
use App\Support\SpreadsheetSanitizer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PostExportService
{
    /**
     * Membangun spreadsheet rekapitulasi berita/acara langsung dari Eloquent Builder via chunkById.
     */
    public function buildSpreadsheetFromQuery(
        Builder $query,
        ?PostPeriod $periodFilter = null,
        ?string $filterSummary = null,
        int &$exportedCount = 0
    ): Spreadsheet {
        @ini_set('memory_limit', '512M');

        $spreadsheet = new Spreadsheet;
        $spreadsheet->getDefaultStyle()->getFont()->setName('Calibri')->setSize(10)->getColor()->setARGB('FF1E293B');

        $spreadsheet->getProperties()
            ->setCreator('Universitas Bina Insan - E-Arsip')
            ->setLastModifiedBy('Tim Humas & Protokoler UNIVBI')
            ->setTitle('Rekapitulasi Publikasi Berita & Liputan Kegiatan')
            ->setDescription('Laporan rekapitulasi berita Harian, Mingguan, dan Bulanan Universitas Bina Insan.');

        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Rekapitulasi Berita');
        $sheet->setShowGridlines(true);

        $sheet->mergeCells('A1:L1');
        $sheet->setCellValue('A1', 'UNIVERSITAS BINA INSAN (UNIVBI)');
        $sheet->getStyle('A1:L1')->applyFromArray([
            'font' => ['bold' => true, 'size' => 14, 'color' => ['argb' => 'FFFFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF1E3A8A']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER, 'indent' => 1],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(28);

        $periodLabel = $periodFilter ? $periodFilter->getLabel() : 'Semua Edisi (Harian, Mingguan, Bulanan)';
        $metaLine = 'REKAPITULASI PUBLIKASI BERITA & KEGIATAN — '.$periodLabel
            .($filterSummary ? " | Filter: {$filterSummary}" : '')
            .' | Diunduh '.now()->translatedFormat('d F Y, H:i').' WIB';

        $sheet->mergeCells('A2:L2');
        SpreadsheetSanitizer::setSafeStringCell($sheet, 'A2', $metaLine);
        $sheet->getStyle('A2:L2')->applyFromArray([
            'font' => ['bold' => true, 'size' => 10, 'color' => ['argb' => 'FF1E3A8A']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFEFF6FF']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER, 'indent' => 1],
            'borders' => ['bottom' => ['borderStyle' => Border::BORDER_MEDIUM, 'color' => ['argb' => 'FF1E3A8A']]],
        ]);
        $sheet->getRowDimension(2)->setRowHeight(22);
        $sheet->getRowDimension(3)->setRowHeight(8);

        $columns = [
            'A' => ['No', 6],
            'B' => ['Edisi Berita', 17],
            'C' => ['Kategori', 20],
            'D' => ['Judul Berita & Kegiatan', 44],
            'E' => ['Unit / Pelaksana', 26],
            'F' => ['Status Moderasi', 22],
            'G' => ['Tanggal Acara', 15],
            'H' => ['Tanggal Tayang', 17],
            'I' => ['Pembaca', 12],
            'J' => ['Galeri Foto', 13],
            'K' => ['Bukti Borang', 13],
            'L' => ['Tautan Portal Publik', 24],
        ];

        $headerRow = 4;
        foreach ($columns as $col => [$label, $width]) {
            $sheet->setCellValue("{$col}{$headerRow}", $label);
            $sheet->getColumnDimension($col)->setWidth($width);
        }

        $sheet->getStyle("A{$headerRow}:L{$headerRow}")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF'], 'size' => 10],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF1E293B']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FF334155']]],
        ]);
        $sheet->getRowDimension($headerRow)->setRowHeight(26);

        $startDataRow = 5;
        $currentRow = $startDataRow;
        $counter = 1;

        (clone $query)
            ->with(['category:id,name', 'unit:id,name,code'])
            ->withCount('photos')
            ->chunkById(250, function (Collection $chunk) use ($sheet, &$currentRow, &$counter): void {
                foreach ($chunk as $post) {
                    /** @var Post $post */
                    $periodText = $post->period?->getLabel() ?? PostPeriod::Daily->getLabel();
                    $categoryName = $post->category?->name ?? '-';
                    $unitName = $post->unit
                        ? (filled($post->unit->code) ? "{$post->unit->name} ({$post->unit->code})" : $post->unit->name)
                        : '-';
                    $statusText = $post->status?->getLabel() ?? '-';
                    $eventDate = $post->event_date ? $post->event_date->format('d/m/Y') : '-';
                    $publishedAt = $post->published_at ? $post->published_at->format('d/m/Y H:i') : '-';
                    $viewsCount = (int) ($post->views_count ?? 0);
                    $photosCount = (int) ($post->photos_count ?? 0);
                    $borangLabel = $post->is_borang ? 'Ya (Borang)' : 'Tidak';

                    $sheet->setCellValue("A{$currentRow}", $counter);
                    SpreadsheetSanitizer::setSafeStringCell($sheet, "B{$currentRow}", $periodText);
                    SpreadsheetSanitizer::setSafeStringCell($sheet, "C{$currentRow}", $categoryName);
                    SpreadsheetSanitizer::setSafeStringCell($sheet, "D{$currentRow}", $post->title);
                    SpreadsheetSanitizer::setSafeStringCell($sheet, "E{$currentRow}", $unitName);
                    SpreadsheetSanitizer::setSafeStringCell($sheet, "F{$currentRow}", $statusText);
                    SpreadsheetSanitizer::setSafeStringCell($sheet, "G{$currentRow}", $eventDate);
                    SpreadsheetSanitizer::setSafeStringCell($sheet, "H{$currentRow}", $publishedAt);
                    $sheet->setCellValue("I{$currentRow}", $viewsCount);
                    $sheet->setCellValue("J{$currentRow}", $photosCount);
                    SpreadsheetSanitizer::setSafeStringCell($sheet, "K{$currentRow}", $borangLabel);

                    if ($post->status === PostStatus::Published) {
                        SpreadsheetSanitizer::setSafeHyperlinkCell($sheet, "L{$currentRow}", $post->getPublicUrl(), 'Buka Artikel ↗');
                        $sheet->getStyle("L{$currentRow}")->getFont()->setBold(true)->setUnderline(true)->getColor()->setARGB('FF2563EB');
                    } else {
                        SpreadsheetSanitizer::setSafeStringCell($sheet, "L{$currentRow}", 'Belum Tayang');
                        $sheet->getStyle("L{$currentRow}")->getFont()->setItalic(true)->getColor()->setARGB('FF64748B');
                    }

                    $bg = ($counter % 2 === 0) ? 'FFF8FAFC' : 'FFFFFFFF';
                    $sheet->getStyle("A{$currentRow}:L{$currentRow}")->applyFromArray([
                        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => $bg]],
                        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FFE2E8F0']]],
                        'alignment' => ['vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
                    ]);
                    $sheet->getStyle("A{$currentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                    $sheet->getStyle("B{$currentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                    $sheet->getStyle("F{$currentRow}:L{$currentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                    $sheet->getStyle("I{$currentRow}:J{$currentRow}")->getNumberFormat()->setFormatCode('#,##0');

                    $sheet->getRowDimension($currentRow)->setRowHeight(24);
                    $currentRow++;
                    $counter++;
                }
            }, 'posts.id', 'id');

        $totalRows = $counter - 1;
        $exportedCount = $totalRows;

        if ($currentRow === $startDataRow) {
            $sheet->mergeCells("A{$currentRow}:L{$currentRow}");
            $sheet->setCellValue("A{$currentRow}", 'Tidak ada data berita yang sesuai dengan filter saat ini.');
            $sheet->getStyle("A{$currentRow}:L{$currentRow}")->applyFromArray([
                'font' => ['italic' => true, 'color' => ['argb' => 'FF64748B']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FFE2E8F0']]],
            ]);
            $sheet->getRowDimension($currentRow)->setRowHeight(28);
        } else {
            $lastDataRow = $currentRow - 1;
            $summaryRow = $currentRow;

            $sheet->mergeCells("A{$summaryRow}:C{$summaryRow}");
            $sheet->setCellValue("A{$summaryRow}", "TOTAL PUBLIKASI ({$totalRows} Artikel)");
            $sheet->setCellValue("I{$summaryRow}", "=SUM(I{$startDataRow}:I{$lastDataRow})");
            $sheet->setCellValue("J{$summaryRow}", "=SUM(J{$startDataRow}:J{$lastDataRow})");

            $sheet->getStyle("A{$summaryRow}:L{$summaryRow}")->applyFromArray([
                'font' => ['bold' => true, 'color' => ['argb' => 'FF0F172A']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFF1F5F9']],
                'borders' => [
                    'top' => ['borderStyle' => Border::BORDER_MEDIUM, 'color' => ['argb' => 'FF1E293B']],
                    'bottom' => ['borderStyle' => Border::BORDER_MEDIUM, 'color' => ['argb' => 'FF1E293B']],
                    'allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FFCBD5E1']],
                ],
                'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
            ]);
            $sheet->getStyle("A{$summaryRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT)->setIndent(1);
            $sheet->getStyle("I{$summaryRow}:J{$summaryRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("I{$summaryRow}:J{$summaryRow}")->getNumberFormat()->setFormatCode('#,##0');
            $sheet->getRowDimension($summaryRow)->setRowHeight(24);
            $sheet->setAutoFilter("A{$headerRow}:L{$lastDataRow}");
        }

        $sheet->freezePane('A5');
        $pageSetup = $sheet->getPageSetup();
        $pageSetup->setOrientation(PageSetup::ORIENTATION_LANDSCAPE);
        $pageSetup->setPaperSize(PageSetup::PAPERSIZE_A4);
        $pageSetup->setFitToWidth(1);
        $pageSetup->setFitToHeight(0);
        $pageSetup->setRowsToRepeatAtTopByStartAndEnd($headerRow, $headerRow);

        return $spreadsheet;
    }

    /**
     * Menyimpan rekapitulasi berita sebagai berkas .xlsx di disk dan mengembalikan jumlah baris terekspor.
     */
    public function saveSpreadsheetFromQueryToFile(
        Builder $query,
        string $absolutePath,
        ?PostPeriod $periodFilter = null,
        ?string $filterSummary = null
    ): int {
        @set_time_limit(0);
        $exportedCount = 0;
        $spreadsheet = $this->buildSpreadsheetFromQuery($query, $periodFilter, $filterSummary, $exportedCount);
        $writer = new Xlsx($spreadsheet);
        $writer->save($absolutePath);
        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet, $writer);

        return $exportedCount;
    }

    /**
     * Mengunduh rekapitulasi berita sebagai StreamedResponse .xlsx.
     */
    public function downloadResponseFromQuery(
        Builder $query,
        string $filename,
        ?PostPeriod $periodFilter = null,
        ?string $filterSummary = null
    ): StreamedResponse {
        @set_time_limit(0);
        $spreadsheet = $this->buildSpreadsheetFromQuery($query, $periodFilter, $filterSummary);
        $writer = new Xlsx($spreadsheet);

        return response()->streamDownload(function () use ($writer): void {
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'max-age=0',
        ]);
    }
}
