<?php

declare(strict_types=1);

namespace App\Filament\Resources\OutgoingLetters\Pages;

use App\Enums\OutgoingLetterStatus;
use App\Filament\Resources\OutgoingLetters\OutgoingLetterResource;
use App\Models\OutgoingLetter;
use App\Rules\PdfDocumentFileRule;
use App\Services\LetterManagementService;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\View as SchemaView;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Arr;
use Illuminate\Support\HtmlString;

class ViewOutgoingLetter extends ViewRecord
{
    protected static string $resource = OutgoingLetterResource::class;

    /**
     * @return array<Action|ActionGroup>
     */
    public function getCachedHeaderActions(): array
    {
        return array_values(array_filter(
            $this->cachedHeaderActions,
            fn ($action): bool => ($action instanceof Action ? $action->getName() : '') !== 'preview',
        ));
    }

    protected function getHeaderActions(): array
    {
        /** @var OutgoingLetter $record */
        $record = $this->record;

        return [
            Action::make('approveByHumas')
                ->label('Terbitkan Nomor & Sahkan')
                ->icon('heroicon-o-check-badge')
                ->color('success')
                ->modalHeading('Terbitkan Nomor & Sahkan Surat Keluar')
                ->modalDescription('Sistem akan menerbitkan nomor surat keluar resmi otomatis untuk unit '.$record->unit?->name.' dan membubuhkan stempel ke berkas.')
                ->modalSubmitActionLabel('Terbitkan Nomor & Sahkan')
                ->form([
                    Toggle::make('requires_wet_signature')
                        ->label('Perlu TTD Basah & Cap Sebelum Terbit Final')
                        ->helperText('Jika aktif, nomor surat keluar diterbitkan sekarang, lalu Unit Pengaju atau Humas wajib mengunggah hasil scan PDF ber-TTD basah & cap untuk menyelesaikan status Terbit.')
                        ->default((bool) $record->requires_wet_signature),

                    Textarea::make('note')
                        ->label('Catatan Humas (Opsional)')
                        ->placeholder('Contoh: Nomor resmi telah diterbitkan dan berkas siap diunduh...')
                        ->rows(3),
                ])
                ->action(function (array $data) use ($record): void {
                    $requiresWetSignature = (bool) ($data['requires_wet_signature'] ?? false);

                    app(LetterManagementService::class)->approveOutgoingLetter(
                        $record,
                        auth()->user(),
                        $data['note'] ?? null,
                        $requiresWetSignature
                    );

                    $record->refresh();

                    Notification::make()
                        ->success()
                        ->title($record->status === OutgoingLetterStatus::WaitingSignature
                            ? 'Nomor Terbit — Menunggu TTD Basah'
                            : 'Nomor Surat Keluar Resmi Diterbitkan')
                        ->body($record->status === OutgoingLetterStatus::WaitingSignature
                            ? "Nomor surat {$record->letter_number} telah diterbitkan. Silakan unduh, cetak untuk TTD basah & cap, lalu unggah scan PDF final."
                            : "Nomor surat {$record->letter_number} telah diterbitkan dan berkas siap diunduh.")
                        ->send();
                })
                ->visible(fn (): bool => $record->canBeReviewedByHumas(auth()->user())),

            Action::make('uploadFinalScan')
                ->label('Unggah Scan TTD Basah')
                ->icon('heroicon-o-arrow-up-tray')
                ->color('success')
                ->modalHeading('Unggah Scan Final Surat Keluar (TTD Basah & Cap)')
                ->modalDescription('Unggah berkas PDF hasil scan surat keluar yang sudah ditandatangani basah dan dicap resmi.')
                ->modalSubmitActionLabel('Arsipkan & Terbitkan')
                ->form([
                    Placeholder::make('letter_print_info')
                        ->label('Nomor Surat Keluar')
                        ->content(function () use ($record): HtmlString {
                            $number = e($record->letter_number ?: '-');
                            $downloadUrl = e(route('outgoing-letters.file.download', $record->uuid));

                            return new HtmlString(
                                '<div style="padding: 0.75rem 1rem; border-radius: 0.5rem; background: rgba(37, 99, 235, 0.08); border: 1px solid rgba(37, 99, 235, 0.25); font-size: 0.8125rem;">'
                                .'<div><strong>Nomor Resmi:</strong> <code style="font-family: ui-monospace, monospace; font-weight: 700;">'.$number.'</code></div>'
                                .'<div style="margin-top: 0.35rem;">Belum dicetak? <a href="'.$downloadUrl.'" target="_blank" style="text-decoration: underline; font-weight: 600;">Unduh Berkas Bernomor</a></div>'
                                .'</div>'
                            );
                        }),

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
                ->action(function (array $data) use ($record): void {
                    $rawPath = $data['scan_file'] ?? null;
                    $storedPath = is_array($rawPath) ? Arr::first($rawPath) : $rawPath;

                    app(LetterManagementService::class)->uploadOutgoingFinalScan(
                        $record,
                        auth()->user(),
                        (string) $storedPath,
                        is_string($data['scan_file_name'] ?? null) ? $data['scan_file_name'] : null,
                        is_string($data['note'] ?? null) ? $data['note'] : null
                    );

                    $record->refresh();

                    Notification::make()
                        ->success()
                        ->title('Surat Keluar Resmi Terbit')
                        ->body("Scan final untuk nomor {$record->letter_number} telah tersimpan.")
                        ->send();
                })
                ->visible(fn (): bool => $record->canUploadFinalScan(auth()->user())),

            Action::make('requestRevision')
                ->label('Minta Revisi')
                ->icon('heroicon-o-arrow-path')
                ->color('warning')
                ->modalHeading('Minta Perbaikan Surat Keluar')
                ->form([
                    Textarea::make('note')
                        ->label('Catatan Revisi Wajib')
                        ->placeholder('Jelaskan bagian surat keluar yang perlu diperbaiki oleh unit pengaju...')
                        ->required()
                        ->rows(3),
                ])
                ->action(function (array $data) use ($record): void {
                    app(LetterManagementService::class)->requestRevisionOutgoing(
                        $record,
                        auth()->user(),
                        $data['note']
                    );

                    $record->refresh();

                    Notification::make()
                        ->warning()
                        ->title('Permintaan Revisi Dikirim ke Unit')
                        ->body('Status surat keluar diubah menjadi Perlu Revisi.')
                        ->send();
                })
                ->visible(fn (): bool => $record->canBeReviewedByHumas(auth()->user())),

            Action::make('reject')
                ->label('Tolak')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->modalHeading('Tolak Pengajuan Surat Keluar')
                ->form([
                    Textarea::make('note')
                        ->label('Alasan Penolakan Wajib')
                        ->placeholder('Jelaskan alasan penolakan pengajuan surat keluar ini...')
                        ->required()
                        ->rows(3),
                ])
                ->action(function (array $data) use ($record): void {
                    app(LetterManagementService::class)->rejectOutgoing(
                        $record,
                        auth()->user(),
                        $data['note']
                    );

                    $record->refresh();

                    Notification::make()
                        ->danger()
                        ->title('Pengajuan Surat Keluar Ditolak')
                        ->body('Alasan penolakan telah dikirimkan ke pemohon.')
                        ->send();
                })
                ->visible(fn (): bool => $record->canBeReviewedByHumas(auth()->user())),

            Action::make('resubmit')
                ->label('Ajukan Kembali ke Humas')
                ->icon('heroicon-o-paper-airplane')
                ->color('info')
                ->requiresConfirmation()
                ->modalHeading('Ajukan Kembali Surat Keluar ke Humas?')
                ->modalDescription('Surat keluar yang telah diperbaiki akan dikirim kembali ke antrean penomoran Bagian Humas.')
                ->modalSubmitActionLabel('Ya, Ajukan Kembali')
                ->action(function () use ($record): void {
                    app(LetterManagementService::class)->resubmitOutgoing($record, auth()->user());
                    $record->refresh();

                    Notification::make()
                        ->success()
                        ->title('Surat Keluar Diajukan Kembali')
                        ->body('Surat telah masuk kembali ke antrean peninjauan Bagian Humas.')
                        ->send();
                })
                ->visible(fn (): bool => $record->canBeResubmittedBy(auth()->user())),

            Action::make('preview')
                ->label('Pratinjau Berkas')
                ->icon('heroicon-o-document-magnifying-glass')
                ->color('info')
                ->modalHeading("Pratinjau Surat Keluar: {$record->subject}")
                ->modalWidth(Width::SixExtraLarge)
                ->modalContent(fn (): View => view('filament.components.letter-preview-modal', [
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
                ->visible(fn (): bool => filled($record->getActiveFilePath())),

            Action::make('download')
                ->label('Unduh Berkas')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->url(fn (): string => route('outgoing-letters.file.download', $record->uuid))
                ->visible(fn (): bool => filled($record->getActiveFilePath())),

            EditAction::make()
                ->visible(fn (): bool => OutgoingLetterResource::canEdit($record)),

            DeleteAction::make()
                ->visible(fn (): bool => OutgoingLetterResource::canDelete($record)),
        ];
    }

    public function content(Schema $schema): Schema
    {
        /** @var OutgoingLetter $record */
        $record = $this->getRecord();

        return $schema
            ->components([
                SchemaView::make('filament.components.outgoing-letter-detail')
                    ->viewData([
                        'letter' => $record,
                    ]),
            ]);
    }
}
