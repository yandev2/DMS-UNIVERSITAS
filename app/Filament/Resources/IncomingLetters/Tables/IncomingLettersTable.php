<?php

declare(strict_types=1);

namespace App\Filament\Resources\IncomingLetters\Tables;

use App\Enums\IncomingLetterStatus;
use App\Enums\LetterPriority;
use App\Filament\Resources\IncomingLetters\IncomingLetterResource;
use App\Jobs\GenerateBulkZipArchiveJob;
use App\Models\BulkExport;
use App\Models\IncomingLetter;
use App\Services\LetterManagementService;
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
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Enums\PaginationMode;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;

class IncomingLettersTable
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

                TextColumn::make('agenda_number')
                    ->label('Nomor Agenda Humas')
                    ->searchable()
                    ->copyable()
                    ->sortable()
                    ->weight('bold')
                    ->fontFamily('mono')
                    ->placeholder('[Otomatis]')
                    ->description(fn (IncomingLetter $record): string => 'Diterima: '.($record->received_date?->format('d M Y') ?? '-')),

                TextColumn::make('sender_name')
                    ->label('Asal Surat / Pengirim')
                    ->searchable()
                    ->sortable()
                    ->limit(26)
                    ->tooltip(fn (IncomingLetter $record): string => $record->sender_name)
                    ->description(fn (IncomingLetter $record): ?string => $record->external_letter_number
                        ? 'No: '.Str::limit($record->external_letter_number, 22)
                        : null),

                TextColumn::make('subject')
                    ->label('Perihal Surat')
                    ->searchable()
                    ->sortable()
                    ->limit(32)
                    ->tooltip(fn (IncomingLetter $record): string => $record->subject)
                    ->description(fn (IncomingLetter $record): ?string => $record->unit
                        ? 'Tujuan: '.Str::limit($record->unit->name, 26)
                        : null),

                TextColumn::make('priority')
                    ->label('Sifat')
                    ->badge()
                    ->alignCenter()
                    ->grow(false)
                    ->sortable(),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->alignCenter()
                    ->grow(false)
                    ->sortable(),
            ])
            ->filtersFormColumns([
                'sm' => 2,
                'lg' => 3,
            ])
            ->filtersFormWidth(Width::FourExtraLarge)
            ->filters([
                SelectFilter::make('unit_id')
                    ->label('Unit Tujuan')
                    ->relationship('unit', 'name')
                    ->searchable()
                    ->preload(),

                SelectFilter::make('status')
                    ->label('Status Distribusi')
                    ->options(IncomingLetterStatus::class),

                SelectFilter::make('priority')
                    ->label('Sifat Surat')
                    ->options(LetterPriority::class),
            ], layout: FiltersLayout::Modal)
            ->recordActions([
                Action::make('markAsRead')
                    ->label('Tandai Dibaca')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->modalHeading('Tandai Surat Masuk Sudah Dibaca')
                    ->modalDescription('Konfirmasi bahwa Anda telah membaca surat masuk ini. Jika masih ada pejabat berikutnya pada urutan distribusi, surat akan otomatis diteruskan.')
                    ->form([
                        Textarea::make('disposition_note')
                            ->label('Catatan Disposisi / Pengantar (Opsional)')
                            ->placeholder('Tuliskan catatan disposisi atau arahan tindak lanjut jika diperlukan...')
                            ->rows(3),
                    ])
                    ->action(function (IncomingLetter $record, array $data): void {
                        app(LetterManagementService::class)->markAsRead(
                            $record,
                            auth()->user(),
                            $data['disposition_note'] ?? null
                        );

                        Notification::make()
                            ->success()
                            ->title('Surat Masuk Ditandai Sudah Dibaca')
                            ->body('Status baca Anda telah tercatat pada sistem pelacakan Humas.')
                            ->send();
                    })
                    ->visible(fn (IncomingLetter $record): bool => $record->canUserMarkAsRead(auth()->user())),

                ActionGroup::make([
                    ViewAction::make(),

                    Action::make('preview')
                        ->label('Pratinjau PDF')
                        ->icon('heroicon-o-document-magnifying-glass')
                        ->color('info')
                        ->modalHeading(fn (IncomingLetter $record): string => "Pratinjau Surat Masuk: {$record->subject}")
                        ->modalWidth(Width::SixExtraLarge)
                        ->modalContent(fn (IncomingLetter $record): View => view('filament.components.letter-preview-modal', [
                            'streamUrl' => route('incoming-letters.file.stream', $record->uuid),
                            'downloadUrl' => route('incoming-letters.file.download', $record->uuid),
                            'filePath' => $record->getActiveFilePath(),
                            'fileName' => $record->file_name ?: basename((string) $record->getActiveFilePath()),
                            'letterNumber' => $record->agenda_number,
                            'isStamped' => filled($record->stamped_file_path),
                            'title' => $record->subject,
                        ]))
                        ->modalSubmitAction(false)
                        ->modalCancelActionLabel('Tutup')
                        ->visible(fn (IncomingLetter $record): bool => filled($record->getActiveFilePath())),

                    Action::make('download')
                        ->label('Unduh PDF Berstempel')
                        ->icon('heroicon-o-arrow-down-tray')
                        ->color('gray')
                        ->url(fn (IncomingLetter $record): string => route('incoming-letters.file.download', $record->uuid))
                        ->visible(fn (IncomingLetter $record): bool => filled($record->getActiveFilePath())),

                    EditAction::make()
                        ->visible(fn (IncomingLetter $record): bool => IncomingLetterResource::canEdit($record)),

                    DeleteAction::make()
                        ->visible(fn (IncomingLetter $record): bool => IncomingLetterResource::canDelete($record)),
                ]),
            ])
            ->bulkActions([
                BulkAction::make('downloadZip')
                    ->label('Unduh Paket ZIP (Latar Belakang)')
                    ->icon('heroicon-o-archive-box-arrow-down')
                    ->color('info')
                    ->requiresConfirmation()
                    ->modalHeading('Kemaskan Surat Masuk Terpilih ke ZIP?')
                    ->modalDescription('Sistem akan mengumpulkan berkas surat masuk berstempel agenda yang dipilih dan mengemasnya di latar belakang. Notifikasi lonceng berisi tautan unduh akan dikirimkan begitu proses selesai.')
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
                            'module_type' => 'incoming_letters',
                            'record_ids' => $records->pluck('id')->all(),
                            'total_files' => $records->count(),
                            'zip_file_name' => 'Arsip-Surat-Masuk-'.now()->format('Ymd-His').'.zip',
                            'disk' => 'private',
                            'status' => 'pending',
                            'expires_at' => now()->addHours(24),
                        ]);

                        dispatch(new GenerateBulkZipArchiveJob($export));

                        Notification::make()
                            ->info()
                            ->title('Permintaan Ekspor Diterima')
                            ->body("Pengemasan {$records->count()} surat masuk sedang diproses di latar belakang. Anda akan diberitahu lewat notifikasi lonceng setelah berkas ZIP siap diunduh.")
                            ->send();
                    })
                    ->deselectRecordsAfterCompletion(),

                DeleteBulkAction::make()
                    ->visible(fn (): bool => auth()->user()?->can('delete_any:incoming_letter') ?? false),
            ]);
    }
}
