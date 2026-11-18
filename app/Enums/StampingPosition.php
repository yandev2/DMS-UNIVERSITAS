<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum StampingPosition: string implements HasLabel
{
    case TopRight = 'top_right';
    case TopLeft = 'top_left';
    case BottomCenter = 'bottom_center';
    case Footer = 'footer';
    case None = 'none';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::TopRight => 'Pojok Kanan Atas Halaman 1 (SK / ST / SE)',
            self::TopLeft => 'Pojok Kiri Atas Halaman 1 (Surat Masuk)',
            self::BottomCenter => 'Bagian Bawah Tengah (Sertifikat / Piagam)',
            self::Footer => 'Footer Margin Halaman (MoU / MoA)',
            self::None => 'Tanpa Barcode / Stempel (Hanya Penomoran Otomatis pada Dokumen)',
        };
    }
}
