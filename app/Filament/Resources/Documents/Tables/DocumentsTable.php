<?php

declare(strict_types=1);

namespace App\Filament\Resources\Documents\Tables;

use App\Enums\DocumentStatus;
use App\Enums\VisibilityLevel;
use App\Filament\Resources\Documents\Actions\DocumentWorkflowActions;
use App\Filament\Resources\Documents\DocumentResource;
use App\Filament\Support\DeletionGuard;
use App\Jobs\GenerateBulkZipArchiveJob;
use App\Models\BulkExport;
use App\Models\Document;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Notifications\Notification;
use Filament\Support\Enums\Width;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Enums\PaginationMode;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\Indicator;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class DocumentsTable
{
    public static function configure(Table $table, bool $withVisibilityFilter = true): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query
                ->with(['unit', 'category', 'academicYear', 'primaryFile', 'approvals'])
                ->withExists('files'))
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

                TextColumn::make('document_number')
                    ->label('Nomor Surat')
                    ->searchable()
                    ->copyable()
                    ->sortable()
                    ->weight('bold')
                    ->fontFamily('mono')
                    ->placeholder(fn (?Document $record): string => $record?->is_manual_number ? '-' : '[Otomatis]')
                    ->description(fn (Document $record): ?string => $record->category?->name ? Str::limit($record->category->name, 28) : null)
                    ->tooltip(fn (Document $record): ?string => $record->category?->name),

                TextColumn::make('title')
                    ->label('Judul Naskah Dinas')
                    ->searchable()
                    ->sortable()
                    ->limit(32)
                    ->tooltip(fn (Document $record): string => $record->title.($record->academicYear ? " • TA {$record->academicYear->name}" : ''))
                    ->description(fn (Document $record): ?string => $record->issued_date ? 'Terbit: '.$record->issued_date->format('d M Y') : null),

                TextColumn::make('unit.name')
                    ->label('Unit Kerja / Prodi')
                    ->searchable()
                    ->sortable()
                    ->limit(26)
                    ->tooltip(fn (Document $record): ?string => $record->unit?->name),

                TextColumn::make('visibility')
                    ->label('Akses')
                    ->badge()
                    ->alignCenter()
                    ->grow(false)
                    ->formatStateUsing(fn (VisibilityLevel $state): string => match ($state) {
                        VisibilityLevel::Public => 'Publik',
                        VisibilityLevel::Internal => 'Internal',
                        VisibilityLevel::Confidential => 'Rahasia',
                    })
                    ->tooltip(fn (VisibilityLevel $state): string => $state->getLabel() ?? '')
                    ->sortable(),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->alignCenter()
                    ->grow(false)
                    ->formatStateUsing(fn (DocumentStatus $state): string => match ($state) {
                        DocumentStatus::Draft => 'Draf',
                        DocumentStatus::Pending => 'Menunggu',
                        DocumentStatus::Revision => 'Revisi',
                        DocumentStatus::WaitingSignature => 'Menunggu TTD',
                        DocumentStatus::Approved => 'Disahkan',
                        DocumentStatus::Rejected => 'Ditolak',
                        DocumentStatus::Expired => 'Kedaluwarsa',
                        DocumentStatus::Archived => 'Arsip',
                    })
                    ->tooltip(fn (DocumentStatus $state): string => $state->getLabel() ?? '')
                    ->sortable(),

                TextColumn::make('expired_date')
                    ->label('Masa Berlaku')
                    ->badge()
                    ->alignCenter()
                    ->grow(false)
                    ->state(fn (Document $record): string => ($record->has_expiry && $record->expired_date)
                        ? $record->expired_date->format('d M Y')
                        : ($record->has_expiry ? 'Belum Diatur' : 'Permanen'))
                    ->color(function (Document $record): string {
                        if (! $record->has_expiry) {
                            return 'info';
                        }

                        if (! $record->expired_date) {
                            return 'gray';
                        }

                        if ($record->expired_date->isPast()) {
                            return 'danger';
                        }

                        if ($record->expired_date->lte(now()->addDays(30))) {
                            return 'warning';
                        }

                        return 'success';
                    })
                    ->tooltip(function (Document $record): string {
                        if (! $record->has_expiry) {
                            return 'Dokumen Tetap / Permanen (Tanpa batas kedaluwarsa)';
                        }

                        if (! $record->expired_date) {
                            return 'Tanggal kedaluwarsa belum diisi';
                        }

                        return $record->expired_date->isPast()
                            ? 'Kedaluwarsa sejak '.$record->expired_date->format('d M Y')
                            : 'Aktif berlaku sampai '.$record->expired_date->format('d M Y');
                    })
                    ->sortable(),

                IconColumn::make('is_borang')
                    ->label('Borang')
                    ->boolean()
                    ->trueIcon('heroicon-o-academic-cap')
                    ->trueColor('primary')
                    ->falseIcon('heroicon-o-minus')
                    ->falseColor('gray')
                    ->alignCenter()
                    ->grow(false)
                    ->tooltip(fn (Document $record): string => $record->is_borang ? 'Bukti Borang Akreditasi' : 'Bukan Bukti Borang'),

                TextColumn::make('issued_date')
                    ->label('Tgl Terbit')
                    ->date('d M Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('created_at')
                    ->label('Dibuat Pada')
                    ->dateTime('d M Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters(array_values(array_filter([
                SelectFilter::make('unit_id')
                    ->label('Unit Kerja / Program Studi')
                    ->relationship('unit', 'name')
                    ->searchable()
                    ->preload(),

                SelectFilter::make('document_category_id')
                    ->label('Kategori Dokumen')
                    ->relationship('category', 'name')
                    ->searchable()
                    ->preload(),

                SelectFilter::make('academic_year_id')
                    ->label('Tahun Akademik')
                    ->relationship('academicYear', 'name')
                    ->searchable()
                    ->preload(),

                SelectFilter::make('status')
                    ->label('Status Dokumen')
                    ->options(DocumentStatus::class)
                    ->multiple()
                    ->native(false),

                $withVisibilityFilter ? SelectFilter::make('visibility')
                    ->label('Tingkat Aksesibilitas')
                    ->options(VisibilityLevel::class)
                    ->native(false) : null,

                TernaryFilter::make('is_borang')
                    ->label('Bukti Borang Akreditasi')
                    ->placeholder('Semua Dokumen')
                    ->trueLabel('Hanya Bukti Borang')
                    ->falseLabel('Bukan Bukti Borang')
                    ->native(false),

                TernaryFilter::make('has_expiry')
                    ->label('Sifat Masa Berlaku')
                    ->placeholder('Semua Jenis Dokumen')
                    ->trueLabel('Bermasa Berlaku (Ada Kedaluwarsa)')
                    ->falseLabel('Dokumen Tetap / Permanen')
                    ->native(false),

                Filter::make('issued_period')
                    ->label('Rentang Tanggal Terbit')
                    ->schema([
                        DatePicker::make('issued_from')
                            ->label('Terbit Dari Tanggal')
                            ->native(false),
                        DatePicker::make('issued_until')
                            ->label('Terbit Sampai Tanggal')
                            ->native(false),
                    ])
                    ->columns(2)
                    ->columnSpan(2)
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                filled($data['issued_from'] ?? null),
                                fn (Builder $query): Builder => $query->whereDate('documents.issued_date', '>=', (string) $data['issued_from']),
                            )
                            ->when(
                                filled($data['issued_until'] ?? null),
                                fn (Builder $query): Builder => $query->whereDate('documents.issued_date', '<=', (string) $data['issued_until']),
                            );
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];

                        if (filled($data['issued_from'] ?? null)) {
                            $indicators[] = Indicator::make('Terbit >= '.Carbon::parse((string) $data['issued_from'])->translatedFormat('d M Y'))
                                ->removeField('issued_from');
                        }

                        if (filled($data['issued_until'] ?? null)) {
                            $indicators[] = Indicator::make('Terbit <= '.Carbon::parse((string) $data['issued_until'])->translatedFormat('d M Y'))
                                ->removeField('issued_until');
                        }

                        return $indicators;
                    }),
            ])), layout: FiltersLayout::Modal)
            ->filtersFormColumns([
                'sm' => 2,
                'lg' => 3,
            ])
            ->filtersFormWidth(Width::FourExtraLarge)
            ->recordActions([
                ActionGroup::make([
                    Action::make('preview')
                        ->label('Pratinjau')
                        ->icon('heroicon-o-document-magnifying-glass')
                        ->color('info')
                        ->modalHeading(fn (Document $record): string => "Pratinjau: {$record->title}")
                        ->modalWidth(Width::SixExtraLarge)
                        ->modalContent(fn (Document $record): View => view('filament.components.document-preview-modal', [
                            'streamUrl' => route('admin.documents.stream', $record),
                            'downloadUrl' => route('admin.documents.download', $record),
                            'document' => $record,
                        ]))
                        ->modalSubmitAction(false)
                        ->modalCancelActionLabel('Tutup')
                        ->visible(fn (Document $record): bool => auth()->user()?->can('view', $record) && ($record->primaryFile !== null || (bool) ($record->files_exists ?? $record->files()->exists()))),

                    ViewAction::make()
                        ->url(fn (Document $record): string => DocumentResource::getUrl('view', ['record' => $record])),
                    EditAction::make()
                        ->url(fn (Document $record): string => DocumentResource::getUrl('edit', ['record' => $record])),

                    DocumentWorkflowActions::makeSubmitAction(),
                    DocumentWorkflowActions::makeApproveAction(useLoadedRelations: true),
                    DocumentWorkflowActions::makeUploadFinalScanAction(),
                    DocumentWorkflowActions::makeReturnFromWaitingSignatureAction(),
                    DocumentWorkflowActions::makeRequestRevisionAction(useLoadedRelations: true),
                    DocumentWorkflowActions::makeRejectAction(useLoadedRelations: true),
                    DocumentWorkflowActions::makeResubmitAction(),
                    DocumentWorkflowActions::makeStampAction(),

                    DeletionGuard::preventIfRestricted(DeleteAction::make()),
                ])
                    ->icon('heroicon-m-ellipsis-vertical')
                    ->tooltip('Aksi Dokumen'),
            ])
            ->bulkActions([
                BulkAction::make('downloadZip')
                    ->label('Unduh Paket ZIP (Latar Belakang)')
                    ->icon('heroicon-o-archive-box-arrow-down')
                    ->color('info')
                    ->requiresConfirmation()
                    ->modalHeading('Kemaskan Dokumen Terpilih ke ZIP?')
                    ->modalDescription('Sistem akan mengumpulkan berkas naskah resmi dari dokumen yang dipilih dan mengemasnya di latar belakang. Notifikasi lonceng berisi tautan unduh akan dikirimkan begitu proses selesai.')
                    ->modalSubmitActionLabel('Ya, Kemas di Latar Belakang')
                    ->visible(fn (): bool => (auth()->user()?->hasRole('super_admin') ?? false) && (auth()->user()?->can('archive.bulk_export_zip') ?? false))
                    ->action(function (Collection $records): void {
                        $user = auth()->user();
                        if (! $user || ! $user->hasRole('super_admin') || ! $user->can('archive.bulk_export_zip')) {
                            return;
                        }

                        $export = BulkExport::create([
                            'uuid' => (string) Str::uuid(),
                            'user_id' => $user->id,
                            'module_type' => 'documents',
                            'record_ids' => $records->pluck('id')->all(),
                            'total_files' => $records->count(),
                            'zip_file_name' => 'Arsip-Dokumen-'.now()->format('Ymd-His').'.zip',
                            'disk' => 'private',
                            'status' => 'pending',
                            'expires_at' => now()->addHours(24),
                        ]);

                        dispatch(new GenerateBulkZipArchiveJob($export));

                        Notification::make()
                            ->info()
                            ->title('Permintaan Ekspor Diterima')
                            ->body("Pengemasan {$records->count()} dokumen sedang diproses di latar belakang. Anda akan diberitahu lewat notifikasi lonceng setelah berkas ZIP siap diunduh.")
                            ->send();
                    })
                    ->deselectRecordsAfterCompletion(),

                DeletionGuard::preventBulkIfRestricted(DeleteBulkAction::make()),
            ])
            ->recordUrl(fn (Document $record): string => DocumentResource::getUrl('view', ['record' => $record]))
            ->defaultSort('created_at', 'desc');
    }
}
