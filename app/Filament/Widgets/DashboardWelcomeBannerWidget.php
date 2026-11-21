<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\UserShortcuts\UserShortcutResource;
use App\Models\LandingPageSetting;
use App\Models\User;
use App\Models\UserShortcut;
use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DashboardWelcomeBannerWidget extends Widget
{
    use HasWidgetShield;

    protected static bool $isLazy = false;

    protected static ?int $sort = -2;

    protected int|string|array $columnSpan = 'full';

    protected string $view = 'filament.widgets.dashboard-welcome-banner-widget';

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        /** @var User|null $user */
        $user = Auth::user();

        if ($user) {
            $user->loadMissing(['unit', 'roles']);
        }

        $hour = (int) now()->format('H');
        if ($hour >= 5 && $hour < 11) {
            $greetingTime = 'Selamat Pagi';
        } elseif ($hour >= 11 && $hour < 15) {
            $greetingTime = 'Selamat Siang';
        } elseif ($hour >= 15 && $hour < 18) {
            $greetingTime = 'Selamat Sore';
        } else {
            $greetingTime = 'Selamat Malam';
        }

        $rolesString = $user
            ? $user->roles->pluck('name')->map(fn (string $role): string => Str::headline($role))->implode(' • ')
            : 'Pengguna';

        if (empty($rolesString)) {
            $rolesString = 'Pengguna Sistem';
        }

        $positionTitle = $user?->position_title ?: ($user?->unit?->name ?: 'Civitas Akademika UNIVBI');

        $avatarUrl = null;
        if ($user?->avatar_url && Storage::disk('public')->exists($user->avatar_url)) {
            $avatarUrl = Storage::disk('public')->url($user->avatar_url);
        }

        $settings = LandingPageSetting::current();
        $logoUrl = $settings?->getLogoUrl();

        $shortcuts = $user
            ? $user->shortcuts()->limit(UserShortcut::MAX_PER_USER)->get()
            : collect();

        return [
            'user' => $user,
            'userName' => $user?->name ?? 'Pengguna',
            'userRoles' => $rolesString,
            'positionTitle' => $positionTitle,
            'unitName' => $user?->unit?->name,
            'unitCode' => $user?->unit?->code,
            'nidnNip' => $user?->nidn_nip,
            'greetingTime' => $greetingTime,
            'avatarUrl' => $avatarUrl,
            'logoUrl' => $logoUrl,
            'logoutUrl' => filament()->getLogoutUrl(),
            'initials' => $user ? Str::of($user->name)->explode(' ')->take(2)->map(fn ($p) => mb_substr($p, 0, 1))->implode('') : 'U',
            'shortcuts' => $shortcuts,
            'manageShortcutsUrl' => UserShortcutResource::getUrl('index'),
        ];
    }
}
