<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum IncomingRecipientStatus: string implements HasColor, HasLabel
{
    case Waiting = 'waiting';
    case Unread = 'unread';
    case Read = 'read';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Waiting => 'Menunggu Giliran',
            self::Unread => 'Belum Dibaca',
            self::Read => 'Sudah Dibaca',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Waiting => 'gray',
            self::Unread => 'warning',
            self::Read => 'success',
        };
    }
}
