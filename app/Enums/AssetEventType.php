<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum AssetEventType: string implements HasColor, HasLabel
{
    case InitialPlacement = 'initial_placement';
    case TransferUnit = 'transfer_unit';
    case RelocateRoom = 'relocate_room';
    case Borrow = 'borrow';
    case Return = 'return';
    case StatusChange = 'status_change';
    case StockOpname = 'stock_opname';
    case Disposal = 'disposal';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::InitialPlacement => 'Penempatan Awal Pengadaan',
            self::TransferUnit => 'Mutasi Antar Unit / Prodi',
            self::RelocateRoom => 'Pemindahan Ruangan (Internal Unit)',
            self::Borrow => 'Peminjaman Sementara',
            self::Return => 'Pengembalian Aset Pinjaman',
            self::StatusChange => 'Perubahan Status / Kondisi Fisik',
            self::StockOpname => 'Verifikasi Cek Fisik (Stock Opname)',
            self::Disposal => 'Penghapusan Aset (Disposal SK Rektor)',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::InitialPlacement => 'info',
            self::TransferUnit => 'primary',
            self::RelocateRoom => 'info',
            self::Borrow => 'warning',
            self::Return => 'success',
            self::StatusChange => 'gray',
            self::StockOpname => 'success',
            self::Disposal => 'danger',
        };
    }
}
