<x-filament-widgets::widget class="fi-document-lifecycle-stats-widget">
    <div class="db-stats-grid">
        @foreach($stats as $stat)
            @php
                $color = $stat['color'];
                $variantClass = match ($color) {
                    'amber' => 'db-stat-amber',
                    'emerald' => 'db-stat-emerald',
                    'blue' => 'db-stat-blue',
                    'purple' => 'db-stat-purple',
                    'slate' => 'db-stat-slate',
                    'rose' => 'db-stat-rose',
                    default => 'db-stat-blue',
                };
            @endphp

            <a href="{{ $stat['url'] }}" class="db-stat-card {{ $variantClass }}">
                {{-- Top Row: Label & Icon Container --}}
                <div class="db-stat-header">
                    <span class="db-stat-label">
                        {{ $stat['label'] }}
                    </span>

                    <div class="db-stat-icon-box">
                        @if($stat['icon'] === 'calendar-clock')
                            <svg style="width: 22px; height: 22px; min-width: 22px; min-height: 22px; flex-shrink: 0;" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                            </svg>
                        @elseif($stat['icon'] === 'shield-check')
                            <svg style="width: 22px; height: 22px; min-width: 22px; min-height: 22px; flex-shrink: 0;" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                            </svg>
                        @elseif($stat['icon'] === 'archive-box')
                            <svg style="width: 22px; height: 22px; min-width: 22px; min-height: 22px; flex-shrink: 0;" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4" />
                            </svg>
                        @else
                            <svg style="width: 22px; height: 22px; min-width: 22px; min-height: 22px; flex-shrink: 0;" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        @endif
                    </div>
                </div>

                {{-- Middle: Large Number --}}
                <div class="db-stat-body">
                    <div class="db-stat-value">
                        {{ number_format((int) $stat['value'], 0, ',', '.') }}
                    </div>
                </div>

                {{-- Bottom Row: Status Description & Click Arrow Indicator --}}
                <div class="db-stat-footer">
                    <div class="db-stat-desc-wrap">
                        <span class="db-stat-dot db-dot-{{ $color }}"></span>
                        <span class="db-stat-desc">
                            {{ $stat['description'] }}
                        </span>
                    </div>

                    <div class="db-stat-arrow-wrap">
                        <svg style="width: 14px; height: 14px; min-width: 14px; min-height: 14px; flex-shrink: 0;" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" class="db-stat-arrow">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                        </svg>
                    </div>
                </div>
            </a>
        @endforeach
    </div>
</x-filament-widgets::widget>

