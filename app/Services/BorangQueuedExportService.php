<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\DocumentStatus;
use App\Enums\VisibilityLevel;
use App\Models\AcademicYear;
use App\Models\AccreditationCriterion;
use App\Models\BulkExport;
use App\Models\Document;
use App\Models\Unit;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

class BorangQueuedExportService
{
    public function __construct(
        private readonly BorangExportService $excelService,
        private readonly BorangZipExportService $zipService
    ) {}

    /**
     * Memproses ekspor massal Borang (Excel .xlsx atau Bundel .zip) secara asinkron di antrean.
     */
    public function process(BulkExport $bulkExport): bool
    {
        try {
            $bulkExport->update(['status' => 'processing']);

            $disk = Storage::disk($bulkExport->disk ?: 'private');
            $disk->makeDirectory('temp-exports');

            /** @var array<string, mixed> $payload */
            $payload = is_array($bulkExport->record_ids) ? $bulkExport->record_ids : [];
            $query = $this->buildDocumentQuery($payload);
            $user = $bulkExport->user;

            $isExcel = $bulkExport->module_type === 'borang_excel';
            $extension = $isExcel ? 'xlsx' : 'zip';
            $relativePath = "temp-exports/{$bulkExport->uuid}.{$extension}";
            $absolutePath = $disk->path($relativePath);

            if ($isExcel) {
                $unit = ! empty($payload['unit_id']) ? Unit::find((int) $payload['unit_id']) : null;
                if (! $unit && ! ($payload['bypass_unit_scope'] ?? false) && ! empty($payload['scope_unit_id'])) {
                    $unit = Unit::find((int) $payload['scope_unit_id']);
                }

                $academicYear = ! empty($payload['academic_year_id']) ? AcademicYear::find((int) $payload['academic_year_id']) : null;
                $criterion = ! empty($payload['criterion_id']) ? AccreditationCriterion::find((int) $payload['criterion_id']) : null;
                $instrumentSource = ! empty($payload['instrument_source'])
                    ? (string) $payload['instrument_source']
                    : ($criterion?->instrument_source ?? $unit?->effective_accreditation_instrument);

                $totalCount = (clone $query)->count();
                $this->excelService->saveSpreadsheetFromQueryToFile(
                    $query,
                    $absolutePath,
                    $unit,
                    $academicYear,
                    $criterion,
                    $instrumentSource
                );
            } else {
                $totalCount = $this->zipService->writeZipFromQueryToPath($query, $absolutePath);
            }

            $fileSizeBytes = file_exists($absolutePath) ? (int) filesize($absolutePath) : 0;

            $bulkExport->update([
                'total_files' => $totalCount,
                'zip_file_path' => $relativePath,
                'file_size_bytes' => $fileSizeBytes,
                'status' => 'completed',
                'expires_at' => now()->addHours(24),
            ]);

            if ($user) {
                activity('borang_export')
                    ->causedBy($user)
                    ->performedOn($bulkExport)
                    ->event('completed')
                    ->withProperties([
                        'module' => $bulkExport->module_type,
                        'total_documents' => $totalCount,
                        'file_name' => $bulkExport->zip_file_name,
                        'file_size_bytes' => $fileSizeBytes,
                    ])
                    ->log("Ekspor latar belakang {$bulkExport->module_type} selesai ({$totalCount} dokumen).");

                $humanSize = $this->formatBytes($fileSizeBytes);
                $title = $isExcel ? 'Rekapitulasi Excel Borang Siap Diunduh' : 'Bundel ZIP Bukti Borang Siap Diunduh';
                $btnLabel = $isExcel ? 'Unduh Excel (.xlsx)' : 'Unduh Bundel (.ZIP)';

                Notification::make()
                    ->success()
                    ->title($title)
                    ->body("Proses ekspor berisi {$totalCount} dokumen ({$humanSize}) telah selesai. Tautan berlaku 24 jam.")
                    ->actions([
                        Action::make('download')
                            ->label($btnLabel)
                            ->icon('heroicon-o-arrow-down-tray')
                            ->button()
                            ->url($bulkExport->getDownloadUrl())
                            ->openUrlInNewTab(),
                    ])
                    ->sendToDatabase($user);
            }

            return true;
        } catch (Throwable $e) {
            Log::error('BorangQueuedExportService Error: '.$e->getMessage(), [
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
                    ->title('Ekspor Borang Latar Belakang Gagal')
                    ->body('Terjadi kendala saat memproses ekspor borang: '.$e->getMessage())
                    ->sendToDatabase($bulkExport->user);
            }

            return false;
        }
    }

    /**
     * Membangun query dokumen borang secara deterministik berdasarkan filter/tenancy saat pengajuan.
     *
     * @param  array<string, mixed>  $payload
     */
    private function buildDocumentQuery(array $payload): Builder
    {
        $likeOperator = DB::connection()->getDriverName() === 'pgsql' ? 'ilike' : 'like';

        $query = Document::query()
            ->where('documents.is_borang', true)
            ->where('documents.status', DocumentStatus::Approved);

        if (! ($payload['bypass_unit_scope'] ?? false)) {
            if (isset($payload['scope_unit_ids']) && is_array($payload['scope_unit_ids'])) {
                $query->whereIn('documents.unit_id', array_map('intval', $payload['scope_unit_ids']));
            } elseif (! empty($payload['scope_unit_id'])) {
                $query->where('documents.unit_id', (int) $payload['scope_unit_id']);
            }
        }

        if (! ($payload['can_view_confidential'] ?? true)) {
            $userId = isset($payload['user_id']) ? (int) $payload['user_id'] : 0;
            $query->where(function (Builder $q) use ($userId): void {
                $q->where('documents.visibility', '!=', VisibilityLevel::Confidential->value)
                    ->orWhere('documents.uploaded_by', $userId);
            });
        }

        if (! empty($payload['document_ids']) && is_array($payload['document_ids'])) {
            $query->whereIn('documents.id', array_map('intval', $payload['document_ids']));

            return $query;
        }

        if (! empty($payload['unit_id'])) {
            $query->where('documents.unit_id', (int) $payload['unit_id']);
        }

        if (! empty($payload['academic_year_id'])) {
            $query->where('documents.academic_year_id', (int) $payload['academic_year_id']);
        }

        if (! empty($payload['criterion_id'])) {
            $criterionId = (int) $payload['criterion_id'];
            $query->whereHas('accreditationCriteria', fn (Builder $q): Builder => $q->where('accreditation_criteria.id', $criterionId));
        }

        if (! empty($payload['instrument_source'])) {
            $instrument = (string) $payload['instrument_source'];
            $query->whereHas('accreditationCriteria', fn (Builder $q): Builder => $q->where('accreditation_criteria.instrument_source', $instrument));
        }

        if (! empty($payload['search'])) {
            $search = trim((string) $payload['search']);
            $query->where(function (Builder $q) use ($search, $likeOperator): void {
                $q->where('documents.title', $likeOperator, "%{$search}%")
                    ->orWhere('documents.document_number', $likeOperator, "%{$search}%");
            });
        }

        return $query;
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
