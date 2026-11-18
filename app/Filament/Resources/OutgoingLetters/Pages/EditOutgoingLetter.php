<?php

declare(strict_types=1);

namespace App\Filament\Resources\OutgoingLetters\Pages;

use App\Enums\OutgoingLetterStatus;
use App\Filament\Resources\OutgoingLetters\OutgoingLetterResource;
use App\Models\OutgoingLetter;
use App\Services\LetterManagementService;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditOutgoingLetter extends EditRecord
{
    protected static string $resource = OutgoingLetterResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }

    protected function afterSave(): void
    {
        /** @var OutgoingLetter $record */
        $record = $this->record;
        $service = app(LetterManagementService::class);

        if ($record->status === OutgoingLetterStatus::NeedsRevision && ! (auth()->user()?->hasAnyRole(['super_admin', 'humas']) ?? false)) {
            $service->resubmitOutgoing($record, auth()->user());

            return;
        }

        if ($record->status === OutgoingLetterStatus::Published && filled($record->original_file_path)) {
            $stampedPath = $service->stampOutgoingFile($record);
            if ($stampedPath) {
                $record->update(['stamped_file_path' => $stampedPath]);
            }
        }
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->record]);
    }
}
