<?php

declare(strict_types=1);

namespace App\Filament\Resources\OutgoingLetters\Tables;

use App\Enums\OutgoingLetterStatus;
use App\Filament\Resources\OutgoingLetters\OutgoingLetterResource;
use App\Jobs\GenerateBulkZipArchiveJob;
use App\Models\BulkExport;
use App\Models\OutgoingLetter;
use App\Rules\PdfDocumentFileRule;
use App\Services\LetterManagementService;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Support\Enums\Width;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Enums\PaginationMode;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class OutgoingLettersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->striped()
            ->paginationMode(PaginationMode::Simple)
            ->paginated([5, 10, 25, 50])
            ->defaultPaginationPageOption(10)
            ->searchDebounce('500ms')
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('index')
                    ->label('No.')
                    ->rowIndex()
                    ->alignCenter()
                    ->grow(false),

                TextColumn::make('letter_number')
                    ->label('Nomor Surat Keluar')
                    ->searchable()
                    ->copyable()
                    ->sortable()
                    ->weight('bold')
                    ->fontFamily('mono')
                    ->placeholder('[Menunggu Humas]')
                    ->description(fn (OutgoingLetter $record): string => 'Tanggal: '.($record->letter_date?->format('d M Y') ?? '-')),

                TextColumn::make('subject')
                    ->label('Perihal & Tujuan Surat')
                    ->searchable()
                    ->sortable()
                    ->limit(32)
                    ->tooltip(fn (OutgoingLetter $record): string => $record->subject)
                    ->description(fn (OutgoingLetter $record): string => 'Tujuan: '.Str::limit($record->destination_organization, 28)),

                TextColumn::make('unit.name')
                    ->label('Unit Pengaju')
                    ->searchable()
                    ->sortable()
                    ->limit(26)
                    ->tooltip(fn (OutgoingLetter $record): ?string => $record->unit?->name)
                    ->description(fn (OutgoingLetter $record): string => 'Pemohon: '.($record->creator?->name ? Str::limit($record->creator->name, 20) : '-')),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->alignCenter()
                    ->grow(false)
                    ->sortable(),
            ])
            ->filtersFormColumns([
                'sm' => 2,
                'lg' => 2,
            ])
            ->filtersFormWidth(Width::ExtraLarge)
            ->filters([
                SelectFilter::make('unit_id')
                    ->label('Unit Pengaju')
                    ->relationship('unit', 'name')
                    ->searchable()
                    ->preload(),

                SelectFilter::make('status')
                    ->label('Status Penomoran')
                    ->options(OutgoingLetterStatus::class),
            ], layout: FiltersLayout::Modal)
            ->recordActions([
                ActionGroup::make([
                    ViewAction::make(),

                    Action::make('preview')
                        ->label('Pratinjau Berkas')
                        ->icon('heroicon-o-document-magnifying-glass')
                        ->color('info')
                        ->modalHeading(fn (OutgoingLetter $record): string => "Pratinjau Surat Keluar: {$record->subject}")
                        ->modalWidth(Width::SixExtraLarge)
                        ->modalContent(fn (OutgoingLetter $record): View => view('filament.components.letter-preview-modal', [
                            'streamUrl' => route('outgoing-letters.file.stream', $record->uuid),
                            'downloadUrl' => route('outgoing-letters.file.download', $record->uuid),
                            'filePath' => $record->getActiveFilePath(),
                            'fileName' => $record->file_name ?: basename((string) $record->getActiveFilePath()),
                            'letterNumber' => $record->letter_number,
                            'isStamped' => filled($record->stamped_file_path),
                            'title' => $record->subject,
                        ]))
                        ->modalSubmitAction(false)
                        ->modalCancelActionLabel('Tutup')
                        ->visible(fn (OutgoingLetter $record): bool => filled($record->getActiveFilePath())),

                    EditAction::make()
                        ->visible(fn (OutgoingLetter $record): bool => OutgoingLetterResource::canEdit($record)),

                    Action::make('uploadFinalScan')
                        ->label('Unggah Scan TTD Basah')
                        ->icon('heroicon-o-arrow-up-tray')
                        ->color('success')
                        ->modalHeading('Unggah Scan Final Surat Keluar (TTD Basah & Cap)')
                        ->modalSubmitActionLabel('Arsipkan & Terbitkan')
                        ->form([
                            FileUpload::make('scan_file')
                                ->label('Berkas Scan PDF Final')
                                ->helperText('Wajib format PDF (.pdf), maks. 20 MB.')
                                ->disk('private')
                                ->directory('outgoing-letters')
                                ->acceptedFileTypes(['application/pdf'])
                                ->rules(['mimes:pdf', new PdfDocumentFileRule])
                                ->maxSize(20480)
                                ->storeFileNamesIn('scan_file_name')
                                ->required(),
                            Textarea::make('note')
                                ->label('Catatan Pengarsipan (Opsional)')
                                ->rows(2),
                        ])
                        ->action(function (array $data, OutgoingLetter $record): void {
                            $rawPath = $data['scan_file'] ?? null;
                            $storedPath = is_array($rawPath) ? Arr::first($rawPath) : $rawPath;

                            app(LetterManagementService::class)->uploadOutgoingFinalScan(
                                $record,
                                auth()->user(),
                                (string) $storedPath,
                                is_string($data['scan_file_name'] ?? null) ? $data['scan_file_name'] : null,
                                is_string($data['note'] ?? null) ? $data['note'] : null
                            );

                            Notification::make()
                                ->success()
                                ->title('Surat Keluar Resmi Terbit')
                                ->body("Scan final untuk nomor {$record->letter_number} telah tersimpan.")
                                ->send();
                        })
                        ->visible(fn (OutgoingLetter $record): bool => $record->canUploadFinalScan(auth()->user())),

                    DeleteAction::make()
                        ->visible(fn (OutgoingLetter $record): bool => OutgoingLetterResource::canDelete($record)),
                ]),
            ])
            ->bulkActions([
                BulkAction::make('downloadZip')
                    ->label('Unduh Paket ZIP (Latar Belakang)')
                    ->icon('heroicon-o-archive-box-arrow-down')
                    ->color('info')
                    ->requiresConfirmation()
                    ->modalHeading('Kemaskan Surat Keluar Terpilih ke ZIP?')
                    ->modalDescription('Sistem akan mengumpulkan berkas surat keluar resmi berstempel yang dipilih dan mengemasnya di latar belakang. Notifikasi lonceng berisi tautan unduh akan dikirimkan begitu proses selesai.')
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
                            'module_type' => 'outgoing_letters',
                            'record_ids' => $records->pluck('id')->all(),
                            'total_files' => $records->count(),
                            'zip_file_name' => 'Arsip-Surat-Keluar-'.now()->format('Ymd-His').'.zip',
                            'disk' => 'private',
                            'status' => 'pending',
                            'expires_at' => now()->addHours(24),
                        ]);

                        dispatch(new GenerateBulkZipArchiveJob($export));

                        Notification::make()
                            ->info()
                            ->title('Permintaan Ekspor Diterima')
                            ->body("Pengemasan {$records->count()} surat keluar sedang diproses di latar belakang. Anda akan diberitahu lewat notifikasi lonceng setelah berkas ZIP siap diunduh.")
                            ->send();
                    })
                    ->deselectRecordsAfterCompletion(),

                DeleteBulkAction::make()
                    ->visible(fn (): bool => auth()->user()?->can('delete_any:outgoing_letter') ?? false),
            ]);
    }
}
