@props([
    'letter',
])

@php
    use App\Enums\OutgoingLetterStatus;
    use Illuminate\Support\Facades\Storage;

    $status = $letter->status;
    $isPublished = $status === OutgoingLetterStatus::Published;
    $isWaitingSignature = $status === OutgoingLetterStatus::WaitingSignature;
    $isNumberIssued = $isPublished || $isWaitingSignature || filled($letter->letter_number);
    $isPending = $status === OutgoingLetterStatus::PendingHumas;
    $isRevision = $status === OutgoingLetterStatus::NeedsRevision;
    $isRejected = $status === OutgoingLetterStatus::Rejected;
    $usesWetSignatureFlow = (bool) ($letter->requires_wet_signature || $isWaitingSignature || $letter->final_scan_uploaded_at !== null);

    $activePath = $letter->getActiveFilePath();
    $hasFile = $activePath && Storage::disk('private')->exists($activePath);
    $ext = $activePath ? strtolower(pathinfo($activePath, PATHINFO_EXTENSION)) : '';
    $isPdf = ($ext === 'pdf');
    $isWord = in_array($ext, ['doc', 'docx'], true);
    $fileSize = $hasFile ? Storage::disk('private')->size($activePath) : 0;
    $fileName = $letter->file_name ?: ($activePath ? basename($activePath) : 'berkas-surat-keluar');
    $isStamped = filled($letter->stamped_file_path);
    $streamUrl = route('outgoing-letters.file.stream', $letter->uuid);
    $downloadUrl = route('outgoing-letters.file.download', $letter->uuid);
@endphp

@include('filament.components.document-timeline-styles')

