@php
    $hasIncoming = $incomingCount > 0;
    $hasOutgoing = $outgoingHumasCount > 0;
@endphp

@if($hasIncoming || $hasOutgoing)
<x-filament-widgets::widget class="fi-dashboard-letters-alert">
    <style>
        .db-letters-alert-card {
            width: 100%;
            margin-bottom: 1.25rem;
            border-radius: 0.75rem;
            background: #ffffff;
            border: 1px solid #e2e8f0;
            box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.04);
            overflow: hidden;
        }
        .dark .db-letters-alert-card {
            background: #0f172a !important;
            border-color: #1e293b !important;
            box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.3) !important;
        }
        .db-letters-alert-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1.25rem;
            padding: 1rem 1.25rem;
        }
        @media (max-width: 640px) {
            .db-letters-alert-row {
                flex-direction: column;
                align-items: flex-start;
                gap: 0.875rem;
            }
        }
        .db-letters-alert-row--incoming {
            background: #fffdfa;
            border-left: 3px solid #f59e0b;
        }
        .dark .db-letters-alert-row--incoming {
            background: rgba(245, 158, 11, 0.04) !important;
            border-left-color: #f59e0b !important;
        }
        .db-letters-alert-row--outgoing {
            background: #fafcff;
            border-left: 3px solid #2563eb;
        }
        .dark .db-letters-alert-row--outgoing {
            background: rgba(37, 99, 235, 0.04) !important;
            border-left-color: #3b82f6 !important;
        }
        .db-letters-alert-row + .db-letters-alert-row {
            border-top: 1px solid #f1f5f9;
        }
        .dark .db-letters-alert-row + .db-letters-alert-row {
            border-top-color: #1e293b !important;
        }
        .db-letters-alert-left {
            display: flex;
            align-items: flex-start;
            gap: 0.875rem;
            min-width: 0;
            flex: 1;
        }
        .db-letters-alert-icon {
            width: 2.25rem;
            height: 2.25rem;
            border-radius: 0.5rem;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            margin-top: 0.125rem;
        }
        .db-letters-alert-icon--incoming {
            background: #fef3c7;
            color: #d97706;
        }
        .dark .db-letters-alert-icon--incoming {
            background: rgba(245, 158, 11, 0.15) !important;
            color: #fbbf24 !important;
        }
        .db-letters-alert-icon--outgoing {
            background: #eff6ff;
            color: #2563eb;
        }
        .dark .db-letters-alert-icon--outgoing {
            background: rgba(37, 99, 235, 0.15) !important;
            color: #60a5fa !important;
        }
        .db-letters-alert-icon svg {
            width: 1.25rem;
            height: 1.25rem;
        }
        .db-letters-alert-tag {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            font-size: 0.6875rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 0.2rem;
        }
        .db-letters-alert-tag--incoming {
            color: #b45309;
        }
        .dark .db-letters-alert-tag--incoming {
            color: #f59e0b !important;
        }
        .db-letters-alert-tag--outgoing {
            color: #1d4ed8;
        }
        .dark .db-letters-alert-tag--outgoing {
            color: #60a5fa !important;
        }
        .db-letters-alert-dot {
            width: 0.375rem;
            height: 0.375rem;
            border-radius: 50%;
            display: inline-block;
        }
        .db-letters-alert-dot--incoming {
            background: #f59e0b;
        }
        .db-letters-alert-dot--outgoing {
            background: #2563eb;
        }
        .db-letters-alert-title {
            font-size: 0.875rem;
            font-weight: 600;
            color: #1e293b;
            line-height: 1.4;
            margin: 0;
        }
        .dark .db-letters-alert-title {
            color: #f1f5f9 !important;
        }
        .db-letters-alert-title strong,
        .db-letters-alert-title .db-highlight {
            font-weight: 700;
        }
        .db-letters-alert-title .db-highlight--incoming {
            color: #d97706;
        }
        .dark .db-letters-alert-title .db-highlight--incoming {
            color: #fbbf24 !important;
        }
        .db-letters-alert-title .db-highlight--outgoing {
            color: #2563eb;
        }
        .dark .db-letters-alert-title .db-highlight--outgoing {
            color: #60a5fa !important;
        }
        .db-letters-alert-meta {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 0.35rem 0.625rem;
            font-size: 0.75rem;
            color: #64748b;
            margin-top: 0.25rem;
            line-height: 1.35;
        }
        .dark .db-letters-alert-meta {
            color: #94a3b8 !important;
        }
        .db-letters-alert-meta strong {
            color: #334155;
            font-weight: 600;
        }
        .dark .db-letters-alert-meta strong {
            color: #cbd5e1 !important;
        }
        .db-letters-alert-btn {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            padding: 0.5rem 0.875rem;
            border-radius: 0.375rem;
            font-size: 0.8125rem;
            font-weight: 600;
            color: #ffffff !important;
            text-decoration: none !important;
            white-space: nowrap;
            border: none;
            box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.08);
            transition: background-color 0.15s ease, transform 0.1s ease;
            flex-shrink: 0;
        }
        .db-letters-alert-btn:hover {
            transform: translateY(-1px);
        }
        .db-letters-alert-btn--incoming {
            background: #d97706;
        }
        .db-letters-alert-btn--incoming:hover {
            background: #b45309;
        }
        .db-letters-alert-btn--outgoing {
            background: #2563eb;
        }
        .db-letters-alert-btn--outgoing:hover {
            background: #1d4ed8;
        }
    </style>

    <div class="db-letters-alert-card">
        {{-- Section: Surat Masuk yang Menunggu Ditinjau / Dibaca --}}
        @if($hasIncoming)
            <div class="db-letters-alert-row db-letters-alert-row--incoming">
                <div class="db-letters-alert-left">
                    <div class="db-letters-alert-icon db-letters-alert-icon--incoming">
                        <x-heroicon-o-inbox-arrow-down />
                    </div>

                    <div style="min-width: 0;">
                        <div class="db-letters-alert-tag db-letters-alert-tag--incoming">
                            <span class="db-letters-alert-dot db-letters-alert-dot--incoming"></span>
                            <span>Surat Masuk Perlu Tindakan</span>
                        </div>

                        <h4 class="db-letters-alert-title">
                            Anda memiliki <span class="db-highlight db-highlight--incoming">{{ $incomingCount }} surat masuk</span> yang perlu ditinjau, silakan lihat.
                        </h4>

                        @if($incomingLetters->isNotEmpty())
                            @php $firstSm = $incomingLetters->first(); @endphp
                            <div class="db-letters-alert-meta">
                                <span>Dari: <strong>{{ $firstSm->sender_name }}</strong></span>
                                <span style="opacity: 0.4;">•</span>
                                <span style="white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 550px;">Perihal: <em>"{{ $firstSm->subject }}"</em></span>
                            </div>
                        @endif
                    </div>
                </div>

                <div>
                    <a href="{{ $incomingUrl }}" class="db-letters-alert-btn db-letters-alert-btn--incoming">
                        <span>Lihat Surat Masuk</span>
                        <x-heroicon-m-arrow-right style="width: 1rem; height: 1rem;" />
                    </a>
                </div>
            </div>
        @endif

        {{-- Section: Surat Keluar Unit yang Menunggu Tinjauan & Penomoran Humas --}}
        @if($hasOutgoing)
            <div class="db-letters-alert-row db-letters-alert-row--outgoing">
                <div class="db-letters-alert-left">
                    <div class="db-letters-alert-icon db-letters-alert-icon--outgoing">
                        <x-heroicon-o-paper-airplane />
                    </div>

                    <div style="min-width: 0;">
                        <div class="db-letters-alert-tag db-letters-alert-tag--outgoing">
                            <span class="db-letters-alert-dot db-letters-alert-dot--outgoing"></span>
                            <span>Antrean Penomoran Surat Keluar Humas</span>
                        </div>

                        <h4 class="db-letters-alert-title">
                            Terdapat <span class="db-highlight db-highlight--outgoing">{{ $outgoingHumasCount }} surat keluar dari unit</span> yang perlu ditinjau dan dinomori.
                        </h4>

                        @if($outgoingHumasLetters->isNotEmpty())
                            @php $firstSk = $outgoingHumasLetters->first(); @endphp
                            <div class="db-letters-alert-meta">
                                <span>Unit: <strong>{{ $firstSk->unit?->name }}</strong></span>
                                <span style="opacity: 0.4;">•</span>
                                <span>Tujuan: <strong>{{ $firstSk->destination_organization }}</strong></span>
                                <span style="opacity: 0.4;">•</span>
                                <span style="white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 480px;"><em>"{{ $firstSk->subject }}"</em></span>
                            </div>
                        @endif
                    </div>
                </div>

                <div>
                    <a href="{{ $outgoingHumasUrl }}" class="db-letters-alert-btn db-letters-alert-btn--outgoing">
                        <span>Tinjau & Beri Nomor</span>
                        <x-heroicon-m-arrow-right style="width: 1rem; height: 1rem;" />
                    </a>
                </div>
            </div>
        @endif
    </div>
</x-filament-widgets::widget>
@endif

