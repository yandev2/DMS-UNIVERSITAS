<?php

namespace App\Models;

use App\Enums\DocumentStatus;
use App\Enums\VisibilityLevel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class Document extends Model
{
    use HasFactory, LogsActivity, SoftDeletes;

    /**
     * Daftar berkas fisik yang akan dibersihkan setelah penghapusan permanen (force delete) berhasil.
     *
     * @var array<int, array{disk: string, path: ?string}>
     */
    protected array $pendingPhysicalFilesToPurge = [];

    protected $fillable = [
        'uuid',
        'title',
        'slug',
        'unit_id',
        'document_category_id',
        'academic_year_id',
        'uploaded_by',
        'is_manual_number',
        'sequence_year',
        'sequence_number',
        'document_number',
        'visibility',
        'status',
        'current_step',
        'total_steps',
        'final_approved_by',
        'final_scan_uploaded_by',
        'approved_at',
        'final_scan_uploaded_at',
        'signer_name_snapshot',
        'signer_position_snapshot',
        'is_borang',
        'requires_wet_signature',
        'has_expiry',
        'issued_date',
        'expired_date',
        'reminder_intervals',
        'reminder_recipients',
        'replaces_document_id',
        'custom_metadata',
        'description',
        'views_count',
        'downloads_count',
    ];

    protected static function booted(): void
    {
        static::creating(function (Document $document): void {
            if (empty($document->uuid)) {
                $document->uuid = (string) Str::uuid();
            }

            if (empty($document->slug) && ! empty($document->title)) {
                $document->slug = Str::slug($document->title);
            }
        });

        static::saved(function (Document $document): void {
            $document->archiveReplacedDocumentIfApproved();
            self::invalidateStatsCache();
        });

        static::deleted(static function (): void {
            self::invalidateStatsCache();
        });

        static::restored(static function (): void {
            self::invalidateStatsCache();
        });

        static::deleting(function (Document $document): bool {
            if ($document->getDeletionRestrictionReason()) {
                return false;
            }

            return true;
        });

        static::forceDeleting(function (Document $document): void {
            $document->pendingPhysicalFilesToPurge = [];

            $files = $document->files()->get(['disk', 'original_file_path', 'stamped_file_path']);
            foreach ($files as $file) {
                $disk = $file->disk?->value ?? 'private';

                $document->pendingPhysicalFilesToPurge[] = ['disk' => $disk, 'path' => $file->original_file_path];
                $document->pendingPhysicalFilesToPurge[] = ['disk' => $disk, 'path' => $file->stamped_file_path];
            }
        });

        static::forceDeleted(function (Document $document): void {
            foreach ($document->pendingPhysicalFilesToPurge as $entry) {
                DocumentFile::deleteStoredFileIfUnreferenced($entry['disk'], $entry['path']);
            }

            $document->pendingPhysicalFilesToPurge = [];
        });
    }

    public static function getStatsCacheVersion(): int
    {
        return (int) Cache::rememberForever('document_stats_version', static fn (): int => 1);
    }

    public static function invalidateStatsCache(): void
    {
        Cache::forever('document_stats_version', self::getStatsCacheVersion() + 1);
    }

    /**
     * Kunci cakupan deterministik berbasis ID, unit, serta izin akses pengguna agar cache statistik terisolasi aman.
     */
    public static function statsCacheScopeKeyFor(?User $user): string
    {
        if (! $user) {
            return 'guest';
        }

        $roles = $user->roles->pluck('name')->sort()->implode(',');

        return implode(':', [
            'id'.(int) $user->id,
            'u'.(int) ($user->unit_id ?? 0),
            'sa'.($user->hasRole('super_admin') ? '1' : '0'),
            'b'.($user->can('document.bypass_unit_scope') ? '1' : '0'),
            'c'.($user->can('document.view_confidential') ? '1' : '0'),
            'd'.($user->hasRole('dekan') ? '1' : '0'),
            'r'.substr(sha1($roles), 0, 8),
        ]);
    }

    public function getDeletionRestrictionReason(): ?string
    {
        $reasons = [];

        if ($this->replacementDocuments()->exists()) {
            $reasons[] = 'menjadi referensi riwayat bagi '.$this->replacementDocuments()->count().' dokumen pembaruan';
        }

        return ! empty($reasons) ? implode(', ', $reasons) : null;
    }

    /**
     * Memindahkan status dokumen terdahulu ke Arsip Pasif (Archived) secara otomatis
     * ketika dokumen pengganti/pencabut resmi disahkan (Approved).
     */
    public function archiveReplacedDocumentIfApproved(): void
    {
        if (
            $this->status !== DocumentStatus::Approved
            || ! $this->replaces_document_id
            || $this->replaces_document_id === $this->id
        ) {
            return;
        }

        $replaced = static::query()->find($this->replaces_document_id);

        if (! $replaced || ! in_array($replaced->status, [DocumentStatus::Approved, DocumentStatus::Expired], true)) {
            return;
        }

        $replaced->update([
            'status' => DocumentStatus::Archived,
        ]);

        $replacementLabel = $this->document_number ?: $this->title;

        activity('document_audit')
            ->performedOn($replaced)
            ->causedBy(auth()->user() ?: $this->finalApprover ?: $this->uploader)
            ->event('archived')
            ->withProperties([
                'document_number' => $replaced->document_number,
                'replaced_by_document_id' => $this->id,
                'replaced_by_document_number' => $this->document_number,
            ])
            ->log("Dokumen otomatis dipindahkan ke arsip pasif (Diarsipkan) karena digantikan oleh naskah {$replacementLabel}.");
    }

    public function getVerificationUrl(): string
    {
        $baseUrl = rtrim((string) config('app.url', env('APP_URL', 'http://localhost')), '/');

        return "{$baseUrl}/verify/{$this->uuid}";
    }

    public function getAssessorUrl(): string
    {
        $baseUrl = rtrim((string) config('app.url', env('APP_URL', 'http://localhost')), '/');

        return "{$baseUrl}/bukti-borang/{$this->uuid}";
    }

    protected function casts(): array
    {
        return [
            'is_manual_number' => 'boolean',
            'sequence_year' => 'integer',
            'sequence_number' => 'integer',
            'visibility' => VisibilityLevel::class,
            'status' => DocumentStatus::class,
            'current_step' => 'integer',
            'total_steps' => 'integer',
            'approved_at' => 'datetime',
            'final_scan_uploaded_at' => 'datetime',
            'is_borang' => 'boolean',
            'requires_wet_signature' => 'boolean',
            'has_expiry' => 'boolean',
            'issued_date' => 'date',
            'expired_date' => 'date',
            'reminder_intervals' => 'array',
            'reminder_recipients' => 'array',
            'custom_metadata' => 'array',
            'views_count' => 'integer',
            'downloads_count' => 'integer',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('document_audit')
            ->logOnly([
                'title',
                'unit_id',
                'document_category_id',
                'academic_year_id',
                'document_number',
                'visibility',
                'status',
                'current_step',
                'is_borang',
                'issued_date',
                'expired_date',
                'replaces_document_id',
            ])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(DocumentCategory::class, 'document_category_id');
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function finalApprover(): BelongsTo
    {
        return $this->belongsTo(User::class, 'final_approved_by');
    }

    public function finalScanUploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'final_scan_uploaded_by');
    }

    public function replacedDocument(): BelongsTo
    {
        return $this->belongsTo(Document::class, 'replaces_document_id');
    }

    public function replacementDocuments(): HasMany
    {
        return $this->hasMany(Document::class, 'replaces_document_id');
    }

    public function files(): HasMany
    {
        return $this->hasMany(DocumentFile::class)->orderBy('order_index');
    }

    public function primaryFile(): HasOne
    {
        return $this->hasOne(DocumentFile::class)
            ->where('is_primary', true)
            ->where('is_active', true)
            ->latestOfMany('version');
    }

    public function approvals(): HasMany
    {
        return $this->hasMany(DocumentApproval::class)
            ->orderBy('revision_round')
            ->orderBy('step_order');
    }

    /**
     * Mendapatkan rekaman approval aktif pada putaran (round) dan tahapan (step) saat ini.
     */
    public function getCurrentApproval(): ?DocumentApproval
    {
        $approvals = $this->relationLoaded('approvals')
            ? $this->approvals
            : $this->approvals()->get();

        $maxRound = $approvals->max('revision_round') ?? 1;

        return $approvals
            ->where('revision_round', $maxRound)
            ->where('step_order', $this->current_step)
            ->first();
    }

    /**
     * Mendapatkan label representasi tahapan persetujuan aktif (misal: "Tingkat 1 (Kaprodi)", "Perlu Revisi").
     */
    public function getCurrentStepLabel(): string
    {
        if ($this->status === DocumentStatus::Revision) {
            return 'Perlu Revisi';
        }

        if ($this->status === DocumentStatus::WaitingSignature) {
            return 'Menunggu TTD Basah & Cap';
        }

        if ($this->status === DocumentStatus::Approved || $this->status === DocumentStatus::Archived) {
            return 'Disahkan';
        }

        if ($this->status === DocumentStatus::Rejected) {
            return 'Ditolak';
        }

        $currentApproval = $this->getCurrentApproval();
        if ($currentApproval?->approver_role) {
            $roleName = str($currentApproval->approver_role)->title()->replace('_', ' ')->toString();

            return "Tingkat {$this->current_step} ({$roleName})";
        }

        // Fallback ke alur kategori naskah jika rekaman approval belum tersedia
        if ($this->category) {
            $flows = $this->category->relationLoaded('approvalFlows')
                ? $this->category->approvalFlows
                : $this->category->approvalFlows()->get();

            $flow = $flows->firstWhere('step_order', $this->current_step);
            if ($flow) {
                $roleName = $flow->step_label ?: str($flow->approver_role)->title()->replace('_', ' ')->toString();

                return "Tingkat {$this->current_step} ({$roleName})";
            }
        }

        return $this->current_step > 0 ? "Tingkat {$this->current_step}" : '-';
    }

    public function accreditationCriteria(): BelongsToMany
    {
        return $this->belongsToMany(
            AccreditationCriterion::class,
            'criterion_document',
            'document_id',
            'accreditation_criterion_id'
        )
            ->using(CriterionDocument::class)
            ->withPivot(['id', 'indicator_code', 'order_index', 'note'])
            ->withTimestamps();
    }

    public function posts(): BelongsToMany
    {
        return $this->belongsToMany(Post::class, 'post_document')
            ->using(PostDocument::class)
            ->withPivot(['id', 'custom_button_label', 'order_index'])
            ->withTimestamps();
    }

    public function reminderLogs(): HasMany
    {
        return $this->hasMany(ReminderLog::class);
    }

    /**
     * Mendapatkan koleksi model User aktif yang menjadi penerima notifikasi pengingat dokumen.
     * Mendukung ID user numerik maupun string email untuk kompatibilitas data lama.
     * Jika daftar penerima belum diatur atau kosong, otomatis fallback ke akun pengunggah dokumen.
     *
     * @return Collection<int, User>
     */
    public function getReminderRecipientUsers(): Collection
    {
        $raw = (array) ($this->reminder_recipients ?? []);
        $userIds = [];
        $emails = [];

        foreach ($raw as $item) {
            if (is_numeric($item) && (int) $item > 0) {
                $userIds[] = (int) $item;
            } elseif (is_string($item) && filter_var($item, FILTER_VALIDATE_EMAIL)) {
                $emails[] = strtolower(trim($item));
            }
        }

        $users = collect();

        if (! empty($userIds) || ! empty($emails)) {
            $users = User::query()
                ->where('is_active', true)
                ->where(function (Builder $query) use ($userIds, $emails): void {
                    if (! empty($userIds)) {
                        $query->whereIn('id', $userIds);
                    }

                    if (! empty($emails)) {
                        $placeholders = implode(', ', array_fill(0, count($emails), '?'));
                        $method = ! empty($userIds) ? 'orWhereRaw' : 'whereRaw';
                        $query->{$method}("LOWER(email) IN ({$placeholders})", $emails);
                    }
                })
                ->get()
                ->unique('id')
                ->values();
        }

        // Fallback jika tidak ada penerima aktif yang terdaftar: kirimkan ke pengunggah dokumen
        if ($users->isEmpty() && $this->uploaded_by) {
            $uploader = $this->uploader ?? User::find($this->uploaded_by);
            if ($uploader && $uploader->is_active) {
                $users = collect([$uploader]);
            }
        }

        return $users;
    }

    /**
     * Mendapatkan daftar alamat email aktif dari para pengguna penerima pengingat.
     * Menjamin alamat email selalu sinkron dan mutakhir saat profil pengguna berganti email.
     *
     * @return list<string>
     */
    public function getReminderRecipientEmails(): array
    {
        return $this->getReminderRecipientUsers()
            ->pluck('email')
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Membatasi query dokumen hanya pada naskah yang berhak diakses oleh pengguna.
     * Mengikuti aturan otorisasi baku pada DocumentPolicy:
     * - Super Admin dapat mengakses seluruh dokumen.
     * - Pengguna dengan hak bypass_unit_scope dapat mengakses seluruh dokumen lintas unit.
     * - Pengguna unit hanya dapat mengakses dokumen unitnya, dokumen yang diunggahnya,
     *   dokumen prodi bawahan (khusus dekan), naskah publik, atau bukti borang.
     * - Dokumen rahasia (Confidential) hanya dapat diakses pemegang izin document.view_confidential
     *   atau pengunggah aslinya.
     */
    public function scopeAccessibleFor(Builder $query, ?User $user): Builder
    {
        if (! $user) {
            return $query->whereRaw('1 = 0');
        }

        if ($user->hasRole('super_admin')) {
            return $query;
        }

        // Filter Tenancy & Hak Unit
        $accessibleUnitIds = $user->accessibleUnitIds();
        if ($accessibleUnitIds !== null) {
            $query->where(function (Builder $builder) use ($user, $accessibleUnitIds): void {
                $builder->where('documents.uploaded_by', $user->id)
                    ->orWhere('documents.visibility', VisibilityLevel::Public);

                if ($accessibleUnitIds !== []) {
                    $builder->orWhereIn('documents.unit_id', $accessibleUnitIds);
                }

                if ($user->can('letter.manage_humas')) {
                    $builder->orWhere('documents.status', DocumentStatus::WaitingSignature->value)
                        ->orWhere('documents.final_scan_uploaded_by', $user->id)
                        ->orWhereExists(function ($approvalSub): void {
                            $approvalSub->selectRaw('1')
                                ->from('document_approvals')
                                ->whereColumn('document_approvals.document_id', 'documents.id')
                                ->where('document_approvals.approver_role', 'humas');
                        });
                }
            });
        }

        // Proteksi Kerahasiaan Dokumen (Confidential Guard)
        if (! $user->can('document.view_confidential')) {
            $query->where(function (Builder $builder) use ($user): void {
                $builder->where('documents.visibility', '!=', VisibilityLevel::Confidential)
                    ->orWhere('documents.uploaded_by', $user->id);
            });
        }

        return $query;
    }
}
