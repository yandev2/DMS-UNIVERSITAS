<?php

namespace App\Models;

use App\Enums\AssetCondition;
use App\Enums\AssetEventType;
use App\Enums\AssetStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssetMovement extends Model
{
    use HasFactory;

    public const UPDATED_AT = null;

    protected $fillable = [
        'asset_id',
        'event_type',
        'from_unit_id',
        'to_unit_id',
        'from_location_id',
        'to_location_id',
        'from_user_id',
        'to_user_id',
        'recipient_name_snapshot',
        'previous_status',
        'new_status',
        'previous_condition',
        'new_condition',
        'reference_document_id',
        'expected_return_date',
        'returned_at',
        'notes',
        'evidence_photo_path',
        'recorded_by',
        'event_date',
    ];

    protected function casts(): array
    {
        return [
            'event_type' => AssetEventType::class,
            'previous_status' => AssetStatus::class,
            'new_status' => AssetStatus::class,
            'previous_condition' => AssetCondition::class,
            'new_condition' => AssetCondition::class,
            'expected_return_date' => 'date',
            'returned_at' => 'datetime',
            'event_date' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function fromUnit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'from_unit_id');
    }

    public function toUnit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'to_unit_id');
    }

    public function fromLocation(): BelongsTo
    {
        return $this->belongsTo(AssetLocation::class, 'from_location_id');
    }

    public function toLocation(): BelongsTo
    {
        return $this->belongsTo(AssetLocation::class, 'to_location_id');
    }

    public function fromUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'from_user_id');
    }

    public function toUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'to_user_id');
    }

    public function referenceDocument(): BelongsTo
    {
        return $this->belongsTo(Document::class, 'reference_document_id');
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
