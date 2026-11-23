@props([
    'accreditationShowcase' => null,
])

@php
    $showcase = $accreditationShowcase ?? \App\Models\Unit::getPublicAccreditationShowcaseData();
    $institution = $showcase['institution'] ?? null;
    $units = $showcase['units'] ?? [];
    $totalCount = (int) ($showcase['total_count'] ?? 0);
    $topGradeCount = (int) ($showcase['top_grade_count'] ?? 0);
    $filters = $showcase['filters'] ?? [];

    $toneBadgeMap = [
        'emerald' => 'bg-emerald-50 text-emerald-700 border-emerald-200/80 dark:bg-emerald-950/60 dark:text-emerald-300 dark:border-emerald-800/60',
        'blue' => 'bg-blue-50 text-blue-700 border-blue-200/80 dark:bg-blue-950/60 dark:text-blue-300 dark:border-blue-800/60',
        'sky' => 'bg-sky-50 text-sky-700 border-sky-200/80 dark:bg-sky-950/60 dark:text-sky-300 dark:border-sky-800/60',
        'amber' => 'bg-amber-50 text-amber-700 border-amber-200/80 dark:bg-amber-950/60 dark:text-amber-300 dark:border-amber-800/60',
    ];

    $statusBadgeMap = [
        'active' => [
            'pill' => 'bg-emerald-50 text-emerald-700 border-emerald-200/80 dark:bg-emerald-950/60 dark:text-emerald-300 dark:border-emerald-800/60',
            'dot' => 'bg-emerald-500',
        ],
        'expiring' => [
            'pill' => 'bg-amber-50 text-amber-700 border-amber-200/80 dark:bg-amber-950/60 dark:text-amber-300 dark:border-amber-800/60',
            'dot' => 'bg-amber-500',
        ],
        'expired' => [
            'pill' => 'bg-rose-50 text-rose-700 border-rose-200/80 dark:bg-rose-950/60 dark:text-rose-300 dark:border-rose-800/60',
            'dot' => 'bg-rose-500',
        ],
    ];

    $unitsCount = count($units);
    $gridClass = match (true) {
        $unitsCount === 1 => 'grid grid-cols-1 max-w-2xl gap-5',
        $unitsCount === 2 => 'grid grid-cols-1 md:grid-cols-2 gap-5',
        default => 'grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 sm:gap-5',
    };
@endphp

