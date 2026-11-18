<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum StorageDisk: string implements HasColor, HasLabel
{
    case Private = 'private';
    case Public = 'public';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Private => 'Privat (Otentikasi Controller)',
            self::Public => 'Publik (Symlink Terbuka)',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Private => 'warning',
            self::Public => 'success',
        };
    }
}
