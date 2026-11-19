<?php

namespace App\Models;

use App\Enums\OutgoingLetterStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class OutgoingLetter extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'uuid',
        'unit_id',
        'academic_year_id',
        'created_by',
        'reviewed_by',
        'final_scan_uploaded_by',
        'destination_organization',
        'subject',
        'description',
        'letter_date',
        'letter_number',
        'sequence_year',
        'sequence_number',
        'status',
        'requires_wet_signature',
        'review_note',
        'reviewed_at',
        'final_scan_uploaded_at',
        'original_file_path',
        'stamped_file_path',
        'file_name',
    ];

    protected function casts(): array
    {
        return [
            'letter_date' => 'date',
            'sequence_year' => 'integer',
            'sequence_number' => 'integer',
            'status' => OutgoingLetterStatus::class,
            'requires_wet_signature' => 'boolean',
            'reviewed_at' => 'datetime',
            'final_scan_uploaded_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (OutgoingLetter $letter): void {
            if (empty($letter->uuid)) {
                $letter->uuid = (string) Str::uuid();
            }
        });

        static::updating(function (OutgoingLetter $letter): void {
            if ($letter->isDirty('original_file_path')) {
                $oldOriginal = $letter->getOriginal('original_file_path');
                if (filled($oldOriginal) && Storage::disk('private')->exists($oldOriginal)) {
                    Storage::disk('private')->delete($oldOriginal);
                }
            }
            if ($letter->isDirty('stamped_file_path')) {
                $oldStamped = $letter->getOriginal('stamped_file_path');
                if (filled($oldStamped) && Storage::disk('private')->exists($oldStamped)) {
                    Storage::disk('private')->delete($oldStamped);
                }
            }
        });

        static::saved(static function (): void {
            IncomingLetter::invalidateStatsCache();
        });

        static::deleted(function (OutgoingLetter $letter): void {
            $letter->deletePhysicalFiles();
            IncomingLetter::invalidateStatsCache();
        });

        static::restored(static function (): void {
            IncomingLetter::invalidateStatsCache();
        });

        static::forceDeleted(function (OutgoingLetter $letter): void {
            $letter->deletePhysicalFiles();
            IncomingLetter::invalidateStatsCache();
        });
    }

    public function deletePhysicalFiles(): void
    {
        $storage = Storage::disk('private');

        if (filled($this->original_file_path) && $storage->exists($this->original_file_path)) {
            $storage->delete($this->original_file_path);
        }

        if (filled($this->stamped_file_path) && $storage->exists($this->stamped_file_path)) {
            $storage->delete($this->stamped_file_path);
        }
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function finalScanUploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'final_scan_uploaded_by');
    }

    public function scopeAccessibleFor(Builder $query, ?User $user): Builder
    {
        if (! $user) {
            return $query->whereRaw('1 = 0');
        }

        if ($user->can('letter.manage_humas') || $user->can('document.bypass_unit_scope')) {
            return $query;
        }

        $accessibleUnitIds = $user->accessibleUnitIds() ?? [];

        return $query->where(function (Builder $q) use ($user, $accessibleUnitIds): void {
            $q->where('created_by', $user->id);

            if ($accessibleUnitIds !== []) {
                $q->orWhereIn('unit_id', $accessibleUnitIds);
            }
        });
    }

    public function canUserAccess(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        if ($user->can('letter.manage_humas') || $user->can('document.bypass_unit_scope')) {
            return true;
        }

        if ((int) $this->created_by === (int) $user->id) {
            return true;
        }

        return $user->canAccessUnit($this->unit_id);
    }

    public function canBeReviewedByHumas(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        return $user->can('letter.manage_humas')
            && $this->status === OutgoingLetterStatus::PendingHumas;
    }

    public function canBeResubmittedBy(?User $user): bool
    {
        if (! $user || $this->status !== OutgoingLetterStatus::NeedsRevision) {
            return false;
        }

        return (int) $this->created_by === (int) $user->id
            || $user->hasAnyRole(['super_admin', 'humas']);
    }

    public function canUploadFinalScan(?User $user): bool
    {
        if (! $user || $this->status !== OutgoingLetterStatus::WaitingSignature) {
            return false;
        }

        if ($user->hasRole('super_admin') || $user->can('letter.manage_humas') || $user->can('document.bypass_unit_scope')) {
            return true;
        }

        if ((int) $this->created_by === (int) $user->id) {
            return true;
        }

        return $user->unit_id !== null
            && (int) $this->unit_id === (int) $user->unit_id
            && ($user->can('create:outgoing_letter') || $user->can('update:outgoing_letter'));
    }

    public function getActiveFilePath(): ?string
    {
        return $this->stamped_file_path ?: $this->original_file_path;
    }

    public function isPdf(): bool
    {
        $path = $this->getActiveFilePath();

        return $path ? str_ends_with(strtolower($path), '.pdf') : false;
    }

    public function getVerificationUrl(): string
    {
        return route('outgoing-letters.file.stream', ['outgoingLetter' => $this->uuid]);
    }
}
