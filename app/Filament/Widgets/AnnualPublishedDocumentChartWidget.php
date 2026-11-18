<?php

namespace App\Filament\Widgets;

use App\Enums\DocumentStatus;
use App\Models\Document;
use App\Models\User;
use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;
use Filament\Widgets\Widget;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class AnnualPublishedDocumentChartWidget extends Widget
{
    use HasWidgetShield;

    protected static bool $isLazy = false;

    protected static ?int $sort = 2;

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

        $year = (int) date('Y');
        $currentMonth = (int) date('n');

        $monthFullNames = [
            1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
            5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
            9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
        ];

        $monthShortNames = [
            1 => 'Jan', 2 => 'Feb', 3 => 'Mar', 4 => 'Apr',
            5 => 'Mei', 6 => 'Jun', 7 => 'Jul', 8 => 'Agu',
            9 => 'Sep', 10 => 'Okt', 11 => 'Nov', 12 => 'Des',
        ];

        if (! $user) {
            return [
                'year' => $year,
                'months' => [],
                'totalYearly' => 0,
                'currentMonthCount' => 0,
                'currentMonthName' => $monthFullNames[$currentMonth] ?? 'Bulan Ini',
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
        $cacheKey = "dashboard_annual_chart:v{$docVersion}:{$year}:{$scopeKey}";

        /** @var array<int, int> $rawCounts */
        $rawCounts = Cache::remember($cacheKey, now()->addSeconds(300), static function () use ($user, $startOfYear, $endOfYear): array {
            $counts = array_fill(1, 12, 0);
            $driver = DB::connection()->getDriverName();
            $dateExpr = 'COALESCE(documents.issued_date, documents.approved_at, documents.created_at)';
            $monthExpr = match ($driver) {
                'pgsql' => "EXTRACT(MONTH FROM {$dateExpr})::int",
                'sqlite' => "CAST(strftime('%m', {$dateExpr}) AS INTEGER)",
                default => "MONTH({$dateExpr})",
            };

            $rows = Document::query()
                ->accessibleFor($user)
                ->toBase()
                ->whereIn('documents.status', [
                    DocumentStatus::Approved->value,
                    DocumentStatus::Archived->value,
                    DocumentStatus::Expired->value,
                ])
                ->where(static function ($query) use ($startOfYear, $endOfYear): void {
                    $query->whereBetween('documents.issued_date', [$startOfYear->toDateString(), $endOfYear->toDateString()])
                        ->orWhere(static function ($sub) use ($startOfYear, $endOfYear): void {
                            $sub->whereNull('documents.issued_date')
                                ->whereBetween('documents.approved_at', [$startOfYear->toDateTimeString(), $endOfYear->toDateTimeString()]);
                        })
                        ->orWhere(static function ($sub2) use ($startOfYear, $endOfYear): void {
                            $sub2->whereNull('documents.issued_date')
                                ->whereNull('documents.approved_at')
                                ->whereBetween('documents.created_at', [$startOfYear->toDateTimeString(), $endOfYear->toDateTimeString()]);
                        });
                })
                ->selectRaw("{$monthExpr} as month_num, COUNT(*) as total")
                ->groupByRaw($monthExpr)
                ->get();

            foreach ($rows as $row) {
                $monthNum = (int) ($row->month_num ?? 0);
                if ($monthNum >= 1 && $monthNum <= 12) {
                    $counts[$monthNum] = (int) ($row->total ?? 0);
                }
            }

            return $counts;
        });

        $monthsData = [];

        for ($m = 1; $m <= 12; $m++) {
            $count = (int) ($rawCounts[$m] ?? 0);

            $monthsData[$m] = [
                'number' => $m,
                'short_name' => $monthShortNames[$m],
                'full_name' => $monthFullNames[$m],
                'count' => $count,
                'is_current' => ($m === $currentMonth),
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
            'currentMonthCount' => $rawCounts[$currentMonth] ?? 0,
            'currentMonthName' => $monthFullNames[$currentMonth] ?? 'Bulan Ini',
            'monthlyAverage' => $totalYearly > 0 ? round($totalYearly / 12, 1) : 0,
            'highestMonthLabel' => $highestMonthLabel,
            'scopeLabel' => $scopeLabel,
            'maxScale' => $maxScale,
        ];
    }
}
