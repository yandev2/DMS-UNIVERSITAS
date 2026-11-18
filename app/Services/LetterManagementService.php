<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\IncomingLetterStatus;
use App\Enums\IncomingRecipientStatus;
use App\Enums\OutgoingLetterStatus;
use App\Models\IncomingLetter;
use App\Models\IncomingLetterRecipient;
use App\Models\OutgoingLetter;
use App\Models\Unit;
use App\Models\User;
use Carbon\CarbonInterface;
use Filament\Actions\Action;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use PhpOffice\PhpWord\TemplateProcessor;
use Throwable;

class LetterManagementService
{
    public function __construct(
        protected DocumentNamingService $namingService,
        protected DocumentNumberingService $numberingService,
        protected DocumentStampingService $stampingService
    ) {}

    /**
     * Membangkitkan nomor agenda surat masuk secara atomik per Unit Tujuan dan Tahun.
     * Format: {seq}/UNIVBI.{unit}/SM/{roman_month}/{year}
     */
    public function generateIncomingAgendaNumber(IncomingLetter $letter): string
    {
        if (filled($letter->agenda_number)) {
            return $letter->agenda_number;
        }

        return DB::transaction(function () use ($letter): string {
            $unit = $letter->unit ?? Unit::findOrFail($letter->unit_id);
            $refDate = $letter->received_date ?? now();
            $year = (int) $refDate->format('Y');
            $month = (int) $refDate->format('m');

            Unit::query()->where('id', $unit->id)->lockForUpdate()->first();

            $maxSeq = (int) (IncomingLetter::withTrashed()
                ->where('unit_id', $unit->id)
                ->where('sequence_year', $year)
                ->max('sequence_number') ?? 0);

            $nextSeq = $maxSeq + 1;
            $unitCode = $this->namingService->resolveUnitCode($unit);
            $pattern = '{seq}/UNIVBI.{unit}/SM/{roman_month}/{year}';

            do {
                $agendaNumber = $this->numberingService->formatNumber(
                    pattern: $pattern,
                    sequence: $nextSeq,
                    unitCode: $unitCode,
                    categoryCode: 'SM',
                    month: $month,
                    year: $year
                );

                $exists = IncomingLetter::withTrashed()
                    ->where('agenda_number', $agendaNumber)
                    ->where('id', '!=', $letter->id)
                    ->exists();

                if ($exists) {
                    $nextSeq++;
                }
            } while ($exists);

            $letter->update([
                'sequence_year' => $year,
                'sequence_number' => $nextSeq,
                'agenda_number' => $agendaNumber,
            ]);

            return $agendaNumber;
        });
    }

    /**
     * Pratinjau nomor agenda surat masuk berikutnya untuk unit tertentu.
     */
    public function previewNextIncomingNumber(Unit|int $unit, ?int $year = null, ?int $month = null): string
    {
        $unitModel = $unit instanceof Unit ? $unit : Unit::find($unit);
        if (! $unitModel) {
            return '-';
        }

        $year ??= (int) date('Y');
        $month ??= (int) date('m');

        $maxSeq = (int) (IncomingLetter::withTrashed()
            ->where('unit_id', $unitModel->id)
            ->where('sequence_year', $year)
            ->max('sequence_number') ?? 0);

        $nextSeq = $maxSeq + 1;
        $unitCode = $this->namingService->resolveUnitCode($unitModel);

        return $this->numberingService->formatNumber(
            pattern: '{seq}/UNIVBI.{unit}/SM/{roman_month}/{year}',
            sequence: $nextSeq,
            unitCode: $unitCode,
            categoryCode: 'SM',
            month: $month,
            year: $year
        );
    }

