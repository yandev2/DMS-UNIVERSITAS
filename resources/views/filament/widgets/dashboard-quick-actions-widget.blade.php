<x-filament-widgets::widget class="fi-dashboard-quick-actions db-quick-actions-widget">
    @if(!empty($actions))
        <div class="db-quick-actions-panel">
            {{-- Header Ringkas --}}
            <div class="db-qa-header">
                <div class="db-qa-header-left">
                    <div class="db-qa-header-icon">
                        <x-filament::icon icon="heroicon-m-bolt" />
                    </div>
                    <div>
                        <span class="db-qa-title">Pintasan Aksi Cepat</span>
                        <span class="db-qa-subtitle">&bull; Akses langsung fitur operasional dan tata kelola e-Arsip & Borang UNIVBI</span>
                    </div>
                </div>
                <div class="db-qa-header-right">
                    <span class="db-qa-count-badge">
                        <span class="db-qa-count-dot"></span>
                        {{ count($actions) }} Aksi Tersedia
                    </span>
                </div>
            </div>

            {{-- 1 Baris Tombol Aksi Cepat (Desktop: 1 Row, Mobile: Grid 2 Kolom) --}}
            <div class="db-quick-actions-row">
                @foreach($actions as $action)
                    <a href="{{ $action['url'] }}"
                       @if(!empty($action['open_in_new_tab'])) target="_blank" rel="noopener" @endif
                       class="db-qa-btn db-qa-btn-{{ $action['theme'] }}"
                       title="{{ $action['title'] }} — {{ $action['description'] }}"
                       data-action-id="{{ $action['id'] }}">
                        
                        <span class="db-qa-btn-icon">
                            <x-filament::icon :icon="$action['icon']" />
                        </span>

                        <span class="db-qa-btn-label">{{ $action['short_title'] ?? $action['title'] }}</span>

                        @if(!empty($action['badge']))
                            <span class="db-qa-btn-badge db-qa-btn-badge-{{ $action['badge_tone'] }}">
                                @if($action['badge_tone'] === 'warning')
                                    <span class="db-qa-badge-pulse"></span>
                                @endif
                                {{ $action['badge'] }}
                            </span>
                        @endif
                    </a>
                @endforeach
            </div>
        </div>
    @endif
</x-filament-widgets::widget>
