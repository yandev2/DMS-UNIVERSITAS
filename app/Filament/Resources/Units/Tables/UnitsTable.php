<?php

namespace App\Filament\Resources\Units\Tables;

use App\Enums\AccreditationInstrument;
use App\Enums\DegreeLevel;
use App\Enums\UnitType;
use App\Filament\Support\DeletionGuard;
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
use Filament\Tables\Table;

class UnitsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')
                    ->label('Kode')
                    ->badge()
                    ->color('primary')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('name')
                    ->label('Nama Unit / Program Studi')
                    ->weight(FontWeight::SemiBold)
                    ->searchable()
                    ->sortable(),

                TextColumn::make('type')
                    ->label('Jenis Unit')
                    ->badge()
                    ->sortable(),

                TextColumn::make('degree_level')
                    ->label('Jenjang')
                    ->badge()
                    ->placeholder('-'),

                TextColumn::make('parent.name')
                    ->label('Unit Induk')
                    ->placeholder('Tingkat Universitas')
                    ->toggleable(),

                TextColumn::make('headUser.name')
                    ->label('Kepala Unit / Kaprodi')
                    ->placeholder('Belum Ditentukan')
                    ->searchable(),

                TextColumn::make('accreditation_grade')
                    ->label('Akreditasi')
                    ->badge()
                    ->color('success')
                    ->placeholder('-'),

                TextColumn::make('accreditation_instrument')
                    ->label('Instrumen Borang')
                    ->badge()
                    ->color(fn (?string $state): string => AccreditationInstrument::tryFrom($state ?? '')?->getColor() ?? 'info')
                    ->placeholder('-')
                    ->toggleable(),

                IconColumn::make('is_active')
                    ->label('Aktif')
                    ->boolean()
                    ->sortable(),
            ])
            ->defaultSort('id')
            ->filters([
                SelectFilter::make('type')
                    ->label('Jenis Unit')
                    ->options(UnitType::class)
                    ->native(false),

                SelectFilter::make('degree_level')
                    ->label('Jenjang Prodi')
                    ->options(DegreeLevel::class)
                    ->native(false),

                SelectFilter::make('accreditation_instrument')
                    ->label('Instrumen Borang')
                    ->options(AccreditationInstrument::options())
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
