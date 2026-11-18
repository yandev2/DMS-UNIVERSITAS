<?php

namespace App\Models;

use App\Enums\DocumentStatus;
use App\Enums\PostPeriod;
use App\Enums\PostStatus;
use App\Enums\VisibilityLevel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class Post extends Model
{
    use HasFactory, LogsActivity, SoftDeletes;

    protected $fillable = [
        'title',
        'slug',
        'post_category_id',
        'period',
        'unit_id',
        'academic_year_id',
        'author_id',
        'thumbnail_url',
        'thumbnail_caption',
        'event_date',
        'event_location',
        'excerpt',
        'content',
        'status',
        'submitted_at',
        'approved_by',
        'approved_at',
        'moderation_note',
        'published_at',
        'photos_count',
        'allow_public_zip_download',
        'views_count',
        'shares_count',
        'zip_downloads_count',
        'is_featured',
        'is_pinned',
        'is_borang',
    ];

    protected static function booted(): void
    {
        static::creating(function (Post $post): void {
            if (empty($post->slug) && ! empty($post->title)) {
                $post->slug = Str::slug($post->title);
            }

            if (empty($post->period)) {
                $post->period = PostPeriod::Daily;
            }
        });

        static::saved(static function (): void {
            self::invalidateStatsCache();
        });
        static::deleted(static function (): void {
            self::invalidateStatsCache();
        });
        static::restored(static function (): void {
            self::invalidateStatsCache();
        });
    }

    public static function getStatsCacheVersion(): int
    {
        return (int) Cache::rememberForever('post_stats_version', static fn (): int => 1);
    }

    public static function invalidateStatsCache(): void
    {
        Cache::forever('post_stats_version', self::getStatsCacheVersion() + 1);
    }

    protected function casts(): array
    {
        return [
            'event_date' => 'date',
            'period' => PostPeriod::class,
            'status' => PostStatus::class,
            'submitted_at' => 'datetime',
            'approved_at' => 'datetime',
            'published_at' => 'datetime',
            'photos_count' => 'integer',
            'allow_public_zip_download' => 'boolean',
            'views_count' => 'integer',
            'shares_count' => 'integer',
            'zip_downloads_count' => 'integer',
            'is_featured' => 'boolean',
            'is_pinned' => 'boolean',
            'is_borang' => 'boolean',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('cms_moderation')
            ->logOnly([
                'title',
                'post_category_id',
                'period',
                'unit_id',
                'status',
                'approved_by',
                'published_at',
                'is_featured',
                'is_pinned',
                'is_borang',
            ])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(PostCategory::class, 'post_category_id');
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function documents(): BelongsToMany
    {
        return $this->belongsToMany(Document::class, 'post_document')
            ->using(PostDocument::class)
            ->withPivot(['id', 'custom_button_label', 'order_index'])
            ->orderByPivot('order_index')
            ->withTimestamps();
    }

    public function postDocuments(): HasMany
    {
        return $this->hasMany(PostDocument::class, 'post_id')->orderBy('order_index');
    }

    public function photos(): HasMany
    {
        return $this->hasMany(PostPhoto::class)->orderBy('order_index');
    }

    public function publicDocuments(): BelongsToMany
    {
        return $this->documents()
            ->where('visibility', VisibilityLevel::Public)
            ->whereIn('status', [DocumentStatus::Approved, DocumentStatus::Archived]);
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', PostStatus::Published)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now());
    }

    public function scopeFeatured(Builder $query): Builder
    {
        return $query->where('is_featured', true);
    }

    public function scopePinned(Builder $query): Builder
    {
        return $query->where('is_pinned', true);
    }

    public function scopePeriod(Builder $query, PostPeriod|string $period): Builder
    {
        $value = $period instanceof PostPeriod ? $period->value : $period;

        return $query->where('period', $value);
    }

    /**
     * Terapkan batasan akses berita sesuai peran & cakupan unit pengguna.
     */
    public function scopeAccessibleFor(Builder $query, ?User $user): Builder
    {
        if (! $user) {
            return $query;
        }

        if ($user->can('post.moderate_humas') || $user->can('document.bypass_unit_scope')) {
            return $query;
        }

        return $query->where(function (Builder $builder) use ($user): void {
            $builder->where('posts.unit_id', $user->unit_id)
                ->orWhere('posts.author_id', $user->id)
                ->orWhere('posts.status', PostStatus::Published);

            if ($user->hasRole('dekan')) {
                $builder->orWhereIn('posts.unit_id', function ($sub) use ($user): void {
                    $sub->select('id')
                        ->from('units')
                        ->where('parent_id', $user->unit_id);
                });
            }
        });
    }

    public function getThumbnailUrl(): ?string
    {
        if (empty($this->thumbnail_url)) {
            $photo = $this->photos()->where('is_cover', true)->first()
                ?? $this->photos()->orderBy('order_index')->first();

            return $photo?->getThumbnailUrl() ?? $photo?->getUrl();
        }

        if (Str::startsWith($this->thumbnail_url, ['http://', 'https://'])) {
            return $this->thumbnail_url;
        }

        return Storage::disk('public')->url($this->thumbnail_url);
    }

    public function getPublicUrl(): string
    {
        $baseUrl = rtrim((string) config('app.url', env('APP_URL', 'http://localhost')), '/');

        return "{$baseUrl}/berita/{$this->slug}";
    }

    public function getZipDownloadUrl(): string
    {
        $baseUrl = rtrim((string) config('app.url', env('APP_URL', 'http://localhost')), '/');

        return "{$baseUrl}/berita/{$this->slug}/unduh-foto";
    }
}
