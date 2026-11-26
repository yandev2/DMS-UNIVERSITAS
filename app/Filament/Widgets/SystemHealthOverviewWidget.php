<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Data\System\SystemHealthSnapshotData;
use App\Filament\Pages\SystemHealthPage;
use App\Services\System\SystemHealthService;
use Filament\Notifications\Notification;
use Filament\Widgets\Widget;
use Livewire\Attributes\On;

class SystemHealthOverviewWidget extends Widget
{
    protected static bool $isDiscovered = false;

    protected int|string|array $columnSpan = 'full';

    protected string $view = 'filament.widgets.system-health.overview-widget';

    public bool $autoPoll = false;

    public string $lastCheckedAt = '';

    public static function canView(): bool
    {
        return SystemHealthPage::canAccess();
    }

    public function mount(): void
    {
        $this->lastCheckedAt = now()->format('H:i:s');
    }

    public function toggleAutoPoll(): void
    {
        $this->autoPoll = ! $this->autoPoll;
    }

    public function probeNow(SystemHealthService $healthService): void
    {
        $healthService->freshCheck();
        $this->lastCheckedAt = now()->format('H:i:s');
        $this->dispatch('system-health-refreshed');

        Notification::make()
            ->title('Probe kesehatan sistem selesai')
            ->body('Metrik database, cache, antrean, dan penyimpanan telah diperbarui.')
            ->success()
            ->send();
    }

    #[On('system-health-refreshed')]
    public function refreshSnapshot(): void
    {
        app(SystemHealthService::class)->freshCheck();
        $this->lastCheckedAt = now()->format('H:i:s');
    }

    public function getSnapshot(): SystemHealthSnapshotData
    {
        return app(SystemHealthService::class)->check();
    }
}
