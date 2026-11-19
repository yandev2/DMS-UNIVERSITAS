<?php

declare(strict_types=1);

namespace App\Filament\Resources\OutgoingLetters\Pages;

use App\Filament\Resources\OutgoingLetters\OutgoingLetterResource;
use App\Models\OutgoingLetter;
use App\Services\LetterManagementService;
use Filament\Resources\Pages\CreateRecord;

class CreateOutgoingLetter extends CreateRecord
{
    protected static string $resource = OutgoingLetterResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $user = auth()->user();
        $data['created_by'] = $user?->id;

        if ($user && $user->unit_id) {
            $requestedUnitId = filled($data['unit_id'] ?? null) ? (int) $data['unit_id'] : null;
            $hasGlobalLetterScope = $user->can('letter.manage_humas') || $user->can('document.bypass_unit_scope');

            if ($requestedUnitId === null || (! $hasGlobalLetterScope && ! $user->canAccessUnit($requestedUnitId))) {
                $data['unit_id'] = $user->unit_id;
            }
        }

        return $data;
    }

    protected function afterCreate(): void
    {
        /** @var OutgoingLetter $record */
        $record = $this->record;

        app(LetterManagementService::class)->processNewOutgoingLetter($record, auth()->user());
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->record]);
    }
}
