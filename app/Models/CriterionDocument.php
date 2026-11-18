<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

class CriterionDocument extends Pivot
{
    public $incrementing = true;

    protected $table = 'criterion_document';

    protected $fillable = [
        'document_id',
        'accreditation_criterion_id',
        'indicator_code',
        'order_index',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'order_index' => 'integer',
        ];
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    public function criterion(): BelongsTo
    {
        return $this->belongsTo(AccreditationCriterion::class, 'accreditation_criterion_id');
    }
}
