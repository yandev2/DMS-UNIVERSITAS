<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\ApprovalStatus;
use App\Enums\DocumentStatus;
use App\Enums\VisibilityLevel;
use App\Models\CategoryApprovalFlow;
use App\Models\Document;
use App\Models\DocumentApproval;
use App\Models\DocumentCategory;
use App\Models\DocumentFile;
use App\Models\User;
use App\Rules\PdfDocumentFileRule;
use Filament\Actions\Action as FilamentAction;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;

class DocumentApprovalService
{
    public function __construct(
        protected DocumentNumberingService $numberingService,
        protected DocumentStampingService $stampingService
    ) {}

    /**
     * Menginisialisasi antrean alur persetujuan bertingkat saat dokumen diajukan.
     *
     * @throws InvalidArgumentException
     */
    public function initializeApprovalFlow(Document $document): void
    {
        // Dokumen dengan nomor manual (arsip fisik lama) tidak memerlukan antrean approval
        if ($document->is_manual_number) {
            return;
        }

        $autoApprovedDoc = null;
        $firstStepInfo = null;

        DB::transaction(function () use ($document, &$autoApprovedDoc, &$firstStepInfo): void {
            $lockedDoc = Document::query()
                ->with(['category', 'unit', 'uploader'])
                ->lockForUpdate()
                ->find($document->id);

            if (! $lockedDoc) {
                return;
            }

            if ($lockedDoc->status !== DocumentStatus::Draft) {
                throw new InvalidArgumentException('Hanya dokumen berstatus Draf yang dapat diajukan ke alur persetujuan awal.');
            }

            $category = $lockedDoc->category ?? DocumentCategory::find($lockedDoc->document_category_id);
            if (! $category) {
                return;
            }

            $flows = $this->resolveApplicableFlows($lockedDoc, $category);

            // Jika kategori tidak memerlukan approval atau tidak memiliki aturan tahapan approval
            if (! $category->requires_approval || $flows->isEmpty()) {
                $this->finalizeWithoutApprovalInTransaction($lockedDoc, auth()->user());
                $autoApprovedDoc = $lockedDoc;

                return;
            }

            $totalSteps = $flows->count();

            $lockedDoc->update([
                'status' => DocumentStatus::Pending,
                'current_step' => 1,
                'total_steps' => $totalSteps,
            ]);

            // Hapus antrean approval putaran lama jika ada (clean slate)
            $lockedDoc->approvals()->delete();

            foreach ($flows as $index => $flow) {
                $effectiveStep = $index + 1;

                DocumentApproval::create([
                    'document_id' => $lockedDoc->id,
                    'revision_round' => 1,
                    'step_order' => $effectiveStep,
                    'approver_role' => $flow->approver_role,
                    'target_unit_id' => $this->resolveTargetUnitId($lockedDoc, $flow->approver_role),
                    'status' => ($effectiveStep === 1) ? ApprovalStatus::Pending : ApprovalStatus::Waiting,
                ]);
            }

            $firstFlow = $flows->first();
            $firstStepInfo = [
                'document' => $lockedDoc,
                'step' => 1,
                'role' => $firstFlow->approver_role,
            ];
        });

        // Stamping & notifikasi dijalankan setelah transaksi commit agar tidak menahan row lock DB
        if ($autoApprovedDoc !== null) {
            $this->stampingService->stamp($autoApprovedDoc);
            if ($autoApprovedDoc->status === DocumentStatus::WaitingSignature) {
                $this->notifyWetSignatureReady($autoApprovedDoc);
            }
        } elseif ($firstStepInfo !== null) {
            $this->notifyApproversForStep($firstStepInfo['document'], $firstStepInfo['step'], $firstStepInfo['role']);
        }

        $document->refresh();
    }

    /**
     * Memeriksa apakah seorang pengguna berwenang menyetujui langkah persetujuan dokumen saat ini.
     */
    public function canUserApprove(User $user, Document $document, bool $useLoadedRelations = false): bool
    {
        if ($document->status !== DocumentStatus::Pending || $document->current_step < 1) {
            return false;
        }

        $currentApproval = ($useLoadedRelations && $document->relationLoaded('approvals'))
            ? $document->getCurrentApproval()
            : $this->getCurrentApproval($document);
        if (! $currentApproval || $currentApproval->status !== ApprovalStatus::Pending) {
            return false;
        }

        // Super Admin memiliki hak override penuh
        if ($user->hasRole('super_admin')) {
            return true;
        }

        // Tahapan khusus Bagian Humas (memeriksa tata naskah/kelengkapan lintas unit)
        $isHumasStep = $currentApproval->approver_role === 'humas'
            && ($user->hasRole('humas') || $user->can('letter.manage_humas'));

        // Pimpinan wajib memiliki izin sistem document.approve (kecuali tahapan khusus Humas)
        if (! $user->can('document.approve') && ! $isHumasStep) {
            return false;
        }

        // Periksa apakah pengguna memiliki role yang dipersyaratkan oleh tahapan approval saat ini
        if (! $isHumasStep && ! $user->hasRole($currentApproval->approver_role)) {
            return false;
        }

        // Humas pada step humas & pengguna dengan hak bypass (Rektorat, Warek, LPM) dapat memproses lintas seluruh unit
        if ($isHumasStep || $user->can('document.bypass_unit_scope')) {
            return true;
        }

        if (! filled($user->unit_id)) {
            return false;
        }

        // Dekan dapat menyetujui dokumen milik fakultasnya sendiri atau seluruh prodi di bawah fakultasnya
        if ($user->hasRole('dekan')) {
            $docUnit = $document->unit;

            return $document->unit_id === $user->unit_id || ($docUnit && $docUnit->parent_id === $user->unit_id);
        }

        // Role tingkat unit (Kaprodi, Kepala Biro, Unit): dokumen harus milik unit pengguna
        return $document->unit_id === $user->unit_id;
    }

