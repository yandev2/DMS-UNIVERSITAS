<?php

namespace App\Models;

use App\Enums\ReminderInterval;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReminderLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'document_id',
        'recipient_user_id',
        'recipient_email',
        'interval_type',
        'sent_date',
        'expired_date_snapshot',
        'sent_at',
        'is_success',
        'error_message',
    ];

    protected function casts(): array
    {
        return [
            'interval_type' => ReminderInterval::class,
            'sent_date' => 'date',
            'expired_date_snapshot' => 'date',
            'sent_at' => 'datetime',
            'is_success' => 'boolean',
        ];
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    public function recipientUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recipient_user_id');
    }
}
