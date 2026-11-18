@php
    $activePath = $filePath ?? null;
    $ext = $activePath ? strtolower(pathinfo($activePath, PATHINFO_EXTENSION)) : '';
    $isPdf = ($ext === 'pdf');
    $isWordSource = in_array($ext, ['doc', 'docx'], true);
    $pdfPreviewUrl = ($isPdf && ! empty($streamUrl)) ? $streamUrl : null;
    $isWord = ! $isPdf && $isWordSource;
@endphp

@include('filament.components.document-timeline-styles')

<div class="doc-modal-container">
    <div class="doc-modal-toolbar">
        <div class="doc-modal-file-info">
            @if ($isPdf)
                <div class="doc-modal-file-icon doc-modal-file-icon-pdf" title="Berkas PDF">
                    <x-heroicon-s-document-text class="doc-icon-md" style="width: 1.375rem; height: 1.375rem;" />
                </div>
            @elseif ($isWord)
                <div class="doc-modal-file-icon doc-modal-file-icon-word" title="Berkas Microsoft Word">
                    <x-heroicon-s-document class="doc-icon-md" style="width: 1.375rem; height: 1.375rem;" />
                </div>
            @else
                <div class="doc-modal-file-icon" title="Berkas Surat">
                    <x-heroicon-s-paper-clip class="doc-icon-md" style="width: 1.375rem; height: 1.375rem;" />
                </div>
            @endif

            <div class="doc-modal-file-content">
                <div class="doc-modal-file-name">
                    {{ $fileName ?? $title ?? 'Berkas Surat' }}
                </div>

                <div class="doc-modal-meta-row">
                    <span class="doc-modal-tag {{ $isPdf ? 'doc-modal-tag-pdf' : ($isWord ? 'doc-modal-tag-doc' : 'doc-badge-gray') }}">
                        @if ($isWordSource && $isPdf)
                            {{ strtoupper($ext) }} &rarr; PDF
                        @else
                            {{ strtoupper($ext ?: 'BERKAS') }}
                        @endif
                    </span>

                    @if (!empty($letterNumber))
                        <span>•</span>
                        <span class="doc-modal-tag doc-modal-tag-mono">
                            <x-heroicon-m-hashtag class="doc-icon-xs" style="width: 0.75rem; height: 0.75rem; opacity: 0.7;" />
                            {{ $letterNumber }}
                        </span>
                    @endif

                    @if (!empty($isStamped))
                        <span>•</span>
                        <span class="doc-badge doc-badge-teal" style="font-size: 0.6875rem; font-weight: 500; padding: 0.125rem 0.5rem;">
                            <x-heroicon-s-check-badge class="doc-icon-xs" style="width: 0.8125rem; height: 0.8125rem;" />
                            Stempel Pojok Kanan Atas & QR Aktif
                        </span>
                    @endif
                </div>
            </div>
        </div>

        <div class="doc-modal-actions">
            @if ($isPdf && !empty($pdfPreviewUrl))
                <a
                    href="{{ $pdfPreviewUrl }}"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="doc-modal-btn doc-modal-btn-secondary"
                >
                    <x-heroicon-m-arrow-top-right-on-square class="doc-icon-xs" style="width: 0.9375rem; height: 0.9375rem;" />
                    <span>Buka di Tab Baru</span>
                </a>
            @endif

            @if (!empty($downloadUrl))
                <a
                    href="{{ $downloadUrl }}"
                    class="doc-modal-btn doc-modal-btn-primary"
                >
                    <x-heroicon-m-arrow-down-tray class="doc-icon-xs" style="width: 0.9375rem; height: 0.9375rem;" />
                    <span>Unduh Berkas</span>
                </a>
            @endif
        </div>
    </div>

    @if ($isPdf && !empty($pdfPreviewUrl))
        <div class="doc-modal-frame-wrap">
            <iframe
                src="{{ $pdfPreviewUrl }}#toolbar=1&navpanes=0"
                class="doc-modal-iframe"
                title="Pratinjau Surat PDF"
                loading="lazy"
            ></iframe>
        </div>
    @elseif ($activePath && $isWord && !empty($streamUrl))
        @include('filament.components.word-preview-stage', [
            'streamUrl' => $streamUrl,
            'downloadUrl' => $downloadUrl ?? null,
            'extension' => $ext ?: 'docx',
            'canDownload' => ! empty($downloadUrl),
        ])
    @elseif ($activePath)
        <div class="doc-modal-empty-box">
            <div class="doc-modal-file-icon doc-modal-file-icon-word" style="width: 3.5rem; height: 3.5rem; margin-bottom: 1rem; border-radius: 0.5rem;">
                <x-heroicon-s-document-arrow-down class="doc-icon-lg" style="width: 2rem; height: 2rem;" />
            </div>
            <h4 style="font-size: 1rem; font-weight: 700; color: var(--doc-text-primary); margin-bottom: 0.5rem;">
                Berkas Surat (.{{ strtoupper($ext) }})
            </h4>
            <p style="font-size: 0.8125rem; color: var(--doc-text-muted); max-width: 32rem; margin-bottom: 1.5rem; line-height: 1.45;">
                Pratinjau langsung di browser dikhususkan untuk format PDF. Silakan unduh berkas surat ini untuk membukanya.
            </p>
            @if (!empty($downloadUrl))
                <a
                    href="{{ $downloadUrl }}"
                    class="doc-modal-btn doc-modal-btn-primary"
                    style="padding: 0.5625rem 1.25rem;"
                >
                    <x-heroicon-m-arrow-down-tray class="doc-icon-sm" style="width: 1rem; height: 1rem;" />
                    <span>Unduh Berkas (.{{ strtoupper($ext) }})</span>
                </a>
            @endif
        </div>
    @else
        <div class="doc-modal-empty-box">
            <h4 class="doc-empty-title">Berkas Fisik Belum Tersedia</h4>
            <p class="doc-empty-text">Surat ini belum memiliki berkas fisik yang diunggah.</p>
        </div>
    @endif
</div>

