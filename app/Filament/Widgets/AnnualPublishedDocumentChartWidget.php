<?php

namespace App\Filament\Widgets;

use App\Enums\DocumentStatus;
use App\Models\Document;
use App\Models\User;
use App\Support\DashboardPeriodFilter;
use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\Widget;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class AnnualPublishedDocumentChartWidget extends Widget
{
    use HasWidgetShield;
    use InteractsWithPageFilters;

    protected static bool $isLazy = false;

    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = 'full';

    protected ?string $pollingInterval = null;

    protected string $view = 'filament.widgets.annual-published-document-chart-widget';

    /**
     * @return array<string, mixed>
     */
    public function getViewData(): array
    {
        /** @var User|null $user */
        $user = Auth::user();

        $period = DashboardPeriodFilter::resolve($this->pageFilters);
        $year = $period['chart_year'];
        $selectedEndMonth = $period['end_month'];

        $monthFullNames = DashboardPeriodFilter::MONTH_NAMES;
        $monthShortNames = DashboardPeriodFilter::MONTH_SHORT_NAMES;

        $periodPillLong = $period['is_default_current_month']
            ? 'Bulan Ini ('.($monthFullNames[$selectedEndMonth] ?? 'Bulan Ini').')'
            : ($period['is_single_month']
                ? 'Bulan Dipilih ('.($monthFullNames[$selectedEndMonth] ?? '').')'
                : 'Periode Dipilih');

        $periodPillShort = $period['is_default_current_month']
            ? 'Bulan Ini'
            : ($period['is_single_month']
                ? ($monthShortNames[$selectedEndMonth] ?? 'Bulan Ini')
                : 'Periode');

        if (! $user) {
            return [
                'year' => $year,
                'months' => [],
                'totalYearly' => 0,
                'currentMonthCount' => 0,
                'currentMonthName' => $monthFullNames[$selectedEndMonth] ?? 'Bulan Ini',
                'periodPillLong' => $periodPillLong,
                'periodPillShort' => $periodPillShort,
                'periodStartDate' => $period['start_date']->toDateString(),
                'periodEndDate' => $period['end_date']->toDateString(),
                'monthlyAverage' => 0,
                'highestMonthLabel' => '-',
                'scopeLabel' => 'Tidak Ada Akses',
                'maxScale' => 5,
            ];
        }

        $startOfYear = Carbon::createFromDate($year, 1, 1)->startOfDay();
        $endOfYear = Carbon::createFromDate($year, 12, 31)->endOfDay();

        $docVersion = Document::getStatsCacheVersion();
        $scopeKey = Document::statsCacheScopeKeyFor($user);
        $cacheKey = "dashboard_annual_chart:v{$docVersion}:{$year}:{$period['cache_key']}:{$scopeKey}";

        /** @var array{monthly: array<int, int>, period_count: int} $chartData */
        $chartData = Cache::remember($cacheKey, now()->addSeconds(300), static function () use ($user, $startOfYear, $endOfYear, $period): array {
            $counts = array_fill(1, 12, 0);
            $driver = DB::connection()->getDriverName();
            $monthExpr = match ($driver) {
                'pgsql' => 'EXTRACT(MONTH FROM documents.issued_date)::int',
                'sqlite' => "CAST(strftime('%m', documents.issued_date) AS INTEGER)",
                default => 'MONTH(documents.issued_date)',
            };

            $publishedStatuses = [
                DocumentStatus::Approved->value,
                DocumentStatus::Archived->value,
                DocumentStatus::Expired->value,
            ];

            $rows = Document::query()
                ->accessibleFor($user)
                ->toBase()
                ->whereIn('documents.status', $publishedStatuses)
                ->whereBetween('documents.issued_date', [
                    $startOfYear->toDateString(),
                    $endOfYear->toDateString(),
                ])
                ->selectRaw("{$monthExpr} as month_num, COUNT(*) as total")
                ->groupByRaw($monthExpr)
                ->get();

            foreach ($rows as $row) {
                $monthNum = (int) ($row->month_num ?? 0);
                if ($monthNum >= 1 && $monthNum <= 12) {
                    $counts[$monthNum] = (int) ($row->total ?? 0);
                }
            }

            if ($period['start_year'] === $period['chart_year'] && $period['end_year'] === $period['chart_year']) {
                $periodCount = 0;
                for ($m = $period['start_month']; $m <= $period['end_month']; $m++) {
                    $periodCount += (int) ($counts[$m] ?? 0);
                }
            } else {
                $periodQuery = Document::query()
                    ->accessibleFor($user)
                    ->whereIn('documents.status', $publishedStatuses);
                DashboardPeriodFilter::applyToDocumentIssuedDate($periodQuery, $period);
                $periodCount = (int) $periodQuery->toBase()->count();
            }

            return [
                'monthly' => $counts,
                'period_count' => $periodCount,
            ];
        });

        $rawCounts = $chartData['monthly'];
        $monthsData = [];

        for ($m = 1; $m <= 12; $m++) {
            $count = (int) ($rawCounts[$m] ?? 0);
            $monthDate = Carbon::createFromDate($year, $m, 1);
            $isWithinPeriod = $monthDate->betweenIncluded(
                $period['start_date']->startOfMonth(),
                $period['end_date']->startOfMonth()
            );

            $monthsData[$m] = [
                'number' => $m,
                'short_name' => $monthShortNames[$m],
                'full_name' => $monthFullNames[$m],
                'count' => $count,
                'is_current' => $isWithinPeriod,
            ];
        }

        $totalYearly = array_sum($rawCounts);
        $maxCount = max($rawCounts);
        $maxScale = $maxCount > 0 ? (int) (ceil($maxCount / 5) * 5) : 5;
        if ($maxScale < $maxCount) {
            $maxScale = $maxCount;
        }

        // Hitung persentase tinggi bar dan persentase kontribusi per bulan
        foreach ($monthsData as $m => $data) {
            $count = $data['count'];
            $monthsData[$m]['height_percent'] = $maxScale > 0 ? min(100, (int) round(($count / $maxScale) * 100)) : 0;
            $monthsData[$m]['share_percent'] = $totalYearly > 0 ? round(($count / $totalYearly) * 100, 1) : 0;
        }

        // Temukan bulan dengan terbitan tertinggi
        $highestMonthLabel = '-';
        if ($maxCount > 0) {
            $highestMonths = array_keys($rawCounts, $maxCount);
            $highestMonthNum = end($highestMonths);
            $highestMonthLabel = $monthFullNames[$highestMonthNum]." ({$maxCount} Naskah)";
        }

        // Tentukan label cakupan wewenang unit pengguna
        if ($user->hasRole('super_admin')) {
            $scopeLabel = 'Seluruh Kampus (Super Administrator)';
        } elseif ($user->can('document.bypass_unit_scope')) {
            $scopeLabel = 'Lintas Seluruh Fakultas & Unit Kerja';
        } elseif ($user->unit) {
            $scopeLabel = $user->unit->name.($user->unit->code ? " ({$user->unit->code})" : '');
        } else {
            $scopeLabel = 'Unit Kerja Pengguna';
        }

        return [
            'year' => $year,
            'months' => $monthsData,
            'totalYearly' => $totalYearly,
            'currentMonthCount' => (int) ($chartData['period_count'] ?? 0),
            'currentMonthName' => $monthFullNames[$selectedEndMonth] ?? 'Bulan Ini',
            'periodPillLong' => $periodPillLong,
            'periodPillShort' => $periodPillShort,
            'periodStartDate' => Carbon::createFromDate($year, 1, 1)->toDateString(),
            'periodEndDate' => Carbon::createFromDate($year, 12, 31)->toDateString(),
            'monthlyAverage' => $totalYearly > 0 ? round($totalYearly / 12, 1) : 0,
            'highestMonthLabel' => $highestMonthLabel,
            'scopeLabel' => $scopeLabel,
            'maxScale' => $maxScale,
        ];
    }
}
