<?php

declare(strict_types=1);

namespace App\Filament\Resources\Posts\Tables;

use App\Enums\PostPeriod;
use App\Enums\PostStatus;
use App\Filament\Resources\Posts\PostResource;
use App\Models\Post;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Enums\PaginationMode;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class PostsTable
{
    public static function configure(Table $table, bool $withBorangFilter = true): Table
    {
        return $table
            ->striped()
            ->paginationMode(PaginationMode::Simple)
            ->paginated([5, 10, 25, 50])
            ->defaultPaginationPageOption(10)
            ->searchDebounce('500ms')
            ->columns([
                TextColumn::make('index')
                    ->label('No.')
                    ->rowIndex()
                    ->alignCenter()
                    ->grow(false),

                ImageColumn::make('thumbnail_url')
                    ->label('Sampul')
                    ->disk('public')
                    ->square()
                    ->width(48)
                    ->height(40)
                    ->alignCenter()
                    ->grow(false),

                TextColumn::make('title')
                    ->label('Judul Berita & Acara')
                    ->searchable()
                    ->sortable()
                    ->limit(38)
                    ->tooltip(fn (Post $record): string => $record->title)
                    ->weight('bold')
                    ->description(function (Post $record): ?string {
                        $meta = [];
                        if ($record->is_pinned) {
                            $meta[] = '📌 Tersemat';
                        }
                        if ($record->is_featured) {
                            $meta[] = '⭐ Headline';
                        }
                        if ($record->event_date) {
                            $meta[] = 'Acara: '.$record->event_date->translatedFormat('d M Y');
                        }
                        if ($record->academicYear) {
                            $meta[] = 'TA '.$record->academicYear->name;
                        }

                        return ! empty($meta) ? implode(' • ', $meta) : null;
                    }),

                TextColumn::make('category.name')
                    ->label('Kategori')
                    ->badge()
                    ->sortable()
                    ->alignCenter()
                    ->grow(false),

                TextColumn::make('period')
                    ->label('Edisi')
                    ->badge()
                    ->sortable()
                    ->alignCenter()
                    ->grow(false),

                TextColumn::make('unit.name')
                    ->label('Unit / Ormawa')
                    ->sortable()
                    ->limit(26)
                    ->tooltip(fn (Post $record): ?string => $record->unit?->name),

                TextColumn::make('status')
                    ->label('Status Moderasi')
                    ->badge()
                    ->sortable()
                    ->alignCenter()
                    ->grow(false),

                TextColumn::make('photos_count')
                    ->label('Foto')
                    ->counts('photos')
                    ->badge()
                    ->color('gray')
                    ->icon(Heroicon::OutlinedPhoto)
                    ->alignCenter()
                    ->grow(false),

                TextColumn::make('views_count')
                    ->label('Dilihat')
                    ->sortable()
                    ->alignCenter()
                    ->grow(false)
                    ->formatStateUsing(fn ($state): string => number_format((int) $state).'x'),

                IconColumn::make('is_featured')
                    ->label('Headline')
                    ->boolean()
                    ->alignCenter()
                    ->grow(false)
                    ->toggleable(isToggledHiddenByDefault: true),

                IconColumn::make('is_pinned')
                    ->label('Pin')
                    ->boolean()
                    ->alignCenter()
                    ->grow(false)
                    ->toggleable(isToggledHiddenByDefault: true),

                IconColumn::make('is_borang')
                    ->label('Borang')
                    ->boolean()
                    ->trueIcon('heroicon-o-academic-cap')
                    ->trueColor('primary')
                    ->falseIcon('heroicon-o-minus')
                    ->falseColor('gray')
                    ->alignCenter()
                    ->grow(false)
                    ->tooltip(fn (Post $record): string => $record->is_borang ? 'Dokumentasi Terkait Borang Akreditasi' : 'Bukan Dokumentasi Borang')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('published_at')
                    ->label('Tanggal Terbit')
                    ->dateTime('d M Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters(array_values(array_filter([
                SelectFilter::make('post_category_id')
                    ->label('Kategori')
                    ->relationship('category', 'name')
                    ->preload(),

                SelectFilter::make('period')
                    ->label('Jenis Edisi')
                    ->options(PostPeriod::class)
                    ->native(false),

                SelectFilter::make('unit_id')
                    ->label('Unit Pelaksana')
                    ->relationship('unit', 'name')
                    ->searchable()
                    ->preload(),

                SelectFilter::make('status')
                    ->label('Status Moderasi')
                    ->options(PostStatus::class),

                TernaryFilter::make('is_featured')
                    ->label('Berita Utama (Headline)'),

                TernaryFilter::make('is_pinned')
                    ->label('Sematkan di Atas (Pinned)'),

                $withBorangFilter ? TernaryFilter::make('is_borang')
                    ->label('Dokumentasi Borang')
                    ->placeholder('Semua Berita')
                    ->trueLabel('Hanya Dokumentasi Borang')
                    ->falseLabel('Bukan Dokumentasi Borang')
                    ->native(false) : null,
            ])), layout: FiltersLayout::Modal)
            ->filtersFormColumns([
                'sm' => 2,
                'lg' => 3,
            ])
            ->filtersFormWidth(Width::FourExtraLarge)
            ->recordActions([
                ActionGroup::make([
                    Action::make('view_public')
                        ->label('Lihat di Portal Publik')
                        ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                        ->color('info')
                        ->url(fn (Post $record): string => $record->getPublicUrl(), shouldOpenInNewTab: true)
                        ->visible(fn (Post $record): bool => $record->status === PostStatus::Published),

                    Action::make('download_zip')
                        ->label('Unduh Paket Foto (.ZIP)')
                        ->icon(Heroicon::OutlinedArrowDownTray)
                        ->color('success')
                        ->url(fn (Post $record): string => $record->getZipDownloadUrl(), shouldOpenInNewTab: true)
                        ->visible(fn (Post $record): bool => ($record->getAttribute('photos_count') !== null ? (int) $record->photos_count > 0 : $record->photos()->exists()) && (auth()->user()?->can('post.download_zip') ?? false)),

                    ViewAction::make()
                        ->url(fn (Post $record): string => PostResource::getUrl('view', ['record' => $record])),
                    EditAction::make()
                        ->url(fn (Post $record): string => PostResource::getUrl('edit', ['record' => $record])),
                    DeleteAction::make(),
                ]),
            ])
            ->bulkActions([
                DeleteBulkAction::make(),
            ])
            ->recordUrl(fn (Post $record): string => PostResource::getUrl('view', ['record' => $record]))
            ->defaultSort('created_at', 'desc');
    }
}
