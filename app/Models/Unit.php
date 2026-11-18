<?php

namespace App\Models;

use App\Enums\AccreditationInstrument;
use App\Enums\DegreeLevel;
use App\Enums\UnitType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Cache;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class Unit extends Model
{
    use HasFactory, LogsActivity, SoftDeletes;

    protected $fillable = [
        'name',
        'code',
        'type',
        'degree_level',
        'parent_id',
        'head_user_id',
        'accreditation_grade',
        'accreditation_body',
        'accreditation_instrument',
        'accreditation_sk_number',
        'accreditation_expired_date',
        'accreditation_document_id',
        'is_assessor_access_active',
        'assessor_access_start',
        'assessor_access_end',
        'assessor_passcode',
        'assessor_notes',
        'description',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'type' => UnitType::class,
            'degree_level' => DegreeLevel::class,
            'accreditation_expired_date' => 'date',
            'is_assessor_access_active' => 'boolean',
            'assessor_access_start' => 'date',
            'assessor_access_end' => 'date',
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saved(static function (): void {
            self::invalidateAssessorCache();
        });

        static::deleted(static function (): void {
            self::invalidateAssessorCache();
        });

        static::restored(static function (): void {
            self::invalidateAssessorCache();
        });

        static::deleting(function (Unit $unit): bool {
            if ($unit->getDeletionRestrictionReason()) {
                return false;
            }

            return true;
        });
    }

    public static function getAssessorCacheVersion(): int
    {
        return (int) Cache::rememberForever('unit_assessor_cache_version', static fn (): int => 1);
    }

    public static function invalidateAssessorCache(): void
    {
        Cache::forever('unit_assessor_cache_version', self::getAssessorCacheVersion() + 1);
    }

    /**
     * Menghitung jumlah unit dengan portal asesor aktif sesuai cakupan akses pengguna (ter-cache + memoized per-request).
     */
    public static function countActiveAssessorUnitsForUser(?User $user): int
    {
        if (! $user) {
            return 0;
        }

        $today = now()->toDateString();
        $isBypass = $user->hasRole('super_admin') || $user->can('document.bypass_unit_scope');
        $isDekan = $user->hasRole('dekan');
        $scopeKey = $isBypass
            ? 'all'
            : implode(':', [
                'u'.(int) ($user->unit_id ?? 0),
                $isDekan ? 'dekan' : 'std',
            ]);

        $version = self::getAssessorCacheVersion();
        $requestCacheKey = "unit_assessor_cnt:{$version}:{$today}:{$scopeKey}";

        $attributes = request()->attributes;
        /** @var array<string, int> $memo */
        $memo = $attributes->get('_unit_assessor_counts', []);
        if (array_key_exists($requestCacheKey, $memo)) {
            return $memo[$requestCacheKey];
        }

        $cacheKey = "dashboard_assessor_units:v{$version}:{$today}:{$scopeKey}";

        $count = (int) Cache::remember($cacheKey, now()->addSeconds(120), static function () use ($user, $today, $isBypass, $isDekan): int {
            $query = self::query()
                ->where('is_assessor_access_active', true)
                ->where(static fn (Builder $q): Builder => $q->whereNull('assessor_access_start')->orWhere('assessor_access_start', '<=', $today))
                ->where(static fn (Builder $q): Builder => $q->whereNull('assessor_access_end')->orWhere('assessor_access_end', '>=', $today));

            if (! $isBypass) {
                if ($isDekan && $user->unit_id) {
                    $query->where(static function (Builder $q) use ($user): void {
                        $q->where('id', $user->unit_id)
                            ->orWhere('parent_id', $user->unit_id);
                    });
                } elseif ($user->unit_id) {
                    $query->where('id', $user->unit_id);
                } else {
                    return 0;
                }
            }

            return $query->count();
        });

        $memo[$requestCacheKey] = $count;
        $attributes->set('_unit_assessor_counts', $memo);

        return $count;
    }

    public function getDeletionRestrictionReason(): ?string
    {
        $reasons = [];

        if ($this->children()->exists()) {
            $reasons[] = $this->children()->count().' unit/prodi bawahan';
        }

        if ($this->users()->exists()) {
            $reasons[] = $this->users()->count().' pengguna/pegawai';
        }

        if ($this->documents()->exists()) {
            $reasons[] = $this->documents()->count().' dokumen arsip';
        }

        if ($this->posts()->exists()) {
            $reasons[] = $this->posts()->count().' artikel berita';
        }

        if ($this->currentAssets()->exists()) {
            $reasons[] = $this->currentAssets()->count().' aset inventaris';
        }

        if ($this->assetLocations()->exists()) {
            $reasons[] = $this->assetLocations()->count().' ruangan/lokasi aset';
        }

        return ! empty($reasons) ? implode(', ', $reasons) : null;
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('master_data')
            ->logFillable()
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Unit::class, 'parent_id');
    }

    public function headUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'head_user_id');
    }

    public function accreditationDocument(): BelongsTo
    {
        return $this->belongsTo(Document::class, 'accreditation_document_id');
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }

    public function posts(): HasMany
    {
        return $this->hasMany(Post::class);
    }

    public function assetLocations(): HasMany
    {
        return $this->hasMany(AssetLocation::class);
    }

    public function currentAssets(): HasMany
    {
        return $this->hasMany(Asset::class, 'current_unit_id');
    }

    public function initialAssets(): HasMany
    {
        return $this->hasMany(Asset::class, 'initial_unit_id');
    }

    public function isAssessorAccessOpen(): bool
    {
        if (! $this->is_assessor_access_active) {
            return false;
        }

        $today = now()->startOfDay();

        if ($this->assessor_access_start && $today->lt($this->assessor_access_start->startOfDay())) {
            return false;
        }

        if ($this->assessor_access_end && $today->gt($this->assessor_access_end->endOfDay())) {
            return false;
        }

        return true;
    }

    public function getAssessorAccessStatus(): string
    {
        if (! $this->is_assessor_access_active) {
            return 'inactive';
        }

        $today = now()->startOfDay();

        if ($this->assessor_access_start && $today->lt($this->assessor_access_start->startOfDay())) {
            return 'scheduled';
        }

        if ($this->assessor_access_end && $today->gt($this->assessor_access_end->endOfDay())) {
            return 'expired';
        }

        return 'open';
    }

    public function getAssessorPortalUrl(): string
    {
        $baseUrl = rtrim((string) config('app.url', env('APP_URL', 'http://localhost')), '/');

        return "{$baseUrl}/bukti-borang/unit/{$this->code}";
    }

    public function getAccreditationInstrumentEnum(): ?AccreditationInstrument
    {
        return AccreditationInstrument::tryFrom($this->accreditation_instrument ?? '');
    }

    public function getEffectiveAccreditationInstrumentAttribute(): string
    {
        if (filled($this->accreditation_instrument)) {
            return (string) $this->accreditation_instrument;
        }

        if ($this->parent_id && $this->parent?->accreditation_instrument) {
            return (string) $this->parent->accreditation_instrument;
        }

        if (filled($this->accreditation_body)) {
            return AccreditationInstrument::normalize($this->accreditation_body) ?? (string) $this->accreditation_body;
        }

        return AccreditationInstrument::BanPt->value;
    }

    /**
     * Get active criteria applicable for this unit's accreditation instrument.
     *
     * @return Collection<int, AccreditationCriterion>
     */
    public function getApplicableCriteria(): Collection
    {
        return AccreditationCriterion::query()
            ->where('instrument_source', $this->effective_accreditation_instrument)
            ->where('is_active', true)
            ->orderBy('order_index')
            ->get();
    }
}
