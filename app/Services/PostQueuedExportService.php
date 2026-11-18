<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\PostPeriod;
use App\Enums\PostStatus;
use App\Models\BulkExport;
use App\Models\Post;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

class PostQueuedExportService
{
    public function __construct(
        private readonly PostExportService $postExportService
    ) {}

    /**
     * Memproses ekspor rekapitulasi berita/acara (.xlsx) secara penuh di latar belakang.
     */
    public function process(BulkExport $bulkExport): bool
    {
        try {
            $bulkExport->update(['status' => 'processing']);

            $disk = Storage::disk($bulkExport->disk ?: 'private');
            $disk->makeDirectory('temp-exports');

            /** @var array<string, mixed> $payload */
            $payload = is_array($bulkExport->record_ids) ? $bulkExport->record_ids : [];
            $user = $bulkExport->user;
            $query = $this->buildPostQuery($payload, $user);

            $periodRaw = $payload['period'] ?? null;
            if (blank($periodRaw) && in_array($payload['active_tab'] ?? null, ['daily', 'weekly', 'monthly'], true)) {
                $periodRaw = $payload['active_tab'];
            }

            $periodFilter = filled($periodRaw) ? PostPeriod::tryFrom((string) $periodRaw) : null;
            $filterSummary = $this->buildFilterSummary($payload);

            $relativePath = "temp-exports/{$bulkExport->uuid}.xlsx";
            $absolutePath = $disk->path($relativePath);

            $totalRows = $this->postExportService->saveSpreadsheetFromQueryToFile(
                $query,
                $absolutePath,
                $periodFilter,
                $filterSummary
            );

            $fileSizeBytes = file_exists($absolutePath) ? (int) filesize($absolutePath) : 0;

            $bulkExport->update([
                'total_files' => $totalRows,
                'zip_file_path' => $relativePath,
                'file_size_bytes' => $fileSizeBytes,
                'status' => 'completed',
                'expires_at' => now()->addDay(),
            ]);

            if ($user) {
                activity('cms_export')
                    ->causedBy($user)
                    ->performedOn($bulkExport)
                    ->event('completed')
                    ->withProperties([
                        'module' => $bulkExport->module_type,
                        'total_posts' => $totalRows,
                        'file_name' => $bulkExport->zip_file_name,
                        'file_size_bytes' => $fileSizeBytes,
                        'filters' => $payload,
                    ])
                    ->log("Ekspor latar belakang rekapitulasi berita selesai ({$totalRows} artikel).");

                $humanSize = $this->formatBytes($fileSizeBytes);

                Notification::make()
                    ->success()
                    ->title('Rekapitulasi Berita (.xlsx) Siap Diunduh')
                    ->body("Ekspor rekapitulasi berisi {$totalRows} artikel ({$humanSize}) telah selesai diproses. Tautan unduhan aktif selama 1 hari (24 jam).")
                    ->actions([
                        Action::make('download')
                            ->label('Unduh Excel (.xlsx)')
                            ->icon('heroicon-o-arrow-down-tray')
                            ->button()
                            ->url($bulkExport->getDownloadUrl())
                            ->openUrlInNewTab(),
                    ])
                    ->sendToDatabase($user);
            }

            return true;
        } catch (Throwable $e) {
            Log::error('PostQueuedExportService Error: '.$e->getMessage(), [
                'bulk_export_id' => $bulkExport->id,
                'module_type' => $bulkExport->module_type,
            ]);

            $bulkExport->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);

            if ($bulkExport->user) {
                Notification::make()
                    ->danger()
                    ->title('Ekspor Rekapitulasi Berita Gagal')
                    ->body('Terjadi kendala saat memproses ekspor rekapitulasi berita: '.$e->getMessage())
                    ->sendToDatabase($bulkExport->user);
            }

            return false;
        }
    }

    /**
     * Membangun query artikel terfilter secara deterministik sesuai hak akses dan filter pengguna.
     *
     * @param  array<string, mixed>  $payload
     */
    private function buildPostQuery(array $payload, ?User $user): Builder
    {
        $likeOperator = DB::connection()->getDriverName() === 'pgsql' ? 'ilike' : 'like';

        $query = Post::query()->accessibleFor($user);

        match ($payload['active_tab'] ?? null) {
            'daily' => $query->where('posts.period', PostPeriod::Daily),
            'weekly' => $query->where('posts.period', PostPeriod::Weekly),
            'monthly' => $query->where('posts.period', PostPeriod::Monthly),
            'published' => $query->where('posts.status', PostStatus::Published),
            'pending_review' => $query->where('posts.status', PostStatus::PendingReview),
            'draft_revision' => $query->whereIn('posts.status', [
                PostStatus::Draft,
                PostStatus::Revision,
            ]),
            default => null,
        };

        if (! empty($payload['post_category_id'])) {
            $query->where('posts.post_category_id', (int) $payload['post_category_id']);
        }

        if (! empty($payload['period'])) {
            $period = PostPeriod::tryFrom((string) $payload['period']);
            if ($period) {
                $query->where('posts.period', $period);
            }
        }

        if (! empty($payload['unit_id'])) {
            $query->where('posts.unit_id', (int) $payload['unit_id']);
        }

        if (! empty($payload['status'])) {
            $status = PostStatus::tryFrom((string) $payload['status']);
            if ($status) {
                $query->where('posts.status', $status);
            }
        }

        foreach (['is_featured', 'is_pinned', 'is_borang'] as $booleanField) {
            $raw = $payload[$booleanField] ?? null;
            if ($raw === null || $raw === '') {
                continue;
            }

            $boolValue = filter_var($raw, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($boolValue !== null) {
                $query->where("posts.{$booleanField}", $boolValue);
            }
        }

        if (! empty($payload['search'])) {
            $search = trim((string) $payload['search']);
            $query->where('posts.title', $likeOperator, "%{$search}%");
        }

        return $query;
    }

    /**
     * Menyusun ringkasan label filter untuk tajuk laporan Excel.
     *
     * @param  array<string, mixed>  $payload
     */
    private function buildFilterSummary(array $payload): ?string
    {
        $parts = [];

        if (! empty($payload['active_tab']) && $payload['active_tab'] !== 'all') {
            $parts[] = match ($payload['active_tab']) {
                'daily' => 'Tab Harian',
                'weekly' => 'Tab Mingguan',
                'monthly' => 'Tab Bulanan',
                'published' => 'Tab Tayang Publik',
                'pending_review' => 'Tab Menunggu Moderasi',
                'draft_revision' => 'Tab Draf & Revisi',
                default => null,
            };
        }

        if (! empty($payload['status']) && ($status = PostStatus::tryFrom((string) $payload['status']))) {
            $parts[] = 'Status: '.$status->getLabel();
        }

        if (! empty($payload['search'])) {
            $parts[] = 'Kata kunci: "'.trim((string) $payload['search']).'"';
        }

        $parts = array_values(array_filter($parts));

        return $parts !== [] ? implode(', ', $parts) : null;
    }

    private function formatBytes(int $bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= (1 << (10 * $pow));

        return round($bytes, $precision).' '.$units[$pow];
    }
}
