@php
    $devName = \App\Support\Security\RuntimeIntegrityManifest::getDeveloperName();
    $devEmail = \App\Support\Security\RuntimeIntegrityManifest::getDeveloperEmail();
    $devWeb = \App\Support\Security\RuntimeIntegrityManifest::getDeveloperWebsite();
    $copyright = \App\Support\Security\RuntimeIntegrityManifest::getCopyrightNotice();
@endphp

@once
<style>
.fi-panel-footer {
    width: 100% !important;
    margin-top: 1.5rem !important;
    padding: 1rem 1.25rem !important;
    border-top: 1px solid #e2e8f0 !important;
    background: transparent !important;
    box-sizing: border-box !important;
    transition: background-color 0.2s ease, border-color 0.2s ease !important;
}

.dark .fi-panel-footer,
html.dark .fi-panel-footer,
[data-theme="dark"] .fi-panel-footer {
    border-top: 1px solid rgba(255, 255, 255, 0.08) !important;
}

.fi-panel-footer-inner {
    display: flex !important;
    flex-direction: column !important;
    align-items: center !important;
    justify-content: center !important;
    gap: 0.5rem !important;
    font-size: 0.72rem !important;
    line-height: 1.2rem !important;
    color: #64748b !important;
    width: 100% !important;
    box-sizing: border-box !important;
    text-align: center !important;
}

/* Tablet & Laptop (termasuk 13 & 14 inch): Strict Single Horizontal Line */
@media (min-width: 768px) {
    .fi-panel-footer-inner {
        flex-direction: row !important;
        flex-wrap: nowrap !important;
        justify-content: space-between !important;
        align-items: center !important;
        gap: 0.75rem !important;
        text-align: left !important;
    }
}

.dark .fi-panel-footer-inner,
html.dark .fi-panel-footer-inner,
[data-theme="dark"] .fi-panel-footer-inner {
    color: #94a3b8 !important;
}

.fi-panel-footer-copyright {
    text-align: center !important;
    font-weight: 500 !important;
    white-space: normal !important;
}

@media (min-width: 768px) {
    .fi-panel-footer-copyright {
        text-align: left !important;
        white-space: nowrap !important;
        flex-shrink: 0 !important;
    }
}

.fi-panel-footer-author {
    display: flex !important;
    flex-wrap: wrap !important;
    align-items: center !important;
    justify-content: center !important;
    gap: 0.35rem !important;
    text-align: center !important;
}

@media (min-width: 768px) {
    .fi-panel-footer-author {
        flex-wrap: nowrap !important;
        white-space: nowrap !important;
        flex-shrink: 0 !important;
        justify-content: flex-end !important;
        text-align: right !important;
    }
}

.fi-panel-footer-author-name {
    font-weight: 700 !important;
    color: #1e293b !important;
    letter-spacing: 0.02em !important;
}

.dark .fi-panel-footer-author-name,
html.dark .fi-panel-footer-author-name,
[data-theme="dark"] .fi-panel-footer-author-name {
    color: #f1f5f9 !important;
}

.fi-panel-footer-link {
    color: #2563eb !important;
    text-decoration: none !important;
    font-weight: 600 !important;
    display: inline-flex !important;
    align-items: center !important;
    gap: 0.25rem !important;
    white-space: nowrap !important;
    transition: color 0.15s ease !important;
}

.fi-panel-footer-link:hover {
    color: #1d4ed8 !important;
    text-decoration: underline !important;
}

.dark .fi-panel-footer-link,
html.dark .fi-panel-footer-link,
[data-theme="dark"] .fi-panel-footer-link {
    color: #60a5fa !important;
}

.dark .fi-panel-footer-link:hover,
html.dark .fi-panel-footer-link:hover,
[data-theme="dark"] .fi-panel-footer-link:hover {
    color: #93c5fd !important;
}

.fi-panel-footer-web-link {
    color: #475569 !important;
    text-decoration: none !important;
    font-weight: 600 !important;
    display: inline-flex !important;
    align-items: center !important;
    gap: 0.25rem !important;
    white-space: nowrap !important;
    transition: color 0.15s ease !important;
}

.fi-panel-footer-web-link:hover {
    color: #0f172a !important;
    text-decoration: underline !important;
}

.dark .fi-panel-footer-web-link,
html.dark .fi-panel-footer-web-link,
[data-theme="dark"] .fi-panel-footer-web-link {
    color: #cbd5e1 !important;
}

.dark .fi-panel-footer-web-link:hover,
html.dark .fi-panel-footer-web-link:hover,
[data-theme="dark"] .fi-panel-footer-web-link:hover {
    color: #f8fafc !important;
}

.fi-panel-footer-sep {
    color: #94a3b8 !important;
    opacity: 0.8 !important;
}

.dark .fi-panel-footer-sep,
html.dark .fi-panel-footer-sep,
[data-theme="dark"] .fi-panel-footer-sep {
    color: #475569 !important;
}
</style>
@endonce

<footer class="fi-panel-footer" style="width: 100%; margin-top: 1.5rem; padding: 1rem 1.25rem; border-top: 1px solid var(--doc-border, #e2e8f0); box-sizing: border-box;">
    <div class="fi-panel-footer-inner" style="display: flex; align-items: center; justify-content: space-between; gap: 0.75rem; font-size: 0.72rem; line-height: 1.2rem;">
        <!-- Copyright Notice -->
        <div class="fi-panel-footer-copyright" style="font-weight: 500;">
            {{ $copyright }}
        </div>

        <!-- Developer Attribution & Contacts -->
        <div class="fi-panel-footer-author" style="display: flex; align-items: center; gap: 0.35rem;">
            <span>Dikembangkan oleh:</span>
            <span class="fi-panel-footer-author-name" style="font-weight: 700; letter-spacing: 0.02em;">{{ $devName }}</span>
            <span class="fi-panel-footer-sep">•</span>
            <a href="mailto:{{ $devEmail }}" class="fi-panel-footer-link" style="font-weight: 600; display: inline-flex; align-items: center; gap: 0.25rem;">
                <svg style="width: 0.85rem; height: 0.85rem; flex-shrink: 0;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                </svg>
                <span>{{ $devEmail }}</span>
            </a>
            @if($devWeb)
                <span class="fi-panel-footer-sep">•</span>
                <a href="{{ $devWeb }}" target="_blank" rel="noopener noreferrer" class="fi-panel-footer-web-link" style="font-weight: 600; display: inline-flex; align-items: center; gap: 0.25rem;">
                    <svg style="width: 0.85rem; height: 0.85rem; flex-shrink: 0;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                    </svg>
                    <span>Portofolio / Web</span>
                </a>
            @endif
        </div>
    </div>
</footer>
