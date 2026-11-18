<!DOCTYPE html>
<html lang="id" class="h-full">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $settings->system_name }} | {{ $settings->university_name }}</title>
    <meta name="description" content="{{ $settings->system_tagline }}">

    @if ($settings->favicon_path)
        <link rel="icon" href="{{ Storage::disk('public')->url($settings->favicon_path) }}">
    @endif

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700,800&display=swap" rel="stylesheet" />

    <!-- Anti-flicker Dark Mode Script -->
    <script>
        if (localStorage.theme === 'dark' || (!('theme' in localStorage) && window.matchMedia(
                '(prefers-color-scheme: dark)').matches)) {
            document.documentElement.classList.add('dark');
        } else {
            document.documentElement.classList.remove('dark');
        }
    </script>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        .no-scrollbar::-webkit-scrollbar {
            display: none;
        }

        .no-scrollbar {
            -ms-overflow-style: none;
            scrollbar-width: none;
        }

        html {
            scroll-behavior: smooth;
        }

        /* Hilangkan outline putih bawaan browser saat input fokus */
        input:focus,
        input:focus-visible,
        button:focus,
        select:focus {
            outline: none !important;
        }
    </style>
</head>

<body
    class="min-h-full flex flex-col font-sans antialiased text-slate-800 dark:text-slate-100 bg-slate-50 dark:bg-slate-950 selection:bg-blue-100 selection:text-blue-900 transition-colors duration-200">

    <!-- 1. Header Institusi Resmi (Seragam & Terstruktur) -->
    @include('public.partials.header')

    <!-- 2. Hero Section & Banner Beranda (CMS-Driven) -->
    <section
        class="relative bg-gradient-to-b from-blue-50/70 via-white to-slate-100/90 dark:from-navy-950 dark:via-slate-950 dark:to-navy-900 text-slate-900 dark:text-white border-b border-slate-200/80 dark:border-slate-800/80 overflow-hidden py-10 sm:py-14 lg:py-16 transition-colors">
        @if ($bannerUrl = $settings->getBannerUrl())
            <div class="absolute inset-0 z-0 overflow-hidden">
                <img src="{{ $bannerUrl }}" alt="Banner Kampus"
                    class="w-full h-full object-cover object-right md:object-right-center opacity-95 dark:opacity-85 transition-opacity">
                <!-- Gradien pelindung keterbacaan teks di sisi kiri, memudar transparan di sisi kanan agar foto kampus tampil jernih & tajam -->
                <div
                    class="absolute inset-0 bg-gradient-to-r from-white via-white/85 via-35% to-transparent dark:from-navy-950 dark:via-navy-950/85 dark:via-35% dark:to-transparent transition-colors pointer-events-none">
                </div>
                <!-- Vignette bawah tipis untuk menyatu dengan seksi berikutnya -->
                <div
                    class="absolute inset-x-0 bottom-0 h-16 bg-gradient-to-t from-slate-100 dark:from-navy-950 to-transparent pointer-events-none">
                </div>
            </div>
        @else
            <!-- Latar Belakang Gradien Abstrak Modern -->
            <div
                class="absolute inset-0 z-0 bg-gradient-to-br from-blue-50/80 via-white to-slate-100 dark:from-navy-950 dark:via-slate-900 dark:to-blue-950 transition-colors">
                <div
                    class="absolute -top-24 -left-24 w-96 h-96 bg-blue-400/10 dark:bg-blue-500/10 rounded-full blur-3xl">
                </div>
                <div
                    class="absolute -bottom-24 -right-24 w-96 h-96 bg-indigo-400/10 dark:bg-indigo-500/10 rounded-full blur-3xl">
                </div>
            </div>
        @endif

        <div class="relative z-10 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="max-w-2xl">
                <!-- Badge Lencana Hero -->
                <div
                    class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md bg-blue-50 dark:bg-blue-600/20 border border-blue-200 dark:border-blue-400/30 text-blue-700 dark:text-blue-300 text-xs font-semibold mb-3.5 shadow-2xs transition-colors">
                    <span class="w-2 h-2 rounded-full bg-blue-600 dark:bg-blue-400 animate-pulse"></span>
                    {{ $settings->hero_badge }}
                </div>

                <!-- Headline Utama (Ukuran Lebih Proporsional & Rapi) -->
                <h1
                    class="text-2xl sm:text-3xl lg:text-4xl font-extrabold tracking-tight text-slate-900 dark:text-white leading-tight mb-3.5 transition-colors">
                    {{ $settings->hero_title }}
                </h1>

                <!-- Subtitle / Tagline -->
                <p
                    class="text-xs sm:text-sm text-slate-600 dark:text-slate-300 leading-relaxed mb-6 max-w-xl font-normal transition-colors">
                    {{ $settings->hero_subtitle }}
                </p>

                <!-- Tombol CTA -->
                <div class="flex flex-wrap items-center gap-3">
                    <a href="{{ $settings->hero_cta_primary_url ?: '#dokumen-publik' }}"
                        class="inline-flex items-center justify-center gap-2 px-5 py-2.5 rounded-lg font-bold text-xs sm:text-sm bg-blue-600 hover:bg-blue-700 dark:hover:bg-blue-500 text-white shadow-md shadow-blue-600/20 transition-all hover:scale-[1.02]">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                        </svg>
                        {{ $settings->hero_cta_primary_label ?: 'Jelajahi Dokumen Publik' }}
                    </a>

                    <a href="{{ $settings->hero_cta_secondary_url && $settings->hero_cta_secondary_url !== '/verify' ? $settings->hero_cta_secondary_url : route('public.posts.index') }}"
                        class="inline-flex items-center justify-center gap-2 px-5 py-2.5 rounded-lg font-bold text-xs sm:text-sm bg-white dark:bg-white/10 hover:bg-slate-50 dark:hover:bg-white/15 text-slate-800 dark:text-white border border-slate-300 dark:border-white/20 shadow-2xs dark:shadow-none backdrop-blur-md transition-all hover:scale-[1.02]">
                        <svg class="w-4 h-4 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor"
                            viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10a2 2 0 012 2v1m2 13a2 2 0 01-2-2V7m2 13a2 2 0 002-2V9a2 2 0 00-2-2h-2m-4-3H9M7 16h6M7 8h6v4H7V8z" />
                        </svg>
                        {{ $settings->hero_cta_secondary_label && $settings->hero_cta_secondary_label !== 'Verifikasi Naskah QR' ? $settings->hero_cta_secondary_label : 'Warta & Kegiatan Kampus' }}
                    </a>
                </div>
            </div>

            <!-- Bar Ringkasan Metrik Cepat (Ukuran Card Kompak & Padat dengan Efek Hover Zoom & Glow) -->
            <div
                class="mt-8 sm:mt-10 grid grid-cols-2 sm:grid-cols-4 gap-3 border-t border-slate-200/80 dark:border-slate-800/80 pt-6 transition-colors">
                <!-- Card 1: Dokumen Sah Terbuka -->
                <div
                    class="p-3 sm:p-3.5 rounded-lg bg-white/80 dark:bg-slate-900/60 border border-slate-200/90 dark:border-slate-800/80 backdrop-blur-xs shadow-2xs dark:shadow-none transition-all duration-300 hover:scale-[1.03] hover:-translate-y-0.5 hover:bg-white dark:hover:bg-slate-900/90 hover:border-blue-400/80 dark:hover:border-blue-500/60 hover:shadow-lg hover:shadow-blue-500/15 dark:hover:shadow-blue-500/20 cursor-default group">
                    <span
                        class="block text-xl sm:text-2xl font-extrabold text-slate-900 dark:text-white group-hover:text-blue-600 dark:group-hover:text-blue-400 transition-colors">
                        {{ number_format($stats['total_public_docs']) }}+
                    </span>
                    <span class="block text-[11px] sm:text-xs font-medium text-slate-500 dark:text-slate-400 mt-0.5">
                        Dokumen Sah Terbuka
                    </span>
                </div>

                <!-- Card 2: Warta & Acara Kampus -->
                <div
                    class="p-3 sm:p-3.5 rounded-lg bg-white/80 dark:bg-slate-900/60 border border-slate-200/90 dark:border-slate-800/80 backdrop-blur-xs shadow-2xs dark:shadow-none transition-all duration-300 hover:scale-[1.03] hover:-translate-y-0.5 hover:bg-white dark:hover:bg-slate-900/90 hover:border-blue-400/80 dark:hover:border-blue-500/60 hover:shadow-lg hover:shadow-blue-500/15 dark:hover:shadow-blue-500/20 cursor-default group">
                    <span
                        class="block text-xl sm:text-2xl font-extrabold text-slate-900 dark:text-white group-hover:text-blue-600 dark:group-hover:text-blue-400 transition-colors">
                        {{ number_format($stats['total_published_posts']) }}
                    </span>
                    <span class="block text-[11px] sm:text-xs font-medium text-slate-500 dark:text-slate-400 mt-0.5">
                        Warta & Acara Kampus
                    </span>
                </div>

                <!-- Card 3: Unit Kerja & Fakultas -->
                <div
                    class="p-3 sm:p-3.5 rounded-lg bg-white/80 dark:bg-slate-900/60 border border-slate-200/90 dark:border-slate-800/80 backdrop-blur-xs shadow-2xs dark:shadow-none transition-all duration-300 hover:scale-[1.03] hover:-translate-y-0.5 hover:bg-white dark:hover:bg-slate-900/90 hover:border-blue-400/80 dark:hover:border-blue-500/60 hover:shadow-lg hover:shadow-blue-500/15 dark:hover:shadow-blue-500/20 cursor-default group">
                    <span
                        class="block text-xl sm:text-2xl font-extrabold text-slate-900 dark:text-white group-hover:text-blue-600 dark:group-hover:text-blue-400 transition-colors">
                        {{ number_format($stats['total_units']) }}
                    </span>
                    <span class="block text-[11px] sm:text-xs font-medium text-slate-500 dark:text-slate-400 mt-0.5">
                        Unit Kerja & Fakultas
                    </span>
                </div>

                <!-- Card 4: Keabsahan QR Terdaftar -->
                <div
                    class="p-3 sm:p-3.5 rounded-lg bg-white/80 dark:bg-slate-900/60 border border-slate-200/90 dark:border-slate-800/80 backdrop-blur-xs shadow-2xs dark:shadow-none transition-all duration-300 hover:scale-[1.03] hover:-translate-y-0.5 hover:bg-white dark:hover:bg-slate-900/90 hover:border-emerald-400/80 dark:hover:border-emerald-500/60 hover:shadow-lg hover:shadow-emerald-500/15 dark:hover:shadow-emerald-500/20 cursor-default group">
                    <span
                        class="block text-xl sm:text-2xl font-extrabold text-emerald-600 dark:text-emerald-400 group-hover:scale-105 transition-transform">
                        100%
                    </span>
                    <span class="block text-[11px] sm:text-xs font-medium text-slate-500 dark:text-slate-400 mt-0.5">
                        Keabsahan QR Terdaftar
                    </span>
                </div>
            </div>
        </div>
    </section>

    <!-- 3. Seksi Tabel Dokumen Publik Resmi + Fitur Pencarian -->
    <section id="dokumen-publik" class="py-16 sm:py-20 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 w-full">
        <!-- Header Seksi Dokumen -->
        <div class="mb-8">
            <div>
                <div
                    class="inline-flex items-center gap-1.5 px-3 py-1 rounded-lg text-xs font-bold uppercase tracking-wider bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-300 mb-3">
                    {{ $settings->docs_section_badge ?: 'Transparansi Informasi Publik' }}
                </div>
                <h2 class="text-2xl sm:text-3xl font-black text-slate-900 dark:text-white tracking-tight">
                    {{ $settings->docs_section_title ?: 'Daftar Naskah Dinas & Dokumen Publik' }}
                </h2>
                <p class="text-sm text-slate-500 dark:text-slate-400 mt-2 max-w-3xl">
                    {{ $settings->docs_section_description ?: 'Telusuri surat keputusan, surat edaran, panduan akademik, dan berkas naskah dinas resmi institusi yang sah dan terbuka untuk umum.' }}
                </p>
            </div>
        </div>

        <!-- Form Pencarian & Filter Kategori Terpadu -->
        <div
            class="bg-white dark:bg-slate-900 p-4 rounded-xl border border-slate-300 dark:border-slate-700 shadow-xs mb-6 transition-colors">
            <form action="{{ route('landing.home') }}#dokumen-publik" method="GET"
                class="grid grid-cols-1 sm:grid-cols-12 gap-3">
                <!-- Input Pencarian Kata Kunci -->
                <div class="sm:col-span-6 relative">
                    <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                        </svg>
                    </div>
                    <input type="text" name="q" value="{{ $searchQuery }}"
                        placeholder="Cari judul naskah, nomor surat resmi, atau unit penerbit..."
                        class="w-full pl-10 pr-4 py-2 rounded-lg border border-slate-300 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-sm text-slate-900 dark:text-slate-100 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all">
                </div>

                <!-- Dropdown Filter Kategori Dokumen (Custom Styled) -->
                <div class="sm:col-span-4 relative" id="category-dropdown-container">
                    <input type="hidden" name="category" id="selected-category-input" value="{{ $selectedCategory }}">

                    <!-- Trigger Button -->
                    <button type="button" id="category-dropdown-trigger" onclick="toggleCategoryDropdown()"
                        class="w-full flex items-center justify-between px-3.5 py-2 rounded-lg border border-slate-300 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-sm text-slate-900 dark:text-slate-100 hover:border-slate-400 dark:hover:border-slate-600 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all shadow-2xs">
                        <div class="flex items-center gap-2 truncate pr-2">
                            <svg class="w-4 h-4 text-blue-600 dark:text-blue-400 shrink-0" fill="none"
                                stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z" />
                            </svg>
                            <span id="category-selected-label" class="truncate font-medium text-xs sm:text-sm">
                                {{ $categories->firstWhere('id', $selectedCategory)?->name ?? 'Semua Kategori Dokumen' }}
                            </span>
                        </div>
                        <svg id="category-dropdown-chevron"
                            class="w-4 h-4 text-slate-400 shrink-0 transition-transform duration-200" fill="none"
                            stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M19 9l-7 7-7-7" />
                        </svg>
                    </button>

                    <!-- Dropdown Options Menu -->
                    <div id="category-dropdown-menu"
                        class="hidden absolute z-30 left-0 right-0 mt-2 max-h-72 overflow-y-auto no-scrollbar rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 p-1.5 shadow-xl transition-all">
                        <!-- Opsi: Semua Kategori -->
                        <div onclick="selectCategory('', 'Semua Kategori Dokumen')"
                            class="flex items-center justify-between px-3 py-2 rounded-lg text-xs font-semibold cursor-pointer transition-colors {{ empty($selectedCategory) ? 'bg-blue-50 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300' : 'text-slate-700 dark:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-700/60' }}">
                            <div class="flex items-center gap-2">
                                <span
                                    class="w-2 h-2 rounded-full {{ empty($selectedCategory) ? 'bg-blue-600 dark:bg-blue-400' : 'bg-slate-300 dark:bg-slate-600' }}"></span>
                                <span>Semua Kategori Dokumen</span>
                            </div>
                            @if (empty($selectedCategory))
                                <svg class="w-4 h-4 text-blue-600 dark:text-blue-400" fill="none"
                                    stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M5 13l4 4L19 7" />
                                </svg>
                            @endif
                        </div>

                        <div class="my-1 border-t border-slate-100 dark:border-slate-700/60"></div>

                        <!-- Opsi Kategori Dinamis -->
                        @foreach ($categories as $cat)
                            @php $isSelected = ((string)$selectedCategory === (string)$cat->id); @endphp
                            <div onclick="selectCategory('{{ $cat->id }}', '{{ addslashes($cat->name) }}')"
                                class="flex items-center justify-between px-3 py-2 rounded-lg text-xs font-medium cursor-pointer transition-colors {{ $isSelected ? 'bg-blue-50 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300 font-bold' : 'text-slate-700 dark:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-700/60' }}">
                                <div class="flex items-center gap-2 truncate pr-2">
                                    <span
                                        class="px-1.5 py-0.5 rounded text-[10px] font-mono font-bold bg-slate-200/80 dark:bg-slate-700 text-slate-700 dark:text-slate-300 shrink-0">
                                        {{ $cat->code }}
                                    </span>
                                    <span class="truncate">{{ $cat->name }}</span>
                                </div>
                                @if ($isSelected)
                                    <svg class="w-4 h-4 text-blue-600 dark:text-blue-400 shrink-0" fill="none"
                                        stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M5 13l4 4L19 7" />
                                    </svg>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>

                <!-- Tombol Submit Cari & Reset -->
                <div class="sm:col-span-2 flex items-center gap-2">
                    <button type="submit"
                        class="w-full py-2 px-4 rounded-lg text-sm font-bold bg-blue-600 hover:bg-blue-700 text-white shadow-2xs transition-colors flex items-center justify-center gap-1.5">
                        <span>Cari</span>
                    </button>
                    @if (!empty($searchQuery) || !empty($selectedCategory))
                        <a href="{{ route('landing.home') }}#dokumen-publik"
                            class="p-2 rounded-lg text-slate-500 hover:text-slate-800 dark:text-slate-400 dark:hover:text-slate-200 bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 border border-slate-300 dark:border-slate-700 transition-colors"
                            title="Reset Pencarian">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </a>
                    @endif
                </div>
            </form>

            @if (!empty($searchQuery) || !empty($selectedCategory))
                <div
                    class="mt-3 pt-3 border-t border-slate-200 dark:border-slate-800 flex items-center justify-between text-xs text-slate-600 dark:text-slate-400">
                    <div>
                        Menampilkan hasil untuk:
                        @if (!empty($searchQuery))
                            <span class="font-bold text-slate-900 dark:text-slate-100">"{{ $searchQuery }}"</span>
                        @endif
                        @if (!empty($selectedCategory))
                            <span class="ml-1">dalam kategori terpilih</span>
                        @endif
                    </div>
                    <span class="font-bold text-slate-800 dark:text-slate-200">{{ $documents->total() }} naskah
                        ditemukan</span>
                </div>
            @endif
        </div>

        <!-- Tabel Dokumen Publik Sah (Tampilan Solid, Kokoh & Berkontras Tegas) -->
        @if ($documents->count() > 0)
            <div
                class="bg-white dark:bg-slate-900 rounded-xl border border-slate-300 dark:border-slate-700 shadow-sm overflow-hidden transition-colors">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm border-collapse">
                        <thead
                            class="bg-slate-100 dark:bg-slate-800 text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-slate-200 border-b-2 border-slate-300 dark:border-slate-700">
                            <tr>
                                <th scope="col"
                                    class="py-3 px-3.5 w-12 text-center border-r border-slate-200 dark:border-slate-700">
                                    #</th>
                                <th scope="col" class="py-3 px-4 border-r border-slate-200 dark:border-slate-700">
                                    Nomor Naskah Resmi & Kategori</th>
                                <th scope="col" class="py-3 px-4 border-r border-slate-200 dark:border-slate-700">
                                    Judul Dokumen</th>
                                <th scope="col"
                                    class="py-3 px-4 hidden md:table-cell border-r border-slate-200 dark:border-slate-700">
                                    Unit Penerbit</th>
                                <th scope="col"
                                    class="py-3 px-4 hidden sm:table-cell border-r border-slate-200 dark:border-slate-700">
                                    Tanggal Sah</th>
                                <th scope="col" class="py-3 px-4 text-center w-36">Aksi Cepat</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200 dark:divide-slate-700 font-medium">
                            @foreach ($documents as $doc)
                                <tr
                                    class="odd:bg-white even:bg-slate-50/70 dark:odd:bg-slate-900 dark:even:bg-slate-800/40 hover:bg-blue-50/50 dark:hover:bg-slate-800/80 transition-colors">
                                    <!-- Nomor Urut -->
                                    <td
                                        class="py-3.5 px-3.5 text-center text-xs font-bold text-slate-400 dark:text-slate-500 border-r border-slate-200 dark:border-slate-800">
                                        {{ $documents->firstItem() + $loop->index }}
                                    </td>

                                    <!-- Nomor Naskah Resmi -->
                                    <td
                                        class="py-3.5 px-4 whitespace-nowrap border-r border-slate-200 dark:border-slate-800">
                                        <div
                                            class="font-mono text-xs font-bold text-slate-900 dark:text-slate-100 bg-slate-100 dark:bg-slate-800 border border-slate-300 dark:border-slate-600 px-2.5 py-1 rounded-md inline-block shadow-2xs">
                                            {{ $doc->document_number }}
                                        </div>
                                        <div class="flex items-center gap-1.5 mt-1">
                                            @if ($doc->category)
                                                <span
                                                    class="inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-bold bg-blue-50 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300 border border-blue-200 dark:border-blue-800/60">
                                                    {{ $doc->category->name }}
                                                </span>
                                            @endif
                                        </div>
                                    </td>

                                    <!-- Judul & Kategori -->
                                    <td class="py-3.5 px-4 border-r border-slate-200 dark:border-slate-800">
                                        <div
                                            class="font-bold text-slate-900 dark:text-white leading-snug line-clamp-2 text-sm">
                                            {{ $doc->title }}
                                        </div>
                                        <div class="flex flex-wrap items-center gap-2 mt-1.5">

                                            <span class="md:hidden text-[11px] text-slate-500 dark:text-slate-400">
                                                • {{ $doc->unit?->name }}
                                            </span>
                                        </div>
                                    </td>

                                    <!-- Unit Penerbit (Desktop) -->
                                    <td
                                        class="py-3.5 px-4 hidden md:table-cell whitespace-nowrap text-xs font-semibold text-slate-700 dark:text-slate-300 border-r border-slate-200 dark:border-slate-800">
                                        {{ $doc->unit?->name ?? '-' }}
                                    </td>

                                    <!-- Tanggal Pengesahan -->
                                    <td
                                        class="py-3.5 px-4 hidden sm:table-cell whitespace-nowrap text-xs font-medium text-slate-600 dark:text-slate-400 border-r border-slate-200 dark:border-slate-800">
                                        {{ $doc->approved_at?->translatedFormat('d M Y') ?? ($doc->issued_date?->translatedFormat('d M Y') ?? '-') }}
                                    </td>

                                    <!-- Aksi Cepat (Tombol Berbingkai Solid) -->
                                    <td class="py-3.5 px-4 text-center whitespace-nowrap">
                                        <div class="inline-flex items-center gap-1.5">
                                            <!-- Pratinjau Streaming PDF -->
                                            <a href="{{ route('document.verify.stream', $doc->uuid) }}"
                                                target="_blank"
                                                class="inline-flex items-center justify-center w-8 h-8 rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-300 hover:text-blue-600 hover:border-blue-400 dark:hover:text-blue-400 dark:hover:border-blue-500 shadow-2xs hover:shadow-xs transition-all"
                                                title="Pratinjau PDF Resmi">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor"
                                                    viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                        stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                        stroke-width="2"
                                                        d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                                </svg>
                                            </a>

                                            <!-- Unduh Dokumen -->
                                            <a href="{{ route('document.verify.download', $doc->uuid) }}"
                                                class="inline-flex items-center justify-center w-8 h-8 rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-300 hover:text-emerald-600 hover:border-emerald-400 dark:hover:text-emerald-400 dark:hover:border-emerald-500 shadow-2xs hover:shadow-xs transition-all"
                                                title="Unduh Berkas PDF Asli">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor"
                                                    viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                        stroke-width="2"
                                                        d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                                                </svg>
                                            </a>

                                            <!-- Validasi Portal QR -->
                                            <a href="{{ route('document.verify.show', $doc->uuid) }}" target="_blank"
                                                class="inline-flex items-center justify-center w-8 h-8 rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-300 hover:text-purple-600 hover:border-purple-400 dark:hover:text-purple-400 dark:hover:border-purple-500 shadow-2xs hover:shadow-xs transition-all"
                                                title="Cek Verifikasi Keabsahan QR">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor"
                                                    viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                        stroke-width="2"
                                                        d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                                                </svg>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <!-- Paginasi Dokumen -->
                <div class="p-3.5 bg-slate-50/50 dark:bg-slate-900/50 border-t border-slate-200 dark:border-slate-700">
                    {{ $documents->fragment('dokumen-publik')->links() }}
                </div>
            </div>
        @else
            <!-- State Kosong Dokumen -->
            <div
                class="py-14 text-center bg-white dark:bg-slate-900 rounded-xl border border-slate-300 dark:border-slate-700 p-8 shadow-xs">
                <div
                    class="w-12 h-12 mx-auto mb-3 rounded-lg bg-slate-100 dark:bg-slate-800 flex items-center justify-center text-slate-400 border border-slate-200 dark:border-slate-700">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                            d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                </div>
                <h3 class="text-base font-bold text-slate-900 dark:text-white">Tidak Ditemukan Dokumen Publik</h3>
                <p class="text-xs sm:text-sm text-slate-500 dark:text-slate-400 mt-1 max-w-sm mx-auto">
                    Belum ada naskah dinas bertaraf akses publik yang cocok dengan kata kunci pencarian Anda saat ini.
                </p>
                @if (!empty($searchQuery) || !empty($selectedCategory))
                    <a href="{{ route('landing.home') }}#dokumen-publik"
                        class="mt-4 inline-flex items-center px-4 py-2 rounded-lg text-xs font-semibold bg-blue-600 text-white hover:bg-blue-700 transition-colors shadow-2xs">
                        Tampilkan Semua Dokumen
                    </a>
                @endif
            </div>
        @endif
    </section>

    <!-- 4. Seksi Warta & Dokumentasi Kegiatan Kampus Terbaru (Maksimal 5 Berita) -->
    <section
        class="py-16 sm:py-20 bg-slate-100/70 dark:bg-slate-900/40 border-t border-slate-200/80 dark:border-slate-800/80">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <!-- Header Seksi Berita -->
            <div class="flex flex-col md:flex-row md:items-end justify-between gap-6 mb-10">
                <div>
                    <div
                        class="inline-flex items-center gap-1.5 px-3 py-1 rounded-lg text-xs font-bold uppercase tracking-wider bg-navy-100 text-navy-800 dark:bg-blue-900/40 dark:text-blue-300 mb-3">
                        {{ $settings->news_section_badge ?: 'Kabar & Kegiatan' }}
                    </div>
                    <h2 class="text-2xl sm:text-3xl font-black text-slate-900 dark:text-white tracking-tight">
                        {{ $settings->news_section_title ?: 'Warta & Publikasi Kampus Terbaru' }}
                    </h2>
                    <p class="text-sm text-slate-500 dark:text-slate-400 mt-2 max-w-2xl">
                        {{ $settings->news_section_description ?: 'Liputan kegiatan akademik, kerja sama strategis, prestasi mahasiswa, dan pengumuman resmi Universitas Bina Insan.' }}
                    </p>
                </div>

                <!-- Tombol Navigasi Lihat Semua Berita (Atas) -->
                <div class="shrink-0">
                    <a href="{{ route('public.posts.index') }}"
                        class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl text-xs sm:text-sm font-bold text-white bg-blue-600 hover:bg-blue-700 shadow-sm transition-all hover:scale-[1.02]">
                        <span>{{ $settings->news_view_all_label ?: 'Lihat Semua Warta Kampus' }}</span>
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M17 8l4 4m0 0l-4 4m4-4H3" />
                        </svg>
                    </a>
                </div>
            </div>

            @if ($latestPosts->count() > 0)
                @php
                    $primaryPost = $latestPosts->first();
                    $sidePosts = $latestPosts->slice(1, 2);
                    $extraPosts = $latestPosts->slice(3);
                @endphp

                <!-- Layout Utama: 1 Card Utama di Kiri & 2 Card Kecil di Kanan -->
                <div class="grid grid-cols-1 lg:grid-cols-12 gap-5 items-stretch">
                    <!-- Kolom Kiri: 1 Card Berita Utama (Featured / Headline) -->
                    <div class="{{ $sidePosts->count() > 0 ? 'lg:col-span-7' : 'lg:col-span-12' }} flex">
                        <article
                            class="w-full bg-white dark:bg-slate-900 rounded-xl border border-slate-300 dark:border-slate-800 shadow-xs hover:shadow-md transition-all duration-200 flex flex-col group overflow-hidden">
                            <!-- Thumbnail Foto dengan Bantalan Inset Framing -->
                            <div class="p-3.5 pb-0">
                                <a href="{{ route('public.posts.show', $primaryPost->slug) }}"
                                    class="block relative aspect-[16/9] w-full rounded-lg overflow-hidden bg-slate-100 dark:bg-slate-800 border border-slate-200/80 dark:border-slate-700/80">
                                    @if ($thumb = $primaryPost->getThumbnailUrl())
                                        <img src="{{ $thumb }}" alt="{{ $primaryPost->title }}"
                                            class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300">
                                    @else
                                        <div
                                            class="w-full h-full flex flex-col items-center justify-center bg-gradient-to-br from-navy-900 to-slate-900 text-white p-4 text-center">
                                            <span
                                                class="text-xs font-bold uppercase tracking-wider text-blue-400">{{ $settings->university_acronym ?: 'UNIVBI' }}
                                                Warta</span>
                                            <span
                                                class="text-sm font-semibold mt-1 line-clamp-2">{{ $primaryPost->title }}</span>
                                        </div>
                                    @endif

                                    <!-- Badges di atas Thumbnail -->
                                    <div class="absolute top-2.5 left-2.5 flex flex-wrap gap-1">
                                        @if ($primaryPost->is_pinned)
                                            <span
                                                class="px-2 py-0.5 rounded text-[10px] font-extrabold uppercase tracking-wider bg-amber-500 text-white shadow-2xs">
                                                PINNED
                                            </span>
                                        @elseif($primaryPost->is_featured)
                                            <span
                                                class="px-2 py-0.5 rounded text-[10px] font-extrabold uppercase tracking-wider bg-rose-600 text-white shadow-2xs">
                                                ★ BERITA UTAMA
                                            </span>
                                        @endif
                                        @if ($primaryPost->category)
                                            <span
                                                class="px-2 py-0.5 rounded text-[10px] font-bold bg-navy-900/90 text-white backdrop-blur-xs shadow-2xs">
                                                {{ $primaryPost->category->name }}
                                            </span>
                                        @endif
                                        @if ($primaryPost->period)
                                            <span
                                                class="px-2 py-0.5 rounded text-[10px] font-bold bg-blue-600/90 text-white backdrop-blur-xs shadow-2xs">
                                                {{ $primaryPost->period->getShortLabel() }}
                                            </span>
                                        @endif
                                    </div>

                                    @if ($primaryPost->photos_count > 1)
                                        <div
                                            class="absolute bottom-2.5 right-2.5 px-2 py-0.5 rounded text-[10px] font-bold bg-black/75 text-white backdrop-blur-xs flex items-center gap-1 shadow-2xs">
                                            <svg class="w-3 h-3" fill="none" stroke="currentColor"
                                                viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                            </svg>
                                            {{ $primaryPost->photos_count }} Foto
                                        </div>
                                    @endif
                                </a>
                            </div>

                            <!-- Konten Teks Berita Utama -->
                            <div class="p-4 sm:p-5 flex-1 flex flex-col justify-between">
                                <div>
                                    <!-- Tanggal & Lokasi Acara -->
                                    <div
                                        class="flex items-center gap-2 text-xs text-slate-500 dark:text-slate-400 mb-2">
                                        <span>{{ $primaryPost->published_at?->translatedFormat('d M Y') ?? $primaryPost->created_at->translatedFormat('d M Y') }}</span>
                                        @if ($primaryPost->event_location)
                                            <span>•</span>
                                            <span
                                                class="truncate max-w-[200px]">{{ $primaryPost->event_location }}</span>
                                        @endif
                                    </div>

                                    <!-- Judul Berita -->
                                    <h3
                                        class="text-base sm:text-lg font-bold text-slate-900 dark:text-white group-hover:text-blue-600 dark:group-hover:text-blue-400 transition-colors leading-snug line-clamp-2">
                                        <a href="{{ route('public.posts.show', $primaryPost->slug) }}">
                                            {{ $primaryPost->title }}
                                        </a>
                                    </h3>

                                    <!-- Ringkasan / Excerpt -->
                                    <p
                                        class="text-xs sm:text-sm text-slate-600 dark:text-slate-400 mt-2 line-clamp-2 sm:line-clamp-3 leading-relaxed">
                                        {{ $primaryPost->excerpt ?: Str::limit(strip_tags($primaryPost->content ?? ''), 140) }}
                                    </p>

                                    <!-- Data Tambahan Terkait Postingan Utama (Kaya Informasi) -->
                                    <div
                                        class="mt-3 pt-2.5 border-t border-slate-100 dark:border-slate-800/80 flex flex-wrap items-center gap-x-4 gap-y-1.5 text-xs text-slate-600 dark:text-slate-400">
                                        @if ($primaryPost->event_location)
                                            <div class="inline-flex items-center gap-1.5 truncate max-w-[260px]"
                                                title="Lokasi: {{ $primaryPost->event_location }}">
                                                <svg class="w-3.5 h-3.5 text-rose-500 shrink-0" fill="none"
                                                    stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                        stroke-width="2"
                                                        d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                        stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                                                </svg>
                                                <span class="truncate">{{ $primaryPost->event_location }}</span>
                                            </div>
                                        @endif

                                        @if ($primaryPost->event_date)
                                            <div class="inline-flex items-center gap-1.5"
                                                title="Waktu Pelaksanaan Acara">
                                                <svg class="w-3.5 h-3.5 text-blue-500 shrink-0" fill="none"
                                                    stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                        stroke-width="2"
                                                        d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                                </svg>
                                                <span>Acara:
                                                    {{ $primaryPost->event_date->translatedFormat('d M Y') }}</span>
                                            </div>
                                        @endif

                                        <div class="inline-flex items-center gap-1.5" title="Jumlah Pembaca">
                                            <svg class="w-3.5 h-3.5 text-amber-500 shrink-0" fill="none"
                                                stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                            </svg>
                                            <span>{{ number_format($primaryPost->views_count) }}x Dibaca</span>
                                        </div>

                                        @if ($primaryPost->photos_count > 0)
                                            <div class="inline-flex items-center gap-1.5"
                                                title="Galeri Foto Dokumentasi">
                                                <svg class="w-3.5 h-3.5 text-emerald-500 shrink-0" fill="none"
                                                    stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                        stroke-width="2"
                                                        d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                                </svg>
                                                <span>{{ $primaryPost->photos_count }} Foto Arsip</span>
                                            </div>
                                        @endif
                                    </div>
                                </div>

                                <!-- Footer Kartu -->
                                <div
                                    class="mt-4 pt-3 border-t border-slate-200/80 dark:border-slate-800/80 flex items-center justify-between text-xs text-slate-500 dark:text-slate-400">
                                    <span class="font-medium truncate max-w-[200px] text-xs">
                                        {{ $primaryPost->unit?->name ?? 'Universitas Bina Insan' }}
                                    </span>
                                    <a href="{{ route('public.posts.show', $primaryPost->slug) }}"
                                        class="font-semibold text-blue-600 dark:text-blue-400 hover:underline flex items-center gap-1 shrink-0 text-xs">
                                        Baca Selengkapnya
                                        <svg class="w-3 h-3" fill="none" stroke="currentColor"
                                            viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M9 5l7 7-7 7" />
                                        </svg>
                                    </a>
                                </div>
                            </div>
                        </article>
                    </div>

                    @if ($sidePosts->count() > 0)
                        <!-- Kolom Kanan: 2 Card Kecil (Tersusun Vertikal Berimbang & Gambar Full Height) -->
                        <div class="lg:col-span-5 flex flex-col gap-4 justify-between">
                            @foreach ($sidePosts as $sidePost)
                                <article
                                    class="bg-white dark:bg-slate-900 rounded-xl border border-slate-300 dark:border-slate-800 shadow-xs hover:shadow-md transition-all duration-200 flex flex-col sm:flex-row group overflow-hidden flex-1">
                                    <!-- Thumbnail Foto Card Kecil: TINGGI GAMBAR FULL CARD -->
                                    <div
                                        class="w-full sm:w-44 md:w-52 shrink-0 relative overflow-hidden self-stretch bg-slate-100 dark:bg-slate-800 min-h-[170px] sm:min-h-full">
                                        <a href="{{ route('public.posts.show', $sidePost->slug) }}"
                                            class="block w-full h-full relative">
                                            @if ($thumb = $sidePost->getThumbnailUrl())
                                                <img src="{{ $thumb }}" alt="{{ $sidePost->title }}"
                                                    class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300">
                                            @else
                                                <div
                                                    class="w-full h-full flex flex-col items-center justify-center bg-gradient-to-br from-navy-900 to-slate-900 text-white p-3 text-center">
                                                    <span
                                                        class="text-xs font-bold uppercase tracking-wider text-blue-400">{{ $settings->university_acronym ?: 'UNIVBI' }}</span>
                                                </div>
                                            @endif

                                            <!-- Badges di atas Foto Full Height -->
                                            <div class="absolute top-2.5 left-2.5 flex flex-wrap gap-1">
                                                @if ($sidePost->category)
                                                    <span
                                                        class="px-2 py-0.5 rounded text-[9px] font-bold bg-navy-900/90 text-white backdrop-blur-xs shadow-2xs">
                                                        {{ $sidePost->category->name }}
                                                    </span>
                                                @endif
                                                @if ($sidePost->period)
                                                    <span
                                                        class="px-1.5 py-0.5 rounded text-[9px] font-bold bg-blue-600/90 text-white backdrop-blur-xs shadow-2xs">
                                                        {{ $sidePost->period->getShortLabel() }}
                                                    </span>
                                                @endif
                                                @if ($sidePost->is_featured)
                                                    <span
                                                        class="px-1.5 py-0.5 rounded text-[9px] font-extrabold uppercase bg-rose-600 text-white shadow-2xs">
                                                        ★
                                                    </span>
                                                @endif
                                            </div>

                                            @if ($sidePost->photos_count > 1)
                                                <div
                                                    class="absolute bottom-2.5 right-2.5 px-2 py-0.5 rounded text-[9px] font-bold bg-black/75 text-white backdrop-blur-xs flex items-center gap-1 shadow-2xs">
                                                    <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor"
                                                        viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round"
                                                            stroke-width="2"
                                                            d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                                    </svg>
                                                    {{ $sidePost->photos_count }} Foto
                                                </div>
                                            @endif
                                        </a>
                                    </div>

                                    <!-- Konten Teks Card Kecil -->
                                    <div class="p-4 sm:p-5 flex-1 flex flex-col justify-between min-w-0">
                                        <div>
                                            <!-- Tanggal Terbit -->
                                            <div
                                                class="flex items-center gap-2 text-[11px] text-slate-500 dark:text-slate-400 mb-1.5">
                                                <span
                                                    class="font-medium">{{ $sidePost->published_at?->translatedFormat('d M Y') ?? $sidePost->created_at->translatedFormat('d M Y') }}</span>
                                                @if ($sidePost->academicYear)
                                                    <span>•</span>
                                                    <span class="truncate max-w-[140px]">TA
                                                        {{ $sidePost->academicYear->name }}</span>
                                                @endif
                                            </div>

                                            <!-- Judul Card Kecil -->
                                            <h4
                                                class="text-sm sm:text-base font-bold text-slate-900 dark:text-white group-hover:text-blue-600 dark:group-hover:text-blue-400 transition-colors leading-snug line-clamp-2">
                                                <a href="{{ route('public.posts.show', $sidePost->slug) }}">
                                                    {{ $sidePost->title }}
                                                </a>
                                            </h4>

                                            <!-- Excerpt Ringkas -->
                                            <p
                                                class="text-xs text-slate-600 dark:text-slate-400 mt-1.5 line-clamp-2 leading-relaxed">
                                                {{ $sidePost->excerpt ?: Str::limit(strip_tags($sidePost->content ?? ''), 95) }}
                                            </p>

                                            <!-- Data Tambahan Terkait Postingan (Menghilangkan Ruang Kosong) -->
                                            <div
                                                class="mt-3 pt-2.5 border-t border-slate-100 dark:border-slate-800/80 flex flex-wrap items-center gap-x-3.5 gap-y-1.5 text-[11px] text-slate-600 dark:text-slate-400">
                                                @if ($sidePost->event_location)
                                                    <div class="inline-flex items-center gap-1.5 truncate max-w-[210px]"
                                                        title="Lokasi: {{ $sidePost->event_location }}">
                                                        <svg class="w-3.5 h-3.5 text-rose-500 shrink-0" fill="none"
                                                            stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                                stroke-width="2"
                                                                d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                                stroke-width="2"
                                                                d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                                                        </svg>
                                                        <span class="truncate">{{ $sidePost->event_location }}</span>
                                                    </div>
                                                @endif

                                                @if ($sidePost->event_date)
                                                    <div class="inline-flex items-center gap-1.5"
                                                        title="Waktu Pelaksanaan Acara">
                                                        <svg class="w-3.5 h-3.5 text-blue-500 shrink-0" fill="none"
                                                            stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                                stroke-width="2"
                                                                d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                                        </svg>
                                                        <span>Acara:
                                                            {{ $sidePost->event_date->translatedFormat('d M Y') }}</span>
                                                    </div>
                                                @endif

                                                <div class="inline-flex items-center gap-1.5" title="Jumlah Pembaca">
                                                    <svg class="w-3.5 h-3.5 text-amber-500 shrink-0" fill="none"
                                                        stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round"
                                                            stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                        <path stroke-linecap="round" stroke-linejoin="round"
                                                            stroke-width="2"
                                                            d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                                    </svg>
                                                    <span>{{ number_format($sidePost->views_count) }}x Dibaca</span>
                                                </div>

                                                @if ($sidePost->photos_count > 0)
                                                    <div class="inline-flex items-center gap-1.5"
                                                        title="Galeri Foto Dokumentasi">
                                                        <svg class="w-3.5 h-3.5 text-emerald-500 shrink-0"
                                                            fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                                stroke-width="2"
                                                                d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                                        </svg>
                                                        <span>{{ $sidePost->photos_count }} Foto Arsip</span>
                                                    </div>
                                                @endif
                                            </div>
                                        </div>

                                        <!-- Footer Mini: Unit Kerja & Link Baca -->
                                        <div
                                            class="mt-3.5 pt-2.5 border-t border-slate-200/80 dark:border-slate-800/80 flex items-center justify-between text-xs text-slate-500 dark:text-slate-400">
                                            <span class="font-medium truncate max-w-[170px]">
                                                {{ $sidePost->unit?->name ?? 'Universitas Bina Insan' }}
                                            </span>
                                            <a href="{{ route('public.posts.show', $sidePost->slug) }}"
                                                class="font-semibold text-blue-600 dark:text-blue-400 hover:underline flex items-center gap-1 shrink-0">
                                                Baca Selengkapnya
                                                <svg class="w-3 h-3" fill="none" stroke="currentColor"
                                                    viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                        stroke-width="2" d="M9 5l7 7-7 7" />
                                                </svg>
                                            </a>
                                        </div>
                                    </div>
                                </article>
                            @endforeach
                        </div>
                    @endif
                </div>

                @if ($extraPosts->count() > 0)
                    <!-- Baris Tambahan: Berita Ke-4 & Ke-5 (Jika Ada) -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-5 mt-5">
                        @foreach ($extraPosts as $extraPost)
                            <article
                                class="bg-white dark:bg-slate-900 rounded-xl border border-slate-300 dark:border-slate-800 shadow-xs hover:shadow-md transition-all duration-200 flex flex-col sm:flex-row group overflow-hidden">
                                <!-- Thumbnail Foto Card Ekstra: TINGGI GAMBAR FULL CARD -->
                                <div
                                    class="w-full sm:w-44 md:w-52 shrink-0 relative overflow-hidden self-stretch bg-slate-100 dark:bg-slate-800 min-h-[170px] sm:min-h-full">
                                    <a href="{{ route('public.posts.show', $extraPost->slug) }}"
                                        class="block w-full h-full relative">
                                        @if ($thumb = $extraPost->getThumbnailUrl())
                                            <img src="{{ $thumb }}" alt="{{ $extraPost->title }}"
                                                class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300">
                                        @else
                                            <div
                                                class="w-full h-full flex flex-col items-center justify-center bg-gradient-to-br from-navy-900 to-slate-900 text-white p-3 text-center">
                                                <span
                                                    class="text-xs font-bold uppercase tracking-wider text-blue-400">{{ $settings->university_acronym ?: 'UNIVBI' }}</span>
                                            </div>
                                        @endif

                                        <div class="absolute top-2.5 left-2.5 flex flex-wrap gap-1">
                                            @if ($extraPost->category)
                                                <span
                                                    class="px-2 py-0.5 rounded text-[9px] font-bold bg-navy-900/90 text-white backdrop-blur-xs shadow-2xs">
                                                    {{ $extraPost->category->name }}
                                                </span>
                                            @endif
                                            @if ($extraPost->period)
                                                <span
                                                    class="px-1.5 py-0.5 rounded text-[9px] font-bold bg-blue-600/90 text-white backdrop-blur-xs shadow-2xs">
                                                    {{ $extraPost->period->getShortLabel() }}
                                                </span>
                                            @endif
                                        </div>
                                    </a>
                                </div>

                                <!-- Konten Teks -->
                                <div class="p-4 sm:p-5 flex-1 flex flex-col justify-between min-w-0">
                                    <div>
                                        <div class="text-[11px] text-slate-500 dark:text-slate-400 mb-1">
                                            {{ $extraPost->published_at?->translatedFormat('d M Y') ?? $extraPost->created_at->translatedFormat('d M Y') }}
                                        </div>
                                        <h4
                                            class="text-sm sm:text-base font-bold text-slate-900 dark:text-white group-hover:text-blue-600 dark:group-hover:text-blue-400 transition-colors leading-snug line-clamp-2">
                                            <a href="{{ route('public.posts.show', $extraPost->slug) }}">
                                                {{ $extraPost->title }}
                                            </a>
                                        </h4>
                                        <p
                                            class="text-xs text-slate-600 dark:text-slate-400 mt-1.5 line-clamp-2 leading-relaxed">
                                            {{ $extraPost->excerpt ?: Str::limit(strip_tags($extraPost->content ?? ''), 95) }}
                                        </p>

                                        <!-- Data Tambahan -->
                                        <div
                                            class="mt-3 pt-2.5 border-t border-slate-100 dark:border-slate-800/80 flex flex-wrap items-center gap-x-3.5 gap-y-1.5 text-[11px] text-slate-600 dark:text-slate-400">
                                            @if ($extraPost->event_location)
                                                <div class="inline-flex items-center gap-1.5 truncate max-w-[210px]">
                                                    <svg class="w-3.5 h-3.5 text-rose-500 shrink-0" fill="none"
                                                        stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round"
                                                            stroke-width="2"
                                                            d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                                                        <path stroke-linecap="round" stroke-linejoin="round"
                                                            stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                                                    </svg>
                                                    <span class="truncate">{{ $extraPost->event_location }}</span>
                                                </div>
                                            @endif
                                            <div class="inline-flex items-center gap-1.5">
                                                <svg class="w-3.5 h-3.5 text-amber-500 shrink-0" fill="none"
                                                    stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                        stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                        stroke-width="2"
                                                        d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                                </svg>
                                                <span>{{ number_format($extraPost->views_count) }}x Dibaca</span>
                                            </div>
                                        </div>
                                    </div>

                                    <div
                                        class="mt-3.5 pt-2.5 border-t border-slate-200/80 dark:border-slate-800/80 flex items-center justify-between text-xs text-slate-500 dark:text-slate-400">
                                        <span class="truncate max-w-[150px]">
                                            {{ $extraPost->unit?->name ?? 'Universitas Bina Insan' }}
                                        </span>
                                        <a href="{{ route('public.posts.show', $extraPost->slug) }}"
                                            class="font-semibold text-blue-600 dark:text-blue-400 hover:underline flex items-center gap-0.5 shrink-0">
                                            Baca Selengkapnya
                                            <svg class="w-3 h-3" fill="none" stroke="currentColor"
                                                viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M9 5l7 7-7 7" />
                                            </svg>
                                        </a>
                                    </div>
                                </div>
                            </article>
                        @endforeach
                    </div>
                @endif

                <!-- Tombol Bawah: Lihat Semua Berita -->
                <div class="mt-8 sm:mt-10 text-center">
                    <a href="{{ route('public.posts.index') }}"
                        class="inline-flex items-center gap-2 px-6 py-3 rounded-lg text-xs sm:text-sm font-bold text-white bg-blue-600 hover:bg-blue-700 shadow-sm transition-all hover:scale-[1.02]">
                        <span>{{ $settings->news_view_all_label ?: 'Lihat Semua Warta Kampus' }}</span>
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M17 8l4 4m0 0l-4 4m4-4H3" />
                        </svg>
                    </a>
                </div>
            @else
                <!-- State Kosong Berita -->
                <div
                    class="py-16 text-center bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 p-8 shadow-xs">
                    <div
                        class="w-14 h-14 mx-auto mb-3.5 rounded-xl bg-slate-100 dark:bg-slate-800 flex items-center justify-center text-slate-400">
                        <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                d="M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10a2 2 0 012 2v1m2 13a2 2 0 01-2-2V7m2 13a2 2 0 002-2V9a2 2 0 00-2-2h-2m-4-3H9M7 16h6M7 8h6v4H7V8z" />
                        </svg>
                    </div>
                    <h3 class="text-base font-bold text-slate-900 dark:text-white">Belum Ada Warta Diterbitkan</h3>
                    <p class="text-xs sm:text-sm text-slate-500 dark:text-slate-400 mt-1 max-w-sm mx-auto">
                        Warta dan liputan dokumentasi kegiatan kampus akan ditampilkan di sini setelah dirilis secara
                        resmi.
                    </p>
                </div>
            @endif
        </div>
    </section>

    <!-- 5. Footer Institusi & Developer Resmi -->
    @include('public.partials.footer')

    <!-- Script Pengendali Dark Mode, Menu Mobile & Custom Dropdown -->
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

        function toggleMobileMenu() {
            const menu = document.getElementById('mobile-menu');
            if (menu) {
                menu.classList.toggle('hidden');
            }
        }

        function toggleCategoryDropdown() {
            const menu = document.getElementById('category-dropdown-menu');
            const chevron = document.getElementById('category-dropdown-chevron');
            if (menu) {
                const isHidden = menu.classList.contains('hidden');
                if (isHidden) {
                    menu.classList.remove('hidden');
                    if (chevron) chevron.classList.add('rotate-180');
                } else {
                    menu.classList.add('hidden');
                    if (chevron) chevron.classList.remove('rotate-180');
                }
            }
        }

        function selectCategory(id, name) {
            const input = document.getElementById('selected-category-input');
            const label = document.getElementById('category-selected-label');
            if (input) input.value = id;
            if (label) label.textContent = name;
            toggleCategoryDropdown();
        }

        document.addEventListener('click', function(event) {
            const container = document.getElementById('category-dropdown-container');
            const menu = document.getElementById('category-dropdown-menu');
            const chevron = document.getElementById('category-dropdown-chevron');
            if (container && menu && !container.contains(event.target)) {
                menu.classList.add('hidden');
                if (chevron) chevron.classList.remove('rotate-180');
            }
        });
    </script>
</body>

</html>
