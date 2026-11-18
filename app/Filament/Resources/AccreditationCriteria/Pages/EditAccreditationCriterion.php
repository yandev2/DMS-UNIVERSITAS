<?php

namespace App\Filament\Resources\AccreditationCriteria\Pages;

use App\Filament\Resources\AccreditationCriteria\AccreditationCriterionResource;
use App\Filament\Support\DeletionGuard;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditAccreditationCriterion extends EditRecord
{
    protected static string $resource = AccreditationCriterionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeletionGuard::preventIfRestricted(DeleteAction::make()),
        ];
    }
}
