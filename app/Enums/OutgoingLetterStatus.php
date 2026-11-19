<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum OutgoingLetterStatus: string implements HasColor, HasLabel
{
    case PendingHumas = 'pending_humas';
    case Revision = 'revision';
    case WaitingSignature = 'waiting_signature';
    case Published = 'published';
    case Rejected = 'rejected';

    public const NeedsRevision = self::Revision;

    public function getLabel(): ?string
    {
        return match ($this) {
            self::PendingHumas => 'Menunggu Tinjauan Humas',
            self::Revision => 'Perlu Revisi',
            self::WaitingSignature => 'Menunggu TTD Basah & Cap',
            self::Published => 'Terbit & Siap Diunduh',
            self::Rejected => 'Ditolak',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::PendingHumas => 'warning',
            self::Revision => 'danger',
            self::WaitingSignature => 'info',
            self::Published => 'success',
            self::Rejected => 'danger',
        };
    }
}
