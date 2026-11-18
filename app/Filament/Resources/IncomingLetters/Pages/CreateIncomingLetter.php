<?php

declare(strict_types=1);

namespace App\Filament\Resources\IncomingLetters\Pages;

use App\Filament\Resources\IncomingLetters\IncomingLetterResource;
use App\Models\IncomingLetter;
use App\Services\LetterManagementService;
use Filament\Resources\Pages\CreateRecord;

class CreateIncomingLetter extends CreateRecord
{
    protected static string $resource = IncomingLetterResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['registered_by'] = auth()->id();

        return $data;
    }

    protected function afterCreate(): void
    {
        /** @var IncomingLetter $record */
        $record = $this->record;

        app(LetterManagementService::class)->initializeIncomingDistribution($record);
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->record]);
    }
}