    /**
     * Mengambil rekaman DocumentApproval yang sedang aktif menunggu tindakan.
     */
    public function getCurrentApproval(Document $document, bool $lockForUpdate = false): ?DocumentApproval
    {
        $maxRound = $document->approvals()->max('revision_round') ?? 1;

        $query = $document->approvals()
            ->where('revision_round', $maxRound)
            ->where('step_order', $document->current_step);

        if ($lockForUpdate) {
            $query->lockForUpdate();
        }

        return $query->first();
    }

    /**
     * Menyetujui dokumen pada tahapan yang sedang aktif.
     * Jika merupakan tahapan terakhir, dokumen disahkan, nomor resmi terbit, dan stempel QR dicetak.
     *
     * @throws AuthorizationException
     */
    public function approve(Document $document, User $user, ?string $note = null): bool
    {
        $isFinalApproval = false;
        $lockedDoc = null;
        $nextStepRole = null;
        $nextStepNumber = null;

        $result = DB::transaction(function () use ($document, $user, $note, &$isFinalApproval, &$lockedDoc, &$nextStepRole, &$nextStepNumber): bool {
            $lockedDoc = Document::query()
                ->with(['unit', 'category', 'uploader'])
                ->lockForUpdate()
                ->find($document->id);

            if (! $lockedDoc || ! $this->canUserApprove($user, $lockedDoc)) {
                throw new AuthorizationException('Anda tidak memiliki wewenang untuk menyetujui dokumen ini pada tahapan saat ini.');
            }

            $currentApproval = $this->getCurrentApproval($lockedDoc, lockForUpdate: true);
            if (! $currentApproval || $currentApproval->status !== ApprovalStatus::Pending) {
                return false;
            }

            $positionSnapshot = $this->resolvePositionSnapshot($user);

            $currentApproval->update([
                'status' => ApprovalStatus::Approved,
                'approved_by' => $user->id,
                'approved_at' => now(),
                'approver_position_snapshot' => $positionSnapshot,
                'note' => $note,
            ]);

            // Jika belum tahapan terakhir: majukan ke tingkat berikutnya
            if ($lockedDoc->current_step < $lockedDoc->total_steps) {
                $nextStep = $lockedDoc->current_step + 1;
                $maxRound = $currentApproval->revision_round ?? 1;

                $nextApproval = $lockedDoc->approvals()
                    ->where('revision_round', $maxRound)
                    ->where('step_order', $nextStep)
                    ->lockForUpdate()
                    ->first();

                if (! $nextApproval) {
                    throw new InvalidArgumentException("Tahapan persetujuan tingkat {$nextStep} tidak ditemukan pada antrean dokumen ini.");
                }

                $nextApproval->update([
                    'status' => ApprovalStatus::Pending,
                ]);

                $lockedDoc->update([
                    'current_step' => $nextStep,
                    'status' => DocumentStatus::Pending,
                ]);

                $nextStepRole = $nextApproval->approver_role;
                $nextStepNumber = $nextStep;

                activity('document_audit')
                    ->performedOn($lockedDoc)
                    ->causedBy($user)
                    ->event('approved')
                    ->withProperties([
                        'step_order' => $currentApproval->step_order,
                        'approver_role' => $currentApproval->approver_role,
                        'is_final' => false,
                        'note' => $note,
                    ])
                    ->log("Menyetujui naskah dinas pada tingkat {$currentApproval->step_order} ({$currentApproval->approver_role})".($note ? ": \"{$note}\"" : ''));

                return true;
            }

            // TAHAPAN TERAKHIR: Pengesahan Substansi (terbitkan nomor resmi terlebih dahulu)
            if (empty($lockedDoc->document_number)) {
                $this->numberingService->generateNumber($lockedDoc);
            }

            $requiresWetSignature = $this->requiresWetSignature($lockedDoc);
            $targetStatus = $requiresWetSignature
                ? DocumentStatus::WaitingSignature
                : DocumentStatus::Approved;

            $lockedDoc->update([
                'status' => $targetStatus,
                'requires_wet_signature' => $requiresWetSignature,
                'approved_at' => now(),
                'final_approved_by' => $user->id,
                'signer_name_snapshot' => $user->name,
                'signer_position_snapshot' => $positionSnapshot,
            ]);

            $isFinalApproval = true;

            activity('document_audit')
                ->performedOn($lockedDoc)
                ->causedBy($user)
                ->event($requiresWetSignature ? 'waiting_signature' : 'approved')
                ->withProperties([
                    'step_order' => $currentApproval->step_order,
                    'approver_role' => $currentApproval->approver_role,
                    'is_final' => true,
                    'requires_wet_signature' => $requiresWetSignature,
                    'document_number' => $lockedDoc->document_number,
                    'note' => $note,
                ])
                ->log($requiresWetSignature
                    ? "Menyetujui substansi naskah dinas (Nomor {$lockedDoc->document_number} terbit — menunggu TTD basah, cap & unggahan scan final)".($note ? " dengan catatan: \"{$note}\"" : '')
                    : "Mengesahkan dokumen secara final (Nomor {$lockedDoc->document_number} resmi diterbitkan)".($note ? " dengan catatan: \"{$note}\"" : ''));

            return true;
        });

        // Eksekusi stamping & notifikasi di luar transaksi DB agar proses file/LibreOffice tidak menahan row lock
        if ($result && $lockedDoc) {
            if ($isFinalApproval) {
                $this->stampingService->stamp($lockedDoc);

                if ($lockedDoc->status === DocumentStatus::WaitingSignature) {
                    $this->notifyWetSignatureReady($lockedDoc, $user);
                } else {
                    $this->notifyUploader(
                        $lockedDoc,
                        "Dokumen Disahkan: {$lockedDoc->document_number}",
                        "Naskah '{$lockedDoc->title}' telah disahkan secara final oleh {$user->name} dengan nomor resmi {$lockedDoc->document_number}.",
                        'success'
                    );
                }
            } elseif ($nextStepRole !== null && $nextStepNumber !== null) {
                $this->notifyApproversForStep($lockedDoc, $nextStepNumber, $nextStepRole);
            }
        }

        $document->refresh();

        return $result;
    }

