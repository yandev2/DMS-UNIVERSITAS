<?php

namespace App\Providers\Filament;

use App\Filament\Pages\Auth\EditProfile;
use App\Filament\Pages\Auth\Login;
use App\Filament\Pages\Dashboard;
use App\Filament\Resources\UserShortcuts\UserShortcutResource;
use App\Filament\Widgets\AnnualPublishedDocumentChartWidget;
use App\Filament\Widgets\DashboardQuickActionsWidget;
use App\Filament\Widgets\DashboardWelcomeBannerWidget;
use App\Filament\Widgets\DocumentLifecycleStatsWidget;
use App\Filament\Widgets\DocumentOverviewWidget;
use App\Filament\Widgets\PendingApprovalsWidget;
use App\Filament\Widgets\PendingLettersAlertWidget;
use App\Filament\Widgets\PostPeriodOverviewWidget;
use App\Models\LandingPageSetting;
use BezhanSalleh\FilamentShield\FilamentShieldPlugin;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\MenuItem;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\View\PanelsRenderHook;
use Illuminate\Contracts\View\View;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Blade;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Zvizvi\FilamentNotificationsTabs\FilamentNotificationsTabsPlugin;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->brandName('Archiver-Sistem')
            ->brandLogo(fn () => view('filament.admin.logo'))
            ->brandLogoHeight('2rem')
            ->favicon(function (): string {
                try {
                    $cmsFavicon = LandingPageSetting::current()?->getFaviconUrl();
                    if (filled($cmsFavicon)) {
                        return $cmsFavicon;
                    }
                } catch (\Throwable) {
                    // Abaikan jika tabel pengaturan CMS belum siap (mis. saat migrasi awal CLI)
                }

                return asset('favicon.ico');
            })
            ->sidebarCollapsibleOnDesktop()
            ->collapsedSidebarWidth('4.25rem')
            ->viteTheme('resources/css/filament/admin/theme.css')
            ->navigationGroups([
                'E-Arsip & Dokumen',
                'Persuratan',
                'Borang Akreditasi',
                'Publikasi & Informasi Publik',
                'Master Data',
                'Manajemen Akses & Pengguna',
                'Keamanan & Audit Trail',
            ])
            ->databaseNotifications()
            ->login(Login::class)
            ->profile(EditProfile::class, isSimple: false)
            ->userMenuItems([
                'user_shortcuts' => MenuItem::make()
                    ->label('Pintasan Web Saya')
                    ->url(fn (): string => UserShortcutResource::getUrl('index'))
                    ->icon('heroicon-o-link'),
            ])
            ->colors([
                'primary' => Color::Blue,
                'blue' => Color::Blue,
                'red' => Color::Red,
                'green' => Color::Green,
                'amber' => Color::Amber,
                'purple' => Color::Purple,
                'orange' => Color::Orange,
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([
                DashboardWelcomeBannerWidget::class,
                PendingLettersAlertWidget::class,
                DashboardQuickActionsWidget::class,
                DocumentOverviewWidget::class,
                DocumentLifecycleStatsWidget::class,
                AnnualPublishedDocumentChartWidget::class,
                PendingApprovalsWidget::class,
                PostPeriodOverviewWidget::class,
            ])
            ->plugins([
                FilamentShieldPlugin::make()
                    ->navigationGroup('Manajemen Akses & Pengguna')
                    ->navigationSort(2),
                FilamentNotificationsTabsPlugin::make(),
            ])
            ->renderHook(
                PanelsRenderHook::HEAD_END,
                function (): string {
                    $vTimeline = @filemtime(public_path('css/document-timeline.css')) ?: time();
                    $vBorang = @filemtime(public_path('css/borang-bank.css')) ?: time();
                    $vDashboard = @filemtime(public_path('css/dashboard-custom.css')) ?: time();
                    $vModals = @filemtime(public_path('css/modals-custom.css')) ?: time();

                    $html = '<link rel="stylesheet" href="/css/document-timeline.css?v='.$vTimeline.'">'
                          .'<link rel="stylesheet" href="/css/borang-bank.css?v='.$vBorang.'">'
                          .'<link rel="stylesheet" href="/css/dashboard-custom.css?v='.$vDashboard.'">'
                          .'<link rel="stylesheet" href="/css/modals-custom.css?v='.$vModals.'">';

                    if (request()->routeIs('filament.*.auth.login')) {
                        $html .= '<script src="https://cdn.tailwindcss.com"></script><script>tailwind.config = { darkMode: "class" };</script>';
                        $html .= Blade::render('@vite([\'resources/css/app.css\'])');
                    } elseif (is_file(public_path('build/manifest.json')) || is_file(public_path('hot'))) {
                        $html .= Blade::render('@vite([\'resources/js/word-preview.js\'])');
                    }

                    return $html;
                },
            )
            ->renderHook(
                PanelsRenderHook::GLOBAL_SEARCH_BEFORE,
                fn (): View => view('filament.components.topbar-shortcut-button'),
            )
            ->renderHook(
                PanelsRenderHook::FOOTER,
                fn (): View => view('filament.components.panel-footer'),
            )
            ->renderHook(
                PanelsRenderHook::BODY_END,
                fn (): View => view('filament.components.upload-error-handler'),
            )
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
