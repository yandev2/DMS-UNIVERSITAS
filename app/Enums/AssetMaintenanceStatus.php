<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum AssetMaintenanceStatus: string implements HasColor, HasLabel
{
    case Pending = 'pending';
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case Unrepairable = 'unrepairable';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Pending => 'Menunggu Pemeriksaan Teknisi',
            self::InProgress => 'Sedang Dikerjakan / Diservis',
            self::Completed => 'Perbaikan Selesai',
            self::Unrepairable => 'Tidak Dapat Diperbaiki (Rusak Berat)',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Pending => 'gray',
            self::InProgress => 'warning',
            self::Completed => 'success',
            self::Unrepairable => 'danger',
        };
    }
}
