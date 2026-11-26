<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Data\System\SystemHealthSnapshotData;
use App\Filament\Pages\SystemHealthPage;
use App\Services\System\SystemHealthService;
use Filament\Widgets\Widget;
use Livewire\Attributes\On;

class SystemRuntimeConfigWidget extends Widget
{
    protected static bool $isDiscovered = false;

    protected int|string|array $columnSpan = 'full';

    protected string $view = 'filament.widgets.system-health.runtime-config-widget';

    public static function canView(): bool
    {
        return SystemHealthPage::canAccess();
    }

    #[On('system-health-refreshed')]
    public function refreshSnapshot(): void
    {
        // Trigger re-render with fresh snapshot.
    }

    public function getSnapshot(): SystemHealthSnapshotData
    {
        return app(SystemHealthService::class)->check();
    }

    /**
     * @return list<array{key: string, label: string, value: string, category: string, warning: bool}>
     */
    public function getConfigRows(): array
    {
        $snapshot = $this->getSnapshot();
        $isProductionLike = in_array(strtolower($snapshot->appEnvironment), ['production', 'staging'], true);

        return [
            [
                'key' => 'php_version',
                'label' => 'Versi PHP',
                'value' => 'PHP '.$snapshot->phpVersion,
                'category' => 'performance',
                'warning' => false,
            ],
            [
                'key' => 'laravel_version',
                'label' => 'Versi Laravel',
                'value' => 'v'.$snapshot->laravelVersion,
                'category' => 'performance',
                'warning' => false,
            ],
            [
                'key' => 'app_env',
                'label' => 'Environment Aplikasi',
                'value' => strtoupper($snapshot->appEnvironment),
                'category' => 'security',
                'warning' => false,
            ],
            [
                'key' => 'app_debug',
                'label' => 'Mode Debug (APP_DEBUG)',
                'value' => $snapshot->appDebug ? 'AKTIF (true)' : 'NONAKTIF (false)',
                'category' => 'security',
                'warning' => $snapshot->appDebug && $isProductionLike,
            ],
            [
                'key' => 'opcache',
                'label' => 'Zend OPcache',
                'value' => $snapshot->opcacheEnabled ? 'Aktif' : 'Tidak Aktif',
                'category' => 'performance',
                'warning' => ! $snapshot->opcacheEnabled && $isProductionLike,
            ],
            [
                'key' => 'memory_limit',
                'label' => 'Memory Limit PHP',
                'value' => $snapshot->memoryLimit,
                'category' => 'performance',
                'warning' => false,
            ],
            [
                'key' => 'max_execution_time',
                'label' => 'Max Execution Time',
                'value' => $snapshot->maxExecutionTime > 0 ? $snapshot->maxExecutionTime.' detik' : 'Tanpa Batas (0)',
                'category' => 'performance',
                'warning' => false,
            ],
            [
                'key' => 'app_timezone',
                'label' => 'Zona Waktu & Waktu UTC',
                'value' => $snapshot->appTimezone.' • '.$snapshot->serverUtcTime,
                'category' => 'security',
                'warning' => false,
            ],
        ];
    }
}
