<?php

declare(strict_types=1);

namespace App\Services\Concerns;

use Illuminate\Support\Carbon;

trait FormatsQueuedExportMetadata
{
    /**
     * Mengubah nilai filter boolean opsional ('1', '0', true, false) ke tipe `?bool`.
     */
    protected function parseNullableBoolean(mixed $value): ?bool
    {
        if ($value === null || $value === '') {
            return null;
        }

        return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
    }

    /**
     * Memformat rentang tanggal filter menjadi label periode yang mudah dibaca.
     */
    protected function formatDatePeriodLabel(?string $fromDate, ?string $untilDate): string
    {
        $from = filled($fromDate)
            ? Carbon::parse($fromDate)->translatedFormat('d M Y')
            : '-';
        $until = filled($untilDate)
            ? Carbon::parse($untilDate)->translatedFormat('d M Y')
            : '-';

        return "{$from} s.d. {$until}";
    }

    /**
     * Memformat ukuran berkas byte ke satuan B / KB / MB / GB.
     */
    protected function formatBytes(int $bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= (1 << (10 * $pow));

        return round($bytes, $precision).' '.$units[$pow];
    }
}
