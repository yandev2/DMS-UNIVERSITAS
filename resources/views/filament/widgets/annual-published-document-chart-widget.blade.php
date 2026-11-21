<x-filament-widgets::widget class="db-annual-chart-widget">
    <div class="db-chart-container">
        <!-- 1. Header Card & Ringkasan Metrik Cepat -->
        <div class="db-chart-header">
            <div class="db-chart-header-left">
                <div class="db-chart-icon-badge">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                        class="shrink-0"
                        style="width: 20px; height: 20px; min-width: 20px; min-height: 20px; flex-shrink: 0;">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                    </svg>
                </div>
                <div>
                    <div class="db-chart-badge-tag">
                        <span class="db-badge-dot"></span>
                        <span>REKAPITULASI RESMI TAHUNAN &bull; {{ $year }}</span>
                    </div>
                    <h3 class="db-chart-title">
                        Penerbitan Naskah Dinas Sah
                    </h3>
                    <p class="db-chart-subtitle">
                        Grafik volume naskah resmi yang telah divalidasi dan terbit per bulan pada tahun {{ $year }}
                    </p>
                </div>
            </div>

            <!-- Pill Metrik Ringkas di Sisi Kanan (Satu Baris Rapi) -->
            <div class="db-chart-pills-group">
                <div class="db-chart-stat-pill">
                    <span class="db-pill-title">
                        <span class="db-pill-title-long">Total Terbit ({{ $year }})</span>
                        <span class="db-pill-title-short">Total ({{ $year }})</span>
                    </span>
                    <div class="db-pill-value-row">
                        <span class="db-pill-num text-blue-600 dark:text-blue-400">{{ $totalYearly }}</span>
                        <span class="db-pill-unit">Naskah</span>
                    </div>
                </div>

                <div class="db-chart-stat-pill">
                    <span class="db-pill-title">
                        <span class="db-pill-title-long">{{ $periodPillLong ?? "Bulan Ini ({$currentMonthName})" }}</span>
                        <span class="db-pill-title-short">{{ $periodPillShort ?? 'Bulan Ini' }}</span>
                    </span>
                    <div class="db-pill-value-row">
                        <span class="db-pill-num text-emerald-600 dark:text-emerald-400">{{ $currentMonthCount }}</span>
                        <span class="db-pill-unit">Naskah</span>
                    </div>
                </div>

                <div class="db-chart-stat-pill">
                    <span class="db-pill-title">
                        <span class="db-pill-title-long">Rata-rata Bulanan</span>
                        <span class="db-pill-title-short">Rata-rata</span>
                    </span>
                    <div class="db-pill-value-row">
                        <span class="db-pill-num text-amber-500 dark:text-amber-400">{{ $monthlyAverage }}</span>
                        <span class="db-pill-unit">/bln</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- 2. Visualisasi Kolom 12 Bulan (Full Width & Tanpa Scrollbar Mengganggu) -->
        <div class="db-chart-body-wrapper">
            <div class="db-chart-scroll-area">
                <div class="db-chart-grid-columns">
                    @foreach ($months as $month)
                        <div class="db-chart-col {{ $month['is_current'] ? 'is-current-month' : '' }}">
                            <!-- Area Batang Vertikal -->
                            <div class="db-chart-bar-slot">
                                <!-- Background Track -->
                                <div class="db-chart-track-bg"></div>

                                @if ($month['count'] > 0)
                                    <!-- Batang Berisi Aktif -->
                                    <div class="db-chart-bar-fill"
                                        style="height: {{ max(16, $month['height_percent']) }}%;">
                                        <!-- Label Angka di Atas Batang -->
                                        <span class="db-chart-bar-badge">
                                            {{ $month['count'] }}
                                        </span>
                                    </div>
                                @else
                                    <!-- Indikator 0 Naskah -->
                                    <div class="db-chart-bar-zero"></div>
                                @endif

                                <!-- Hover Tooltip Interaktif -->
                                <div class="db-chart-tooltip">
                                    <span class="db-tooltip-month">{{ $month['full_name'] }} {{ $year }}</span>
                                    <span class="db-tooltip-count">
                                        <strong>{{ $month['count'] }}</strong> Naskah Sah Terbit
                                    </span>
                                    @if ($totalYearly > 0)
                                        <span class="db-tooltip-share">
                                            Kontribusi: {{ $month['share_percent'] }}% dari total
                                        </span>
                                    @endif
                                    @if ($month['is_current'])
                                        <span class="db-tooltip-current-tag">&bull; Periode Terpilih</span>
                                    @endif
                                </div>
                            </div>

                            <!-- Label Nama Bulan di Bawah Batang -->
                            <div class="db-chart-month-label-wrap">
                                <span
                                    class="db-chart-month-label {{ $month['is_current'] ? 'text-blue-600 dark:text-blue-400 font-extrabold' : '' }}">
                                    {{ $month['short_name'] }}
                                </span>
                                @if ($month['is_current'])
                                    <span class="db-chart-current-dot" title="Bulan Berjalan"></span>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        <!-- 3. Sub-Footer Catatan Hak Akses & Tombol Pintas -->
        <div class="db-chart-footer">
            <div class="db-chart-scope-indicator">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                    class="shrink-0"
                    style="width: 18px; height: 18px; min-width: 18px; min-height: 18px; flex-shrink: 0;">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                </svg>
                <span>Cakupan Hak Akses: <strong
                        class="text-slate-800 dark:text-slate-200">{{ $scopeLabel }}</strong></span>
            </div>

            <div class="db-chart-footer-right">
                @if ($highestMonthLabel !== '-')
                    <span class="db-chart-peak-info">
                        Puncak Penerbitan: <strong>{{ $highestMonthLabel }}</strong>
                    </span>
                @endif
                <a href="{{ \App\Filament\Resources\Documents\DocumentResource::getUrl('index', ['tab' => 'all', 'filters' => ['status' => ['values' => [\App\Enums\DocumentStatus::Approved->value, \App\Enums\DocumentStatus::Archived->value, \App\Enums\DocumentStatus::Expired->value]], 'issued_period' => ['issued_from' => $periodStartDate ?? now()->startOfYear()->toDateString(), 'issued_until' => $periodEndDate ?? now()->endOfYear()->toDateString()]]]) }}"
                    class="db-chart-action-link">
                    <span>Buka Arsip Naskah Sah</span>
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                        class="shrink-0"
                        style="width: 14px; height: 14px; min-width: 14px; min-height: 14px; flex-shrink: 0;">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M14 5l7 7m0 0l-7 7m7-7H3" />
                    </svg>
                </a>
            </div>
        </div>
    </div>
</x-filament-widgets::widget>