    /**
     * Pimpinan meminta revisi kepada staf pengunggah dengan catatan perbaikan wajib.
     *
     * @throws AuthorizationException
     * @throws InvalidArgumentException
     */
    public function requestRevision(Document $document, User $user, string $note): bool
    {
        if (blank($note)) {
            throw new InvalidArgumentException('Catatan revisi wajib diisi agar pengunggah mengetahui bagian yang perlu diperbaiki.');
        }

        $lockedDoc = null;

        $result = DB::transaction(function () use ($document, $user, $note, &$lockedDoc): bool {
            $lockedDoc = Document::query()
                ->with(['unit', 'category', 'uploader'])
                ->lockForUpdate()
                ->find($document->id);

            if (! $lockedDoc || ! $this->canUserApprove($user, $lockedDoc)) {
                throw new AuthorizationException('Anda tidak memiliki wewenang untuk meminta revisi pada dokumen ini.');
            }

            $currentApproval = $this->getCurrentApproval($lockedDoc, lockForUpdate: true);
            if (! $currentApproval || $currentApproval->status !== ApprovalStatus::Pending) {
                return false;
            }

            $currentApproval->update([
                'status' => ApprovalStatus::Revision,
                'approved_by' => $user->id,
                'approved_at' => now(),
                'approver_position_snapshot' => $user->position_title ?: ($user->roles->first()?->name ?: 'Pemeriksa'),
                'note' => $note,
            ]);

            $lockedDoc->update([
                'status' => DocumentStatus::Revision,
            ]);

            activity('document_audit')
                ->performedOn($lockedDoc)
                ->causedBy($user)
                ->event('revision_requested')
                ->withProperties([
                    'step_order' => $currentApproval->step_order,
                    'approver_role' => $currentApproval->approver_role,
                    'note' => $note,
                ])
                ->log("Meminta revisi naskah dinas pada tingkat {$currentApproval->step_order} ({$currentApproval->approver_role}): \"{$note}\"");

            return true;
        });

        if ($result && $lockedDoc) {
            $this->notifyUploader(
                $lockedDoc,
                "Permintaan Revisi: {$lockedDoc->title}",
                "{$user->name} meminta revisi pada naskah '{$lockedDoc->title}': \"{$note}\"",
                'warning'
            );
        }

        $document->refresh();

        return $result;
    }

    /**
     * Pimpinan menolak dokumen secara resmi dengan alasan penolakan wajib.
     *
     * @throws AuthorizationException
     * @throws InvalidArgumentException
     */
    public function reject(Document $document, User $user, string $note): bool
    {
        if (blank($note)) {
            throw new InvalidArgumentException('Alasan penolakan wajib diisi untuk transparansi keputusan.');
        }

        $lockedDoc = null;

        $result = DB::transaction(function () use ($document, $user, $note, &$lockedDoc): bool {
            $lockedDoc = Document::query()
                ->with(['unit', 'category', 'uploader'])
                ->lockForUpdate()
                ->find($document->id);

            if (! $lockedDoc || ! $this->canUserApprove($user, $lockedDoc)) {
                throw new AuthorizationException('Anda tidak memiliki wewenang untuk menolak dokumen ini.');
            }

            $currentApproval = $this->getCurrentApproval($lockedDoc, lockForUpdate: true);
            if (! $currentApproval || $currentApproval->status !== ApprovalStatus::Pending) {
                return false;
            }

            $currentApproval->update([
                'status' => ApprovalStatus::Rejected,
                'approved_by' => $user->id,
                'approved_at' => now(),
                'approver_position_snapshot' => $user->position_title ?: ($user->roles->first()?->name ?: 'Pemeriksa'),
                'note' => $note,
            ]);

            $lockedDoc->update([
                'status' => DocumentStatus::Rejected,
            ]);

            activity('document_audit')
                ->performedOn($lockedDoc)
                ->causedBy($user)
                ->event('rejected')
                ->withProperties([
                    'step_order' => $currentApproval->step_order,
                    'approver_role' => $currentApproval->approver_role,
                    'reason' => $note,
                ])
                ->log("Menolak naskah dinas pada tingkat {$currentApproval->step_order} ({$currentApproval->approver_role}): \"{$note}\"");

            return true;
        });

        if ($result && $lockedDoc) {
            $this->notifyUploader(
                $lockedDoc,
                "Dokumen Ditolak: {$lockedDoc->title}",
                "{$user->name} menolak naskah '{$lockedDoc->title}' dengan alasan: \"{$note}\"",
                'danger'
            );
        }

        $document->refresh();

        return $result;
    }

