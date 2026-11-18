<?php

namespace App\Filament\Support;

use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;

abstract class SoftDeleteTrashPage extends ListRecords
{
    public static function canAccess(array $parameters = []): bool
    {
        $resource = static::getResource();

        if (method_exists($resource, 'canAccessTrash')) {
            return parent::canAccess($parameters) && $resource::canAccessTrash();
        }

        return parent::canAccess($parameters);
    }

    public static function shouldRegisterNavigation(array $parameters = []): bool
    {
        return false;
    }

    public function getTitle(): string|Htmlable
    {
        return 'Keranjang Sampah: '.static::getResource()::getTitleCasePluralModelLabel();
    }

    public function getBreadcrumb(): ?string
    {
        return 'Keranjang Sampah';
    }

    public function getBreadcrumbs(): array
    {
        return [
            static::getResource()::getUrl('index') => static::getResource()::getTitleCasePluralModelLabel(),
            'Keranjang Sampah',
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('back')
                ->label('Kembali ke Daftar')
                ->icon(Heroicon::ArrowLeft)
                ->color('gray')
                ->url(fn (): string => static::getResource()::getUrl('index')),
        ];
    }

    public function table(Table $table): Table
    {
        $table = static::getResource()::table($table)
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->onlyTrashed())
            ->recordUrl(null)
            ->recordActions([
                ActionGroup::make([
                    RestoreAction::make(),
                    DeletionGuard::preventIfRestricted(ForceDeleteAction::make()),
                ])
                    ->icon('heroicon-m-ellipsis-vertical')
                    ->tooltip('Aksi'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    RestoreBulkAction::make(),
                    DeletionGuard::preventBulkIfRestricted(ForceDeleteBulkAction::make()),
                ]),
            ])
            ->emptyStateIcon(Heroicon::OutlinedTrash)
            ->emptyStateHeading('Keranjang sampah kosong')
            ->emptyStateDescription('Tidak ada data yang dihapus sementara.');

        $table->pushColumns([
            TextColumn::make('deleted_at')
                ->label('Dihapus Pada')
                ->dateTime('d M Y H:i')
                ->sortable(),
        ]);

        return $table;
    }
}
