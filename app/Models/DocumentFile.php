<?php

namespace App\Models;

use App\Enums\StorageDisk;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentFile extends Model
{
    use HasFactory;

    protected $fillable = [
        'document_id',
        'uploaded_by',
        'is_primary',
        'label',
        'order_index',
        'version',
        'original_file_name',
        'standardized_file_name',
        'original_file_path',
        'stamped_file_path',
        'disk',
        'mime_type',
        'file_extension',
        'file_size',
        'page_count',
        'file_hash_sha256',
        'stamped_file_hash_sha256',
        'is_active',
        'google_drive_id',
        'google_drive_url',
        'is_synced',
        'synced_at',
    ];

    protected function casts(): array
    {
        return [
            'is_primary' => 'boolean',
            'order_index' => 'integer',
            'version' => 'integer',
            'disk' => StorageDisk::class,
            'file_size' => 'integer',
            'page_count' => 'integer',
            'is_active' => 'boolean',
            'is_synced' => 'boolean',
            'synced_at' => 'datetime',
        ];
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function getEffectiveFilePath(): string
    {
        return $this->stamped_file_path ?: $this->original_file_path;
    }

    public function getEffectiveSha256Hash(): ?string
    {
        if (filled($this->stamped_file_path)) {
            return $this->stamped_file_hash_sha256 ?: $this->file_hash_sha256;
        }

        return $this->file_hash_sha256;
    }

    public function getEffectiveDownloadFileName(): string
    {
        $effectivePath = $this->getEffectiveFilePath();
        $actualExtension = strtolower((string) pathinfo($effectivePath, PATHINFO_EXTENSION));
        $baseName = $this->standardized_file_name ?: basename($effectivePath);

        if ($actualExtension === '') {
            return $baseName;
        }

        $nameWithoutExt = pathinfo($baseName, PATHINFO_FILENAME);

        return "{$nameWithoutExt}.{$actualExtension}";
    }
}
