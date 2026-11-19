<?php

namespace App\Filament\Resources\Units\Tables;

use App\Enums\AccreditationInstrument;
use App\Enums\DegreeLevel;
use App\Enums\UnitType;
use App\Filament\Support\DeletionGuard;
use App\Models\Unit;
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
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

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
            ->groups([
                Group::make('parent_id')
                    ->label('Unit Induk')
                    ->titlePrefixedWithLabel(false)
                    ->collapsible()
                    ->getKeyFromRecordUsing(fn (Unit $record): string => $record->parent_id ? (string) $record->parent_id : '0')
                    ->getTitleFromRecordUsing(function (Unit $record): string {
                        $parent = $record->parent;

                        if (! $parent) {
                            return 'Tingkat Universitas (Pimpinan / Tanpa Induk)';
                        }

                        $parentLabel = filled($parent->code)
                            ? "{$parent->name} ({$parent->code})"
                            : $parent->name;

                        if ($parent->parent) {
                            $grandParentCode = $parent->parent->code ?: $parent->parent->name;

                            return "{$grandParentCode} -> {$parentLabel}";
                        }

                        return $parentLabel;
                    })
                    ->getDescriptionFromRecordUsing(function (Unit $record): ?string {
                        if (! $record->parent) {
                            return 'Unit tingkat puncak pada struktur organisasi universitas.';
                        }

                        return 'Sub-unit di bawah naungan '.$record->parent->name.'.';
                    })
                    ->orderQueryUsing(function (Builder $query, string $direction): Builder {
                        $dir = strtolower($direction) === 'desc' ? 'DESC' : 'ASC';

                        // Urutkan hierarki induk: 0 = Tanpa Induk (Rektorat), 1 = Induk tingkat atas (Fakultas/Biro), 2 = Sub-unit (Prodi)
                        $depthSql = '(CASE WHEN units.parent_id IS NULL THEN 0 WHEN EXISTS (SELECT 1 FROM units AS p WHERE p.id = units.parent_id AND p.parent_id IS NULL) THEN 1 ELSE 2 END)';

                        return $query
                            ->orderByRaw("{$depthSql} {$dir}")
                            ->orderByRaw("COALESCE(units.parent_id, 0) {$dir}");
                    })
                    ->scopeQueryByKeyUsing(fn (Builder $query, ?string $key): Builder => ($key === null || $key === '0' || $key === '')
                        ? $query->whereNull('units.parent_id')
                        : $query->where('units.parent_id', (int) $key)),

                Group::make('type')
                    ->label('Jenis Unit')
                    ->collapsible(),
            ])
            ->defaultGroup('parent_id')
            ->defaultSort('name')
            ->defaultPaginationPageOption(25)
            ->filters([
                SelectFilter::make('parent_id')
                    ->label('Unit Induk')
                    ->relationship('parent', 'name')
                    ->searchable()
                    ->preload()
                    ->native(false),

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
