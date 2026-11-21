<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\IncomingLetterStatus;
use App\Enums\LetterPriority;
use App\Models\AcademicYear;
use App\Models\BulkExport;
use App\Models\IncomingLetter;
use App\Models\Unit;
use App\Models\User;
use App\Services\Concerns\FormatsQueuedExportMetadata;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

class IncomingLetterQueuedExportService
{
    use FormatsQueuedExportMetadata;

    public function __construct(
        private readonly IncomingLetterExportService $exportService
    ) {}

    /**
     * Memproses ekspor rekapitulasi Surat Masuk (.xlsx) secara asinkron di antrean latar belakang.
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

            $query = $this->buildFilteredQuery($payload, $user);

            $relativePath = "temp-exports/{$bulkExport->uuid}.xlsx";
            $absolutePath = $disk->path($relativePath);

            $metadata = [
                'period_label' => $this->buildPeriodLabel($payload),
                'filter_summary' => $this->buildFilterSummary($payload),
                'requester_name' => $user?->name ?? 'Tim Humas & E-Arsip UNIVBI',
            ];

            $totalCount = $this->exportService->saveSpreadsheetFromQueryToFile(
                $query,
                $absolutePath,
                $metadata
            );

            $fileSizeBytes = file_exists($absolutePath) ? (int) filesize($absolutePath) : 0;

            $bulkExport->update([
                'total_files' => $totalCount,
                'zip_file_path' => $relativePath,
                'file_size_bytes' => $fileSizeBytes,
                'status' => 'completed',
                'expires_at' => now()->addHours(24),
            ]);

            if ($user) {
                activity('letter_export')
                    ->causedBy($user)
                    ->performedOn($bulkExport)
                    ->event('completed')
                    ->withProperties([
                        'module' => $bulkExport->module_type,
                        'total_letters' => $totalCount,
                        'file_name' => $bulkExport->zip_file_name,
                        'file_size_bytes' => $fileSizeBytes,
                        'filters' => $payload,
                    ])
                    ->log("Ekspor latar belakang surat masuk selesai ({$totalCount} surat).");

                $humanSize = $this->formatBytes($fileSizeBytes);

                Notification::make()
                    ->success()
                    ->title('Rekapitulasi Surat Masuk Excel (.xlsx) Siap Diunduh')
                    ->body("Ekspor berisi {$totalCount} surat masuk ({$humanSize}) telah selesai diproses. Tautan unduhan aktif selama 24 jam.")
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
            Log::error('IncomingLetterQueuedExportService Error: '.$e->getMessage(), [
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
                    ->title('Ekspor Excel Surat Masuk Gagal')
                    ->body('Terjadi kendala saat memproses ekspor surat masuk ke Excel: '.$e->getMessage())
                    ->sendToDatabase($bulkExport->user);
            }

            return false;
        }
    }

    /**
     * Membangun query Surat Masuk terfilter beserta isolasi hak akses/urutan distribusi pengguna.
     *
     * @param  array<string, mixed>  $filters
     */
    public function buildFilteredQuery(array $filters, ?User $user): Builder
    {
        $query = IncomingLetter::query()->accessibleFor($user);

        if (filled($filters['received_from'] ?? null)) {
            $query->whereDate('incoming_letters.received_date', '>=', (string) $filters['received_from']);
        }

        if (filled($filters['received_until'] ?? null)) {
            $query->whereDate('incoming_letters.received_date', '<=', (string) $filters['received_until']);
        }

        if (filled($filters['unit_id'] ?? null)) {
            $query->where('incoming_letters.unit_id', (int) $filters['unit_id']);
        }

        if (filled($filters['academic_year_id'] ?? null)) {
            $query->where('incoming_letters.academic_year_id', (int) $filters['academic_year_id']);
        }

        $statuses = array_values(array_filter(
            (array) ($filters['status'] ?? []),
            fn (mixed $item): bool => filled($item)
        ));
        if ($statuses !== []) {
            $query->whereIn('incoming_letters.status', $statuses);
        }

        $priorities = array_values(array_filter(
            (array) ($filters['priority'] ?? []),
            fn (mixed $item): bool => filled($item)
        ));
        if ($priorities !== []) {
            $query->whereIn('incoming_letters.priority', $priorities);
        }

        return $query;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function buildPeriodLabel(array $payload): string
    {
        return $this->formatDatePeriodLabel(
            isset($payload['received_from']) ? (string) $payload['received_from'] : null,
            isset($payload['received_until']) ? (string) $payload['received_until'] : null
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function buildFilterSummary(array $payload): string
    {
        $parts = [];

        if (filled($payload['unit_id'] ?? null)) {
            $unitName = Unit::query()->whereKey((int) $payload['unit_id'])->value('name');
            $parts[] = 'Unit Tujuan: '.($unitName ?? '#'.$payload['unit_id']);
        }

        if (filled($payload['academic_year_id'] ?? null)) {
            $yearName = AcademicYear::query()->whereKey((int) $payload['academic_year_id'])->value('name');
            $parts[] = 'TA: '.($yearName ?? '#'.$payload['academic_year_id']);
        }

        $statuses = array_values(array_filter(
            (array) ($payload['status'] ?? []),
            fn (mixed $item): bool => filled($item)
        ));
        if ($statuses !== []) {
            $labels = array_map(
                fn (mixed $val): string => IncomingLetterStatus::tryFrom((string) $val)?->getLabel() ?? (string) $val,
                $statuses
            );
            $parts[] = 'Status: '.implode(', ', $labels);
        }

        $priorities = array_values(array_filter(
            (array) ($payload['priority'] ?? []),
            fn (mixed $item): bool => filled($item)
        ));
        if ($priorities !== []) {
            $labels = array_map(
                fn (mixed $val): string => LetterPriority::tryFrom((string) $val)?->getLabel() ?? (string) $val,
                $priorities
            );
            $parts[] = 'Sifat: '.implode(', ', $labels);
        }

        return $parts !== [] ? implode(' | ', $parts) : 'Semua Unit Tujuan, Sifat & Status';
    }
}
