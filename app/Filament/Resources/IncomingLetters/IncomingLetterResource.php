<?php

declare(strict_types=1);

namespace App\Filament\Resources\IncomingLetters;

use App\Filament\Resources\IncomingLetters\Pages\CreateIncomingLetter;
use App\Filament\Resources\IncomingLetters\Pages\EditIncomingLetter;
use App\Filament\Resources\IncomingLetters\Pages\ListIncomingLetters;
use App\Filament\Resources\IncomingLetters\Pages\ViewIncomingLetter;
use App\Filament\Resources\IncomingLetters\Schemas\IncomingLetterForm;
use App\Filament\Resources\IncomingLetters\Tables\IncomingLettersTable;
use App\Models\IncomingLetter;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class IncomingLetterResource extends Resource
{
    protected static ?string $model = IncomingLetter::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedInboxArrowDown;

    protected static string|UnitEnum|null $navigationGroup = 'Persuratan';

    protected static ?string $navigationLabel = 'Surat Masuk';

    protected static ?string $modelLabel = 'Surat Masuk';

    protected static ?string $pluralModelLabel = 'Surat Masuk';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'subject';

    public static function canAccess(): bool
    {
        return static::canViewAny();
    }

    public static function canViewAny(): bool
    {
        return auth()->user()?->can('view_any:incoming_letter') ?? false;
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->can('create:incoming_letter') ?? false;
    }

    public static function canView(Model $record): bool
    {
        $user = auth()->user();

        return $record instanceof IncomingLetter
            && $user !== null
            && $user->can('view:incoming_letter')
            && $record->canUserAccess($user);
    }

    public static function canEdit(Model $record): bool
    {
        return auth()->user()?->can('update:incoming_letter') ?? false;
    }

    public static function canDelete(Model $record): bool
    {
        return auth()->user()?->can('delete:incoming_letter') ?? false;
    }

    public static function getNavigationBadge(): ?string
    {
        $user = auth()->user();
        if (! $user) {
            return null;
        }

        $unreadForUser = IncomingLetter::query()
            ->pendingTurnForUser($user)
            ->count();

        if ($unreadForUser > 0) {
            return (string) $unreadForUser;
        }

        return null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function form(Schema $schema): Schema
    {
        return IncomingLetterForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return IncomingLettersTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListIncomingLetters::route('/'),
            'create' => CreateIncomingLetter::route('/create'),
            'view' => ViewIncomingLetter::route('/{record}'),
            'edit' => EditIncomingLetter::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['unit', 'registrar', 'recipients.recipient'])
            ->accessibleFor(auth()->user());
    }
}
