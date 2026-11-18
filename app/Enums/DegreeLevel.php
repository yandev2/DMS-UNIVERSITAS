<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum DegreeLevel: string implements HasLabel
{
    case D3 = 'D3';
    case D4 = 'D4';
    case S1 = 'S1';
    case S2 = 'S2';
    case S3 = 'S3';
    case Profesi = 'Profesi';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::D3 => 'Diploma Tiga (D3)',
            self::D4 => 'Sarjana Terapan (D4)',
            self::S1 => 'Sarjana (S1)',
            self::S2 => 'Magister (S2)',
            self::S3 => 'Doktor (S3)',
            self::Profesi => 'Program Profesi',
        };
    }
}
