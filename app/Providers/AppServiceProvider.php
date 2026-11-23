<?php

namespace App\Providers;

use App\Listeners\LogFailedLogin;
use App\Listeners\LogSuccessfulLogin;
use App\Listeners\LogSuccessfulLogout;
use App\Models\LandingPageSetting;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Auth\Events\Failed as AuthFailed;
use Illuminate\Auth\Events\Login as AuthLogin;
use Illuminate\Auth\Events\Logout as AuthLogout;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::before(static function ($user, string $ability): ?bool {
            return method_exists($user, 'hasRole') && $user->hasRole('super_admin')
                ? true
                : null;
        });

        DatePicker::configureUsing(function (DatePicker $datePicker): void {
            $datePicker->native(false);
        });

        DateTimePicker::configureUsing(function (DateTimePicker $dateTimePicker): void {
            $dateTimePicker->native(false);
        });

        SelectFilter::configureUsing(function (SelectFilter $filter): void {
            $filter->native(false);
        });

        CreateAction::configureUsing(function (CreateAction $action): void {
            $action
                ->icon(Heroicon::OutlinedPlus)
                ->extraAttributes(['class' => 'ui-header-action-create'], merge: true);
        });

        Table::configureUsing(function (Table $table): void {
            $table
                ->filtersApplyAction(fn (Action $action): Action => $action
                    ->label('Terapkan Filter')
                    ->icon(Heroicon::OutlinedFunnel))
                ->filtersResetAction(fn (Action $action): Action => $action
                    ->label('Reset')
                    ->color('gray')
                    ->icon(Heroicon::OutlinedArrowPath))
                ->filtersTriggerAction(fn (Action $action): Action => $action
                    ->icon(Heroicon::OutlinedAdjustmentsHorizontal)
                    ->modalHeading('Filter')
                    ->modalDescription('Gunakan filter untuk mempersempit data yang ditampilkan')
                    ->modalIcon(Heroicon::OutlinedFunnel)
                    ->modalIconColor('primary')
                    ->modalFooterActions(function (Action $action) use ($table): array {
                        $actions = [
                            $table->getFiltersResetAction(),
                        ];

                        if ($cancelAction = $action->getModalCancelAction()) {
                            $actions[] = $cancelAction;
                        }

                        $actions[] = $table->getFiltersApplyAction()->close();

                        return $actions;
                    }))
                ->columnManagerTriggerAction(fn (Action $action): Action => $action
                    ->icon(Heroicon::OutlinedViewColumns));
        });

        View::composer('public.*', function ($view): void {
            try {
                $view->with('siteSettings', LandingPageSetting::current());
            } catch (\Throwable) {
                // Fallback aman jika database belum siap/termigrasi
            }
        });

        Event::listen(AuthLogin::class, LogSuccessfulLogin::class);
        Event::listen(AuthLogout::class, LogSuccessfulLogout::class);
        Event::listen(AuthFailed::class, LogFailedLogin::class);
    }
}
