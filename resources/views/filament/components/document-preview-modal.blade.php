@php
    $primaryFile = $document->primaryFile ?? $document->files()->where('is_active', true)->first();
    $effectivePath = $primaryFile?->getEffectiveFilePath();
    $effectiveExt = $effectivePath
        ? strtolower((string) pathinfo($effectivePath, PATHINFO_EXTENSION))
        : strtolower((string) ($primaryFile?->file_extension ?? ''));
    $originalExt = strtolower((string) ($primaryFile?->file_extension ?? $effectiveExt));

    $isWordSource = in_array($originalExt, ['doc', 'docx'], true);
    $isPdf = ($effectiveExt === 'pdf') || ($primaryFile && $primaryFile->mime_type === 'application/pdf' && ! in_array($effectiveExt, ['doc', 'docx'], true));
    $pdfPreviewUrl = $isPdf ? $streamUrl : null;
    $isWord = ! $isPdf && in_array($effectiveExt, ['doc', 'docx'], true);
    $canDownload = auth()->user()?->can('downloadPrivate', $document);
@endphp

@include('filament.components.document-timeline-styles')

<div class="doc-modal-container">
    {{-- Solid Enterprise Inspection Bar --}}
    <div class="doc-modal-toolbar">
        <div class="doc-modal-file-info">
            {{-- Solid File Icon Badge --}}
            @if ($isPdf)
                <div class="doc-modal-file-icon doc-modal-file-icon-pdf" title="Berkas PDF">
                    <x-heroicon-s-document-text class="doc-icon-md" style="width: 1.375rem; height: 1.375rem;" />
                </div>
            @elseif ($isWord)
                <div class="doc-modal-file-icon doc-modal-file-icon-word" title="Berkas Microsoft Word">
                    <x-heroicon-s-document class="doc-icon-md" style="width: 1.375rem; height: 1.375rem;" />
                </div>
            @else
                <div class="doc-modal-file-icon" title="Berkas Naskah">
                    <x-heroicon-s-paper-clip class="doc-icon-md" style="width: 1.375rem; height: 1.375rem;" />
                </div>
            @endif

            <div class="doc-modal-file-content">
                {{-- Standardized Filename --}}
                <div class="doc-modal-file-name">
                    {{ $primaryFile?->standardized_file_name ?? $primaryFile?->original_file_name ?? $document->title }}
                </div>

                {{-- Technical Metadata Chips --}}
                <div class="doc-modal-meta-row">
                    <span class="doc-modal-tag {{ $isPdf ? 'doc-modal-tag-pdf' : ($isWord ? 'doc-modal-tag-doc' : 'doc-badge-gray') }}">
                        @if ($isWordSource && $isPdf)
                            {{ strtoupper($originalExt) }} &rarr; PDF
                        @else
                            {{ strtoupper($effectiveExt ?: ($primaryFile?->file_extension ?? 'BERKAS')) }}
                        @endif
                    </span>

                    <span style="font-family: ui-monospace, SFMono-Regular, monospace; font-weight: 500;">
                        {{ number_format(($primaryFile?->file_size ?? 0) / 1024, 1) }} KB
                    </span>

                    @if ($primaryFile?->page_count)
                        <span>•</span>
                        <span>{{ $primaryFile->page_count }} Halaman</span>
                    @endif

                    @if ($document->document_number)
                        <span>•</span>
                        <span class="doc-modal-tag doc-modal-tag-mono" title="Nomor Registrasi Surat Resmi">
                            <x-heroicon-m-hashtag class="doc-icon-xs" style="width: 0.75rem; height: 0.75rem; opacity: 0.7;" />
                            {{ $document->document_number }}
                        </span>
                    @endif

                    @if ($primaryFile?->stamped_file_path)
                        <span>•</span>
                        <span class="doc-badge doc-badge-teal" style="font-size: 0.6875rem; font-weight: 500; padding: 0.125rem 0.5rem;">
                            <x-heroicon-s-check-badge class="doc-icon-xs" style="width: 0.8125rem; height: 0.8125rem;" />
                            Berstempel Sah & QR
                        </span>
                    @endif
                </div>
            </div>
        </div>

        {{-- High-Precision Action Buttons --}}
        <div class="doc-modal-actions">
            @if ($isPdf && filled($pdfPreviewUrl))
                <a
                    href="{{ $pdfPreviewUrl }}"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="doc-modal-btn doc-modal-btn-secondary"
                    title="Buka dokumen di tab browser terpisah"
                >
                    <x-heroicon-m-arrow-top-right-on-square class="doc-icon-xs" style="width: 0.9375rem; height: 0.9375rem;" />
                    <span>Buka di Tab Baru</span>
                </a>
            @endif

            @if ($canDownload)
                <a
                    href="{{ $downloadUrl }}"
                    class="doc-modal-btn doc-modal-btn-primary"
                    title="Unduh berkas fisik terenkripsi"
                >
                    <x-heroicon-m-arrow-down-tray class="doc-icon-xs" style="width: 0.9375rem; height: 0.9375rem;" />
                    <span>Unduh Berkas</span>
                </a>
            @endif
        </div>
    </div>

    {{-- Main Preview Workstation Canvas --}}
    @if ($isPdf && filled($pdfPreviewUrl))
        <div class="doc-modal-frame-wrap">
            <iframe
                src="{{ $pdfPreviewUrl }}#toolbar=1&navpanes=0"
                class="doc-modal-iframe"
                title="Pratinjau Dokumen PDF"
                loading="lazy"
            ></iframe>
        </div>
    @elseif ($primaryFile && $isWord)
        @include('filament.components.word-preview-stage', [
            'streamUrl' => $streamUrl,
            'downloadUrl' => $downloadUrl,
            'extension' => $effectiveExt ?: ($primaryFile->file_extension ?? 'docx'),
            'canDownload' => $canDownload,
        ])
    @elseif ($primaryFile)
        <div class="doc-modal-empty-box">
            <div class="doc-modal-file-icon doc-modal-file-icon-word" style="width: 3.5rem; height: 3.5rem; margin-bottom: 1rem; border-radius: 0.5rem;">
                <x-heroicon-s-document-arrow-down class="doc-icon-lg" style="width: 2rem; height: 2rem;" />
            </div>
            <h4 style="font-size: 1rem; font-weight: 700; color: var(--doc-text-primary); margin-bottom: 0.5rem;">
                Berkas Naskah Dinas Microsoft Word (.{{ $primaryFile->file_extension }})
            </h4>
            <p style="font-size: 0.8125rem; color: var(--doc-text-muted); max-width: 32rem; margin-bottom: 1.5rem; line-height: 1.45;">
                Pratinjau visual langsung di browser dikhususkan untuk format PDF. Berkas berbasis Microsoft Word dapat diunduh untuk disunting menggunakan aplikasi pengolah kata.
            </p>
            @if ($canDownload)
                <a
                    href="{{ $downloadUrl }}"
                    class="doc-modal-btn doc-modal-btn-primary"
                    style="padding: 0.5625rem 1.25rem;"
                >
                    <x-heroicon-m-arrow-down-tray class="doc-icon-sm" style="width: 1rem; height: 1rem;" />
                    <span>Unduh Dokumen Word (.{{ strtoupper($primaryFile->file_extension) }})</span>
                </a>
            @else
                <div style="font-size: 0.75rem; color: var(--doc-badge-warning-text); background-color: var(--doc-badge-warning-bg); padding: 0.5rem 1rem; border-radius: 0.375rem; border: 1px solid var(--doc-badge-warning-border);">
                    Anda tidak memiliki izin khusus (<code>document.download_private</code>) untuk mengunduh berkas fisik ini.
                </div>
            @endif
        </div>
    @else
        <div class="doc-modal-empty-box">
            <div class="doc-empty-icon" style="margin-bottom: 1rem;">
                <x-heroicon-o-document-magnifying-glass class="doc-icon-lg" />
            </div>
            <h4 class="doc-empty-title">Berkas Fisik Belum Tersedia</h4>
            <p class="doc-empty-text">Dokumen ini belum memiliki berkas fisik yang diunggah ke penyimpanan privat.</p>
        </div>
    @endif
</div>
