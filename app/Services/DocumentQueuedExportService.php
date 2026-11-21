<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\DocumentStatus;
use App\Enums\VisibilityLevel;
use App\Models\AcademicYear;
use App\Models\BulkExport;
use App\Models\Document;
use App\Models\DocumentCategory;
use App\Models\Unit;
use App\Models\User;
use App\Services\Concerns\FormatsQueuedExportMetadata;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

class DocumentQueuedExportService
{
    use FormatsQueuedExportMetadata;

    public function __construct(
        private readonly DocumentExportService $documentExportService
    ) {}

    /**
     * Memproses ekspor rekapitulasi dokumen arsip (.xlsx) secara asinkron di antrean latar belakang.
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
                'requester_name' => $user?->name ?? 'Sistem E-Arsip UNIVBI',
            ];

            $totalCount = $this->documentExportService->saveSpreadsheetFromQueryToFile(
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
                activity('document_export')
                    ->causedBy($user)
                    ->performedOn($bulkExport)
                    ->event('completed')
                    ->withProperties([
                        'module' => $bulkExport->module_type,
                        'total_documents' => $totalCount,
                        'file_name' => $bulkExport->zip_file_name,
                        'file_size_bytes' => $fileSizeBytes,
                        'filters' => $payload,
                    ])
                    ->log("Ekspor latar belakang dokumen arsip selesai ({$totalCount} dokumen).");

                $humanSize = $this->formatBytes($fileSizeBytes);

                Notification::make()
                    ->success()
                    ->title('Rekapitulasi Dokumen Excel (.xlsx) Siap Diunduh')
                    ->body("Ekspor berisi {$totalCount} dokumen ({$humanSize}) telah selesai diproses. Tautan unduhan aktif selama 24 jam.")
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
            Log::error('DocumentQueuedExportService Error: '.$e->getMessage(), [
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
                    ->title('Ekspor Excel Dokumen Gagal')
                    ->body('Terjadi kendala saat memproses ekspor dokumen ke Excel: '.$e->getMessage())
                    ->sendToDatabase($bulkExport->user);
            }

            return false;
        }
    }

    /**
     * Membangun query dokumen terfilter beserta isolasi hak akses & kerahasiaan pengguna.
     *
     * @param  array<string, mixed>  $filters
     */
    public function buildFilteredQuery(array $filters, ?User $user): Builder
    {
        $query = Document::query()->accessibleFor($user);

        if (filled($filters['issued_from'] ?? null)) {
            $query->whereDate('documents.issued_date', '>=', (string) $filters['issued_from']);
        }

        if (filled($filters['issued_until'] ?? null)) {
            $query->whereDate('documents.issued_date', '<=', (string) $filters['issued_until']);
        }

        if (filled($filters['unit_id'] ?? null)) {
            $query->where('documents.unit_id', (int) $filters['unit_id']);
        }

        if (filled($filters['document_category_id'] ?? null)) {
            $query->where('documents.document_category_id', (int) $filters['document_category_id']);
        }

        if (filled($filters['academic_year_id'] ?? null)) {
            $query->where('documents.academic_year_id', (int) $filters['academic_year_id']);
        }

        $statuses = array_values(array_filter(
            (array) ($filters['status'] ?? []),
            fn (mixed $item): bool => filled($item)
        ));
        if ($statuses !== []) {
            $query->whereIn('documents.status', $statuses);
        }

        if (filled($filters['visibility'] ?? null)) {
            $query->where('documents.visibility', (string) $filters['visibility']);
        }

        $isBorang = $this->parseNullableBoolean($filters['is_borang'] ?? null);
        if ($isBorang !== null) {
            $query->where('documents.is_borang', $isBorang);
        }

        $hasExpiry = $this->parseNullableBoolean($filters['has_expiry'] ?? null);
        if ($hasExpiry !== null) {
            $query->where('documents.has_expiry', $hasExpiry);
        }

        return $query;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function buildPeriodLabel(array $payload): string
    {
        return $this->formatDatePeriodLabel(
            isset($payload['issued_from']) ? (string) $payload['issued_from'] : null,
            isset($payload['issued_until']) ? (string) $payload['issued_until'] : null
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
            $parts[] = 'Unit: '.($unitName ?? '#'.$payload['unit_id']);
        }

        if (filled($payload['document_category_id'] ?? null)) {
            $catName = DocumentCategory::query()->whereKey((int) $payload['document_category_id'])->value('name');
            $parts[] = 'Kategori: '.($catName ?? '#'.$payload['document_category_id']);
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
                fn (mixed $val): string => DocumentStatus::tryFrom((string) $val)?->getLabel() ?? (string) $val,
                $statuses
            );
            $parts[] = 'Status: '.implode(', ', $labels);
        }

        if (filled($payload['visibility'] ?? null)) {
            $visLabel = VisibilityLevel::tryFrom((string) $payload['visibility'])?->getLabel() ?? (string) $payload['visibility'];
            $parts[] = 'Akses: '.$visLabel;
        }

        $isBorang = $this->parseNullableBoolean($payload['is_borang'] ?? null);
        if ($isBorang !== null) {
            $parts[] = 'Borang: '.($isBorang ? 'Ya' : 'Tidak');
        }

        $hasExpiry = $this->parseNullableBoolean($payload['has_expiry'] ?? null);
        if ($hasExpiry !== null) {
            $parts[] = 'Masa Berlaku: '.($hasExpiry ? 'Bermasa Berlaku' : 'Permanen');
        }

        return $parts !== [] ? implode(' | ', $parts) : 'Semua Kategori, Unit & Status';
    }
}
