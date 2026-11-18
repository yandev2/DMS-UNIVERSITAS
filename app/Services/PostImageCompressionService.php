<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\StorageDisk;
use App\Exceptions\ImageCompressionException;
use App\Models\PostPhoto;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\Encoders\JpegEncoder;
use Intervention\Image\Encoders\WebpEncoder;
use Intervention\Image\ImageManager;
use Intervention\Image\Interfaces\EncoderInterface;
use Intervention\Image\Interfaces\ImageInterface;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Throwable;

/**
 * Layanan khusus untuk validasi, optimasi, dan kompresi adaptif gambar pada modul Posts.
 *
 * Fitur utama:
 * - Mendukung unggahan gambar hingga 50 MB dengan hasil akhir wajib <= 1 MB.
 * - Pre-flight check dimensi dan MIME nyata (`finfo` + `getimagesize`) untuk mencegah OOM / pixel bomb.
 * - Arsitektur Smart Hybrid: file <= 1 MB diproses langsung secara sinkron (~100 ms),
 *   sedangkan file besar (> 1 MB) disimpan aman ke folder `pending/` untuk dikompresi oleh queue job.
 */
class PostImageCompressionService
{
    public const int MAX_UPLOAD_BYTES = 52_428_800; // 50 MB (50 * 1024 * 1024)

    public const int MAX_OUTPUT_BYTES = 1_048_576; // 1 MB (1024 * 1024)

    public const int TARGET_SAFE_BYTES = 1_003_520; // ~980 KB margin aman agar selalu terbaca <= 1 MB

    public const int MAX_DECODE_PIXELS = 80_000_000; // 80 MP batas aman anti decompression bomb GD

    public const int THUMBNAIL_MAX_WIDTH = 480;

    public const int THUMBNAIL_QUALITY = 76;

    public const string PENDING_DIRECTORY = 'posts/photos/pending';

    /**
     * Daftar tipe MIME gambar yang aman dan dapat didekode oleh GD PHP.
     *
     * @var list<string>
     */
    public const array ALLOWED_MIME_TYPES = [
        'image/jpeg',
        'image/png',
        'image/webp',
        'image/gif',
        'image/avif',
    ];

    /**
     * Tangga kompresi adaptif bertahap: [lebar/tinggi maksimum px, daftar kualitas encode].
     *
     * @var list<array{0: int, 1: list<int>}>
     */
    private const array COMPRESSION_LADDER = [
        [2048, [82, 72, 60]],
        [1600, [72, 58]],
        [1280, [66, 52]],
        [1024, [58, 46]],
        [800, [50, 38]],
    ];

    /**
     * Validasi header file gambar lokal sebelum didekode ke bitmap memori GD.
     *
     * @return array{width: int, height: int, mime: string, size: int}
     *
     * @throws ImageCompressionException
     */
    public function validateSourceFile(string $absolutePath): array
    {
        if (! is_file($absolutePath) || ! is_readable($absolutePath)) {
            throw ImageCompressionException::fileNotReadable();
        }

        $fileSize = @filesize($absolutePath);
        if ($fileSize === false || $fileSize <= 0) {
            throw ImageCompressionException::emptyFile();
        }

        if ($fileSize > self::MAX_UPLOAD_BYTES) {
            throw ImageCompressionException::exceedsMaxUploadSize($fileSize, self::MAX_UPLOAD_BYTES);
        }

        $detectedMime = $this->detectRealMimeType($absolutePath);
        if ($this->isHeicOrRawMime($detectedMime)) {
            throw ImageCompressionException::unsupportedHeicOrRaw($detectedMime);
        }

        if (! in_array($detectedMime, self::ALLOWED_MIME_TYPES, true)) {
            throw ImageCompressionException::unsupportedMimeType($detectedMime);
        }

        $imageInfo = @getimagesize($absolutePath);
        if ($imageInfo === false || empty($imageInfo[0]) || empty($imageInfo[1])) {
            throw ImageCompressionException::corruptOrInvalidImage();
        }

        $width = (int) $imageInfo[0];
        $height = (int) $imageInfo[1];
        $totalPixels = $width * $height;

        if ($totalPixels > self::MAX_DECODE_PIXELS) {
            throw ImageCompressionException::dimensionsTooLarge($width, $height, self::MAX_DECODE_PIXELS);
        }

        return [
            'width' => $width,
            'height' => $height,
            'mime' => $detectedMime,
            'size' => (int) $fileSize,
        ];
    }

