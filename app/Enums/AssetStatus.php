<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum AssetStatus: string implements HasColor, HasLabel
{
    case Available = 'available';
    case InUse = 'in_use';
    case Borrowed = 'borrowed';
    case InMaintenance = 'in_maintenance';
    case Damaged = 'damaged';
    case Lost = 'lost';
    case Disposed = 'disposed';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Available => 'Tersedia (Gudang BAUK)',
            self::InUse => 'Dipakai (Aktif di Unit)',
            self::Borrowed => 'Dipinjam Sementara',
            self::InMaintenance => 'Sedang Diperbaiki / Servis',
            self::Damaged => 'Rusak (Ditarik dari Operasional)',
            self::Lost => 'Hilang (Dalam Investigasi)',
            self::Disposed => 'Dihapusbukukan (Afkir SK Rektor)',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Available => 'info',
            self::InUse => 'success',
            self::Borrowed => 'primary',
            self::InMaintenance => 'warning',
            self::Damaged => 'danger',
            self::Lost => 'gray',
            self::Disposed => 'gray',
        };
    }
}
