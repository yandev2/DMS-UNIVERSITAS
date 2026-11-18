<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Document;
use App\Models\DocumentCategory;
use App\Models\Unit;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class DocumentNumberingService
{
    public function __construct(
        protected DocumentNamingService $namingService
    ) {}

    /**
     * Membangkitkan nomor surat resmi secara atomik dengan pessimistic locking (lockForUpdate).
     * Menjamin tidak ada nomor urut ganda atau nomor loncat saat terjadi pengesahan bersamaan.
     */
    public function generateNumber(Document $document): string
    {
        // Jika dokumen diatur sebagai nomor manual dan sudah memiliki nomor fisik
        if ($document->is_manual_number && filled($document->document_number)) {
            return $document->document_number;
        }

        return DB::transaction(function () use ($document): string {
            $category = $document->category ?? DocumentCategory::findOrFail($document->document_category_id);
            $unit = $document->unit ?? Unit::findOrFail($document->unit_id);

            $issuedDate = $document->issued_date ?? now();
            $year = (int) $issuedDate->format('Y');
            $month = (int) $issuedDate->format('m');

            // Row-level lock pada Unit untuk menjamin penomoran atomik bebas race-condition (PostgreSQL & MySQL compliant)
            Unit::query()->where('id', $unit->id)->lockForUpdate()->first();

            // Ambil nomor urut tertinggi untuk kombinasi kategori, unit, dan tahun tersebut (termasuk dokumen yang diarsip/dihapus)
            $maxSeq = (int) (Document::withTrashed()
                ->where('document_category_id', $category->id)
                ->where('unit_id', $unit->id)
                ->where('sequence_year', $year)
                ->max('sequence_number') ?? 0);

            $nextSeq = $maxSeq + 1;

            $unitCode = $this->namingService->resolveUnitCode($unit);
            $categoryCode = $this->namingService->resolveCategoryCode($category);
            $pattern = $category->number_format ?: '{seq}/UNIVBI.{unit}/{cat}/{roman_month}/{year}';

            // Loop pengaman jika nomor yang dihasilkan sudah pernah dipakai secara manual oleh pengguna lain atau dokumen soft-deleted
            do {
                $docNumber = $this->formatNumber(
                    pattern: $pattern,
                    sequence: $nextSeq,
                    unitCode: $unitCode,
                    categoryCode: $categoryCode,
                    month: $month,
                    year: $year
                );

                $exists = Document::withTrashed()
                    ->where('document_number', $docNumber)
                    ->where('id', '!=', $document->id)
                    ->exists();

                if ($exists) {
                    $nextSeq++;
                }
            } while ($exists);

            $document->update([
                'sequence_year' => $year,
                'sequence_number' => $nextSeq,
                'document_number' => $docNumber,
            ]);

            return $docNumber;
        });
    }

    /**
     * Mempratinjau format nomor surat berikutnya tanpa mengubah data database (read-only preview).
     */
    public function previewNextNumber(
        DocumentCategory|int $category,
        Unit|int $unit,
        ?int $year = null,
        ?int $month = null
    ): string {
        $categoryModel = $category instanceof DocumentCategory ? $category : DocumentCategory::find($category);
        $unitModel = $unit instanceof Unit ? $unit : Unit::find($unit);

        if (! $categoryModel || ! $unitModel) {
            throw new InvalidArgumentException('Kategori dokumen dan unit organisasi wajib valid untuk mempratinjau format penomoran.');
        }

        $year ??= (int) date('Y');
        $month ??= (int) date('m');

        $maxSeq = Document::withTrashed()
            ->where('document_category_id', $categoryModel->id)
            ->where('unit_id', $unitModel->id)
            ->where('sequence_year', $year)
            ->max('sequence_number') ?? 0;

        $nextSeq = $maxSeq + 1;
        $unitCode = $this->namingService->resolveUnitCode($unitModel);
        $categoryCode = $this->namingService->resolveCategoryCode($categoryModel);
        $pattern = $categoryModel->number_format ?: '{seq}/UNIVBI.{unit}/{cat}/{roman_month}/{year}';

        return $this->formatNumber(
            pattern: $pattern,
            sequence: $nextSeq,
            unitCode: $unitCode,
            categoryCode: $categoryCode,
            month: $month,
            year: $year
        );
    }

    /**
     * Mengganti token pada pola format penomoran surat.
     */
    public function formatNumber(
        string $pattern,
        int $sequence,
        string $unitCode,
        string $categoryCode,
        int $month,
        int $year
    ): string {
        $romanMonth = $this->toRoman($month);
        $twoDigitMonth = str_pad((string) $month, 2, '0', STR_PAD_LEFT);
        $seq3 = str_pad((string) $sequence, 3, '0', STR_PAD_LEFT);
        $seq4 = str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);

        return strtr($pattern, [
            '{seq:4}' => $seq4,
            '{seq}' => $seq3,
            '{unit}' => $unitCode,
            '{cat}' => $categoryCode,
            '{roman_month}' => $romanMonth,
            '{month}' => $twoDigitMonth,
            '{year}' => (string) $year,
        ]);
    }

    /**
     * Konversi angka bulan kalender (1 - 12) ke angka romawi standar surat dinas.
     */
    public function toRoman(int $month): string
    {
        $map = [
            1 => 'I',
            2 => 'II',
            3 => 'III',
            4 => 'IV',
            5 => 'V',
            6 => 'VI',
            7 => 'VII',
            8 => 'VIII',
            9 => 'IX',
            10 => 'X',
            11 => 'XI',
            12 => 'XII',
        ];

        return $map[$month] ?? (string) $month;
    }
}
