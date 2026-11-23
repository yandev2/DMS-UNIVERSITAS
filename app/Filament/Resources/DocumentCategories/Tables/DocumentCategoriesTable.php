<?php

namespace App\Filament\Resources\DocumentCategories\Tables;

use App\Filament\Support\DeletionGuard;
use App\Models\DocumentCategory;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Enums\Width;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class DocumentCategoriesTable
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
                    ->label('Nama Kategori Dokumen')
                    ->weight(FontWeight::SemiBold)
                    ->searchable()
                    ->sortable(),

                TextColumn::make('default_visibility')
                    ->label('Visibilitas Default')
                    ->badge(),

                TextColumn::make('allowed_units_summary')
                    ->label('Cakupan Unit')
                    ->badge()
                    ->state(fn (DocumentCategory $record): array|string => $record->allowedUnits->isEmpty()
                        ? 'Semua Unit'
                        : $record->allowedUnits->pluck('code')->all())
                    ->color(fn (DocumentCategory $record): string => $record->allowedUnits->isEmpty()
                        ? 'gray'
                        : 'info')
                    ->tooltip(fn (DocumentCategory $record): string => $record->allowedUnits->isEmpty()
                        ? 'Umum — dapat dipilih oleh seluruh unit kerja.'
                        : 'Khusus unit: '.$record->allowedUnits->pluck('name')->implode(', ')),

                IconColumn::make('requires_approval')
                    ->label('Wajib Approval')
                    ->boolean(),

                TextColumn::make('approval_flows_count')
                    ->label('Jml Tingkat')
                    ->counts('approvalFlows')
                    ->badge()
                    ->color('info')
                    ->suffix(' Tingkat'),

                IconColumn::make('has_expiry')
                    ->label('Masa Berlaku')
                    ->boolean(),

                IconColumn::make('requires_wet_signature')
                    ->label('TTD Basah')
                    ->boolean(),

                IconColumn::make('template_file_path')
                    ->label('Template Word')
                    ->boolean()
                    ->trueIcon('heroicon-o-document-arrow-down')
                    ->falseIcon('heroicon-o-minus')
                    ->trueColor('success')
                    ->falseColor('gray')
                    ->tooltip(fn (DocumentCategory $record): string => $record->hasTemplate() ? ('Unduh '.($record->template_file_name ?? 'Template Word')) : 'Belum Ada Template')
                    ->url(fn (DocumentCategory $record): ?string => $record->getTemplateDownloadUrl())
                    ->openUrlInNewTab(true)
                    ->alignCenter(),

                IconColumn::make('is_active')
                    ->label('Aktif')
                    ->boolean(),
            ])
            ->defaultSort('id')
            ->filtersFormColumns([
                'sm' => 2,
                'lg' => 3,
            ])
            ->filtersFormWidth(Width::FourExtraLarge)
            ->filters([
                TernaryFilter::make('requires_approval')
                    ->label('Wajib Approval')
                    ->native(false),

                TernaryFilter::make('has_template')
                    ->label('Memiliki Template Word')
                    ->queries(
                        true: fn ($query) => $query->whereNotNull('template_file_path'),
                        false: fn ($query) => $query->whereNull('template_file_path'),
                    )
                    ->native(false),

                TernaryFilter::make('has_expiry')
                    ->label('Memiliki Masa Berlaku')
                    ->native(false),

                TernaryFilter::make('requires_wet_signature')
                    ->label('Memerlukan TTD Basah & Cap')
                    ->native(false),

                SelectFilter::make('allowedUnits')
                    ->label('Khusus Unit Kerja')
                    ->relationship('allowedUnits', 'name')
                    ->multiple()
                    ->searchable()
                    ->preload(),
            ], layout: FiltersLayout::Modal)
            ->recordActions([
                Action::make('download_template')
                    ->label('Unduh Template')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('success')
                    ->url(fn (DocumentCategory $record): string => route('admin.document-categories.download-template', $record))
                    ->openUrlInNewTab(true)
                    ->visible(fn (DocumentCategory $record): bool => $record->hasTemplate()),

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
