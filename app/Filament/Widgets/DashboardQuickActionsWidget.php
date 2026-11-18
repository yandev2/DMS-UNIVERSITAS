<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Filament\Pages\AccreditationDataBank;
use App\Filament\Resources\AuditLogs\AuditLogResource;
use App\Filament\Resources\Documents\DocumentResource;
use App\Filament\Resources\Posts\PostResource;
use App\Models\ActivityLog;
use App\Models\Document;
use App\Models\Post;
use App\Models\Unit;
use App\Models\User;
use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\Auth;

class DashboardQuickActionsWidget extends Widget
{
    use HasWidgetShield;

    protected static bool $isLazy = false;

    protected static ?int $sort = 0;

    protected int|string|array $columnSpan = 'full';

    protected string $view = 'filament.widgets.dashboard-quick-actions-widget';

    public static function canView(): bool
    {
        /** @var User|null $user */
        $user = Auth::user();

        if (! $user) {
            return false;
        }

        $permission = static::getWidgetPermission();
        if ($permission && ! $user->can($permission)) {
            return false;
        }

        return ! empty(static::getAvailableActionsForUser($user));
    }

    /**
     * @return array<string, mixed>
     */
    public function getViewData(): array
    {
        /** @var User|null $user */
        $user = Auth::user();

        if (! $user) {
            return [
                'actions' => [],
            ];
        }

        return [
            'actions' => static::getAvailableActionsForUser($user),
        ];
    }

    /**
     * @return array<int, array{
     *     id: string,
     *     title: string,
     *     short_title: string,
     *     description: string,
     *     url: string,
     *     icon: string,
     *     theme: string,
     *     badge: ?string,
     *     badge_tone: string,
     *     open_in_new_tab: bool,
     *     permission_hint: string
     * }>
     */
    public static function getAvailableActionsForUser(User $user): array
    {
        $unitVersion = Unit::getAssessorCacheVersion();
        $scopeKey = Document::statsCacheScopeKeyFor($user).":uv{$unitVersion}:".now()->toDateString();

        $attributes = request()->attributes;
        /** @var array<string, array<int, array<string, mixed>>> $memo */
        $memo = $attributes->get('_dashboard_quick_actions', []);
        if (array_key_exists($scopeKey, $memo)) {
            return $memo[$scopeKey];
        }

        $actions = [];

        // 1. Registrasi Naskah Dinas Baru
        if ($user->hasRole('super_admin') || $user->can('create', Document::class)) {
            $actions[] = [
                'id' => 'create_document',
                'title' => 'Registrasi Naskah Baru',
                'short_title' => 'Registrasi Naskah',
                'description' => 'Draf surat keluar, SK, SE, surat tugas, atau MoU',
                'url' => DocumentResource::getUrl('create'),
                'icon' => 'heroicon-o-document-plus',
                'theme' => 'blue',
                'badge' => null,
                'badge_tone' => 'neutral',
                'open_in_new_tab' => false,
                'permission_hint' => 'create:document',
            ];
        }

        // 2. Akses Portal Asesor Eksternal
        $canManageAssessor = $user->hasRole('super_admin')
            || $user->can('document.bypass_unit_scope')
            || $user->hasAnyRole(['lpm', 'dekan', 'kepala_biro']);

        if ($canManageAssessor) {
            $openAssessorCount = Unit::countActiveAssessorUnitsForUser($user);

            $actions[] = [
                'id' => 'assessor_portal',
                'title' => 'Akses Portal Asesor',
                'short_title' => 'Akses Asesor',
                'description' => 'Buka & kelola jadwal asesmen serta PIN unit',
                'url' => AccreditationDataBank::getUrl(),
                'icon' => 'heroicon-o-key',
                'theme' => 'purple',
                'badge' => $openAssessorCount > 0 ? "{$openAssessorCount} Unit" : null,
                'badge_tone' => $openAssessorCount > 0 ? 'success' : 'neutral',
                'open_in_new_tab' => false,
                'permission_hint' => 'unit.manage_assessor',
            ];
        }

        // 3. Repositori & Cari Naskah
        if ($user->hasRole('super_admin') || $user->can('viewAny', Document::class)) {
            $actions[] = [
                'id' => 'search_documents',
                'title' => 'Repositori & Cari Naskah',
                'short_title' => 'Cari Naskah',
                'description' => 'Pencarian arsip digital, cek QR & status naskah',
                'url' => DocumentResource::getUrl('index'),
                'icon' => 'heroicon-o-magnifying-glass',
                'theme' => 'cyan',
                'badge' => null,
                'badge_tone' => 'neutral',
                'open_in_new_tab' => false,
                'permission_hint' => 'view_any:document',
            ];
        }

        // 4. Publikasi Warta Kampus
        if ($user->hasRole('super_admin') || $user->can('create', Post::class)) {
            $actions[] = [
                'id' => 'create_post',
                'title' => 'Publikasi Warta Kampus',
                'short_title' => 'Warta Kampus',
                'description' => 'Tulis warta, pengumuman, atau berita kegiatan resmi',
                'url' => PostResource::getUrl('create'),
                'icon' => 'heroicon-o-newspaper',
                'theme' => 'rose',
                'badge' => null,
                'badge_tone' => 'neutral',
                'open_in_new_tab' => false,
                'permission_hint' => 'create:post',
            ];
        }

        // 5. Log Audit & Keamanan Sistem
        if ($user->hasRole('super_admin') || $user->can('viewAny', ActivityLog::class)) {
            $actions[] = [
                'id' => 'audit_logs',
                'title' => 'Log Audit & Keamanan',
                'short_title' => 'Log Audit',
                'description' => 'Pantau riwayat aktivitas & jejak integritas sistem',
                'url' => AuditLogResource::getUrl('index'),
                'icon' => 'heroicon-o-shield-check',
                'theme' => 'slate',
                'badge' => null,
                'badge_tone' => 'neutral',
                'open_in_new_tab' => false,
                'permission_hint' => 'view_any:activity_log',
            ];
        }

        $memo[$scopeKey] = $actions;
        $attributes->set('_dashboard_quick_actions', $memo);

        return $actions;
    }
}