    /**
     * Membangkitkan nomor surat keluar secara atomik per Unit Pengaju dan Tahun.
     * Format: {seq}/UNIVBI.{unit}/SKL/{roman_month}/{year}
     */
    public function generateOutgoingLetterNumber(OutgoingLetter $letter): string
    {
        if (filled($letter->letter_number)) {
            return $letter->letter_number;
        }

        return DB::transaction(function () use ($letter): string {
            $unit = $letter->unit ?? Unit::findOrFail($letter->unit_id);
            $refDate = $letter->letter_date ?? now();
            $year = (int) $refDate->format('Y');
            $month = (int) $refDate->format('m');

            Unit::query()->where('id', $unit->id)->lockForUpdate()->first();

            $maxSeq = (int) (OutgoingLetter::withTrashed()
                ->where('unit_id', $unit->id)
                ->where('sequence_year', $year)
                ->max('sequence_number') ?? 0);

            $nextSeq = $maxSeq + 1;
            $unitCode = $this->namingService->resolveUnitCode($unit);
            $pattern = '{seq}/UNIVBI.{unit}/SKL/{roman_month}/{year}';

            do {
                $letterNumber = $this->numberingService->formatNumber(
                    pattern: $pattern,
                    sequence: $nextSeq,
                    unitCode: $unitCode,
                    categoryCode: 'SKL',
                    month: $month,
                    year: $year
                );

                $exists = OutgoingLetter::withTrashed()
                    ->where('letter_number', $letterNumber)
                    ->where('id', '!=', $letter->id)
                    ->exists();

                if ($exists) {
                    $nextSeq++;
                }
            } while ($exists);

            $letter->update([
                'sequence_year' => $year,
                'sequence_number' => $nextSeq,
                'letter_number' => $letterNumber,
            ]);

            return $letterNumber;
        });
    }

    /**
     * Pratinjau nomor surat keluar berikutnya untuk unit tertentu.
     */
    public function previewNextOutgoingNumber(Unit|int $unit, ?int $year = null, ?int $month = null): string
    {
        $unitModel = $unit instanceof Unit ? $unit : Unit::find($unit);
        if (! $unitModel) {
            return '-';
        }

        $year ??= (int) date('Y');
        $month ??= (int) date('m');

        $maxSeq = (int) (OutgoingLetter::withTrashed()
            ->where('unit_id', $unitModel->id)
            ->where('sequence_year', $year)
            ->max('sequence_number') ?? 0);

        $nextSeq = $maxSeq + 1;
        $unitCode = $this->namingService->resolveUnitCode($unitModel);

        return $this->numberingService->formatNumber(
            pattern: '{seq}/UNIVBI.{unit}/SKL/{roman_month}/{year}',
            sequence: $nextSeq,
            unitCode: $unitCode,
            categoryCode: 'SKL',
            month: $month,
            year: $year
        );
    }

    /**
     * Inisialisasi penomoran, stempel pojok kanan atas, dan distribusi berjenjang Surat Masuk.
     */
    public function initializeIncomingDistribution(IncomingLetter $letter): void
    {
        DB::transaction(function () use ($letter): void {
            if (empty($letter->agenda_number)) {
                $this->generateIncomingAgendaNumber($letter);
            }

            $letter->refresh();

            if (filled($letter->original_file_path)) {
                $stampedPath = $this->stampIncomingPdf($letter);
                if ($stampedPath) {
                    $letter->update(['stamped_file_path' => $stampedPath]);
                }
            }

            $recipients = $letter->recipients()->with('recipient')->orderBy('step_order')->orderBy('id')->get();
            $totalSteps = $recipients->count();

            if ($totalSteps === 0) {
                $letter->update([
                    'current_step' => 0,
                    'total_steps' => 0,
                    'status' => IncomingLetterStatus::Completed,
                ]);

                return;
            }

            $stepIndex = 1;
            $firstRecipientRecord = null;

            foreach ($recipients as $recipientRecord) {
                $user = $recipientRecord->recipient;
                $positionSnapshot = $recipientRecord->recipient_position_snapshot
                    ?: ($user?->position_title ?: ($user?->roles->first()?->name ?: 'Pejabat Kampus'));

                $isFirst = ($stepIndex === 1);

                $recipientRecord->update([
                    'step_order' => $stepIndex,
                    'recipient_position_snapshot' => $positionSnapshot,
                    'status' => $isFirst ? IncomingRecipientStatus::Unread : IncomingRecipientStatus::Waiting,
                    'notified_at' => $isFirst ? now() : null,
                ]);

                if ($isFirst) {
                    $firstRecipientRecord = $recipientRecord->fresh(['recipient']);
                }

                $stepIndex++;
            }

            $letter->update([
                'current_step' => 1,
                'total_steps' => $totalSteps,
                'status' => IncomingLetterStatus::InProgress,
            ]);

            if ($firstRecipientRecord && $firstRecipientRecord->recipient) {
                $this->sendIncomingTurnNotification($letter, $firstRecipientRecord);
            }
        });
    }

