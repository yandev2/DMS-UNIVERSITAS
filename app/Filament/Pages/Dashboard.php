<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Filament\Widgets\DashboardQuickActionsWidget;
use App\Filament\Widgets\DashboardWelcomeBannerWidget;
use App\Filament\Widgets\PendingLettersAlertWidget;
use App\Support\DashboardPeriodFilter;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Pages\Dashboard\Concerns\HasFiltersForm;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\Widget;
use Filament\Widgets\WidgetConfiguration;

class Dashboard extends BaseDashboard
{
    use HasFiltersForm;

    /**
     * Widget operasional atas yang tidak terpengaruh oleh filter rentang bulan-tahun.
     *
     * @var array<int, class-string<Widget>>
     */
    private const TOP_OPERATIONAL_WIDGETS = [
        DashboardWelcomeBannerWidget::class,
        PendingLettersAlertWidget::class,
        DashboardQuickActionsWidget::class,
    ];

    public function filtersForm(Schema $schema): Schema
    {
        $defaults = DashboardPeriodFilter::defaults();
        $monthOptions = DashboardPeriodFilter::monthOptions();
        $yearOptions = DashboardPeriodFilter::yearOptions();

        return $schema
            ->columns(1)
            ->components([
                Section::make('Filter Periode Statistik')
                    ->description(fn (): string => 'Menampilkan data naskah & publikasi untuk periode: '.DashboardPeriodFilter::resolve($this->filters)['label'])
                    ->icon(Heroicon::OutlinedCalendarDateRange)
                    ->extraAttributes(['class' => 'db-period-filter-section'])
                    ->compact()
                    ->collapsible()
                    ->columnSpanFull()
                    ->headerActions([
                        Action::make('currentMonth')
                            ->label('Bulan Ini')
                            ->size('xs')
                            ->color('gray')
                            ->action(function (): void {
                                $this->filters = DashboardPeriodFilter::defaults();
                                $this->getFiltersForm()->fill($this->filters);
                                $this->updatedFilters();
                                $this->forceRender();
                            }),

                        Action::make('currentYear')
                            ->label('Tahun Ini')
                            ->size('xs')
                            ->color('gray')
                            ->action(function (): void {
                                $currentYear = (int) now()->year;
                                $this->filters = [
                                    'start_month' => 1,
                                    'start_year' => $currentYear,
                                    'end_month' => 12,
                                    'end_year' => $currentYear,
                                ];
                                $this->getFiltersForm()->fill($this->filters);
                                $this->updatedFilters();
                                $this->forceRender();
                            }),
                    ])
                    ->columns([
                        'default' => 2,
                        'md' => 4,
                    ])
                    ->schema([
                        Select::make('start_month')
                            ->label('Dari Bulan')
                            ->options($monthOptions)
                            ->default($defaults['start_month'])
                            ->native(false)
                            ->selectablePlaceholder(false)
                            ->live(),

                        Select::make('start_year')
                            ->label('Dari Tahun')
                            ->options($yearOptions)
                            ->default($defaults['start_year'])
                            ->native(false)
                            ->selectablePlaceholder(false)
                            ->live(),

                        Select::make('end_month')
                            ->label('Sampai Bulan')
                            ->options($monthOptions)
                            ->default($defaults['end_month'])
                            ->native(false)
                            ->selectablePlaceholder(false)
                            ->live(),

                        Select::make('end_year')
                            ->label('Sampai Tahun')
                            ->options($yearOptions)
                            ->default($defaults['end_year'])
                            ->native(false)
                            ->selectablePlaceholder(false)
                            ->live(),
                    ]),
            ]);
    }

    public function updatedFilters(): void
    {
        if ($this->persistsFiltersInSession()) {
            session()->put(
                $this->getFiltersSessionKey(),
                $this->filters,
            );
        }

        $this->forceRender();
    }

    public function content(Schema $schema): Schema
    {
        $allWidgets = $this->getWidgets();
        $topWidgets = [];
        $analyticalWidgets = [];

        foreach ($allWidgets as $widget) {
            $widgetClass = $widget instanceof WidgetConfiguration ? $widget->widget : $widget;

            if (in_array($widgetClass, self::TOP_OPERATIONAL_WIDGETS, true)) {
                $topWidgets[] = $widget;
            } else {
                $analyticalWidgets[] = $widget;
            }
        }

        return $schema
            ->components([
                Grid::make($this->getColumns())
                    ->key('topWidgetsGrid')
                    ->schema(fn (): array => $this->getWidgetsSchemaComponents($topWidgets)),
                $this->getFiltersFormContentComponent(),
                Grid::make($this->getColumns())
                    ->key('analyticalWidgetsGrid')
                    ->schema(fn (): array => $this->getWidgetsSchemaComponents($analyticalWidgets)),
            ]);
    }
}