{{-- Card 1: Stepper Alur Penomoran Surat Keluar --}}
<div class="doc-card" style="background-color: var(--doc-surface); border: 1px solid var(--doc-border); border-radius: 0.5rem; padding: 1.25rem 1.5rem; margin-bottom: 1.25rem; box-shadow: var(--doc-shadow-sm); color: var(--doc-text-primary);">
    <div class="doc-card-header" style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 0.75rem; padding-bottom: 1rem; border-bottom: 1px solid var(--doc-border);">
        <div style="display: flex; align-items: center; gap: 0.75rem;">
            <div class="doc-card-title-icon" style="width: 2.25rem; height: 2.25rem; border-radius: 0.375rem; background-color: var(--doc-badge-info-bg); color: var(--doc-badge-info-solid); border: 1px solid var(--doc-badge-info-border); display: inline-flex; align-items: center; justify-content: center; flex-shrink: 0;">
                <x-heroicon-o-paper-airplane class="doc-icon-md" style="width: 1.25rem; height: 1.25rem;" />
            </div>
            <div>
                <h3 class="doc-card-title" style="font-size: 0.9375rem; font-weight: 700; color: var(--doc-text-primary); margin: 0; line-height: 1.25rem; letter-spacing: -0.01em;">
                    Pelacakan Penomoran & Pengesahan Surat Keluar
                </h3>
                <p class="doc-card-subtitle" style="font-size: 0.75rem; color: var(--doc-text-muted); margin: 0.25rem 0 0 0;">
                    Alur pengajuan surat keluar dari unit kerja hingga penerbitan nomor resmi otomatis oleh Bagian Humas.
                </p>
            </div>
        </div>

        <div class="doc-badge-group" style="display: flex; align-items: center; flex-wrap: wrap; gap: 0.5rem;">
            <span class="doc-badge" style="display: inline-flex; align-items: center; gap: 0.375rem; padding: 0.25rem 0.625rem; border-radius: 0.375rem; font-size: 0.75rem; font-weight: 600;
                @if($isPublished) background-color: var(--doc-badge-success-bg); color: var(--doc-badge-success-text); border: 1px solid var(--doc-badge-success-border);
                @elseif($isWaitingSignature) background-color: var(--doc-badge-info-bg); color: var(--doc-badge-info-text); border: 1px solid var(--doc-badge-info-border);
                @elseif($isPending) background-color: var(--doc-badge-warning-bg); color: var(--doc-badge-warning-text); border: 1px solid var(--doc-badge-warning-border);
                @else background-color: var(--doc-badge-danger-bg); color: var(--doc-badge-danger-text); border: 1px solid var(--doc-badge-danger-border);
                @endif
            ">
                <span class="doc-dot @if($isPending || $isWaitingSignature) doc-dot-pulse @endif" style="
                    @if($isPublished) background: var(--doc-badge-success-solid);
                    @elseif($isWaitingSignature) background: var(--doc-badge-info-solid);
                    @elseif($isPending) background: var(--doc-badge-warning-solid);
                    @else background: var(--doc-badge-danger-solid);
                    @endif
                "></span>
                {{ $status->getLabel() }}
            </span>

            @if($letter->letter_number)
                <span class="doc-badge doc-badge-info doc-badge-mono" style="display: inline-flex; align-items: center; gap: 0.375rem; padding: 0.25rem 0.625rem; border-radius: 0.375rem; font-size: 0.75rem; font-weight: 700; font-family: ui-monospace, SFMono-Regular, monospace; background-color: var(--doc-badge-info-bg); color: var(--doc-badge-info-text); border: 1px solid var(--doc-badge-info-border);">
                    <x-heroicon-m-tag class="doc-icon-xs" style="width: 0.875rem; height: 0.875rem;" />
                    {{ $letter->letter_number }}
                </span>
            @endif
        </div>
    </div>

    @if($isWaitingSignature)
        <div style="background-color: var(--doc-badge-info-bg); border: 1px solid var(--doc-badge-info-border); border-radius: 0.375rem; padding: 0.875rem 1rem; margin-top: 1rem; display: flex; align-items: flex-start; justify-content: space-between; flex-wrap: wrap; gap: 0.75rem;">
            <div style="flex: 1; min-width: 240px; font-size: 0.8125rem; line-height: 1.35rem; color: var(--doc-text-primary);">
                <strong>Nomor Terbit — Menunggu TTD Basah, Cap & Scan Final</strong>
                <p style="margin: 0.25rem 0 0 0; color: var(--doc-text-secondary);">
                    Unduh berkas bernomor <strong style="font-family: ui-monospace, monospace;">{{ $letter->letter_number }}</strong> untuk dicetak, dimintakan TTD basah & cap resmi, lalu unggah scan PDF final melalui tombol <strong>"Unggah Scan TTD Basah"</strong> (dapat dilakukan Unit Pengaju atau Humas).
                </p>
            </div>
            @if($hasFile)
                <a href="{{ $downloadUrl }}" style="display: inline-flex; align-items: center; gap: 0.45rem; padding: 0.5rem 0.9rem; border-radius: 0.375rem; font-size: 0.8125rem; font-weight: 600; background-color: #2563eb; color: #ffffff; text-decoration: none; white-space: nowrap;">
                    <x-heroicon-m-arrow-down-tray style="width: 1rem; height: 1rem;" />
                    <span>Unduh Berkas Bernomor</span>
                </a>
            @endif
        </div>
    @elseif(($isRevision || $isRejected) && filled($letter->review_note))
        <div style="background-color: {{ $isRevision ? 'var(--doc-badge-warning-bg)' : 'var(--doc-badge-danger-bg)' }}; border: 1px solid {{ $isRevision ? 'var(--doc-badge-warning-border)' : 'var(--doc-badge-danger-border)' }}; border-radius: 0.375rem; padding: 0.875rem 1rem; margin-top: 1rem; display: flex; align-items: flex-start; gap: 0.75rem;">
            <div style="flex-shrink: 0; color: {{ $isRevision ? 'var(--doc-badge-warning-solid)' : 'var(--doc-badge-danger-solid)' }}; margin-top: 0.125rem;">
                <x-heroicon-s-exclamation-triangle style="width: 1.25rem; height: 1.25rem;" />
            </div>
            <div style="flex: 1; font-size: 0.8125rem; line-height: 1.35rem; color: var(--doc-text-primary);">
                <strong>{{ $isRevision ? 'Surat Keluar Memerlukan Perbaikan Unit' : 'Pengajuan Surat Keluar Ditolak Humas' }}</strong>
                <p style="margin: 0.25rem 0 0 0; color: var(--doc-text-secondary);">
                    Catatan dari <strong>{{ $letter->reviewer?->name ?? 'Bagian Humas' }}</strong>: "{{ $letter->review_note }}"
                </p>
            </div>
        </div>
    @endif

    <div class="doc-stepper-overflow" style="overflow-x: auto; padding-bottom: 0.5rem; margin-top: 1.25rem; -webkit-overflow-scrolling: touch;">
        <div class="doc-stepper-track" style="display: flex; align-items: flex-start; min-width: 580px; padding: 0.5rem 0; width: 100%;">
            {{-- Node 1: Pengajuan Unit --}}
            <div class="doc-stepper-node" style="display: flex; flex-direction: column; align-items: center; flex: 1; text-align: center;">
                <div class="doc-stepper-circle doc-circle-approved" style="width: 2.375rem; height: 2.375rem; border-radius: 9999px; background-color: var(--doc-badge-success-solid); color: #ffffff; display: flex; align-items: center; justify-content: center; box-shadow: 0 0 0 4px var(--doc-badge-success-bg);">
                    <x-heroicon-s-check class="doc-icon-md" style="width: 1.25rem; height: 1.25rem;" />
                </div>
                <div class="doc-stepper-info" style="margin-top: 0.5rem; max-width: 180px; text-align: center;">
                    <span class="doc-stepper-step-tag" style="display: inline-block; font-size: 0.6875rem; font-weight: 700; color: var(--doc-badge-success-solid); text-transform: uppercase;">
                        1. Pengajuan Surat
                    </span>
                    <h5 class="doc-stepper-name" style="font-size: 0.75rem; font-weight: 700; color: var(--doc-text-primary); margin: 0.125rem 0 0 0;">
                        {{ $letter->creator?->name ?? 'Pemohon' }}
                    </h5>
                    <p class="doc-stepper-sub" style="font-size: 0.6875rem; color: var(--doc-text-muted); margin: 0.125rem 0 0 0;">
                        {{ $letter->unit?->name }}
                    </p>
                    <p class="doc-stepper-time" style="font-size: 0.625rem; color: var(--doc-text-subtle); margin: 0.125rem 0 0 0;">
                        {{ $letter->created_at?->translatedFormat('d M Y, H:i') }}
                    </p>
                </div>
            </div>

            <div class="doc-stepper-line" style="flex: 1; height: 3px; margin-top: 1.1875rem; min-width: 2rem;
                @if($isNumberIssued) background-color: var(--doc-badge-success-solid);
                @elseif($isPending) background-color: var(--doc-badge-warning-solid);
                @else background-color: var(--doc-badge-danger-solid);
                @endif
            "></div>

            {{-- Node 2: Verifikasi & Penomoran Humas --}}
            <div class="doc-stepper-node" style="display: flex; flex-direction: column; align-items: center; flex: 1; text-align: center;">
                <div class="doc-stepper-circle" style="width: 2.375rem; height: 2.375rem; border-radius: 9999px; display: flex; align-items: center; justify-content: center;
                    @if($isNumberIssued) background-color: var(--doc-badge-success-solid); color: #ffffff; box-shadow: 0 0 0 4px var(--doc-badge-success-bg);
                    @elseif($isPending) background-color: var(--doc-badge-warning-solid); color: #ffffff; box-shadow: 0 0 0 4px var(--doc-badge-warning-bg);
                    @else background-color: var(--doc-badge-danger-solid); color: #ffffff; box-shadow: 0 0 0 4px var(--doc-badge-danger-bg);
                    @endif
                ">
                    @if($isNumberIssued)
                        <x-heroicon-s-check class="doc-icon-md" style="width: 1.25rem; height: 1.25rem;" />
                    @elseif($isPending)
                        <x-heroicon-s-clock class="doc-icon-md" style="width: 1.25rem; height: 1.25rem;" />
                    @else
                        <x-heroicon-s-arrow-path class="doc-icon-md" style="width: 1.25rem; height: 1.25rem;" />
                    @endif
                </div>
                <div class="doc-stepper-info" style="margin-top: 0.5rem; max-width: 185px; text-align: center;">
                    <span class="doc-stepper-step-tag" style="display: inline-block; font-size: 0.6875rem; font-weight: 700; text-transform: uppercase;
                        @if($isNumberIssued) color: var(--doc-badge-success-solid);
                        @elseif($isPending) color: var(--doc-badge-warning-solid);
                        @else color: var(--doc-badge-danger-solid);
                        @endif
                    ">
                        2. Tinjauan & Nomor Humas
                    </span>
                    <h5 class="doc-stepper-name" style="font-size: 0.75rem; font-weight: 700; color: var(--doc-text-primary); margin: 0.125rem 0 0 0;">
                        {{ $letter->reviewer?->name ?? 'Bagian Humas' }}
                    </h5>
                    <p class="doc-stepper-sub" style="font-size: 0.6875rem; color: var(--doc-text-muted); margin: 0.125rem 0 0 0;">
                        {{ $isNumberIssued ? 'Nomor Resmi Diterbitkan' : ($isPending ? 'Menunggu Tinjauan Humas' : $status->getLabel()) }}
                    </p>
                    @if($letter->reviewed_at)
                        <p class="doc-stepper-time" style="font-size: 0.625rem; color: var(--doc-text-subtle); margin: 0.125rem 0 0 0;">
                            {{ $letter->reviewed_at->translatedFormat('d M Y, H:i') }}
                        </p>
                    @endif
                </div>
            </div>

            <div class="doc-stepper-line" style="flex: 1; height: 3px; margin-top: 1.1875rem; min-width: 2rem;
                @if($isPublished) background-color: var(--doc-badge-success-solid);
                @elseif($isWaitingSignature) background-color: var(--doc-badge-info-solid);
                @else background-color: var(--doc-stepper-rail);
                @endif
            "></div>

            {{-- Node 3: Berkas Siap Diunduh / TTD Basah Final --}}
            <div class="doc-stepper-node" style="display: flex; flex-direction: column; align-items: center; flex: 1; text-align: center;">
                <div class="doc-stepper-circle" style="width: 2.375rem; height: 2.375rem; border-radius: 9999px; display: flex; align-items: center; justify-content: center;
                    @if($isPublished) background-color: var(--doc-badge-success-solid); color: #ffffff; box-shadow: 0 0 0 4px var(--doc-badge-success-bg);
                    @elseif($isWaitingSignature) background-color: var(--doc-badge-info-solid); color: #ffffff; box-shadow: 0 0 0 4px var(--doc-badge-info-bg);
                    @else background-color: var(--doc-stepper-waiting-bg); color: var(--doc-stepper-waiting-text); border: 2px solid var(--doc-stepper-waiting-border);
                    @endif
                ">
                    @if($isWaitingSignature)
                        <x-heroicon-s-arrow-up-tray class="doc-icon-md" style="width: 1.25rem; height: 1.25rem;" />
                    @else
                        <x-heroicon-s-check-badge class="doc-icon-md" style="width: 1.25rem; height: 1.25rem;" />
                    @endif
                </div>
                <div class="doc-stepper-info" style="margin-top: 0.5rem; max-width: 185px; text-align: center;">
                    <span class="doc-stepper-step-tag" style="display: inline-block; font-size: 0.6875rem; font-weight: 700; text-transform: uppercase;
                        @if($isPublished) color: var(--doc-badge-success-solid);
                        @elseif($isWaitingSignature) color: var(--doc-badge-info-solid);
                        @else color: var(--doc-text-muted);
                        @endif
                    ">
                        {{ $usesWetSignatureFlow ? ($isPublished ? '3. Arsip Scan Final Sah' : '3. TTD Basah, Cap & Scan') : '3. Siap Diunduh' }}
                    </span>
                    @if($isPublished)
                        <h5 class="doc-stepper-name" style="font-size: 0.75rem; font-weight: 700; color: var(--doc-text-primary); margin: 0.125rem 0 0 0; font-family: ui-monospace, monospace;">
                            {{ $letter->letter_number }}
                        </h5>
                        <p class="doc-stepper-sub" style="font-size: 0.6875rem; color: var(--doc-text-muted); margin: 0.125rem 0 0 0;">
                            {{ $letter->final_scan_uploaded_at ? 'Scan final oleh '.($letter->finalScanUploader?->name ?? 'Unit / Humas') : 'Berkas berstempel tersimpan' }}
                        </p>
                    @elseif($isWaitingSignature)
                        <h5 class="doc-stepper-name" style="font-size: 0.75rem; font-weight: 700; color: var(--doc-text-primary); margin: 0.125rem 0 0 0; font-family: ui-monospace, monospace;">
                            {{ $letter->letter_number }}
                        </h5>
                        <p class="doc-stepper-sub" style="font-size: 0.6875rem; color: var(--doc-badge-info-solid); margin: 0.125rem 0 0 0;">
                            Menunggu scan PDF (Unit / Humas)
                        </p>
                    @else
                        <p class="doc-stepper-sub" style="font-size: 0.6875rem; font-style: italic; color: var(--doc-text-subtle); margin: 0.125rem 0 0 0;">
                            Menunggu pengesahan Humas
                        </p>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Card 2: Berkas Fisik & Pratinjau Media Surat Keluar (Storage Privat) --}}
