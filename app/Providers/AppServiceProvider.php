<?php

namespace App\Providers;

use App\Listeners\LogFailedLogin;
use App\Listeners\LogSuccessfulLogin;
use App\Listeners\LogSuccessfulLogout;
use App\Models\LandingPageSetting;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Tables\Filters\SelectFilter;
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
