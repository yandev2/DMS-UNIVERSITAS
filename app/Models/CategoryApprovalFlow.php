<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CategoryApprovalFlow extends Model
{
    use HasFactory;

    protected $fillable = [
        'document_category_id',
        'step_order',
        'approver_role',
        'step_label',
        'is_mandatory',
    ];

    protected function casts(): array
    {
        return [
            'step_order' => 'integer',
            'is_mandatory' => 'boolean',
        ];
    }

    public function documentCategory(): BelongsTo
    {
        return $this->belongsTo(DocumentCategory::class);
    }
}