    /**
     * Staf pengunggah mengajukan kembali dokumen yang telah direvisi ke alur persetujuan.
     * Sistem memulai putaran revisi baru (revision_round + 1) dan mengembalikan antrean ke tingkat 1.
     *
     * @throws AuthorizationException
     * @throws InvalidArgumentException
     */
    public function resubmit(Document $document, ?User $actor = null): void
    {
        if ($actor !== null && ! $actor->can('update', $document)) {
            throw new AuthorizationException('Anda tidak memiliki wewenang untuk mengajukan kembali dokumen ini.');
        }

        $autoApprovedDoc = null;
        $firstStepInfo = null;

        DB::transaction(function () use ($document, $actor, &$autoApprovedDoc, &$firstStepInfo): void {
            $lockedDoc = Document::query()
                ->with(['unit', 'category', 'uploader'])
                ->lockForUpdate()
                ->find($document->id);

            if (! $lockedDoc) {
                return;
            }

            if ($lockedDoc->status !== DocumentStatus::Revision) {
                throw new InvalidArgumentException('Hanya dokumen berstatus Revisi yang dapat diajukan kembali.');
            }

            $category = $lockedDoc->category ?? DocumentCategory::find($lockedDoc->document_category_id);
            if (! $category) {
                throw new InvalidArgumentException('Kategori dokumen tidak ditemukan untuk memulai ulang alur persetujuan.');
            }

            $flows = $this->resolveApplicableFlows($lockedDoc, $category);

            // Jika kategori diubah menjadi tanpa persetujuan bertingkat, langsung sahkan agar tidak terjebak di Revisi
            if (! $category->requires_approval || $flows->isEmpty()) {
                $this->finalizeWithoutApprovalInTransaction($lockedDoc, $actor ?? auth()->user());
                $autoApprovedDoc = $lockedDoc;

                return;
            }

            $currentMaxRound = (int) ($lockedDoc->approvals()->max('revision_round') ?? 1);
            $newRound = $currentMaxRound + 1;

            foreach ($flows as $index => $flow) {
                $effectiveStep = $index + 1;

                DocumentApproval::create([
                    'document_id' => $lockedDoc->id,
                    'revision_round' => $newRound,
                    'step_order' => $effectiveStep,
                    'approver_role' => $flow->approver_role,
                    'target_unit_id' => $this->resolveTargetUnitId($lockedDoc, $flow->approver_role),
                    'status' => ($effectiveStep === 1) ? ApprovalStatus::Pending : ApprovalStatus::Waiting,
                ]);
            }

            $lockedDoc->update([
                'status' => DocumentStatus::Pending,
                'current_step' => 1,
                'total_steps' => $flows->count(),
            ]);

            activity('document_audit')
                ->performedOn($lockedDoc)
                ->causedBy($actor ?: auth()->user() ?: $lockedDoc->uploader)
                ->event('resubmitted')
                ->withProperties([
                    'revision_round' => $newRound,
                    'total_steps' => $flows->count(),
                ])
                ->log("Mengajukan kembali naskah dinas ke alur persetujuan tingkat 1 (Putaran Evaluasi Ke-{$newRound})");

            $firstFlow = $flows->first();
            $firstStepInfo = [
                'document' => $lockedDoc,
                'step' => 1,
                'role' => $firstFlow->approver_role,
            ];
        });

        if ($autoApprovedDoc !== null) {
            $this->stampingService->stamp($autoApprovedDoc);
            if ($autoApprovedDoc->status === DocumentStatus::WaitingSignature) {
                $this->notifyWetSignatureReady($autoApprovedDoc);
            }
        } elseif ($firstStepInfo !== null) {
            $this->notifyApproversForStep($firstStepInfo['document'], $firstStepInfo['step'], $firstStepInfo['role']);
        }

        $document->refresh();
    }

    /**
     * Menentukan apakah pengguna berwenang mengunggah berkas scan PDF final (ber-TTD basah & cap).
     * Fleksibel: dapat dilakukan oleh pengunggah/unit pengusul maupun Bagian Humas/Admin.
     */
    public function canUploadFinalScan(User $user, Document $document): bool
    {
        if ($document->status !== DocumentStatus::WaitingSignature) {
            return false;
        }

        if ($user->hasRole('super_admin') || $user->can('letter.manage_humas') || $user->can('document.bypass_unit_scope')) {
            return true;
        }

        if ((int) $document->uploaded_by === (int) $user->id) {
            return true;
        }

        return filled($user->unit_id)
            && (int) $document->unit_id === (int) $user->unit_id
            && ($user->can('update:document') || $user->can('create:document'));
    }