    /**
     * Tangani file unggahan dari Filament FileUpload untuk galeri PostPhoto (jalur Smart Hybrid).
     *
     * Jika ukuran file yang masuk server sudah <= 1 MB (umumnya sudah di-pre-resize oleh browser),
     * gambar langsung dinormalisasi + dibuat thumbnail secara sinkron. Jika masih > 1 MB (s.d. 50 MB),
     * file divalidasi instan lalu disimpan sementara di `pending/` untuk dikompresi oleh Queue Job.
     *
     * @return array{path: string, thumbnail_path: ?string, mime_type: string, file_extension: string, file_size: int, width: int, height: int, is_queued: bool}
     *
     * @throws ImageCompressionException
     */
    public function prepareUploadForPostPhoto(
        UploadedFile $file,
        string $diskName = 'public',
        string $directory = 'posts/photos',
    ): array {
        return $this->withLocalTemporarySource($file, function (string $localPath) use ($diskName, $directory): array {
            $meta = $this->validateSourceFile($localPath);

            if ($meta['size'] <= self::MAX_OUTPUT_BYTES) {
                return [
                    ...$this->compressAndWriteToStorage($localPath, $diskName, $directory, true),
                    'is_queued' => false,
                ];
            }

            $uuid = (string) Str::uuid();
            $extension = $this->extensionForMime($meta['mime']);
            $pendingPath = trim(self::PENDING_DIRECTORY, '/')."/{$uuid}.{$extension}";
            $storage = Storage::disk($diskName);

            $stream = @fopen($localPath, 'rb');
            if ($stream === false) {
                throw ImageCompressionException::fileNotReadable();
            }

            try {
                $stored = $storage->put($pendingPath, $stream);
            } finally {
                if (is_resource($stream)) {
                    fclose($stream);
                }
            }

            if (! $stored) {
                throw ImageCompressionException::storageWriteFailed();
            }

            return [
                'path' => $pendingPath,
                'thumbnail_path' => null,
                'mime_type' => $meta['mime'],
                'file_extension' => $extension,
                'file_size' => $meta['size'],
                'width' => $meta['width'],
                'height' => $meta['height'],
                'is_queued' => true,
            ];
        });
    }

    /**
     * Kompresi gambar secara sinkron langsung ke <= 1 MB (digunakan untuk lampiran RichEditor).
     *
     * @return array{path: string, thumbnail_path: ?string, mime_type: string, file_extension: string, file_size: int, width: int, height: int}
     *
     * @throws ImageCompressionException
     */
    public function compressUploadedFileSync(
        UploadedFile $file,
        string $diskName = 'public',
        string $directory = 'posts/attachments',
        bool $generateThumbnail = false,
    ): array {
        return $this->withLocalTemporarySource($file, function (string $localPath) use ($diskName, $directory, $generateThumbnail): array {
            $this->validateSourceFile($localPath);

            return $this->compressAndWriteToStorage($localPath, $diskName, $directory, $generateThumbnail);
        });
    }

