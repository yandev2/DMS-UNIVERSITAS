@props([
    'document',
])

@php
    use App\Enums\ApprovalStatus;

    $approvalsByRound = $document->approvals()
        ->with(['approver', 'targetUnit'])
        ->orderBy('revision_round')
        ->orderBy('step_order')
        ->get()
        ->groupBy('revision_round');
@endphp

@include('filament.components.document-timeline-styles')

<div style="display: flex; flex-direction: column; gap: 1.25rem;">
    @if($approvalsByRound->isEmpty())
        <div class="doc-empty-state" style="border-radius: 0.5rem; border: 1px solid var(--doc-border); background-color: var(--doc-surface); padding: 2.5rem 1.5rem; text-align: center; box-shadow: var(--doc-shadow-sm); color: var(--doc-text-primary);">
            <div class="doc-empty-icon" style="margin: 0 auto; display: flex; width: 3rem; height: 3rem; align-items: center; justify-content: center; border-radius: 9999px; background-color: var(--doc-surface-muted); color: var(--doc-text-muted); border: 1px solid var(--doc-border);">
                <x-heroicon-o-document-check class="doc-icon-md" style="width: 1.5rem; height: 1.5rem;" />
            </div>
            <h4 class="doc-empty-title" style="margin-top: 0.75rem; font-size: 0.9375rem; font-weight: 700; color: var(--doc-text-primary);">
                Tidak Ada Antrean Persetujuan Bertingkat
            </h4>
            <p class="doc-empty-text" style="margin-top: 0.375rem; font-size: 0.8125rem; color: var(--doc-text-muted); max-width: 28rem; margin-left: auto; margin-right: auto; line-height: 1.375rem;">
                @if($document->is_manual_number)
                    Dokumen ini diarsipkan menggunakan nomor surat fisik terdahulu (arsip manual), sehingga tidak melalui tahapan evaluasi estafet.
                @else
                    Kategori dokumen ini diatur untuk langsung disahkan secara otomatis tanpa alur persetujuan pejabat berjenjang.
                @endif
            </p>
        </div>
    @else
        @foreach($approvalsByRound as $round => $approvals)
            @php
                $hasRevision = $approvals->contains(fn ($a) => $a->status === ApprovalStatus::Revision);
                $hasRejected = $approvals->contains(fn ($a) => $a->status === ApprovalStatus::Rejected);
                $isAllApproved = $approvals->every(fn ($a) => $a->status === ApprovalStatus::Approved);
            @endphp

            <div class="doc-card" style="border-radius: 0.5rem; border: 1px solid var(--doc-border); background-color: var(--doc-surface); box-shadow: var(--doc-shadow-sm); overflow: hidden; padding: 0; margin-bottom: 0;">
                {{-- Round Header --}}
                <div class="doc-table-round-header" style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 0.75rem; padding: 0.875rem 1.25rem; background-color: var(--doc-surface-muted); border-bottom: 1px solid var(--doc-border);">
                    <div style="display: flex; align-items: center; gap: 0.625rem;">
                        <span style="display: flex; width: 1.75rem; height: 1.75rem; align-items: center; justify-content: center; border-radius: 0.375rem; background-color: var(--doc-badge-info-bg); color: var(--doc-badge-info-text); font-weight: 700; font-size: 0.75rem; border: 1px solid var(--doc-badge-info-border);">
                            #{{ $round }}
                        </span>
                        <div>
                            <h4 style="font-size: 0.875rem; font-weight: 700; color: var(--doc-text-primary); margin: 0; line-height: 1.25rem;">
                                Putaran Evaluasi Ke-{{ $round }}
                            </h4>
                            <p style="font-size: 0.75rem; color: var(--doc-text-muted); margin: 0.125rem 0 0 0;">
                                Berisi {{ $approvals->count() }} tahapan persetujuan pada putaran ini
                            </p>
                        </div>
                    </div>

                    <div>
                        @if($hasRevision)
                            <span class="doc-badge doc-badge-warning" style="background-color: var(--doc-badge-warning-bg); color: var(--doc-badge-warning-text); border: 1px solid var(--doc-badge-warning-border);">
                                <x-heroicon-s-arrow-path class="doc-icon-xs" />
                                Memerlukan Perbaikan
                            </span>
                        @elseif($hasRejected)
                            <span class="doc-badge doc-badge-danger" style="background-color: var(--doc-badge-danger-bg); color: var(--doc-badge-danger-text); border: 1px solid var(--doc-badge-danger-border);">
                                <x-heroicon-s-x-circle class="doc-icon-xs" />
                                Ditolak
                            </span>
                        @elseif($isAllApproved)
                            <span class="doc-badge doc-badge-success" style="background-color: var(--doc-badge-success-bg); color: var(--doc-badge-success-text); border: 1px solid var(--doc-badge-success-border);">
                                <x-heroicon-s-check-circle class="doc-icon-xs" />
                                Disetujui Lengkap
                            </span>
                        @else
                            <span class="doc-badge doc-badge-info" style="background-color: var(--doc-badge-info-bg); color: var(--doc-badge-info-text); border: 1px solid var(--doc-badge-info-border);">
                                <x-heroicon-s-clock class="doc-icon-xs" />
                                Sedang Berjalan
                            </span>
                        @endif
                    </div>
                </div>

                {{-- Table of Steps in this round --}}
                <div class="doc-table-wrapper" style="overflow-x: auto; width: 100%; border: 0; border-radius: 0;">
                    <table class="doc-table" style="width: 100%; text-align: left; font-size: 0.75rem; border-collapse: collapse;">
                        <thead style="background-color: var(--doc-surface-muted); color: var(--doc-text-muted); font-size: 0.6875rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; border-bottom: 1px solid var(--doc-border);">
                            <tr>
                                <th style="padding: 0.75rem 1rem; width: 4rem; text-align: center;">Tingkat</th>
                                <th style="padding: 0.75rem 1rem;">Peran & Unit Wewenang</th>
                                <th style="padding: 0.75rem 1rem;">Status Keputusan</th>
                                <th style="padding: 0.75rem 1rem;">Pejabat Eksekutor</th>
                                <th style="padding: 0.75rem 1rem;">Tanggal & Waktu</th>
                                <th style="padding: 0.75rem 1rem;">Catatan / Alasan</th>
                            </tr>
                        </thead>
                        <tbody style="font-family: inherit;">
                            @foreach($approvals as $approval)
                                @php
                                    $status = $approval->status;
                                @endphp
                                <tr style="border-bottom: 1px solid var(--doc-border); background-color: var(--doc-surface);">
                                    <td style="padding: 0.875rem 1rem; text-align: center; vertical-align: top; font-weight: 700; color: var(--doc-text-primary);">
                                        {{ $approval->step_order }}
                                    </td>
                                    <td style="padding: 0.875rem 1rem; vertical-align: top;">
                                        <div style="font-weight: 700; color: var(--doc-text-primary);">
                                            {{ str($approval->approver_role)->title()->replace('_', ' ') }}
                                        </div>
                                        <div style="font-size: 0.6875rem; color: var(--doc-text-muted); margin-top: 0.125rem;">
                                            {{ $approval->targetUnit?->name ?? 'Unit Kerja Terkait' }}
                                        </div>
                                    </td>
                                    <td style="padding: 0.875rem 1rem; vertical-align: top; white-space: nowrap;">
                                        <span class="doc-badge
                                            @if($status === ApprovalStatus::Approved) doc-badge-success
                                            @elseif($status === ApprovalStatus::Pending) doc-badge-warning
                                            @elseif($status === ApprovalStatus::Revision) doc-badge-danger
                                            @elseif($status === ApprovalStatus::Rejected) doc-badge-danger
                                            @else doc-badge-gray
                                            @endif
                                        " style="
                                            @if($status === ApprovalStatus::Approved) background-color: var(--doc-badge-success-bg); color: var(--doc-badge-success-text); border: 1px solid var(--doc-badge-success-border);
                                            @elseif($status === ApprovalStatus::Pending) background-color: var(--doc-badge-warning-bg); color: var(--doc-badge-warning-text); border: 1px solid var(--doc-badge-warning-border);
                                            @elseif($status === ApprovalStatus::Revision || $status === ApprovalStatus::Rejected) background-color: var(--doc-badge-danger-bg); color: var(--doc-badge-danger-text); border: 1px solid var(--doc-badge-danger-border);
                                            @else background-color: var(--doc-badge-gray-bg); color: var(--doc-badge-gray-text); border: 1px solid var(--doc-badge-gray-border);
                                            @endif
                                        ">
                                            {{ $status->getLabel() }}
                                        </span>
                                    </td>
                                    <td style="padding: 0.875rem 1rem; vertical-align: top;">
                                        @if($approval->approver)
                                            <div style="font-weight: 700; color: var(--doc-text-primary);">
                                                {{ $approval->approver->name }}
                                            </div>
                                            <div style="font-size: 0.6875rem; color: var(--doc-text-muted);">
                                                {{ $approval->approver_position_snapshot ?? $approval->approver->email }}
                                            </div>
                                        @else
                                            <span style="color: var(--doc-text-muted); font-style: italic;">Belum diproses</span>
                                        @endif
                                    </td>
                                    <td style="padding: 0.875rem 1rem; vertical-align: top; white-space: nowrap; color: var(--doc-text-muted);">
                                        @if($approval->approved_at)
                                            <div style="color: var(--doc-text-primary); font-weight: 600;">
                                                {{ $approval->approved_at->translatedFormat('d M Y') }}
                                            </div>
                                            <div style="font-size: 0.6875rem;">
                                                {{ $approval->approved_at->translatedFormat('H:i') }} WIB
                                            </div>
                                        @else
                                            <span style="font-style: italic;">—</span>
                                        @endif
                                    </td>
                                    <td style="padding: 0.875rem 1rem; vertical-align: top;">
                                        @if($approval->note)
                                            <div style="background-color: var(--doc-surface-muted); border: 1px solid var(--doc-border); border-radius: 0.375rem; padding: 0.5rem 0.75rem; font-style: italic; color: var(--doc-text-primary); max-width: 20rem;">
                                                "{{ $approval->note }}"
                                            </div>
                                        @else
                                            <span style="color: var(--doc-text-muted); font-style: italic;">Tidak ada catatan</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endforeach
    @endif
</div>