<div class="doc-card" style="background-color: var(--doc-surface); border: 1px solid var(--doc-border); border-radius: 0.5rem; padding: 1.25rem 1.5rem; margin-bottom: 1.25rem; box-shadow: var(--doc-shadow-sm); color: var(--doc-text-primary);">
    <div class="doc-card-header" style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 0.75rem; padding-bottom: 1rem; border-bottom: 1px solid var(--doc-border); margin-bottom: 1.25rem;">
        <div style="display: flex; align-items: center; gap: 0.625rem;">
            <div class="doc-card-title-icon" style="width: 2.25rem; height: 2.25rem; border-radius: 0.375rem; background-color: var(--doc-badge-info-bg); color: var(--doc-badge-info-solid); border: 1px solid var(--doc-badge-info-border); display: inline-flex; align-items: center; justify-content: center; flex-shrink: 0;">
                <x-heroicon-o-document-magnifying-glass class="doc-icon-md" style="width: 1.25rem; height: 1.25rem;" />
            </div>
            <div>
                <h3 class="doc-card-title" style="font-size: 0.9375rem; font-weight: 700; color: var(--doc-text-primary); margin: 0; line-height: 1.25rem; letter-spacing: -0.01em;">
                    Berkas Fisik & Pratinjau Media Surat Keluar
                </h3>
                <p class="doc-card-subtitle" style="font-size: 0.75rem; color: var(--doc-text-muted); margin: 0.125rem 0 0 0;">
                    Berkas surat keluar tersimpan di penyimpanan privat terenkripsi. Berkas berstempel diterbitkan setelah disahkan Humas.
                </p>
            </div>
        </div>

        @if($hasFile)
            @if($isStamped)
                <span class="doc-badge doc-badge-teal" style="display: inline-flex; align-items: center; gap: 0.35rem; font-size: 0.75rem; font-weight: 700; padding: 0.25rem 0.65rem; border-radius: 0.375rem; background-color: var(--doc-badge-teal-bg); color: var(--doc-badge-teal-text); border: 1px solid var(--doc-badge-teal-border);">
                    <x-heroicon-m-check-badge style="width: 0.95rem; height: 0.95rem;" />
                    Berkas Berstempel & QR Resmi Tersedia
                </span>
            @else
                <span class="doc-badge doc-badge-warning" style="display: inline-flex; align-items: center; gap: 0.35rem; font-size: 0.75rem; font-weight: 600; padding: 0.25rem 0.65rem; border-radius: 0.375rem; background-color: var(--doc-badge-warning-bg); color: var(--doc-badge-warning-text); border: 1px solid var(--doc-badge-warning-border);">
                    <x-heroicon-m-clock style="width: 0.95rem; height: 0.95rem;" />
                    Berkas Draf Pengajuan Unit (Belum Disahkan)
                </span>
            @endif
        @endif
    </div>

    @if($hasFile)
        {{-- File Metadata & Action Bar --}}
        <div class="doc-file-card" style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 1rem; padding: 1rem 1.25rem; background-color: var(--doc-surface-muted); border: 1px solid var(--doc-border); border-radius: 0.375rem;">
            <div style="display: flex; align-items: center; gap: 0.875rem; min-width: 0;">
                @if($isPdf)
                    <div class="doc-modal-file-icon doc-modal-file-icon-pdf" style="width: 2.75rem; height: 2.75rem; border-radius: 0.375rem;" title="Berkas PDF">
                        <x-heroicon-s-document-text class="doc-icon-md" style="width: 1.375rem; height: 1.375rem;" />
                    </div>
                @elseif($isWord)
                    <div class="doc-modal-file-icon doc-modal-file-icon-word" style="width: 2.75rem; height: 2.75rem; border-radius: 0.375rem;" title="Berkas Microsoft Word">
                        <x-heroicon-s-document class="doc-icon-md" style="width: 1.375rem; height: 1.375rem;" />
                    </div>
                @else
                    <div class="doc-modal-file-icon" style="width: 2.75rem; height: 2.75rem; border-radius: 0.375rem;" title="Berkas Surat">
                        <x-heroicon-s-paper-clip class="doc-icon-md" style="width: 1.375rem; height: 1.375rem;" />
                    </div>
                @endif

                <div style="min-width: 0;">
                    <div style="font-size: 0.875rem; font-weight: 600; color: var(--doc-text-primary); word-break: break-all; line-height: 1.25rem; letter-spacing: -0.01em;">
                        {{ $fileName }}
                    </div>
                    <div style="font-size: 0.75rem; color: var(--doc-text-muted); margin-top: 0.25rem; display: flex; align-items: center; flex-wrap: wrap; gap: 0.5rem;">
                        <span class="doc-modal-tag {{ $isPdf ? 'doc-modal-tag-pdf' : ($isWord ? 'doc-modal-tag-doc' : 'doc-badge-gray') }}">
                            {{ strtoupper($ext ?: 'BERKAS') }}
                        </span>
                        <span style="font-family: ui-monospace, SFMono-Regular, monospace; font-weight: 500;">
                            {{ number_format($fileSize / 1024, 1) }} KB
                        </span>
                        @if($letter->letter_number)
                            <span>•</span>
                            <span class="doc-modal-tag doc-modal-tag-mono" title="Nomor Surat Keluar Resmi">
                                <x-heroicon-m-tag class="doc-icon-xs" style="width: 0.75rem; height: 0.75rem; opacity: 0.7;" />
                                {{ $letter->letter_number }}
                            </span>
                        @endif
                        @if($isPublished)
                            <span>•</span>
                            <span style="color: var(--doc-badge-success-text); font-weight: 600;">Resmi & Siap Diunduh</span>
                        @endif
                    </div>
                </div>
            </div>

            {{-- Action Buttons --}}
            <div style="display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap;">
                <button
                    type="button"
                    wire:click="mountAction('preview')"
                    class="doc-btn doc-btn-primary"
                    style="display: inline-flex; align-items: center; gap: 0.375rem; padding: 0.4375rem 0.875rem; border-radius: 0.375rem; font-size: 0.75rem; font-weight: 600; cursor: pointer; border: 1px solid #1d4ed8; background-color: #2563eb; color: #ffffff;"
                >
                    <x-heroicon-m-eye class="doc-icon-xs" style="width: 0.875rem; height: 0.875rem;" />
                    <span>Pratinjau Layar Penuh</span>
                </button>

                @if($isPdf)
                    <a
                        href="{{ $streamUrl }}"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="doc-btn doc-btn-outlined"
                        style="display: inline-flex; align-items: center; gap: 0.375rem; padding: 0.4375rem 0.875rem; border-radius: 0.375rem; font-size: 0.75rem; font-weight: 600; text-decoration: none; border: 1px solid var(--doc-border-strong); background-color: var(--doc-surface); color: var(--doc-text-primary);"
                    >
                        <x-heroicon-m-arrow-top-right-on-square class="doc-icon-xs" style="width: 0.875rem; height: 0.875rem;" />
                        <span>Buka di Tab Baru</span>
                    </a>
                @endif

                <a
                    href="{{ $downloadUrl }}"
                    class="doc-btn doc-btn-outlined"
                    style="display: inline-flex; align-items: center; gap: 0.375rem; padding: 0.4375rem 0.875rem; border-radius: 0.375rem; font-size: 0.75rem; font-weight: 600; text-decoration: none; border: 1px solid var(--doc-border-strong); background-color: var(--doc-surface); color: var(--doc-text-primary);"
                >
                    <x-heroicon-m-arrow-down-tray class="doc-icon-xs" style="width: 0.875rem; height: 0.875rem;" />
                    <span>Unduh Berkas</span>
                </a>
            </div>
        </div>

        {{-- Embedded Interactive Media Preview Canvas --}}
        @if($isPdf)
            <div style="margin-top: 1rem; border-radius: 0.5rem; overflow: hidden; border: 1px solid var(--doc-border); background-color: var(--doc-surface-muted);">
                <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 0.5rem; padding: 0.625rem 1rem; background-color: var(--doc-surface); border-bottom: 1px solid var(--doc-border); font-size: 0.75rem;">
                    <div style="display: flex; align-items: center; gap: 0.5rem; font-weight: 600; color: var(--doc-text-primary);">
                        <x-heroicon-s-eye style="width: 1rem; height: 1rem; color: #2563eb;" />
                        <span>Pratinjau Interaktif Dokumen PDF (Langsung di Halaman)</span>
                    </div>
                    <span style="font-size: 0.7rem; color: var(--doc-text-muted);">
                        Gunakan kontrol di dalam bingkai untuk navigasi halaman, zoom, atau cetak
                    </span>
                </div>
                <div style="width: 100%; height: 680px; min-height: 540px; background-color: #525659; position: relative;">
                    <iframe
                        src="{{ $streamUrl }}#toolbar=1&navpanes=0"
                        style="width: 100%; height: 100%; border: none; display: block;"
                        title="Pratinjau Berkas Surat Keluar"
                        loading="lazy"
                    ></iframe>
                </div>
            </div>
        @elseif($isWord)
            <div class="doc-empty-state" style="margin-top: 1rem; padding: 2rem; border-radius: 0.375rem; background-color: var(--doc-surface-muted); border: 1px solid var(--doc-border); text-align: center;">
                <div class="doc-modal-file-icon doc-modal-file-icon-word" style="width: 3.25rem; height: 3.25rem; margin: 0 auto 1rem auto; border-radius: 0.5rem;">
                    <x-heroicon-s-document-arrow-down class="doc-icon-lg" style="width: 1.75rem; height: 1.75rem;" />
                </div>
                <h4 style="font-size: 0.9375rem; font-weight: 700; color: var(--doc-text-primary); margin-bottom: 0.375rem;">
                    Berkas Surat Microsoft Word (.{{ strtoupper($ext) }})
                </h4>
                <p style="font-size: 0.8125rem; color: var(--doc-text-muted); max-width: 30rem; margin: 0 auto 1.25rem auto; line-height: 1.45;">
                    Pratinjau visual langsung di browser dioptimalkan untuk format PDF. Silakan unduh berkas untuk membukanya menggunakan Microsoft Word atau LibreOffice.
                </p>
                <a
                    href="{{ $downloadUrl }}"
                    class="doc-btn doc-btn-primary"
                    style="display: inline-flex; align-items: center; gap: 0.5rem; padding: 0.5rem 1.25rem; border-radius: 0.375rem; font-size: 0.8125rem; font-weight: 600; text-decoration: none; border: 1px solid #1d4ed8; background-color: #2563eb; color: #ffffff;"
                >
                    <x-heroicon-m-arrow-down-tray style="width: 1rem; height: 1rem;" />
                    <span>Unduh Dokumen Word (.{{ strtoupper($ext) }})</span>
                </a>
            </div>
        @endif
    @else
        <div class="doc-empty-state" style="padding: 1.75rem; border-radius: 0.375rem; background-color: var(--doc-surface-muted); border: 1px solid var(--doc-border); text-align: center;">
            <div class="doc-empty-icon" style="margin-bottom: 0.75rem;">
                <x-heroicon-o-document-magnifying-glass class="doc-icon-lg" style="width: 2rem; height: 2rem; color: var(--doc-text-muted); margin: 0 auto;" />
            </div>
            <h4 style="font-size: 0.875rem; font-weight: 700; color: var(--doc-text-primary); margin: 0 0 0.25rem 0;">Berkas Fisik Belum Tersedia</h4>
            <p style="font-size: 0.75rem; color: var(--doc-text-muted); margin: 0;">Surat keluar ini belum memiliki berkas fisik yang diunggah.</p>
        </div>
    @endif