    /**
     * Mencatat waktu pertama kali penerima membuka halaman detail surat masuk.
     */
    public function recordOpened(IncomingLetter $letter, ?User $user): void
    {
        if (! $user) {
            return;
        }

        $recipientRecord = $letter->getRecipientRecordFor($user);

        if ($recipientRecord && is_null($recipientRecord->opened_at)) {
            $recipientRecord->update([
                'opened_at' => now(),
            ]);
        }
    }

    /**
     * Menandai surat masuk telah dibaca oleh pejabat penerima aktif, lalu meneruskan secara berjenjang ke penerima berikutnya.
     *
     * @throws AuthorizationException
     */
    public function markAsRead(IncomingLetter $letter, User $user, ?string $dispositionNote = null): bool
    {
        if (! $letter->canUserMarkAsRead($user)) {
            throw new AuthorizationException('Anda tidak berada pada giliran baca aktif untuk surat masuk ini.');
        }

        return DB::transaction(function () use ($letter, $user, $dispositionNote): bool {
            $activeRecipient = $letter->getRecipientRecordFor($user);
            if (! $activeRecipient) {
                return false;
            }

            $cleanNote = filled($dispositionNote) ? trim($dispositionNote) : null;

            $activeRecipient->update([
                'status' => IncomingRecipientStatus::Read,
                'opened_at' => $activeRecipient->opened_at ?? now(),
                'read_at' => now(),
                'disposition_note' => $cleanNote,
            ]);

            $nextStep = (int) $activeRecipient->step_order + 1;
            $nextRecipient = $letter->recipients()
                ->with('recipient')
                ->where('step_order', $nextStep)
                ->first();

            if ($nextRecipient) {
                $nextRecipient->update([
                    'status' => IncomingRecipientStatus::Unread,
                    'notified_at' => now(),
                ]);

                $letter->update([
                    'current_step' => $nextStep,
                    'status' => IncomingLetterStatus::InProgress,
                ]);

                if ($nextRecipient->recipient) {
                    $this->sendIncomingTurnNotification($letter->fresh(), $nextRecipient, $user, $cleanNote);
                }

                return true;
            }

            // Seluruh penerima dalam antrean telah membaca surat
            $letter->update([
                'status' => IncomingLetterStatus::Completed,
            ]);

            if ($letter->registrar) {
                FilamentNotification::make()
                    ->title("Surat Masuk Selesai Dibaca: {$letter->agenda_number}")
                    ->body("{$user->name} telah menandai surat masuk '{$letter->subject}' sebagai sudah dibaca. Seluruh alur distribusi selesai.")
                    ->status('success')
                    ->actions([
                        Action::make('view')
                            ->label('Lihat Lacak Surat')
                            ->url(url('/admin/incoming-letters/'.$letter->id)),
                    ])
                    ->sendToDatabase($letter->registrar);
            }

            return true;
        });
    }

