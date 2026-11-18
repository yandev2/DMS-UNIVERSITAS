<?php

declare(strict_types=1);

namespace App\Filament\Pages\Documents;

use App\Enums\VisibilityLevel;
use App\Models\Document;
use BackedEnum;
use Filament\Support\Icons\Heroicon;

class ConfidentialDocuments extends BaseVisibilityDocumentPage
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedLockClosed;

    protected static ?string $navigationLabel = 'Dokumen Rahasia';

    protected static ?string $title = 'Dokumen Rahasia Pimpinan';

    protected static ?int $navigationSort = 4;

    protected static ?string $slug = 'documents/confidential';

    public static function getVisibility(): VisibilityLevel
    {
        return VisibilityLevel::Confidential;
    }

    public static function canAccess(): bool
    {
        $user = auth()->user();

        if (! $user || ! $user->can('view_any:document')) {
            return false;
        }

        return $user->can('document.view_confidential')
            || Document::query()->where('visibility', VisibilityLevel::Confidential)->where('uploaded_by', $user->id)->exists();
    }
}
