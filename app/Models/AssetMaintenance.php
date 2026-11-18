<?php

namespace App\Models;

use App\Enums\AssetCondition;
use App\Enums\AssetMaintenanceStatus;
use App\Enums\AssetMaintenanceType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class AssetMaintenance extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = [
        'asset_id',
        'unit_id',
        'reported_by',
        'handled_by',
        'maintenance_type',
        'issue_description',
        'action_taken',
        'vendor_or_technician',
        'reported_date',
        'start_date',
        'completed_date',
        'cost',
        'status',
        'condition_after',
        'damage_photo_path',
        'invoice_photo_path',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'maintenance_type' => AssetMaintenanceType::class,
            'reported_date' => 'datetime',
            'start_date' => 'date',
            'completed_date' => 'date',
            'cost' => 'decimal:2',
            'status' => AssetMaintenanceStatus::class,
            'condition_after' => AssetCondition::class,
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('asset_audit')
            ->logOnly([
                'asset_id',
                'maintenance_type',
                'status',
                'cost',
                'condition_after',
                'completed_date',
            ])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_by');
    }

    public function handler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handled_by');
    }
}
