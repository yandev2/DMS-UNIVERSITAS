<x-filament-widgets::widget class="fi-post-period-overview-widget">
    <div class="db-stats-grid">
        @foreach ($stats as $stat)
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
                <div class="db-stat-header">
                    <span class="db-stat-label">
                        {{ $stat['label'] }}
                    </span>

                    <div class="db-stat-icon-box">
                        @if ($stat['icon'] === 'sun')
                            <svg style="width: 22px; height: 22px; min-width: 22px; min-height: 22px; flex-shrink: 0;"
                                width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z" />
                            </svg>
                        @elseif($stat['icon'] === 'calendar-days')
                            <svg style="width: 22px; height: 22px; min-width: 22px; min-height: 22px; flex-shrink: 0;"
                                width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                            </svg>
                        @elseif($stat['icon'] === 'newspaper')
                            <svg style="width: 22px; height: 22px; min-width: 22px; min-height: 22px; flex-shrink: 0;"
                                width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10a2 2 0 012 2v1m2 13a2 2 0 01-2-2V7m2 13a2 2 0 002-2V9a2 2 0 00-2-2h-2m-4-3H9M7 16h6M7 8h6v4H7V8z" />
                            </svg>
                        @else
                            <svg style="width: 22px; height: 22px; min-width: 22px; min-height: 22px; flex-shrink: 0;"
                                width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                            </svg>
                        @endif
                    </div>
                </div>

                <div class="db-stat-body">
                    <div class="db-stat-value">
                        {{ number_format((int) $stat['value'], 0, ',', '.') }}
                    </div>
                </div>

                <div class="db-stat-footer">
                    <div class="db-stat-desc-wrap">
                        <span class="db-stat-dot db-dot-{{ $color }}"></span>
                        <span class="db-stat-desc">
                            {{ $stat['description'] }}
                        </span>
                    </div>

                    <div class="db-stat-arrow-wrap">
                        <svg style="width: 14px; height: 14px; min-width: 14px; min-height: 14px; flex-shrink: 0;"
                            width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                            class="db-stat-arrow">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                        </svg>
                    </div>
                </div>
            </a>
        @endforeach
    </div>
</x-filament-widgets::widget>
