<?php

namespace App\Models;

use App\Enums\AccreditationInstrument;
use App\Enums\DegreeLevel;
use App\Enums\DocumentStatus;
use App\Enums\UnitType;
use App\Enums\VisibilityLevel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
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
            self::invalidatePublicAccreditationCache();
        });

        static::deleted(static function (): void {
            self::invalidateAssessorCache();
            self::invalidatePublicAccreditationCache();
        });

        static::restored(static function (): void {
            self::invalidateAssessorCache();
            self::invalidatePublicAccreditationCache();
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

    public static function getPublicAccreditationCacheVersion(): int
    {
        return (int) Cache::rememberForever('unit_public_accreditation_cache_version', static fn (): int => 1);
    }

    public static function invalidatePublicAccreditationCache(): void
    {
        Cache::forever('unit_public_accreditation_cache_version', self::getPublicAccreditationCacheVersion() + 1);
    }

    /**
     * Cek cepat apakah terdapat minimal satu unit aktif dengan peringkat akreditasi (di-memoize per-request & di-cache).
     */
    public static function hasPublicAccreditedUnits(): bool
    {
        $attributes = request()->attributes;
        if ($attributes->has('_has_public_accredited_units')) {
            return (bool) $attributes->get('_has_public_accredited_units');
        }

        $unitVersion = self::getPublicAccreditationCacheVersion();
        $cacheKey = "landing:has_accredited_units:uv{$unitVersion}";

        $hasUnits = (bool) Cache::remember($cacheKey, now()->addMinutes(10), static function (): bool {
            return self::query()
                ->where('is_active', true)
                ->whereNotNull('accreditation_grade')
                ->where('accreditation_grade', '!=', '')
                ->exists();
        });

        $attributes->set('_has_public_accredited_units', $hasUnits);

        return $hasUnits;
    }

    /**
     * Ambil daftar ringkas unit aktif berakreditasi untuk Landing Page (ter-cache sebagai array primitif yang aman).
     *
     * @return array{
     *     institution: array<string, mixed>|null,
     *     units: array<int, array<string, mixed>>,
     *     total_count: int,
     *     top_grade_count: int,
     *     filters: array<string, string>,
     * }
     */
    public static function getPublicAccreditationShowcaseData(): array
    {
        $unitVersion = self::getPublicAccreditationCacheVersion();
        $docVersion = Document::getStatsCacheVersion();
        $today = now()->toDateString();
        $cacheKey = "landing:accredited_units:uv{$unitVersion}:dv{$docVersion}:{$today}";

        /** @var array{institution: array<string, mixed>|null, units: array<int, array<string, mixed>>, total_count: int, top_grade_count: int, filters: array<string, string>} $cached */
        $cached = Cache::remember($cacheKey, now()->addMinutes(10), static function (): array {
            $gradePriority = [
                'Unggul' => 1,
                'A' => 2,
                'Baik Sekali' => 3,
                'B' => 4,
                'Baik' => 5,
                'C' => 6,
                'Terakreditasi Sementara' => 7,
            ];

            $typePriority = [
                UnitType::Rektorat->value => 1,
                UnitType::Fakultas->value => 2,
                UnitType::Prodi->value => 3,
                UnitType::Lembaga->value => 4,
                UnitType::Biro->value => 5,
                UnitType::Upt->value => 6,
                UnitType::UnitPendukung->value => 7,
            ];

            $degreePriority = [
                DegreeLevel::S3->value => 1,
                DegreeLevel::S2->value => 2,
                DegreeLevel::Profesi->value => 3,
                DegreeLevel::S1->value => 4,
                DegreeLevel::D4->value => 5,
                DegreeLevel::D3->value => 6,
            ];

            $records = self::query()
                ->select([
                    'id',
                    'name',
                    'code',
                    'type',
                    'degree_level',
                    'parent_id',
                    'accreditation_grade',
                    'accreditation_body',
                    'accreditation_sk_number',
                    'accreditation_expired_date',
                    'accreditation_document_id',
                ])
                ->where('is_active', true)
                ->whereNotNull('accreditation_grade')
                ->where('accreditation_grade', '!=', '')
                ->with([
                    'parent:id,name,code',
                    'accreditationDocument' => static function ($query): void {
                        $query->select(['id', 'uuid', 'title', 'visibility', 'status', 'document_number'])
                            ->where('visibility', VisibilityLevel::Public)
                            ->whereIn('status', [DocumentStatus::Approved, DocumentStatus::Archived])
                            ->whereNotNull('document_number');
                    },
                ])
                ->get()
                ->sort(static function (self $a, self $b) use ($gradePriority, $typePriority, $degreePriority): int {
                    $aType = $a->type?->value ?? '';
                    $bType = $b->type?->value ?? '';

                    // Institusi/Rektorat selalu didahulukan sebagai spotlight
                    $aIsInst = $aType === UnitType::Rektorat->value ? 0 : 1;
                    $bIsInst = $bType === UnitType::Rektorat->value ? 0 : 1;
                    if ($aIsInst !== $bIsInst) {
                        return $aIsInst <=> $bIsInst;
                    }

                    $aGrade = $gradePriority[(string) $a->accreditation_grade] ?? 99;
                    $bGrade = $gradePriority[(string) $b->accreditation_grade] ?? 99;
                    if ($aGrade !== $bGrade) {
                        return $aGrade <=> $bGrade;
                    }

                    $aTypeRank = $typePriority[$aType] ?? 99;
                    $bTypeRank = $typePriority[$bType] ?? 99;
                    if ($aTypeRank !== $bTypeRank) {
                        return $aTypeRank <=> $bTypeRank;
                    }

                    $aDeg = $degreePriority[$a->degree_level?->value ?? ''] ?? 99;
                    $bDeg = $degreePriority[$b->degree_level?->value ?? ''] ?? 99;
                    if ($aDeg !== $bDeg) {
                        return $aDeg <=> $bDeg;
                    }

                    return strcasecmp((string) $a->name, (string) $b->name);
                })
                ->values();

            $nowDay = now()->startOfDay();
            $warningCutoff = $nowDay->copy()->addDays(180);

            $institution = null;
            $units = [];
            $filters = [];
            $topGradeCount = 0;

            foreach ($records as $unit) {
                $grade = trim((string) $unit->accreditation_grade);
                if (in_array($grade, ['Unggul', 'A'], true)) {
                    $topGradeCount++;
                }

                $tone = match ($grade) {
                    'Unggul', 'A' => 'emerald',
                    'Baik Sekali', 'B' => 'blue',
                    'Baik', 'C' => 'sky',
                    default => 'amber',
                };

                $statusKey = null;
                $statusLabel = null;
                $expiredLabel = null;

                if ($unit->accreditation_expired_date) {
                    $expDate = $unit->accreditation_expired_date->copy()->startOfDay();
                    $expiredLabel = $expDate->translatedFormat('d M Y');

                    if ($expDate->lt($nowDay)) {
                        $statusKey = 'expired';
                        $statusLabel = 'Masa Perpanjangan';
                    } elseif ($expDate->lte($warningCutoff)) {
                        $statusKey = 'expiring';
                        $statusLabel = 'Segera Perpanjang';
                    } else {
                        $statusKey = 'active';
                        $statusLabel = 'Aktif';
                    }
                }

                $doc = $unit->accreditationDocument;
                $documentStreamUrl = null;
                if (
                    $doc !== null
                    && $doc->visibility === VisibilityLevel::Public
                    && in_array($doc->status, [DocumentStatus::Approved, DocumentStatus::Archived], true)
                    && filled($doc->document_number)
                    && filled($doc->uuid)
                ) {
                    $documentStreamUrl = route('document.verify.stream', $doc->uuid);
                }

                $typeValue = $unit->type?->value ?? '';
                $degreeValue = $unit->degree_level?->value;
                $badgeChip = $degreeValue
                    ?: match ($unit->type) {
                        UnitType::Rektorat => 'Institusi',
                        UnitType::Fakultas => 'Fakultas',
                        UnitType::Lembaga => 'Lembaga',
                        UnitType::Biro => 'Biro',
                        UnitType::Upt => 'UPT',
                        UnitType::Prodi => 'Prodi',
                        default => 'Unit',
                    };

                $filterKey = $degreeValue ? "deg:{$degreeValue}" : "type:{$typeValue}";
                if ($typeValue !== UnitType::Rektorat->value && ! isset($filters[$filterKey])) {
                    $filters[$filterKey] = $badgeChip;
                }

                $item = [
                    'id' => $unit->id,
                    'name' => (string) $unit->name,
                    'code' => (string) $unit->code,
                    'type_value' => $typeValue,
                    'degree_value' => $degreeValue,
                    'badge_chip' => $badgeChip,
                    'filter_key' => $filterKey,
                    'parent_name' => $unit->parent?->name,
                    'grade' => $grade,
                    'tone' => $tone,
                    'body' => filled($unit->accreditation_body) ? trim((string) $unit->accreditation_body) : null,
                    'sk_number' => filled($unit->accreditation_sk_number) ? trim((string) $unit->accreditation_sk_number) : null,
                    'expired_label' => $expiredLabel,
                    'status_key' => $statusKey,
                    'status_label' => $statusLabel,
                    'document_stream_url' => $documentStreamUrl,
                ];

                if ($institution === null && $typeValue === UnitType::Rektorat->value) {
                    $institution = $item;
                } else {
                    $units[] = $item;
                }
            }

            return [
                'institution' => $institution,
                'units' => $units,
                'total_count' => count($units) + ($institution !== null ? 1 : 0),
                'top_grade_count' => $topGradeCount,
                'filters' => $filters,
            ];
        });

        return $cached;
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

    public function documentCategories(): BelongsToMany
    {
        return $this->belongsToMany(DocumentCategory::class, 'document_category_unit')
            ->withTimestamps();
    }

    public function posts(): HasMany
    {
        return $this->hasMany(Post::class);
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
