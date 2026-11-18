<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\DocumentStatus;
use App\Enums\ReminderInterval;
use App\Models\Document;
use App\Models\ReminderLog;
use App\Models\User;
use App\Notifications\DocumentExpiredNotification;
use App\Notifications\DocumentExpiryReminderNotification;
use App\Services\DTO\ReminderRunResult;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class DocumentReminderService
{
    private const CHUNK_SIZE = 100;

    private const MAX_ERROR_MESSAGE_LENGTH = 1000;

    /**
     * Menjalankan pemindaian dan pengiriman notifikasi pengingat kedaluwarsa dokumen.
     */
    public function run(?CarbonInterface $referenceDate = null, bool $dryRun = false): ReminderRunResult
    {
        $timezone = (string) config('app.timezone', 'Asia/Jakarta');
        $today = ($referenceDate ?? now($timezone))->copy()->startOfDay();
        $result = new ReminderRunResult;

        // 1. Ubah status dokumen yang sudah melewati masa berlaku menjadi Expired & kirim notifikasi kedaluwarsa
        $result->expiredMarkedCount = $this->markExpiredDocuments($today, $dryRun, $result);

        // 2. Pindai dokumen aktif (Approved) secara bertahap (chunkById) untuk menjaga efisiensi memori.
        // Dokumen berstatus Archived (Pasif) dikecualikan agar tidak lagi menerima pengingat kedaluwarsa.
        Document::query()
            ->where('has_expiry', true)
            ->whereNotNull('expired_date')
            ->where('expired_date', '>=', $today->toDateString())
            ->where('status', DocumentStatus::Approved)
            ->whereNull('deleted_at')
            ->with(['unit', 'uploader'])
            ->orderBy('id')
            ->chunkById(self::CHUNK_SIZE, function (EloquentCollection $documents) use ($today, $dryRun, $result): void {
                $result->scannedCount += $documents->count();

                $sentLookup = $this->prefetchSentLogs($documents->pluck('id')->all());

                foreach ($documents as $document) {
                    $this->processDocument($document, $today, $dryRun, $result, $sentLookup);
                }
            });

        return $result;
    }

    /**
     * Menandai dokumen yang tanggal kedaluwarsanya telah lewat menjadi status Expired
     * serta mengirimkan notifikasi resmi kedaluwarsa kepada penerima terkait.
     */
    public function markExpiredDocuments(
        CarbonInterface $today,
        bool $dryRun = false,
        ?ReminderRunResult $result = null
    ): int {
        $query = Document::query()
            ->where('has_expiry', true)
            ->whereNotNull('expired_date')
            ->where('expired_date', '<', $today->toDateString())
            ->where('status', DocumentStatus::Approved)
            ->whereNull('deleted_at');

        if ($dryRun && $result === null) {
            return (clone $query)->count();
        }

        $count = 0;

        $query
            ->with(['unit', 'uploader'])
            ->orderBy('id')
            ->chunkById(self::CHUNK_SIZE, function (EloquentCollection $expiredDocs) use ($today, $dryRun, $result, &$count): void {
                foreach ($expiredDocs as $doc) {
                    $count++;

                    if ($dryRun) {
                        if ($result !== null) {
                            $this->simulateExpiredNotification($doc, $today, $result);
                        }

                        continue;
                    }

                    $previousStatus = $doc->status instanceof DocumentStatus
                        ? $doc->status->value
                        : (string) $doc->status;

                    // Matikan pencatatan otomatis Spatie sementara agar tidak terjadi log ganda untuk transisi status yang sama.
                    $doc->disableLogging();
                    try {
                        $doc->update(['status' => DocumentStatus::Expired]);
                    } finally {
                        $doc->enableLogging();
                    }

                    $this->recordAuditLogSafely(
                        $doc,
                        "Masa berlaku naskah dinas telah berakhir pada {$doc->expired_date?->format('d/m/Y')}. Status otomatis dialihkan menjadi Expired.",
                        'expired',
                        [
                            'old' => ['status' => $previousStatus],
                            'attributes' => ['status' => DocumentStatus::Expired->value],
                            'expired_date' => $doc->expired_date?->toDateString(),
                        ]
                    );

                    $this->dispatchExpiredNotifications($doc, $today, $result);
                }
            });

        return $count;
    }

    /**
     * Memproses pengiriman pengingat untuk satu dokumen kepada seluruh penerimanya.
     *
     * @param  array<int, array<string, array<string, array<string, bool>>>>  $sentLookup
     */
    protected function processDocument(
        Document $document,
        CarbonInterface $today,
        bool $dryRun,
        ReminderRunResult $result,
        array &$sentLookup = []
    ): void {
        $daysRemaining = (int) $today->diffInDays($document->expired_date?->copy()->startOfDay(), false);

        /** @var Collection<int, User> $recipients */
        $recipients = $document->getReminderRecipientUsers();

        if ($recipients->isEmpty()) {
            $result->skippedCount++;

            return;
        }

        $seenEmails = [];

        foreach ($recipients as $recipient) {
            $recipientEmail = strtolower(trim((string) $recipient->email));

            if ($recipientEmail === '') {
                $result->skippedCount++;

                continue;
            }

            if (isset($seenEmails[$recipientEmail])) {
                continue;
            }
            $seenEmails[$recipientEmail] = true;

            $dueInterval = $this->resolveDueIntervalForRecipient($document, $recipientEmail, $today, $sentLookup);

            if (! $dueInterval) {
                $result->skippedCount++;

                continue;
            }

            $snapshot = $document->expired_date?->toDateString() ?? $today->toDateString();

            if (! filter_var($recipientEmail, FILTER_VALIDATE_EMAIL)) {
                $errorMessage = "Alamat email penerima tidak valid: '{$recipientEmail}'.";
                $this->recordInvalidEmailFailure($document, $recipient, $recipientEmail, $dueInterval, $daysRemaining, $today, $snapshot, $dryRun, $result, $errorMessage);

                continue;
            }

            if ($dryRun) {
                $result->sentCount++;
                $result->details[] = [
                    'document_id' => $document->id,
                    'document_number' => $document->document_number,
                    'recipient_email' => $recipientEmail,
                    'interval' => $dueInterval->value,
                    'days_remaining' => $daysRemaining,
                    'status' => 'would_send',
                ];

                continue;
            }

            try {
                $recipient->notifyNow(new DocumentExpiryReminderNotification($document, $dueInterval, $daysRemaining));
            } catch (\Throwable $e) {
                $errorMessage = $this->sanitizeErrorMessage($e->getMessage());

                $this->recordReminderLogSafely(
                    documentId: $document->id,
                    recipientUserId: $recipient->id,
                    recipientEmail: $recipientEmail,
                    interval: $dueInterval,
                    today: $today,
                    snapshot: $snapshot,
                    isSuccess: false,
                    errorMessage: $errorMessage,
                );

                Log::error("Gagal mengirimkan notifikasi pengingat kedaluwarsa dokumen #{$document->id} ke {$recipientEmail}: {$errorMessage}");

                $result->failedCount++;
                $result->errors[] = "Dokumen #{$document->id} ({$recipientEmail}): {$errorMessage}";
                $result->details[] = [
                    'document_id' => $document->id,
                    'document_number' => $document->document_number,
                    'recipient_email' => $recipientEmail,
                    'interval' => $dueInterval->value,
                    'days_remaining' => $daysRemaining,
                    'status' => 'failed',
                ];

                continue;
            }

            $sentLookup[$document->id][$recipientEmail][$dueInterval->value][$snapshot] = true;

            $this->recordReminderLogSafely(
                documentId: $document->id,
                recipientUserId: $recipient->id,
                recipientEmail: $recipientEmail,
                interval: $dueInterval,
                today: $today,
                snapshot: $snapshot,
                isSuccess: true,
                errorMessage: null,
            );

            $this->recordAuditLogSafely(
                $document,
                "Mengirimkan notifikasi pengingat masa berlaku [{$dueInterval->getLabel()}] kepada {$recipient->name} ({$recipientEmail})."
            );

            $result->sentCount++;
            $result->details[] = [
                'document_id' => $document->id,
                'document_number' => $document->document_number,
                'recipient_email' => $recipientEmail,
                'interval' => $dueInterval->value,
                'days_remaining' => $daysRemaining,
                'status' => 'sent',
            ];
        }
    }

    /**
     * Menentukan interval pengingat yang jatuh tempo untuk penerima tertentu pada tanggal hari ini.
     * Menerapkan mode susulan (catch-up) dengan memprioritaskan interval yang paling mendesak.
     *
     * @param  array<int, array<string, array<string, array<string, bool>>>>|null  $sentLookup
     */
    public function resolveDueIntervalForRecipient(
        Document $document,
        string $recipientEmail,
        CarbonInterface $today,
        ?array $sentLookup = null
    ): ?ReminderInterval {
        if (! $document->expired_date) {
            return null;
        }

        $daysRemaining = (int) $today->diffInDays($document->expired_date->copy()->startOfDay(), false);

        if ($daysRemaining < 0) {
            return null;
        }

        $configured = (array) ($document->reminder_intervals ?? []);

        if (empty($configured)) {
            return null;
        }

        $intervals = [];

        foreach ($configured as $val) {
            $enum = is_string($val) ? ReminderInterval::tryFrom($val) : ($val instanceof ReminderInterval ? $val : null);
            if ($enum) {
                $intervals[] = $enum;
            }
        }

        if (empty($intervals)) {
            return null;
        }

        // Urutkan dari yang paling mendesak / terdekat ke kedaluwarsa (H7 -> H30 -> H60)
        usort($intervals, fn (ReminderInterval $a, ReminderInterval $b): int => $a->daysBeforeExpiry() <=> $b->daysBeforeExpiry());

        $normalizedEmail = strtolower(trim($recipientEmail));
        $snapshot = $document->expired_date->toDateString();

        foreach ($intervals as $interval) {
            if ($daysRemaining <= $interval->daysBeforeExpiry()) {
                $alreadySent = $sentLookup !== null
                    ? isset($sentLookup[$document->id][$normalizedEmail][$interval->value][$snapshot])
                    : $this->hasSent($document->id, $normalizedEmail, $interval, $snapshot);

                if (! $alreadySent) {
                    return $interval;
                }

                // Jika interval yang lebih mendesak sudah terkirim, hentikan agar tidak mengirim interval yang lebih lama
                return null;
            }
        }

        return null;
    }

    /**
     * Memeriksa apakah notifikasi untuk interval dan masa berlaku tertentu sudah pernah sukses terkirim.
     */
    public function hasSent(
        int $documentId,
        string $recipientEmail,
        ReminderInterval $interval,
        string $expiredDateSnapshot
    ): bool {
        $normalizedEmail = strtolower(trim($recipientEmail));

        return ReminderLog::query()
            ->where('document_id', $documentId)
            ->where('recipient_email', $normalizedEmail)
            ->where('interval_type', $interval)
            ->whereDate('expired_date_snapshot', $expiredDateSnapshot)
            ->where('is_success', true)
            ->exists();
    }

    /**
     * Mengambil indeks log pengiriman sukses untuk satu batch dokumen guna mencegah N+1 query.
     *
     * @param  list<int>  $documentIds
     * @return array<int, array<string, array<string, array<string, bool>>>>
     */
    private function prefetchSentLogs(array $documentIds): array
    {
        if (empty($documentIds)) {
            return [];
        }

        $logs = ReminderLog::query()
            ->whereIn('document_id', $documentIds)
            ->where('is_success', true)
            ->get(['document_id', 'recipient_email', 'interval_type', 'expired_date_snapshot']);

        $lookup = [];

        foreach ($logs as $log) {
            $email = strtolower(trim((string) $log->recipient_email));
            $intervalValue = $log->interval_type instanceof ReminderInterval
                ? $log->interval_type->value
                : (string) $log->interval_type;
            $snapshot = $log->expired_date_snapshot?->toDateString() ?? '';

            if ($email !== '' && $intervalValue !== '' && $snapshot !== '') {
                $lookup[(int) $log->document_id][$email][$intervalValue][$snapshot] = true;
            }
        }

        return $lookup;
    }

    /**
     * Mengirimkan notifikasi resmi saat dokumen pertama kali dialihkan menjadi status Expired.
     */
    private function dispatchExpiredNotifications(
        Document $doc,
        CarbonInterface $today,
        ?ReminderRunResult $result
    ): void {
        $recipients = $doc->getReminderRecipientUsers();
        $daysRemaining = (int) $today->diffInDays($doc->expired_date?->copy()->startOfDay(), false);
        $seenEmails = [];

        foreach ($recipients as $recipient) {
            $recipientEmail = strtolower(trim((string) $recipient->email));

            if ($recipientEmail === '' || isset($seenEmails[$recipientEmail])) {
                continue;
            }
            $seenEmails[$recipientEmail] = true;

            if (! filter_var($recipientEmail, FILTER_VALIDATE_EMAIL)) {
                $errorMessage = "Alamat email penerima tidak valid untuk notifikasi kedaluwarsa: '{$recipientEmail}'.";
                Log::warning("Dokumen #{$doc->id}: {$errorMessage}");

                if ($result !== null) {
                    $result->failedCount++;
                    $result->errors[] = "Dokumen #{$doc->id} ({$recipientEmail}): {$errorMessage}";
                    $result->details[] = [
                        'document_id' => $doc->id,
                        'document_number' => $doc->document_number,
                        'recipient_email' => $recipientEmail,
                        'interval' => 'expired',
                        'days_remaining' => $daysRemaining,
                        'status' => 'failed',
                    ];
                }

                continue;
            }

            try {
                $recipient->notifyNow(new DocumentExpiredNotification($doc, $today));
            } catch (\Throwable $e) {
                $errorMessage = $this->sanitizeErrorMessage($e->getMessage());
                Log::error("Gagal mengirimkan notifikasi status Expired dokumen #{$doc->id} ke {$recipientEmail}: {$errorMessage}");

                if ($result !== null) {
                    $result->failedCount++;
                    $result->errors[] = "Dokumen #{$doc->id} ({$recipientEmail}): {$errorMessage}";
                    $result->details[] = [
                        'document_id' => $doc->id,
                        'document_number' => $doc->document_number,
                        'recipient_email' => $recipientEmail,
                        'interval' => 'expired',
                        'days_remaining' => $daysRemaining,
                        'status' => 'failed',
                    ];
                }

                continue;
            }

            $this->recordAuditLogSafely(
                $doc,
                "Mengirimkan notifikasi status Kedaluwarsa (Expired) kepada {$recipient->name} ({$recipientEmail})."
            );

            if ($result !== null) {
                $result->expiredNotifiedCount++;
                $result->details[] = [
                    'document_id' => $doc->id,
                    'document_number' => $doc->document_number,
                    'recipient_email' => $recipientEmail,
                    'interval' => 'expired',
                    'days_remaining' => $daysRemaining,
                    'status' => 'sent',
                ];
            }
        }
    }

    /**
     * Mensimulasikan pengiriman notifikasi status Expired pada mode dry-run.
     */
    private function simulateExpiredNotification(Document $doc, CarbonInterface $today, ReminderRunResult $result): void
    {
        $recipients = $doc->getReminderRecipientUsers();
        $daysRemaining = (int) $today->diffInDays($doc->expired_date?->copy()->startOfDay(), false);
        $seenEmails = [];

        foreach ($recipients as $recipient) {
            $recipientEmail = strtolower(trim((string) $recipient->email));

            if ($recipientEmail === '' || isset($seenEmails[$recipientEmail]) || ! filter_var($recipientEmail, FILTER_VALIDATE_EMAIL)) {
                continue;
            }
            $seenEmails[$recipientEmail] = true;

            $result->expiredNotifiedCount++;
            $result->details[] = [
                'document_id' => $doc->id,
                'document_number' => $doc->document_number,
                'recipient_email' => $recipientEmail,
                'interval' => 'expired',
                'days_remaining' => $daysRemaining,
                'status' => 'would_send',
            ];
        }
    }

    /**
     * Mencatat kegagalan validasi email penerima secara terisolasi.
     */
    private function recordInvalidEmailFailure(
        Document $document,
        User $recipient,
        string $recipientEmail,
        ReminderInterval $dueInterval,
        int $daysRemaining,
        CarbonInterface $today,
        string $snapshot,
        bool $dryRun,
        ReminderRunResult $result,
        string $errorMessage
    ): void {
        if (! $dryRun) {
            $this->recordReminderLogSafely(
                documentId: $document->id,
                recipientUserId: $recipient->id,
                recipientEmail: $recipientEmail,
                interval: $dueInterval,
                today: $today,
                snapshot: $snapshot,
                isSuccess: false,
                errorMessage: $errorMessage,
            );
        }

        Log::warning("Dokumen #{$document->id}: {$errorMessage}");
        $result->failedCount++;
        $result->errors[] = "Dokumen #{$document->id} ({$recipientEmail}): {$errorMessage}";
        $result->details[] = [
            'document_id' => $document->id,
            'document_number' => $document->document_number,
            'recipient_email' => $recipientEmail,
            'interval' => $dueInterval->value,
            'days_remaining' => $daysRemaining,
            'status' => 'failed',
        ];
    }

    /**
     * Menyimpan catatan ReminderLog dalam blok terisolasi agar kegagalan logging
     * tidak membatalkan eksekusi batch dokumen lainnya.
     */
    private function recordReminderLogSafely(
        int $documentId,
        ?int $recipientUserId,
        string $recipientEmail,
        ReminderInterval $interval,
        CarbonInterface $today,
        string $snapshot,
        bool $isSuccess,
        ?string $errorMessage
    ): void {
        try {
            ReminderLog::create([
                'document_id' => $documentId,
                'recipient_user_id' => $recipientUserId,
                'recipient_email' => $recipientEmail,
                'interval_type' => $interval,
                'sent_date' => $today->toDateString(),
                'expired_date_snapshot' => $snapshot,
                'sent_at' => now(),
                'is_success' => $isSuccess,
                'error_message' => $errorMessage,
            ]);
        } catch (\Throwable $logException) {
            Log::critical("Gagal menyimpan ReminderLog untuk dokumen #{$documentId} ({$recipientEmail}): {$logException->getMessage()}");
        }
    }

    /**
     * Menyimpan log aktivitas secara aman tanpa mengganggu alur notifikasi utama.
     *
     * @param  array<string, mixed>  $properties
     */
    private function recordAuditLogSafely(
        Document $document,
        string $description,
        ?string $event = null,
        array $properties = []
    ): void {
        try {
            $logger = activity('document_audit')->performedOn($document);

            if ($event !== null) {
                $logger->event($event);
            }

            if (! empty($properties)) {
                $logger->withProperties($properties);
            }

            $logger->log($description);
        } catch (\Throwable $auditException) {
            Log::warning("Gagal mencatat activity_log pengingat untuk dokumen #{$document->id}: {$auditException->getMessage()}");
        }
    }

    /**
     * Memastikan pesan kesalahan bersih (valid UTF-8) dan tidak melebihi batas penyimpanan.
     */
    private function sanitizeErrorMessage(string $message): string
    {
        $clean = trim(mb_convert_encoding($message, 'UTF-8', 'UTF-8'));

        if (mb_strlen($clean, 'UTF-8') > self::MAX_ERROR_MESSAGE_LENGTH) {
            return mb_substr($clean, 0, self::MAX_ERROR_MESSAGE_LENGTH - 3, 'UTF-8').'...';
        }

        return $clean;
    }
}
