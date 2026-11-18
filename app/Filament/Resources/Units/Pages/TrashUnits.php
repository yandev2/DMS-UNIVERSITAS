<?php

namespace App\Filament\Resources\Units\Pages;

use App\Filament\Resources\Units\UnitResource;
use App\Filament\Support\SoftDeleteTrashPage;

class TrashUnits extends SoftDeleteTrashPage
{
    protected static string $resource = UnitResource::class;
}
