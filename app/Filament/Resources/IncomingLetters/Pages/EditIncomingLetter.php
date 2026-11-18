<?php

declare(strict_types=1);

namespace App\Filament\Resources\IncomingLetters\Pages;

use App\Enums\IncomingLetterStatus;
use App\Enums\IncomingRecipientStatus;
use App\Filament\Resources\IncomingLetters\IncomingLetterResource;
use App\Models\IncomingLetter;
use App\Services\LetterManagementService;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditIncomingLetter extends EditRecord
{
    protected static string $resource = IncomingLetterResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }

    protected function afterSave(): void
    {
        /** @var IncomingLetter $record */
        $record = $this->record;

        if (filled($record->original_file_path)) {
            $stampedPath = app(LetterManagementService::class)->stampIncomingPdf($record);
            if ($stampedPath) {
                $record->update(['stamped_file_path' => $stampedPath]);
            }
        }

        $recipients = $record->recipients()->with('recipient')->orderBy('step_order')->orderBy('id')->get();
        $totalSteps = $recipients->count();
        $stepIndex = 1;

        foreach ($recipients as $item) {
            $user = $item->recipient;
            $pos = $item->recipient_position_snapshot
                ?: ($user?->position_title ?: ($user?->roles->first()?->name ?: 'Pejabat Kampus'));

            $status = $item->status;
            if ($stepIndex === (int) $record->current_step && $status === IncomingRecipientStatus::Waiting) {
                $status = IncomingRecipientStatus::Unread;
            }

            $item->update([
                'step_order' => $stepIndex,
                'recipient_position_snapshot' => $pos,
                'status' => $status,
            ]);

            $stepIndex++;
        }

        $allRead = $recipients->isNotEmpty() && $recipients->every(
            fn ($r) => $r->status === IncomingRecipientStatus::Read
        );

        $record->update([
            'total_steps' => $totalSteps,
            'status' => $allRead ? IncomingLetterStatus::Completed : IncomingLetterStatus::InProgress,
        ]);
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->record]);
    }
}
