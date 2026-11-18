<?php

declare(strict_types=1);

namespace App\Filament\Pages\Documents;

use App\Enums\VisibilityLevel;
use App\Filament\Resources\Documents\DocumentResource;
use App\Filament\Resources\Documents\Tables\DocumentsTable;
use App\Models\Document;
use Filament\Pages\Page;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use UnitEnum;

abstract class BaseVisibilityDocumentPage extends Page implements HasTable
{
    use InteractsWithTable;

    protected string $view = 'filament.pages.documents.visibility-documents';

    protected static string|UnitEnum|null $navigationGroup = 'E-Arsip & Dokumen';

    abstract public static function getVisibility(): VisibilityLevel;

    public function table(Table $table): Table
    {
        return DocumentsTable::configure($table, withVisibilityFilter: false)
            ->query(
                DocumentResource::getEloquentQuery()
                    ->where('visibility', static::getVisibility())
            )
            ->recordUrl(fn (Document $record): string => DocumentResource::getUrl('view', ['record' => $record]));
    }

    protected function getHeaderActions(): array
    {
        return [];
    }

    /**
     * @return array<string, string>
     */
    public function getBreadcrumbs(): array
    {
        return [
            DocumentResource::getUrl('index') => 'E-Arsip & Dokumen',
            static::getNavigationLabel(),
        ];
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->can('view_any:document') ?? false;
    }
}
