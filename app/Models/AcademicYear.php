<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AcademicYear extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'semester',
        'start_date',
        'end_date',
        'is_active',
    ];

    protected static function booted(): void
    {
        static::saved(function (AcademicYear $academicYear): void {
            if ($academicYear->is_active) {
                static::query()
                    ->whereKeyNot($academicYear->getKey())
                    ->where('is_active', true)
                    ->update(['is_active' => false]);
            }
        });

        static::deleting(function (AcademicYear $academicYear): bool {
            if ($academicYear->getDeletionRestrictionReason()) {
                return false;
            }

            return true;
        });
    }

    public function getDeletionRestrictionReason(): ?string
    {
        $reasons = [];

        if ($this->is_active) {
            $reasons[] = 'periode ini sedang berstatus sebagai tahun akademik aktif';
        }

        if ($this->documents()->exists()) {
            $reasons[] = $this->documents()->count().' dokumen arsip';
        }

        if ($this->posts()->exists()) {
            $reasons[] = $this->posts()->count().' artikel berita';
        }

        return ! empty($reasons) ? implode(', ', $reasons) : null;
    }

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'is_active' => 'boolean',
        ];
    }

    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }

    public function posts(): HasMany
    {
        return $this->hasMany(Post::class);
    }
}