    /**
     * Memproses surat keluar baru yang dibuat oleh Unit atau Humas.
     */
    public function processNewOutgoingLetter(OutgoingLetter $letter, User $actor): void
    {
        if ($actor->can('letter.manage_humas')) {
            $this->approveOutgoingLetter($letter, $actor, 'Diterbitkan dan dinomori langsung oleh Bagian Humas.');

            return;
        }

        $letter->update([
            'status' => OutgoingLetterStatus::PendingHumas,
        ]);

        $this->notifyHumasForOutgoingReview($letter);
    }

    /**
     * Humas menyetujui surat keluar, menerbitkan nomor resmi otomatis, dan membubuhkan stempel ke berkas.
     *
     * @throws AuthorizationException
     */
    public function approveOutgoingLetter(OutgoingLetter $letter, User $humasUser, ?string $note = null): void
    {
        if (! $humasUser->can('letter.manage_humas')) {
            throw new AuthorizationException('Hanya Bagian Humas yang berwenang menerbitkan nomor dan mengesahkan surat keluar.');
        }

        DB::transaction(function () use ($letter, $humasUser, $note): void {
            if (empty($letter->letter_number)) {
                $this->generateOutgoingLetterNumber($letter);
            }

            $letter->refresh();

            if (filled($letter->original_file_path)) {
                $stampedPath = $this->stampOutgoingFile($letter);
                if ($stampedPath) {
                    $letter->update(['stamped_file_path' => $stampedPath]);
                }
            }

            $letter->update([
                'status' => OutgoingLetterStatus::Published,
                'reviewed_by' => $humasUser->id,
                'reviewed_at' => now(),
                'review_note' => filled($note) ? trim($note) : 'Nomor surat resmi telah diterbitkan oleh Humas.',
            ]);

            if ($letter->creator && (int) $letter->created_by !== (int) $humasUser->id) {
                FilamentNotification::make()
                    ->title("Nomor Surat Keluar Terbit: {$letter->letter_number}")
                    ->body("Surat keluar '{$letter->subject}' tujuan {$letter->destination_organization} telah dinomori oleh Humas dan siap diunduh.")
                    ->status('success')
                    ->actions([
                        Action::make('view')
                            ->label('Unduh Berkas')
                            ->url(url('/admin/outgoing-letters/'.$letter->id)),
                    ])
                    ->sendToDatabase($letter->creator);
            }
        });
    }

    /**
     * Humas meminta revisi atas draf surat keluar dari unit.
     *
     * @throws AuthorizationException
     */
    public function requestRevisionOutgoing(OutgoingLetter $letter, User $humasUser, string $note): void
    {
        if (! $humasUser->can('letter.manage_humas')) {
            throw new AuthorizationException('Hanya Bagian Humas yang berwenang meminta revisi surat keluar.');
        }

        if (blank($note)) {
            throw new InvalidArgumentException('Catatan revisi wajib diisi.');
        }

        $letter->update([
            'status' => OutgoingLetterStatus::NeedsRevision,
            'reviewed_by' => $humasUser->id,
            'reviewed_at' => now(),
            'review_note' => trim($note),
        ]);

        if ($letter->creator) {
            FilamentNotification::make()
                ->title('Permintaan Revisi Surat Keluar')
                ->body("Humas meminta perbaikan pada surat keluar '{$letter->subject}': \"{$note}\"")
                ->status('warning')
                ->actions([
                    Action::make('view')
                        ->label('Perbaiki Surat')
                        ->url(url('/admin/outgoing-letters/'.$letter->id)),
                ])
                ->sendToDatabase($letter->creator);
        }
    }

