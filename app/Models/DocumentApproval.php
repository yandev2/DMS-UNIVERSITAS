<?php

namespace App\Models;

use App\Enums\ApprovalStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentApproval extends Model
{
    use HasFactory;

    protected $fillable = [
        'document_id',
        'revision_round',
        'step_order',
        'approver_role',
        'target_unit_id',
        'status',
        'approved_by',
        'approver_position_snapshot',
        'approved_at',
        'note',
    ];

    protected static function booted(): void
    {
        static::saved(static function (): void {
            Document::invalidateStatsCache();
        });

        static::deleted(static function (): void {
            Document::invalidateStatsCache();
        });
    }

    protected function casts(): array
    {
        return [
            'revision_round' => 'integer',
            'step_order' => 'integer',
            'status' => ApprovalStatus::class,
            'approved_at' => 'datetime',
        ];
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    public function targetUnit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'target_unit_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
