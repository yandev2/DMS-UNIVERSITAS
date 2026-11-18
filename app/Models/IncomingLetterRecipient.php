<?php

namespace App\Models;

use App\Enums\IncomingRecipientStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IncomingLetterRecipient extends Model
{
    use HasFactory;

    protected $fillable = [
        'incoming_letter_id',
        'step_order',
        'recipient_user_id',
        'recipient_position_snapshot',
        'instruction_note',
        'disposition_note',
        'status',
        'notified_at',
        'opened_at',
        'read_at',
    ];

    protected static function booted(): void
    {
        static::saved(static function (): void {
            IncomingLetter::invalidateStatsCache();
        });

        static::deleted(static function (): void {
            IncomingLetter::invalidateStatsCache();
        });
    }

    protected function casts(): array
    {
        return [
            'step_order' => 'integer',
            'status' => IncomingRecipientStatus::class,
            'notified_at' => 'datetime',
            'opened_at' => 'datetime',
            'read_at' => 'datetime',
        ];
    }

    public function incomingLetter(): BelongsTo
    {
        return $this->belongsTo(IncomingLetter::class, 'incoming_letter_id');
    }

    public function recipient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recipient_user_id');
    }
}