    /**
     * Kompresi file `PostPhoto` yang berada di folder `pending/` menjadi file final <= 1 MB + thumbnail.
     *
     * @throws ImageCompressionException
     */
    public function compressStoredPhoto(PostPhoto $photo): void
    {
        $diskName = $photo->disk instanceof StorageDisk
            ? $photo->disk->value
            : (is_string($photo->disk) && $photo->disk !== '' ? $photo->disk : 'public');

        $storage = Storage::disk($diskName);
        $sourceStoragePath = (string) $photo->file_path;

        if ($sourceStoragePath === '' || ! $storage->exists($sourceStoragePath)) {
            throw ImageCompressionException::fileNotReadable();
        }

        $result = $this->withStorageFileAsLocal($storage, $sourceStoragePath, function (string $localPath) use ($diskName): array {
            $this->validateSourceFile($localPath);

            return $this->compressAndWriteToStorage($localPath, $diskName, 'posts/photos', true);
        });

        DB::transaction(function () use ($photo, $result, $storage, $sourceStoragePath): void {
            $freshPhoto = PostPhoto::query()->lockForUpdate()->find($photo->id);
            if (! $freshPhoto || $freshPhoto->file_path !== $sourceStoragePath) {
                // Bersihkan file hasil jika record sudah berubah/dihapus selama job berjalan
                $storage->delete($result['path']);
                if (! empty($result['thumbnail_path'])) {
                    $storage->delete($result['thumbnail_path']);
                }

                return;
            }

            $freshPhoto->update([
                'file_path' => $result['path'],
                'thumbnail_path' => $result['thumbnail_path'],
                'standardized_file_name' => basename($result['path']),
                'mime_type' => $result['mime_type'],
                'file_extension' => $result['file_extension'],
                'file_size' => $result['file_size'],
                'width' => $result['width'],
                'height' => $result['height'],
            ]);
        });
    }

    /**
     * Eksekusi kompresi adaptif terhadap file gambar lokal hingga ukuran <= TARGET_SAFE_BYTES.
     *
     * @return array{binary: string, width: int, height: int, size: int, mime: string, extension: string, thumbnail_binary: ?string}
     *
     * @throws ImageCompressionException
     */
    public function compressLocalFileToTarget(string $sourcePath, bool $withThumbnail = true): array
    {
        $this->validateSourceFile($sourcePath);

        $previousMemoryLimit = ini_get('memory_limit');
        $didRaiseMemory = false;

        if ($this->shouldRaiseMemoryLimit($previousMemoryLimit, '512M')) {
            @ini_set('memory_limit', '512M');
            $didRaiseMemory = true;
        }

        $image = null;

        try {
            $manager = new ImageManager(Driver::class);
            $image = $manager->decodePath($sourcePath);

            // Jika animasi multi-frame (GIF/WebP animasi), ambil frame pertama agar kompresi <= 1 MB terjaga
            if ($image->isAnimated()) {
                $image->removeAnimation(0);
            }

            // Terapkan rotasi EXIF terlebih dahulu sebelum metadata EXIF dilepas saat encoding
            $image->orient();

            $useWebp = function_exists('imagewebp');
            $extension = $useWebp ? 'webp' : 'jpg';
            $mime = $useWebp ? 'image/webp' : 'image/jpeg';

            $bestBinary = null;
            $bestWidth = $image->width();
            $bestHeight = $image->height();
            $smallestAchievedBytes = PHP_INT_MAX;

            foreach (self::COMPRESSION_LADDER as [$maxDimension, $qualities]) {
                $image->scaleDown(width: $maxDimension, height: $maxDimension);

                foreach ($qualities as $quality) {
                    $encoded = $image->encode($this->makeEncoder($useWebp, $quality));
                    $binary = $encoded->toString();
                    $byteLength = strlen($binary);

                    if ($byteLength < $smallestAchievedBytes) {
                        $smallestAchievedBytes = $byteLength;
                    }

                    if ($byteLength <= self::TARGET_SAFE_BYTES) {
                        $bestBinary = $binary;
                        $bestWidth = $image->width();
                        $bestHeight = $image->height();

                        break 2;
                    }
                }
            }

            if ($bestBinary === null) {
                throw ImageCompressionException::targetSizeUnreachable(
                    $smallestAchievedBytes === PHP_INT_MAX ? (int) @filesize($sourcePath) : $smallestAchievedBytes,
                    self::MAX_OUTPUT_BYTES,
                );
            }

            $thumbnailBinary = null;
            if ($withThumbnail) {
                $image->scaleDown(width: self::THUMBNAIL_MAX_WIDTH, height: self::THUMBNAIL_MAX_WIDTH);
                $thumbnailBinary = $image->encode($this->makeEncoder($useWebp, self::THUMBNAIL_QUALITY))->toString();
            }

            return [
                'binary' => $bestBinary,
                'width' => $bestWidth,
                'height' => $bestHeight,
                'size' => strlen($bestBinary),
                'mime' => $mime,
                'extension' => $extension,
                'thumbnail_binary' => $thumbnailBinary,
            ];
        } catch (ImageCompressionException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            Log::warning('Kegagalan saat mendecode/mengompresi gambar Post.', [
                'source' => basename($sourcePath),
                'error' => $exception->getMessage(),
            ]);

            throw ImageCompressionException::processingFailed($exception);
        } finally {
            if ($image instanceof ImageInterface) {
                unset($image);
            }

            if ($didRaiseMemory && is_string($previousMemoryLimit) && $previousMemoryLimit !== '') {
                @ini_set('memory_limit', $previousMemoryLimit);
            }
        }
    }

