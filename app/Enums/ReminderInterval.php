<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum ReminderInterval: string implements HasColor, HasLabel
{
    case H60 = 'h60';
    case H30 = 'h30';
    case H7 = 'h7';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::H60 => 'H-60 Hari Sebelum Kedaluwarsa',
            self::H30 => 'H-30 Hari Sebelum Kedaluwarsa',
            self::H7 => 'H-7 Hari (Kritis) Sebelum Kedaluwarsa',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::H60 => 'info',
            self::H30 => 'warning',
            self::H7 => 'danger',
        };
    }

    public function daysBeforeExpiry(): int
    {
        return match ($this) {
            self::H60 => 60,
            self::H30 => 30,
            self::H7 => 7,
        };
    }
}
