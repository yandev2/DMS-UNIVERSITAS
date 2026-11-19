<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Enums\PostPeriod;
use App\Enums\PostStatus;
use App\Filament\Resources\Posts\PostResource;
use App\Models\Post;
use App\Models\User;
use App\Support\DashboardPeriodFilter;
use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

class PostPeriodOverviewWidget extends Widget
{
    use HasWidgetShield;
    use InteractsWithPageFilters;

    protected static bool $isLazy = false;

    protected static ?int $sort = 5;

    protected ?string $pollingInterval = null;

    protected int|string|array $columnSpan = 'full';

    protected string $view = 'filament.widgets.post-period-overview-widget';

    public static function canView(): bool
    {
        /** @var User|null $user */
        $user = Auth::user();
        if (! $user) {
            return false;
        }

        return $user->can('view:post_period_overview_widget') || $user->can('view_any:post');
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        /** @var User|null $user */
        $user = Auth::user();

        if (! $user) {
            return ['stats' => []];
        }

        $period = DashboardPeriodFilter::resolve($this->pageFilters);
        $rangeStart = $period['start_date']->startOfDay()->toDateTimeString();
        $rangeEnd = $period['end_date']->endOfDay()->toDateTimeString();

        $isBypass = $user->can('post.moderate_humas') || $user->can('document.bypass_unit_scope');
        $scopeKey = $isBypass
            ? 'all'
            : implode(':', [
                'u'.(int) ($user->unit_id ?? 0),
                'a'.(int) $user->id,
                $user->hasRole('dekan') ? 'dekan' : 'std',
            ]);

        $version = Post::getStatsCacheVersion();
        $cacheKey = "post_period_overview_rows:v{$version}:{$scopeKey}:{$period['cache_key']}";

        /** @var array<int, array{period: string, status: string, total: int, total_views: int, period_published_count: int}> $aggregates */
        $aggregates = Cache::remember($cacheKey, now()->addSeconds(120), static function () use ($user, $rangeStart, $rangeEnd): array {
            return Post::query()
                ->accessibleFor($user)
                ->whereRaw('COALESCE(posts.published_at, posts.created_at) BETWEEN ? AND ?', [$rangeStart, $rangeEnd])
                ->toBase()
                ->selectRaw('period, status, COUNT(*) as total, COALESCE(SUM(views_count), 0) as total_views')
                ->selectRaw('SUM(CASE WHEN published_at BETWEEN ? AND ? THEN 1 ELSE 0 END) as period_published_count', [$rangeStart, $rangeEnd])
                ->groupBy('period', 'status')
                ->get()
                ->map(static fn (object $row): array => [
                    'period' => (string) ($row->period ?? PostPeriod::Daily->value),
                    'status' => (string) ($row->status ?? ''),
                    'total' => (int) ($row->total ?? 0),
                    'total_views' => (int) ($row->total_views ?? 0),
                    'period_published_count' => (int) ($row->period_published_count ?? 0),
                ])
                ->all();
        });

        $byPeriod = [];
        foreach (PostPeriod::cases() as $case) {
            $byPeriod[$case->value] = [
                'published' => 0,
                'pending' => 0,
                'total' => 0,
                'views' => 0,
            ];
        }

        $publishedInPeriod = 0;
        $totalViews = 0;
        $totalPublishedFiltered = 0;

        foreach ($aggregates as $row) {
            if (! is_array($row)) {
                continue;
            }

            $periodKey = (string) (($row['period'] ?? '') !== '' ? $row['period'] : PostPeriod::Daily->value);
            if (! isset($byPeriod[$periodKey])) {
                continue;
            }

            $statusKey = (string) ($row['status'] ?? '');
            $count = (int) ($row['total'] ?? 0);
            $views = (int) ($row['total_views'] ?? 0);

            $byPeriod[$periodKey]['total'] += $count;
            $byPeriod[$periodKey]['views'] += $views;
            $totalViews += $views;

            if ($statusKey === PostStatus::Published->value) {
                $byPeriod[$periodKey]['published'] += $count;
                $totalPublishedFiltered += $count;
                $publishedInPeriod += (int) ($row['period_published_count'] ?? 0);
            } elseif ($statusKey === PostStatus::PendingReview->value) {
                $byPeriod[$periodKey]['pending'] += $count;
            }
        }

        $fourthCardLabel = $period['is_default_current_month']
            ? 'Tayang Bulan Ini'
            : 'Tayang Periode Ini';

        $stats = [
            [
                'label' => 'Berita Harian',
                'value' => $byPeriod[PostPeriod::Daily->value]['published'],
                'description' => "Total {$byPeriod[PostPeriod::Daily->value]['total']} • {$byPeriod[PostPeriod::Daily->value]['pending']} moderasi",
                'color' => 'blue',
                'icon' => 'sun',
                'url' => PostResource::getUrl('index', [
                    'filters' => ['period' => ['value' => PostPeriod::Daily->value]],
                ]),
            ],
            [
                'label' => 'Berita Mingguan',
                'value' => $byPeriod[PostPeriod::Weekly->value]['published'],
                'description' => "Total {$byPeriod[PostPeriod::Weekly->value]['total']} • {$byPeriod[PostPeriod::Weekly->value]['pending']} moderasi",
                'color' => 'amber',
                'icon' => 'calendar-days',
                'url' => PostResource::getUrl('index', [
                    'filters' => ['period' => ['value' => PostPeriod::Weekly->value]],
                ]),
            ],
            [
                'label' => 'Berita Bulanan',
                'value' => $byPeriod[PostPeriod::Monthly->value]['published'],
                'description' => "Total {$byPeriod[PostPeriod::Monthly->value]['total']} • {$byPeriod[PostPeriod::Monthly->value]['pending']} moderasi",
                'color' => 'emerald',
                'icon' => 'newspaper',
                'url' => PostResource::getUrl('index', [
                    'filters' => ['period' => ['value' => PostPeriod::Monthly->value]],
                ]),
            ],
            [
                'label' => $fourthCardLabel,
                'value' => $publishedInPeriod,
                'description' => "{$period['label']} • ".number_format($totalViews, 0, ',', '.').' pembaca',
                'color' => 'purple',
                'icon' => 'chart-bar',
                'url' => PostResource::getUrl('index', ['tab' => 'published']),
            ],
        ];

        return ['stats' => $stats];
    }
}
