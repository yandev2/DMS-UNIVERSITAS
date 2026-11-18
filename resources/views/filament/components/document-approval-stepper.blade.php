@props([
    'document',
])

@php
    use App\Enums\ApprovalStatus;
    use App\Enums\DocumentStatus;

    $docStatus = $document->status;
    $isManual = (bool) $document->is_manual_number;
    $requiresApproval = (bool) ($document->category?->requires_approval ?? false);
    $totalSteps = (int) $document->total_steps;

    // Ambil approvals untuk putaran aktif (terakhir)
    $maxRound = $document->approvals()->max('revision_round') ?? 1;
    $activeApprovals = $document->approvals()
        ->where('revision_round', $maxRound)
        ->with(['approver', 'targetUnit'])
        ->orderBy('step_order')
        ->get();

    // Dokumen telah disahkan secara resmi jika berstatus Approved, Diarsipkan, atau telah berstempel/bernomor naskah
    $isApprovedOrArchived = in_array($docStatus, [DocumentStatus::Approved, DocumentStatus::Archived], true)
        || $document->approved_at !== null
        || filled($document->document_number);

    // Catatan revisi atau penolakan terakhir jika ada
    $latestActionWithNote = $document->approvals()
        ->whereNotNull('note')
        ->whereIn('status', [ApprovalStatus::Revision, ApprovalStatus::Rejected, ApprovalStatus::Approved])
        ->latest('updated_at')
        ->first();
@endphp

@include('filament.components.document-timeline-styles')

