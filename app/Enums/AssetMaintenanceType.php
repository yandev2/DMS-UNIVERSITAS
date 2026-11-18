<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum AssetMaintenanceType: string implements HasColor, HasLabel
{
    case Repair = 'repair';
    case RoutineService = 'routine_service';
    case Calibration = 'calibration';
    case Upgrade = 'upgrade';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Repair => 'Perbaikan Kerusakan (Repair)',
            self::RoutineService => 'Servis Rutin / Berkala (Preventive)',
            self::Calibration => 'Kalibrasi Alat Laboratorium',
            self::Upgrade => 'Upgrade Komponen / Spesifikasi',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Repair => 'danger',
            self::RoutineService => 'info',
            self::Calibration => 'primary',
            self::Upgrade => 'success',
        };
    }
}
