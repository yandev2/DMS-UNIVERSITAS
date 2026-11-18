<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;
use Throwable;

/**
 * Exception khusus untuk kegagalan validasi atau kompresi gambar pada modul Posts.
 *
 * Setiap pesan disusun dalam bahasa yang jelas bagi pengguna akhir agar mereka
 * mengetahui penyebab pasti kegagalan upload dan langkah perbaikannya.
 */
class ImageCompressionException extends RuntimeException
{
    public static function fileNotReadable(): self
    {
        return new self('Berkas gambar tidak ditemukan atau gagal dibaca oleh server saat proses upload.');
    }

    public static function emptyFile(): self
    {
        return new self('Berkas gambar kosong (0 byte) atau rusak saat pengiriman dari browser.');
    }

    public static function exceedsMaxUploadSize(int $sizeBytes, int $maxBytes): self
    {
        $actualMb = number_format($sizeBytes / 1_048_576, 1, ',', '.');
        $limitMb = (int) round($maxBytes / 1_048_576);

        return new self("Ukuran foto terlalu besar ({$actualMb} MB). Batas maksimal unggahan adalah {$limitMb} MB.");
    }

    public static function unsupportedHeicOrRaw(string $mime): self
    {
        return new self("Format foto ({$mime}) belum didukung langsung oleh server. Harap ekspor atau ubah foto iPhone/kamera ke format JPG, PNG, atau WebP terlebih dahulu.");
    }

    public static function unsupportedMimeType(string $mime): self
    {
        return new self("Format berkas tidak didukung ({$mime}). Unggah hanya file foto asli berformat JPG, PNG, WebP, GIF, atau AVIF.");
    }

    public static function corruptOrInvalidImage(): self
    {
        return new self('File tidak dapat dibaca sebagai gambar yang valid. Kemungkinan file korup, terpotong saat upload, atau bukan file gambar asli.');
    }

    public static function dimensionsTooLarge(int $width, int $height, int $maxPixels): self
    {
        $megapixels = number_format(($width * $height) / 1_000_000, 1, ',', '.');
        $maxMegapixels = (int) round($maxPixels / 1_000_000);

        return new self("Resolusi dimensi foto terlalu ekstrem ({$width} × {$height} piksel / {$megapixels} MP). Batas aman sistem adalah {$maxMegapixels} MP agar memori server tidak kehabisan.");
    }

    public static function processingFailed(?Throwable $previous = null): self
    {
        return new self(
            'Sistem gagal memproses atau mengompresi gambar ini karena struktur file rusak atau memori tidak mencukupi. Coba simpan ulang foto sebagai JPG lalu unggah kembali.',
            0,
            $previous,
        );
    }

    public static function targetSizeUnreachable(int $lowestBytes, int $targetBytes): self
    {
        $achievedKb = number_format($lowestBytes / 1024, 0, ',', '.');
        $targetMb = number_format($targetBytes / 1_048_576, 1, ',', '.');

        return new self("Gambar terlalu kompleks untuk dikompresi ke bawah {$targetMb} MB (ukuran terkecil yang tercapai {$achievedKb} KB). Coba perkecil resolusi atau potong foto terlebih dahulu.");
    }

    public static function storageWriteFailed(): self
    {
        return new self('Hasil kompresi gambar gagal disimpan ke disk penyimpanan server. Hubungi administrator untuk memeriksa kapasitas atau izin folder storage.');
    }
}
