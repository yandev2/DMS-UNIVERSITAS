<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum DocumentStatus: string implements HasColor, HasLabel
{
    case Draft = 'draft';
    case Pending = 'pending';
    case Revision = 'revision';
    case WaitingSignature = 'waiting_signature';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Expired = 'expired';
    case Archived = 'archived';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Draft => 'Draf',
            self::Pending => 'Menunggu Persetujuan',
            self::Revision => 'Perlu Revisi',
            self::WaitingSignature => 'Menunggu TTD Basah & Cap',
            self::Approved => 'Disahkan (Approved)',
            self::Rejected => 'Ditolak',
            self::Expired => 'Kedaluwarsa',
            self::Archived => 'Diarsipkan (Pasif)',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Draft => 'gray',
            self::Pending => 'warning',
            self::Revision => 'danger',
            self::WaitingSignature => 'info',
            self::Approved => 'success',
            self::Rejected => 'danger',
            self::Expired => 'gray',
            self::Archived => 'info',
        };
    }
}
