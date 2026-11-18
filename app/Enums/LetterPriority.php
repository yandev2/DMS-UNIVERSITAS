<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum LetterPriority: string implements HasColor, HasLabel
{
    case Biasa = 'biasa';
    case Penting = 'penting';
    case Segera = 'segera';
    case Rahasia = 'rahasia';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Biasa => 'Biasa / Rutin',
            self::Penting => 'Penting',
            self::Segera => 'Segera / Kilat',
            self::Rahasia => 'Rahasia',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Biasa => 'gray',
            self::Penting => 'info',
            self::Segera => 'warning',
            self::Rahasia => 'danger',
        };
    }
}
