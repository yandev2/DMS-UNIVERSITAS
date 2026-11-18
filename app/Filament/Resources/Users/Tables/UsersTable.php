<?php

namespace App\Filament\Resources\Users\Tables;

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

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Nama Pengguna')
                    ->weight(FontWeight::SemiBold)
                    ->searchable()
                    ->sortable(),

                TextColumn::make('email')
                    ->label('Email')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('nidn_nip')
                    ->label('NIDN/NIP')
                    ->placeholder('-')
                    ->searchable()
                    ->toggleable(),

                TextColumn::make('position_title')
                    ->label('Jabatan')
                    ->placeholder('-')
                    ->searchable(),

                TextColumn::make('unit.name')
                    ->label('Unit Kerja / Prodi')
                    ->badge()
                    ->color('info')
                    ->placeholder('Lintas Unit')
                    ->sortable(),

                TextColumn::make('roles.name')
                    ->label('Role Aktif')
                    ->badge()
                    ->color('primary'),

                IconColumn::make('is_active')
                    ->label('Aktif')
                    ->boolean()
                    ->sortable(),
            ])
            ->defaultSort('id')
            ->filters([
                SelectFilter::make('unit_id')
                    ->label('Unit Kerja / Prodi')
                    ->relationship('unit', 'name')
                    ->searchable()
                    ->preload(),

                SelectFilter::make('roles')
                    ->label('Role')
                    ->relationship('roles', 'name')
                    ->preload()
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
