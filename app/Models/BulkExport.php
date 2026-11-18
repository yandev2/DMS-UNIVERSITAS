<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Model riwayat dan manifest ekspor berkas ZIP massal latar belakang.
 *
 * @property int $id
 * @property string $uuid
 * @property int $user_id
 * @property string $module_type
 * @property array<int> $record_ids
 * @property int $total_files
 * @property string $zip_file_name
 * @property string|null $zip_file_path
 * @property string $disk
 * @property int $file_size_bytes
 * @property string $status
 * @property string|null $error_message
 * @property CarbonInterface $expires_at
 * @property CarbonInterface|null $downloaded_at
 * @property CarbonInterface $created_at
 * @property CarbonInterface $updated_at
 * @property-read User|null $user
 */
class BulkExport extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'uuid',
        'user_id',
        'module_type',
        'record_ids',
        'total_files',
        'zip_file_name',
        'zip_file_path',
        'disk',
        'file_size_bytes',
        'status',
        'error_message',
        'expires_at',
        'downloaded_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'record_ids' => 'array',
            'total_files' => 'integer',
            'file_size_bytes' => 'integer',
            'expires_at' => 'datetime',
            'downloaded_at' => 'datetime',
        ];
    }

    /**
     * Relasi ke Super Admin yang meminta ekspor berkas.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Periksa apakah berkas ekspor telah melewati batas retensi 24 jam.
     */
    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    /**
     * Periksa apakah berkas berhasil dikemas dan siap diunduh.
     */
    public function isReady(): bool
    {
        return $this->status === 'completed' && filled($this->zip_file_path);
    }

    /**
     * Dapatkan URL unduh aman untuk paket ZIP.
     */
    public function getDownloadUrl(): string
    {
        return route('admin.bulk-exports.download', ['uuid' => $this->uuid]);
    }
}
