<?php

declare(strict_types=1);

namespace App\Filament\Resources\UserShortcuts;

use App\Filament\Resources\UserShortcuts\Pages\ManageUserShortcuts;
use App\Models\UserShortcut;
use BackedEnum;
use Closure;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class UserShortcutResource extends Resource
{
    protected static ?string $model = UserShortcut::class;

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $slug = 'user-shortcuts';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedLink;

    protected static ?string $navigationLabel = 'Pintasan Web Saya';

    protected static ?string $modelLabel = 'Pintasan Web';

    protected static ?string $pluralModelLabel = 'Pintasan Web Saya';

    protected static ?string $recordTitleAttribute = 'title';

    public static function canAccess(): bool
    {
        return auth()->check();
    }

    public static function canViewAny(): bool
    {
        return auth()->check();
    }

    public static function canCreate(): bool
    {
        return auth()->check();
    }

    public static function canEdit(Model $record): bool
    {
        return $record instanceof UserShortcut
            && auth()->check()
            && (int) $record->user_id === (int) auth()->id();
    }

    public static function canDelete(Model $record): bool
    {
        return $record instanceof UserShortcut
            && auth()->check()
            && (int) $record->user_id === (int) auth()->id();
    }

    public static function canDeleteAny(): bool
    {
        return auth()->check();
    }

    public static function canReorder(): bool
    {
        return auth()->check();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                TextInput::make('title')
                    ->label('Judul Pintasan Web')
                    ->placeholder('Contoh: SISTER Kemdiktisaintek / SIAKAD UNIVBI')
                    ->helperText('Nama singkat yang akan ditampilkan pada kartu pintasan di Dasbor Anda (maks. 80 karakter).')
                    ->required()
                    ->maxLength(80)
                    ->rule(static function (?UserShortcut $record): Closure {
                        return static function (string $attribute, mixed $value, Closure $fail) use ($record): void {
                            if ($record && $record->exists) {
                                return;
                            }

                            $userId = (int) auth()->id();
                            $currentCount = UserShortcut::query()->where('user_id', $userId)->count();

                            if ($currentCount >= UserShortcut::MAX_PER_USER) {
                                $fail('Anda telah mencapai batas maksimal '.UserShortcut::MAX_PER_USER.' pintasan web. Hapus salah satu pintasan lama terlebih dahulu.');
                            }
                        };
                    }),

                TextInput::make('url')
                    ->label('URL Tautan Web')
                    ->placeholder('https://sister.kemdikbud.go.id')
                    ->helperText('Wajib menyertakan protokol lengkap http:// atau https://.')
                    ->required()
                    ->maxLength(500)
                    ->rules(['url:http,https'])
                    ->validationMessages([
                        'url' => 'URL tautan wajib diawali http:// atau https:// yang valid.',
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->reorderable('sort_order')
            ->defaultSort('sort_order')
            ->striped()
            ->paginated([10, 25])
            ->emptyStateHeading('Belum Ada Pintasan Web Pribadi')
            ->emptyStateDescription('Tambahkan tautan cepat ke portal atau aplikasi web yang sering Anda gunakan (maksimal '.UserShortcut::MAX_PER_USER.' pintasan). Pintasan Anda akan langsung muncul di Card Selamat Datang Dasbor.')
            ->emptyStateIcon('heroicon-o-link')
            ->columns([
                TextColumn::make('index')
                    ->label('No.')
                    ->rowIndex()
                    ->alignCenter()
                    ->grow(false),

                TextColumn::make('title')
                    ->label('Judul Pintasan')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->description(fn (UserShortcut $record): string => 'Domain: '.$record->getHostDomain()),

                TextColumn::make('url')
                    ->label('Alamat URL Tujuan')
                    ->searchable()
                    ->fontFamily('mono')
                    ->limit(58)
                    ->tooltip(fn (UserShortcut $record): string => $record->url)
                    ->url(fn (UserShortcut $record): string => $record->url, shouldOpenInNewTab: true)
                    ->color('primary'),

                TextColumn::make('updated_at')
                    ->label('Diperbarui')
                    ->dateTime('d M Y H:i')
                    ->sortable()
                    ->toggleable(),
            ])
            ->recordActions([
                EditAction::make()
                    ->modalHeading('Ubah Pintasan Web')
                    ->modalWidth(Width::Large),
                DeleteAction::make()
                    ->modalHeading('Hapus Pintasan Web?'),
            ])
            ->bulkActions([
                DeleteBulkAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageUserShortcuts::route('/'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('user_id', (int) auth()->id());
    }
}
