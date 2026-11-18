<?php

declare(strict_types=1);

namespace App\Filament\Pages\Documents;

use App\Enums\VisibilityLevel;
use BackedEnum;
use Filament\Support\Icons\Heroicon;

class InternalDocuments extends BaseVisibilityDocumentPage
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingOffice2;

    protected static ?string $navigationLabel = 'Dokumen Internal';

    protected static ?string $title = 'Dokumen Internal Civitas';

    protected static ?int $navigationSort = 2;

    protected static ?string $slug = 'documents/internal';

    public static function getVisibility(): VisibilityLevel
    {
        return VisibilityLevel::Internal;
    }
}
