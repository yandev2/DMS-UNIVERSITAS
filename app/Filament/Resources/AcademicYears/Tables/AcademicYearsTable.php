<?php

namespace App\Filament\Resources\AcademicYears\Tables;

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

class AcademicYearsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Tahun Akademik')
                    ->weight(FontWeight::SemiBold)
                    ->searchable()
                    ->sortable(),

                TextColumn::make('semester')
                    ->label('Semester')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => ucfirst($state))
                    ->color(fn (string $state): string => match ($state) {
                        'ganjil' => 'primary',
                        'genap' => 'info',
                        default => 'warning',
                    }),

                TextColumn::make('start_date')
                    ->label('Mulai')
                    ->date('d M Y')
                    ->placeholder('-'),

                TextColumn::make('end_date')
                    ->label('Selesai')
                    ->date('d M Y')
                    ->placeholder('-'),

                TextColumn::make('documents_count')
                    ->label('Jml Dokumen')
                    ->counts('documents')
                    ->badge()
                    ->color('gray'),

                IconColumn::make('is_active')
                    ->label('Periode Aktif')
                    ->boolean()
                    ->sortable(),
            ])
            ->defaultSort('start_date', 'desc')
            ->filters([
                SelectFilter::make('semester')
                    ->options([
                        'ganjil' => 'Semester Ganjil',
                        'genap' => 'Semester Genap',
                        'antara' => 'Semester Antara',
                    ])
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
