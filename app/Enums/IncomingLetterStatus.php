<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum IncomingLetterStatus: string implements HasColor, HasLabel
{
    case InProgress = 'in_progress';
    case Completed = 'completed';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::InProgress => 'Dalam Distribusi',
            self::Completed => 'Selesai Dibaca',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::InProgress => 'warning',
            self::Completed => 'success',
        };
    }
}
