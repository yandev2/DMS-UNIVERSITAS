@props([
    'streamUrl',
    'downloadUrl' => null,
    'extension' => 'docx',
    'canDownload' => true,
])

@php
    $hasWordBundle = is_file(public_path('build/manifest.json')) || is_file(public_path('hot'));
    $extUpper = strtoupper((string) $extension);
@endphp

@if ($hasWordBundle && strtolower((string) $extension) === 'docx' && filled($streamUrl))
    <div
        class="doc-word-preview-wrap"
        data-word-preview-root
        data-stream-url="{{ $streamUrl }}"
        x-data
        x-init="if (window.dmsMountWordPreview) { window.dmsMountWordPreview($el); }"
    >
        <div class="doc-word-preview-loading" data-word-loading>
            <div class="doc-modal-file-icon doc-modal-file-icon-word" style="width: 3rem; height: 3rem; margin-bottom: 0.75rem; border-radius: 0.5rem;">
                <x-heroicon-s-document-text class="doc-icon-md" style="width: 1.5rem; height: 1.5rem;" />
            </div>
            <h4 style="font-size: 0.9375rem; font-weight: 700; color: var(--doc-text-primary); margin: 0;">
                Memuat Pratinjau Dokumen Word (.DOCX)...
            </h4>
            <p style="font-size: 0.8125rem; color: var(--doc-text-muted); margin: 0.375rem 0 0 0;">
                Merender halaman dokumen di browser secara aman.
            </p>
            <progress class="doc-word-preview-progress" aria-label="Memuat pratinjau dokumen Word"></progress>
        </div>

        <div class="doc-word-preview-error" data-word-error role="alert" hidden>
            <div class="doc-modal-file-icon doc-modal-file-icon-word" style="width: 3.25rem; height: 3.25rem; margin-bottom: 1rem; border-radius: 0.5rem;">
                <x-heroicon-s-document-arrow-down class="doc-icon-lg" style="width: 1.75rem; height: 1.75rem;" />
            </div>
            <h4 style="font-size: 1rem; font-weight: 700; color: var(--doc-text-primary); margin: 0 0 0.5rem 0;">
                Pratinjau Browser Tidak Tersedia (.{{ $extUpper }})
            </h4>
            <p data-word-error-message style="font-size: 0.8125rem; color: var(--doc-text-muted); max-width: 30rem; margin: 0 0 1.25rem 0; line-height: 1.45;">
                Berkas Microsoft Word tidak dapat dirender langsung di browser. Silakan unduh berkas untuk membukanya.
            </p>
            @if ($canDownload && filled($downloadUrl))
                <a
                    href="{{ $downloadUrl }}"
                    class="doc-modal-btn doc-modal-btn-primary"
                    style="padding: 0.5625rem 1.25rem;"
                >
                    <x-heroicon-m-arrow-down-tray class="doc-icon-sm" style="width: 1rem; height: 1rem;" />
                    <span>Unduh Dokumen Word (.{{ $extUpper }})</span>
                </a>
            @endif
        </div>

        <div class="doc-word-preview-stage" data-word-stage hidden></div>
    </div>
@else
    <div class="doc-modal-empty-box">
        <div class="doc-modal-file-icon doc-modal-file-icon-word" style="width: 3.5rem; height: 3.5rem; margin-bottom: 1rem; border-radius: 0.5rem;">
            <x-heroicon-s-document-arrow-down class="doc-icon-lg" style="width: 2rem; height: 2rem;" />
        </div>
        <h4 style="font-size: 1rem; font-weight: 700; color: var(--doc-text-primary); margin-bottom: 0.5rem;">
            Berkas Naskah Dinas Microsoft Word (.{{ $extUpper }})
        </h4>
        <p style="font-size: 0.8125rem; color: var(--doc-text-muted); max-width: 32rem; margin-bottom: 1.5rem; line-height: 1.45;">
            Pratinjau visual langsung di browser mendukung format PDF dan Word modern (.DOCX). Untuk berkas .{{ $extUpper }}, silakan unduh berkas untuk membukanya menggunakan aplikasi pengolah kata.
        </p>
        @if ($canDownload && filled($downloadUrl))
            <a
                href="{{ $downloadUrl }}"
                class="doc-modal-btn doc-modal-btn-primary"
                style="padding: 0.5625rem 1.25rem;"
            >
                <x-heroicon-m-arrow-down-tray class="doc-icon-sm" style="width: 1rem; height: 1rem;" />
                <span>Unduh Dokumen Word (.{{ $extUpper }})</span>
            </a>
        @else
            <div style="font-size: 0.75rem; color: var(--doc-badge-warning-text); background-color: var(--doc-badge-warning-bg); padding: 0.5rem 1rem; border-radius: 0.375rem; border: 1px solid var(--doc-badge-warning-border);">
                Anda tidak memiliki izin khusus (<code>document.download_private</code>) untuk mengunduh berkas fisik ini.
            </div>
        @endif
    </div>
@endif

