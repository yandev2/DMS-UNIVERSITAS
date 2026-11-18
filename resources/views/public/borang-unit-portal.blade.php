<!DOCTYPE html>
<html lang="id" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Portal Akreditasi Asesor - {{ $unit->name }} | {{ ($settings ?? $siteSettings ?? \App\Models\LandingPageSetting::current())->university_name }}</title>

    @php
        $activeSettings = $settings ?? $siteSettings ?? \App\Models\LandingPageSetting::current();
        $logoUrl = $activeSettings->getLogoUrl();
    @endphp
    @if($activeSettings->favicon_path)
        <link rel="icon" href="{{ Storage::disk('public')->url($activeSettings->favicon_path) }}">
    @endif

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700,800&display=swap" rel="stylesheet" />

    <script>
        if (localStorage.theme === 'dark' || (!('theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            document.documentElement.classList.add('dark');
        } else {
            document.documentElement.classList.remove('dark');
        }
    </script>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        .custom-scrollbar::-webkit-scrollbar {
            width: 6px;
            height: 6px;
        }
        .custom-scrollbar::-webkit-scrollbar-track {
            background: #f1f5f9;
        }
        .custom-scrollbar::-webkit-scrollbar-thumb {
            background: #cbd5e1;
            border-radius: 4px;
        }
        .custom-scrollbar::-webkit-scrollbar-thumb:hover {
            background: #94a3b8;
        }
        .dark .custom-scrollbar::-webkit-scrollbar-track {
            background: #0f172a;
        }
        .dark .custom-scrollbar::-webkit-scrollbar-thumb {
            background: #334155;
            border-radius: 4px;
        }
        .dark .custom-scrollbar::-webkit-scrollbar-thumb:hover {
            background: #475569;
        }
    </style>
</head>
<body class="h-full flex flex-col font-sans antialiased text-slate-800 dark:text-slate-100 bg-slate-100 dark:bg-slate-950 selection:bg-blue-100 selection:text-blue-900 transition-colors duration-200">
    @php
        $criterionColors = [
            'K1' => ['bg' => 'bg-slate-800 dark:bg-slate-700', 'badge' => 'bg-slate-100 dark:bg-slate-800 text-slate-800 dark:text-slate-200 border-slate-300 dark:border-slate-700'],
            'K2' => ['bg' => 'bg-sky-600 dark:bg-sky-500', 'badge' => 'bg-sky-50 dark:bg-sky-950/50 text-sky-700 dark:text-sky-300 border-sky-200 dark:border-sky-800'],
            'K3' => ['bg' => 'bg-emerald-600 dark:bg-emerald-500', 'badge' => 'bg-emerald-50 dark:bg-emerald-950/50 text-emerald-700 dark:text-emerald-300 border-emerald-200 dark:border-emerald-800'],
            'K4' => ['bg' => 'bg-blue-600 dark:bg-blue-500', 'badge' => 'bg-blue-50 dark:bg-blue-950/50 text-blue-700 dark:text-blue-300 border-blue-200 dark:border-blue-800'],
            'K5' => ['bg' => 'bg-amber-600 dark:bg-amber-500', 'badge' => 'bg-amber-50 dark:bg-amber-950/50 text-amber-700 dark:text-amber-300 border-amber-200 dark:border-amber-800'],
            'K6' => ['bg' => 'bg-purple-600 dark:bg-purple-500', 'badge' => 'bg-purple-50 dark:bg-purple-950/50 text-purple-700 dark:text-purple-300 border-purple-200 dark:border-purple-800'],
            'K7' => ['bg' => 'bg-teal-600 dark:bg-teal-500', 'badge' => 'bg-teal-50 dark:bg-teal-950/50 text-teal-700 dark:text-teal-300 border-teal-200 dark:border-teal-800'],
            'K8' => ['bg' => 'bg-rose-600 dark:bg-rose-500', 'badge' => 'bg-rose-50 dark:bg-rose-950/50 text-rose-700 dark:text-rose-300 border-rose-200 dark:border-rose-800'],
            'K9' => ['bg' => 'bg-violet-600 dark:bg-violet-500', 'badge' => 'bg-violet-50 dark:bg-violet-950/50 text-violet-700 dark:text-violet-300 border-violet-200 dark:border-violet-800'],
        ];

        $isFiltered = $search !== '' || ($selectedCriterion !== '' && $selectedCriterion !== 'all') || ($selectedYearId !== null && $selectedYearId > 0);
        $baseRouteParams = array_filter([
            'unitCode' => $unit->code,
            'year' => ($selectedYearId !== null && $selectedYearId > 0) ? $selectedYearId : null,
            'q' => $search !== '' ? $search : null,
        ], fn ($v): bool => $v !== null && $v !== '');
    @endphp

    <!-- Top Institutional Navbar -->
    <header class="bg-white dark:bg-slate-900 border-b border-slate-200 dark:border-slate-800 shrink-0 sticky top-0 z-30 shadow-xs transition-colors">
        <div class="max-w-[1920px] mx-auto px-4 sm:px-6 py-2.5 flex items-center justify-between gap-4">
            <!-- Left Branding & Unit Context -->
            <div class="flex items-center gap-3 min-w-0">
                @if($logoUrl)
                    <img src="{{ $logoUrl }}" alt="{{ $activeSettings->university_name }}" class="h-10 w-auto object-contain shrink-0">
                @else
                    <div class="h-10 px-2.5 rounded-lg bg-navy-900 dark:bg-blue-600 text-white flex items-center justify-center font-black text-xs tracking-wider shadow-sm shrink-0">
                        {{ $activeSettings->university_acronym ?: 'UNIVBI' }}
                    </div>
                @endif
                <div class="min-w-0">
                    <div class="flex items-center gap-2">
                        <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-bold tracking-wider uppercase bg-blue-50 dark:bg-blue-950/60 text-blue-700 dark:text-blue-300 border border-blue-200 dark:border-blue-800/80">
                            Portal Dossier Asesor
                        </span>
                        <span class="hidden sm:inline-block text-slate-300 dark:text-slate-600 font-light">|</span>
                        <span class="hidden sm:inline-block text-xs font-semibold text-slate-500 dark:text-slate-400">
                            Instrumen {{ $unit->effective_accreditation_instrument ?? $unit->accreditation_body ?? 'BAN-PT / LAM' }}
                        </span>
                    </div>
                    <div class="text-sm font-bold text-slate-900 dark:text-white truncate">
                        {{ $unit->name }} &bull; {{ $activeSettings->university_name ?: 'Universitas Bina Insan' }}
                    </div>
                </div>
            </div>

            <!-- Right Controls & Actions -->
            <div class="flex items-center gap-2 sm:gap-3 shrink-0">
                <button type="button"
                        id="themeToggleBtn"
                        onclick="toggleTheme()"
                        class="p-2 rounded-lg text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-800 transition"
                        title="Beralih Tema Gelap / Terang">
                    <svg class="w-4 h-4 hidden dark:block" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"/>
                    </svg>
                    <svg class="w-4 h-4 block dark:hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"/>
                    </svg>
                </button>

                <div class="hidden sm:inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold bg-emerald-50 dark:bg-emerald-950/40 text-emerald-700 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-800/60">
                    <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                    <span>Masa Asesmen Aktif</span>
                    @if($unit->assessor_access_end)
                        <span class="text-[11px] text-emerald-600 dark:text-emerald-300 font-normal">
                            (s.d. {{ $unit->assessor_access_end->translatedFormat('d M Y') }})
                        </span>
                    @endif
                </div>

                @if($activeDocument)
                    <a id="headerDownloadBtn"
                       href="{{ route('borang.public.download', $activeDocument->uuid) }}"
                       class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-lg text-xs font-bold bg-navy-900 hover:bg-blue-700 dark:bg-blue-600 dark:hover:bg-blue-500 text-white transition-colors shadow-sm">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                        </svg>
                        <span class="hidden sm:inline">Unduh Berkas (.PDF)</span>
                        <span class="sm:hidden">Unduh</span>
                    </a>
                @endif
            </div>
        </div>
    </header>

    <!-- Main Workspace (Full-height split layout on desktop) -->
    <main class="flex-1 flex flex-col lg:flex-row min-h-0 overflow-hidden bg-slate-100 dark:bg-slate-950 transition-colors">

        <!-- Left Sidebar: Dossier Directory, Search, Academic Year & Criteria Filter -->
        <aside class="w-full lg:w-[420px] xl:w-[460px] shrink-0 h-auto lg:h-full overflow-y-auto bg-slate-50 dark:bg-slate-900/60 border-r border-slate-200 dark:border-slate-800 p-4 sm:p-5 lg:p-6 space-y-4 custom-scrollbar">

            <!-- Card 1: Profil Program Studi, Search, Filter Tahun Akademik & Kriteria -->
            <div class="bg-white dark:bg-slate-900 rounded-xl border border-slate-200/80 dark:border-slate-800 p-4 sm:p-5 shadow-xs transition-colors space-y-3">
                <div class="flex items-center justify-between gap-2">
                    <span class="text-[11px] font-bold tracking-wider text-slate-500 dark:text-slate-400 uppercase">
                        Dossier Bukti Fisik Program Studi
                    </span>
                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[10px] font-bold bg-blue-50 dark:bg-blue-950/60 text-blue-700 dark:text-blue-300 border border-blue-200 dark:border-blue-800">
                        {{ $totalUnitDocuments }} Berkas
                    </span>
                </div>

                <div class="text-sm font-bold text-slate-900 dark:text-white leading-tight">
                    {{ $unit->name }}
                </div>

                <!-- Server-side Search & Academic Year Filter Form -->
                <search>
                    <form method="GET"
                          action="{{ route('borang.public.unit', ['unitCode' => $unit->code]) }}"
                          id="portalFilterForm"
                          aria-label="Pencarian dan Filter Dokumen Dossier"
                          class="space-y-2.5 pt-2 border-t border-slate-100 dark:border-slate-800/80">
                        @if($selectedCriterion !== '' && $selectedCriterion !== 'all')
                            <input type="hidden" name="criterion" value="{{ $selectedCriterion }}">
                        @endif

                        <div class="relative flex items-center gap-1.5">
                            <div class="relative flex-1">
                                <label for="portalSearchInput" class="sr-only">Cari judul atau nomor naskah</label>
                                <span class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-400" aria-hidden="true">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                                    </svg>
                                </span>
                                <input type="search"
                                       id="portalSearchInput"
                                       name="q"
                                       value="{{ $search }}"
                                       placeholder="Cari judul atau nomor naskah..."
                                       autocomplete="off"
                                       autocorrect="off"
                                       autocapitalize="none"
                                       spellcheck="false"
                                       enterkeyhint="search"
                                       class="w-full pl-9 pr-3 py-1.5 text-xs rounded-lg border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800/80 text-slate-800 dark:text-slate-100 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-blue-500/40 focus:border-blue-500">
                            </div>
                            <button type="submit"
                                    class="px-3 py-1.5 rounded-lg text-xs font-semibold bg-slate-800 hover:bg-blue-600 dark:bg-blue-600 dark:hover:bg-blue-500 text-white transition shrink-0 focus-visible:outline-2 focus-visible:outline-blue-600 focus-visible:outline-offset-2">
                                Cari
                            </button>
                        </div>

                        @if($availableYears->isNotEmpty())
                            @php
                                $selectedYearObj = $availableYears->firstWhere('id', $selectedYearId);
                                $selectedYearLabel = $selectedYearObj ? 'TA '.$selectedYearObj->name : 'Semua Tahun Akademik';
                            @endphp
                            <div class="flex items-center gap-2">
                                <span id="portalYearLabel" class="text-[11px] font-semibold text-slate-500 dark:text-slate-400 shrink-0">
                                    Tahun Akademik:
                                </span>
                                <div class="relative flex-1 min-w-0" id="portalYearDropdown">
                                    <input type="hidden" id="portalYearInput" name="year" value="{{ $selectedYearObj?->id ?? '' }}">
                                    <button type="button"
                                            id="portalYearTrigger"
                                            aria-haspopup="listbox"
                                            aria-expanded="false"
                                            aria-controls="portalYearListbox"
                                            aria-labelledby="portalYearLabel portalYearTriggerText"
                                            class="w-full flex items-center justify-between gap-2 py-1.5 px-2.5 text-xs font-medium rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-200 hover:border-blue-400 dark:hover:border-blue-500 focus-visible:outline-2 focus-visible:outline-blue-600 focus-visible:outline-offset-2 transition">
                                        <span class="flex items-center gap-1.5 min-w-0">
                                            <svg class="w-3.5 h-3.5 text-slate-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                            </svg>
                                            <span id="portalYearTriggerText" class="truncate">{{ $selectedYearLabel }}</span>
                                        </span>
                                        <svg id="portalYearChevron" class="w-3.5 h-3.5 text-slate-400 shrink-0 transition-transform duration-150" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                                        </svg>
                                    </button>

                                    <ul id="portalYearListbox"
                                        role="listbox"
                                        aria-labelledby="portalYearLabel"
                                        tabindex="-1"
                                        class="hidden absolute left-0 right-0 mt-1.5 z-40 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 shadow-lg py-1 max-h-60 overflow-y-auto custom-scrollbar">
                                        @php $isAllYearSelected = ! $selectedYearObj; @endphp
                                        <li role="option"
                                            data-value=""
                                            data-label="Semua Tahun Akademik"
                                            aria-selected="{{ $isAllYearSelected ? 'true' : 'false' }}"
                                            tabindex="-1"
                                            class="js-year-option flex items-center justify-between gap-2 px-3 py-1.5 text-xs cursor-pointer transition {{ $isAllYearSelected ? 'bg-blue-50 dark:bg-blue-950/60 text-blue-700 dark:text-blue-300 font-semibold' : 'text-slate-700 dark:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-700/70' }}">
                                            <span class="truncate">Semua Tahun Akademik</span>
                                            <svg class="w-3.5 h-3.5 text-blue-600 dark:text-blue-400 shrink-0 {{ $isAllYearSelected ? '' : 'hidden' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                                            </svg>
                                        </li>
                                        @foreach($availableYears as $ay)
                                            @php $isThisYearSelected = $selectedYearId === (int) $ay->id; @endphp
                                            <li role="option"
                                                data-value="{{ $ay->id }}"
                                                data-label="TA {{ $ay->name }}"
                                                aria-selected="{{ $isThisYearSelected ? 'true' : 'false' }}"
                                                tabindex="-1"
                                                class="js-year-option flex items-center justify-between gap-2 px-3 py-1.5 text-xs cursor-pointer transition {{ $isThisYearSelected ? 'bg-blue-50 dark:bg-blue-950/60 text-blue-700 dark:text-blue-300 font-semibold' : 'text-slate-700 dark:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-700/70' }}">
                                                <span class="truncate">TA {{ $ay->name }}</span>
                                                <svg class="w-3.5 h-3.5 text-blue-600 dark:text-blue-400 shrink-0 {{ $isThisYearSelected ? '' : 'hidden' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                                                </svg>
                                            </li>
                                        @endforeach
                                    </ul>
                                </div>
                            </div>
                        @endif
                    </form>
                </search>

                <!-- Server-side Interactive Filter per Kriteria BAN-PT / LAM -->
                <div class="pt-2 border-t border-slate-100 dark:border-slate-800/80">
                    <div class="flex items-center justify-between gap-2 mb-2">
                        <span class="text-[11px] font-semibold text-slate-500 dark:text-slate-400">
                            Filter Kriteria Akreditasi:
                        </span>
                        @if($isFiltered)
                            <a href="{{ route('borang.public.unit', ['unitCode' => $unit->code]) }}"
                               class="text-[11px] font-semibold text-blue-600 dark:text-blue-400 hover:underline">
                                Reset Filter
                            </a>
                        @endif
                    </div>
                    <div class="flex flex-wrap gap-1.5" id="criteriaFilterBar">
                        @php $isAllActive = ($selectedCriterion === '' || $selectedCriterion === 'all'); @endphp
                        <a href="{{ route('borang.public.unit', $baseRouteParams) }}"
                           data-criteria="all"
                           class="criteria-btn px-2.5 py-1 rounded text-xs font-bold transition {{ $isAllActive ? 'active bg-blue-600 text-white shadow-xs' : 'bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-300 border border-slate-200 dark:border-slate-700 hover:bg-slate-100 dark:hover:bg-slate-700' }}">
                            Semua ({{ $totalFilteredYearDocuments ?? $totalUnitDocuments }})
                        </a>

                        @foreach($criteriaSummary as $cSum)
                            @php
                                $isCritActive = $selectedCriterion === $cSum->code;
                                $critRouteParams = array_merge($baseRouteParams, ['criterion' => $cSum->code]);
                            @endphp
                            <a href="{{ route('borang.public.unit', $critRouteParams) }}"
                               data-criteria="{{ $cSum->code }}"
                               title="{{ $cSum->name }}"
                               class="criteria-btn px-2.5 py-1 rounded text-xs font-semibold transition {{ $isCritActive ? 'active bg-blue-600 text-white shadow-xs' : 'bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-300 border border-slate-200 dark:border-slate-700 hover:bg-slate-100 dark:hover:bg-slate-700' }}">
                                {{ $cSum->code }} ({{ (int) $cSum->doc_count }})
                            </a>
                        @endforeach
                    </div>
                </div>
            </div>

            <!-- Card 2: Daftar Berkas Bukti Terpaginasi (Maks 25 per Halaman DOM) -->
            <div class="space-y-2.5" id="documentsListContainer">
                @forelse($documents as $doc)
                    @php
                        $isActive = $activeDocument && $doc->id === $activeDocument->id;
                        $docStreamUrl = route('borang.public.stream', $doc->uuid);
                        $docDownloadUrl = route('borang.public.download', $doc->uuid);
                        $docTrackUrl = route('borang.public.unit.track', ['unitCode' => $unit->code, 'uuid' => $doc->uuid]);
                        $docPageUrl = route('borang.public.unit', array_filter([
                            'unitCode' => $unit->code,
                            'doc' => $doc->uuid,
                            'criterion' => ($selectedCriterion !== '' && $selectedCriterion !== 'all') ? $selectedCriterion : null,
                            'year' => ($selectedYearId !== null && $selectedYearId > 0) ? $selectedYearId : null,
                            'q' => $search !== '' ? $search : null,
                            'page' => $documents->currentPage() > 1 ? $documents->currentPage() : null,
                        ], fn ($v): bool => $v !== null && $v !== ''));
                    @endphp

                    <div class="document-item-card transition" data-doc-uuid="{{ $doc->uuid }}">
                        <a href="{{ $docPageUrl }}"
                           onclick="return switchActiveDocument(event, this)"
                           data-uuid="{{ $doc->uuid }}"
                           data-title="{{ $doc->title }}"
                           data-number="{{ $doc->document_number ?: 'Dokumen Sah' }}"
                           data-year="{{ $doc->academicYear?->name ?? '' }}"
                           data-stream-url="{{ $docStreamUrl }}"
                           data-download-url="{{ $docDownloadUrl }}"
                           data-track-url="{{ $docTrackUrl }}"
                           data-page-url="{{ $docPageUrl }}"
                           class="js-doc-card block p-4 rounded-xl bg-white dark:bg-slate-900 transition group {{ $isActive ? 'border-2 border-blue-500 shadow-sm' : 'border border-slate-200 dark:border-slate-800 hover:border-blue-400 dark:hover:border-blue-600 hover:shadow-xs' }}">
                            <div class="flex items-center justify-between gap-2 mb-2">
                                <span class="js-active-badge inline-flex items-center gap-1.5 px-2 py-0.5 rounded text-[10px] font-bold bg-blue-600 text-white shadow-xs {{ $isActive ? '' : 'hidden' }}">
                                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse"></span>
                                    Sedang Dibuka
                                </span>

                                <span class="js-inactive-prompt text-[11px] font-semibold text-slate-500 dark:text-slate-400 group-hover:text-blue-600 dark:group-hover:text-blue-400 flex items-center gap-1 {{ $isActive ? 'hidden' : '' }}">
                                    <span>Buka Dokumen</span>
                                    <svg class="w-3.5 h-3.5 group-hover:translate-x-0.5 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                                    </svg>
                                </span>

                                <div class="flex flex-wrap gap-1 justify-end">
                                    @foreach($doc->accreditationCriteria as $c)
                                        @php
                                            $color = $criterionColors[$c->code] ?? ['bg' => 'bg-blue-600', 'badge' => 'bg-blue-50 text-blue-700'];
                                        @endphp
                                        <span class="js-criterion-pill text-[10px] font-bold px-1.5 py-0.5 rounded {{ $isActive ? 'text-white '.$color['bg'] : 'bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300' }}"
                                              data-active-bg="{{ $color['bg'] }}">
                                            {{ $c->code }}
                                        </span>
                                    @endforeach
                                </div>
                            </div>

                            <div class="js-doc-title text-xs sm:text-sm leading-snug mb-1 transition-colors {{ $isActive ? 'font-bold text-slate-900 dark:text-white' : 'font-semibold text-slate-800 dark:text-slate-200 group-hover:text-blue-600 dark:group-hover:text-blue-400' }}">
                                {{ $doc->title }}
                            </div>

                            <div class="flex items-center flex-wrap gap-x-2 gap-y-1 text-[11px] text-slate-500 dark:text-slate-400">
                                <span class="font-mono select-all">{{ $doc->document_number }}</span>
                                @if($doc->academicYear)
                                    <span class="inline-flex items-center px-1.5 py-0.2 rounded text-[10px] font-semibold bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 border border-slate-200/80 dark:border-slate-700">
                                        TA {{ $doc->academicYear->name }}
                                    </span>
                                @endif
                                @if($doc->signer_name_snapshot)
                                    <span class="text-slate-300 dark:text-slate-600">&bull;</span>
                                    <span class="truncate" title="Pejabat Pengesah: {{ $doc->signer_name_snapshot }}">
                                        Pengesah: <strong class="text-slate-700 dark:text-slate-200 font-semibold">{{ $doc->signer_name_snapshot }}</strong>
                                    </span>
                                @endif
                            </div>

                            @if($doc->accreditationCriteria->isNotEmpty())
                                <div class="js-active-indicators mt-2.5 pt-2 border-t border-slate-100 dark:border-slate-800 text-[11px] text-slate-600 dark:text-slate-300 space-y-1 {{ $isActive ? '' : 'hidden' }}">
                                    @foreach($doc->accreditationCriteria as $c)
                                        <div class="flex items-center justify-between gap-1">
                                            <span class="font-medium truncate">{{ $c->name }}</span>
                                            @if($c->pivot?->indicator_code)
                                                <span class="font-mono font-semibold px-1.5 py-0.2 rounded bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 shrink-0">
                                                    {{ $c->pivot->indicator_code }}
                                                </span>
                                            @endif
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                        </a>
                    </div>
                @empty
                    <div class="p-6 text-center bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-800 text-xs text-slate-500">
                        @if($isFiltered)
                            Tidak ditemukan dokumen bukti akreditasi yang cocok dengan filter atau kata kunci Anda.
                        @else
                            Belum ada dokumen bukti fisik akreditasi yang disahkan untuk unit ini.
                        @endif
                    </div>
                @endforelse
            </div>

            <!-- Compact Simple Pagination Bar -->
            @if($documents->hasPages())
                <nav aria-label="Navigasi Halaman Dokumen" class="flex items-center justify-between gap-2 bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-800 px-3 py-2 text-xs shadow-xs">
                    @if($documents->onFirstPage())
                        <span class="px-2.5 py-1 rounded-lg text-slate-400 dark:text-slate-600 bg-slate-50 dark:bg-slate-800/50 cursor-not-allowed font-medium">
                            &larr; Sebelumnya
                        </span>
                    @else
                        <a href="{{ $documents->previousPageUrl() }}"
                           class="px-2.5 py-1 rounded-lg bg-slate-100 hover:bg-blue-600 hover:text-white dark:bg-slate-800 dark:hover:bg-blue-600 text-slate-700 dark:text-slate-200 font-semibold transition">
                            &larr; Sebelumnya
                        </a>
                    @endif

                    <span class="text-[11px] font-semibold text-slate-500 dark:text-slate-400">
                        Halaman {{ $documents->currentPage() }}
                    </span>

                    @if($documents->hasMorePages())
                        <a href="{{ $documents->nextPageUrl() }}"
                           class="px-2.5 py-1 rounded-lg bg-slate-100 hover:bg-blue-600 hover:text-white dark:bg-slate-800 dark:hover:bg-blue-600 text-slate-700 dark:text-slate-200 font-semibold transition">
                            Berikutnya &rarr;
                        </a>
                    @else
                        <span class="px-2.5 py-1 rounded-lg text-slate-400 dark:text-slate-600 bg-slate-50 dark:bg-slate-800/50 cursor-not-allowed font-medium">
                            Berikutnya &rarr;
                        </span>
                    @endif
                </nav>
            @endif

            <!-- Card 3: Jaminan Keabsahan & Integritas Dokumen -->
            <div class="bg-emerald-50/80 dark:bg-emerald-950/30 border border-emerald-200/90 dark:border-emerald-800/50 rounded-xl p-4 shadow-xs transition-colors">
                <div class="flex gap-3 items-start">
                    <div class="w-7 h-7 rounded-lg bg-emerald-100 dark:bg-emerald-900/60 text-emerald-700 dark:text-emerald-300 flex items-center justify-center shrink-0 mt-0.5">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                        </svg>
                    </div>
                    <div class="text-xs text-emerald-950 dark:text-emerald-200 leading-relaxed">
                        <span class="font-bold text-emerald-900 dark:text-emerald-300 block mb-0.5">Jaminan Keabsahan Naskah Digital:</span>
                        Seluruh naskah dalam portal dossier ini merupakan salinan digital sah yang telah divalidasi oleh Lembaga Penjaminan Mutu (LPM) Universitas Bina Insan, dilengkapi stempel digital dinas dan QR Code keabsahan naskah.
                    </div>
                </div>
            </div>

            <!-- Helpdesk Contact Info -->
            <div class="text-[11px] text-slate-500 dark:text-slate-400 text-center pt-2 pb-4">
                Lembaga Penjaminan Mutu (LPM) &bull; Gedung Rektorat Lt. 2 {{ $activeSettings->university_acronym ?: 'UNIVBI' }}<br>
                Bantuan Teknis: <a href="mailto:{{ $activeSettings->campus_email ?: 'lpm@univbi.ac.id' }}" class="text-blue-600 dark:text-blue-400 font-medium hover:underline">{{ $activeSettings->campus_email ?: 'lpm@univbi.ac.id' }}</a>
            </div>
        </aside>

        <!-- Right PDF Viewer & Active Document Canvas -->
        <section id="viewerContainer" class="flex-1 flex flex-col min-w-0 min-h-0 h-full relative overflow-hidden bg-slate-200/60 dark:bg-slate-950 transition-colors">
            @if($activeDocument)
                <!-- Slim, Airy 1-Row Viewer Toolbar -->
                <div class="h-12 shrink-0 bg-white dark:bg-slate-900 border-b border-slate-200 dark:border-slate-800 px-4 sm:px-5 flex items-center justify-between gap-3 text-xs shadow-xs transition-colors">
                    <div class="flex items-center gap-2 sm:gap-2.5 min-w-0">
                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded font-bold text-[11px] bg-red-50 dark:bg-red-950/50 text-red-700 dark:text-red-400 border border-red-200 dark:border-red-900/60 shrink-0">
                            <svg class="w-3.5 h-3.5 text-red-500" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4zm2 6a1 1 0 011-1h6a1 1 0 110 2H7a1 1 0 01-1-1zm1 3a1 1 0 100 2h6a1 1 0 100-2H7z" clip-rule="evenodd"/>
                            </svg>
                            PDF
                        </span>

                        <span id="activeDocNumber" class="font-mono font-bold text-slate-800 dark:text-slate-100 text-xs sm:text-sm select-all shrink-0">
                            {{ $activeDocument->document_number }}
                        </span>

                        <span id="activeDocYearBadge" class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-semibold bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 border border-slate-200 dark:border-slate-700 shrink-0 {{ $activeDocument->academicYear ? '' : 'hidden' }}">
                            {{ $activeDocument->academicYear ? 'TA '.$activeDocument->academicYear->name : '' }}
                        </span>

                        <span class="text-slate-300 dark:text-slate-600 hidden sm:inline">&bull;</span>

                        <span id="activeDocTitle" class="text-slate-600 dark:text-slate-400 truncate hidden sm:inline font-medium" title="{{ $activeDocument->title }}">
                            {{ $activeDocument->title }}
                        </span>
                    </div>

                    <div class="flex items-center gap-1.5 sm:gap-2 shrink-0">
                        <button type="button"
                                id="btnCopyActiveDoc"
                                data-doc-number="{{ $activeDocument->document_number }}"
                                onclick="copyActiveDocNumber(this.dataset.docNumber)"
                                class="inline-flex items-center gap-1 px-2.5 py-1 rounded text-xs font-semibold text-slate-700 dark:text-slate-300 bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 transition"
                                title="Salin Nomor Naskah Resmi">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                            </svg>
                            <span id="copyActiveDocText">Salin No.</span>
                        </button>

                        <a id="btnOpenNewTab"
                           href="{{ route('borang.public.stream', $activeDocument->uuid) }}"
                           target="_blank"
                           rel="noopener"
                           class="inline-flex items-center gap-1 px-2.5 py-1 rounded text-xs font-semibold text-slate-700 dark:text-slate-300 bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 transition"
                           title="Buka PDF di Tab Baru">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
                            </svg>
                            <span class="hidden sm:inline">Tab Baru</span>
                        </a>

                        <button type="button"
                                onclick="toggleFullscreen()"
                                class="inline-flex items-center gap-1 px-2.5 py-1 rounded text-xs font-semibold text-slate-700 dark:text-slate-300 bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 transition"
                                title="Layar Penuh">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8V4m0 0h4M4 4l5 5m11-1v4m0-4h-4m4 0l-5 5M4 16v4m0 0h4m-4 0l5-5m11 5l-5-5m5 5v-4m0 4h-4"/>
                            </svg>
                            <span class="hidden md:inline">Layar Penuh</span>
                        </button>
                    </div>
                </div>

                <!-- PDF Embed Canvas -->
                <div class="flex-1 min-h-0 relative w-full bg-slate-300 dark:bg-slate-950">
                    <iframe id="activePdfFrame"
                            src="{{ route('borang.public.stream', $activeDocument->uuid) }}#view=FitH"
                            class="w-full h-full border-0 absolute inset-0 block bg-white"
                            title="Pratinjau Naskah Dinas PDF">
                    </iframe>
                </div>

                <!-- Mobile Fallback Helper -->
                <div class="lg:hidden bg-white dark:bg-slate-900 border-t border-slate-200 dark:border-slate-800 p-2 text-center text-xs text-slate-600 dark:text-slate-400">
                    Pratinjau PDF tidak tampil di ponsel?
                    <a id="mobileFallbackStreamLink"
                       href="{{ route('borang.public.stream', $activeDocument->uuid) }}"
                       target="_blank"
                       class="text-blue-600 dark:text-blue-400 font-bold underline ml-1">
                        Buka PDF Langsung
                    </a>
                </div>
            @else
                <div class="flex-1 flex items-center justify-center p-8 text-center text-slate-500">
                    <div>
                        <div class="w-12 h-12 rounded-xl bg-slate-200 dark:bg-slate-800 text-slate-400 flex items-center justify-center mx-auto mb-3">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                            </svg>
                        </div>
                        <h3 class="text-base font-bold text-slate-800 dark:text-slate-200">Tidak Ada Dokumen Aktif</h3>
                        <p class="text-xs text-slate-500 mt-1">Pilih salah satu dokumen bukti pada daftar di sisi kiri atau sesuaikan filter.</p>
                    </div>
                </div>
            @endif
        </section>
    </main>

    <script>
        function toggleTheme() {
            if (document.documentElement.classList.contains('dark')) {
                document.documentElement.classList.remove('dark');
                localStorage.theme = 'light';
            } else {
                document.documentElement.classList.add('dark');
                localStorage.theme = 'dark';
            }
        }

        function setCardActiveState(cardEl, isActive) {
            const activeBadge = cardEl.querySelector('.js-active-badge');
            const inactivePrompt = cardEl.querySelector('.js-inactive-prompt');
            const titleEl = cardEl.querySelector('.js-doc-title');
            const indicatorsEl = cardEl.querySelector('.js-active-indicators');
            const criterionPills = cardEl.querySelectorAll('.js-criterion-pill');

            if (isActive) {
                cardEl.classList.remove('border', 'border-slate-200', 'dark:border-slate-800', 'hover:border-blue-400', 'dark:hover:border-blue-600', 'hover:shadow-xs');
                cardEl.classList.add('border-2', 'border-blue-500', 'shadow-sm');
                if (activeBadge) activeBadge.classList.remove('hidden');
                if (inactivePrompt) inactivePrompt.classList.add('hidden');
                if (indicatorsEl) indicatorsEl.classList.remove('hidden');
                if (titleEl) {
                    titleEl.classList.remove('font-semibold', 'text-slate-800', 'dark:text-slate-200');
                    titleEl.classList.add('font-bold', 'text-slate-900', 'dark:text-white');
                }
                criterionPills.forEach((pill) => {
                    const bgClasses = (pill.dataset.activeBg || 'bg-blue-600').split(' ');
                    pill.classList.remove('bg-slate-100', 'dark:bg-slate-800', 'text-slate-700', 'dark:text-slate-300');
                    pill.classList.add('text-white', ...bgClasses);
                });
            } else {
                cardEl.classList.remove('border-2', 'border-blue-500', 'shadow-sm');
                cardEl.classList.add('border', 'border-slate-200', 'dark:border-slate-800', 'hover:border-blue-400', 'dark:hover:border-blue-600', 'hover:shadow-xs');
                if (activeBadge) activeBadge.classList.add('hidden');
                if (inactivePrompt) inactivePrompt.classList.remove('hidden');
                if (indicatorsEl) indicatorsEl.classList.add('hidden');
                if (titleEl) {
                    titleEl.classList.remove('font-bold', 'text-slate-900', 'dark:text-white');
                    titleEl.classList.add('font-semibold', 'text-slate-800', 'dark:text-slate-200');
                }
                criterionPills.forEach((pill) => {
                    const bgClasses = (pill.dataset.activeBg || 'bg-blue-600').split(' ');
                    pill.classList.remove('text-white', ...bgClasses);
                    pill.classList.add('bg-slate-100', 'dark:bg-slate-800', 'text-slate-700', 'dark:text-slate-300');
                });
            }
        }

        function switchActiveDocument(event, cardEl) {
            if (event.metaKey || event.ctrlKey || event.shiftKey || event.altKey || event.button !== 0) {
                return true;
            }

            const frame = document.getElementById('activePdfFrame');
            if (!frame) {
                return true;
            }

            event.preventDefault();

            const streamUrl = cardEl.dataset.streamUrl;
            const downloadUrl = cardEl.dataset.downloadUrl;
            const trackUrl = cardEl.dataset.trackUrl;
            const pageUrl = cardEl.dataset.pageUrl;
            const docNumber = cardEl.dataset.number || '';
            const docTitle = cardEl.dataset.title || '';
            const docYear = cardEl.dataset.year || '';

            document.querySelectorAll('.js-doc-card').forEach((el) => {
                setCardActiveState(el, el === cardEl);
            });

            frame.src = streamUrl + '#view=FitH';

            const numEl = document.getElementById('activeDocNumber');
            const titleEl = document.getElementById('activeDocTitle');
            const yearBadgeEl = document.getElementById('activeDocYearBadge');
            const copyBtn = document.getElementById('btnCopyActiveDoc');
            const newTabBtn = document.getElementById('btnOpenNewTab');
            const headerDownloadBtn = document.getElementById('headerDownloadBtn');
            const mobileFallback = document.getElementById('mobileFallbackStreamLink');

            if (numEl) numEl.textContent = docNumber;
            if (titleEl) {
                titleEl.textContent = docTitle;
                titleEl.title = docTitle;
            }
            if (yearBadgeEl) {
                if (docYear) {
                    yearBadgeEl.textContent = 'TA ' + docYear;
                    yearBadgeEl.classList.remove('hidden');
                } else {
                    yearBadgeEl.textContent = '';
                    yearBadgeEl.classList.add('hidden');
                }
            }
            if (copyBtn) copyBtn.dataset.docNumber = docNumber;
            if (newTabBtn) newTabBtn.href = streamUrl;
            if (headerDownloadBtn) headerDownloadBtn.href = downloadUrl;
            if (mobileFallback) mobileFallback.href = streamUrl;

            if (pageUrl && window.history && window.history.replaceState) {
                window.history.replaceState({}, '', pageUrl);
            }

            if (trackUrl) {
                const csrfMeta = document.querySelector('meta[name="csrf-token"]');
                fetch(trackUrl, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': csrfMeta ? csrfMeta.getAttribute('content') : '',
                        'Accept': 'application/json',
                    },
                    keepalive: true,
                }).catch(() => {});
            }

            return false;
        }

        function copyActiveDocNumber(text) {
            if (!text) return;
            const btnText = document.getElementById('copyActiveDocText');
            const originalText = btnText.innerText;

            const onSuccess = () => {
                btnText.innerText = 'Tersalin!';
                setTimeout(() => {
                    btnText.innerText = originalText;
                }, 2000);
            };

            if (navigator.clipboard && window.isSecureContext) {
                navigator.clipboard.writeText(text).then(onSuccess).catch(() => fallbackCopy(text, onSuccess));
            } else {
                fallbackCopy(text, onSuccess);
            }
        }

        function fallbackCopy(text, callback) {
            const textArea = document.createElement('textarea');
            textArea.value = text;
            textArea.style.position = 'fixed';
            textArea.style.left = '-9999px';
            textArea.style.top = '0';
            document.body.appendChild(textArea);
            textArea.focus();
            textArea.select();
            try {
                document.execCommand('copy');
                if (callback) callback();
            } catch (err) {
                console.error('Copy fallback failed', err);
            }
            document.body.removeChild(textArea);
        }

        function toggleFullscreen() {
            const el = document.getElementById('viewerContainer');
            if (!document.fullscreenElement) {
                if (el.requestFullscreen) {
                    el.requestFullscreen();
                } else if (el.webkitRequestFullscreen) {
                    el.webkitRequestFullscreen();
                }
            } else {
                if (document.exitFullscreen) {
                    document.exitFullscreen();
                }
            }
        }

        (function initPortalSearchDebounce() {
            const searchInput = document.getElementById('portalSearchInput');
            const filterForm = document.getElementById('portalFilterForm');
            if (!searchInput || !filterForm) return;

            let timer = null;
            let lastSubmitted = searchInput.value;
            let isComposing = false;

            searchInput.addEventListener('compositionstart', () => {
                isComposing = true;
            });
            searchInput.addEventListener('compositionend', () => {
                isComposing = false;
            });

            searchInput.addEventListener('input', (event) => {
                if (event.isComposing || isComposing) return;
                clearTimeout(timer);
                timer = setTimeout(() => {
                    if (searchInput.value !== lastSubmitted) {
                        lastSubmitted = searchInput.value;
                        filterForm.submit();
                    }
                }, 480);
            });
        })();

        (function initPortalYearDropdown() {
            const wrapper = document.getElementById('portalYearDropdown');
            const trigger = document.getElementById('portalYearTrigger');
            const triggerText = document.getElementById('portalYearTriggerText');
            const chevron = document.getElementById('portalYearChevron');
            const listbox = document.getElementById('portalYearListbox');
            const hiddenInput = document.getElementById('portalYearInput');
            const filterForm = document.getElementById('portalFilterForm');

            if (!wrapper || !trigger || !listbox || !hiddenInput || !filterForm) return;

            const options = Array.from(listbox.querySelectorAll('.js-year-option'));
            let activeIndex = Math.max(0, options.findIndex((opt) => opt.getAttribute('aria-selected') === 'true'));

            const openMenu = () => {
                listbox.classList.remove('hidden');
                trigger.setAttribute('aria-expanded', 'true');
                if (chevron) chevron.classList.add('rotate-180');
                if (options[activeIndex]) {
                    options[activeIndex].focus();
                }
            };

            const closeMenu = (restoreFocus = false) => {
                listbox.classList.add('hidden');
                trigger.setAttribute('aria-expanded', 'false');
                if (chevron) chevron.classList.remove('rotate-180');
                if (restoreFocus) {
                    trigger.focus();
                }
            };

            const selectOption = (optionEl) => {
                if (!optionEl) return;
                const val = optionEl.dataset.value ?? '';
                const label = optionEl.dataset.label ?? '';

                hiddenInput.value = val;
                if (triggerText) triggerText.textContent = label;

                options.forEach((opt) => {
                    opt.setAttribute('aria-selected', opt === optionEl ? 'true' : 'false');
                });

                closeMenu(false);
                filterForm.submit();
            };

            trigger.addEventListener('click', () => {
                if (listbox.classList.contains('hidden')) {
                    openMenu();
                } else {
                    closeMenu(false);
                }
            });

            trigger.addEventListener('keydown', (event) => {
                if (event.key === 'ArrowDown' || event.key === 'ArrowUp' || event.key === 'Enter' || event.key === ' ') {
                    event.preventDefault();
                    openMenu();
                }
            });

            listbox.addEventListener('keydown', (event) => {
                if (event.key === 'Escape') {
                    event.preventDefault();
                    closeMenu(true);
                    return;
                }
                if (event.key === 'ArrowDown') {
                    event.preventDefault();
                    activeIndex = (activeIndex + 1) % options.length;
                    options[activeIndex].focus();
                    return;
                }
                if (event.key === 'ArrowUp') {
                    event.preventDefault();
                    activeIndex = (activeIndex - 1 + options.length) % options.length;
                    options[activeIndex].focus();
                    return;
                }
                if (event.key === 'Home') {
                    event.preventDefault();
                    activeIndex = 0;
                    options[activeIndex].focus();
                    return;
                }
                if (event.key === 'End') {
                    event.preventDefault();
                    activeIndex = options.length - 1;
                    options[activeIndex].focus();
                    return;
                }
                if (event.key === 'Enter' || event.key === ' ') {
                    event.preventDefault();
                    selectOption(options[activeIndex]);
                }
            });

            options.forEach((opt, idx) => {
                opt.addEventListener('mouseenter', () => {
                    activeIndex = idx;
                });
                opt.addEventListener('click', () => {
                    selectOption(opt);
                });
            });

            document.addEventListener('click', (event) => {
                if (!wrapper.contains(event.target) && !listbox.classList.contains('hidden')) {
                    closeMenu(false);
                }
            });
        })();
    </script>
</body>
</html>
