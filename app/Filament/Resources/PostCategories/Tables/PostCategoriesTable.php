<?php

namespace App\Filament\Resources\PostCategories\Tables;

use App\Filament\Support\DeletionGuard;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Support\Colors\Color;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class PostCategoriesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('order_index')
                    ->label('No.')
                    ->sortable()
                    ->alignCenter()
                    ->grow(false),

                TextColumn::make('name')
                    ->label('Nama Kategori')
                    ->weight(FontWeight::SemiBold)
                    ->searchable()
                    ->sortable(),

                TextColumn::make('slug')
                    ->label('Slug')
                    ->color('gray')
                    ->searchable(),

                IconColumn::make('color')
                    ->label('Warna Badge')
                    ->icon('heroicon-s-swatch')
                    ->color(fn (string $state): string|array => match ($state) {
                        'blue' => Color::Blue,
                        'red' => Color::Red,
                        'green' => Color::Green,
                        'amber' => Color::Amber,
                        'purple' => Color::Purple,
                        'orange' => Color::Orange,
                        'primary' => 'primary',
                        'info' => 'info',
                        'success' => 'success',
                        'warning' => 'warning',
                        'danger' => 'danger',
                        default => 'gray',
                    })
                    ->tooltip(fn (string $state): string => match ($state) {
                        'blue' => 'Biru (Blue)',
                        'red' => 'Merah (Red)',
                        'green' => 'Hijau (Green)',
                        'amber' => 'Kuning / Amber',
                        'purple' => 'Ungu (Purple)',
                        'orange' => 'Oranye (Orange)',
                        'primary' => 'Biru Utama (Primary)',
                        'info' => 'Biru Muda (Info)',
                        'success' => 'Hijau (Success)',
                        'warning' => 'Kuning (Warning)',
                        'danger' => 'Merah (Danger)',
                        default => ucfirst($state),
                    })
                    ->alignCenter()
                    ->grow(false),

                TextColumn::make('posts_count')
                    ->label('Jml Artikel')
                    ->counts('posts')
                    ->badge()
                    ->color('info')
                    ->alignCenter()
                    ->grow(false),

                IconColumn::make('is_active')
                    ->label('Aktif')
                    ->boolean()
                    ->sortable()
                    ->alignCenter()
                    ->grow(false),
            ])
            ->defaultSort('order_index')
            ->filters([
                TernaryFilter::make('is_active')
                    ->label('Status Kategori Aktif')
                    ->native(false),
            ], layout: FiltersLayout::Modal)
            ->recordActions([
                ActionGroup::make([
                    EditAction::make(),
                    DeletionGuard::preventIfRestricted(DeleteAction::make()),
                ])
                    ->icon('heroicon-m-ellipsis-vertical')
                    ->tooltip('Aksi'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeletionGuard::preventBulkIfRestricted(DeleteBulkAction::make()),
                ]),
            ]);
    }
}