    /**
     * Mengunggah hasil scan PDF final ber-TTD basah & cap untuk mengunci arsip menjadi Disahkan (Approved).
     *
     * @throws AuthorizationException
     * @throws InvalidArgumentException
     */
    public function uploadFinalSignedScan(
        Document $document,
        User $actor,
        string $storedFilePath,
        ?string $originalFileName = null,
        ?string $note = null
    ): Document {
        PdfDocumentFileRule::assertValidStoredPdf('private', $storedFilePath);

        $storage = Storage::disk('private');
        $fileSize = (int) ($storage->size($storedFilePath) ?: 0);
        $mimeType = $storage->mimeType($storedFilePath) ?: 'application/pdf';
        $localPath = $storage->path($storedFilePath);
        $fileHash = is_file($localPath)
            ? (hash_file('sha256', $localPath) ?: null)
            : hash('sha256', (string) $storage->get($storedFilePath));

        $finalizedDoc = null;

        try {
            $finalizedDoc = DB::transaction(function () use (
                $document,
                $actor,
                $storedFilePath,
                $originalFileName,
                $note,
                $fileSize,
                $mimeType,
                $fileHash
            ): Document {
                $lockedDoc = Document::query()
                    ->with(['unit', 'category', 'uploader', 'files'])
                    ->lockForUpdate()
                    ->find($document->id);

                if (! $lockedDoc) {
                    throw new InvalidArgumentException('Dokumen tidak ditemukan.');
                }

                if ($lockedDoc->status !== DocumentStatus::WaitingSignature) {
                    throw new InvalidArgumentException('Dokumen ini tidak lagi berstatus Menunggu TTD Basah & Cap (kemungkinan sudah difinalisasi oleh pengguna lain).');
                }

                if (! $this->canUploadFinalScan($actor, $lockedDoc)) {
                    throw new AuthorizationException('Anda tidak memiliki wewenang untuk mengunggah berkas scan final pada dokumen ini.');
                }

                $namingService = app(DocumentNamingService::class);
                $standardFileName = $namingService->generateStandardFileName(
                    $lockedDoc->title,
                    $lockedDoc->unit,
                    $lockedDoc->category,
                    'pdf',
                    $lockedDoc->issued_date ? (int) $lockedDoc->issued_date->format('Y') : null
                );

                // Non-aktifkan file draf pra-TTD tanpa menghapus riwayat untuk audit trail
                DocumentFile::query()
                    ->where('document_id', $lockedDoc->id)
                    ->where('is_active', true)
                    ->update([
                        'is_active' => false,
                        'is_primary' => false,
                    ]);

                $nextVersion = ((int) DocumentFile::query()
                    ->where('document_id', $lockedDoc->id)
                    ->max('version')) + 1;

                DocumentFile::create([
                    'document_id' => $lockedDoc->id,
                    'uploaded_by' => $actor->id,
                    'is_primary' => true,
                    'label' => 'Arsip Scan Final (TTD Basah & Cap)',
                    'order_index' => 1,
                    'version' => $nextVersion,
                    'original_file_name' => $originalFileName ?: basename($storedFilePath),
                    'standardized_file_name' => $standardFileName,
                    'original_file_path' => $storedFilePath,
                    'stamped_file_path' => null,
                    'disk' => 'private',
                    'mime_type' => $mimeType,
                    'file_extension' => 'pdf',
                    'file_size' => $fileSize,
                    'file_hash_sha256' => $fileHash,
                    'stamped_file_hash_sha256' => null,
                    'is_active' => true,
                ]);

                $lockedDoc->update([
                    'status' => DocumentStatus::Approved,
                    'final_scan_uploaded_by' => $actor->id,
                    'final_scan_uploaded_at' => now(),
                ]);

                $actorScope = ($actor->can('letter.manage_humas') || $actor->hasRole('humas'))
                    ? 'Bagian Humas'
                    : 'Unit Pengaju';

                activity('document_audit')
                    ->performedOn($lockedDoc)
                    ->causedBy($actor)
                    ->event('final_scan_uploaded')
                    ->withProperties([
                        'document_number' => $lockedDoc->document_number,
                        'file_name' => $standardFileName,
                        'file_hash_sha256' => $fileHash,
                        'version' => $nextVersion,
                        'uploaded_by_scope' => $actorScope,
                        'note' => $note,
                    ])
                    ->log("Mengunggah scan final (TTD Basah & Cap) oleh {$actor->name} [{$actorScope}] — dokumen resmi disahkan ({$lockedDoc->document_number})".(filled($note) ? ": \"{$note}\"" : ''));

                return $lockedDoc;
            });
        } catch (\Throwable $e) {
            $alreadyReferenced = DocumentFile::query()
                ->where('original_file_path', $storedFilePath)
                ->exists();

            if (! $alreadyReferenced && $storage->exists($storedFilePath)) {
                $storage->delete($storedFilePath);
            }

            throw $e;
        }

        $this->notifyCounterpartOnFinalScanUploaded($finalizedDoc, $actor);
        $document->refresh();

        return $finalizedDoc->fresh();
    }

    /**
     * Memeriksa apakah dokumen berstatus Menunggu TTD Basah boleh dikembalikan ke Revisi
     * jika terjadi kesalahan cetak atau pembatalan penandatanganan fisik.
     */
    public function canReturnWaitingSignatureToRevision(User $user, Document $document): bool
    {
        if ($document->status !== DocumentStatus::WaitingSignature) {
            return false;
        }

        return $user->hasRole('super_admin')
            || $user->can('letter.manage_humas')
            || $user->can('document.approve');
    }

