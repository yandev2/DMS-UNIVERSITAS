<?php

declare(strict_types=1);

namespace App\Filament\Resources\Documents;

use App\Enums\VisibilityLevel;
use App\Filament\Resources\Documents\Pages\CreateDocument;
use App\Filament\Resources\Documents\Pages\EditDocument;
use App\Filament\Resources\Documents\Pages\ListDocuments;
use App\Filament\Resources\Documents\Pages\TrashDocuments;
use App\Filament\Resources\Documents\Pages\ViewDocument;
use App\Filament\Resources\Documents\Schemas\DocumentForm;
use App\Filament\Resources\Documents\Tables\DocumentsTable;
use App\Filament\Support\HasSoftDeletesResource;
use App\Models\Document;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use UnitEnum;

class DocumentResource extends Resource
{
    use HasSoftDeletesResource;

    protected static ?string $model = Document::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedFolderArrowDown;

    protected static string|UnitEnum|null $navigationGroup = 'E-Arsip & Dokumen';

    protected static ?string $navigationLabel = 'Dokumen Arsip & Borang';

    protected static ?string $modelLabel = 'Dokumen Arsip';

    protected static ?string $pluralModelLabel = 'Dokumen Arsip & Borang';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'title';

    public static function form(Schema $schema): Schema
    {
        return DocumentForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return DocumentsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDocuments::route('/'),
            'create' => CreateDocument::route('/create'),
            'trash' => TrashDocuments::route('/trash'),
            'view' => ViewDocument::route('/{record}'),
            'edit' => EditDocument::route('/{record}/edit'),
        ];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $user = auth()->user();

        if (! $user) {
            return $query;
        }

        if ($user->can('document.bypass_unit_scope')) {
            return $query;
        }

        return $query->where(function (Builder $builder) use ($user): void {
            $builder->where('unit_id', $user->unit_id)
                ->orWhere('uploaded_by', $user->id)
                ->orWhere('visibility', VisibilityLevel::Public)
                ->orWhere('is_borang', true);

            if ($user->hasRole('dekan')) {
                $builder->orWhereIn('unit_id', function ($sub) use ($user): void {
                    $sub->select('id')->from('units')->where('parent_id', $user->unit_id);
                });
            }
        });
    }
}
