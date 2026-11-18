<?php

declare(strict_types=1);

namespace App\Filament\Resources\Posts\Pages;

use App\Enums\PostPeriod;
use App\Enums\PostStatus;
use App\Filament\Resources\Posts\PostResource;
use App\Filament\Widgets\PostPeriodOverviewWidget;
use App\Jobs\GeneratePostExportJob;
use App\Models\BulkExport;
use App\Models\Post;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class ListPosts extends ListRecords
{
    protected static string $resource = PostResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('exportExcel')
                ->label('Ekspor Rekapitulasi Berita (.xlsx)')
                ->icon(Heroicon::OutlinedArrowDownTray)
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading('Proses Ekspor Rekapitulasi Berita di Latar Belakang?')
                ->modalDescription('Ekspor dijalankan penuh di antrean latar belakang agar halaman tetap cepat walaupun data mencapai jutaan baris. Anda akan menerima notifikasi beserta tautan unduhan yang aktif selama 1 hari setelah berkas siap.')
                ->modalSubmitActionLabel('Ya, Jalankan Ekspor')
                ->action(function (): void {
                    $user = auth()->user();
                    if (! $user) {
                        return;
                    }

                    $periodRaw = $this->tableFilters['period']['value'] ?? null;
                    if (blank($periodRaw) && in_array($this->activeTab, ['daily', 'weekly', 'monthly'], true)) {
                        $periodRaw = $this->activeTab;
                    }

                    $periodEnum = filled($periodRaw) ? PostPeriod::tryFrom((string) $periodRaw) : null;
                    $suffix = $periodEnum ? '-'.strtoupper($periodEnum->value) : '';
                    $filename = 'Rekapitulasi-Berita'.$suffix.'-UNIVBI-'.now()->format('Ymd-His').'.xlsx';

                    $payload = [
                        'user_id' => $user->id,
                        'active_tab' => $this->activeTab,
                        'search' => filled($this->tableSearch) ? trim((string) $this->tableSearch) : null,
                        'post_category_id' => filled($this->tableFilters['post_category_id']['value'] ?? null)
                            ? (int) $this->tableFilters['post_category_id']['value']
                            : null,
                        'period' => filled($this->tableFilters['period']['value'] ?? null)
                            ? (string) $this->tableFilters['period']['value']
                            : null,
                        'unit_id' => filled($this->tableFilters['unit_id']['value'] ?? null)
                            ? (int) $this->tableFilters['unit_id']['value']
                            : null,
                        'status' => filled($this->tableFilters['status']['value'] ?? null)
                            ? (string) $this->tableFilters['status']['value']
                            : null,
                        'is_featured' => $this->tableFilters['is_featured']['value'] ?? null,
                        'is_pinned' => $this->tableFilters['is_pinned']['value'] ?? null,
                        'is_borang' => $this->tableFilters['is_borang']['value'] ?? null,
                    ];

                    $export = BulkExport::create([
                        'uuid' => (string) Str::uuid(),
                        'user_id' => $user->id,
                        'module_type' => 'posts_excel',
                        'record_ids' => $payload,
                        'total_files' => 0,
                        'zip_file_name' => $filename,
                        'disk' => 'private',
                        'status' => 'pending',
                        'expires_at' => now()->addDay(),
                    ]);

                    GeneratePostExportJob::dispatch($export);

                    Notification::make()
                        ->info()
                        ->title('Ekspor Rekapitulasi Berita Sedang Diproses')
                        ->body('Permintaan ekspor Anda telah masuk ke antrean latar belakang. Anda akan mendapatkan notifikasi lonceng berisi tautan unduhan (berlaku 1 hari) begitu proses selesai.')
                        ->send();
                })
                ->visible(fn (): bool => auth()->user()?->can('post.moderate_humas') || auth()->user()?->can('view_any:post')),

            CreateAction::make(),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            PostPeriodOverviewWidget::class,
        ];
    }

    public function getTabs(): array
    {
        $user = auth()->user();
        $isHumasOrAdmin = $user && ($user->can('post.moderate_humas') || $user->can('document.bypass_unit_scope'));

        $version = Post::getStatsCacheVersion();
        $badgeScope = $isHumasOrAdmin ? 'all' : 'u'.(int) ($user?->unit_id ?? 0);

        $pendingCount = Cache::remember(
            "posts_pending_badge:v{$version}:{$badgeScope}",
            now()->addSeconds(120),
            static function () use ($isHumasOrAdmin, $user): int {
                return Post::query()
                    ->where('status', PostStatus::PendingReview)
                    ->when(! $isHumasOrAdmin && $user, fn ($q) => $q->where('unit_id', $user->unit_id))
                    ->count();
            }
        );

        return [
            'all' => Tab::make('Semua Artikel')
                ->icon('heroicon-o-newspaper'),

            'daily' => Tab::make('Harian')
                ->icon('heroicon-o-sun')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('period', PostPeriod::Daily)),

            'weekly' => Tab::make('Mingguan')
                ->icon('heroicon-o-calendar-days')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('period', PostPeriod::Weekly)),

            'monthly' => Tab::make('Bulanan')
                ->icon('heroicon-o-document-chart-bar')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('period', PostPeriod::Monthly)),

            'published' => Tab::make('Tayang Publik')
                ->icon('heroicon-o-check-badge')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('status', PostStatus::Published)),

            'pending_review' => Tab::make('Menunggu Moderasi')
                ->icon('heroicon-o-clock')
                ->badge($pendingCount > 0 ? (string) $pendingCount : null)
                ->badgeColor('warning')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('status', PostStatus::PendingReview)),

            'draft_revision' => Tab::make('Draf & Revisi')
                ->icon('heroicon-o-pencil-square')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->whereIn('status', [
                    PostStatus::Draft,
                    PostStatus::Revision,
                ])),
        ];
    }
}
