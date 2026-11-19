<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Document;
use App\Models\Post;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class DashboardPeriodFilter
{
    /**
     * @var array<int, string>
     */
    public const MONTH_NAMES = [
        1 => 'Januari',
        2 => 'Februari',
        3 => 'Maret',
        4 => 'April',
        5 => 'Mei',
        6 => 'Juni',
        7 => 'Juli',
        8 => 'Agustus',
        9 => 'September',
        10 => 'Oktober',
        11 => 'November',
        12 => 'Desember',
    ];

    /**
     * @var array<int, string>
     */
    public const MONTH_SHORT_NAMES = [
        1 => 'Jan',
        2 => 'Feb',
        3 => 'Mar',
        4 => 'Apr',
        5 => 'Mei',
        6 => 'Jun',
        7 => 'Jul',
        8 => 'Agu',
        9 => 'Sep',
        10 => 'Okt',
        11 => 'Nov',
        12 => 'Des',
    ];

    /**
     * Nilai default filter adalah bulan dan tahun berjalan saat ini.
     *
     * @return array{start_month: int, start_year: int, end_month: int, end_year: int}
     */
    public static function defaults(?CarbonInterface $reference = null): array
    {
        $now = $reference ? CarbonImmutable::instance($reference) : CarbonImmutable::now();

        return [
            'start_month' => $now->month,
            'start_year' => $now->year,
            'end_month' => $now->month,
            'end_year' => $now->year,
        ];
    }

    /**
     * Menormalisasi input filter menjadi rentang tanggal valid serta label periode Indonesia.
     *
     * @param  array<string, mixed>|null  $filters
     * @return array{
     *     start_month: int,
     *     start_year: int,
     *     end_month: int,
     *     end_year: int,
     *     start_date: CarbonImmutable,
     *     end_date: CarbonImmutable,
     *     is_single_month: bool,
     *     is_default_current_month: bool,
     *     chart_year: int,
     *     cache_key: string,
     *     label: string
     * }
     */
    public static function resolve(?array $filters, ?CarbonInterface $reference = null): array
    {
        $now = $reference ? CarbonImmutable::instance($reference) : CarbonImmutable::now();
        $defaults = self::defaults($now);

        $startMonth = self::normalizeMonth($filters['start_month'] ?? null, $defaults['start_month']);
        $startYear = self::normalizeYear($filters['start_year'] ?? null, $defaults['start_year']);
        $endMonth = self::normalizeMonth($filters['end_month'] ?? null, $defaults['end_month']);
        $endYear = self::normalizeYear($filters['end_year'] ?? null, $defaults['end_year']);

        $startDate = CarbonImmutable::create($startYear, $startMonth, 1)->startOfMonth();
        $endDate = CarbonImmutable::create($endYear, $endMonth, 1)->endOfMonth();

        // Jika pengguna memilih rentang terbalik, tukar otomatis agar tetap valid
        if ($startDate->greaterThan($endDate)) {
            [$startMonth, $endMonth] = [$endMonth, $startMonth];
            [$startYear, $endYear] = [$endYear, $startYear];
            $startDate = CarbonImmutable::create($startYear, $startMonth, 1)->startOfMonth();
            $endDate = CarbonImmutable::create($endYear, $endMonth, 1)->endOfMonth();
        }

        $isSingleMonth = ($startYear === $endYear) && ($startMonth === $endMonth);
        $isDefaultCurrentMonth = $isSingleMonth
            && ($startMonth === $now->month)
            && ($startYear === $now->year);

        return [
            'start_month' => $startMonth,
            'start_year' => $startYear,
            'end_month' => $endMonth,
            'end_year' => $endYear,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'is_single_month' => $isSingleMonth,
            'is_default_current_month' => $isDefaultCurrentMonth,
            'chart_year' => $endYear,
            'cache_key' => $startDate->format('Ym').'-'.$endDate->format('Ym'),
            'label' => self::formatRangeLabel($startMonth, $startYear, $endMonth, $endYear),
        ];
    }

    /**
     * Memfilter query dokumen berdasarkan kolom `issued_date` pada rentang bulan-tahun aktif.
     *
     * @param  Builder<Document>  $query
     * @param  array{start_date: CarbonImmutable, end_date: CarbonImmutable}  $period
     * @return Builder<Document>
     */
    public static function applyToDocumentIssuedDate(Builder $query, array $period): Builder
    {
        return $query->whereBetween('documents.issued_date', [
            $period['start_date']->toDateString(),
            $period['end_date']->toDateString(),
        ]);
    }

    /**
     * Daftar opsi bulan berbahasa Indonesia untuk form select filter.
     *
     * @return array<int, string>
     */
    public static function monthOptions(): array
    {
        return self::MONTH_NAMES;
    }

    /**
     * Daftar opsi tahun dinamis berdasarkan data dokumen/berita terawal hingga tahun berjalan.
     *
     * @return array<int, string>
     */
    public static function yearOptions(): array
    {
        $currentYear = (int) now()->year;
        $docVersion = Document::getStatsCacheVersion();
        $postVersion = Post::getStatsCacheVersion();

        /** @var int $minYear */
        $minYear = Cache::remember(
            "dashboard_filter_min_year:d{$docVersion}:p{$postVersion}",
            now()->addMinutes(10),
            static function () use ($currentYear): int {
                $minDocDate = DB::table('documents')
                    ->whereNull('deleted_at')
                    ->min('issued_date');

                $minPostDate = DB::table('posts')
                    ->whereNull('deleted_at')
                    ->min('published_at');

                $years = [$currentYear - 4];

                if (is_string($minDocDate) && $minDocDate !== '') {
                    $years[] = (int) substr($minDocDate, 0, 4);
                }

                if (is_string($minPostDate) && $minPostDate !== '') {
                    $years[] = (int) substr($minPostDate, 0, 4);
                }

                return max(2000, min($years));
            }
        );

        $maxYear = $currentYear + 1;
        $options = [];

        for ($year = $maxYear; $year >= $minYear; $year--) {
            $options[$year] = (string) $year;
        }

        return $options;
    }

    public static function formatRangeLabel(int $startMonth, int $startYear, int $endMonth, int $endYear): string
    {
        if ($startYear === $endYear && $startMonth === $endMonth) {
            return (self::MONTH_NAMES[$startMonth] ?? '')." {$startYear}";
        }

        if ($startYear === $endYear) {
            $from = self::MONTH_SHORT_NAMES[$startMonth] ?? '';
            $to = self::MONTH_SHORT_NAMES[$endMonth] ?? '';

            return "{$from} – {$to} {$startYear}";
        }

        $from = self::MONTH_SHORT_NAMES[$startMonth] ?? '';
        $to = self::MONTH_SHORT_NAMES[$endMonth] ?? '';

        return "{$from} {$startYear} – {$to} {$endYear}";
    }

    private static function normalizeMonth(mixed $value, int $fallback): int
    {
        if (! is_numeric($value)) {
            return $fallback;
        }

        $month = (int) $value;

        return ($month >= 1 && $month <= 12) ? $month : $fallback;
    }

    private static function normalizeYear(mixed $value, int $fallback): int
    {
        if (! is_numeric($value)) {
            return $fallback;
        }

        $year = (int) $value;

        return ($year >= 2000 && $year <= 2100) ? $year : $fallback;
    }
}