    /**
     * Baca metadata dimensi gambar dari disk storage tanpa mendecode seluruh piksel ke GD.
     *
     * @return array{width: ?int, height: ?int, size: int, mime: ?string}
     */
    public function inspectStoredImageMetadata(string $diskName, string $storagePath): array
    {
        $storage = Storage::disk($diskName);
        if ($storagePath === '' || ! $storage->exists($storagePath)) {
            return [
                'width' => null,
                'height' => null,
                'size' => 0,
                'mime' => null,
            ];
        }

        $size = (int) ($storage->size($storagePath) ?: 0);
        $mime = rescue(fn () => $storage->mimeType($storagePath), report: false);

        try {
            $dimensions = $this->withStorageFileAsLocal($storage, $storagePath, function (string $localPath): array {
                $info = @getimagesize($localPath);

                return [
                    'width' => isset($info[0]) ? (int) $info[0] : null,
                    'height' => isset($info[1]) ? (int) $info[1] : null,
                    'mime' => $info['mime'] ?? null,
                ];
            });

            return [
                'width' => $dimensions['width'],
                'height' => $dimensions['height'],
                'size' => $size,
                'mime' => $mime ?: $dimensions['mime'],
            ];
        } catch (Throwable) {
            return [
                'width' => null,
                'height' => null,
                'size' => $size,
                'mime' => $mime ?: null,
            ];
        }
    }

    /**
     * Kompresi file lokal dan tulis hasilnya (+ opsional thumbnail) secara atomik ke disk storage.
     *
     * @return array{path: string, thumbnail_path: ?string, mime_type: string, file_extension: string, file_size: int, width: int, height: int}
     *
     * @throws ImageCompressionException
     */
    private function compressAndWriteToStorage(
        string $localSourcePath,
        string $diskName,
        string $directory,
        bool $generateThumbnail,
    ): array {
        $compressed = $this->compressLocalFileToTarget($localSourcePath, $generateThumbnail);

        $uuid = (string) Str::uuid();
        $cleanDirectory = trim($directory, '/');
        $extension = $compressed['extension'];

        $mainPath = "{$cleanDirectory}/{$uuid}.{$extension}";
        $thumbnailPath = $compressed['thumbnail_binary'] !== null
            ? "{$cleanDirectory}/thumbnails/{$uuid}.{$extension}"
            : null;

        $storage = Storage::disk($diskName);

        if (! $storage->put($mainPath, $compressed['binary'])) {
            throw ImageCompressionException::storageWriteFailed();
        }

        if ($thumbnailPath !== null && $compressed['thumbnail_binary'] !== null) {
            if (! $storage->put($thumbnailPath, $compressed['thumbnail_binary'])) {
                $storage->delete($mainPath);

                throw ImageCompressionException::storageWriteFailed();
            }
        }

        return [
            'path' => $mainPath,
            'thumbnail_path' => $thumbnailPath,
            'mime_type' => $compressed['mime'],
            'file_extension' => $extension,
            'file_size' => $compressed['size'],
            'width' => $compressed['width'],
            'height' => $compressed['height'],
        ];
    }

    private function makeEncoder(bool $useWebp, int $quality): EncoderInterface
    {
        if ($useWebp) {
            return new WebpEncoder(quality: $quality, strip: true);
        }

        return new JpegEncoder(quality: $quality, progressive: true, strip: true);
    }

