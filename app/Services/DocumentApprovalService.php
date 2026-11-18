<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\ApprovalStatus;
use App\Enums\DocumentStatus;
use App\Enums\VisibilityLevel;
use App\Models\Document;
use App\Models\DocumentApproval;
use App\Models\DocumentCategory;
use App\Models\User;
use Filament\Actions\Action as FilamentAction;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
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

            $flows = $category->approvalFlows()->orderBy('step_order')->get();

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

            foreach ($flows as $flow) {
                DocumentApproval::create([
                    'document_id' => $lockedDoc->id,
                    'revision_round' => 1,
                    'step_order' => $flow->step_order,
                    'approver_role' => $flow->approver_role,
                    'target_unit_id' => $this->resolveTargetUnitId($lockedDoc, $flow->approver_role),
                    'status' => ($flow->step_order === 1) ? ApprovalStatus::Pending : ApprovalStatus::Waiting,
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

        // Pimpinan wajib memiliki izin sistem document.approve
        if (! $user->can('document.approve') && ! $user->hasRole('super_admin')) {
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

        // Periksa apakah pengguna memiliki role yang dipersyaratkan oleh tahapan approval saat ini
        if (! $user->hasRole($currentApproval->approver_role)) {
            return false;
        }

        // Pengguna dengan hak bypass (Rektorat, Warek, LPM) dapat menyetujui dokumen lintas seluruh unit
        if ($user->can('document.bypass_unit_scope')) {
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

            // TAHAPAN TERAKHIR: Pengesahan Penuh (Final Approval)
            // Terbitkan nomor surat terlebih dahulu agar tersedia saat event pengarsipan otomatis dokumen terganti
            if (empty($lockedDoc->document_number)) {
                $this->numberingService->generateNumber($lockedDoc);
            }

            $lockedDoc->update([
                'status' => DocumentStatus::Approved,
                'approved_at' => now(),
                'final_approved_by' => $user->id,
                'signer_name_snapshot' => $user->name,
                'signer_position_snapshot' => $positionSnapshot,
            ]);

            $isFinalApproval = true;

            activity('document_audit')
                ->performedOn($lockedDoc)
                ->causedBy($user)
                ->event('approved')
                ->withProperties([
                    'step_order' => $currentApproval->step_order,
                    'approver_role' => $currentApproval->approver_role,
                    'is_final' => true,
                    'document_number' => $lockedDoc->document_number,
                    'note' => $note,
                ])
                ->log("Mengesahkan dokumen secara final (Nomor {$lockedDoc->document_number} resmi diterbitkan)".($note ? " dengan catatan: \"{$note}\"" : ''));

            return true;
        });

        // Eksekusi stamping & notifikasi di luar transaksi DB agar proses file/LibreOffice tidak menahan row lock
        if ($result && $lockedDoc) {
            if ($isFinalApproval) {
                $this->stampingService->stamp($lockedDoc);
                $this->notifyUploader(
                    $lockedDoc,
                    "Dokumen Disahkan: {$lockedDoc->document_number}",
                    "Naskah '{$lockedDoc->title}' telah disahkan secara final oleh {$user->name} dengan nomor resmi {$lockedDoc->document_number}.",
                    'success'
                );
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

            $flows = $category->approvalFlows()->orderBy('step_order')->get();

            // Jika kategori diubah menjadi tanpa persetujuan bertingkat, langsung sahkan agar tidak terjebak di Revisi
            if (! $category->requires_approval || $flows->isEmpty()) {
                $this->finalizeWithoutApprovalInTransaction($lockedDoc, $actor ?? auth()->user());
                $autoApprovedDoc = $lockedDoc;

                return;
            }

            $currentMaxRound = (int) ($lockedDoc->approvals()->max('revision_round') ?? 1);
            $newRound = $currentMaxRound + 1;

            foreach ($flows as $flow) {
                DocumentApproval::create([
                    'document_id' => $lockedDoc->id,
                    'revision_round' => $newRound,
                    'step_order' => $flow->step_order,
                    'approver_role' => $flow->approver_role,
                    'target_unit_id' => $this->resolveTargetUnitId($lockedDoc, $flow->approver_role),
                    'status' => ($flow->step_order === 1) ? ApprovalStatus::Pending : ApprovalStatus::Waiting,
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
        } elseif ($firstStepInfo !== null) {
            $this->notifyApproversForStep($firstStepInfo['document'], $firstStepInfo['step'], $firstStepInfo['role']);
        }

        $document->refresh();
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

        if (! $user->can('document.approve')) {
            return $query->whereRaw('1 = 0');
        }

        $roles = $user->roles->pluck('name')->all();

        if (empty($roles)) {
            return $query->whereRaw('1 = 0');
        }

        if (! $user->can('document.view_confidential')) {
            $query->where(function (Builder $confSub) use ($user): void {
                $confSub->where('documents.visibility', '!=', VisibilityLevel::Confidential)
                    ->orWhere('documents.uploaded_by', $user->id);
            });
        }

        return $query->whereHas('approvals', function (Builder $q) use ($user, $roles): void {
            $q->whereColumn('document_approvals.step_order', 'documents.current_step')
                ->whereRaw('document_approvals.revision_round = (SELECT MAX(da_max.revision_round) FROM document_approvals da_max WHERE da_max.document_id = documents.id)')
                ->where('document_approvals.status', ApprovalStatus::Pending)
                ->whereIn('document_approvals.approver_role', $roles)
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
        if (in_array($approverRole, ['rektor', 'warek', 'super_admin'], true)) {
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

        $lockedDoc->update([
            'status' => DocumentStatus::Approved,
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
            ->event('approved')
            ->withProperties([
                'is_auto_approved' => true,
                'document_number' => $lockedDoc->document_number,
            ])
            ->log("Disahkan langsung oleh sistem (Kategori tanpa antrean persetujuan, Nomor {$lockedDoc->document_number} diterbitkan)");
    }

    private function resolvePositionSnapshot(User $user): string
    {
        return $user->position_title ?: ($user->roles->first()?->name ?: 'Pimpinan');
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
            ->whereHas('roles', static fn (Builder $q) => $q->where('name', $approverRole))
            ->get();

        return $candidates->filter(function (User $candidate) use ($document): bool {
            if (! $candidate->can('document.approve') && ! $candidate->hasRole('super_admin')) {
                return false;
            }

            if ($document->visibility === VisibilityLevel::Confidential
                && ! $candidate->can('document.view_confidential')
                && $candidate->id !== $document->uploaded_by) {
                return false;
            }

            if ($candidate->hasRole('super_admin') || $candidate->can('document.bypass_unit_scope')) {
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
}
