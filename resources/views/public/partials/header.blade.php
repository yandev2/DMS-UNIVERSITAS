@php
    $headerSettings = $settings ?? $activeSettings ?? \App\Models\LandingPageSetting::current();
    $headerLogoUrl = $headerSettings->getLogoUrl();
    $currentRouteName = request()->route() ? request()->route()->getName() : '';
    $isHome = request()->is('/') || $currentRouteName === 'landing.home';
    $isPosts = request()->is('warta*') || request()->is('berita*') || str_starts_with($currentRouteName, 'public.posts.');
    $hasAccreditationMenu = isset($accreditationShowcase)
        ? (($accreditationShowcase['total_count'] ?? 0) > 0)
        : \App\Models\Unit::hasPublicAccreditedUnits();
@endphp

<!-- Header Institusi Resmi (Seragam & Terstruktur) -->
<header class="bg-white/95 dark:bg-slate-900/95 backdrop-blur-md border-b border-slate-200 dark:border-slate-800/80 shrink-0 sticky top-0 z-40 shadow-xs transition-colors">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-3 flex items-center justify-between gap-4">
        <!-- Branding Kampus & Nama Sistem -->
        <a href="{{ route('landing.home') }}" class="flex items-center gap-3 min-w-0 group">
            @if($headerLogoUrl)
                <img src="{{ $headerLogoUrl }}" alt="{{ $headerSettings->university_name }}" class="h-10 w-auto object-contain shrink-0 group-hover:scale-105 transition-transform">
            @else
                <div class="h-10 px-3 rounded-lg bg-navy-900 dark:bg-blue-600 text-white flex items-center justify-center font-black text-xs tracking-wider shadow-xs shrink-0 group-hover:scale-105 transition-transform">
                    {{ $headerSettings->university_acronym ?: 'UNIVBI' }}
                </div>
            @endif
            <div class="truncate">
                <span class="block text-sm sm:text-base font-extrabold text-slate-900 dark:text-white leading-tight group-hover:text-blue-600 dark:group-hover:text-blue-400 transition-colors truncate">
                    {{ $headerSettings->university_name }}
                </span>
                <span class="block text-xs font-medium text-slate-500 dark:text-slate-400 truncate mt-0.5">
                    {{ $headerSettings->system_name }}
                </span>
            </div>
        </a>

        <!-- Navigasi Desktop -->
        <nav class="hidden lg:flex items-center gap-1.5 text-xs font-semibold">
            <a href="{{ route('landing.home') }}" class="px-3 py-1.5 rounded-lg transition-colors {{ $isHome ? 'text-blue-600 dark:text-blue-400 bg-blue-50 dark:bg-blue-900/30 font-bold' : 'text-slate-600 dark:text-slate-300 hover:text-slate-900 dark:hover:text-white hover:bg-slate-100 dark:hover:bg-slate-800' }}">
                Beranda
            </a>
            <a href="{{ $isHome ? '#dokumen-publik' : route('landing.home') . '#dokumen-publik' }}" class="px-3 py-1.5 rounded-lg text-slate-600 dark:text-slate-300 hover:text-slate-900 dark:hover:text-white hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors">
                Dokumen Publik
            </a>
            @if ($hasAccreditationMenu)
                <a href="{{ $isHome ? '#status-akreditasi' : route('landing.home') . '#status-akreditasi' }}" class="px-3 py-1.5 rounded-lg text-slate-600 dark:text-slate-300 hover:text-slate-900 dark:hover:text-white hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors">
                    Akreditasi
                </a>
            @endif
            <a href="{{ route('public.posts.index') }}" class="px-3 py-1.5 rounded-lg transition-colors {{ $isPosts ? 'text-blue-600 dark:text-blue-400 bg-blue-50 dark:bg-blue-900/30 font-bold' : 'text-slate-600 dark:text-slate-300 hover:text-slate-900 dark:hover:text-white hover:bg-slate-100 dark:hover:bg-slate-800' }}">
                Warta & Acara
            </a>
        </nav>

        <!-- Aksi Kanan: Dark Mode Toggle & Login Portal -->
        <div class="flex items-center gap-2 sm:gap-3 shrink-0">
            <!-- Tombol Mode Gelap/Terang -->
            <button
                type="button"
                onclick="toggleDarkMode()"
                class="p-2 text-slate-500 hover:text-slate-800 dark:text-slate-400 dark:hover:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-800 rounded-lg transition-colors cursor-pointer"
                title="Beralih Mode Gelap/Terang"
                aria-label="Toggle Theme"
            >
                <svg class="w-5 h-5 hidden dark:block text-amber-400" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M10 2a1 1 0 011 1v1a1 1 0 11-2 0V3a1 1 0 011-1zm4 8a4 4 0 11-8 0 4 4 0 018 0zm-.464 4.95l.707.707a1 1 0 001.414-1.414l-.707-.707a1 1 0 00-1.414 1.414zm2.12-10.607a1 1 0 010 1.414l-.706.707a1 1 0 11-1.414-1.414l.707-.707a1 1 0 011.414 0zM17 11a1 1 0 100-2h-1a1 1 0 100 2h1zm-7 4a1 1 0 011 1v1a1 1 0 11-2 0v-1a1 1 0 011-1zM5.05 6.464A1 1 0 106.465 5.05l-.708-.707a1 1 0 00-1.414 1.414l.707.707zm1.414 8.486l-.707.707a1 1 0 01-1.414-1.414l.707-.707a1 1 0 011.414 1.414zM4 11a1 1 0 100-2H3a1 1 0 000 2h1z" clip-rule="evenodd" />
                </svg>
                <svg class="w-5 h-5 block dark:hidden text-slate-600" fill="currentColor" viewBox="0 0 20 20">
                    <path d="M17.293 13.293A8 8 0 016.707 2.707a8.001 8.001 0 1010.586 10.586z" />
                </svg>
            </button>

            <!-- Tombol Portal Staf / Admin -->
            <a
                href="{{ route('filament.admin.auth.login') }}"
                class="inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-lg text-xs font-semibold bg-navy-900 hover:bg-navy-800 text-white dark:bg-blue-600 dark:hover:bg-blue-700 shadow-xs transition-colors"
            >
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1" />
                </svg>
                <span class="hidden sm:inline">Portal Staf / Admin</span>
                <span class="sm:hidden">Masuk</span>
            </a>

            <!-- Mobile Menu Button -->
            <button
                type="button"
                onclick="toggleMobileMenu()"
                class="lg:hidden p-2 text-slate-500 hover:text-slate-800 dark:text-slate-400 dark:hover:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-800 rounded-lg transition-colors cursor-pointer"
                aria-label="Buka Menu"
            >
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                </svg>
            </button>
        </div>
    </div>

    <!-- Mobile Navigation Menu Dropdown -->
    <div id="mobile-menu" class="hidden lg:hidden border-t border-slate-200 dark:border-slate-800 px-4 pt-2 pb-4 space-y-1 bg-white/95 dark:bg-slate-900/95 backdrop-blur-md">
        <a href="{{ route('landing.home') }}" class="block px-3 py-2 rounded-lg text-sm font-semibold {{ $isHome ? 'text-blue-600 dark:text-blue-400 bg-blue-50 dark:bg-blue-900/30' : 'text-slate-700 dark:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-800' }}">
            Beranda Utama
        </a>
        <a href="{{ $isHome ? '#dokumen-publik' : route('landing.home') . '#dokumen-publik' }}" @if($isHome) onclick="toggleMobileMenu()" @endif class="block px-3 py-2 rounded-lg text-sm font-medium text-slate-700 dark:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-800">
            Daftar Dokumen Publik
        </a>
        @if ($hasAccreditationMenu)
            <a href="{{ $isHome ? '#status-akreditasi' : route('landing.home') . '#status-akreditasi' }}" @if($isHome) onclick="toggleMobileMenu()" @endif class="block px-3 py-2 rounded-lg text-sm font-medium text-slate-700 dark:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-800">
                Status Akreditasi
            </a>
        @endif
        <a href="{{ route('public.posts.index') }}" class="block px-3 py-2 rounded-lg text-sm font-medium {{ $isPosts ? 'text-blue-600 dark:text-blue-400 bg-blue-50 dark:bg-blue-900/30 font-semibold' : 'text-slate-700 dark:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-800' }}">
            Warta & Dokumentasi Kegiatan
        </a>
    </div>
</header>

<script>
    if (typeof window.toggleDarkMode === 'undefined') {
        window.toggleDarkMode = function() {
            if (document.documentElement.classList.contains('dark')) {
                document.documentElement.classList.remove('dark');
                localStorage.theme = 'light';
            } else {
                document.documentElement.classList.add('dark');
                localStorage.theme = 'dark';
            }
        };
    }
    if (typeof window.toggleMobileMenu === 'undefined') {
        window.toggleMobileMenu = function() {
            const menu = document.getElementById('mobile-menu');
            if (menu) {
                menu.classList.toggle('hidden');
            }
        };
    }
</script>

