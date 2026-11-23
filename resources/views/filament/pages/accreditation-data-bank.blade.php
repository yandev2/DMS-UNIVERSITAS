<x-filament-panels::page>
    <link rel="stylesheet"
        href="{{ asset('css/borang-bank.css') }}?v={{ @filemtime(public_path('css/borang-bank.css')) ?: time() }}">

    @php
        $stats = $this->getCriteriaStatistics();
        $activeAssessorUnits = $this->getActiveAssessorUnits();

        $criteriaColors = [
            'K1' => '#4f46e5', // Indigo
            'K2' => '#0284c7', // Sky
            'K3' => '#059669', // Emerald
            'K4' => '#2563eb', // Blue
            'K5' => '#d97706', // Amber
            'K6' => '#7c3aed', // Violet
            'K7' => '#0d9488', // Teal
            'K8' => '#e11d48', // Rose
            'K9' => '#9333ea', // Purple
        ];

        $palette = [
            '#4f46e5',
            '#0284c7',
            '#059669',
            '#2563eb',
            '#d97706',
            '#7c3aed',
            '#0d9488',
            '#e11d48',
            '#9333ea',
            '#ea580c',
            '#0891b2',
            '#475569',
        ];

        $criteriaShortLabels = [
            'K1' => 'Visi Misi',
            'K2' => 'Tata Pamong',
            'K3' => 'Mahasiswa',
            'K4' => 'SDM',
            'K5' => 'Sarpras',
            'K6' => 'Pendidikan',
            'K7' => 'Penelitian',
            'K8' => 'PkM',
            'K9' => 'Luaran',
        ];
    @endphp

    <!-- Enterprise Guidance Banner (Clean, Compact, No Glow) -->
    <div class="borang-banner">
        <div class="borang-banner-content">
            <!-- Left Info Block -->
            <div class="borang-banner-left">
                <div class="borang-banner-icon">
                    <x-filament::icon icon="heroicon-o-academic-cap" />
                </div>
                <div>
                    <div class="borang-banner-title-row">
                        <h2 class="borang-banner-title">
                            Bank Data Bukti Fisik Borang Akreditasi ({{ $stats['total_criteria'] }} Kriteria)
                        </h2>
                        <span class="borang-banner-badge">
                            <span class="borang-banner-badge-dot"></span>
                            Standar {{ $stats['active_instrument_name'] ?? 'BAN-PT / LAM' }}
                        </span>
                    </div>
                    <p class="borang-banner-desc">
                        Penyusunan berkas bukti pendukung instrumen akreditasi program studi & institusi.
                    </p>
                </div>
            </div>

            <!-- Right Active Filters Info -->
            <div class="borang-banner-right">
                @if (
                    $stats['active_unit_name'] ||
                        $stats['active_year_name'] ||
                        $stats['selected_code'] ||
                        ($stats['active_instrument_name'] ?? false))
                    <span class="borang-filter-caption">Filter / Instrumen:</span>
                    @if (!empty($stats['active_instrument_name']))
                        <span class="borang-filter-pill borang-filter-pill-instrument">
                            <x-filament::icon icon="heroicon-m-academic-cap" />
                            {{ $stats['active_instrument_name'] }}
                        </span>
                    @endif
                    @if ($stats['active_unit_name'])
                        <span class="borang-filter-pill">
                            <x-filament::icon icon="heroicon-m-building-office" />
                            {{ $stats['active_unit_name'] }}
                        </span>
                    @endif
                    @if ($stats['active_year_name'])
                        <span class="borang-filter-pill">
                            <x-filament::icon icon="heroicon-m-calendar" />
                            {{ $stats['active_year_name'] }}
                        </span>
                    @endif
                    @if ($stats['selected_code'])
                        <span class="borang-filter-pill borang-filter-pill-criteria">
                            Kriteria: {{ $stats['selected_code'] }}
                        </span>
                    @endif
                @else
                    <span class="borang-filter-caption">
                        Menampilkan seluruh arsip borang yang disahkan
                    </span>
                @endif
            </div>
        </div>

        <!-- Integrated Assessor Portal Status Bar -->
        <div class="borang-banner-assessor-bar">
            @if ($activeAssessorUnits->isNotEmpty())
                <div class="borang-assessor-status-header">
                    <span class="borang-assessor-pulse-dot"></span>
                    <span class="borang-assessor-status-title">
                        Portal Akses Asesor Sedang Terbuka ({{ $activeAssessorUnits->count() }} Unit)
                    </span>

                </div>

                <div class="borang-assessor-list">
                    @foreach ($activeAssessorUnits as $openUnit)
                        <div class="borang-assessor-item">
                            <div class="borang-assessor-item-left">
                                <div class="borang-assessor-item-title-row">
                                    <span class="borang-assessor-status-pill">Akses Terbuka</span>
                                    <span class="borang-assessor-unit-name">{{ $openUnit->name }}</span>
                                </div>
                                <div class="borang-assessor-item-meta-row">
                                    <span class="borang-assessor-date">
                                        Masa Asesmen:
                                        @if ($openUnit->assessor_access_end)
                                            s.d. {{ $openUnit->assessor_access_end->format('d M Y') }}
                                        @else
                                            Tanpa batas waktu
                                        @endif
                                    </span>
                                    @if ($openUnit->assessor_passcode)
                                        <span class="borang-assessor-sep">&bull;</span>
                                        <span class="borang-assessor-pin">PIN:
                                            {{ $openUnit->assessor_passcode }}</span>
                                    @endif
                                </div>
                            </div>

                            <div class="borang-assessor-item-actions">
                                <button type="button" class="borang-assessor-btn-copy"
                                    onclick="copyAssessorUnitLink('{{ $openUnit->getAssessorPortalUrl() }}', this)"
                                    title="Salin Tautan Portal Dossier Lengkap Asesor untuk {{ $openUnit->name }}">
                                    <x-filament::icon icon="heroicon-m-link"
                                        style="width: 0.875rem; height: 0.875rem;" />
                                    <span>Salin Tautan Portal</span>
                                </button>

                                <a href="{{ $openUnit->getAssessorPortalUrl() }}" target="_blank" rel="noopener"
                                    class="borang-assessor-btn-preview"
                                    title="Pratinjau Portal Dossier Lengkap di Tab Baru">
                                    <x-filament::icon icon="heroicon-m-arrow-top-right-on-square"
                                        style="width: 0.875rem; height: 0.875rem;" />
                                    <span>Pratinjau</span>
                                </a>

                                <button type="button" class="borang-assessor-btn-close"
                                    wire:click="mountAction('closeAssessorAccess', { unit_id: {{ $openUnit->id }} })"
                                    title="Klik untuk menutup akses asesor {{ $openUnit->name }}">
                                    <x-filament::icon icon="heroicon-m-lock-closed"
                                        style="width: 0.875rem; height: 0.875rem;" />
                                    <span>Tutup Akses</span>
                                </button>
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <div class="borang-assessor-closed-row">
                    <span class="borang-assessor-closed-icon">
                        <x-filament::icon icon="heroicon-m-shield-check" style="width: 0.9375rem; height: 0.9375rem;" />
                    </span>
                    <span class="borang-assessor-closed-text">
                        Seluruh portal akses asesor sedang tertutup (aman). Tautan bukti borang terlindungi dari akses
                        luar.
                    </span>
                </div>
            @endif
        </div>
    </div>

    <!-- Interactive Mini Column / Bar Distribution Chart Panel (Option 1) -->
    <div class="borang-analytics-panel">
        <!-- Left: Readiness & Kesiapan Summary -->
        <div class="borang-readiness-card">
            <div>
                <div class="borang-readiness-header">
                    <span class="borang-readiness-title">
                        KESIAPAN BORANG
                    </span>
                    <span class="borang-readiness-badge">
                        {{ $stats['total_criteria'] }} Kriteria
                    </span>
                </div>

                <div class="borang-readiness-metric-box">
                    <span class="borang-readiness-value">
                        {{ number_format($stats['total']) }}
                    </span>
                    <span class="borang-readiness-unit">
                        Berkas Bukti
                    </span>
                    <p class="borang-readiness-sub">
                        Dokumen Sah Terverifikasi
                    </p>
                </div>

                <div class="borang-readiness-progress-block">
                    <div class="borang-readiness-progress-label">
                        <span>Kelengkapan Kriteria</span>
                        <span class="borang-readiness-progress-percent">{{ $stats['readiness_percent'] }}%</span>
                    </div>
                    <div class="borang-readiness-track">
                        <div class="borang-readiness-fill" style="width: {{ $stats['readiness_percent'] }}%;"></div>
                    </div>
                    <span class="borang-readiness-caption">
                        {{ $stats['filled_count'] }} dari {{ $stats['total_criteria'] }} Kriteria Terisi
                    </span>
                </div>
            </div>

            <div class="borang-readiness-footer">
                @if ($stats['selected_id'] === null)
                    <button type="button" wire:click="filterByCriterionId(null)"
                        class="borang-all-btn borang-all-btn-active">
                        <x-filament::icon icon="heroicon-m-check" style="width: 0.875rem; height: 0.875rem;" />
                        Semua Kriteria Aktif
                    </button>
                @else
                    <button type="button" wire:click="resetCriteriaFilter" class="borang-all-btn borang-all-btn-reset">
                        <x-filament::icon icon="heroicon-m-x-circle" style="width: 0.875rem; height: 0.875rem;" />
                        Reset Filter (Semua Kriteria)
                    </button>
                @endif
            </div>
        </div>

        <!-- Right: 9-Column Bar Distribution Chart (Interactive & Responsive) -->
        <div class="borang-chart-card">
            <div class="borang-chart-header">
                <div>
                    <h3 class="borang-chart-title">
                        <x-filament::icon icon="heroicon-o-chart-bar-square" />
                        Distribusi Bukti Fisik per Kriteria Akreditasi
                    </h3>
                    <p class="borang-chart-subtitle">
                        Klik kolom kriteria untuk memfilter tabel langsung.
                        <span class="hidden sm:inline">Tinggi kolom menyesuaikan volume berkas.</span>
                        <span class="sm:hidden">Geser ke samping (swipe) untuk melihat seluruh K1–K9.</span>
                    </p>
                </div>

                @if ($stats['selected_code'])
                    <div class="borang-active-filter-wrap">
                        <button type="button" wire:click="resetCriteriaFilter" class="borang-active-filter-pill"
                            title="Klik untuk menghapus filter kriteria {{ $stats['selected_code'] }}">
                            <span class="borang-active-filter-dot"></span>
                            <span class="borang-active-filter-text">Filter:
                                <strong>{{ $stats['selected_code'] }}</strong></span>
                            <span class="borang-active-filter-reset">
                                <x-filament::icon icon="heroicon-m-x-mark" class="borang-reset-icon" />
                                Reset
                            </span>
                        </button>
                    </div>
                @endif
            </div>

            <!-- Scroll container for small mobile screens -->
            <div class="borang-chart-scroll">
                <div class="borang-chart-grid">
                    @foreach ($stats['criteria'] as $c)
                        @php
                            $colColor =
                                $criteriaColors[$c['code']] ?? ($palette[$loop->index % count($palette)] ?? '#2563eb');
                            $shortLabel =
                                $criteriaShortLabels[$c['code']] ??
                                \Illuminate\Support\Str::limit(
                                    trim(
                                        (string) preg_replace('/^Kriteria\s*\d+\s*[-:]\s*/i', '', (string) $c['name']),
                                    ),
                                    12,
                                );

                            if ($c['count'] > 0) {
                                $barHeightPercent = max(
                                    22,
                                    (int) round(($c['count'] / max(1, $stats['max_count'])) * 100),
                                );
                            } else {
                                $barHeightPercent = 0;
                            }
                        @endphp

                        <button type="button" wire:click="filterByCriterionId({{ $c['id'] }})"
                            class="borang-chart-col {{ $c['is_selected'] ? 'borang-chart-col-selected' : '' }}"
                            title="{{ $c['code'] }} - {{ $c['name'] }} ({{ $c['count'] }} Berkas Bukti)">
                            <!-- Top Count Badge -->
                            <span
                                class="borang-col-count {{ $c['count'] > 0 ? 'borang-col-count-active' : 'borang-col-count-empty' }}">
                                {{ $c['count'] }}
                            </span>

                            <!-- Vertical Bar Track -->
                            <div class="borang-col-track">
                                @if ($c['count'] > 0)
                                    <div class="borang-col-fill"
                                        style="height: {{ $barHeightPercent }}%; background-color: {{ $colColor }};">
                                    </div>
                                @else
                                    <div class="borang-col-empty-line"></div>
                                @endif
                            </div>

                            <!-- Bottom Labels -->
                            <div class="borang-col-labels">
                                <span class="borang-col-code">
                                    {{ $c['code'] }}
                                </span>
                                <span class="borang-col-sub" title="{{ $c['name'] }}">
                                    {{ $shortLabel }}
                                </span>
                            </div>
                        </button>
                    @endforeach
                </div>
            </div>
        </div>
    </div>

    <!-- The Livewire Table -->
    {{ $this->table }}

    <script>
        window.copyBorangUrl = function(url) {
            if (!url) return;

            function fallbackCopy(text) {
                var textArea = document.createElement("textarea");
                textArea.value = text;
                textArea.style.position = "fixed";
                textArea.style.left = "-999999px";
                textArea.style.top = "-999999px";
                textArea.style.opacity = "0";
                textArea.setAttribute("readonly", "");
                document.body.appendChild(textArea);
                textArea.focus();
                textArea.select();
                textArea.setSelectionRange(0, 99999);
                try {
                    document.execCommand("copy");
                } catch (err) {
                    console.error("Fallback copy failed:", err);
                }
                document.body.removeChild(textArea);
            }

            if (window.navigator && window.navigator.clipboard && window.isSecureContext) {
                window.navigator.clipboard.writeText(url).catch(function() {
                    fallbackCopy(url);
                });
            } else {
                fallbackCopy(url);
            }
        };

        window.copyAssessorUnitLink = function(url, btnElement) {
            window.copyBorangUrl(url);

            if (btnElement) {
                var span = btnElement.querySelector('span');
                var origText = span ? span.innerText : 'Salin Tautan Portal';
                if (span) span.innerText = 'Tersalin!';
                setTimeout(function() {
                    if (span) span.innerText = origText;
                }, 2000);
            }
        };
    </script>
</x-filament-panels::page>
