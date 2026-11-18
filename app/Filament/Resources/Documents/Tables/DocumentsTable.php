<?php

declare(strict_types=1);

namespace App\Filament\Resources\Documents\Tables;

use App\Enums\DocumentStatus;
use App\Enums\VisibilityLevel;
use App\Filament\Resources\Documents\DocumentResource;
use App\Filament\Support\DeletionGuard;
use App\Jobs\GenerateBulkZipArchiveJob;
use App\Models\BulkExport;
use App\Models\Document;
use App\Services\DocumentApprovalService;
use App\Services\DocumentStampingService;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Support\Enums\Width;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Enums\PaginationMode;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
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

                    Action::make('approve')
                        ->label('Setujui Dokumen')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->form([
                            Textarea::make('note')
                                ->label('Catatan Persetujuan (Opsional)')
                                ->placeholder('Contoh: Disetujui tanpa catatan...')
                                ->rows(3),
                        ])
                        ->action(function (Document $record, array $data): void {
                            app(DocumentApprovalService::class)->approve($record, auth()->user(), $data['note'] ?? null);
                            $record->refresh();
                            Notification::make()
                                ->success()
                                ->title('Dokumen Berhasil Disetujui')
                                ->body($record->status === DocumentStatus::Approved
                                    ? "Dokumen resmi disahkan! Nomor resmi {$record->document_number} telah terbit."
                                    : 'Persetujuan Anda telah dicatat dan diteruskan ke tahapan berikutnya.')
                                ->send();
                        })
                        ->visible(fn (Document $record): bool => app(DocumentApprovalService::class)->canUserApprove(auth()->user(), $record, useLoadedRelations: true)),

                    Action::make('requestRevision')
                        ->label('Minta Revisi')
                        ->icon('heroicon-o-arrow-path')
                        ->color('warning')
                        ->form([
                            Textarea::make('note')
                                ->label('Catatan Revisi / Perbaikan Wajib')
                                ->placeholder('Jelaskan bagian yang perlu diperbaiki...')
                                ->required()
                                ->rows(3),
                        ])
                        ->action(function (Document $record, array $data): void {
                            app(DocumentApprovalService::class)->requestRevision($record, auth()->user(), $data['note']);
                            Notification::make()
                                ->warning()
                                ->title('Permintaan Revisi Dikirim')
                                ->body('Catatan perbaikan telah dicatat dan status dokumen berubah menjadi Revisi.')
                                ->send();
                        })
                        ->visible(fn (Document $record): bool => app(DocumentApprovalService::class)->canUserApprove(auth()->user(), $record, useLoadedRelations: true)),

                    Action::make('reject')
                        ->label('Tolak Dokumen')
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->form([
                            Textarea::make('note')
                                ->label('Alasan Penolakan Wajib')
                                ->placeholder('Jelaskan alasan penolakan naskah dinas...')
                                ->required()
                                ->rows(3),
                        ])
                        ->action(function (Document $record, array $data): void {
                            app(DocumentApprovalService::class)->reject($record, auth()->user(), $data['note']);
                            Notification::make()
                                ->danger()
                                ->title('Dokumen Ditolak')
                                ->body('Keputusan penolakan telah dicatat secara resmi.')
                                ->send();
                        })
                        ->visible(fn (Document $record): bool => app(DocumentApprovalService::class)->canUserApprove(auth()->user(), $record, useLoadedRelations: true)),

                    Action::make('resubmit')
                        ->label('Ajukan Kembali')
                        ->icon('heroicon-o-paper-airplane')
                        ->color('info')
                        ->requiresConfirmation()
                        ->modalHeading('Ajukan Kembali Dokumen?')
                        ->modalDescription('Dokumen yang telah direvisi akan diajukan kembali ke alur persetujuan tingkat 1.')
                        ->modalSubmitActionLabel('Ya, Ajukan Kembali')
                        ->action(function (Document $record): void {
                            app(DocumentApprovalService::class)->resubmit($record);
                            Notification::make()
                                ->success()
                                ->title('Dokumen Berhasil Diajukan Kembali')
                                ->body('Dokumen telah masuk kembali ke antrean persetujuan tingkat 1.')
                                ->send();
                        })
                        ->visible(fn (Document $record): bool => $record->status === DocumentStatus::Revision && auth()->user()?->can('update', $record)),

                    Action::make('stamp')
                        ->label(fn (Document $record): string => $record->primaryFile?->stamped_file_path ? 'Perbarui Stempel' : 'Bubuhi Stempel')
                        ->icon('heroicon-o-check-badge')
                        ->color('success')
                        ->requiresConfirmation()
                        ->modalHeading('Bubuhi Stempel Resmi & QR Code?')
                        ->modalDescription('Sistem akan membubuhi kotak penomoran naskah dinas, tanggal pengesahan, pejabat penandatangan, dan stempel QR-Code verifikasi ke berkas dokumen.')
                        ->modalSubmitActionLabel('Ya, Bubuhi Stempel')
                        ->action(function (Document $record): void {
                            $stampedFile = app(DocumentStampingService::class)->stamp($record);
                            if ($stampedFile && $stampedFile->stamped_file_path) {
                                Notification::make()
                                    ->success()
                                    ->title('Stempel Berhasil Dibubuhkan')
                                    ->body('Berkas fisik dokumen kini telah resmi berstempel dan memiliki QR Code keabsahan.')
                                    ->send();
                            } else {
                                Notification::make()
                                    ->warning()
                                    ->title('Stempel Dilewati')
                                    ->body('Dokumen tidak memiliki berkas fisik atau diatur tanpa stempel visual.')
                                    ->send();
                            }
                        })
                        ->visible(fn (Document $record): bool => $record->status === DocumentStatus::Approved && auth()->user()?->can('update', $record) && ($record->primaryFile !== null || (bool) ($record->files_exists ?? $record->files()->exists()))),

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
