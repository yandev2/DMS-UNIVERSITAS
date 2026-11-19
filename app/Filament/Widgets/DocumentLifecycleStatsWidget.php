<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Enums\DocumentStatus;
use App\Filament\Resources\Documents\DocumentResource;
use App\Models\Document;
use App\Models\User;
use App\Support\DashboardPeriodFilter;
use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class DocumentLifecycleStatsWidget extends Widget
{
    use HasWidgetShield;
    use InteractsWithPageFilters;

    protected static bool $isLazy = false;

    protected static ?int $sort = 2;

    protected ?string $pollingInterval = null;

    protected int|string|array $columnSpan = 'full';

    protected string $view = 'filament.widgets.document-lifecycle-stats-widget';

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        /** @var User|null $user */
        $user = Auth::user();
        $period = DashboardPeriodFilter::resolve($this->pageFilters);

        if (! $user) {
            return [
                'stats' => [],
                'periodLabel' => $period['label'],
            ];
        }

        $docVersion = Document::getStatsCacheVersion();
        $scopeKey = Document::statsCacheScopeKeyFor($user);
        $cacheKey = "dashboard_doc_lifecycle:v{$docVersion}:{$period['cache_key']}:{$scopeKey}";

        /** @var array{has_expiry: int, expired_sub: int, permanent: int, archived: int, rejected: int} $counts */
        $counts = Cache::remember($cacheKey, now()->addSeconds(120), static function () use ($user, $period): array {
            $isPgsql = DB::connection()->getDriverName() === 'pgsql';
            $hasExpiryTrue = $isPgsql ? 'documents.has_expiry IS TRUE' : 'documents.has_expiry = 1';
            $hasExpiryFalse = $isPgsql ? 'documents.has_expiry IS FALSE' : 'documents.has_expiry = 0';

            $query = Document::query()->accessibleFor($user);
            DashboardPeriodFilter::applyToDocumentIssuedDate($query, $period);

            $row = $query
                ->toBase()
                ->selectRaw("COALESCE(SUM(CASE WHEN {$hasExpiryTrue} THEN 1 ELSE 0 END), 0) as has_expiry_count")
                ->selectRaw(
                    "COALESCE(SUM(CASE WHEN {$hasExpiryTrue} AND documents.status = ? THEN 1 ELSE 0 END), 0) as expired_sub_count",
                    [DocumentStatus::Expired->value]
                )
                ->selectRaw("COALESCE(SUM(CASE WHEN {$hasExpiryFalse} THEN 1 ELSE 0 END), 0) as permanent_count")
                ->selectRaw(
                    'COALESCE(SUM(CASE WHEN documents.status IN (?, ?) THEN 1 ELSE 0 END), 0) as archived_count',
                    [DocumentStatus::Archived->value, DocumentStatus::Expired->value]
                )
                ->selectRaw(
                    'COALESCE(SUM(CASE WHEN documents.status = ? THEN 1 ELSE 0 END), 0) as rejected_count',
                    [DocumentStatus::Rejected->value]
                )
                ->first();

            return [
                'has_expiry' => (int) ($row->has_expiry_count ?? 0),
                'expired_sub' => (int) ($row->expired_sub_count ?? 0),
                'permanent' => (int) ($row->permanent_count ?? 0),
                'archived' => (int) ($row->archived_count ?? 0),
                'rejected' => (int) ($row->rejected_count ?? 0),
            ];
        });

        $issuedPeriodFilter = [
            'issued_from' => $period['start_date']->toDateString(),
            'issued_until' => $period['end_date']->toDateString(),
        ];

        $hasExpiryCount = (int) ($counts['has_expiry'] ?? 0);
        $expiredSubCount = (int) ($counts['expired_sub'] ?? 0);
        $permanentCount = (int) ($counts['permanent'] ?? 0);
        $archivedCount = (int) ($counts['archived'] ?? 0);
        $rejectedCount = (int) ($counts['rejected'] ?? 0);

        return [
            'periodLabel' => $period['label'],
            'stats' => [
                [
                    'key' => 'has_expiry',
                    'label' => 'Bermasa Berlaku',
                    'value' => $hasExpiryCount,
                    'description' => $expiredSubCount > 0
                        ? "{$expiredSubCount} kedaluwarsa • {$period['label']}"
                        : "Ada batas berlaku • {$period['label']}",
                    'icon' => 'calendar-clock',
                    'color' => 'blue',
                    'url' => DocumentResource::getUrl('index', [
                        'tab' => 'all',
                        'filters' => [
                            'has_expiry' => ['value' => '1'],
                            'issued_period' => $issuedPeriodFilter,
                        ],
                    ]),
                ],
                [
                    'key' => 'permanent',
                    'label' => 'Dokumen Permanen',
                    'value' => $permanentCount,
                    'description' => "Berlaku selamanya • {$period['label']}",
                    'icon' => 'shield-check',
                    'color' => 'emerald',
                    'url' => DocumentResource::getUrl('index', [
                        'tab' => 'all',
                        'filters' => [
                            'has_expiry' => ['value' => '0'],
                            'issued_period' => $issuedPeriodFilter,
                        ],
                    ]),
                ],
                [
                    'key' => 'archived',
                    'label' => 'Dokumen Arsip',
                    'value' => $archivedCount,
                    'description' => "Arsip & kedaluwarsa • {$period['label']}",
                    'icon' => 'archive-box',
                    'color' => 'slate',
                    'url' => DocumentResource::getUrl('index', [
                        'tab' => 'archived_expired',
                        'filters' => [
                            'issued_period' => $issuedPeriodFilter,
                        ],
                    ]),
                ],
                [
                    'key' => 'rejected',
                    'label' => 'Dokumen Ditolak',
                    'value' => $rejectedCount,
                    'description' => $rejectedCount > 0
                        ? "Perlu perhatian • {$period['label']}"
                        : "Nihil penolakan • {$period['label']}",
                    'icon' => 'x-circle',
                    'color' => 'rose',
                    'url' => DocumentResource::getUrl('index', [
                        'tab' => 'all',
                        'filters' => [
                            'status' => [
                                'values' => [DocumentStatus::Rejected->value],
                            ],
                            'issued_period' => $issuedPeriodFilter,
                        ],
                    ]),
                ],
            ],
        ];
    }
}
