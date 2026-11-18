<?php

namespace App\Models;

use App\Enums\IncomingLetterStatus;
use App\Enums\IncomingRecipientStatus;
use App\Enums\LetterPriority;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class IncomingLetter extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'uuid',
        'unit_id',
        'academic_year_id',
        'registered_by',
        'sender_name',
        'external_letter_number',
        'external_letter_date',
        'received_date',
        'agenda_number',
        'sequence_year',
        'sequence_number',
        'subject',
        'summary',
        'priority',
        'status',
        'current_step',
        'total_steps',
        'original_file_path',
        'stamped_file_path',
        'file_name',
    ];

    protected function casts(): array
    {
        return [
            'external_letter_date' => 'date',
            'received_date' => 'date',
            'sequence_year' => 'integer',
            'sequence_number' => 'integer',
            'priority' => LetterPriority::class,
            'status' => IncomingLetterStatus::class,
            'current_step' => 'integer',
            'total_steps' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (IncomingLetter $letter): void {
            if (empty($letter->uuid)) {
                $letter->uuid = (string) Str::uuid();
            }
        });

        static::updating(function (IncomingLetter $letter): void {
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

        static::deleted(function (IncomingLetter $letter): void {
            $letter->deletePhysicalFiles();
            self::invalidateStatsCache();
        });

        static::saved(static function (): void {
            self::invalidateStatsCache();
        });

        static::restored(static function (): void {
            self::invalidateStatsCache();
        });

        static::forceDeleted(function (IncomingLetter $letter): void {
            $letter->deletePhysicalFiles();
            self::invalidateStatsCache();
        });
    }

    public static function getStatsCacheVersion(): int
    {
        return (int) Cache::rememberForever('letter_stats_version', static fn (): int => 1);
    }

    public static function invalidateStatsCache(): void
    {
        Cache::forever('letter_stats_version', self::getStatsCacheVersion() + 1);
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

    public function registrar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registered_by');
    }

    public function recipients(): HasMany
    {
        return $this->hasMany(IncomingLetterRecipient::class, 'incoming_letter_id')->orderBy('step_order');
    }

    /**
     * Scope query for users based on role and sequential distribution step.
     */
    public function scopeAccessibleFor(Builder $query, ?User $user): Builder
    {
        if (! $user) {
            return $query->whereRaw('1 = 0');
        }

        if ($user->can('letter.manage_humas')) {
            return $query;
        }

        return $query->where(function (Builder $q) use ($user): void {
            $q->where('registered_by', $user->id)
                ->orWhereHas('recipients', function (Builder $recipientQuery) use ($user): void {
                    $recipientQuery->where('recipient_user_id', $user->id)
                        ->whereColumn('incoming_letter_recipients.step_order', '<=', 'incoming_letters.current_step');
                });
        });
    }

    /**
     * Scope query untuk surat masuk aktif yang gilirannya sedang menunggu dibaca oleh user terkait.
     */
    public function scopePendingTurnForUser(Builder $query, ?User $user): Builder
    {
        if (! $user) {
            return $query->whereRaw('1 = 0');
        }

        return $query
            ->where('incoming_letters.status', IncomingLetterStatus::InProgress)
            ->whereExists(function ($sub) use ($user): void {
                $sub->selectRaw('1')
                    ->from('incoming_letter_recipients')
                    ->whereColumn('incoming_letter_recipients.incoming_letter_id', 'incoming_letters.id')
                    ->where('incoming_letter_recipients.recipient_user_id', $user->id)
                    ->where('incoming_letter_recipients.status', IncomingRecipientStatus::Unread->value)
                    ->whereColumn('incoming_letter_recipients.step_order', 'incoming_letters.current_step');
            });
    }

    public function canUserAccess(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        if ($user->can('letter.manage_humas') || (int) $this->registered_by === (int) $user->id) {
            return true;
        }

        $recipients = $this->relationLoaded('recipients')
            ? $this->recipients
            : $this->recipients()->get();

        return $recipients->contains(function (IncomingLetterRecipient $item) use ($user): bool {
            return (int) $item->recipient_user_id === (int) $user->id
                && (int) $item->step_order <= (int) $this->current_step;
        });
    }

    public function getCurrentRecipient(): ?IncomingLetterRecipient
    {
        $recipients = $this->relationLoaded('recipients')
            ? $this->recipients
            : $this->recipients()->with('recipient')->get();

        return $recipients->firstWhere('step_order', (int) $this->current_step);
    }

    public function getRecipientRecordFor(?User $user): ?IncomingLetterRecipient
    {
        if (! $user) {
            return null;
        }

        $recipients = $this->relationLoaded('recipients')
            ? $this->recipients
            : $this->recipients()->get();

        // If user appears in multiple steps, prioritize the currently active unread step first
        $activeStep = $recipients->first(function (IncomingLetterRecipient $item) use ($user): bool {
            return (int) $item->recipient_user_id === (int) $user->id
                && (int) $item->step_order === (int) $this->current_step
                && $item->status === IncomingRecipientStatus::Unread;
        });

        if ($activeStep) {
            return $activeStep;
        }

        return $recipients->first(function (IncomingLetterRecipient $item) use ($user): bool {
            return (int) $item->recipient_user_id === (int) $user->id
                && (int) $item->step_order <= (int) $this->current_step;
        });
    }

    public function canUserMarkAsRead(?User $user): bool
    {
        if (! $user || $this->status === IncomingLetterStatus::Completed) {
            return false;
        }

        $activeRecipient = $this->getRecipientRecordFor($user);

        if (! $activeRecipient) {
            return false;
        }

        return (int) $activeRecipient->step_order === (int) $this->current_step
            && $activeRecipient->status === IncomingRecipientStatus::Unread;
    }

    public function getTrackingStepLabel(): string
    {
        if ($this->status === IncomingLetterStatus::Completed) {
            return 'Selesai Dibaca Semua Penerima';
        }

        $current = $this->getCurrentRecipient();
        if (! $current) {
            return 'Teragenda di Humas';
        }

        $name = $current->recipient?->name ?? 'Pejabat Terkait';
        $pos = $current->recipient_position_snapshot ? " ({$current->recipient_position_snapshot})" : '';

        return "Tahap {$current->step_order}: {$name}{$pos}";
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
        return route('incoming-letters.file.stream', ['incomingLetter' => $this->uuid]);
    }
}
