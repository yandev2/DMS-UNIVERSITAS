<?php

namespace App\Models;

use App\Enums\AccreditationInstrument;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class AccreditationCriterion extends Model
{
    use HasFactory;

    protected $table = 'accreditation_criteria';

    protected $fillable = [
        'code',
        'name',
        'instrument_source',
        'description',
        'order_index',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'order_index' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::deleting(function (AccreditationCriterion $criterion): bool {
            if ($criterion->getDeletionRestrictionReason()) {
                return false;
            }

            return true;
        });
    }

    public function getDeletionRestrictionReason(): ?string
    {
        if ($this->documents()->exists()) {
            return $this->documents()->count().' dokumen bukti fisik akreditasi yang tertaut';
        }

        return null;
    }

    public function documents(): BelongsToMany
    {
        return $this->belongsToMany(
            Document::class,
            'criterion_document',
            'accreditation_criterion_id',
            'document_id'
        )
            ->using(CriterionDocument::class)
            ->withPivot(['id', 'indicator_code', 'order_index', 'note'])
            ->withTimestamps();
    }

    /**
     * Scope query to a specific accreditation instrument.
     */
    public function scopeForInstrument(Builder $query, ?string $instrument): Builder
    {
        if (blank($instrument)) {
            return $query;
        }

        return $query->where('instrument_source', $instrument);
    }

    /**
     * Scope query to the effective instrument of a unit.
     */
    public function scopeForUnit(Builder $query, Unit $unit): Builder
    {
        $instrument = $unit->effective_accreditation_instrument;

        return $this->scopeForInstrument($query, $instrument);
    }

    public function getInstrumentEnum(): ?AccreditationInstrument
    {
        return AccreditationInstrument::tryFrom($this->instrument_source ?? '');
    }

    public function getInstrumentLabelAttribute(): string
    {
        return $this->getInstrumentEnum()?->getLabel() ?? ($this->instrument_source ?: 'Umum');
    }

    public function getInstrumentColorAttribute(): string
    {
        return $this->getInstrumentEnum()?->getColor() ?? 'gray';
    }
}
