<x-filament-widgets::widget class="fi-document-overview-widget">
    <div class="db-stats-grid">
        @foreach($stats as $stat)
            @php
                $color = $stat['color'];
                $variantClass = match ($color) {
                    'amber' => 'db-stat-amber',
                    'emerald' => 'db-stat-emerald',
                    'blue' => 'db-stat-blue',
                    'purple' => 'db-stat-purple',
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
                        @if($stat['icon'] === 'clock')
                            <svg style="width: 22px; height: 22px; min-width: 22px; min-height: 22px; flex-shrink: 0;" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        @elseif($stat['icon'] === 'check-badge')
                            <svg style="width: 22px; height: 22px; min-width: 22px; min-height: 22px; flex-shrink: 0;" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z" />
                            </svg>
                        @elseif($stat['icon'] === 'academic-cap')
                            <svg style="width: 22px; height: 22px; min-width: 22px; min-height: 22px; flex-shrink: 0;" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 14l9-5-9-5-9 5 9 5z" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 14l6.16-3.422a12.083 12.083 0 01.665 6.479A11.952 11.952 0 0012 20.055a11.952 11.952 0 00-6.824-2.998 12.078 12.078 0 01.665-6.479L12 14z" />
                            </svg>
                        @elseif($stat['icon'] === 'lock-open')
                            <svg style="width: 22px; height: 22px; min-width: 22px; min-height: 22px; flex-shrink: 0;" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 11V7a4 4 0 118 0m-4 8v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2z" />
                            </svg>
                        @else
                            <svg style="width: 22px; height: 22px; min-width: 22px; min-height: 22px; flex-shrink: 0;" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
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
