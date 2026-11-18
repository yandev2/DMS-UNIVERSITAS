<?php

namespace App\Models;

use App\Enums\StampingPosition;
use App\Enums\VisibilityLevel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class DocumentCategory extends Model
{
    use HasFactory, LogsActivity;

    protected $attributes = [
        'stamp_position' => 'footer',
        'stamp_x_offset' => 0,
        'stamp_y_offset' => 0,
    ];

    protected $fillable = [
        'name',
        'slug',
        'code',
        'number_format',
        'requires_approval',
        'allow_manual_number',
        'has_expiry',
        'default_visibility',
        'stamp_position',
        'stamp_x_offset',
        'stamp_y_offset',
        'allowed_unit_types',
        'description',
        'template_file_path',
        'template_file_name',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'requires_approval' => 'boolean',
            'allow_manual_number' => 'boolean',
            'has_expiry' => 'boolean',
            'default_visibility' => VisibilityLevel::class,
            'stamp_position' => StampingPosition::class,
            'stamp_x_offset' => 'integer',
            'stamp_y_offset' => 'integer',
            'allowed_unit_types' => 'array',
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (DocumentCategory $category): void {
            if ($category->isDirty('template_file_path') && blank($category->template_file_path)) {
                $category->template_file_name = null;
            }
        });

        static::updated(function (DocumentCategory $category): void {
            if (! $category->wasChanged('template_file_path')) {
                return;
            }

            $oldPath = $category->getOriginal('template_file_path');
            if (is_string($oldPath) && $oldPath !== '' && $oldPath !== $category->template_file_path) {
                static::deleteTemplateFile($oldPath);
            }
        });

        static::deleting(function (DocumentCategory $category): bool {
            if ($category->getDeletionRestrictionReason()) {
                return false;
            }

            return true;
        });

        static::deleted(function (DocumentCategory $category): void {
            static::deleteTemplateFile($category->template_file_path);
        });
    }

    public function getDeletionRestrictionReason(): ?string
    {
        if ($this->documents()->exists()) {
            return $this->documents()->count().' dokumen arsip yang menggunakan kategori ini';
        }

        return null;
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('master_data')
            ->logFillable()
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    public function approvalFlows(): HasMany
    {
        return $this->hasMany(CategoryApprovalFlow::class)->orderBy('step_order');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }

    public function hasTemplate(): bool
    {
        return ! empty($this->template_file_path);
    }

    public function hasExistingTemplateFile(): bool
    {
        return $this->resolveTemplateDisk() !== null;
    }

    public function resolveTemplateDisk(): ?string
    {
        $path = $this->template_file_path;
        if (! is_string($path) || trim($path) === '') {
            return null;
        }

        foreach (['private', 'public'] as $diskName) {
            if (rescue(fn (): bool => Storage::disk($diskName)->exists($path), false, report: false)) {
                return $diskName;
            }
        }

        return null;
    }

    public function getTemplateDownloadUrl(): ?string
    {
        if (! $this->hasTemplate()) {
            return null;
        }

        return route('admin.document-categories.download-template', $this);
    }

    protected static function deleteTemplateFile(?string $path): void
    {
        if (! is_string($path) || trim($path) === '' || Str::startsWith($path, ['http://', 'https://'])) {
            return;
        }

        foreach (['private', 'public'] as $diskName) {
            rescue(function () use ($diskName, $path): void {
                $storage = Storage::disk($diskName);
                if ($storage->exists($path)) {
                    $storage->delete($path);
                }
            }, report: false);
        }
    }
}
