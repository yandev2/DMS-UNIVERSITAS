<?php

namespace App\Models;

use App\Enums\AssetCondition;
use App\Enums\AssetOwnership;
use App\Enums\AssetStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class Asset extends Model
{
    use HasFactory, LogsActivity, SoftDeletes;

    protected $fillable = [
        'uuid',
        'batch_uuid',
        'asset_code',
        'sequence_number',
        'category_id',
        'initial_unit_id',
        'current_unit_id',
        'current_location_id',
        'assigned_to_user_id',
        'name',
        'brand',
        'model_name',
        'serial_number',
        'specifications',
        'status',
        'condition',
        'ownership_status',
        'acquisition_year',
        'acquisition_date',
        'acquisition_cost',
        'funding_source',
        'vendor_name',
        'warranty_expired_date',
        'next_maintenance_date',
        'bast_document_id',
        'disposal_document_id',
        'disposed_at',
        'photo_path',
        'thumbnail_path',
        'maintenances_count',
        'total_maintenance_cost',
        'last_verified_at',
        'last_verified_by',
        'notes',
        'created_by',
    ];

    protected static function booted(): void
    {
        static::creating(function (Asset $asset): void {
            if (empty($asset->uuid)) {
                $asset->uuid = (string) Str::uuid();
            }
        });
    }

    protected function casts(): array
    {
        return [
            'sequence_number' => 'integer',
            'status' => AssetStatus::class,
            'condition' => AssetCondition::class,
            'ownership_status' => AssetOwnership::class,
            'acquisition_year' => 'integer',
            'acquisition_date' => 'date',
            'acquisition_cost' => 'decimal:2',
            'warranty_expired_date' => 'date',
            'next_maintenance_date' => 'date',
            'disposed_at' => 'datetime',
            'maintenances_count' => 'integer',
            'total_maintenance_cost' => 'decimal:2',
            'last_verified_at' => 'datetime',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('asset_audit')
            ->logOnly([
                'asset_code',
                'name',
                'current_unit_id',
                'current_location_id',
                'assigned_to_user_id',
                'status',
                'condition',
                'disposal_document_id',
                'disposed_at',
            ])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(AssetCategory::class, 'category_id');
    }

    public function initialUnit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'initial_unit_id');
    }

    public function currentUnit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'current_unit_id');
    }

    public function currentLocation(): BelongsTo
    {
        return $this->belongsTo(AssetLocation::class, 'current_location_id');
    }

    public function assignedToUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to_user_id');
    }

    public function bastDocument(): BelongsTo
    {
        return $this->belongsTo(Document::class, 'bast_document_id');
    }

    public function disposalDocument(): BelongsTo
    {
        return $this->belongsTo(Document::class, 'disposal_document_id');
    }

    public function lastVerifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'last_verified_by');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function movements(): HasMany
    {
        return $this->hasMany(AssetMovement::class)->orderByDesc('event_date')->orderByDesc('id');
    }

    public function maintenances(): HasMany
    {
        return $this->hasMany(AssetMaintenance::class)->orderByDesc('reported_date');
    }
}
