<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Enums\OutgoingLetterStatus;
use App\Filament\Resources\IncomingLetters\IncomingLetterResource;
use App\Filament\Resources\OutgoingLetters\OutgoingLetterResource;
use App\Models\IncomingLetter;
use App\Models\OutgoingLetter;
use App\Models\User;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

class PendingLettersAlertWidget extends Widget
{
    protected static bool $isLazy = false;

    protected static ?int $sort = -1;

    protected int|string|array $columnSpan = 'full';

    protected string $view = 'filament.widgets.pending-letters-alert-widget';

    public static function canView(): bool
    {
        /** @var User|null $user */
        $user = Auth::user();

        if (! $user || ! $user->can('view:pending_letters_alert_widget')) {
            return false;
        }

        $data = static::getPendingLetterCounts($user);

        return $data['incomingCount'] > 0 || $data['outgoingHumasCount'] > 0;
    }

    /**
     * @return array{incomingCount: int, outgoingHumasCount: int}
     */
    public static function getPendingLetterCounts(User $user): array
    {
        $canHumas = $user->can('letter.manage_humas');
        $version = IncomingLetter::getStatsCacheVersion();
        $scopeKey = "v{$version}:u{$user->id}:h".($canHumas ? '1' : '0');

        $attributes = request()->attributes;
        /** @var array<string, array{incomingCount: int, outgoingHumasCount: int}> $memo */
        $memo = $attributes->get('_pending_letter_counts', []);
        if (array_key_exists($scopeKey, $memo)) {
            return $memo[$scopeKey];
        }

        $cacheKey = "dashboard_pending_letters:{$scopeKey}";

        /** @var array{incomingCount: int, outgoingHumasCount: int} $counts */
        $counts = Cache::remember($cacheKey, now()->addSeconds(120), static function () use ($user, $canHumas): array {
            $incomingCount = IncomingLetter::query()
                ->pendingTurnForUser($user)
                ->count();

            $outgoingHumasCount = 0;
            if ($canHumas) {
                $outgoingHumasCount = OutgoingLetter::query()
                    ->where('status', OutgoingLetterStatus::PendingHumas)
                    ->count();
            }

            return [
                'incomingCount' => (int) $incomingCount,
                'outgoingHumasCount' => (int) $outgoingHumasCount,
            ];
        });

        $memo[$scopeKey] = $counts;
        $attributes->set('_pending_letter_counts', $memo);

        return $counts;
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        /** @var User $user */
        $user = Auth::user();
        $counts = static::getPendingLetterCounts($user);

        $incomingCount = $counts['incomingCount'];
        $incomingLetters = $incomingCount > 0
            ? IncomingLetter::query()
                ->pendingTurnForUser($user)
                ->latest('created_at')
                ->limit(1)
                ->get()
            : collect();

        $outgoingHumasCount = $counts['outgoingHumasCount'];
        $outgoingHumasLetters = $outgoingHumasCount > 0
            ? OutgoingLetter::query()
                ->where('status', OutgoingLetterStatus::PendingHumas)
                ->with(['unit'])
                ->latest('created_at')
                ->limit(1)
                ->get()
            : collect();

        return [
            'user' => $user,
            'incomingCount' => $incomingCount,
            'incomingLetters' => $incomingLetters,
            'incomingUrl' => IncomingLetterResource::getUrl('index', ['tab' => 'my_turn']),
            'outgoingHumasCount' => $outgoingHumasCount,
            'outgoingHumasLetters' => $outgoingHumasLetters,
            'outgoingHumasUrl' => OutgoingLetterResource::getUrl('index', ['tab' => 'pending_humas']),
        ];
    }
}
