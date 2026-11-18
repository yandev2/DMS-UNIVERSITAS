@props([
    'title' => 'Panduan Resmi',
    'rawMessage' => '',
    'data' => [],
])

@php
    $type = $data['type'] ?? 'simple';
    $sections = $data['sections'] ?? [];
    $intro = $data['intro'] ?? null;
    $bullets = $data['bullets'] ?? [];
    $paragraphs = $data['paragraphs'] ?? [];
    $hasTokens = (bool) ($data['has_tokens'] ?? false);
@endphp

@include('filament.components.document-timeline-styles')

<div class="hint-modal-wrapper"
     x-data="{
         copiedToken: null,
         toastMessage: null,
         timeoutId: null,
         copyToken(text, el) {
             if (!text) return;

             const onSuccess = () => {
                 this.copiedToken = text;
                 this.toastMessage = 'Token ' + text + ' berhasil disalin!';
                 if (this.timeoutId) clearTimeout(this.timeoutId);
                 this.timeoutId = setTimeout(() => {
                     if (this.copiedToken === text) {
                         this.copiedToken = null;
                         this.toastMessage = null;
                     }
                 }, 2200);
             };

             const runFallback = () => {
                 try {
                     const ta = document.createElement('textarea');
                     ta.value = text;
                     ta.setAttribute('readonly', '');
                     ta.style.position = 'fixed';
                     ta.style.top = '0';
                     ta.style.left = '-9999px';
                     ta.style.opacity = '0';
                     ta.style.pointerEvents = 'none';

                     const container = (el && el.closest('.hint-modal-wrapper')) || this.$el || document.body;
                     container.appendChild(ta);

                     ta.focus();
                     ta.select();
                     ta.setSelectionRange(0, ta.value.length);

                     const successful = document.execCommand('copy');
                     container.removeChild(ta);

                     if (successful) {
                         onSuccess();
                         return true;
                     }
                 } catch (err) {
                     console.warn('Fallback copy error:', err);
                 }
                 return false;
             };

             if (window.isSecureContext && navigator.clipboard && typeof navigator.clipboard.writeText === 'function') {
                 navigator.clipboard.writeText(text)
                     .then(onSuccess)
                     .catch(() => {
                         runFallback();
                     });
             } else {
                 runFallback();
             }
         }
     }">

    <style>
        .hint-modal-wrapper {
            display: flex;
            flex-direction: column;
            gap: 1rem;
            text-align: left;
            font-size: 0.875rem;
            line-height: 1.6;
            color: var(--doc-text-secondary);
        }

        .hint-header-bar {
            background-color: var(--doc-surface-muted);
            border: 1px solid var(--doc-border);
            border-radius: 0.5rem;
            padding: 0.875rem 1rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 0.5rem;
        }

        .hint-notice-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.375rem;
            padding: 0.2rem 0.5rem;
            border-radius: 0.25rem;
            font-size: 0.6875rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            background-color: var(--doc-badge-info-bg);
            color: var(--doc-badge-info-solid);
            border: 1px solid var(--doc-badge-info-border);
        }

        .hint-card {
            background-color: var(--doc-surface);
            border: 1px solid var(--doc-border);
            border-radius: 0.5rem;
            padding: 1rem 1.25rem;
            box-shadow: var(--doc-shadow-sm);
        }

        .hint-card-header {
            display: flex;
            align-items: center;
            gap: 0.625rem;
            padding-bottom: 0.625rem;
            margin-bottom: 0.75rem;
            border-bottom: 1px solid var(--doc-border);
        }

        .hint-card-num {
            font-family: ui-monospace, monospace;
            font-size: 0.75rem;
            font-weight: 800;
            padding: 0.15rem 0.45rem;
            border-radius: 0.25rem;
            background-color: var(--doc-surface-muted);
            color: var(--doc-text-primary);
            border: 1px solid var(--doc-border);
            flex-shrink: 0;
        }

        .hint-card-title {
            font-size: 0.9375rem;
            font-weight: 700;
            color: var(--doc-text-primary);
            margin: 0;
            line-height: 1.35rem;
        }

        .hint-card-intro {
            font-size: 0.8125rem;
            color: var(--doc-text-muted);
            margin-bottom: 0.75rem;
            line-height: 1.45rem;
        }

        .hint-list {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
            margin: 0;
            padding: 0;
            list-style: none;
        }

        .hint-list-item {
            display: flex;
            align-items: flex-start;
            gap: 0.625rem;
            font-size: 0.8125rem;
            line-height: 1.45rem;
        }

        .hint-bullet-dot {
            width: 0.375rem;
            height: 0.375rem;
            border-radius: 9999px;
            background-color: #0284c7;
            margin-top: 0.5rem;
            flex-shrink: 0;
        }
        .dark .hint-bullet-dot {
            background-color: #38bdf8;
        }

        /* Interactive Click-to-Copy Token Chip */
        [x-cloak] {
            display: none !important;
        }

        .hint-chip {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            padding: 0.15rem 0.5rem;
            border-radius: 0.25rem;
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
            font-size: 0.75rem;
            font-weight: 700;
            background-color: #f0f9ff;
            color: #0369a1;
            border: 1px solid #bae6fd;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.15s ease;
            vertical-align: middle;
            margin: 0.1rem 0.15rem;
            user-select: none;
            outline: none;
        }
        .hint-chip:hover {
            background-color: #e0f2fe;
            border-color: #7dd3fc;
            color: #0284c7;
            transform: translateY(-1px);
            box-shadow: 0 1px 2px rgba(2, 132, 199, 0.12);
        }
        .hint-chip:active {
            transform: translateY(0);
        }
        .hint-chip:focus-visible {
            box-shadow: 0 0 0 2px #0284c7;
        }

        .dark .hint-chip {
            background-color: rgba(14, 165, 233, 0.12);
            color: #38bdf8;
            border-color: rgba(14, 165, 233, 0.3);
        }
        .dark .hint-chip:hover {
            background-color: rgba(14, 165, 233, 0.22);
            border-color: rgba(14, 165, 233, 0.5);
            color: #7dd3fc;
        }
        .dark .hint-chip:focus-visible {
            box-shadow: 0 0 0 2px #38bdf8;
        }

        /* Success Active State when Copied */
        .hint-chip.hint-chip-active {
            background-color: #ecfdf5 !important;
            border-color: #6ee7b7 !important;
            color: #047857 !important;
            box-shadow: 0 0 0 1px #34d399 !important;
        }
        .dark .hint-chip.hint-chip-active {
            background-color: rgba(16, 185, 129, 0.2) !important;
            border-color: rgba(16, 185, 129, 0.5) !important;
            color: #6ee7b7 !important;
            box-shadow: 0 0 0 1px rgba(16, 185, 129, 0.4) !important;
        }

        .hint-chip-icon {
            display: inline-flex;
            align-items: center;
            opacity: 0.6;
            transition: opacity 0.15s ease;
        }
        .hint-chip:hover .hint-chip-icon {
            opacity: 1;
        }
        .hint-icon-svg {
            width: 0.75rem;
            height: 0.75rem;
        }

        .hint-chip-copied {
            font-size: 0.6875rem;
            font-weight: 800;
            color: #047857;
            display: inline-flex;
            align-items: center;
            gap: 0.15rem;
            animation: hint-fade 0.18s ease-out;
        }
        .dark .hint-chip-copied {
            color: #34d399;
        }

        .hint-toast-banner {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.625rem 0.875rem;
            border-radius: 0.375rem;
            background-color: #ecfdf5;
            border: 1px solid #a7f3d0;
            color: #065f46;
            box-shadow: 0 1px 2px rgba(5, 150, 105, 0.1);
        }
        .dark .hint-toast-banner {
            background-color: rgba(16, 185, 129, 0.15);
            border-color: rgba(16, 185, 129, 0.35);
            color: #a7f3d0;
        }

        .hint-footer-tip {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.625rem 0.875rem;
            border-radius: 0.375rem;
            background-color: var(--doc-surface-muted);
            border: 1px solid var(--doc-border);
            font-size: 0.75rem;
            color: var(--doc-text-muted);
        }

        @keyframes hint-fade {
            from { opacity: 0; transform: scale(0.9); }
            to { opacity: 1; transform: scale(1); }
        }
    </style>

    {{-- Top Official Notice Bar --}}
    <div class="hint-header-bar">
        <div style="display: flex; align-items: center; gap: 0.5rem;">
            <span class="hint-notice-badge">
                <x-heroicon-m-information-circle style="width: 0.875rem; height: 0.875rem;" />
                Pedoman Resmi
            </span>
            <span style="font-size: 0.75rem; font-weight: 600; color: var(--doc-text-muted);">
                Sistem E-Arsip & Tata Kelola Naskah UNIVBI
            </span>
        </div>
        @if($hasTokens)
            <span style="font-size: 0.6875rem; color: #0284c7; font-weight: 700;">
                ★ Token Otomatis Dapat Disalin
            </span>
        @endif
    </div>

    {{-- Intro Message (jika ada) --}}
    @if(filled($intro))
        <div style="font-size: 0.875rem; font-weight: 600; color: var(--doc-text-primary); padding: 0.125rem 0.25rem;">
            {{ $intro }}
        </div>
    @endif

    {{-- Kasus 1: Teks Terstruktur dengan Bagian Bernomor (1., 2., 3.) --}}
    @if($type === 'numbered_sections' && !empty($sections))
        <div style="display: flex; flex-direction: column; gap: 0.875rem;">
            @foreach($sections as $sec)
                <div class="hint-card">
                    <div class="hint-card-header">
                        <span class="hint-card-num">
                            {{ str_pad((string)($sec['number'] ?? $loop->iteration), 2, '0', STR_PAD_LEFT) }}
                        </span>
                        <h4 class="hint-card-title">
                            {{ $sec['title'] }}
                        </h4>
                    </div>

                    @if(filled($sec['intro']))
                        <div class="hint-card-intro">
                            {!! $sec['intro_formatted'] !!}
                        </div>
                    @endif

                    @if(!empty($sec['items']))
                        <ul class="hint-list">
                            @foreach($sec['items'] as $item)
                                <li class="hint-list-item">
                                    <span class="hint-bullet-dot"></span>
                                    <div>{!! $item['html'] !!}</div>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            @endforeach
        </div>

    {{-- Kasus 2: Teks dengan Poin-Poin Peluru (Bullets) --}}
    @elseif($type === 'bullets' && !empty($bullets))
        <div class="hint-card">
            <ul class="hint-list">
                @foreach($bullets as $b)
                    <li class="hint-list-item">
                        <span class="hint-bullet-dot"></span>
                        <div>{!! $b['html'] !!}</div>
                    </li>
                @endforeach
            </ul>
        </div>

    {{-- Kasus 3: Paragraf Sederhana / Naratif --}}
    @else
        <div class="hint-card" style="border-left: 3px solid #0284c7;">
            @foreach($paragraphs as $p)
                <p style="margin-bottom: {{ $loop->last ? '0' : '0.75rem' }}; font-size: 0.875rem; line-height: 1.6; color: var(--doc-text-secondary);">
                    {!! $p !!}
                </p>
            @endforeach
        </div>
    @endif

    {{-- Bottom Interactive Tip & Feedback (hanya jika ada token) --}}
    @if($hasTokens)
        {{-- Floating Success Toast Bar --}}
        <div x-show="toastMessage"
             x-cloak
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0 translate-y-1"
             x-transition:enter-end="opacity-100 translate-y-0"
             x-transition:leave="transition ease-in duration-150"
             x-transition:leave-start="opacity-100 translate-y-0"
             x-transition:leave-end="opacity-0 translate-y-1"
             class="hint-toast-banner">
            <x-heroicon-m-check-circle style="width: 1.125rem; height: 1.125rem; color: #059669; flex-shrink: 0;" />
            <span x-text="toastMessage" style="font-weight: 700; font-size: 0.8125rem;"></span>
        </div>

        {{-- Default Hint Notice --}}
        <div class="hint-footer-tip" x-show="!toastMessage">
            <x-heroicon-m-light-bulb style="width: 1.125rem; height: 1.125rem; color: #d97706; flex-shrink: 0;" />
            <div>
                <strong>Tips:</strong> Klik pada label token kode berwarna biru di atas untuk langsung menyalin teks tag ke clipboard Anda.
            </div>
        </div>
    @endif
</div>

