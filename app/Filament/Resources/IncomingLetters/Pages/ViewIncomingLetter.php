<?php

declare(strict_types=1);

namespace App\Filament\Resources\IncomingLetters\Pages;

use App\Enums\IncomingLetterStatus;
use App\Filament\Resources\IncomingLetters\IncomingLetterResource;
use App\Models\IncomingLetter;
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

class ViewIncomingLetter extends ViewRecord
{
    protected static string $resource = IncomingLetterResource::class;

    public function mount(int|string $record): void
    {
        parent::mount($record);

        /** @var IncomingLetter $letter */
        $letter = $this->record;
        app(LetterManagementService::class)->recordOpened($letter, auth()->user());
    }

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
        /** @var IncomingLetter $record */
        $record = $this->record;

        return [
            Action::make('markAsRead')
                ->label('Tandai Sudah Dibaca')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->modalHeading('Tandai Surat Masuk Sudah Dibaca')
                ->modalDescription('Konfirmasi bahwa Anda telah meninjau surat masuk ini. Jika masih ada pejabat berikutnya pada urutan distribusi, surat akan otomatis diteruskan beserta notifikasi.')
                ->modalSubmitActionLabel('Konfirmasi Sudah Dibaca')
                ->form([
                    Textarea::make('disposition_note')
                        ->label('Catatan Disposisi / Pengantar (Opsional)')
                        ->placeholder('Contoh: Mohon ditindaklanjuti sesuai jadwal / Diteruskan ke Bapak Rektor...')
                        ->rows(3),
                ])
                ->action(function (array $data) use ($record): void {
                    app(LetterManagementService::class)->markAsRead(
                        $record,
                        auth()->user(),
                        $data['disposition_note'] ?? null
                    );

                    $record->refresh();

                    Notification::make()
                        ->success()
                        ->title('Surat Masuk Ditandai Sudah Dibaca')
                        ->body($record->status === IncomingLetterStatus::Completed
                            ? 'Terima kasih. Seluruh tahapan distribusi surat masuk ini telah selesai dibaca.'
                            : 'Status baca Anda telah tercatat dan surat otomatis diteruskan ke pejabat berikutnya.')
                        ->send();
                })
                ->visible(fn (): bool => $record->canUserMarkAsRead(auth()->user())),

            Action::make('preview')
                ->label('Pratinjau PDF')
                ->icon('heroicon-o-document-magnifying-glass')
                ->color('info')
                ->modalHeading("Pratinjau Surat Masuk: {$record->subject}")
                ->modalWidth(Width::SixExtraLarge)
                ->modalContent(fn (): View => view('filament.components.letter-preview-modal', [
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
                ->visible(fn (): bool => filled($record->getActiveFilePath())),

            Action::make('download')
                ->label('Unduh PDF Berstempel')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->url(fn (): string => route('incoming-letters.file.download', $record->uuid))
                ->visible(fn (): bool => filled($record->getActiveFilePath())),

            EditAction::make()
                ->visible(fn (): bool => IncomingLetterResource::canEdit($record)),

            DeleteAction::make()
                ->visible(fn (): bool => IncomingLetterResource::canDelete($record)),
        ];
    }

    public function content(Schema $schema): Schema
    {
        /** @var IncomingLetter $record */
        $record = $this->getRecord();

        return $schema
            ->components([
                SchemaView::make('filament.components.incoming-letter-tracker')
                    ->viewData([
                        'letter' => $record,
                    ]),
            ]);
    }
}
