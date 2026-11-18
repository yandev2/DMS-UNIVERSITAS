<?php

namespace App\Filament\Resources\AccreditationCriteria;

use App\Filament\Resources\AccreditationCriteria\Pages\CreateAccreditationCriterion;
use App\Filament\Resources\AccreditationCriteria\Pages\EditAccreditationCriterion;
use App\Filament\Resources\AccreditationCriteria\Pages\ListAccreditationCriteria;
use App\Filament\Resources\AccreditationCriteria\Schemas\AccreditationCriterionForm;
use App\Filament\Resources\AccreditationCriteria\Tables\AccreditationCriteriaTable;
use App\Models\AccreditationCriterion;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class AccreditationCriterionResource extends Resource
{
    protected static ?string $model = AccreditationCriterion::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedAcademicCap;

    protected static string|UnitEnum|null $navigationGroup = 'Master Data';

    protected static ?string $navigationLabel = 'Kriteria Borang (K1–K9)';

    protected static ?string $modelLabel = 'Kriteria Borang Akreditasi';

    protected static ?string $pluralModelLabel = 'Kriteria Borang Akreditasi (K1–K9)';

    protected static ?int $navigationSort = 3;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return AccreditationCriterionForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AccreditationCriteriaTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAccreditationCriteria::route('/'),
            'create' => CreateAccreditationCriterion::route('/create'),
            'edit' => EditAccreditationCriterion::route('/{record}/edit'),
        ];
    }
}
