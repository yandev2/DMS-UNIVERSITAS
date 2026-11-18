<?php

declare(strict_types=1);

namespace App\Filament\Pages\Borang;

use App\Filament\Pages\AccreditationDataBank;
use App\Filament\Resources\Posts\PostResource;
use App\Filament\Resources\Posts\Tables\PostsTable;
use App\Models\Post;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use UnitEnum;

class BorangDocumentationPosts extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPhoto;

    protected static string|UnitEnum|null $navigationGroup = 'Borang Akreditasi';

    protected static ?string $navigationLabel = 'Dokumentasi Borang';

    protected static ?string $title = 'Dokumentasi Berita & Acara Borang Akreditasi';

    protected static ?int $navigationSort = 2;

    protected static ?string $slug = 'borang/dokumentasi-acara';

    protected string $view = 'filament.pages.borang.borang-documentation-posts';

    public static function canAccess(): bool
    {
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        return $user->can('borang.export_excel')
            || $user->can('borang.download_bulk_zip')
            || $user->can('borang.configure_assessor')
            || $user->can('view_any:post');
    }

    public function table(Table $table): Table
    {
        return PostsTable::configure($table, withBorangFilter: false)
            ->query(
                PostResource::getEloquentQuery()
                    ->where('is_borang', true)
            )
            ->recordUrl(fn (Post $record): string => PostResource::getUrl('view', ['record' => $record]));
    }

    /**
     * @return array<string, string>
     */
    public function getBreadcrumbs(): array
    {
        return [
            AccreditationDataBank::getUrl() => 'Borang Akreditasi',
            static::getNavigationLabel(),
        ];
    }
}
