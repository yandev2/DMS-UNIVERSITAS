<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class UserShortcut extends Model
{
    use HasFactory;

    public const MAX_PER_USER = 12;

    protected $fillable = [
        'user_id',
        'title',
        'url',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (UserShortcut $shortcut): void {
            if (empty($shortcut->user_id) && auth()->check()) {
                $shortcut->user_id = (int) auth()->id();
            }

            if ($shortcut->sort_order === null || $shortcut->sort_order === 0) {
                $maxOrder = (int) static::query()
                    ->where('user_id', $shortcut->user_id)
                    ->max('sort_order');
                $shortcut->sort_order = $maxOrder + 1;
            }
        });
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Mengekstrak nama host/domain ringkas dari URL tujuan untuk ditampilkan pada kartu pintasan.
     */
    public function getHostDomain(): string
    {
        $host = parse_url($this->url, PHP_URL_HOST);
        if (! is_string($host) || $host === '') {
            return Str::limit($this->url, 32);
        }

        return (string) preg_replace('/^www\./i', '', strtolower($host));
    }

    /**
     * Mendapatkan inisial huruf pertama judul untuk kotak ikon pintasan.
     */
    public function getInitial(): string
    {
        $clean = trim((string) preg_replace('/[^A-Za-z0-9]/u', '', $this->title));

        return $clean !== '' ? strtoupper(mb_substr($clean, 0, 1)) : 'W';
    }
}
