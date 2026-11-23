<!DOCTYPE html>
<html lang="id" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Warta & Dokumentasi Kegiatan Kampus | {{ ($settings ?? $siteSettings ?? \App\Models\LandingPageSetting::current())->university_name }}</title>

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
        /* Sembunyikan scrollbar bawaan tapi tetap mempertahankan fungsionalitas scroll */
        .no-scrollbar::-webkit-scrollbar {
            display: none;
        }
        .no-scrollbar {
            -ms-overflow-style: none;
            scrollbar-width: none;
        }
        /* Hilangkan outline putih bawaan browser saat input fokus */
        input:focus,
        input:focus-visible,
        button:focus,
        select:focus {
            outline: none !important;
            box-shadow: none !important;
        }
        /* Card headline utama: stretched link + ringkas multi-baris */
        .featured-headline-card a.card-link::after {
            content: "";
            position: absolute;
            inset: 0;
        }
        .featured-headline-card:has(a.card-link:focus-visible) {
            outline: 2px solid rgb(37 99 235);
            outline-offset: 2px;
        }
        .featured-headline-card a:not(.card-link),
        .featured-headline-card button {
            position: relative;
            z-index: 1;
        }
        .headline-excerpt {
            display: -webkit-box;
            -webkit-line-clamp: 4;
            -webkit-box-orient: vertical;
            line-clamp: 4;
            overflow: clip;
        }
    </style>
