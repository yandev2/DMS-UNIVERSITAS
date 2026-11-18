<?php

declare(strict_types=1);

namespace App\Support;

use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class SpreadsheetSanitizer
{
    /**
     * Memeriksa apakah nilai teks diawali karakter pemicu formula spreadsheet selain placeholder '-'.
     */
    public static function needsFormulaNeutralization(?string $value): bool
    {
        if ($value === null || $value === '') {
            return false;
        }

        $trimmed = trim($value);
        if ($trimmed === '' || $trimmed === '-') {
            return false;
        }

        return preg_match('/^[\x00-\x20]*[=+\-@\t\r]/u', $value) === 1;
    }

    /**
     * Menetralkan teks masukan pengguna agar tidak dievaluasi sebagai formula di Excel/LibreOffice/CSV.
     */
    public static function sanitizeFormulaText(?string $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        if (self::needsFormulaNeutralization($value)) {
            return "'".$value;
        }

        return $value;
    }

    /**
     * Menulis teks secara eksplisit sebagai string murni dengan proteksi Formula Injection ganda.
     */
    public static function setSafeStringCell(Worksheet $sheet, string $coordinate, mixed $value): void
    {
        $stringValue = $value === null ? '' : (string) $value;
        $needsNeutralization = self::needsFormulaNeutralization($stringValue);
        $safeValue = $needsNeutralization ? "'".$stringValue : $stringValue;

        $sheet->setCellValueExplicit($coordinate, $safeValue, DataType::TYPE_STRING);

        if ($needsNeutralization) {
            $sheet->getStyle($coordinate)->setQuotePrefix(true);
        }
    }

    /**
     * Menulis rumus =HYPERLINK() hanya jika URL valid (http/https) dan aman dari injection kutipan ganda.
     */
    public static function setSafeHyperlinkCell(Worksheet $sheet, string $coordinate, string $url, string $label): void
    {
        $cleanUrl = trim((string) preg_replace('/[\x00-\x1F\x7F]/', '', $url));
        $cleanLabel = trim((string) preg_replace('/[\x00-\x1F\x7F]/', '', $label));

        if ($cleanUrl === '' || preg_match('/^https?:\/\//i', $cleanUrl) !== 1) {
            self::setSafeStringCell($sheet, $coordinate, $cleanLabel !== '' ? $cleanLabel : '-');

            return;
        }

        $escapedUrl = str_replace('"', '%22', $cleanUrl);
        $escapedLabel = str_replace('"', '""', $cleanLabel);

        $sheet->setCellValue($coordinate, "=HYPERLINK(\"{$escapedUrl}\", \"{$escapedLabel}\")");
    }
}
