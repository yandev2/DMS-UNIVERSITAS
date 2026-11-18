<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\DocumentCategory;
use App\Models\Unit;
use Illuminate\Support\Str;

class DocumentNamingService
{
    /**
     * Menghasilkan path direktori penyimpanan sharding: documents/{Tahun}/{Bulan}/{KodeUnit}
     */
    public function generateDirectory(Unit|int|string|null $unit = null, ?int $year = null, ?int $month = null): string
    {
        $year ??= (int) date('Y');
        $month ??= (int) date('m');
        $unitCode = $this->resolveUnitCode($unit);

        $paddedMonth = str_pad((string) $month, 2, '0', STR_PAD_LEFT);

        return "documents/{$year}/{$paddedMonth}/{$unitCode}";
    }

    /**
     * Menghasilkan nama file baku: [Tahun]-[KodeUnit]-[KodeKategori]-[SlugJudul].[ext]
     * Contoh: 2026-TI-SK-pengangkatan-dosen-pembimbing.pdf
     */
    public function generateStandardFileName(
        string $title,
        Unit|int|string|null $unit,
        DocumentCategory|int|string|null $category,
        string $extension,
        ?int $year = null
    ): string {
        $year ??= (int) date('Y');
        $unitCode = $this->resolveUnitCode($unit);
        $categoryCode = $this->resolveCategoryCode($category);

        $cleanTitle = Str::slug($title);
        if (blank($cleanTitle)) {
            $cleanTitle = 'dokumen-arsip';
        }

        // Batasi panjang slug judul agar total nama file tidak melebihi batas sistem berkas (max 80 chars untuk slug)
        $shortTitleSlug = Str::limit($cleanTitle, 80, '');

        $cleanExtension = ltrim(strtolower(trim($extension)), '.');
        if (blank($cleanExtension)) {
            $cleanExtension = 'pdf';
        }

        return "{$year}-{$unitCode}-{$categoryCode}-{$shortTitleSlug}.{$cleanExtension}";
    }

    /**
     * Menghasilkan full storage path: documents/{Tahun}/{Bulan}/{KodeUnit}/[Tahun]-[KodeUnit]-[KodeKategori]-[SlugJudul].[ext]
     */
    public function generateStoragePath(
        string $title,
        Unit|int|string|null $unit,
        DocumentCategory|int|string|null $category,
        string $extension,
        ?int $year = null,
        ?int $month = null
    ): string {
        $directory = $this->generateDirectory($unit, $year, $month);
        $fileName = $this->generateStandardFileName($title, $unit, $category, $extension, $year);

        return "{$directory}/{$fileName}";
    }

    /**
     * Resolusi kode unit organisasi.
     */
    public function resolveUnitCode(Unit|int|string|null $unit): string
    {
        if ($unit instanceof Unit) {
            $code = $unit->code;
        } elseif (is_numeric($unit)) {
            $foundUnit = Unit::find($unit);
            $code = $foundUnit?->code ?? (string) $unit;
        } elseif (is_string($unit) && filled($unit)) {
            $code = $unit;
        } else {
            $code = 'UMUM';
        }

        $sanitized = preg_replace('/[^A-Za-z0-9_-]/', '', strtoupper(trim($code)));

        return filled($sanitized) ? $sanitized : 'UMUM';
    }

    /**
     * Resolusi kode kategori dokumen.
     */
    public function resolveCategoryCode(DocumentCategory|int|string|null $category): string
    {
        if ($category instanceof DocumentCategory) {
            $code = $category->code;
        } elseif (is_numeric($category)) {
            $foundCat = DocumentCategory::find($category);
            $code = $foundCat?->code ?? (string) $category;
        } elseif (is_string($category) && filled($category)) {
            $code = $category;
        } else {
            $code = 'BERKAS';
        }

        $sanitized = preg_replace('/[^A-Za-z0-9_-]/', '', strtoupper(trim($code)));

        return filled($sanitized) ? $sanitized : 'BERKAS';
    }
}
