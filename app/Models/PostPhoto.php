<?php

namespace App\Models;

use App\Enums\StorageDisk;
use App\Jobs\CompressPostPhotoJob;
use App\Services\PostImageCompressionService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PostPhoto extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'post_id',
        'uploaded_by',
        'original_file_name',
        'standardized_file_name',
        'file_path',
        'thumbnail_path',
        'disk',
        'caption',
        'alt_text',
        'mime_type',
        'file_extension',
        'file_size',
        'width',
        'height',
        'order_index',
        'is_cover',
        'downloads_count',
    ];

    protected static function booted(): void
    {
        static::creating(function (PostPhoto $photo): void {
            if (empty($photo->uuid)) {
                $photo->uuid = (string) Str::uuid();
            }

            if (empty($photo->disk)) {
                $photo->disk = StorageDisk::Public;
            }

            if (empty($photo->uploaded_by) && auth()->check()) {
                $photo->uploaded_by = auth()->id();
            }

            static::hydrateFileAttributesFromStorage($photo, false);
        });

        static::updating(function (PostPhoto $photo): void {
            if ($photo->isDirty('file_path')) {
                $photo->thumbnail_path = $photo->isDirty('thumbnail_path') ? $photo->thumbnail_path : null;
                static::hydrateFileAttributesFromStorage($photo, ! $photo->isDirty('file_size'));
            }
        });

        static::updated(function (PostPhoto $photo): void {
            if (! $photo->wasChanged('file_path')) {
                return;
            }

            $oldPath = $photo->getOriginal('file_path');
            $oldThumbnail = $photo->getOriginal('thumbnail_path');

            static::deleteStoredFiles(
                $photo,
                array_filter([
                    is_string($oldPath) && $oldPath !== $photo->file_path ? $oldPath : null,
                    is_string($oldThumbnail) && $oldThumbnail !== $photo->thumbnail_path ? $oldThumbnail : null,
                ]),
            );
        });

        static::saved(function (PostPhoto $photo): void {
            $post = $photo->post;
            if ($post) {
                $updateData = [
                    'photos_count' => $post->photos()->count(),
                ];

                $oldFilePath = $photo->getOriginal('file_path');
                $oldThumbPath = $photo->getOriginal('thumbnail_path');
                $pointsToReplacedPhoto = in_array($post->thumbnail_url, array_filter([$oldFilePath, $oldThumbPath]), true);

                if ($photo->is_cover || empty($post->thumbnail_url) || $pointsToReplacedPhoto) {
                    $updateData['thumbnail_url'] = $photo->thumbnail_path ?: $photo->file_path;
                    if (! empty($photo->caption)) {
                        $updateData['thumbnail_caption'] = $photo->caption;
                    }
                }

                $post->updateQuietly($updateData);
            }

            if (
                ($photo->wasRecentlyCreated || $photo->wasChanged('file_path'))
                && str_starts_with((string) $photo->file_path, PostImageCompressionService::PENDING_DIRECTORY.'/')
            ) {
                CompressPostPhotoJob::dispatch($photo->id, $photo->uploaded_by);
            }
        });

        static::deleted(function (PostPhoto $photo): void {
            static::deleteStoredFiles($photo, array_filter([$photo->file_path, $photo->thumbnail_path]));

            $post = $photo->post;
            if ($post) {
                $firstPhoto = $post->photos()->orderBy('order_index')->first();
                $post->updateQuietly([
                    'photos_count' => $post->photos()->count(),
                    'thumbnail_url' => $firstPhoto?->thumbnail_path ?: $firstPhoto?->file_path,
                    'thumbnail_caption' => $firstPhoto?->caption,
                ]);
            }
        });
    }

    /**
     * Lengkapi metadata berkas foto (`file_size`, `mime_type`, `width`, `height`, `thumbnail_path`)
     * dari penyimpanan jika belum terisi.
     */
    protected static function hydrateFileAttributesFromStorage(PostPhoto $photo, bool $forceRefresh): void
    {
        if (empty($photo->file_path)) {
            return;
        }

        $diskName = static::resolveDiskName($photo);
        $storage = Storage::disk($diskName);
        $baseName = basename($photo->file_path);

        if (empty($photo->original_file_name)) {
            $photo->original_file_name = $baseName;
        }

        if ($forceRefresh || empty($photo->standardized_file_name)) {
            $photo->standardized_file_name = $baseName;
        }

        if ($forceRefresh || empty($photo->file_extension)) {
            $photo->file_extension = strtolower(pathinfo($baseName, PATHINFO_EXTENSION) ?: 'jpg');
        }

        if (empty($photo->thumbnail_path)) {
            $candidateThumb = 'posts/photos/thumbnails/'.pathinfo($baseName, PATHINFO_FILENAME).'.'.pathinfo($baseName, PATHINFO_EXTENSION);
            if ($candidateThumb !== $photo->file_path && $storage->exists($candidateThumb)) {
                $photo->thumbnail_path = $candidateThumb;
            }
        }

        $needsMetadata = $forceRefresh
            || empty($photo->file_size)
            || empty($photo->mime_type)
            || empty($photo->width)
            || empty($photo->height);

        if ($needsMetadata && $storage->exists($photo->file_path)) {
            $meta = app(PostImageCompressionService::class)->inspectStoredImageMetadata($diskName, $photo->file_path);

            if ($forceRefresh || empty($photo->file_size)) {
                $photo->file_size = $meta['size'];
            }

            if ($forceRefresh || empty($photo->mime_type)) {
                $photo->mime_type = $meta['mime'] ?: 'image/jpeg';
            }

            if ($forceRefresh || empty($photo->width)) {
                $photo->width = $meta['width'];
            }

            if ($forceRefresh || empty($photo->height)) {
                $photo->height = $meta['height'];
            }
        } else {
            $photo->file_size ??= 0;
            $photo->mime_type ??= 'image/jpeg';
        }
    }

    /**
     * Hapus berkas fisik dari disk secara aman saat foto diperbarui atau dihapus.
     *
     * @param  array<int, string>  $paths
     */
    protected static function deleteStoredFiles(PostPhoto $photo, array $paths): void
    {
        if ($paths === []) {
            return;
        }

        $storage = Storage::disk(static::resolveDiskName($photo));

        foreach ($paths as $path) {
            if (is_string($path) && $path !== '' && ! Str::startsWith($path, ['http://', 'https://'])) {
                rescue(fn () => $storage->delete($path), report: false);
            }
        }
    }

    protected static function resolveDiskName(PostPhoto $photo): string
    {
        if ($photo->disk instanceof StorageDisk) {
            return $photo->disk->value;
        }

        return is_string($photo->disk) && $photo->disk !== '' ? $photo->disk : 'public';
    }

    protected function casts(): array
    {
        return [
            'disk' => StorageDisk::class,
            'file_size' => 'integer',
            'width' => 'integer',
            'height' => 'integer',
            'order_index' => 'integer',
            'is_cover' => 'boolean',
            'downloads_count' => 'integer',
        ];
    }

    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function getUrl(): string
    {
        if (Str::startsWith($this->file_path, ['http://', 'https://'])) {
            return $this->file_path;
        }

        $diskName = $this->disk?->value ?? (is_string($this->disk) ? $this->disk : 'public');

        return Storage::disk($diskName)->url($this->file_path);
    }

    public function getThumbnailUrl(): string
    {
        $path = $this->thumbnail_path ?: $this->file_path;

        if (Str::startsWith($path, ['http://', 'https://'])) {
            return $path;
        }

        $diskName = $this->disk?->value ?? (is_string($this->disk) ? $this->disk : 'public');

        return Storage::disk($diskName)->url($path);
    }
}
