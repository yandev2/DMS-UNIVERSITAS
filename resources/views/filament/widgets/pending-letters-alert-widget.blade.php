@php
    $hasIncoming = $incomingCount > 0;
    $hasOutgoing = $outgoingHumasCount > 0;
@endphp

<x-filament-widgets::widget class="fi-dashboard-letters-alert">
    @if($hasIncoming || $hasOutgoing)
    <div class="db-letters-alert-card">
        {{-- Section: Surat Masuk yang Menunggu Ditinjau / Dibaca --}}
        @if($hasIncoming)
            <div class="db-letters-alert-row db-letters-alert-row--incoming">
                <div class="db-letters-alert-left">
                    <div class="db-letters-alert-icon db-letters-alert-icon--incoming">
                        <x-heroicon-o-inbox-arrow-down />
                    </div>

                    <div class="db-letters-alert-content">
                        <div class="db-letters-alert-tag db-letters-alert-tag--incoming">
                            <span class="db-letters-alert-dot db-letters-alert-dot--incoming"></span>
                            <span class="db-letters-alert-tag-text">Surat Masuk Perlu Tindakan</span>
                        </div>

                        <h4 class="db-letters-alert-title">
                            Anda memiliki <span class="db-highlight db-highlight--incoming">{{ $incomingCount }} surat masuk</span> yang perlu ditinjau, silakan lihat.
                        </h4>

                        @if($incomingLetters->isNotEmpty())
                            @php $firstSm = $incomingLetters->first(); @endphp
                            <div class="db-letters-alert-meta">
                                <div class="db-letters-meta-item">
                                    <span class="db-letters-meta-label">Dari:</span>
                                    <strong class="db-letters-meta-val">{{ $firstSm->sender_name }}</strong>
                                </div>
                                <span class="db-letters-meta-dot">•</span>
                                <div class="db-letters-meta-item db-letters-meta-item--subject">
                                    <span class="db-letters-meta-label">Perihal:</span>
                                    <span class="db-letters-meta-val db-letters-alert-subject"><em>"{{ $firstSm->subject }}"</em></span>
                                </div>
                            </div>
                        @endif
                    </div>
                </div>

                <div class="db-letters-alert-action-wrap">
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

                    <div class="db-letters-alert-content">
                        <div class="db-letters-alert-tag db-letters-alert-tag--outgoing">
                            <span class="db-letters-alert-dot db-letters-alert-dot--outgoing"></span>
                            <span class="db-letters-alert-tag-text">Antrean Penomoran Surat Keluar Humas</span>
                        </div>

                        <h4 class="db-letters-alert-title">
                            Terdapat <span class="db-highlight db-highlight--outgoing">{{ $outgoingHumasCount }} surat keluar dari unit</span> yang perlu ditinjau dan dinomori.
                        </h4>

                        @if($outgoingHumasLetters->isNotEmpty())
                            @php $firstSk = $outgoingHumasLetters->first(); @endphp
                            <div class="db-letters-alert-meta">
                                <div class="db-letters-meta-item">
                                    <span class="db-letters-meta-label">Unit:</span>
                                    <strong class="db-letters-meta-val">{{ $firstSk->unit?->code ?: ($firstSk->unit?->name ?? '-') }}</strong>
                                </div>
                                <span class="db-letters-meta-dot">•</span>
                                <div class="db-letters-meta-item">
                                    <span class="db-letters-meta-label">Tujuan:</span>
                                    <strong class="db-letters-meta-val">{{ $firstSk->destination_organization }}</strong>
                                </div>
                                <span class="db-letters-meta-dot">•</span>
                                <div class="db-letters-meta-item db-letters-meta-item--subject">
                                    <span class="db-letters-meta-label">Perihal:</span>
                                    <span class="db-letters-meta-val db-letters-alert-subject"><em>"{{ $firstSk->subject }}"</em></span>
                                </div>
                            </div>
                        @endif
                    </div>
                </div>

                <div class="db-letters-alert-action-wrap">
                    <a href="{{ $outgoingHumasUrl }}" class="db-letters-alert-btn db-letters-alert-btn--outgoing">
                        <span>Tinjau & Beri Nomor</span>
                        <x-heroicon-m-arrow-right style="width: 1rem; height: 1rem;" />
                    </a>
                </div>
            </div>
        @endif
    </div>
    @endif
</x-filament-widgets::widget>