    /**
     * Mengembalikan dokumen Menunggu TTD Basah ke status Perlu Revisi jika terjadi pembatalan/kesalahan cetak.
     *
     * @throws AuthorizationException
     * @throws InvalidArgumentException
     */
    public function returnWaitingSignatureToRevision(Document $document, User $actor, string $reason): Document
    {
        if (blank($reason)) {
            throw new InvalidArgumentException('Alasan pengembalian ke revisi wajib diisi.');
        }

        $lockedDoc = DB::transaction(function () use ($document, $actor, $reason): Document {
            $lockedDoc = Document::query()
                ->with(['uploader', 'unit'])
                ->lockForUpdate()
                ->find($document->id);

            if (! $lockedDoc || ! $this->canReturnWaitingSignatureToRevision($actor, $lockedDoc)) {
                throw new AuthorizationException('Anda tidak memiliki wewenang mengembalikan dokumen ini ke tahap revisi.');
            }

            $lockedDoc->update([
                'status' => DocumentStatus::Revision,
            ]);

            activity('document_audit')
                ->performedOn($lockedDoc)
                ->causedBy($actor)
                ->event('revision_requested')
                ->withProperties([
                    'from_status' => DocumentStatus::WaitingSignature->value,
                    'document_number' => $lockedDoc->document_number,
                    'reason' => trim($reason),
                ])
                ->log("Mengembalikan dokumen dari tahap TTD Basah ke Perlu Revisi: \"{$reason}\"");

            return $lockedDoc;
        });

        $this->notifyUploader(
            $lockedDoc,
            "Dikembalikan ke Revisi: {$lockedDoc->title}",
            "{$actor->name} mengembalikan naskah '{$lockedDoc->title}' dari tahap TTD basah ke status Perlu Revisi: \"{$reason}\"",
            'warning'
        );

        $document->refresh();

        return $lockedDoc->fresh();
    }

    /**
     * Membangun query builder dokumen yang sedang menunggu persetujuan dari pengguna yang login.
     * Digunakan untuk tab antrean khusus pimpinan di Filament.
     */
    public function getPendingApprovalsQueryForUser(User $user): Builder
    {
        $query = Document::query()
            ->where('status', DocumentStatus::Pending)
            ->where('current_step', '>', 0);

        if ($user->hasRole('super_admin')) {
            return $query;
        }

        $canApprove = $user->can('document.approve');
        $canHumasReview = $user->can('letter.manage_humas') || $user->hasRole('humas');

        if (! $canApprove && ! $canHumasReview) {
            return $query->whereRaw('1 = 0');
        }

        $roles = $user->roles->pluck('name')->all();

        if (! $canHumasReview && empty($roles)) {
            return $query->whereRaw('1 = 0');
        }

        if (! $user->can('document.view_confidential')) {
            $query->where(function (Builder $confSub) use ($user): void {
                $confSub->where('documents.visibility', '!=', VisibilityLevel::Confidential)
                    ->orWhere('documents.uploaded_by', $user->id);
            });
        }

        return $query->whereHas('approvals', function (Builder $q) use ($user, $roles, $canApprove, $canHumasReview): void {
            $q->whereColumn('document_approvals.step_order', 'documents.current_step')
                ->whereRaw('document_approvals.revision_round = (SELECT MAX(da_max.revision_round) FROM document_approvals da_max WHERE da_max.document_id = documents.id)')
                ->where('document_approvals.status', ApprovalStatus::Pending)
                ->where(function (Builder $roleBranch) use ($user, $roles, $canApprove, $canHumasReview): void {
                    if ($canHumasReview) {
                        $roleBranch->where('document_approvals.approver_role', 'humas');
                    }

                    if ($canApprove && ! empty($roles)) {
                        $method = $canHumasReview ? 'orWhere' : 'where';
                        $roleBranch->{$method}(function (Builder $stdBranch) use ($user, $roles): void {
                            $stdBranch->whereIn('document_approvals.approver_role', $roles)
                                ->where(function (Builder $sub) use ($user): void {
                                    if ($user->can('document.bypass_unit_scope')) {
                                        return;
                                    }

                                    if (! filled($user->unit_id)) {
                                        $sub->whereRaw('1 = 0');

                                        return;
                                    }

                                    if ($user->hasRole('dekan')) {
                                        $sub->where('documents.unit_id', $user->unit_id)
                                            ->orWhereIn('documents.unit_id', function ($unitSub) use ($user): void {
                                                $unitSub->select('id')->from('units')->where('parent_id', $user->unit_id);
                                            });

                                        return;
                                    }

                                    $sub->where('documents.unit_id', $user->unit_id);
                                });
                        });
                    }
                });
        });
    }

    /**
     * Menghitung jumlah dokumen yang sedang menunggu persetujuan pengguna yang login.
     */
    public function countPendingApprovalsForUser(User $user): int
    {
        return $this->getPendingApprovalsQueryForUser($user)->count();
    }

