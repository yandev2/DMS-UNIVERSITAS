@php
    /** @var \App\Models\DocumentCategory $category */
    $unitCode = $unitCode ?? null;
    $categoryPattern = filled($category->number_format)
        ? (string) $category->number_format
        : '{seq}/UNIVBI.{unit}/{cat}/{roman_month}/{year}';

    $sampleUnit = filled($unitCode) ? strtoupper((string) $unitCode) : 'UNIT';
    $sampleCat = strtoupper((string) ($category->code ?: 'KAT'));
    $samplePreview = str_replace(
        ['{seq}', '{unit}', '{cat}', '{roman_month}', '{month}', '{year}'],
        ['001', $sampleUnit, $sampleCat, 'XI', date('m'), date('Y')],
        $categoryPattern,
    );

    $flags = [
        [
            'id' => 'pattern-category',
            'flag' => $categoryPattern,
            'label' => 'Pola Nomor Resmi Kategori',
            'badge' => 'Utama',
            'description' => "Tempel pada baris Nomor Surat di berkas Word (.docx). Contoh hasil: {$samplePreview}",
            'primary' => true,
        ],
        [
            'id' => 'nomor-surat',
            'flag' => '${NOMOR_SURAT}',
            'label' => 'Tag Nomor Surat Ringkas',
            'badge' => 'Nomor',
            'description' => 'Alternatif singkat (atau {nomor_surat}) untuk diisi nomor surat resmi.',
            'primary' => false,
        ],
        [
            'id' => 'tanggal-sah',
            'flag' => '${TANGGAL_SAH}',
            'label' => 'Tanggal Pengesahan',
            'badge' => 'Tanggal',
            'description' => 'Otomatis diisi tanggal terbit/ACC resmi (atau {tanggal_sah}).',
            'primary' => false,
        ],
        [
            'id' => 'pejabat',
            'flag' => '${PEJABAT}',
            'label' => 'Pejabat Penandatangan',
            'badge' => 'Pengesah',
            'description' => 'Otomatis diisi nama & gelar pejabat pengesah akhir (atau {pejabat}).',
            'primary' => false,
        ],
        [
            'id' => 'verify-url',
            'flag' => '${VERIFY_URL}',
            'label' => 'Tautan Verifikasi QR',
            'badge' => 'Opsional',
            'description' => 'Tautan halaman verifikasi publik keabsahan dokumen.',
            'primary' => false,
        ],
        [
            'id' => 'uuid',
            'flag' => '${UUID}',
            'label' => 'UUID Dokumen',
            'badge' => 'Opsional',
            'description' => 'Identitas unik dokumen pada sistem e-Arsip.',
            'primary' => false,
        ],
    ];
@endphp