<div class="doc-card" style="background-color: var(--doc-surface); border: 1px solid var(--doc-border); border-radius: 0.5rem; padding: 1.25rem 1.5rem; margin-bottom: 1.25rem; box-shadow: var(--doc-shadow-sm); color: var(--doc-text-primary);">
    {{-- Header Status & Info Dokumen --}}
    <div class="doc-card-header" style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 0.75rem; padding-bottom: 1rem; border-bottom: 1px solid var(--doc-border);">
        <div style="display: flex; align-items: center; gap: 0.75rem;">
            <div class="doc-card-title-icon" style="width: 2.25rem; height: 2.25rem; border-radius: 0.375rem; background-color: var(--doc-badge-info-bg); color: var(--doc-badge-info-solid); border: 1px solid var(--doc-badge-info-border); display: inline-flex; align-items: center; justify-content: center; flex-shrink: 0;">
                <x-heroicon-o-paper-airplane class="doc-icon-md" style="width: 1.25rem; height: 1.25rem;" />
            </div>
            <div>
                <h3 class="doc-card-title" style="font-size: 0.9375rem; font-weight: 700; color: var(--doc-text-primary); margin: 0; line-height: 1.25rem; letter-spacing: -0.01em;">
                    Pelacakan Alur & Status Pengesahan Naskah Dinas
                </h3>
                <p class="doc-card-subtitle" style="font-size: 0.75rem; color: var(--doc-text-muted); margin: 0.25rem 0 0 0;">
                    Transparansi alur persetujuan surat dari pengajuan staf hingga penomoran & stempel resmi.
                </p>
            </div>
        </div>

        <div class="doc-badge-group" style="display: flex; align-items: center; flex-wrap: wrap; gap: 0.5rem;">
            @if($maxRound > 1)
                <span class="doc-badge doc-badge-warning" style="display: inline-flex; align-items: center; gap: 0.375rem; padding: 0.25rem 0.625rem; border-radius: 0.375rem; font-size: 0.75rem; font-weight: 600; background-color: var(--doc-badge-warning-bg); color: var(--doc-badge-warning-text); border: 1px solid var(--doc-badge-warning-border);">
                    <x-heroicon-s-arrow-path class="doc-icon-xs" style="width: 0.875rem; height: 0.875rem;" />
                    Putaran Evaluasi Ke-{{ $maxRound }}
                </span>
            @endif

            <span class="doc-badge
                @if($docStatus === DocumentStatus::Approved) doc-badge-success
                @elseif($docStatus === DocumentStatus::Archived) doc-badge-info
                @elseif($docStatus === DocumentStatus::Pending) doc-badge-warning
                @elseif($docStatus === DocumentStatus::Revision) doc-badge-danger
                @elseif($docStatus === DocumentStatus::Rejected) doc-badge-danger
                @else doc-badge-gray
                @endif
            " style="display: inline-flex; align-items: center; gap: 0.375rem; padding: 0.25rem 0.625rem; border-radius: 0.375rem; font-size: 0.75rem; font-weight: 600;
                @if($docStatus === DocumentStatus::Approved) background-color: var(--doc-badge-success-bg); color: var(--doc-badge-success-text); border: 1px solid var(--doc-badge-success-border);
                @elseif($docStatus === DocumentStatus::Archived) background-color: var(--doc-badge-info-bg); color: var(--doc-badge-info-text); border: 1px solid var(--doc-badge-info-border);
                @elseif($docStatus === DocumentStatus::Pending) background-color: var(--doc-badge-warning-bg); color: var(--doc-badge-warning-text); border: 1px solid var(--doc-badge-warning-border);
                @elseif($docStatus === DocumentStatus::Revision || $docStatus === DocumentStatus::Rejected) background-color: var(--doc-badge-danger-bg); color: var(--doc-badge-danger-text); border: 1px solid var(--doc-badge-danger-border);
                @else background-color: var(--doc-badge-gray-bg); color: var(--doc-badge-gray-text); border: 1px solid var(--doc-badge-gray-border);
                @endif
            ">
                <span class="doc-dot
                    @if($docStatus === DocumentStatus::Pending) doc-dot-pulse @endif
                " style="
                    @if($docStatus === DocumentStatus::Approved) background: var(--doc-badge-success-solid);
                    @elseif($docStatus === DocumentStatus::Archived) background: var(--doc-badge-info-solid);
                    @elseif($docStatus === DocumentStatus::Pending) background: var(--doc-badge-warning-solid);
                    @elseif($docStatus === DocumentStatus::Revision || $docStatus === DocumentStatus::Rejected) background: var(--doc-badge-danger-solid);
                    @else background: var(--doc-badge-gray-solid);
                    @endif
                "></span>
                {{ $docStatus->getLabel() }}
            </span>

            @if($document->document_number)
                <span class="doc-badge doc-badge-info doc-badge-mono" style="display: inline-flex; align-items: center; gap: 0.375rem; padding: 0.25rem 0.625rem; border-radius: 0.375rem; font-size: 0.75rem; font-weight: 700; font-family: ui-monospace, SFMono-Regular, monospace; background-color: var(--doc-badge-info-bg); color: var(--doc-badge-info-text); border: 1px solid var(--doc-badge-info-border);">
                    <x-heroicon-m-tag class="doc-icon-xs" style="width: 0.875rem; height: 0.875rem;" />
                    {{ $document->document_number }}
                </span>
            @endif
        </div>
    </div>

    {{-- Alert Banner jika Revisi atau Ditolak --}}
    @if($docStatus === DocumentStatus::Revision && $latestActionWithNote)
        <div class="doc-alert-revision" style="background-color: var(--doc-badge-warning-bg); border: 1px solid var(--doc-badge-warning-border); border-radius: 0.375rem; padding: 1rem; margin-top: 1rem; display: flex; align-items: flex-start; gap: 0.75rem; color: var(--doc-badge-warning-text);">
            <div style="flex-shrink: 0; color: var(--doc-badge-warning-solid); margin-top: 0.125rem;">
                <x-heroicon-s-exclamation-triangle class="doc-icon-md" style="width: 1.25rem; height: 1.25rem;" />
            </div>
            <div class="doc-alert-content" style="flex: 1; font-size: 0.8125rem; line-height: 1.25rem;">
                <h4 style="font-weight: 700; margin: 0; color: var(--doc-badge-warning-text);">
                    Naskah Dinas Memerlukan Revisi
                </h4>
                <p style="margin: 0.25rem 0 0 0; color: var(--doc-text-secondary);">
                    Catatan perbaikan dari <strong>{{ $latestActionWithNote->approver?->name ?? 'Pemeriksa' }}</strong>
                    @if($latestActionWithNote->approver_position_snapshot)
                        ({{ $latestActionWithNote->approver_position_snapshot }})
                    @endif:
                </p>
                <div class="doc-alert-quote" style="background-color: var(--doc-surface); border: 1px solid var(--doc-border); border-radius: 0.375rem; padding: 0.625rem 0.875rem; font-style: italic; margin-top: 0.5rem; color: var(--doc-text-primary);">
                    "{{ $latestActionWithNote->note }}"
                </div>
                <p style="margin: 0.5rem 0 0 0; font-size: 0.75rem; color: var(--doc-text-muted);">
                    Silakan perbaiki berkas dokumen atau data formulir, lalu gunakan tombol <strong>"Ajukan Kembali"</strong> pada menu di atas untuk meneruskan ke putaran evaluasi berikutnya.
                </p>
            </div>
        </div>
    @elseif($docStatus === DocumentStatus::Rejected && $latestActionWithNote)
        <div class="doc-alert-rejected" style="background-color: var(--doc-badge-danger-bg); border: 1px solid var(--doc-badge-danger-border); border-radius: 0.375rem; padding: 1rem; margin-top: 1rem; display: flex; align-items: flex-start; gap: 0.75rem; color: var(--doc-badge-danger-text);">
            <div style="flex-shrink: 0; color: var(--doc-badge-danger-solid); margin-top: 0.125rem;">
                <x-heroicon-s-x-circle class="doc-icon-md" style="width: 1.25rem; height: 1.25rem;" />
            </div>
            <div class="doc-alert-content" style="flex: 1; font-size: 0.8125rem; line-height: 1.25rem;">
                <h4 style="font-weight: 700; margin: 0; color: var(--doc-badge-danger-text);">
                    Dokumen Ditolak Secara Resmi
                </h4>
                <p style="margin: 0.25rem 0 0 0; color: var(--doc-text-secondary);">
                    Alasan penolakan oleh <strong>{{ $latestActionWithNote->approver?->name ?? 'Pemeriksa' }}</strong>
                    @if($latestActionWithNote->approver_position_snapshot)
                        ({{ $latestActionWithNote->approver_position_snapshot }})
                    @endif:
                </p>
                <div class="doc-alert-quote" style="background-color: var(--doc-surface); border: 1px solid var(--doc-border); border-radius: 0.375rem; padding: 0.625rem 0.875rem; font-style: italic; margin-top: 0.5rem; color: var(--doc-text-primary);">
                    "{{ $latestActionWithNote->note }}"
                </div>
            </div>
        </div>
    @endif

    {{-- Visual Stepper Progress Bar --}}
    <div class="doc-stepper-overflow" style="overflow-x: auto; padding-bottom: 0.5rem; margin-top: 1.25rem; -webkit-overflow-scrolling: touch;">
        @if($isManual)
            {{-- Dokumen Manual / Backdate --}}
            <div class="doc-stepper-track" style="display: flex; align-items: center; gap: 1rem; padding: 0.5rem 0;">
                <div style="display: flex; align-items: center; gap: 0.75rem;">
                    <div class="doc-stepper-circle doc-circle-approved" style="width: 2.375rem; height: 2.375rem; border-radius: 9999px; background-color: var(--doc-badge-success-solid); color: #ffffff; display: flex; align-items: center; justify-content: center; box-shadow: 0 0 0 4px var(--doc-badge-success-bg);">
                        <x-heroicon-s-check class="doc-icon-md" style="width: 1.25rem; height: 1.25rem;" />
                    </div>
                    <div>
                        <p style="font-size: 0.6875rem; font-weight: 700; color: var(--doc-text-muted); text-transform: uppercase; margin: 0;">Tahap 1</p>
                        <h4 style="font-size: 0.8125rem; font-weight: 700; color: var(--doc-text-primary); margin: 0.125rem 0 0 0;">Pencatatan Arsip Manual</h4>
                        <p style="font-size: 0.6875rem; color: var(--doc-text-muted); margin: 0.125rem 0 0 0;">{{ $document->uploader?->name ?? 'Staf' }} • {{ $document->created_at->translatedFormat('d M Y') }}</p>
                    </div>
                </div>
                <div class="doc-stepper-line doc-line-approved" style="flex: 1; height: 3px; background-color: var(--doc-badge-success-solid);"></div>
                <div style="display: flex; align-items: center; gap: 0.75rem;">
                    <div class="doc-stepper-circle doc-circle-approved" style="width: 2.375rem; height: 2.375rem; border-radius: 9999px; background-color: var(--doc-badge-success-solid); color: #ffffff; display: flex; align-items: center; justify-content: center; box-shadow: 0 0 0 4px var(--doc-badge-success-bg);">
                        <x-heroicon-s-check-badge class="doc-icon-md" style="width: 1.25rem; height: 1.25rem;" />
                    </div>
                    <div>
                        <p style="font-size: 0.6875rem; color: var(--doc-badge-success-solid); font-weight: 700; text-transform: uppercase; margin: 0;">Resmi Terdaftar</p>
                        <h4 style="font-size: 0.8125rem; font-weight: 700; color: var(--doc-text-primary); margin: 0.125rem 0 0 0; font-family: ui-monospace, monospace;">{{ $document->document_number }}</h4>
                        <p style="font-size: 0.6875rem; color: var(--doc-text-muted); margin: 0.125rem 0 0 0;">Nomor fisik arsip terdahulu</p>
                    </div>
                </div>
            </div>
        @elseif(! $requiresApproval || $totalSteps === 0 || $activeApprovals->isEmpty())
            {{-- Dokumen Tanpa Alur Persetujuan ATAU Dokumen yang Telah Sah Penuh --}}
            <div class="doc-stepper-track" style="display: flex; align-items: center; gap: 1rem; padding: 0.5rem 0;">
                <div style="display: flex; align-items: center; gap: 0.75rem;">
                    <div class="doc-stepper-circle doc-circle-approved" style="width: 2.375rem; height: 2.375rem; border-radius: 9999px; background-color: var(--doc-badge-success-solid); color: #ffffff; display: flex; align-items: center; justify-content: center; box-shadow: 0 0 0 4px var(--doc-badge-success-bg);">
                        <x-heroicon-s-check class="doc-icon-md" style="width: 1.25rem; height: 1.25rem;" />
                    </div>
                    <div>
                        <p style="font-size: 0.6875rem; font-weight: 700; color: var(--doc-text-muted); text-transform: uppercase; margin: 0;">Tahap Pengajuan</p>
                        <h4 style="font-size: 0.8125rem; font-weight: 700; color: var(--doc-text-primary); margin: 0.125rem 0 0 0;">{{ $document->uploader?->name ?? 'Staf Pengunggah' }}</h4>
                        <p style="font-size: 0.6875rem; color: var(--doc-text-muted); margin: 0.125rem 0 0 0;">{{ $document->created_at->translatedFormat('d M Y, H:i') }} WIB</p>
                    </div>
                </div>
                <div class="doc-stepper-line doc-line-approved" style="flex: 1; height: 3px; background-color: var(--doc-badge-success-solid);"></div>
                <div style="display: flex; align-items: center; gap: 0.75rem;">
                    <div class="doc-stepper-circle doc-circle-approved" style="width: 2.375rem; height: 2.375rem; border-radius: 9999px; background-color: var(--doc-badge-success-solid); color: #ffffff; display: flex; align-items: center; justify-content: center; box-shadow: 0 0 0 4px var(--doc-badge-success-bg);">
                        <x-heroicon-s-check-badge class="doc-icon-md" style="width: 1.25rem; height: 1.25rem;" />
                    </div>
                    <div>
                        <p style="font-size: 0.6875rem; color: var(--doc-badge-success-solid); font-weight: 700; text-transform: uppercase; margin: 0;">
                            {{ $docStatus === DocumentStatus::Archived ? 'Resmi Disahkan & Diarsipkan' : ($isApprovedOrArchived ? 'Resmi Disahkan' : 'Pengesahan Naskah') }}
                        </p>
                        <h4 style="font-size: 0.8125rem; font-weight: 700; color: var(--doc-text-primary); margin: 0.125rem 0 0 0;">
                            {{ $document->document_number ?? $docStatus->getLabel() }}
                        </h4>
                        <p style="font-size: 0.6875rem; color: var(--doc-text-muted); margin: 0.125rem 0 0 0;">
                            {{ $document->signer_name_snapshot ?? ($document->finalApprover?->name ?? 'Sistem Kampus') }}
                        </p>
                    </div>
                </div>
            </div>
        @else
            {{-- Alur Persetujuan Bertingkat Estafet --}}
            <div class="doc-stepper-track" style="display: flex; align-items: flex-start; min-width: 650px; padding: 0.5rem 0; width: 100%;">
                {{-- Step 0: Pengajuan --}}
                <div class="doc-stepper-node" style="display: flex; flex-direction: column; align-items: center; flex: 1; text-align: center;">
                    <div class="doc-stepper-circle doc-circle-approved" style="width: 2.375rem; height: 2.375rem; border-radius: 9999px; background-color: var(--doc-badge-success-solid); color: #ffffff; display: flex; align-items: center; justify-content: center; box-shadow: 0 0 0 4px var(--doc-badge-success-bg);">
                        <x-heroicon-s-check class="doc-icon-md" style="width: 1.25rem; height: 1.25rem;" />
                    </div>
                    <div class="doc-stepper-info" style="margin-top: 0.5rem; max-width: 150px; text-align: center;">
                        <span class="doc-stepper-step-tag" style="display: inline-block; font-size: 0.6875rem; font-weight: 700; color: var(--doc-badge-success-solid); text-transform: uppercase; letter-spacing: 0.04em;">Diajukan</span>
                        <h5 class="doc-stepper-name" style="font-size: 0.75rem; font-weight: 700; color: var(--doc-text-primary); margin: 0.125rem 0 0 0;">{{ $document->uploader?->name ?? 'Staf' }}</h5>
                        <p class="doc-stepper-sub" style="font-size: 0.6875rem; color: var(--doc-text-muted); margin: 0.125rem 0 0 0;">{{ $document->unit?->name }}</p>
                        <p class="doc-stepper-time" style="font-size: 0.625rem; color: var(--doc-text-subtle); margin: 0.125rem 0 0 0;">{{ $document->created_at->translatedFormat('d M Y, H:i') }}</p>
                    </div>
                </div>

                {{-- Steps 1..N: Reviewers --}}
                @foreach($activeApprovals as $approval)
                    @php
                        $isStepApproved = $approval->status === ApprovalStatus::Approved;
                        $isStepPending = $approval->status === ApprovalStatus::Pending;
                        $isStepRevision = $approval->status === ApprovalStatus::Revision;
                        $isStepRejected = $approval->status === ApprovalStatus::Rejected;
                        $isStepWaiting = $approval->status === ApprovalStatus::Waiting;
                    @endphp

                    {{-- Connector Line --}}
                    <div class="doc-stepper-line
                        @if($isStepApproved) doc-line-approved
                        @elseif($isStepRevision || $isStepRejected) doc-line-danger
                        @elseif($isStepPending) doc-line-pending
                        @endif
                    " style="flex: 1; height: 3px; margin-top: 1.1875rem; min-width: 2rem;
                        @if($isStepApproved) background-color: var(--doc-badge-success-solid);
                        @elseif($isStepRevision || $isStepRejected) background-color: var(--doc-badge-danger-solid);
                        @elseif($isStepPending) background-color: var(--doc-badge-warning-solid);
                        @else background-color: var(--doc-stepper-rail);
                        @endif
                    "></div>

                    {{-- Step Node --}}
                    <div class="doc-stepper-node" style="display: flex; flex-direction: column; align-items: center; flex: 1; text-align: center;">
                        <div class="doc-stepper-circle
                            @if($isStepApproved) doc-circle-approved
                            @elseif($isStepPending) doc-circle-pending
                            @elseif($isStepRevision) doc-circle-revision
                            @elseif($isStepRejected) doc-circle-rejected
                            @else doc-circle-waiting
                            @endif
                        " style="width: 2.375rem; height: 2.375rem; border-radius: 9999px; display: flex; align-items: center; justify-content: center; font-size: 0.8125rem; font-weight: 700;
                            @if($isStepApproved) background-color: var(--doc-badge-success-solid); color: #ffffff; box-shadow: 0 0 0 4px var(--doc-badge-success-bg);
                            @elseif($isStepPending) background-color: var(--doc-badge-warning-solid); color: #ffffff; box-shadow: 0 0 0 4px var(--doc-badge-warning-bg);
                            @elseif($isStepRevision || $isStepRejected) background-color: var(--doc-badge-danger-solid); color: #ffffff; box-shadow: 0 0 0 4px var(--doc-badge-danger-bg);
                            @else background-color: var(--doc-stepper-waiting-bg); color: var(--doc-stepper-waiting-text); border: 2px solid var(--doc-stepper-waiting-border);
                            @endif
                        ">
                            @if($isStepApproved)
                                <x-heroicon-s-check class="doc-icon-md" style="width: 1.25rem; height: 1.25rem;" />
                            @elseif($isStepPending)
                                <x-heroicon-s-clock class="doc-icon-md" style="width: 1.25rem; height: 1.25rem;" />
                            @elseif($isStepRevision)
                                <x-heroicon-s-arrow-path class="doc-icon-md" style="width: 1.25rem; height: 1.25rem;" />
                            @elseif($isStepRejected)
                                <x-heroicon-s-x-mark class="doc-icon-md" style="width: 1.25rem; height: 1.25rem;" />
                            @else
                                <span>{{ $approval->step_order }}</span>
                            @endif
                        </div>

                        <div class="doc-stepper-info" style="margin-top: 0.5rem; max-width: 150px; text-align: center;">
                            <span class="doc-stepper-step-tag" style="
                                @if($isStepApproved) color: var(--doc-badge-success-solid);
                                @elseif($isStepPending) color: var(--doc-badge-warning-solid);
                                @elseif($isStepRevision || $isStepRejected) color: var(--doc-badge-danger-solid);
                                @else color: var(--doc-text-muted);
                                @endif
                            ">
                                Tingkat {{ $approval->step_order }} ({{ str($approval->approver_role)->title()->replace('_', ' ') }})
                            </span>

                            @if($approval->approver)
                                <h5 class="doc-stepper-name" style="font-size: 0.75rem; font-weight: 700; color: var(--doc-text-primary); margin: 0.125rem 0 0 0;" title="{{ $approval->approver->name }}">
                                    {{ $approval->approver->name }}
                                </h5>
                                <p class="doc-stepper-sub" style="font-size: 0.6875rem; color: var(--doc-text-muted); margin: 0.125rem 0 0 0;" title="{{ $approval->approver_position_snapshot }}">
                                    {{ $approval->approver_position_snapshot ?? $approval->targetUnit?->name }}
                                </p>
                                <p class="doc-stepper-time" style="font-size: 0.625rem; color: var(--doc-text-subtle); margin: 0.125rem 0 0 0;">
                                    {{ $approval->approved_at?->translatedFormat('d M Y, H:i') }}
                                </p>
                            @else
                                <h5 class="doc-stepper-name" style="font-size: 0.75rem; font-weight: 600; color: var(--doc-text-muted); margin: 0.125rem 0 0 0;">
                                    {{ $approval->targetUnit?->name ?? 'Unit Terkait' }}
                                </h5>
                                <p class="doc-stepper-sub" style="font-size: 0.6875rem; font-style: italic; color: var(--doc-text-subtle); margin: 0.125rem 0 0 0;">
                                    @if($isStepPending) Menunggu Keputusan @else Menunggu Giliran @endif
                                </p>
                            @endif

                            @if($approval->note)
                                <div class="doc-stepper-note-pill" style="display: inline-block; font-size: 0.625rem; font-style: italic; background-color: var(--doc-surface-muted); color: var(--doc-text-secondary); border: 1px solid var(--doc-border); padding: 0.125rem 0.5rem; border-radius: 0.25rem; margin-top: 0.25rem; max-width: 140px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="{{ $approval->note }}">
                                    "{{ $approval->note }}"
                                </div>
                            @endif
                        </div>
                    </div>
                @endforeach

                {{-- Final Step: Pengesahan & Penomoran Resmi --}}
                <div class="doc-stepper-line
                    @if($isApprovedOrArchived) doc-line-approved
                    @endif
                " style="flex: 1; height: 3px; margin-top: 1.1875rem; min-width: 2rem;
                    @if($isApprovedOrArchived) background-color: var(--doc-badge-success-solid);
                    @else background-color: var(--doc-stepper-rail);
                    @endif
                "></div>

                <div class="doc-stepper-node" style="display: flex; flex-direction: column; align-items: center; flex: 1; text-align: center;">
                    <div class="doc-stepper-circle
                        @if($isApprovedOrArchived) doc-circle-approved
                        @else doc-circle-waiting
                        @endif
                    " style="width: 2.375rem; height: 2.375rem; border-radius: 9999px; display: flex; align-items: center; justify-content: center;
                        @if($isApprovedOrArchived) background-color: var(--doc-badge-success-solid); color: #ffffff; box-shadow: 0 0 0 4px var(--doc-badge-success-bg);
                        @else background-color: var(--doc-stepper-waiting-bg); color: var(--doc-stepper-waiting-text); border: 2px solid var(--doc-stepper-waiting-border);
                        @endif
                    ">
                        <x-heroicon-s-check-badge class="doc-icon-md" style="width: 1.25rem; height: 1.25rem;" />
                    </div>

                    <div class="doc-stepper-info" style="margin-top: 0.5rem; max-width: 160px; text-align: center;">
                        <span class="doc-stepper-step-tag" style="
                            @if($isApprovedOrArchived) color: var(--doc-badge-success-solid);
                            @else color: var(--doc-text-muted);
                            @endif
                        ">
                            @if($docStatus === DocumentStatus::Archived)
                                Disahkan & Diarsipkan
                            @elseif($isApprovedOrArchived)
                                Resmi Disahkan
                            @else
                                Pengesahan & Stempel
                            @endif
                        </span>

                        @if($isApprovedOrArchived)
                            <h5 class="doc-stepper-name" style="font-size: 0.75rem; font-weight: 700; color: var(--doc-text-primary); margin: 0.125rem 0 0 0; font-family: ui-monospace, monospace;" title="{{ $document->document_number }}">
                                {{ $document->document_number }}
                            </h5>
                            <p class="doc-stepper-sub" style="font-size: 0.6875rem; color: var(--doc-text-muted); margin: 0.125rem 0 0 0;" title="{{ $document->signer_name_snapshot }}">
                                {{ $document->signer_name_snapshot ?? ($document->finalApprover?->name ?? 'Pejabat') }}
                            </p>
                            <p class="doc-stepper-time" style="font-size: 0.625rem; color: var(--doc-text-subtle); margin: 0.125rem 0 0 0;">
                                {{ $document->approved_at?->translatedFormat('d M Y, H:i') }}
                            </p>
                        @else
                            <p class="doc-stepper-sub" style="font-size: 0.6875rem; font-style: italic; color: var(--doc-text-subtle); margin: 0.125rem 0 0 0;">
                                Menunggu persetujuan selesai
                            </p>
                        @endif
                    </div>
                </div>
            </div>
        @endif
    </div>
</div>
