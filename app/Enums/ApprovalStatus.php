<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum ApprovalStatus: string implements HasColor, HasLabel
{
    case Waiting = 'waiting';
    case Pending = 'pending';
    case Approved = 'approved';
    case Revision = 'revision';
    case Rejected = 'rejected';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Waiting => 'Menunggu Giliran Tingkat Sebelumnya',
            self::Pending => 'Menunggu Keputusan Pejabat',
            self::Approved => 'Disetujui',
            self::Revision => 'Minta Revisi',
            self::Rejected => 'Ditolak',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Waiting => 'gray',
            self::Pending => 'warning',
            self::Approved => 'success',
            self::Revision => 'danger',
            self::Rejected => 'danger',
        };
    }
}
