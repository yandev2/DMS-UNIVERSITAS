<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum PostStatus: string implements HasColor, HasLabel
{
    case Draft = 'draft';
    case PendingReview = 'pending_review';
    case Revision = 'revision';
    case Published = 'published';
    case Archived = 'archived';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Draft => 'Draf Artikel',
            self::PendingReview => 'Menunggu Moderasi Humas',
            self::Revision => 'Perlu Revisi',
            self::Published => 'Tayang Publik (Published)',
            self::Archived => 'Diarsipkan',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Draft => 'gray',
            self::PendingReview => 'warning',
            self::Revision => 'danger',
            self::Published => 'success',
            self::Archived => 'info',
        };
    }
}