    /**
     * Resolusi unit target approval berdasarkan role pejabat penelaah.
     */
    protected function resolveTargetUnitId(Document $document, string $approverRole): ?int
    {
        if (in_array($approverRole, ['rektor', 'warek', 'humas', 'ketua_spmi', 'super_admin'], true)) {
            return null;
        }

        if ($approverRole === 'dekan') {
            $unit = $document->unit;

            return ($unit && $unit->parent_id) ? $unit->parent_id : $document->unit_id;
        }

        return $document->unit_id;
    }

    /**
     * Mengesahkan dokumen secara langsung di dalam transaksi aktif untuk kategori tanpa persetujuan bertingkat.
     */
    private function finalizeWithoutApprovalInTransaction(Document $lockedDoc, ?User $actor): void
    {
        if (empty($lockedDoc->document_number)) {
            $this->numberingService->generateNumber($lockedDoc);
        }

        $signer = $actor ?: $lockedDoc->uploader;
        $positionSnapshot = $signer ? $this->resolvePositionSnapshot($signer) : 'Sistem Kampus';
        $requiresWetSignature = $this->requiresWetSignature($lockedDoc);
        $targetStatus = $requiresWetSignature
            ? DocumentStatus::WaitingSignature
            : DocumentStatus::Approved;

        $lockedDoc->update([
            'status' => $targetStatus,
            'requires_wet_signature' => $requiresWetSignature,
            'current_step' => 0,
            'total_steps' => 0,
            'approved_at' => now(),
            'final_approved_by' => $signer?->id ?: $lockedDoc->uploaded_by,
            'signer_name_snapshot' => $signer?->name ?: 'Sistem Kampus',
            'signer_position_snapshot' => $positionSnapshot,
        ]);

        activity('document_audit')
            ->performedOn($lockedDoc)
            ->causedBy($signer)
            ->event($requiresWetSignature ? 'waiting_signature' : 'approved')
            ->withProperties([
                'is_auto_approved' => true,
                'requires_wet_signature' => $requiresWetSignature,
                'document_number' => $lockedDoc->document_number,
            ])
            ->log($requiresWetSignature
                ? "Nomor {$lockedDoc->document_number} diterbitkan (Kategori tanpa antrean persetujuan — menunggu TTD basah, cap & unggahan scan final)"
                : "Disahkan langsung oleh sistem (Kategori tanpa antrean persetujuan, Nomor {$lockedDoc->document_number} diterbitkan)");
    }

    private function requiresWetSignature(Document $document): bool
    {
        return (bool) ($document->requires_wet_signature || $document->category?->requires_wet_signature);
    }

    private function resolvePositionSnapshot(User $user): string
    {
        return $user->position_title ?: ($user->roles->first()?->name ?: 'Pimpinan');
    }

    /**
     * Mengirimkan notifikasi ke pengunggah & Humas saat nomor terbit dan dokumen siap dicetak untuk TTD basah & cap.
     */
    private function notifyWetSignatureReady(Document $document, ?User $lastApprover = null): void
    {
        $approverName = $lastApprover?->name ?? 'Pimpinan';
        $number = $document->document_number ?: '-';

        $this->notifyUploader(
            $document,
            "Nomor Terbit ({$number}) — Siap TTD Basah & Cap",
            "Substansi naskah '{$document->title}' telah disetujui oleh {$approverName}. Silakan unduh berkas bernomor, cetak untuk TTD basah & cap Humas, lalu unggah hasil scan PDF final.",
            'info'
        );

        try {
            $humasUsers = User::permission('letter.manage_humas')
                ->where('is_active', true)
                ->get()
                ->filter(fn (User $u): bool => (int) $u->id !== (int) ($document->uploaded_by ?? 0));

            if ($humasUsers->isEmpty()) {
                return;
            }

            $unitName = $document->unit?->name ?? 'Unit Kerja';
            $docUrl = url('/admin/documents/'.$document->id);

            foreach ($humasUsers as $humasUser) {
                FilamentNotification::make()
                    ->title("Siap Cap & Scan Final: {$number}")
                    ->body("Naskah '{$document->title}' ({$unitName}) telah disetujui dan bernomor resmi. Menunggu pengarsipan scan PDF ber-TTD basah & cap (dapat diunggah unit atau Humas).")
                    ->status('info')
                    ->actions([
                        FilamentAction::make('view')
                            ->label('Buka & Unggah Scan')
                            ->url($docUrl),
                    ])
                    ->sendToDatabase($humasUser);
            }
        } catch (\Throwable $e) {
            Log::warning("Gagal mengirim notifikasi siap TTD basah dokumen #{$document->id} ke Humas: {$e->getMessage()}");
        }
    }

    /**
     * Memberitahu pihak terkait (Pengaju atau Humas) ketika scan PDF final ber-TTD basah & cap telah diarsipkan.
     */
    private function notifyCounterpartOnFinalScanUploaded(Document $document, User $actor): void
    {
        $number = $document->document_number ?: '-';

        if ((int) $actor->id !== (int) ($document->uploaded_by ?? 0)) {
            $this->notifyUploader(
                $document,
                "Arsip Scan Final Selesai: {$number}",
                "Berkas scan ber-TTD basah & cap untuk naskah '{$document->title}' telah diunggah oleh {$actor->name}. Dokumen kini resmi Disahkan.",
                'success'
            );

            return;
        }

        try {
            $humasUsers = User::permission('letter.manage_humas')
                ->where('is_active', true)
                ->get()
                ->filter(fn (User $u): bool => (int) $u->id !== (int) $actor->id);

            $docUrl = url('/admin/documents/'.$document->id);

            foreach ($humasUsers as $humasUser) {
                FilamentNotification::make()
                    ->title("Arsip Scan Final Diunggah Unit: {$number}")
                    ->body("{$actor->name} telah mengunggah hasil scan PDF ber-TTD basah & cap untuk '{$document->title}'. Dokumen kini resmi Disahkan.")
                    ->status('success')
                    ->actions([
                        FilamentAction::make('view')
                            ->label('Lihat Arsip')
                            ->url($docUrl),
                    ])
                    ->sendToDatabase($humasUser);
            }
        } catch (\Throwable $e) {
            Log::warning("Gagal mengirim notifikasi arsip scan final dokumen #{$document->id} ke Humas: {$e->getMessage()}");
        }
    }

