<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum VisibilityLevel: string implements HasColor, HasLabel
{
    case Public = 'public';
    case Internal = 'internal';
    case Confidential = 'confidential';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Public => 'Publik (Terbuka Umum)',
            self::Internal => 'Internal Civitas Unit',
            self::Confidential => 'Rahasia Pimpinan (Confidential)',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Public => 'success',
            self::Internal => 'info',
            self::Confidential => 'danger',
        };
    }
}
