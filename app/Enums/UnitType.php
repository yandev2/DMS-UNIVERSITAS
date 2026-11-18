<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum UnitType: string implements HasColor, HasLabel
{
    case Rektorat = 'rektorat';
    case Fakultas = 'fakultas';
    case Prodi = 'prodi';
    case Biro = 'biro';
    case Lembaga = 'lembaga';
    case Upt = 'upt';
    case UnitPendukung = 'unit_pendukung';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Rektorat => 'Rektorat / Pimpinan Universitas',
            self::Fakultas => 'Fakultas',
            self::Prodi => 'Program Studi',
            self::Biro => 'Biro Administrasi',
            self::Lembaga => 'Lembaga',
            self::Upt => 'Unit Pelaksana Teknis (UPT)',
            self::UnitPendukung => 'Unit Pendukung / Bagian',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Rektorat => 'danger',
            self::Fakultas => 'primary',
            self::Prodi => 'success',
            self::Biro => 'warning',
            self::Lembaga => 'info',
            self::Upt => 'gray',
            self::UnitPendukung => 'gray',
        };
    }
}