    /**
     * Humas menolak pengajuan surat keluar.
     *
     * @throws AuthorizationException
     */
    public function rejectOutgoing(OutgoingLetter $letter, User $humasUser, string $note): void
    {
        if (! $humasUser->can('letter.manage_humas')) {
            throw new AuthorizationException('Hanya Bagian Humas yang berwenang menolak surat keluar.');
        }

        if (blank($note)) {
            throw new InvalidArgumentException('Alasan penolakan wajib diisi.');
        }

        $letter->update([
            'status' => OutgoingLetterStatus::Rejected,
            'reviewed_by' => $humasUser->id,
            'reviewed_at' => now(),
            'review_note' => trim($note),
        ]);

        if ($letter->creator) {
            FilamentNotification::make()
                ->title('Pengajuan Surat Keluar Ditolak')
                ->body("Pengajuan surat keluar '{$letter->subject}' ditolak oleh Humas: \"{$note}\"")
                ->status('danger')
                ->actions([
                    Action::make('view')
                        ->label('Lihat Detail')
                        ->url(url('/admin/outgoing-letters/'.$letter->id)),
                ])
                ->sendToDatabase($letter->creator);
        }
    }

    /**
     * Pengaju mengirim ulang surat keluar setelah diperbaiki ke Humas.
     */
    public function resubmitOutgoing(OutgoingLetter $letter, User $actor, ?string $newFilePath = null, ?string $newFileName = null): void
    {
        $updates = [
            'status' => OutgoingLetterStatus::PendingHumas,
        ];

        if (filled($newFilePath)) {
            $updates['original_file_path'] = $newFilePath;
            $updates['stamped_file_path'] = null;
            $updates['file_name'] = $newFileName ?: basename($newFilePath);
        }

        $letter->update($updates);

        $this->notifyHumasForOutgoingReview($letter);
    }

    /**
     * Engine Stempel PDF Footer Pojok Kanan Bawah untuk Surat Masuk:
     * Menampilkan QR Code Verifikasi + Nomor Agenda + Tanggal Diterima pada seluruh halaman.
     */
    public function stampIncomingPdf(IncomingLetter $letter): ?string
    {
        if (empty($letter->original_file_path)) {
            return null;
        }

        $ext = strtolower(pathinfo($letter->original_file_path, PATHINFO_EXTENSION));
        if ($ext !== 'pdf') {
            return null;
        }

        $number = $letter->agenda_number ?: 'AGENDA-HUMAS';
        $numberLabel = str_starts_with(strtolower($number), 'agenda')
            ? $number
            : "Agenda: {$number}";
        $dateStr = $this->formatIndonesianDate($letter->received_date ?? now());
        $verifyUrl = $letter->getVerificationUrl();

        return $this->stampPdfBottomRightFooter(
            originalPath: $letter->original_file_path,
            numberLabel: $numberLabel,
            dateLine: "Diterima: {$dateStr} | e-Arsip UNIVBI",
            verifyUrl: $verifyUrl
        );
    }

    /**
     * Engine Stempel untuk Berkas Surat Keluar (.pdf maupun .docx).
     */
    public function stampOutgoingFile(OutgoingLetter $letter): ?string
    {
        if (empty($letter->original_file_path)) {
            return null;
        }

        $ext = strtolower(pathinfo($letter->original_file_path, PATHINFO_EXTENSION));
        $number = $letter->letter_number ?: 'SURAT-KELUAR';
        $dateStr = $this->formatIndonesianDate($letter->letter_date ?? now());
        $verifyUrl = $letter->getVerificationUrl();

        if ($ext === 'pdf') {
            $numberLabel = str_starts_with(strtolower($number), 'no')
                ? $number
                : "No. {$number}";

            return $this->stampPdfBottomRightFooter(
                originalPath: $letter->original_file_path,
                numberLabel: $numberLabel,
                dateLine: "Tanggal: {$dateStr} | e-Arsip UNIVBI",
                verifyUrl: $verifyUrl
            );
        }

        if ($ext === 'docx') {
            return $this->stampOutgoingWord(
                originalPath: $letter->original_file_path,
                numberText: $number,
                dateText: $dateStr,
                verifyUrl: $verifyUrl,
                uuid: $letter->uuid
            );
        }

        return null;
    }

