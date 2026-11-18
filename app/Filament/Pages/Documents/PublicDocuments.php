<?php

declare(strict_types=1);

namespace App\Filament\Pages\Documents;

use App\Enums\VisibilityLevel;
use BackedEnum;
use Filament\Support\Icons\Heroicon;

class PublicDocuments extends BaseVisibilityDocumentPage
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedGlobeAlt;

    protected static ?string $navigationLabel = 'Dokumen Publik';

    protected static ?string $title = 'Dokumen Akses Publik';

    protected static ?int $navigationSort = 3;

    protected static ?string $slug = 'documents/public';

    public static function getVisibility(): VisibilityLevel
    {
        return VisibilityLevel::Public;
    }
}
