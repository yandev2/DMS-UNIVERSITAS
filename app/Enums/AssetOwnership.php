<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum AssetOwnership: string implements HasColor, HasLabel
{
    case Owned = 'owned';
    case Grant = 'grant';
    case Rented = 'rented';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Owned => 'Milik Sendiri / Yayasan (SD)',
            self::Grant => 'Hibah / Bantuan Pemerintah',
            self::Rented => 'Sewa / Pinjam Pakai (SW)',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Owned => 'success',
            self::Grant => 'info',
            self::Rented => 'warning',
        };
    }
}
