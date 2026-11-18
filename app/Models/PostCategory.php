<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PostCategory extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'color',
        'description',
        'order_index',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'order_index' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::deleting(function (PostCategory $category): bool {
            if ($category->getDeletionRestrictionReason()) {
                return false;
            }

            return true;
        });
    }

    public function getDeletionRestrictionReason(): ?string
    {
        if ($this->posts()->exists()) {
            return $this->posts()->count().' artikel berita yang menggunakan kategori ini';
        }

        return null;
    }

    public function posts(): HasMany
    {
        return $this->hasMany(Post::class);
    }
}