<div class="doc-flag-panel" x-data="{
    init() {
        if (window.__docFlagCopyInitialized) {
            return;
        }
        window.__docFlagCopyInitialized = true;

        const timers = new WeakMap();
        let lastMessage = '';

        function legacyCopy(text) {
            const field = Object.assign(document.createElement('textarea'), { value: text });
            field.setAttribute('readonly', '');
            field.style.cssText = 'position:fixed;inset-block-start:0;opacity:0';
            document.body.append(field);
            field.select();
            try {
                return document.execCommand('copy');
            } finally {
                field.remove();
            }
        }

        function report(button, ok) {
            const tip = button.nextElementSibling;
            if (!tip) {
                return;
            }

            clearTimeout(timers.get(button));
            button.removeAttribute('data-copied');
            button.removeAttribute('data-failed');

            let message = ok ? 'Tersalin' : 'Gagal salin';
            if (message === lastMessage) {
                message += '\u00A0';
            }
            lastMessage = message;

            tip.textContent = '';
            void button.offsetWidth;
            button.setAttribute(ok ? 'data-copied' : 'data-failed', '');
            requestAnimationFrame(() => {
                tip.textContent = message;
            });

            timers.set(button, setTimeout(() => {
                button.removeAttribute('data-copied');
                button.removeAttribute('data-failed');
                tip.textContent = '';
            }, 2000));
        }

        async function copyFromTarget(button, target) {
            const text = target.matches('input, textarea') ?
                target.value :
                target.innerText.trim();

            if (!text) {
                return;
            }

            try {
                if (navigator.clipboard && typeof navigator.clipboard.writeText === 'function') {
                    await navigator.clipboard.writeText(text);
                } else if (!legacyCopy(text)) {
                    throw new Error('Clipboard unavailable');
                }
                report(button, true);
            } catch {
                if (legacyCopy(text)) {
                    report(button, true);
                } else {
                    report(button, false);
                }
            }
        }

        if ('CommandEvent' in window) {
            document.addEventListener('command', (event) => {
                if (event.command === '--copy' && event.source && event.target) {
                    copyFromTarget(event.source, event.target);
                }
            }, true);
        } else {
            document.addEventListener('click', (event) => {
                const button = event.target.closest('button[command=&quot;--copy&quot;]');
                if (!button) {
                    return;
                }
                const target = document.getElementById(button.getAttribute('commandfor'));
                if (target) {
                    copyFromTarget(button, target);
                }
            });
        }
    }
}">
    <style>
        .doc-flag-panel {
            border: 1px solid var(--doc-border, #e2e8f0);
            border-radius: 0.75rem;
            background-color: var(--doc-surface-muted, #f8fafc);
            padding: 0.875rem 1rem;
        }

        .doc-flag-panel-head {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            gap: 0.5rem;
            margin-bottom: 0.625rem;
        }

        .doc-flag-panel-title {
            font-size: 0.8125rem;
            font-weight: 700;
            color: var(--doc-text-primary, #0f172a);
            margin: 0;
        }

        .doc-flag-panel-sub {
            font-size: 0.75rem;
            color: var(--doc-text-muted, #64748b);
            margin: 0.125rem 0 0;
        }

        .doc-flag-cat-pill {
            display: inline-flex;
            align-items: center;
            padding: 0.1875rem 0.5rem;
            border-radius: 5px;
            font-size: 0.6875rem;
            font-weight: 700;
            background-color: var(--doc-badge-info-bg, #f0f9ff);
            border: 1px solid var(--doc-badge-info-border, #bae6fd);
            color: var(--doc-badge-info-text, #0369a1);
        }

        .doc-flag-list {
            list-style: none;
            margin: 0;
            padding: 0;
            display: grid;
            grid-template-columns: repeat(1, minmax(0, 1fr));
            gap: 0.5rem;
        }

        @media (min-width: 768px) {
            .doc-flag-list {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .doc-flag-item-primary {
                grid-column: span 2 / span 2;
            }
        }

        .doc-flag-item {
            display: flex;
            flex-direction: column;
            gap: 0.375rem;
            padding: 0.625rem 0.75rem;
            border-radius: 0.5rem;
            border: 1px solid var(--doc-border, #e2e8f0);
            background-color: var(--doc-surface, #ffffff);
        }

        .doc-flag-item-primary {
            border-color: var(--doc-badge-info-border, #bae6fd);
            background-color: var(--doc-badge-info-bg, #f0f9ff);
        }

        .doc-flag-item-meta {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 0.5rem;
        }

        .doc-flag-item-label {
            font-size: 0.75rem;
            font-weight: 600;
            color: var(--doc-text-secondary, #334155);
        }

        .doc-flag-item-badge {
            font-size: 0.625rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            color: var(--doc-text-muted, #64748b);
        }

        .doc-flag-code-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 0.5rem;
            min-width: 0;
        }

        .doc-flag-code {
            flex: 1 1 auto;
            min-width: 0;
            overflow-x: auto;
            white-space: nowrap;
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
            font-size: 0.78125rem;
            font-weight: 600;
            color: var(--doc-text-primary, #0f172a);
            background-color: var(--doc-surface-subtle, #f1f5f9);
            border: 1px solid var(--doc-border, #e2e8f0);
            border-radius: 0.375rem;
            padding: 0.3125rem 0.5rem;
        }

        .doc-flag-copy-wrap {
            position: relative;
            display: inline-flex;
            align-items: center;
            gap: 0.375rem;
            flex-shrink: 0;
        }

        .doc-flag-copy-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.3125rem;
            min-inline-size: 2.25rem;
            min-block-size: 2rem;
            padding: 0.25rem 0.625rem;
            border-radius: 0.375rem;
            border: 1px solid var(--doc-border-strong, #cbd5e1);
            background-color: var(--doc-surface, #ffffff);
            color: var(--doc-text-primary, #0f172a);
            font-size: 0.75rem;
            font-weight: 600;
            cursor: pointer;
            transition: background-color 0.15s ease, border-color 0.15s ease, color 0.15s ease;
        }

        .doc-flag-copy-btn:hover {
            background-color: var(--doc-surface-hover, #f8fafc);
            border-color: var(--doc-badge-info-solid, #0284c7);
        }

        .doc-flag-copy-btn:focus-visible {
            outline: 3px solid var(--doc-badge-info-solid, #0284c7);
            outline-offset: 2px;
        }

        .doc-flag-copy-btn .doc-flag-icon-check {
            display: none;
        }

        .doc-flag-copy-btn[data-copied] {
            border-color: var(--doc-badge-success-solid, #059669);
            background-color: var(--doc-badge-success-bg, #ecfdf5);
            color: var(--doc-badge-success-text, #065f46);
        }

        .doc-flag-copy-btn[data-copied] .doc-flag-icon-copy {
            display: none;
        }

        .doc-flag-copy-btn[data-copied] .doc-flag-icon-check {
            display: inline-flex;
        }

        .doc-flag-copy-btn[data-failed] {
            border-color: var(--doc-badge-danger-solid, #e11d48);
            background-color: var(--doc-badge-danger-bg, #fff1f2);
            color: var(--doc-badge-danger-text, #9f1239);
        }

        .doc-flag-tip {
            position: absolute;
            inset-block-end: calc(100% + 0.3125rem);
            inset-inline-end: 0;
            z-index: 10;
            padding: 0.1875rem 0.5rem;
            border-radius: 0.375rem;
            background-color: var(--doc-badge-success-solid, #059669);
            color: #ffffff;
            font-size: 0.6875rem;
            font-weight: 700;
            box-shadow: var(--doc-shadow-sm, 0 1px 2px rgba(0, 0, 0, 0.12));
            opacity: 0;
            transition: opacity 0.15s ease;
            pointer-events: none;
            white-space: nowrap;
        }

        .doc-flag-sr-only {
            position: absolute;
            width: 1px;
            height: 1px;
            padding: 0;
            margin: -1px;
            overflow: hidden;
            clip-path: inset(50%);
            white-space: nowrap;
            border: 0;
        }

        .doc-flag-copy-btn[data-copied]+.doc-flag-tip {
            opacity: 1;
        }

        .doc-flag-copy-btn[data-failed]+.doc-flag-tip {
            opacity: 1;
            background-color: var(--doc-badge-danger-solid, #e11d48);
            color: #ffffff;
        }

        .doc-flag-desc {
            font-size: 0.71875rem;
            line-height: 1.4;
            color: var(--doc-text-muted, #64748b);
            margin: 0;
        }

        @media (prefers-reduced-motion: reduce) {

            .doc-flag-copy-btn,
            .doc-flag-tip {
                transition: none;
            }
        }
    </style>

    <div class="doc-flag-panel-head">
        <div>
            <p class="doc-flag-panel-title">Daftar Flag Otomatis untuk Naskah Word (.docx)</p>
            <p class="doc-flag-panel-sub">
                Salin dan tempel flag berikut ke dalam dokumen Anda agar nomor surat &amp; tanggal pengesahan terisi
                otomatis saat disetujui.
            </p>
        </div>
        <span class="doc-flag-cat-pill">
            Kategori: {{ $category->name }} ({{ $category->code }})
        </span>
    </div>

    <ul class="doc-flag-list">
        @foreach ($flags as $flag)
            @php
                $codeId = 'doc-flag-' . $category->id . '-' . $flag['id'];
            @endphp
            <li class="doc-flag-item {{ $flag['primary'] ? 'doc-flag-item-primary' : '' }}">
                <div class="doc-flag-item-meta">
                    <span class="doc-flag-item-label">{{ $flag['label'] }}</span>
                    <span class="doc-flag-item-badge">{{ $flag['badge'] }}</span>
                </div>

                <div class="doc-flag-code-row">
                    <code id="{{ $codeId }}" class="doc-flag-code">{{ $flag['flag'] }}</code>

                    <span class="doc-flag-copy-wrap">
                        <button type="button" class="doc-flag-copy-btn" command="--copy"
                            commandfor="{{ $codeId }}">
                            <span class="doc-flag-icon-copy" aria-hidden="true">
                                <svg width="14" height="14" fill="none" viewBox="0 0 24 24"
                                    stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
                                </svg>
                            </span>
                            <span class="doc-flag-icon-check" aria-hidden="true">
                                <svg width="14" height="14" fill="none" viewBox="0 0 24 24"
                                    stroke="currentColor" stroke-width="2.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                                </svg>
                            </span>
                            <span>Salin</span>
                            <span class="doc-flag-sr-only">flag {{ $flag['flag'] }}</span>
                        </button>
                        <span class="doc-flag-tip" role="status"></span>
                    </span>
                </div>

                <p class="doc-flag-desc">{{ $flag['description'] }}</p>
            </li>
        @endforeach
    </ul>
</div>
