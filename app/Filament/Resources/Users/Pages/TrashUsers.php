<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use App\Filament\Support\SoftDeleteTrashPage;

class TrashUsers extends SoftDeleteTrashPage
{
    protected static string $resource = UserResource::class;
}
