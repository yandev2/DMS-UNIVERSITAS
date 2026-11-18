<?php

declare(strict_types=1);

namespace App\Filament\Resources\OutgoingLetters\Pages;

use App\Filament\Resources\OutgoingLetters\OutgoingLetterResource;
use App\Models\OutgoingLetter;
use App\Services\LetterManagementService;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\View as SchemaView;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Illuminate\Contracts\View\View;

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
                    Textarea::make('note')
                        ->label('Catatan Humas (Opsional)')
                        ->placeholder('Contoh: Nomor resmi telah diterbitkan dan berkas siap diunduh...')
                        ->rows(3),
                ])
                ->action(function (array $data) use ($record): void {
                    app(LetterManagementService::class)->approveOutgoingLetter(
                        $record,
                        auth()->user(),
                        $data['note'] ?? null
                    );

                    $record->refresh();

                    Notification::make()
                        ->success()
                        ->title('Nomor Surat Keluar Resmi Diterbitkan')
                        ->body("Nomor surat {$record->letter_number} telah diterbitkan dan berkas siap diunduh.")
                        ->send();
                })
                ->visible(fn (): bool => $record->canBeReviewedByHumas(auth()->user())),

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