    /**
     * Membubuhi QR Code Verifikasi, Nomor Resmi, dan Tanggal di Footer Pojok Kanan Bawah seluruh halaman PDF.
     */
    public function stampPdfBottomRightFooter(
        string $originalPath,
        string $numberLabel,
        string $dateLine,
        string $verifyUrl
    ): ?string {
        $storage = Storage::disk('private');

        if (! $storage->exists($originalPath)) {
            Log::warning("Berkas PDF tidak ditemukan untuk stempel footer kanan bawah: {$originalPath}");

            return null;
        }

        $tempOriginal = tempnam(sys_get_temp_dir(), 'ltr_orig_').'.pdf';
        $tempStamped = tempnam(sys_get_temp_dir(), 'ltr_stamp_').'.pdf';

        try {
            file_put_contents($tempOriginal, $storage->get($originalPath));

            $this->stampingService->stampPdfFooterOverlay(
                sourceLocalPath: $tempOriginal,
                targetLocalPath: $tempStamped,
                numberLabel: $numberLabel,
                dateLine: $dateLine,
                verifyUrl: $verifyUrl
            );

            $dir = dirname($originalPath);
            $base = basename($originalPath);
            $stampedRelativePath = "{$dir}/stamped-{$base}";

            $storage->put($stampedRelativePath, (string) file_get_contents($tempStamped));

            return $stampedRelativePath;
        } catch (Throwable $e) {
            Log::warning("Gagal membubuhkan stempel footer kanan bawah pada PDF [{$originalPath}]: ".$e->getMessage());

            return null;
        } finally {
            if (file_exists($tempOriginal)) {
                @unlink($tempOriginal);
            }
            if (file_exists($tempStamped)) {
                @unlink($tempStamped);
            }
        }
    }