</div>

{{-- Card 3: Rincian Identitas Surat Keluar --}}
<div class="doc-card" style="background-color: var(--doc-surface); border: 1px solid var(--doc-border); border-radius: 0.5rem; padding: 1.25rem 1.5rem; box-shadow: var(--doc-shadow-sm); color: var(--doc-text-primary);">
    <div style="padding-bottom: 0.875rem; border-bottom: 1px solid var(--doc-border); margin-bottom: 1rem; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.5rem;">
        <div>
            <h4 style="font-size: 0.875rem; font-weight: 700; margin: 0; color: var(--doc-text-primary);">
                Rincian Identitas Surat Keluar
            </h4>
            <p style="font-size: 0.75rem; margin: 0.15rem 0 0 0; color: var(--doc-text-muted);">
                Informasi surat keluar unit kerja beserta nomor surat resmi yang diterbitkan per unit.
            </p>
        </div>
        @if($letter->stamped_file_path)
            <span style="display: inline-flex; align-items: center; gap: 0.35rem; font-size: 0.75rem; font-weight: 600; padding: 0.25rem 0.65rem; border-radius: 0.375rem; background-color: var(--doc-badge-success-bg); color: var(--doc-badge-success-text); border: 1px solid var(--doc-badge-success-border);">
                <x-heroicon-s-check-badge style="width: 0.95rem; height: 0.95rem;" />
                Berkas Berstempel & QR Resmi Tersedia
            </span>
        @endif
    </div>

    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 1rem 1.5rem; font-size: 0.8125rem;">
        <div>
            <div style="font-size: 0.7rem; font-weight: 600; text-transform: uppercase; color: var(--doc-text-muted); letter-spacing: 0.03em;">Nomor Surat Keluar Resmi</div>
            <div style="font-weight: 700; font-family: ui-monospace, monospace; margin-top: 0.2rem; color: var(--doc-text-primary);">
                {{ $letter->letter_number ?: '(Menunggu Penomoran Humas)' }}
            </div>
        </div>

        <div>
            <div style="font-size: 0.7rem; font-weight: 600; text-transform: uppercase; color: var(--doc-text-muted); letter-spacing: 0.03em;">Unit Pengaju</div>
            <div style="font-weight: 600; margin-top: 0.2rem; color: var(--doc-text-primary);">
                {{ $letter->unit?->name ?? '-' }} ({{ $letter->unit?->code ?? '-' }})
            </div>
        </div>

        <div>
            <div style="font-size: 0.7rem; font-weight: 600; text-transform: uppercase; color: var(--doc-text-muted); letter-spacing: 0.03em;">Tujuan Surat (Instansi / Pihak Eksternal)</div>
            <div style="font-weight: 600; margin-top: 0.2rem; color: var(--doc-text-primary);">{{ $letter->destination_organization }}</div>
        </div>

        <div>
            <div style="font-size: 0.7rem; font-weight: 600; text-transform: uppercase; color: var(--doc-text-muted); letter-spacing: 0.03em;">Tanggal Surat</div>
            <div style="margin-top: 0.2rem; color: var(--doc-text-primary);">{{ $letter->letter_date?->translatedFormat('d F Y') ?? '-' }}</div>
        </div>

        <div>
            <div style="font-size: 0.7rem; font-weight: 600; text-transform: uppercase; color: var(--doc-text-muted); letter-spacing: 0.03em;">Diajukan Oleh</div>
            <div style="margin-top: 0.2rem; color: var(--doc-text-primary);">{{ $letter->creator?->name ?? '-' }}</div>
        </div>

        <div>
            <div style="font-size: 0.7rem; font-weight: 600; text-transform: uppercase; color: var(--doc-text-muted); letter-spacing: 0.03em;">Ditinjau & Dinomori Oleh</div>
            <div style="margin-top: 0.2rem; color: var(--doc-text-primary);">{{ $letter->reviewer?->name ?: 'Belum Ditinjau' }}</div>
        </div>

        <div style="grid-column: 1 / -1;">
            <div style="font-size: 0.7rem; font-weight: 600; text-transform: uppercase; color: var(--doc-text-muted); letter-spacing: 0.03em;">Perihal Surat</div>
            <div style="font-weight: 700; font-size: 0.9rem; margin-top: 0.2rem; color: var(--doc-text-primary);">{{ $letter->subject }}</div>
        </div>

        @if(filled($letter->description))
            <div style="grid-column: 1 / -1;">
                <div style="font-size: 0.7rem; font-weight: 600; text-transform: uppercase; color: var(--doc-text-muted); letter-spacing: 0.03em;">Keterangan Singkat</div>
                <div style="margin-top: 0.25rem; padding: 0.65rem 0.85rem; background-color: var(--doc-surface-muted); border: 1px solid var(--doc-border); border-radius: 0.375rem; color: var(--doc-text-secondary); line-height: 1.45;">
                    {{ $letter->description }}
                </div>
            </div>
        @endif
    </div>
</div>

