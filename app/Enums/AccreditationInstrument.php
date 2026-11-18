<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum AccreditationInstrument: string implements HasColor, HasLabel
{
    case BanPt = 'BAN-PT';
    case LamInfokom = 'LAM-INFOKOM';
    case Lamemba = 'LAMEMBA';
    case LamTeknik = 'LAM-TEKNIK';
    case Lamdik = 'LAMDIK';
    case Lamsama = 'LAMSAMA';
    case Lamptkes = 'LAM-PTKes';
    case Internasional = 'Akreditasi Internasional';
    case Lainnya = 'Instrumen Khusus / Lainnya';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::BanPt => 'BAN-PT (Akreditasi Institusi / Umum)',
            self::LamInfokom => 'LAM-INFOKOM (Informatika & Komputer)',
            self::Lamemba => 'LAMEMBA (Ekonomi, Manajemen & Bisnis)',
            self::LamTeknik => 'LAM-TEKNIK (Teknik & Rekayasa)',
            self::Lamdik => 'LAMDIK (Kependidikan)',
            self::Lamsama => 'LAMSAMA (Sains Alam & Formal)',
            self::Lamptkes => 'LAM-PTKes (Kesehatan)',
            self::Internasional => 'Akreditasi Internasional',
            self::Lainnya => 'Instrumen Khusus / Lainnya',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::BanPt => 'primary',
            self::LamInfokom => 'info',
            self::Lamemba => 'warning',
            self::LamTeknik => 'danger',
            self::Lamdik => 'success',
            self::Lamsama => 'cyan',
            self::Lamptkes => 'emerald',
            self::Internasional => 'purple',
            self::Lainnya => 'gray',
        };
    }

    /**
     * Get associative array of [value => label] for dropdown options.
     *
     * @return array<string, string>
     */
    public static function options(): array
    {
        $options = [];
        foreach (self::cases() as $case) {
            $options[$case->value] = $case->getLabel() ?? $case->value;
        }

        return $options;
    }

    /**
     * Normalize loose or legacy instrument strings to a canonical value.
     */
    public static function normalize(?string $value): ?string
    {
        if (blank($value)) {
            return null;
        }

        $cleaned = trim($value);
        $upper = strtoupper($cleaned);

        if (str_contains($upper, 'INFOKOM')) {
            return self::LamInfokom->value;
        }

        if (str_contains($upper, 'EMBA')) {
            return self::Lamemba->value;
        }

        if (str_contains($upper, 'TEKNIK')) {
            return self::LamTeknik->value;
        }

        if (str_contains($upper, 'DIK')) {
            return self::Lamdik->value;
        }

        if (str_contains($upper, 'SAMA')) {
            return self::Lamsama->value;
        }

        if (str_contains($upper, 'PTKES')) {
            return self::Lamptkes->value;
        }

        if (str_contains($upper, 'BAN') || str_contains($upper, 'APT')) {
            return self::BanPt->value;
        }

        return $cleaned;
    }
}
