<?php

declare(strict_types=1);

namespace App\Filament\Resources\OutgoingLetters;

use App\Enums\OutgoingLetterStatus;
use App\Filament\Resources\OutgoingLetters\Pages\CreateOutgoingLetter;
use App\Filament\Resources\OutgoingLetters\Pages\EditOutgoingLetter;
use App\Filament\Resources\OutgoingLetters\Pages\ListOutgoingLetters;
use App\Filament\Resources\OutgoingLetters\Pages\ViewOutgoingLetter;
use App\Filament\Resources\OutgoingLetters\Schemas\OutgoingLetterForm;
use App\Filament\Resources\OutgoingLetters\Tables\OutgoingLettersTable;
use App\Models\OutgoingLetter;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class OutgoingLetterResource extends Resource
{
    protected static ?string $model = OutgoingLetter::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPaperAirplane;

    protected static string|UnitEnum|null $navigationGroup = 'Surat Masuk & Surat Keluar';

    protected static ?string $navigationLabel = 'Surat Keluar (Humas)';

    protected static ?string $modelLabel = 'Surat Keluar';

    protected static ?string $pluralModelLabel = 'Surat Keluar (Humas)';

    protected static ?int $navigationSort = 2;

    protected static ?string $recordTitleAttribute = 'subject';

    public static function canAccess(): bool
    {
        return static::canViewAny();
    }

    public static function canViewAny(): bool
    {
        return auth()->user()?->can('view_any:outgoing_letter') ?? false;
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->can('create:outgoing_letter') ?? false;
    }

    public static function canView(Model $record): bool
    {
        $user = auth()->user();

        return $record instanceof OutgoingLetter
            && $user !== null
            && $user->can('view:outgoing_letter')
            && $record->canUserAccess($user);
    }

    public static function canEdit(Model $record): bool
    {
        $user = auth()->user();
        if (! $user || ! ($record instanceof OutgoingLetter) || ! $user->can('update:outgoing_letter')) {
            return false;
        }

        if ($user->can('letter.manage_humas')) {
            return true;
        }

        return (int) $record->created_by === (int) $user->id
            && in_array($record->status, [OutgoingLetterStatus::PendingHumas, OutgoingLetterStatus::NeedsRevision], true);
    }

    public static function canDelete(Model $record): bool
    {
        $user = auth()->user();
        if (! $user || ! ($record instanceof OutgoingLetter) || ! $user->can('delete:outgoing_letter')) {
            return false;
        }

        if ($user->can('letter.manage_humas')) {
            return true;
        }

        return (int) $record->created_by === (int) $user->id
            && in_array($record->status, [OutgoingLetterStatus::PendingHumas, OutgoingLetterStatus::NeedsRevision], true);
    }

    public static function getNavigationBadge(): ?string
    {
        $user = auth()->user();
        if (! $user) {
            return null;
        }

        if ($user->can('letter.manage_humas')) {
            $pendingCount = OutgoingLetter::query()
                ->where('status', OutgoingLetterStatus::PendingHumas)
                ->count();

            return $pendingCount > 0 ? (string) $pendingCount : null;
        }

        $revisionCount = OutgoingLetter::query()
            ->where('created_by', $user->id)
            ->where('status', OutgoingLetterStatus::NeedsRevision)
            ->count();

        return $revisionCount > 0 ? (string) $revisionCount : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function form(Schema $schema): Schema
    {
        return OutgoingLetterForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return OutgoingLettersTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListOutgoingLetters::route('/'),
            'create' => CreateOutgoingLetter::route('/create'),
            'view' => ViewOutgoingLetter::route('/{record}'),
            'edit' => EditOutgoingLetter::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['unit', 'creator', 'reviewer'])
            ->accessibleFor(auth()->user());
    }
}
