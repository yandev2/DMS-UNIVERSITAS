<?php

declare(strict_types=1);

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;

/**
 * Memastikan berkas unggahan scan final benar-benar merupakan dokumen PDF asli
 * melalui pemeriksaan ekstensi, MIME nyata, dan magic header %PDF-.
 */
class PdfDocumentFileRule implements ValidationRule
{
    private const string PDF_MAGIC_PREFIX = '%PDF-';

    /**
     * @var list<string>
     */
    private const array ALLOWED_PDF_MIMES = [
        'application/pdf',
        'application/x-pdf',
        'application/acrobat',
        'application/octet-stream',
    ];

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (is_string($value)) {
            return;
        }

        if (! $value instanceof UploadedFile) {
            $fail('Berkas scan final harus berupa dokumen PDF (.pdf).');

            return;
        }

        $extension = strtolower($value->getClientOriginalExtension());
        if ($extension !== 'pdf') {
            $fail('Hanya berkas berekstensi .pdf yang diperbolehkan untuk arsip scan TTD basah.');

            return;
        }

        $realPath = $value->getRealPath();
        if (! is_string($realPath) || $realPath === '' || ! is_file($realPath) || ! is_readable($realPath)) {
            $fail('Berkas PDF tidak dapat dibaca oleh server.');

            return;
        }

        $error = self::inspectLocalPdf($realPath);
        if ($error !== null) {
            $fail($error);
        }
    }

    /**
     * Memvalidasi berkas PDF yang telah tersimpan di disk storage privat sebelum difinalisasi.
     *
     * @throws InvalidArgumentException
     */
    public static function assertValidStoredPdf(string $disk, string $relativePath): void
    {
        $storage = Storage::disk($disk);

        if (! $storage->exists($relativePath)) {
            throw new InvalidArgumentException('Berkas PDF scan final tidak ditemukan pada sistem penyimpanan.');
        }

        $ext = strtolower((string) pathinfo($relativePath, PATHINFO_EXTENSION));
        if ($ext !== 'pdf') {
            throw new InvalidArgumentException('Berkas scan final wajib berekstensi .pdf.');
        }

        $localPath = $storage->path($relativePath);
        if (is_file($localPath)) {
            $error = self::inspectLocalPdf($localPath);
            if ($error !== null) {
                throw new InvalidArgumentException($error);
            }

            return;
        }

        $content = $storage->get($relativePath);
        if (! is_string($content) || $content === '' || ! str_starts_with(ltrim($content, "\xEF\xBB\xBF\r\n\t "), self::PDF_MAGIC_PREFIX)) {
            throw new InvalidArgumentException('Berkas tidak memiliki struktur PDF yang valid.');
        }
    }

    private static function inspectLocalPdf(string $realPath): ?string
    {
        $size = @filesize($realPath);
        if ($size === false || $size <= 0) {
            return 'Berkas PDF yang diunggah kosong (0 byte) atau rusak.';
        }

        $detectedMime = self::detectRealMimeType($realPath);
        if (! in_array($detectedMime, self::ALLOWED_PDF_MIMES, true)) {
            return "Berkas tidak dikenali sebagai dokumen PDF asli (terdeteksi tipe: {$detectedMime}).";
        }

        $handle = @fopen($realPath, 'rb');
        if ($handle === false) {
            return 'Berkas PDF gagal dibaca oleh server.';
        }

        try {
            $header = fread($handle, 16);
        } finally {
            fclose($handle);
        }

        if (! is_string($header)) {
            return 'Struktur berkas tidak dikenali sebagai dokumen PDF yang valid.';
        }

        $trimmedHeader = ltrim($header, "\xEF\xBB\xBF\r\n\t ");
        if (! str_starts_with($trimmedHeader, self::PDF_MAGIC_PREFIX)) {
            return 'Isi berkas bukan dokumen PDF yang valid (header %PDF- tidak ditemukan).';
        }

        return null;
    }

    private static function detectRealMimeType(string $realPath): string
    {
        if (function_exists('finfo_open')) {
            $finfo = @finfo_open(FILEINFO_MIME_TYPE);
            if ($finfo !== false) {
                $mime = @finfo_file($finfo, $realPath);
                finfo_close($finfo);

                if (is_string($mime) && $mime !== '') {
                    return strtolower($mime);
                }
            }
        }

        $detected = @mime_content_type($realPath);

        return is_string($detected) && $detected !== ''
            ? strtolower($detected)
            : 'application/octet-stream';
    }
}
