<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum AssetCondition: string implements HasColor, HasLabel
{
    case Good = 'good';
    case MinorDamage = 'minor_damage';
    case MajorDamage = 'major_damage';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Good => 'Baik / Terawat',
            self::MinorDamage => 'Rusak Ringan',
            self::MajorDamage => 'Rusak Berat',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Good => 'success',
            self::MinorDamage => 'warning',
            self::MajorDamage => 'danger',
        };
    }
}
