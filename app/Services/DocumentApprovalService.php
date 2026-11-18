<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\ApprovalStatus;
use App\Enums\DocumentStatus;
use App\Models\Document;
use App\Models\DocumentApproval;
use App\Models\DocumentCategory;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class DocumentApprovalService
{
    public function __construct(
        protected DocumentNumberingService $numberingService,
        protected DocumentStampingService $stampingService
    ) {}

    /**
     * Menginisialisasi antrean alur persetujuan bertingkat saat dokumen diajukan.
     */
    public function initializeApprovalFlow(Document $document): void
    {
        // Dokumen dengan nomor manual (arsip fisik lama) tidak memerlukan antrean approval
        if ($document->is_manual_number) {
            return;
        }

        $category = $document->category ?? DocumentCategory::find($document->document_category_id);
        if (! $category) {
            return;
        }

        $flows = $category->approvalFlows()->orderBy('step_order')->get();

        // Jika kategori tidak memerlukan approval atau tidak memiliki aturan tahapan approval
        if (! $category->requires_approval || $flows->isEmpty()) {
            DB::transaction(function () use ($document): void {
                if (empty($document->document_number)) {
                    $this->numberingService->generateNumber($document);
                }

                $document->update([
                    'status' => DocumentStatus::Approved,
                    'current_step' => 0,
                    'total_steps' => 0,
                    'approved_at' => now(),
                    'final_approved_by' => auth()->id() ?: $document->uploaded_by,
                ]);
            });

            $this->stampingService->stamp($document);

            activity('document_audit')
                ->performedOn($document)
                ->causedBy(auth()->user() ?: $document->uploader)
                ->event('approved')
                ->withProperties([
                    'is_auto_approved' => true,
                    'document_number' => $document->document_number,
                ])
                ->log("Disahkan langsung oleh sistem (Kategori tanpa antrean persetujuan, Nomor {$document->document_number} diterbitkan)");

            return;
        }

        $totalSteps = $flows->count();

        DB::transaction(function () use ($document, $flows, $totalSteps): void {
            $document->update([
                'status' => DocumentStatus::Pending,
                'current_step' => 1,
                'total_steps' => $totalSteps,
            ]);

            // Hapus antrean approval putaran lama jika ada (clean slate)
            $document->approvals()->delete();

            foreach ($flows as $flow) {
                DocumentApproval::create([
                    'document_id' => $document->id,
                    'revision_round' => 1,
                    'step_order' => $flow->step_order,
                    'approver_role' => $flow->approver_role,
                    'target_unit_id' => $this->resolveTargetUnitId($document, $flow->approver_role),
                    'status' => ($flow->step_order === 1) ? ApprovalStatus::Pending : ApprovalStatus::Waiting,
                ]);
            }
        });
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
    public function getCurrentApproval(Document $document): ?DocumentApproval
    {
        $maxRound = $document->approvals()->max('revision_round') ?? 1;

        return $document->approvals()
            ->where('revision_round', $maxRound)
            ->where('step_order', $document->current_step)
            ->first();
    }

    /**
     * Menyetujui dokumen pada tahapan yang sedang aktif.
     * Jika merupakan tahapan terakhir, dokumen disahkan, nomor resmi terbit, dan stempel QR dicetak.
     *
     * @throws AuthorizationException
     */
    public function approve(Document $document, User $user, ?string $note = null): bool
    {
        if (! $this->canUserApprove($user, $document)) {
            throw new AuthorizationException('Anda tidak memiliki wewenang untuk menyetujui dokumen ini pada tahapan saat ini.');
        }

        return DB::transaction(function () use ($document, $user, $note): bool {
            $currentApproval = $this->getCurrentApproval($document);
            if (! $currentApproval) {
                return false;
            }

            $positionSnapshot = $user->position_title ?: ($user->roles->first()?->name ?: 'Pimpinan');

            $currentApproval->update([
                'status' => ApprovalStatus::Approved,
                'approved_by' => $user->id,
                'approved_at' => now(),
                'approver_position_snapshot' => $positionSnapshot,
                'note' => $note,
            ]);

            // Jika belum tahapan terakhir: majukan ke tingkat berikutnya
            if ($document->current_step < $document->total_steps) {
                $nextStep = $document->current_step + 1;

                $maxRound = $document->approvals()->max('revision_round') ?? 1;
                $document->approvals()
                    ->where('revision_round', $maxRound)
                    ->where('step_order', $nextStep)
                    ->update([
                        'status' => ApprovalStatus::Pending,
                    ]);

                $document->update([
                    'current_step' => $nextStep,
                    'status' => DocumentStatus::Pending,
                ]);

                activity('document_audit')
                    ->performedOn($document)
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
            if (empty($document->document_number)) {
                $this->numberingService->generateNumber($document);
            }

            $document->update([
                'status' => DocumentStatus::Approved,
                'approved_at' => now(),
                'final_approved_by' => $user->id,
                'signer_name_snapshot' => $user->name,
                'signer_position_snapshot' => $positionSnapshot,
            ]);

            // Eksekusi Stamping PDF / Word otomatis
            $this->stampingService->stamp($document);

            activity('document_audit')
                ->performedOn($document)
                ->causedBy($user)
                ->event('approved')
                ->withProperties([
                    'step_order' => $currentApproval->step_order,
                    'approver_role' => $currentApproval->approver_role,
                    'is_final' => true,
                    'document_number' => $document->document_number,
                    'note' => $note,
                ])
                ->log("Mengesahkan dokumen secara final (Nomor {$document->document_number} resmi diterbitkan)".($note ? " dengan catatan: \"{$note}\"" : ''));

            return true;
        });
    }

    /**
     * Pimpinan meminta revisi kepada staf pengunggah dengan catatan perbaikan wajib.
     *
     * @throws AuthorizationException
     */
    public function requestRevision(Document $document, User $user, string $note): bool
    {
        if (! $this->canUserApprove($user, $document)) {
            throw new AuthorizationException('Anda tidak memiliki wewenang untuk meminta revisi pada dokumen ini.');
        }

        if (blank($note)) {
            throw new InvalidArgumentException('Catatan revisi wajib diisi agar pengunggah mengetahui bagian yang perlu diperbaiki.');
        }

        return DB::transaction(function () use ($document, $user, $note): bool {
            $currentApproval = $this->getCurrentApproval($document);
            if (! $currentApproval) {
                return false;
            }

            $currentApproval->update([
                'status' => ApprovalStatus::Revision,
                'approved_by' => $user->id,
                'approved_at' => now(),
                'approver_position_snapshot' => $user->position_title ?: ($user->roles->first()?->name ?: 'Pemeriksa'),
                'note' => $note,
            ]);

            $document->update([
                'status' => DocumentStatus::Revision,
            ]);

            activity('document_audit')
                ->performedOn($document)
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
    }

    /**
     * Pimpinan menolak dokumen secara resmi dengan alasan penolakan wajib.
     *
     * @throws AuthorizationException
     */
    public function reject(Document $document, User $user, string $note): bool
    {
        if (! $this->canUserApprove($user, $document)) {
            throw new AuthorizationException('Anda tidak memiliki wewenang untuk menolak dokumen ini.');
        }

        if (blank($note)) {
            throw new InvalidArgumentException('Alasan penolakan wajib diisi untuk transparansi keputusan.');
        }

        return DB::transaction(function () use ($document, $user, $note): bool {
            $currentApproval = $this->getCurrentApproval($document);
            if (! $currentApproval) {
                return false;
            }

            $currentApproval->update([
                'status' => ApprovalStatus::Rejected,
                'approved_by' => $user->id,
                'approved_at' => now(),
                'approver_position_snapshot' => $user->position_title ?: ($user->roles->first()?->name ?: 'Pemeriksa'),
                'note' => $note,
            ]);

            $document->update([
                'status' => DocumentStatus::Rejected,
            ]);

            activity('document_audit')
                ->performedOn($document)
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
    }

    /**
     * Staf pengunggah mengajukan kembali dokumen yang telah direvisi ke alur persetujuan.
     * Sistem memulai putaran revisi baru (revision_round + 1) dan mengembalikan antrean ke tingkat 1.
     */
    public function resubmit(Document $document): void
    {
        $category = $document->category ?? DocumentCategory::find($document->document_category_id);
        if (! $category) {
            return;
        }

        $flows = $category->approvalFlows()->orderBy('step_order')->get();
        if ($flows->isEmpty()) {
            return;
        }

        DB::transaction(function () use ($document, $flows): void {
            $currentMaxRound = $document->approvals()->max('revision_round') ?? 1;
            $newRound = $currentMaxRound + 1;

            foreach ($flows as $flow) {
                DocumentApproval::create([
                    'document_id' => $document->id,
                    'revision_round' => $newRound,
                    'step_order' => $flow->step_order,
                    'approver_role' => $flow->approver_role,
                    'target_unit_id' => $this->resolveTargetUnitId($document, $flow->approver_role),
                    'status' => ($flow->step_order === 1) ? ApprovalStatus::Pending : ApprovalStatus::Waiting,
                ]);
            }

            $document->update([
                'status' => DocumentStatus::Pending,
                'current_step' => 1,
            ]);

            activity('document_audit')
                ->performedOn($document)
                ->causedBy(auth()->user() ?: $document->uploader)
                ->event('resubmitted')
                ->withProperties([
                    'revision_round' => $newRound,
                ])
                ->log("Mengajukan kembali naskah dinas ke alur persetujuan tingkat 1 (Putaran Evaluasi Ke-{$newRound})");
        });
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

        $roles = $user->roles->pluck('name')->all();

        return $query->whereHas('approvals', function (Builder $q) use ($user, $roles): void {
            $q->whereColumn('document_approvals.step_order', 'documents.current_step')
                ->where('document_approvals.status', ApprovalStatus::Pending)
                ->whereIn('document_approvals.approver_role', $roles)
                ->where(function (Builder $sub) use ($user): void {
                    if ($user->can('document.bypass_unit_scope')) {
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
}
