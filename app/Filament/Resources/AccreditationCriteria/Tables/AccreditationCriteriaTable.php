<?php

namespace App\Filament\Resources\AccreditationCriteria\Tables;

use App\Enums\AccreditationInstrument;
use App\Filament\Support\DeletionGuard;
use App\Models\AccreditationCriterion;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class AccreditationCriteriaTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('order_index')
                    ->label('No.')
                    ->sortable(),

                TextColumn::make('code')
                    ->label('Kode')
                    ->badge()
                    ->color('primary')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('name')
                    ->label('Nama Kriteria Borang')
                    ->weight(FontWeight::SemiBold)
                    ->searchable()
                    ->sortable(),

                TextColumn::make('instrument_source')
                    ->label('Instrumen')
                    ->badge()
                    ->color(fn (?string $state): string => AccreditationInstrument::tryFrom($state ?? '')?->getColor() ?? 'info')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('documents_count')
                    ->label('Jml Bukti Dokumen')
                    ->counts('documents')
                    ->badge()
                    ->color('success')
                    ->suffix(' Berkas'),

                IconColumn::make('is_active')
                    ->label('Aktif')
                    ->boolean()
                    ->sortable(),
            ])
            ->defaultSort('instrument_source')
            ->filters([
                SelectFilter::make('instrument_source')
                    ->label('Instrumen Akreditasi')
                    ->options(function (): array {
                        $presets = AccreditationInstrument::options();
                        $db = AccreditationCriterion::query()
                            ->whereNotNull('instrument_source')
                            ->distinct()
                            ->pluck('instrument_source', 'instrument_source')
                            ->toArray();

                        return array_merge($presets, $db);
                    })
                    ->native(false),

                TernaryFilter::make('is_active')
                    ->label('Status Kriteria Aktif')
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
