<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Filament\Widgets\SystemExtensionsWidget;
use App\Filament\Widgets\SystemHealthOverviewWidget;
use App\Filament\Widgets\SystemRuntimeConfigWidget;
use App\Filament\Widgets\SystemStorageAndDiskWidget;
use App\Services\System\SystemHealthService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Livewire;
use Filament\Schemas\Schema;
use UnitEnum;

class SystemHealthPage extends Page
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-cpu-chip';

    protected static string|UnitEnum|null $navigationGroup = 'Keamanan & Audit Trail';

    protected static ?string $navigationLabel = 'Kesehatan Sistem';

    protected static ?string $title = 'Kesehatan Sistem & Informasi Server';

    protected static ?string $slug = 'system-health';

    protected static ?int $navigationSort = 3;

    public static function canAccess(): bool
    {
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        return $user->hasRole('super_admin');
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);
    }

    /**
     * @return array<int, Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('refresh_health')
                ->label('Periksa Ulang Sekarang')
                ->icon('heroicon-o-arrow-path')
                ->color('primary')
                ->action(function (SystemHealthService $healthService): void {
                    $healthService->freshCheck();
                    $this->dispatch('system-health-refreshed');

                    Notification::make()
                        ->title('Pemeriksaan kesehatan sistem selesai')
                        ->body('Seluruh metrik server, cache, database, dan penyimpanan telah diperbarui.')
                        ->success()
                        ->send();
                }),
        ];
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                Group::make([
                    Livewire::make(SystemHealthOverviewWidget::class)
                        ->key('system-health-overview')
                        ->liberatedFromContainerGrid(),
                    Group::make([
                        Livewire::make(SystemStorageAndDiskWidget::class)
                            ->key('system-health-storage')
                            ->liberatedFromContainerGrid()
                            ->extraAttributes(['class' => 'pl-bento-cell pl-bento-equal-cell']),
                        Livewire::make(SystemRuntimeConfigWidget::class)
                            ->key('system-health-runtime')
                            ->liberatedFromContainerGrid()
                            ->extraAttributes(['class' => 'pl-bento-cell pl-bento-equal-cell']),
                    ])
                        ->extraAttributes(['class' => 'pl-bento-row']),
                    Livewire::make(SystemExtensionsWidget::class)
                        ->key('system-health-extensions')
                        ->liberatedFromContainerGrid(),
                ])
                    ->extraAttributes(['class' => 'pl-dashboard-bento']),
            ]);
    }
}