    private function detectRealMimeType(string $absolutePath): string
    {
        if (function_exists('finfo_open')) {
            $finfo = @finfo_open(FILEINFO_MIME_TYPE);
            if ($finfo !== false) {
                $mime = @finfo_file($finfo, $absolutePath);
                finfo_close($finfo);

                if (is_string($mime) && $mime !== '') {
                    return strtolower($mime);
                }
            }
        }

        $detected = @mime_content_type($absolutePath);

        return is_string($detected) && $detected !== ''
            ? strtolower($detected)
            : 'application/octet-stream';
    }

    private function isHeicOrRawMime(string $mime): bool
    {
        return Str::contains($mime, [
            'heic',
            'heif',
            'x-adobe-dng',
            'x-canon-cr',
            'x-nikon-nef',
            'x-sony-arw',
        ]);
    }

    private function extensionForMime(string $mime): string
    {
        return match ($mime) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'image/gif' => 'gif',
            'image/avif' => 'avif',
            default => 'jpg',
        };
    }

    private function shouldRaiseMemoryLimit(string|false $currentLimit, string $targetLimit): bool
    {
        if ($currentLimit === false || $currentLimit === '-1') {
            return false;
        }

        return $this->parseIniBytes($currentLimit) < $this->parseIniBytes($targetLimit);
    }

    private function parseIniBytes(string $value): int
    {
        $trimmed = trim($value);
        if ($trimmed === '') {
            return 0;
        }

        $unit = strtolower($trimmed[-1]);
        $number = (int) $trimmed;

        return match ($unit) {
            'g' => $number * 1024 * 1024 * 1024,
            'm' => $number * 1024 * 1024,
            'k' => $number * 1024,
            default => $number,
        };
    }

    /**
     * Eksekusi callback menggunakan path lokal file TemporaryUploadedFile (aman untuk disk lokal maupun remote).
     *
     * @template T
     *
     * @param  callable(string): T  $callback
     * @return T
     */
    private function withLocalTemporarySource(UploadedFile $file, callable $callback): mixed
    {
        $realPath = rescue(fn () => $file->getRealPath(), report: false);
        if (is_string($realPath) && $realPath !== '' && is_file($realPath) && is_readable($realPath)) {
            return $callback($realPath);
        }

        $tempFile = tempnam(sys_get_temp_dir(), 'post_img_');
        if ($tempFile === false) {
            throw ImageCompressionException::fileNotReadable();
        }

        try {
            $stream = $file instanceof TemporaryUploadedFile
                ? $file->readStream()
                : @fopen((string) $file->getPathname(), 'rb');

            if (! is_resource($stream)) {
                throw ImageCompressionException::fileNotReadable();
            }

            $out = @fopen($tempFile, 'wb');
            if ($out === false) {
                fclose($stream);

                throw ImageCompressionException::fileNotReadable();
            }

            try {
                stream_copy_to_stream($stream, $out);
            } finally {
                fclose($stream);
                fclose($out);
            }

            return $callback($tempFile);
        } finally {
            @unlink($tempFile);
        }
    }

    /**
     * Salin sementara file dari Storage disk (lokal, fake, maupun S3) agar siap dibaca fungsi path-based PHP.
     *
     * @template T
     *
     * @param  callable(string): T  $callback
     * @return T
     */
    private function withStorageFileAsLocal(Filesystem $storage, string $storagePath, callable $callback): mixed
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'post_store_');
        if ($tempFile === false) {
            throw ImageCompressionException::fileNotReadable();
        }

        try {
            $stream = $storage->readStream($storagePath);
            if (! is_resource($stream)) {
                throw ImageCompressionException::fileNotReadable();
            }

            $out = @fopen($tempFile, 'wb');
            if ($out === false) {
                fclose($stream);

                throw ImageCompressionException::fileNotReadable();
            }

            try {
                stream_copy_to_stream($stream, $out);
            } finally {
                fclose($stream);
                fclose($out);
            }

            return $callback($tempFile);
        } finally {
            @unlink($tempFile);
        }
    }
}
