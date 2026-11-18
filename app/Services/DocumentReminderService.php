<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\DocumentStatus;
use App\Enums\ReminderInterval;
use App\Models\Document;
use App\Models\ReminderLog;
use App\Models\User;
use App\Notifications\DocumentExpiryReminderNotification;
use App\Services\DTO\ReminderRunResult;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class DocumentReminderService
{
    /**
     * Menjalankan pemindaian dan pengiriman notifikasi pengingat kedaluwarsa dokumen.
     */
    public function run(?CarbonInterface $referenceDate = null, bool $dryRun = false): ReminderRunResult
    {
        $today = ($referenceDate ?? now())->copy()->startOfDay();
        $result = new ReminderRunResult;

        // 1. Ubah status dokumen yang sudah melewati masa berlaku menjadi Expired
        $result->expiredMarkedCount = $this->markExpiredDocuments($today, $dryRun);

        // 2. Ambil seluruh dokumen aktif (Approved) yang memiliki masa berlaku di masa depan atau hari ini
        // Dokumen berstatus Archived (Pasif) dikecualikan agar tidak lagi menerima pengingat kedaluwarsa.
        $documents = Document::query()
            ->where('has_expiry', true)
            ->whereNotNull('expired_date')
            ->where('expired_date', '>=', $today->toDateString())
            ->where('status', DocumentStatus::Approved)
            ->whereNull('deleted_at')
            ->with(['unit', 'uploader'])
            ->get();

        $result->scannedCount = $documents->count();

        foreach ($documents as $document) {
            $this->processDocument($document, $today, $dryRun, $result);
        }

        return $result;
    }

    /**
     * Menandai dokumen yang tanggal kedaluwarsanya telah lewat menjadi status Expired.
     */
    public function markExpiredDocuments(CarbonInterface $today, bool $dryRun = false): int
    {
        $expiredDocs = Document::query()
            ->where('has_expiry', true)
            ->whereNotNull('expired_date')
            ->where('expired_date', '<', $today->toDateString())
            ->where('status', DocumentStatus::Approved)
            ->whereNull('deleted_at')
            ->get();

        $count = $expiredDocs->count();

        if ($dryRun || $count === 0) {
            return $count;
        }

        foreach ($expiredDocs as $doc) {
            $doc->update(['status' => DocumentStatus::Expired]);

            activity('document_audit')
                ->performedOn($doc)
                ->log("Masa berlaku naskah dinas telah berakhir pada {$doc->expired_date?->format('d/m/Y')}. Status otomatis dialihkan menjadi Expired.");
        }

        return $count;
    }

    /**
     * Memproses pengiriman pengingat untuk satu dokumen kepada seluruh penerimanya.
     */
    protected function processDocument(
        Document $document,
        CarbonInterface $today,
        bool $dryRun,
        ReminderRunResult $result
    ): void {
        $daysRemaining = (int) $today->diffInDays($document->expired_date?->copy()->startOfDay(), false);

        /** @var Collection<int, User> $recipients */
        $recipients = $document->getReminderRecipientUsers();

        if ($recipients->isEmpty()) {
            $result->skippedCount++;

            return;
        }

        foreach ($recipients as $recipient) {
            $recipientEmail = strtolower(trim((string) $recipient->email));

            if (empty($recipientEmail)) {
                $result->skippedCount++;

                continue;
            }

            $dueInterval = $this->resolveDueIntervalForRecipient($document, $recipientEmail, $today);

            if (! $dueInterval) {
                $result->skippedCount++;

                continue;
            }

            $snapshot = $document->expired_date?->toDateString() ?? $today->toDateString();

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
                // Pengiriman notifikasi langsung (mail + database)
                $recipient->notifyNow(new DocumentExpiryReminderNotification($document, $dueInterval, $daysRemaining));

                ReminderLog::create([
                    'document_id' => $document->id,
                    'recipient_user_id' => $recipient->id,
                    'recipient_email' => $recipientEmail,
                    'interval_type' => $dueInterval,
                    'sent_date' => $today->toDateString(),
                    'expired_date_snapshot' => $snapshot,
                    'sent_at' => now(),
                    'is_success' => true,
                    'error_message' => null,
                ]);

                activity('document_audit')
                    ->performedOn($document)
                    ->log("Mengirimkan notifikasi pengingat masa berlaku [{$dueInterval->getLabel()}] kepada {$recipient->name} ({$recipientEmail}).");

                $result->sentCount++;
                $result->details[] = [
                    'document_id' => $document->id,
                    'document_number' => $document->document_number,
                    'recipient_email' => $recipientEmail,
                    'interval' => $dueInterval->value,
                    'days_remaining' => $daysRemaining,
                    'status' => 'sent',
                ];
            } catch (\Throwable $e) {
                $errorMessage = $e->getMessage();

                ReminderLog::create([
                    'document_id' => $document->id,
                    'recipient_user_id' => $recipient->id,
                    'recipient_email' => $recipientEmail,
                    'interval_type' => $dueInterval,
                    'sent_date' => $today->toDateString(),
                    'expired_date_snapshot' => $snapshot,
                    'sent_at' => now(),
                    'is_success' => false,
                    'error_message' => $errorMessage,
                ]);

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
            }
        }
    }

    /**
     * Menentukan interval pengingat yang jatuh tempo untuk penerima tertentu pada tanggal hari ini.
     * Menerapkan mode susulan (catch-up) dengan memprioritaskan interval yang paling mendesak.
     */
    public function resolveDueIntervalForRecipient(
        Document $document,
        string $recipientEmail,
        CarbonInterface $today
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

        $snapshot = $document->expired_date->toDateString();

        foreach ($intervals as $interval) {
            if ($daysRemaining <= $interval->daysBeforeExpiry()) {
                // Jika interval ini belum pernah berhasil dikirim pada masa berlaku ini
                if (! $this->hasSent($document->id, $recipientEmail, $interval, $snapshot)) {
                    return $interval;
                }

                // Jika interval ini sudah pernah terkirim, hentikan agar tidak mengirim interval yang lebih lama
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
        return ReminderLog::query()
            ->where('document_id', $documentId)
            ->where('recipient_email', $recipientEmail)
            ->where('interval_type', $interval)
            ->whereDate('expired_date_snapshot', $expiredDateSnapshot)
            ->where('is_success', true)
            ->exists();
    }
}