    /**
     * Membubuhkan nomor surat resmi, tanggal, dan QR Code pada berkas Word (.docx) Surat Keluar.
     */
    protected function stampOutgoingWord(
        string $originalPath,
        string $numberText,
        string $dateText,
        string $verifyUrl,
        string $uuid
    ): ?string {
        $storage = Storage::disk('private');

        if (! $storage->exists($originalPath)) {
            return null;
        }

        $tempOriginal = tempnam(sys_get_temp_dir(), 'out_docx_orig_').'.docx';
        $tempStamped = tempnam(sys_get_temp_dir(), 'out_docx_stamp_').'.docx';
        $qrPath = null;

        try {
            file_put_contents($tempOriginal, $storage->get($originalPath));
            // Margin 0 pada PNG Word agar modul QR mengisi 100% kotak tanpa padding putih internal berlebih
            $qrPath = $this->stampingService->generateQrCodePng($verifyUrl, 360, 0);

            // 1. Ganti flag penomoran & tanggal via TemplateProcessor (${...}) tanpa merusak struktur XML asli dokumen
            $templateProcessor = new TemplateProcessor($tempOriginal);
            $variables = $templateProcessor->getVariables();
            $hasPlaceholders = false;

            foreach ($variables as $v) {
                $upper = strtoupper($v);
                if (in_array($upper, ['NOMOR_SURAT', 'NOMOR', 'NO_SURAT', 'LETTER_NUMBER', 'DOCUMENT_NUMBER', 'NO'], true)) {
                    $templateProcessor->setValue($v, $numberText);
                    $hasPlaceholders = true;
                } elseif (in_array($upper, ['TANGGAL_SAH', 'TANGGAL', 'TANGGAL_SURAT', 'ISSUED_DATE', 'DATE'], true)) {
                    $templateProcessor->setValue($v, $dateText);
                    $hasPlaceholders = true;
                } elseif ($upper === 'UUID') {
                    $templateProcessor->setValue($v, $uuid);
                    $hasPlaceholders = true;
                } elseif ($upper === 'VERIFY_URL') {
                    $templateProcessor->setValue($v, $verifyUrl);
                    $hasPlaceholders = true;
                }
            }

            if ($hasPlaceholders) {
                $templateProcessor->saveAs($tempStamped);
            } else {
                copy($tempOriginal, $tempStamped);
            }

            // 2. Selalu sematkan QR DrawingML (wp:anchor relativeFrom="page") tepat di pojok kanan bawah kertas
            $zip = new \ZipArchive;
            if ($zip->open($tempStamped) === true) {
                $this->stampingService->injectWordFooterQrDrawing($zip, $qrPath, 52.0, 8.0);
                $zip->close();
            }

            // 3. Buka kembali arsip terflush lalu gabungkan run XML terpecah & ganti seluruh flag/pola penomoran
            if ($zip->open($tempStamped) === true) {
                $escapedNumber = htmlspecialchars($numberText, ENT_XML1, 'UTF-8');
                $escapedDate = htmlspecialchars($dateText, ENT_XML1, 'UTF-8');
                $escapedUuid = htmlspecialchars($uuid, ENT_XML1, 'UTF-8');
                $escapedVerifyUrl = htmlspecialchars($verifyUrl, ENT_XML1, 'UTF-8');

                $searchPatterns = [
                    '{seq}/UNIVBI.{unit}/SKL/{roman_month}/{year}',
                    '{seq}/UNIVBI.{unit}/SKL/{month}/{year}',
                    '{seq}/UNIVBI.{unit}/{cat}/{roman_month}/{year}',
                    '{seq}/UNIVBI.{unit}/{cat}/{month}/{year}',
                ];

                for ($i = 0; $i < $zip->numFiles; $i++) {
                    $entryName = $zip->getNameIndex($i);
                    if ($entryName && str_ends_with($entryName, '.xml')) {
                        $entryXml = $zip->getFromIndex($i);
                        if ($entryXml) {
                            $isDocumentOrHeader = ($entryName === 'word/document.xml') || preg_match('#word/(header|footer)\d+\.xml#', $entryName);
                            if ($isDocumentOrHeader) {
                                $entryXml = $this->stampingService->coalesceWordPlaceholderRuns($entryXml);

                                foreach ($searchPatterns as $sp) {
                                    if (str_contains($entryXml, $sp)) {
                                        $entryXml = str_replace($sp, $escapedNumber, $entryXml);
                                    }
                                }

                                // Regex pola dinamis {seq}/... jika format disesuaikan user pada naskah Word
                                $entryXml = (string) preg_replace_callback('/\{seq\}\/[^\s<>]+(?=\s|<|\")/i', function () use ($escapedNumber): string {
                                    return $escapedNumber;
                                }, $entryXml);

                                $entryXml = (string) preg_replace(
                                    '/\{\s*(nomor_surat|no_surat|letter_number|document_number|nomor|no)\s*\}|\$\{\s*(nomor_surat|no_surat|letter_number|document_number|nomor|no)\s*\}/i',
                                    $escapedNumber,
                                    $entryXml
                                );
                                $entryXml = (string) preg_replace(
                                    '/\{\s*(tanggal_sah|tgl_sah|tanggal_surat|issued_date|tanggal|date)\s*\}|\$\{\s*(tanggal_sah|tgl_sah|tanggal_surat|issued_date|tanggal|date)\s*\}/i',
                                    $escapedDate,
                                    $entryXml
                                );
                                $entryXml = (string) preg_replace('/\$?\{\s*uuid\s*\}/i', $escapedUuid, $entryXml);
                                $entryXml = (string) preg_replace('/\$?\{\s*verify_url\s*\}/i', $escapedVerifyUrl, $entryXml);
                            }

                            $sanitizedXml = preg_replace('/&(?!(amp|lt|gt|quot|apos);)/', '&amp;', (string) $entryXml);
                            $zip->addFromString($entryName, (string) $sanitizedXml);
                        }
                    }
                }
                $zip->close();
            }

            $dir = dirname($originalPath);
            $base = basename($originalPath);
            $stampedRelativePath = "{$dir}/stamped-{$base}";

            $storage->put($stampedRelativePath, (string) file_get_contents($tempStamped));

            return $stampedRelativePath;
        } catch (Throwable $e) {
            Log::warning("Gagal membubuhkan stempel pada Word Surat Keluar [{$originalPath}]: ".$e->getMessage());

            return null;
        } finally {
            if (file_exists($tempOriginal)) {
                @unlink($tempOriginal);
            }
            if (file_exists($tempStamped)) {
                @unlink($tempStamped);
            }
            if ($qrPath && file_exists($qrPath)) {
                @unlink($qrPath);
            }
        }
    }

