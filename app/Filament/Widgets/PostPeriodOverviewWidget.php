<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Enums\PostPeriod;
use App\Enums\PostStatus;
use App\Filament\Resources\Posts\PostResource;
use App\Models\Post;
use App\Models\User;
use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

class PostPeriodOverviewWidget extends Widget
{
    use HasWidgetShield;

    protected static bool $isLazy = false;

    protected static ?int $sort = 4;

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

        $monthStart = now()->startOfMonth()->toDateTimeString();
        $isBypass = $user->can('post.moderate_humas') || $user->can('document.bypass_unit_scope');
        $scopeKey = $isBypass
            ? 'all'
            : implode(':', [
                'u'.(int) ($user->unit_id ?? 0),
                'a'.(int) $user->id,
                $user->hasRole('dekan') ? 'dekan' : 'std',
            ]);

        $version = Post::getStatsCacheVersion();
        $cacheKey = "post_period_overview_rows:v{$version}:{$scopeKey}:".now()->format('Y-m');

        /** @var array<int, array{period: string, status: string, total: int, total_views: int, month_count: int}> $aggregates */
        $aggregates = Cache::remember($cacheKey, now()->addSeconds(120), static function () use ($user, $monthStart): array {
            return Post::query()
                ->accessibleFor($user)
                ->toBase()
                ->selectRaw('period, status, COUNT(*) as total, COALESCE(SUM(views_count), 0) as total_views')
                ->selectRaw('SUM(CASE WHEN published_at >= ? THEN 1 ELSE 0 END) as month_count', [$monthStart])
                ->groupBy('period', 'status')
                ->get()
                ->map(static fn (object $row): array => [
                    'period' => (string) ($row->period ?? PostPeriod::Daily->value),
                    'status' => (string) ($row->status ?? ''),
                    'total' => (int) ($row->total ?? 0),
                    'total_views' => (int) ($row->total_views ?? 0),
                    'month_count' => (int) ($row->month_count ?? 0),
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

        $publishedThisMonth = 0;
        $totalViews = 0;
        $totalPublishedAll = 0;

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
                $totalPublishedAll += $count;
                $publishedThisMonth += (int) ($row['month_count'] ?? 0);
            } elseif ($statusKey === PostStatus::PendingReview->value) {
                $byPeriod[$periodKey]['pending'] += $count;
            }
        }

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
                'label' => 'Tayang Bulan Ini',
                'value' => $publishedThisMonth,
                'description' => "{$totalPublishedAll} tayang • ".number_format($totalViews, 0, ',', '.').' pembaca',
                'color' => 'purple',
                'icon' => 'chart-bar',
                'url' => PostResource::getUrl('index', ['tab' => 'published']),
            ],
        ];

        return ['stats' => $stats];
    }
}