@if ($totalCount > 0)
    <!-- Seksi Status Akreditasi Institusi & Program Studi -->
    <section id="status-akreditasi"
        class="py-16 sm:py-20 bg-white dark:bg-slate-950 border-t border-slate-200/80 dark:border-slate-800/80 transition-colors">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <!-- Header Seksi -->
            <div class="flex flex-col lg:flex-row lg:items-end justify-between gap-6 mb-10">
                <div>
                    <div
                        class="inline-flex items-center gap-1.5 px-3 py-1 rounded-lg text-xs font-bold uppercase tracking-wider bg-emerald-50 text-emerald-700 border border-emerald-200/70 dark:bg-emerald-950/60 dark:text-emerald-300 dark:border-emerald-800/60 mb-3">
                        <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                            aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M12 14l9-5-9-5-9 5 9 5z" />
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M12 14l6.16-3.422a12.083 12.083 0 01.665 6.479A11.952 11.952 0 0012 20.055a11.952 11.952 0 00-6.824-2.998 12.078 12.078 0 01.665-6.479L12 14z" />
                        </svg>
                        <span>Penjaminan Mutu &amp; Akreditasi</span>
                    </div>
                    <h2 class="text-2xl sm:text-3xl font-black text-slate-900 dark:text-white tracking-tight">
                        Status Akreditasi Institusi &amp; Program Studi
                    </h2>
                    <p class="text-sm text-slate-500 dark:text-slate-400 mt-2 max-w-2xl">
                        Peringkat akreditasi resmi perguruan tinggi, fakultas, dan program studi berdasarkan penetapan BAN-PT maupun Lembaga Akreditasi Mandiri (LAM).
                    </p>
                </div>

                <!-- Ringkasan Cepat -->
                <div class="flex flex-wrap items-center gap-2.5 shrink-0">
                    <span
                        class="inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-xl text-xs font-bold bg-slate-100 dark:bg-slate-900 text-slate-700 dark:text-slate-200 border border-slate-200 dark:border-slate-800 tabular-nums">
                        <span class="w-2 h-2 rounded-full bg-blue-500"></span>
                        {{ number_format($totalCount) }} Unit Terakreditasi
                    </span>
                    @if ($topGradeCount > 0)
                        <span
                            class="inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-xl text-xs font-bold bg-emerald-50 dark:bg-emerald-950/60 text-emerald-700 dark:text-emerald-300 border border-emerald-200/80 dark:border-emerald-800/60 tabular-nums">
                            <svg class="w-3.5 h-3.5 shrink-0" fill="currentColor" viewBox="0 0 20 20"
                                aria-hidden="true">
                                <path fill-rule="evenodd"
                                    d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                                    clip-rule="evenodd" />
                            </svg>
                            {{ number_format($topGradeCount) }} Peringkat Unggul / A
                        </span>
                    @endif
                </div>
            </div>

            <!-- Spotlight Akreditasi Perguruan Tinggi / Institusi (Jika Rektorat Terakreditasi) -->
            @if ($institution !== null)
                @php
                    $instToneClass = $toneBadgeMap[$institution['tone']] ?? $toneBadgeMap['emerald'];
                    $instStatusStyle = $institution['status_key'] ? ($statusBadgeMap[$institution['status_key']] ?? null) : null;
                @endphp
                <article
                    class="mb-7 rounded-2xl bg-gradient-to-br from-slate-50 via-white to-emerald-50/40 dark:from-slate-900 dark:via-slate-900 dark:to-emerald-950/20 border border-slate-200/90 dark:border-slate-800 p-5 sm:p-6 shadow-xs">
                    <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-5">
                        <div class="flex items-start gap-4 min-w-0">
                            <div
                                class="w-12 h-12 rounded-2xl bg-emerald-600 text-white shadow-md shadow-emerald-600/20 flex items-center justify-center shrink-0">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                                    aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                                        d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                                </svg>
                            </div>

                            <div class="min-w-0 space-y-1.5">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span
                                        class="px-2 py-0.5 rounded-md text-[10px] font-extrabold uppercase tracking-wider bg-slate-900 text-white dark:bg-white dark:text-slate-900">
                                        Akreditasi Perguruan Tinggi
                                    </span>
                                    <span
                                        class="font-mono text-[11px] font-bold px-2 py-0.5 rounded-md bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 border border-slate-200 dark:border-slate-700">
                                        {{ $institution['code'] }}
                                    </span>
                                    @if ($instStatusStyle && $institution['status_label'])
                                        <span
                                            class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-md text-[11px] font-semibold border {{ $instStatusStyle['pill'] }}">
                                            <span
                                                class="w-1.5 h-1.5 rounded-full {{ $instStatusStyle['dot'] }} shrink-0"></span>
                                            {{ $institution['status_label'] }}
                                        </span>
                                    @endif
                                </div>

                                <h3 class="text-lg sm:text-xl font-extrabold text-slate-900 dark:text-white leading-snug">
                                    {{ $institution['name'] }}
                                </h3>

                                <div
                                    class="flex flex-wrap items-center gap-x-4 gap-y-1.5 text-xs text-slate-600 dark:text-slate-400">
                                    @if ($institution['body'])
                                        <span>
                                            Lembaga: <strong
                                                class="text-slate-800 dark:text-slate-200">{{ $institution['body'] }}</strong>
                                        </span>
                                    @endif
                                    @if ($institution['sk_number'])
                                        <span class="min-w-0 break-all">
                                            SK: <span
                                                class="font-mono font-semibold text-slate-800 dark:text-slate-200">{{ $institution['sk_number'] }}</span>
                                        </span>
                                    @endif
                                    @if ($institution['expired_label'])
                                        <span>
                                            Berlaku s.d. <strong
                                                class="text-slate-800 dark:text-slate-200">{{ $institution['expired_label'] }}</strong>
                                        </span>
                                    @endif
                                </div>
                            </div>
                        </div>

                        <div class="flex flex-wrap sm:flex-nowrap items-center gap-3 shrink-0">
                            @if ($institution['document_stream_url'])
                                <a href="{{ $institution['document_stream_url'] }}" target="_blank"
                                    rel="noopener noreferrer"
                                    class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl text-xs font-semibold bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-200 border border-slate-300 dark:border-slate-700 hover:border-blue-400 hover:text-blue-600 dark:hover:text-blue-400 shadow-2xs transition-colors whitespace-nowrap">
                                    <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor"
                                        viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                    </svg>
                                    <span>Lihat SK (.PDF)</span>
                                </a>
                            @endif

                            <div
                                class="px-5 py-2.5 rounded-xl border {{ $instToneClass }} text-center min-w-[120px]">
                                <span class="block text-[10px] font-bold uppercase tracking-wider opacity-80">
                                    Peringkat
                                </span>
                                <span class="block text-lg sm:text-xl font-black tracking-tight">
                                    {{ $institution['grade'] }}
                                </span>
                            </div>
                        </div>
                    </div>
                </article>
            @endif

            @if ($unitsCount > 0)
                <!-- Tab Filter Cepat Jenjang / Unit (Otomatis muncul jika unit banyak & beragam) -->
                @if ($unitsCount > 6 && count($filters) > 1)
                    <div class="flex items-center gap-2 overflow-x-auto no-scrollbar pb-2 mb-5" role="group"
                        aria-label="Filter Jenjang Unit Akreditasi">
                        <button type="button" data-acc-filter="all"
                            class="js-acc-filter-btn px-3.5 py-1.5 rounded-lg text-xs font-semibold whitespace-nowrap shrink-0 transition-colors bg-slate-900 dark:bg-blue-600 text-white shadow-2xs">
                            Semua ({{ $unitsCount }})
                        </button>
                        @foreach ($filters as $filterKey => $filterLabel)
                            <button type="button" data-acc-filter="{{ $filterKey }}"
                                class="js-acc-filter-btn px-3.5 py-1.5 rounded-lg text-xs font-medium whitespace-nowrap shrink-0 transition-colors bg-slate-100 dark:bg-slate-900 text-slate-700 dark:text-slate-300 border border-slate-200 dark:border-slate-800 hover:bg-slate-200/70 dark:hover:bg-slate-800">
                                {{ $filterLabel }}
                            </button>
                        @endforeach
                    </div>
                @endif

                <!-- Grid Kartu Bento Akreditasi Prodi / Unit -->
                <div class="{{ $gridClass }}" id="accreditation-units-grid">
                    @foreach ($units as $unit)
                        @php
                            $toneClass = $toneBadgeMap[$unit['tone']] ?? $toneBadgeMap['blue'];
                            $statusStyle = $unit['status_key'] ? ($statusBadgeMap[$unit['status_key']] ?? null) : null;
                        @endphp
                        <article data-acc-card data-acc-group="{{ $unit['filter_key'] }}"
                            class="bg-slate-50/70 dark:bg-slate-900/80 hover:bg-white dark:hover:bg-slate-900 rounded-xl border border-slate-200/90 dark:border-slate-800 hover:border-blue-300/80 dark:hover:border-blue-700/70 p-4 sm:p-5 shadow-2xs hover:shadow-md transition-all duration-200 flex flex-col justify-between gap-4">
                            <div class="space-y-3 min-w-0">
                                <!-- Baris Atas: Chip Jenjang / Tipe + Badge Peringkat -->
                                <div class="flex items-start justify-between gap-3">
                                    <div class="flex flex-wrap items-center gap-1.5 min-w-0">
                                        <span
                                            class="px-2 py-0.5 rounded-md text-[11px] font-bold bg-slate-200/70 dark:bg-slate-800 text-slate-700 dark:text-slate-300 shrink-0">
                                            {{ $unit['badge_chip'] }}
                                        </span>
                                        <span
                                            class="font-mono text-[11px] font-semibold px-2 py-0.5 rounded-md bg-white dark:bg-slate-950 text-slate-600 dark:text-slate-400 border border-slate-200/80 dark:border-slate-800 shrink-0">
                                            {{ $unit['code'] }}
                                        </span>
                                    </div>

                                    <span
                                        class="px-2.5 py-1 rounded-lg text-xs font-extrabold border {{ $toneClass }} shrink-0 whitespace-nowrap">
                                        {{ $unit['grade'] }}
                                    </span>
                                </div>

                                <!-- Nama Program Studi / Unit & Fakultas Induk -->
                                <div class="min-w-0">
                                    <h3 class="text-base font-bold text-slate-900 dark:text-white leading-snug">
                                        {{ $unit['name'] }}
                                    </h3>
                                    @if ($unit['parent_name'])
                                        <p class="text-xs text-slate-500 dark:text-slate-400 mt-1 truncate"
                                            title="{{ $unit['parent_name'] }}">
                                            {{ $unit['parent_name'] }}
                                        </p>
                                    @endif
                                </div>

                                <!-- Metadata Akreditasi (Hanya Field yang Terisi) -->
                                @if ($unit['body'] || $unit['sk_number'] || $unit['expired_label'])
                                    <dl
                                        class="pt-3 border-t border-slate-200/70 dark:border-slate-800/80 space-y-1.5 text-xs">
                                        @if ($unit['body'])
                                            <div class="flex items-center justify-between gap-2">
                                                <dt class="text-slate-500 dark:text-slate-400 shrink-0">Lembaga</dt>
                                                <dd class="font-semibold text-slate-800 dark:text-slate-200 text-right truncate">
                                                    {{ $unit['body'] }}
                                                </dd>
                                            </div>
                                        @endif

                                        @if ($unit['sk_number'])
                                            <div class="flex items-start justify-between gap-2">
                                                <dt class="text-slate-500 dark:text-slate-400 shrink-0">Nomor SK</dt>
                                                <dd class="font-mono text-[11px] font-medium text-slate-700 dark:text-slate-300 text-right break-all min-w-0"
                                                    title="{{ $unit['sk_number'] }}">
                                                    {{ $unit['sk_number'] }}
                                                </dd>
                                            </div>
                                        @endif

                                        @if ($unit['expired_label'])
                                            <div class="flex items-center justify-between gap-2 pt-0.5">
                                                <dt class="text-slate-500 dark:text-slate-400 shrink-0">Masa Berlaku</dt>
                                                <dd class="flex items-center gap-1.5 min-w-0 flex-wrap justify-end">
                                                    <span
                                                        class="font-medium text-slate-700 dark:text-slate-300 tabular-nums">
                                                        s.d. {{ $unit['expired_label'] }}
                                                    </span>
                                                    @if ($statusStyle && $unit['status_label'])
                                                        <span
                                                            class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-[10px] font-bold border {{ $statusStyle['pill'] }} shrink-0">
                                                            <span
                                                                class="w-1.5 h-1.5 rounded-full {{ $statusStyle['dot'] }} shrink-0"></span>
                                                            {{ $unit['status_label'] }}
                                                        </span>
                                                    @endif
                                                </dd>
                                            </div>
                                        @endif
                                    </dl>
                                @endif
                            </div>

                            @if ($unit['document_stream_url'])
                                <footer
                                    class="pt-3 border-t border-slate-200/70 dark:border-slate-800/80 flex items-center justify-between gap-3 text-xs">
                                    <span class="text-slate-500 dark:text-slate-400 truncate min-w-0 flex-1">
                                        Sertifikat Resmi Terverifikasi
                                    </span>
                                    <a href="{{ $unit['document_stream_url'] }}" target="_blank"
                                        rel="noopener noreferrer"
                                        class="font-semibold text-blue-600 dark:text-blue-400 hover:underline inline-flex items-center gap-1 shrink-0 whitespace-nowrap">
                                        <span>Lihat SK (.PDF)</span>
                                        <span class="sr-only"> akreditasi {{ $unit['name'] }}</span>
                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                                            aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M9 5l7 7-7 7" />
                                        </svg>
                                    </a>
                                </footer>
                            @endif
                        </article>
                    @endforeach
                </div>
            @endif
        </div>
    </section>
@endif

