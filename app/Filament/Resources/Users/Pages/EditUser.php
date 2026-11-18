<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use App\Filament\Support\DeletionGuard;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeletionGuard::preventIfRestricted(DeleteAction::make()),
            DeletionGuard::preventIfRestricted(ForceDeleteAction::make()),
            RestoreAction::make(),
        ];
    }
}