    /**
     * Mengirimkan notifikasi lonceng panel kepada pejabat yang berwenang pada tahapan aktif.
     */
    private function notifyApproversForStep(Document $document, int $stepOrder, string $approverRole): void
    {
        try {
            $approvers = $this->resolveApproversForStep($document, $approverRole);
            if ($approvers->isEmpty()) {
                return;
            }

            $roleLabel = str($approverRole)->title()->replace('_', ' ')->toString();
            $unitLabel = $document->unit?->name ?: 'Unit Kampus';
            $docUrl = url('/admin/documents/'.$document->id);

            foreach ($approvers as $approver) {
                FilamentNotification::make()
                    ->title("Menunggu Persetujuan Tingkat {$stepOrder} ({$roleLabel})")
                    ->body("Naskah '{$document->title}' dari {$unitLabel} menunggu tinjauan dan keputusan Anda.")
                    ->status('warning')
                    ->actions([
                        FilamentAction::make('view')
                            ->label('Tinjau Naskah')
                            ->url($docUrl),
                    ])
                    ->sendToDatabase($approver);
            }
        } catch (\Throwable $e) {
            Log::warning("Gagal mengirim notifikasi approval dokumen #{$document->id}: {$e->getMessage()}");
        }
    }

    /**
     * Mengirimkan notifikasi keputusan akhir (Disahkan / Revisi / Ditolak) ke pengunggah dokumen.
     */
    private function notifyUploader(Document $document, string $title, string $body, string $status): void
    {
        try {
            $uploader = $document->uploader;
            if (! $uploader || ! $uploader->is_active) {
                return;
            }

            $docUrl = url('/admin/documents/'.$document->id);

            FilamentNotification::make()
                ->title($title)
                ->body($body)
                ->status($status)
                ->actions([
                    FilamentAction::make('view')
                        ->label('Buka Naskah')
                        ->url($docUrl),
                ])
                ->sendToDatabase($uploader);
        } catch (\Throwable $e) {
            Log::warning("Gagal mengirim notifikasi status dokumen #{$document->id} ke pengunggah: {$e->getMessage()}");
        }
    }

    /**
     * @return Collection<int, User>
     */
    private function resolveApproversForStep(Document $document, string $approverRole): Collection
    {
        $candidates = User::query()
            ->where('is_active', true)
            ->where(function (Builder $q) use ($approverRole): void {
                $q->whereHas('roles', static fn (Builder $rq) => $rq->where('name', $approverRole));

                if ($approverRole === 'humas') {
                    $q->orWhereHas('permissions', static fn (Builder $pq) => $pq->where('name', 'letter.manage_humas'))
                        ->orWhereHas('roles.permissions', static fn (Builder $rpq) => $rpq->where('name', 'letter.manage_humas'));
                }
            })
            ->get();

        return $candidates->filter(function (User $candidate) use ($document, $approverRole): bool {
            $isHumasStep = $approverRole === 'humas'
                && ($candidate->hasRole('humas') || $candidate->can('letter.manage_humas'));

            if (! $candidate->can('document.approve') && ! $candidate->hasRole('super_admin') && ! $isHumasStep) {
                return false;
            }

            if ($document->visibility === VisibilityLevel::Confidential
                && ! $candidate->can('document.view_confidential')
                && $candidate->id !== $document->uploaded_by) {
                return false;
            }

            if ($isHumasStep || $candidate->hasRole('super_admin') || $candidate->can('document.bypass_unit_scope')) {
                return true;
            }

            if (! filled($candidate->unit_id)) {
                return false;
            }

            if ($candidate->hasRole('dekan')) {
                $docUnit = $document->unit;

                return $document->unit_id === $candidate->unit_id || ($docUnit && $docUnit->parent_id === $candidate->unit_id);
            }

            return $document->unit_id === $candidate->unit_id;
        })->values();
    }

    /**
     * Menyaring tahapan persetujuan kategori dan melewati tahapan opsional (is_mandatory = false)
     * apabila unit pengunggah tidak memiliki pejabat dengan role tersebut.
     *
     * @return Collection<int, CategoryApprovalFlow>
     */
    private function resolveApplicableFlows(Document $document, DocumentCategory $category): Collection
    {
        $flows = $category->approvalFlows()->orderBy('step_order')->get();

        return $flows
            ->filter(function (CategoryApprovalFlow $flow) use ($document): bool {
                if ($flow->is_mandatory) {
                    return true;
                }

                return $this->resolveApproversForStep($document, $flow->approver_role)->isNotEmpty();
            })
            ->values();
    }
}
