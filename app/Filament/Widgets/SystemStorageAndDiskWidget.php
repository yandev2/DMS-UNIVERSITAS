<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Data\System\SystemHealthSnapshotData;
use App\Filament\Pages\SystemHealthPage;
use App\Services\System\SystemHealthService;
use Filament\Notifications\Notification;
use Filament\Widgets\Widget;
use Livewire\Attributes\On;
use Throwable;

class SystemStorageAndDiskWidget extends Widget
{
    protected static bool $isDiscovered = false;

    protected int|string|array $columnSpan = 'full';

    protected string $view = 'filament.widgets.system-health.storage-disk-widget';

    public static function canView(): bool
    {
        return SystemHealthPage::canAccess();
    }

    #[On('system-health-refreshed')]
    public function refreshSnapshot(): void
    {
        // Trigger re-render with fresh snapshot.
    }

    public function testStorageWrite(): void
    {
        $probeFile = storage_path('app/.health-write-probe-'.bin2hex(random_bytes(4)).'.tmp');
        $payload = 'health-probe-'.now()->toIso8601String();
        $succeeded = false;

        try {
            $written = @file_put_contents($probeFile, $payload);
            $readBack = $written !== false ? @file_get_contents($probeFile) : false;
            $succeeded = $written !== false && $readBack === $payload;
        } catch (Throwable) {
            $succeeded = false;
        } finally {
            if (is_file($probeFile)) {
                @unlink($probeFile);
            }
        }

        app(SystemHealthService::class)->freshCheck();
        $this->dispatch('system-health-refreshed');

        if ($succeeded) {
            Notification::make()
                ->title('Uji tulis storage berhasil')
                ->body('Direktori storage/app dapat ditulis, dibaca ulang, dan dibersihkan secara normal.')
                ->success()
                ->send();

            return;
        }

        Notification::make()
            ->title('Uji tulis storage gagal')
            ->body('Direktori storage/app tidak dapat ditulis. Periksa izin direktori server.')
            ->danger()
            ->send();
    }

    public function getSnapshot(): SystemHealthSnapshotData
    {
        return app(SystemHealthService::class)->check();
    }
}
