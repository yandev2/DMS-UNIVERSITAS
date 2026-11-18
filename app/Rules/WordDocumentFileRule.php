<?php

declare(strict_types=1);

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\UploadedFile;
use ZipArchive;

/**
 * Memastikan berkas unggahan template benar-benar merupakan dokumen Microsoft Word (.docx atau .doc)
 * melalui pemeriksaan ekstensi, MIME biner nyata, struktur OOXML/OLE2, serta penolakan macro berbahaya.
 */
class WordDocumentFileRule implements ValidationRule
{
    private const string DOC_OLE2_MAGIC = "\xD0\xCF\x11\xE0\xA1\xB1\x1A\xE1";

    /**
     * @var list<string>
     */
    private const array ALLOWED_DOCX_BIN_MIMES = [
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/zip',
        'application/x-zip-compressed',
        'application/octet-stream',
    ];

    /**
     * @var list<string>
     */
    private const array ALLOWED_DOC_BIN_MIMES = [
        'application/msword',
        'application/cdfv2',
        'application/x-ole-storage',
        'application/vnd.ms-office',
        'application/octet-stream',
    ];

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (is_string($value)) {
            return;
        }

        if (! $value instanceof UploadedFile) {
            $fail('Berkas template harus berupa dokumen Microsoft Word (.docx atau .doc).');

            return;
        }

        $extension = strtolower($value->getClientOriginalExtension());
        if (! in_array($extension, ['docx', 'doc'], true)) {
            $fail('Hanya berkas Microsoft Word berekstensi .docx atau .doc yang diperbolehkan sebagai template.');

            return;
        }

        $realPath = $value->getRealPath();
        if (! is_string($realPath) || $realPath === '' || ! is_file($realPath) || ! is_readable($realPath)) {
            $fail('Berkas template tidak dapat dibaca oleh server.');

            return;
        }

        $size = @filesize($realPath);
        if ($size === false || $size <= 0) {
            $fail('Berkas Word yang diunggah kosong (0 byte) atau rusak.');

            return;
        }

        $detectedMime = $this->detectRealMimeType($realPath);

        if ($extension === 'docx') {
            $this->validateDocxStructure($realPath, $detectedMime, $fail);

            return;
        }

        $this->validateLegacyDocHeader($realPath, $detectedMime, $fail);
    }

    private function validateDocxStructure(string $realPath, string $detectedMime, Closure $fail): void
    {
        if (! in_array($detectedMime, self::ALLOWED_DOCX_BIN_MIMES, true)) {
            $fail("Berkas tidak dikenali sebagai dokumen Word .docx asli (terdeteksi tipe: {$detectedMime}).");

            return;
        }

        $zip = new ZipArchive;
        if ($zip->open($realPath) !== true) {
            $fail('Struktur berkas .docx rusak atau bukan file Microsoft Word yang valid.');

            return;
        }

        try {
            $hasContentTypes = $zip->locateName('[Content_Types].xml') !== false;
            $hasWordDocument = $zip->locateName('word/document.xml') !== false;

            if (! $hasContentTypes || ! $hasWordDocument) {
                $fail('Isi berkas bukan dokumen Microsoft Word (.docx) yang valid (misalnya arsip ZIP, Excel, atau PowerPoint yang diganti namanya).');

                return;
            }

            if ($zip->locateName('word/vbaProject.bin') !== false) {
                $fail('Berkas Word yang mengandung macro (VBA/.docm) ditolak demi keamanan sistem.');
            }
        } finally {
            $zip->close();
        }
    }

    private function validateLegacyDocHeader(string $realPath, string $detectedMime, Closure $fail): void
    {
        if (! in_array($detectedMime, self::ALLOWED_DOC_BIN_MIMES, true)) {
            $fail("Berkas tidak dikenali sebagai dokumen Word .doc asli (terdeteksi tipe: {$detectedMime}).");

            return;
        }

        $handle = @fopen($realPath, 'rb');
        if ($handle === false) {
            $fail('Berkas Word .doc gagal dibaca oleh server.');

            return;
        }

        try {
            $header = fread($handle, 8);
        } finally {
            fclose($handle);
        }

        if (! is_string($header) || strlen($header) < 8 || $header !== self::DOC_OLE2_MAGIC) {
            $fail('Struktur berkas .doc bukan dokumen Microsoft Word 97-2003 yang valid.');
        }
    }

    private function detectRealMimeType(string $realPath): string
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
