<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Exceptions\ImageCompressionException;
use App\Models\PostPhoto;
use App\Models\User;
use App\Services\PostImageCompressionService;
use Filament\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Mengompresi foto dokumentasi berita berukuran besar (> 1 MB) di latar belakang
 * menjadi format WebP/JPEG maksimal 1 MB beserta thumbnail ringan.
 */
class CompressPostPhotoJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 120;

    public bool $failOnTimeout = true;

    public function __construct(
        public int $postPhotoId,
        public ?int $uploaderId = null,
    ) {}

    public function handle(PostImageCompressionService $compressionService): void
    {
        $photo = PostPhoto::query()->find($this->postPhotoId);

        if (! $photo) {
            if ($this->attempts() < $this->tries) {
                $this->release(1);
            }

            return;
        }

        if (! str_starts_with((string) $photo->file_path, PostImageCompressionService::PENDING_DIRECTORY.'/')) {
            return;
        }

        $compressionService->compressStoredPhoto($photo);
    }

    public function failed(Throwable $exception): void
    {
        Log::error('Background job kompresi foto berita gagal.', [
            'post_photo_id' => $this->postPhotoId,
            'uploader_id' => $this->uploaderId,
            'error' => $exception->getMessage(),
        ]);

        if (! $this->uploaderId) {
            return;
        }

        $uploader = User::query()->find($this->uploaderId);
        if (! $uploader) {
            return;
        }

        $reason = $exception instanceof ImageCompressionException
            ? $exception->getMessage()
            : 'Berkas foto gagal diproses oleh sistem antrean karena rusak atau kehabisan memori. Silakan unggah ulang foto beresolusi lebih wajar.';

        Notification::make()
            ->danger()
            ->title('Kompresi Foto Berita Gagal')
            ->body($reason)
            ->persistent()
            ->sendToDatabase($uploader);
    }
}
