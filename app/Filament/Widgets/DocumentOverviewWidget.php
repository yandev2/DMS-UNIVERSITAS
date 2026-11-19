<?php

namespace App\Filament\Widgets;

use App\Enums\DocumentStatus;
use App\Filament\Resources\Documents\DocumentResource;
use App\Models\Document;
use App\Models\Unit;
use App\Models\User;
use App\Support\DashboardPeriodFilter;
use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class DocumentOverviewWidget extends Widget
{
    use HasWidgetShield;
    use InteractsWithPageFilters;

    protected static bool $isLazy = false;

    protected static ?int $sort = 1;

    protected ?string $pollingInterval = null;

    protected int|string|array $columnSpan = 'full';

    protected string $view = 'filament.widgets.document-overview-widget';

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
            ];
        }

        $docVersion = Document::getStatsCacheVersion();
        $scopeKey = Document::statsCacheScopeKeyFor($user);
        $cacheKey = "dashboard_doc_overview:v{$docVersion}:{$period['cache_key']}:{$scopeKey}";

        /** @var array{pending: int, approved: int, borang: int} $counts */
        $counts = Cache::remember($cacheKey, now()->addSeconds(120), static function () use ($user, $period): array {
            // 1. Dokumen Menunggu Persetujuan / Tindak Lanjut bersifat antrean live saat ini
            $pendingCount = PendingApprovalsWidget::getPendingQueryForUser($user, withEagerLoads: false)
                ->reorder()
                ->count();

            // 2 & 3. Hitungan Naskah Sah & Bukti Borang pada rentang periode issued_date aktif dalam 1 pass SQL
            $accessibleUnitIds = $user->accessibleUnitIds();

            $borangCondition = DB::connection()->getDriverName() === 'pgsql'
                ? 'documents.is_borang IS TRUE'
                : 'documents.is_borang = 1';

            $borangSelect = "COALESCE(SUM(CASE WHEN {$borangCondition} THEN 1 ELSE 0 END), 0) as borang_count";
            $borangBindings = [];

            if ($accessibleUnitIds !== null) {
                if ($accessibleUnitIds === []) {
                    $borangSelect = '0 as borang_count';
                } else {
                    $placeholders = implode(', ', array_fill(0, count($accessibleUnitIds), '?'));
                    $borangSelect = "COALESCE(SUM(CASE WHEN {$borangCondition} AND documents.unit_id IN ({$placeholders}) THEN 1 ELSE 0 END), 0) as borang_count";
                    $borangBindings = $accessibleUnitIds;
                }
            }

            $approvedStatuses = [
                DocumentStatus::Approved->value,
                DocumentStatus::Archived->value,
                DocumentStatus::Expired->value,
            ];

            $aggregateQuery = Document::query()->accessibleFor($user);
            DashboardPeriodFilter::applyToDocumentIssuedDate($aggregateQuery, $period);

            $aggregateRow = $aggregateQuery
                ->toBase()
                ->selectRaw(
                    'COALESCE(SUM(CASE WHEN documents.status IN (?, ?, ?) THEN 1 ELSE 0 END), 0) as approved_count',
                    $approvedStatuses
                )
                ->selectRaw($borangSelect, $borangBindings)
                ->first();

            return [
                'pending' => (int) $pendingCount,
                'approved' => (int) ($aggregateRow->approved_count ?? 0),
                'borang' => (int) ($aggregateRow->borang_count ?? 0),
            ];
        });

        $pendingCount = (int) ($counts['pending'] ?? 0);
        $approvedCount = (int) ($counts['approved'] ?? 0);
        $borangCount = (int) ($counts['borang'] ?? 0);

        // 4. Portal Asesor Aktif (ter-cache bersama dengan Quick Actions & memoized per-request)
        $assessorCount = Unit::countActiveAssessorUnitsForUser($user);

        $issuedPeriodFilter = [
            'issued_from' => $period['start_date']->toDateString(),
            'issued_until' => $period['end_date']->toDateString(),
        ];

        return [
            'stats' => [
                [
                    'key' => 'pending',
                    'label' => 'Menunggu Persetujuan',
                    'value' => $pendingCount,
                    'description' => $pendingCount > 0 ? 'Naskah butuh tindakan verifikasi' : 'Semua naskah tuntas diproses',
                    'icon' => 'clock',
                    'color' => 'amber',
                    'url' => DocumentResource::getUrl('index', [
                        'tab' => 'all',
                        'filters' => [
                            'status' => [
                                'values' => [
                                    DocumentStatus::Pending->value,
                                    DocumentStatus::Revision->value,
                                ],
                            ],
                        ],
                    ]),
                    'is_alert' => $pendingCount > 0,
                ],
                [
                    'key' => 'approved',
                    'label' => 'Naskah Sah & Resmi',
                    'value' => $approvedCount,
                    'description' => "Tervalidasi • {$period['label']}",
                    'icon' => 'check-badge',
                    'color' => 'emerald',
                    'url' => DocumentResource::getUrl('index', [
                        'tab' => 'all',
                        'filters' => [
                            'status' => [
                                'values' => [
                                    DocumentStatus::Approved->value,
                                    DocumentStatus::Archived->value,
                                    DocumentStatus::Expired->value,
                                ],
                            ],
                            'issued_period' => $issuedPeriodFilter,
                        ],
                    ]),
                    'is_alert' => false,
                ],
                [
                    'key' => 'borang',
                    'label' => 'Bukti Borang Akreditasi',
                    'value' => $borangCount,
                    'description' => "Bukti borang • {$period['label']}",
                    'icon' => 'academic-cap',
                    'color' => 'blue',
                    'url' => DocumentResource::getUrl('index', [
                        'tab' => 'all',
                        'filters' => [
                            'is_borang' => [
                                'value' => '1',
                            ],
                            'issued_period' => $issuedPeriodFilter,
                        ],
                    ]),
                    'is_alert' => false,
                ],
                [
                    'key' => 'assessor',
                    'label' => 'Portal Asesor Terbuka',
                    'value' => $assessorCount,
                    'description' => $assessorCount > 0 ? "{$assessorCount} unit aktif dinilai asesor" : 'Tidak ada sesi asesmen terbuka',
                    'icon' => $assessorCount > 0 ? 'lock-open' : 'lock-closed',
                    'color' => 'purple',
                    'url' => route('filament.admin.pages.accreditation-data-bank'),
                    'is_alert' => $assessorCount > 0,
                ],
            ],
        ];
    }
}
