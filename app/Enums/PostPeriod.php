<?php

declare(strict_types=1);

namespace App\Enums;

use BackedEnum;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;

enum PostPeriod: string implements HasColor, HasIcon, HasLabel
{
    case Daily = 'daily';
    case Weekly = 'weekly';
    case Monthly = 'monthly';

    public function getLabel(): string|Htmlable|null
    {
        return match ($this) {
            self::Daily => 'Berita Harian',
            self::Weekly => 'Berita Mingguan',
            self::Monthly => 'Berita Bulanan',
        };
    }

    public function getShortLabel(): string
    {
        return match ($this) {
            self::Daily => 'Harian',
            self::Weekly => 'Mingguan',
            self::Monthly => 'Bulanan',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Daily => 'info',
            self::Weekly => 'warning',
            self::Monthly => 'success',
        };
    }

    public function getIcon(): string|BackedEnum|Htmlable|null
    {
        return match ($this) {
            self::Daily => Heroicon::OutlinedSun,
            self::Weekly => Heroicon::OutlinedCalendarDays,
            self::Monthly => Heroicon::OutlinedNewspaper,
        };
    }
}