</head>
<body class="min-h-full flex flex-col font-sans antialiased text-slate-800 dark:text-slate-100 bg-slate-50 dark:bg-slate-950 selection:bg-blue-100 selection:text-blue-900 transition-colors duration-200">

    <!-- Header Institusi Resmi (Seragam & Terstruktur) -->
    @include('public.partials.header')

    <!-- Hero Section / Search Bar -->
    <section class="bg-gradient-to-b from-white via-slate-50 to-slate-100/70 dark:from-slate-900 dark:via-slate-900/90 dark:to-slate-950 border-b border-slate-200 dark:border-slate-800/80 pt-6 pb-5 sm:py-10 transition-colors">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <!-- Breadcrumb Navigation (Membungkus Rapi ke Bawah jika Layar Sempit) -->
            <nav aria-label="Breadcrumb" class="flex flex-wrap items-center gap-x-2 gap-y-1.5 text-xs text-slate-500 dark:text-slate-400 mb-4 leading-relaxed">
                <a href="{{ route('landing.home') }}" class="inline-flex items-center gap-1 shrink-0 whitespace-nowrap hover:text-blue-600 dark:hover:text-blue-400 font-medium transition-colors">
                    <svg class="w-3.5 h-3.5 text-slate-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                    </svg>
                    <span>Beranda</span>
                </a>
                <span class="text-slate-300 dark:text-slate-600 shrink-0">/</span>
                <span class="{{ $selectedCategorySlug ? 'hover:text-blue-600 dark:hover:text-blue-400' : 'text-slate-800 dark:text-slate-200 font-semibold' }} shrink-0 whitespace-nowrap">
                    @if($selectedCategorySlug)
                        <a href="{{ route('public.posts.index') }}" class="transition-colors">Warta & Liputan</a>
                    @else
                        Warta & Liputan
                    @endif
                </span>
                @if($selectedCategorySlug && $currentCat = $categories->firstWhere('slug', $selectedCategorySlug))
                    <span class="text-slate-300 dark:text-slate-600 shrink-0">/</span>
                    <span class="text-slate-800 dark:text-slate-200 font-semibold break-words">
                        {{ $currentCat->name }}
                    </span>
                @endif
            </nav>

            <div class="max-w-3xl">
                <!-- Badge Kategori Atas (Sedikit Mengotak) -->
                <div class="inline-flex items-center gap-2 px-3 py-1 rounded-md text-xs font-semibold bg-blue-50 dark:bg-blue-950/70 text-blue-700 dark:text-blue-300 border border-blue-200/60 dark:border-blue-800/60 mb-3.5">
                    <svg class="w-3.5 h-3.5 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10a2 2 0 012 2v1m2 13a2 2 0 01-2-2V7m2 13a2 2 0 002-2V9a2 2 0 00-2-2h-2m-4-3H9M7 16h6M7 8h6v4H7V8z" />
                    </svg>
                    Portal Publikasi & Dokumentasi Kampus
                </div>

                <h1 class="text-2xl sm:text-4xl font-extrabold text-slate-900 dark:text-white tracking-tight leading-tight">
                    Warta, Pengumuman & Galeri Kegiatan
                </h1>
                <p class="mt-2.5 text-sm sm:text-base text-slate-600 dark:text-slate-400 leading-relaxed">
                    Liputan kegiatan ilmiah, kerja sama institusi, prestasi sivitas akademika, dan pengumuman resmi Universitas Bina Insan.
                </p>

                <!-- Search Input Form (Terpadu & Modern) -->
                <form action="{{ route('public.posts.index') }}" method="GET" class="mt-5 max-w-xl">
                    @if($selectedCategorySlug)
                        <input type="hidden" name="kategori" value="{{ $selectedCategorySlug }}">
                    @endif
                    @if($selectedPeriod)
                        <input type="hidden" name="edisi" value="{{ $selectedPeriod->value }}">
                    @endif
                    <div class="relative flex items-center bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl p-1.5 shadow-xs focus-within:border-blue-500 focus-within:ring-2 focus-within:ring-blue-500/20 transition-all">
                        <div class="pl-3 pr-2 text-slate-400 shrink-0">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                            </svg>
                        </div>
                        <input type="text"
                            name="q"
                            value="{{ $searchQuery }}"
                            placeholder="Cari berita, acara, atau lokasi..."
                            class="w-full min-w-0 flex-1 pr-2 bg-transparent border-0 border-none outline-none focus:outline-none focus:ring-0 text-xs sm:text-sm text-slate-900 dark:text-white placeholder-slate-400 truncate">
                        @if(!empty($searchQuery) || !empty($selectedCategorySlug) || $selectedPeriod !== null)
                            <a href="{{ route('public.posts.index') }}" class="mr-1.5 px-2.5 py-1.5 text-xs text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200 font-medium transition-colors shrink-0 whitespace-nowrap" title="Reset pencarian">
                                Reset
                            </a>
                        @endif
                        <button type="submit" class="px-4 py-2 rounded-lg bg-blue-600 hover:bg-blue-700 text-white text-xs font-semibold shadow-xs hover:shadow-sm transition-all shrink-0 cursor-pointer">
                            Cari
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </section>

    <!-- Main Content Area -->
    <main class="flex-1 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-4 pb-8 sm:py-8 w-full">
        <!-- Filter Edisi & Kategori: Custom Styled Dropdown di Mobile, Pill Berwarna di Desktop -->
        @php
            $periodPillStyles = [
                \App\Enums\PostPeriod::Daily->value => [
                    'active' => 'bg-rose-600 text-white border-rose-600 font-semibold shadow-xs',
                    'idle' => 'bg-rose-50/80 dark:bg-rose-950/40 text-rose-700 dark:text-rose-300 border-rose-200 dark:border-rose-800/60 hover:bg-rose-100 dark:hover:bg-rose-900/50 font-medium',
                    'menuActive' => 'bg-rose-50 text-rose-700 dark:bg-rose-950/60 dark:text-rose-300 font-bold',
                    'check' => 'text-rose-600 dark:text-rose-400',
                    'dot' => 'bg-rose-500',
                ],
                \App\Enums\PostPeriod::Weekly->value => [
                    'active' => 'bg-amber-500 text-white border-amber-500 font-semibold shadow-xs',
                    'idle' => 'bg-amber-50/80 dark:bg-amber-950/40 text-amber-800 dark:text-amber-300 border-amber-200 dark:border-amber-800/60 hover:bg-amber-100 dark:hover:bg-amber-900/50 font-medium',
                    'menuActive' => 'bg-amber-50 text-amber-800 dark:bg-amber-950/60 dark:text-amber-300 font-bold',
                    'check' => 'text-amber-600 dark:text-amber-400',
                    'dot' => 'bg-amber-500',
                ],
                \App\Enums\PostPeriod::Monthly->value => [
                    'active' => 'bg-blue-600 text-white border-blue-600 font-semibold shadow-xs',
                    'idle' => 'bg-blue-50/80 dark:bg-blue-950/40 text-blue-700 dark:text-blue-300 border-blue-200 dark:border-blue-800/60 hover:bg-blue-100 dark:hover:bg-blue-900/50 font-medium',
                    'menuActive' => 'bg-blue-50 text-blue-700 dark:bg-blue-950/60 dark:text-blue-300 font-bold',
                    'check' => 'text-blue-600 dark:text-blue-400',
                    'dot' => 'bg-blue-500',
                ],
            ];
            $activePeriodStyle = $selectedPeriod ? ($periodPillStyles[$selectedPeriod->value] ?? null) : null;
            $activeCategoryObj = $selectedCategorySlug ? $categories->firstWhere('slug', $selectedCategorySlug) : null;
        @endphp

        <div class="mb-6 sm:mb-8">
            <!-- Tampilan Mobile (< md): Custom Styled Dropdown (Konsisten dengan Landing Page) -->
            <div class="md:hidden grid grid-cols-1 sm:grid-cols-2 gap-2.5" id="mobile-custom-filters">
                <!-- Custom Dropdown Edisi -->
                <div class="relative" data-mobile-dropdown="edisi">
                    <button type="button"
                        id="mobile-filter-edisi"
                        data-dropdown-trigger="edisi"
                        aria-haspopup="true"
                        aria-expanded="false"
                        aria-controls="mobile-menu-edisi"
                        class="w-full flex items-center justify-between gap-2 px-3.5 py-2.5 rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 text-xs font-semibold text-slate-800 dark:text-slate-100 hover:border-slate-300 dark:hover:border-slate-700 shadow-2xs transition-all cursor-pointer">
                        <span class="flex items-center gap-2 min-w-0">
                            <span class="w-2 h-2 rounded-full shrink-0 {{ $activePeriodStyle['dot'] ?? 'bg-slate-400 dark:bg-slate-500' }}"></span>
                            <span class="truncate">
                                {{ $selectedPeriod ? 'Edisi: ' . $selectedPeriod->getShortLabel() : 'Semua Edisi' }}
                            </span>
                        </span>
                        <svg data-dropdown-chevron class="w-4 h-4 text-slate-400 shrink-0 transition-transform duration-200" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                        </svg>
                    </button>

                    <div id="mobile-menu-edisi"
                        data-dropdown-menu="edisi"
                        class="hidden absolute z-30 left-0 right-0 mt-1.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 p-1.5 shadow-xl space-y-0.5">
                        <a href="{{ route('public.posts.index', array_filter(['kategori' => $selectedCategorySlug, 'q' => $searchQuery])) }}"
                            class="flex items-center justify-between gap-2 px-3 py-2 rounded-lg text-xs transition-colors {{ $selectedPeriod === null ? 'bg-slate-100 dark:bg-slate-800 text-slate-900 dark:text-white font-bold' : 'text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-800/60 font-medium' }}">
                            <span class="flex items-center gap-2">
                                <span class="w-2 h-2 rounded-full bg-slate-400 dark:bg-slate-500 shrink-0"></span>
                                <span>Semua Edisi</span>
                            </span>
                            @if($selectedPeriod === null)
                                <svg class="w-4 h-4 text-slate-700 dark:text-slate-200 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                </svg>
                            @endif
                        </a>

                        <div class="my-1 border-t border-slate-100 dark:border-slate-800"></div>

                        @foreach($periods as $periodCase)
                            @php
                                $style = $periodPillStyles[$periodCase->value] ?? $periodPillStyles[\App\Enums\PostPeriod::Monthly->value];
                                $isPeriodActive = $selectedPeriod === $periodCase;
                            @endphp
                            <a href="{{ route('public.posts.index', array_filter(['edisi' => $periodCase->value, 'kategori' => $selectedCategorySlug, 'q' => $searchQuery])) }}"
                                class="flex items-center justify-between gap-2 px-3 py-2 rounded-lg text-xs transition-colors {{ $isPeriodActive ? $style['menuActive'] : 'text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-800/60 font-medium' }}">
                                <span class="flex items-center gap-2">
                                    <span class="w-2 h-2 rounded-full {{ $style['dot'] }} shrink-0"></span>
                                    <span>{{ $periodCase->getShortLabel() }}</span>
                                </span>
                                @if($isPeriodActive)
                                    <svg class="w-4 h-4 {{ $style['check'] }} shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                    </svg>
                                @endif
                            </a>
                        @endforeach
                    </div>
                </div>

                <!-- Custom Dropdown Kategori -->
                <div class="relative" data-mobile-dropdown="kategori">
                    <button type="button"
                        id="mobile-filter-kategori"
                        data-dropdown-trigger="kategori"
                        aria-haspopup="true"
                        aria-expanded="false"
                        aria-controls="mobile-menu-kategori"
                        class="w-full flex items-center justify-between gap-2 px-3.5 py-2.5 rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 text-xs font-semibold text-slate-800 dark:text-slate-100 hover:border-slate-300 dark:hover:border-slate-700 shadow-2xs transition-all cursor-pointer">
                        <span class="flex items-center gap-2 min-w-0">
                            <svg class="w-4 h-4 text-blue-600 dark:text-blue-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z" />
                            </svg>
                            <span class="truncate">
                                {{ $activeCategoryObj?->name ?? 'Semua Kategori' }}
                            </span>
                        </span>
                        <svg data-dropdown-chevron class="w-4 h-4 text-slate-400 shrink-0 transition-transform duration-200" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                        </svg>
                    </button>

                    <div id="mobile-menu-kategori"
                        data-dropdown-menu="kategori"
                        class="hidden absolute z-30 left-0 right-0 mt-1.5 max-h-72 overflow-y-auto no-scrollbar rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 p-1.5 shadow-xl space-y-0.5">
                        <a href="{{ route('public.posts.index', array_filter(['edisi' => $selectedPeriod?->value, 'q' => $searchQuery])) }}"
                            class="flex items-center justify-between gap-2 px-3 py-2 rounded-lg text-xs transition-colors {{ empty($selectedCategorySlug) ? 'bg-blue-50 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300 font-bold' : 'text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-800/60 font-medium' }}">
                            <span class="flex items-center gap-2">
                                <span class="w-2 h-2 rounded-full {{ empty($selectedCategorySlug) ? 'bg-blue-600 dark:bg-blue-400' : 'bg-slate-300 dark:bg-slate-600' }} shrink-0"></span>
                                <span>Semua Kategori</span>
                            </span>
                            @if(empty($selectedCategorySlug))
                                <svg class="w-4 h-4 text-blue-600 dark:text-blue-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                </svg>
                            @endif
                        </a>

                        <div class="my-1 border-t border-slate-100 dark:border-slate-800"></div>

                        @foreach($categories as $category)
                            @php $isCatSelected = $selectedCategorySlug === $category->slug; @endphp
                            <a href="{{ route('public.posts.index', array_filter(['kategori' => $category->slug, 'edisi' => $selectedPeriod?->value, 'q' => $searchQuery])) }}"
                                class="flex items-center justify-between gap-2 px-3 py-2 rounded-lg text-xs transition-colors {{ $isCatSelected ? 'bg-blue-50 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300 font-bold' : 'text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-800/60 font-medium' }}">
                                <span class="truncate">{{ $category->name }}</span>
                                @if($isCatSelected)
                                    <svg class="w-4 h-4 text-blue-600 dark:text-blue-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                    </svg>
                                @endif
                            </a>
                        @endforeach
                    </div>
                </div>
            </div>

            <!-- Tampilan Desktop (>= md): Pill Berwarna Merah/Kuning/Biru untuk Edisi & Pill Kategori -->
            <div class="hidden md:block space-y-3">
                <div class="flex flex-wrap items-center gap-2 py-0.5">
                    <span class="text-[11px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500 shrink-0 mr-1">Edisi:</span>
                    <a href="{{ route('public.posts.index', array_filter(['kategori' => $selectedCategorySlug, 'q' => $searchQuery])) }}"
                        class="px-3 py-1.5 rounded-lg text-xs whitespace-nowrap transition-all shrink-0 border {{ $selectedPeriod === null ? 'bg-slate-900 dark:bg-white text-white dark:text-slate-900 border-slate-900 dark:border-white font-semibold shadow-xs' : 'bg-white dark:bg-slate-900 text-slate-700 dark:text-slate-300 border-slate-200 dark:border-slate-800 hover:bg-slate-100 dark:hover:bg-slate-800/80 font-medium' }}">
                        Semua Edisi
                    </a>
                    @foreach($periods as $periodCase)
                        @php
                            $style = $periodPillStyles[$periodCase->value] ?? $periodPillStyles[\App\Enums\PostPeriod::Monthly->value];
                            $isPeriodActive = $selectedPeriod === $periodCase;
                        @endphp
                        <a href="{{ route('public.posts.index', array_filter(['edisi' => $periodCase->value, 'kategori' => $selectedCategorySlug, 'q' => $searchQuery])) }}"
                            class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs whitespace-nowrap transition-all shrink-0 border {{ $isPeriodActive ? $style['active'] : $style['idle'] }}">
                            <span class="w-1.5 h-1.5 rounded-full shrink-0 {{ $isPeriodActive ? 'bg-white' : $style['dot'] }}"></span>
                            <span>{{ $periodCase->getShortLabel() }}</span>
                        </a>
                    @endforeach
                </div>

                <div class="flex flex-wrap items-center gap-2 py-0.5">
                    <span class="text-[11px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500 shrink-0 mr-1">Kategori:</span>
                    <a href="{{ route('public.posts.index', array_filter(['edisi' => $selectedPeriod?->value, 'q' => $searchQuery])) }}" 
                        class="px-3.5 py-2 rounded-lg text-xs whitespace-nowrap transition-all shrink-0 {{ empty($selectedCategorySlug) ? 'bg-blue-600 text-white font-semibold shadow-xs' : 'bg-white dark:bg-slate-900 text-slate-700 dark:text-slate-300 border border-slate-200 dark:border-slate-800 hover:bg-slate-100 dark:hover:bg-slate-800/80 font-medium' }}">
                        Semua Kategori
                    </a>
                    @foreach($categories as $category)
                        <a href="{{ route('public.posts.index', array_filter(['kategori' => $category->slug, 'edisi' => $selectedPeriod?->value, 'q' => $searchQuery])) }}" 
                            class="px-3.5 py-2 rounded-lg text-xs whitespace-nowrap transition-all shrink-0 {{ $selectedCategorySlug === $category->slug ? 'bg-blue-600 text-white font-semibold shadow-xs' : 'bg-white dark:bg-slate-900 text-slate-700 dark:text-slate-300 border border-slate-200 dark:border-slate-800 hover:bg-slate-100 dark:hover:bg-slate-800/80 font-medium' }}">
                            {{ $category->name }}
                        </a>
                    @endforeach
                </div>
            </div>
        </div>

        <!-- Featured Headline Post (Jika Ada) -->
        @if($featuredPost)
            @php
                $featPhotosCount = $featuredPost->photos_count ?? $featuredPost->photos->count();
                $featDocsCount = $featuredPost->public_documents_count ?? 0;
                $plainContent = trim(preg_replace('/\s+/', ' ', strip_tags((string) $featuredPost->content)) ?? '');
                $rawExcerpt = trim((string) $featuredPost->excerpt);
                $isExcerptRepeatingTitle = $rawExcerpt !== '' && strcasecmp($rawExcerpt, trim((string) $featuredPost->title)) === 0;
                $leadSummary = ($rawExcerpt !== '' && ! $isExcerptRepeatingTitle && mb_strlen($rawExcerpt) >= 60)
                    ? $rawExcerpt
                    : ($plainContent !== '' ? Str::limit($plainContent, 240) : ($rawExcerpt ?: 'Liputan dokumentasi kegiatan resmi Universitas Bina Insan.'));
                $wordCount = str_word_count($plainContent);
                $readingMinutes = max(1, (int) ceil($wordCount / 180));
                $eventDateLabel = $featuredPost->event_date?->translatedFormat('d M Y')
                    ?? $featuredPost->published_at?->translatedFormat('d M Y')
                    ?? '-';
            @endphp

            <article class="featured-headline-card relative mb-12 bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 p-4 sm:p-5 lg:p-6 shadow-xs hover:shadow-md hover:border-slate-300 dark:hover:border-slate-700 transition-all group">
                <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 lg:gap-7 items-stretch">
                    <!-- Foto Sampul Headline -->
                    <div class="lg:col-span-7 relative aspect-video lg:aspect-[16/10] rounded-xl overflow-hidden bg-slate-950 shadow-xs">
                        @if($featuredPost->getThumbnailUrl())
                            <img src="{{ $featuredPost->getThumbnailUrl() }}"
                                alt="{{ $featuredPost->title }}"
                                fetchpriority="high"
                                class="absolute inset-0 w-full h-full object-cover object-center group-hover:scale-105 transition-transform duration-500">
                        @else
                            <div class="absolute inset-0 w-full h-full flex items-center justify-center bg-slate-800 text-slate-400">
                                <svg class="w-16 h-16 opacity-30" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                </svg>
                            </div>
                        @endif

                        <!-- Badge Foto & Headline -->
                        <div class="absolute top-3.5 left-3.5 z-10 flex flex-wrap items-center gap-2">
                            <span class="px-2.5 py-1 rounded-md text-[10px] font-bold uppercase tracking-wider bg-rose-600 text-white shadow-xs">
                                ★ Berita Utama
                            </span>
                            @if($featPhotosCount > 0)
                                <span class="px-2.5 py-1 rounded-md text-[10px] font-semibold bg-slate-900/85 text-white backdrop-blur-xs flex items-center gap-1.5 shadow-xs">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                    </svg>
                                    {{ $featPhotosCount }} Foto
                                </span>
                            @endif
                            @if($featDocsCount > 0)
                                <span class="px-2.5 py-1 rounded-md text-[10px] font-semibold bg-emerald-600/90 text-white backdrop-blur-xs flex items-center gap-1 shadow-xs">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                    </svg>
                                    {{ $featDocsCount }} Dokumen Sah
                                </span>
                            @endif
                        </div>
                    </div>

                    <!-- Kolom Kanan: Uraian Padat, Metadata Kegiatan & Preview Galeri -->
                    <div class="lg:col-span-5 flex flex-col justify-between gap-4 min-w-0 py-0.5">
                        <div class="flex flex-col gap-3.5 min-w-0">
                            <header class="space-y-2.5 min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="px-2.5 py-0.5 rounded-md text-xs font-semibold bg-blue-50 dark:bg-blue-950/80 text-blue-700 dark:text-blue-300 border border-blue-200/50 dark:border-blue-800/50">
                                        {{ $featuredPost->category?->name ?? 'Warta' }}
                                    </span>
                                    @if($featuredPost->period)
                                        <span class="px-2 py-0.5 rounded-md text-xs font-semibold bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 border border-slate-200/70 dark:border-slate-700">
                                            {{ $featuredPost->period->getShortLabel() }}
                                        </span>
                                    @endif
                                    <span class="text-xs text-slate-500 dark:text-slate-400">
                                        {{ $featuredPost->published_at ? $featuredPost->published_at->translatedFormat('d F Y') : '-' }}
                                    </span>
                                </div>

                                <h2 class="text-xl sm:text-2xl font-bold text-slate-900 dark:text-white group-hover:text-blue-600 dark:group-hover:text-blue-400 transition-colors leading-snug">
                                    <a href="{{ route('public.posts.show', $featuredPost->slug) }}" class="card-link focus:outline-none">
                                        {{ $featuredPost->title }}
                                    </a>
                                </h2>
                            </header>

                            <p class="headline-excerpt text-sm text-slate-600 dark:text-slate-300 leading-relaxed">
                                {{ $leadSummary }}
                            </p>

                            <!-- Grid Ringkasan Metadata Acara (2x2 Padat & Rapi) -->
                            <dl class="grid grid-cols-2 gap-2.5 pt-1">
                                <div class="rounded-xl bg-slate-50 dark:bg-slate-800/60 border border-slate-200/70 dark:border-slate-800 px-3 py-2 min-w-0">
                                    <dt class="text-[10px] font-semibold uppercase tracking-wider text-slate-400 dark:text-slate-500 flex items-center gap-1">
                                        <svg class="w-3 h-3 text-blue-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                        </svg>
                                        <span>Tanggal Acara</span>
                                    </dt>
                                    <dd class="mt-0.5 text-xs font-semibold text-slate-800 dark:text-slate-200 truncate">
                                        {{ $eventDateLabel }}
                                    </dd>
                                </div>

                                <div class="rounded-xl bg-slate-50 dark:bg-slate-800/60 border border-slate-200/70 dark:border-slate-800 px-3 py-2 min-w-0">
                                    <dt class="text-[10px] font-semibold uppercase tracking-wider text-slate-400 dark:text-slate-500 flex items-center gap-1">
                                        <svg class="w-3 h-3 text-rose-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                                        </svg>
                                        <span>Lokasi Kegiatan</span>
                                    </dt>
                                    <dd class="mt-0.5 text-xs font-semibold text-slate-800 dark:text-slate-200 truncate" title="{{ $featuredPost->event_location ?: ($featuredPost->unit?->name ?? 'Kampus UNIVBI') }}">
                                        {{ $featuredPost->event_location ?: ($featuredPost->unit?->name ?? 'Kampus UNIVBI') }}
                                    </dd>
                                </div>

                                <div class="rounded-xl bg-slate-50 dark:bg-slate-800/60 border border-slate-200/70 dark:border-slate-800 px-3 py-2 min-w-0">
                                    <dt class="text-[10px] font-semibold uppercase tracking-wider text-slate-400 dark:text-slate-500 flex items-center gap-1">
                                        <svg class="w-3 h-3 text-emerald-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                        </svg>
                                        <span>Pewarta</span>
                                    </dt>
                                    <dd class="mt-0.5 text-xs font-semibold text-slate-800 dark:text-slate-200 truncate flex items-center gap-1.5">
                                        @php
                                            $featuredAuthorName = $featuredPost->author?->name ?? 'Tim Humas UNIVBI';
                                            $featuredAuthorAvatar = $featuredPost->author?->getAvatarUrl();
                                        @endphp
                                        @if($featuredAuthorAvatar)
                                            <img src="{{ $featuredAuthorAvatar }}" alt="{{ $featuredAuthorName }}" class="w-4 h-4 rounded-full object-cover shrink-0 border border-slate-200 dark:border-slate-700" loading="lazy">
                                        @endif
                                        <span class="truncate">{{ $featuredAuthorName }}</span>
                                    </dd>
                                </div>

                                <div class="rounded-xl bg-slate-50 dark:bg-slate-800/60 border border-slate-200/70 dark:border-slate-800 px-3 py-2 min-w-0">
                                    <dt class="text-[10px] font-semibold uppercase tracking-wider text-slate-400 dark:text-slate-500 flex items-center gap-1">
                                        <svg class="w-3 h-3 text-amber-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                        </svg>
                                        <span>Pembaca & Waktu</span>
                                    </dt>
                                    <dd class="mt-0.5 text-xs font-semibold text-slate-800 dark:text-slate-200 truncate">
                                        {{ number_format($featuredPost->views_count) }}× dilihat · {{ $readingMinutes }} mnt baca
                                    </dd>
                                </div>
                            </dl>

                            <!-- Cuplikan Mini Galeri Dokumentasi & Unduhan ZIP -->
                            @if($featuredPost->photos->isNotEmpty())
                                <div class="flex items-center justify-between gap-3 pt-1">
                                    <div class="flex items-center gap-2 min-w-0">
                                        @foreach($featuredPost->photos->take(4) as $thumbPhoto)
                                            <div class="w-12 h-9 rounded-lg overflow-hidden bg-slate-100 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shrink-0">
                                                <img src="{{ $thumbPhoto->getThumbnailUrl() }}"
                                                    alt="{{ $thumbPhoto->caption ?: $featuredPost->title }}"
                                                    loading="lazy"
                                                    class="w-full h-full object-cover">
                                            </div>
                                        @endforeach
                                        @if($featPhotosCount > 4)
                                            <span class="px-2 py-1 rounded-lg text-[11px] font-semibold bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 border border-slate-200 dark:border-slate-700 shrink-0">
                                                +{{ $featPhotosCount - 4 }} lainnya
                                            </span>
                                        @endif
                                    </div>

                                    @if($featuredPost->allow_public_zip_download && $featPhotosCount > 0)
                                        <a href="{{ $featuredPost->getZipDownloadUrl() }}"
                                            class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-lg text-[11px] font-semibold bg-emerald-50 dark:bg-emerald-950/60 hover:bg-emerald-100 dark:hover:bg-emerald-900/60 text-emerald-700 dark:text-emerald-300 border border-emerald-200/80 dark:border-emerald-800/70 transition-colors shrink-0">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                                            </svg>
                                            <span>Unduh Foto</span>
                                        </a>
                                    @endif
                                </div>
                            @endif
                        </div>

                        <footer class="pt-4 border-t border-slate-100 dark:border-slate-800 flex flex-wrap items-center justify-between gap-3 text-xs">
                            <div class="text-slate-500 dark:text-slate-400 min-w-0 truncate">
                                Unit: <span class="font-semibold text-slate-700 dark:text-slate-300">{{ $featuredPost->unit?->name ?? 'UNIVBI' }}</span>
                            </div>
                            <a href="{{ route('public.posts.show', $featuredPost->slug) }}" class="inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-lg font-semibold bg-blue-600 hover:bg-blue-700 text-white shadow-xs transition-all shrink-0">
                                <span>Baca Selengkapnya</span>
                                <span class="sr-only"> tentang {{ $featuredPost->title }}</span>
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3" />
                                </svg>
                            </a>
                        </footer>
                    </div>
                </div>
            </article>
        @endif

        <!-- Header Seksi Grid Berita Terkini -->
        @if($posts->count() > 0)
            <div class="flex items-center justify-between gap-4 mb-6">
                <div>
                    <h2 class="text-lg sm:text-xl font-bold text-slate-900 dark:text-white tracking-tight">
                        Warta & Liputan Terkini
                    </h2>
                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                        Kumpulan liputan resmi dan dokumentasi kegiatan Universitas Bina Insan
                    </p>
                </div>
                <span class="text-xs font-semibold px-2.5 py-1 rounded-md bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 shrink-0 whitespace-nowrap">
                    {{ $posts->total() }} Artikel
                </span>
            </div>

            <!-- Grid Berita & Kegiatan (Dengan Gap Antara Foto dan Card) -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                @foreach($posts as $post)
                    <article class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 p-4 shadow-xs hover:shadow-md hover:border-slate-300 dark:hover:border-slate-700 transition-all flex flex-col justify-between group">
                        <!-- Gambar Sampul dengan Gap dan Sudut Membulat Mandiri -->
                        <a href="{{ route('public.posts.show', $post->slug) }}" class="block relative aspect-video bg-slate-950 rounded-xl overflow-hidden shrink-0 mb-3.5">
                            @if($post->getThumbnailUrl())
                                <img src="{{ $post->getThumbnailUrl() }}" 
                                    alt="{{ $post->title }}" 
                                    loading="lazy"
                                    class="absolute inset-0 w-full h-full object-cover object-center group-hover:scale-105 transition-transform duration-300">
                            @else
                                <div class="absolute inset-0 w-full h-full flex items-center justify-center bg-slate-800 text-slate-400">
                                    <svg class="w-12 h-12 opacity-30" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                    </svg>
                                </div>
                            @endif

                            <!-- Badges Foto Sampul (Sedikit Mengotak) -->
                            <div class="absolute top-2.5 left-2.5 z-10 flex flex-wrap items-center gap-1.5">
                                @if($post->is_pinned)
                                    <span class="px-2 py-0.5 rounded-md text-[10px] font-bold bg-amber-500 text-white shadow-xs">
                                        PINNED
                                    </span>
                                @endif
                                <span class="px-2 py-0.5 rounded-md text-[10px] font-bold bg-navy-900/85 dark:bg-slate-900/90 text-white backdrop-blur-xs">
                                    {{ $post->category?->name ?? 'Berita' }}
                                </span>
                                @if($post->period)
                                    <span class="px-2 py-0.5 rounded-md text-[10px] font-bold bg-blue-600/90 text-white backdrop-blur-xs">
                                        {{ $post->period->getShortLabel() }}
                                    </span>
                                @endif
                            </div>

                            @php $cardPhotosCount = $post->photos_count ?? $post->photos()->count(); @endphp
                            @if($cardPhotosCount > 0)
                                <div class="absolute bottom-2.5 right-2.5 px-2 py-0.5 rounded-md text-[10px] font-semibold bg-slate-900/85 text-white backdrop-blur-xs flex items-center gap-1 shadow-xs">
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                    </svg>
                                    {{ $cardPhotosCount }}
                                </div>
                            @endif
                        </a>

                        <!-- Deskripsi Konten -->
                        <div class="flex-1 flex flex-col justify-between px-1">
                            <div>
                                <div class="flex items-center gap-2 text-xs text-slate-500 dark:text-slate-400 mb-2">
                                    <span>{{ $post->published_at ? $post->published_at->translatedFormat('d M Y') : '-' }}</span>
                                    @if($post->event_location)
                                        <span>•</span>
                                        <span class="truncate">{{ $post->event_location }}</span>
                                    @endif
                                </div>

                                <a href="{{ route('public.posts.show', $post->slug) }}" class="block">
                                    <h3 class="text-base font-bold text-slate-900 dark:text-white group-hover:text-blue-600 dark:group-hover:text-blue-400 transition-colors line-clamp-2 leading-snug">
                                        {{ $post->title }}
                                    </h3>
                                </a>

                                <p class="mt-2 text-xs text-slate-600 dark:text-slate-400 line-clamp-2 leading-relaxed">
                                    {{ $post->excerpt ?: Str::limit(strip_tags($post->content), 120) }}
                                </p>
                            </div>

                            <div class="mt-4 pt-3.5 border-t border-slate-100 dark:border-slate-800 flex items-center justify-between text-xs text-slate-500 dark:text-slate-400">
                                <span class="font-medium truncate max-w-[150px]">{{ $post->unit?->name ?? 'UNIVBI' }}</span>
                                <span class="flex items-center gap-1 font-semibold text-slate-600 dark:text-slate-400">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                    </svg>
                                    {{ number_format($post->views_count) }}
                                </span>
                            </div>
                        </div>
                    </article>
                @endforeach
            </div>

            <!-- Pagination -->
            <div class="mt-10">
                {{ $posts->links() }}
            </div>
        @else
            <!-- State Kosong -->
            <div class="py-16 text-center bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-800 p-8 shadow-xs">
                <div class="w-14 h-14 mx-auto mb-3.5 rounded-xl bg-slate-100 dark:bg-slate-800 flex items-center justify-center text-slate-400">
                    <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10a2 2 0 012 2v1m2 13a2 2 0 01-2-2V7m2 13a2 2 0 002-2V9a2 2 0 00-2-2h-2m-4-3H9M7 16h6M7 8h6v4H7V8z" />
                    </svg>
                </div>
                <h3 class="text-base font-bold text-slate-900 dark:text-white">Tidak Ditemukan Berita atau Kegiatan</h3>
                <p class="text-xs sm:text-sm text-slate-500 dark:text-slate-400 mt-1 max-w-sm mx-auto">
                    Belum ada publikasi yang cocok dengan kata kunci atau kategori yang Anda pilih saat ini.
                </p>
                @if(!empty($searchQuery) || !empty($selectedCategorySlug) || $selectedPeriod !== null)
                    <a href="{{ route('public.posts.index') }}" class="mt-5 inline-flex items-center px-4 py-2 rounded-lg text-xs font-semibold bg-blue-600 text-white hover:bg-blue-700 transition-colors shadow-xs">
                        Tampilkan Semua Berita
                    </a>
                @endif
            </div>
        @endif
    </main>

    <!-- Footer Institusi & Developer Resmi -->
    @include('public.partials.footer')

    <script>
        function toggleDarkMode() {
            if (document.documentElement.classList.contains('dark')) {
                document.documentElement.classList.remove('dark');
                localStorage.theme = 'light';
            } else {
                document.documentElement.classList.add('dark');
                localStorage.theme = 'dark';
            }
        }

        document.addEventListener('DOMContentLoaded', function() {
            const container = document.getElementById('mobile-custom-filters');
            if (!container) return;

            const dropdowns = container.querySelectorAll('[data-mobile-dropdown]');

            const closeAll = function(exceptKey = null) {
                dropdowns.forEach(function(wrapper) {
                    const key = wrapper.getAttribute('data-mobile-dropdown');
                    if (exceptKey && key === exceptKey) return;

                    const trigger = wrapper.querySelector('[data-dropdown-trigger]');
                    const menu = wrapper.querySelector('[data-dropdown-menu]');
                    const chevron = wrapper.querySelector('[data-dropdown-chevron]');

                    if (menu) menu.classList.add('hidden');
                    if (trigger) trigger.setAttribute('aria-expanded', 'false');
                    if (chevron) chevron.classList.remove('rotate-180');
                });
            };

            dropdowns.forEach(function(wrapper) {
                const key = wrapper.getAttribute('data-mobile-dropdown');
                const trigger = wrapper.querySelector('[data-dropdown-trigger]');
                const menu = wrapper.querySelector('[data-dropdown-menu]');
                const chevron = wrapper.querySelector('[data-dropdown-chevron]');

                if (!trigger || !menu) return;

                trigger.addEventListener('click', function(event) {
                    event.stopPropagation();
                    const isOpening = menu.classList.contains('hidden');
                    closeAll(isOpening ? key : null);

                    menu.classList.toggle('hidden', !isOpening);
                    trigger.setAttribute('aria-expanded', isOpening ? 'true' : 'false');
                    if (chevron) chevron.classList.toggle('rotate-180', isOpening);
                });
            });

            document.addEventListener('click', function(event) {
                if (!container.contains(event.target)) {
                    closeAll();
                }
            });

            document.addEventListener('keydown', function(event) {
                if (event.key === 'Escape') {
                    closeAll();
                }
            });
        });
    </script>
</body>
</html>