    /**
     * Format tanggal lengkap Bahasa Indonesia (contoh: 14 November 2026).
     */
    public function formatIndonesianDate(?CarbonInterface $date = null): string
    {
        $date ??= now();
        $months = [
            1 => 'Januari',
            2 => 'Februari',
            3 => 'Maret',
            4 => 'April',
            5 => 'Mei',
            6 => 'Juni',
            7 => 'Juli',
            8 => 'Agustus',
            9 => 'September',
            10 => 'Oktober',
            11 => 'November',
            12 => 'Desember',
        ];

        $day = $date->format('d');
        $monthName = $months[(int) $date->format('m')] ?? $date->format('F');
        $year = $date->format('Y');

        return "{$day} {$monthName} {$year}";
    }

    protected function sendIncomingTurnNotification(
        IncomingLetter $letter,
        IncomingLetterRecipient $recipientRecord,
        ?User $previousReader = null,
        ?string $previousNote = null
    ): void {
        $recipientUser = $recipientRecord->recipient;
        if (! $recipientUser) {
            return;
        }

        $body = "Surat dari {$letter->sender_name} perihal \"{$letter->subject}\" menunggu tinjauan Anda (Tahap {$recipientRecord->step_order}).";
        if ($previousReader) {
            $body = "Diteruskan setelah dibaca oleh {$previousReader->name}"
                .($previousNote ? " dengan catatan: \"{$previousNote}\"." : '.')
                ." Perihal: \"{$letter->subject}\".";
        } elseif (filled($recipientRecord->instruction_note)) {
            $body .= " Pengantar Humas: \"{$recipientRecord->instruction_note}\".";
        }

        FilamentNotification::make()
            ->title("Surat Masuk Baru: {$letter->agenda_number}")
            ->body($body)
            ->status('info')
            ->actions([
                Action::make('view')
                    ->label('Buka & Tandai Dibaca')
                    ->url(url('/admin/incoming-letters/'.$letter->id)),
            ])
            ->sendToDatabase($recipientUser);
    }

    protected function notifyHumasForOutgoingReview(OutgoingLetter $letter): void
    {
        $humasUsers = User::permission('letter.manage_humas')->where('is_active', true)->get();
        if ($humasUsers->isEmpty()) {
            return;
        }

        $unitName = $letter->unit?->name ?? 'Unit Kerja';

        FilamentNotification::make()
            ->title('Antrean Penomoran Surat Keluar')
            ->body("Unit {$unitName} mengajukan surat keluar perihal \"{$letter->subject}\" tujuan {$letter->destination_organization} untuk ditinjau & diberi nomor.")
            ->status('warning')
            ->actions([
                Action::make('view')
                    ->label('Tinjau & Terbitkan Nomor')
                    ->url(url('/admin/outgoing-letters/'.$letter->id)),
            ])
            ->sendToDatabase($humasUsers);
    }

    protected function sanitizePdfText(string $text): string
    {
        $converted = @iconv('UTF-8', 'ISO-8859-1//TRANSLIT', $text);

        return $converted !== false ? $converted : $text;
    }
}
