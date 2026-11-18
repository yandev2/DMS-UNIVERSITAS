<!DOCTYPE html>
<html lang="id" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>@yield('title', 'Terjadi Kendala') | {{ $univName ?? 'Universitas Bina Insan' }}</title>

    @php
        // Resiliensi penuh terhadap kegagalan Database (misal PDOException pada error 500)
        $activeSettings = rescue(fn () => \App\Models\LandingPageSetting::current(), null, false);
        $univName = $activeSettings?->university_name ?: 'Universitas Bina Insan';
        $univAcronym = $activeSettings?->university_acronym ?: 'UNIVBI';
        $systemName = $activeSettings?->system_name ?: 'Sistem Informasi e-Arsip Terpadu & Publikasi Kampus';
        $logoUrl = $activeSettings ? $activeSettings->getLogoUrl() : null;
        $campusEmail = $activeSettings?->campus_email ?: 'humas@univbi.ac.id';
        $campusPhone = $activeSettings?->campus_phone ?: '(0733) 322442';

        $sealDevName = \App\Support\Security\RuntimeIntegrityManifest::getDeveloperName();
        $sealDevEmail = \App\Support\Security\RuntimeIntegrityManifest::getDeveloperEmail();
        $sealDevWeb = \App\Support\Security\RuntimeIntegrityManifest::getDeveloperWebsite();
        $sealCopyright = \App\Support\Security\RuntimeIntegrityManifest::getCopyrightNotice();
    @endphp

    @if($activeSettings && $activeSettings->favicon_path)
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

    <!-- Fallback gaya darurat jika Vite asset gagal termuat pada kondisi error server kritis -->
    <style>
        body { font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; }
    </style>
</head>
<body class="min-h-full flex flex-col font-sans antialiased text-slate-800 dark:text-slate-100 bg-slate-100 dark:bg-slate-950 selection:bg-blue-100 selection:text-blue-900 transition-colors duration-200">

    <!-- Header Institusi Resmi (Kokoh, Tegas, Presisi) -->
    <header class="bg-white/95 dark:bg-slate-900/95 backdrop-blur-md border-b border-slate-200 dark:border-slate-800 shrink-0 sticky top-0 z-40 shadow-2xs transition-colors">
        <div class="max-w-5xl mx-auto px-4 sm:px-6 py-3 flex items-center justify-between gap-4">
            <a href="{{ url('/') }}" class="flex items-center gap-3 min-w-0 group" title="Kembali ke Beranda Utama">
                @if($logoUrl)
                    <img src="{{ $logoUrl }}" alt="{{ $univName }}" class="h-10 w-auto object-contain shrink-0 group-hover:opacity-90 transition-opacity">
                @else
                    <div class="h-10 px-3 rounded-lg bg-slate-900 dark:bg-blue-600 text-white flex items-center justify-center font-black text-xs tracking-wider shadow-2xs shrink-0">
                        {{ $univAcronym }}
                    </div>
                @endif
                <div class="truncate">
                    <span class="block text-sm sm:text-base font-extrabold text-slate-900 dark:text-white leading-tight group-hover:text-blue-600 dark:group-hover:text-blue-400 transition-colors truncate">
                        {{ $univName }}
                    </span>
                    <div class="flex items-center gap-1.5 mt-0.5 text-xs text-slate-500 dark:text-slate-400 font-medium truncate">
                        <span class="font-semibold text-slate-700 dark:text-slate-300 truncate">Layanan Informasi Status Sistem</span>
                        <span class="hidden md:inline-block text-slate-300 dark:text-slate-600 font-light">•</span>
                        <span class="hidden md:inline-block truncate">
                            {{ $systemName }}
                        </span>
                    </div>
                </div>
            </a>

            <!-- Kontrol Mode Tampilan -->
            <button type="button" 
                    onclick="toggleTheme()" 
                    class="p-2 rounded-lg border border-slate-200 dark:border-slate-700 text-slate-500 hover:text-slate-800 dark:text-slate-400 dark:hover:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors"
                    title="Beralih Mode Gelap / Terang"
                    aria-label="Toggle Theme">
                <svg class="w-4 h-4 hidden dark:block text-amber-400" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M10 2a1 1 0 011 1v1a1 1 0 11-2 0V3a1 1 0 011-1zm4 8a4 4 0 11-8 0 4 4 0 018 0zm-.464 4.95l.707.707a1 1 0 001.414-1.414l-.707-.707a1 1 0 00-1.414 1.414zm2.12-10.607a1 1 0 010 1.414l-.706.707a1 1 0 11-1.414-1.414l.707-.707a1 1 0 011.414 0zM17 11a1 1 0 100-2h-1a1 1 0 100 2h1zm-7 4a1 1 0 011 1v1a1 1 0 11-2 0v-1a1 1 0 011-1zM5.05 6.464A1 1 0 106.465 5.05l-.708-.707a1 1 0 00-1.414 1.414l.707.707zm1.414 8.486l-.707.707a1 1 0 01-1.414-1.414l.707-.707a1 1 0 011.414 1.414zM4 11a1 1 0 100-2H3a1 1 0 000 2h1z" clip-rule="evenodd" />
                </svg>
                <svg class="w-4 h-4 block dark:hidden text-slate-600" fill="currentColor" viewBox="0 0 20 20">
                    <path d="M17.293 13.293A8 8 0 016.707 2.707a8.001 8.001 0 1010.586 10.586z" />
                </svg>
            </button>
        </div>
    </header>

    <!-- Konten Utama Halaman Status Error -->
    <main class="flex-1 max-w-4xl w-full mx-auto px-4 sm:px-6 py-8 sm:py-12 flex flex-col justify-center">

        <div class="bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-800 rounded-2xl p-6 sm:p-10 shadow-xs space-y-7 transition-colors">
            
            <!-- Blok Judul & Status Header -->
            <div class="flex flex-col sm:flex-row items-start gap-5 sm:gap-6">
                <!-- Kotak Ikon Identitas Error (Kotak Persegi Presisi, Bukan Pill/Lingkaran) -->
                <div class="w-14 h-14 sm:w-16 sm:h-16 rounded-xl flex items-center justify-center shrink-0 border shadow-2xs @yield('icon_container_classes', 'bg-slate-100 dark:bg-slate-800 border-slate-300 dark:border-slate-700 text-slate-700 dark:text-slate-200')">
                    @yield('icon')
                </div>

                <div class="space-y-2 flex-1 min-w-0">
                    <!-- Badge Status Persegi Panjang Tegas (Bukan Pill) -->
                    <div class="inline-flex items-center gap-2 px-2.5 py-1 text-xs font-mono font-bold uppercase tracking-wider rounded-md border @yield('badge_classes', 'bg-slate-100 dark:bg-slate-800 text-slate-800 dark:text-slate-200 border-slate-300 dark:border-slate-700')">
                        <span class="w-1.5 h-1.5 rounded-xs @yield('badge_dot_classes', 'bg-slate-500')"></span>
                        @yield('badge_text', 'STATUS HTTP')
                    </div>

                    <h1 class="text-xl sm:text-2xl font-black text-slate-900 dark:text-white tracking-tight leading-tight">
                        @yield('heading', 'Terjadi Kendala pada Permintaan Anda')
                    </h1>

                    <p class="text-sm text-slate-600 dark:text-slate-300 leading-relaxed">
                        @yield('description')
                    </p>
                </div>
            </div>

            <!-- Konten Tambahan / Pesan Spesifik Exception -->
            @yield('additional_alert')

            <!-- Panel Panduan Diagnostik & Verifikasi Mandiri -->
            <div class="bg-slate-50 dark:bg-slate-950/60 border border-slate-200 dark:border-slate-800 rounded-xl p-4 sm:p-5">
                <div class="text-xs font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-3 flex items-center gap-2">
                    <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <span>@yield('diagnostic_title', 'Panduan Verifikasi & Langkah Tindak Lanjut')</span>
                </div>

                <ul class="space-y-2 text-xs sm:text-sm text-slate-600 dark:text-slate-400">
                    @yield('diagnostic_items')
                </ul>
            </div>

            <!-- Bilah Aksi Utama (Tombol Presisi Segi Empat Rounded-LG) -->
            <div class="pt-4 border-t border-slate-200 dark:border-slate-800 flex flex-wrap items-center justify-between gap-3">
                <div class="flex flex-wrap items-center gap-2.5">
                    @yield('actions')
                </div>

                <div class="text-xs text-slate-500 dark:text-slate-400">
                    Butuh bantuan? <a href="mailto:{{ $campusEmail }}" class="font-medium text-blue-600 dark:text-blue-400 hover:underline">{{ $campusEmail }}</a>
                </div>
            </div>

            <!-- Catatan Debugging Khusus Pengembang (Hanya aktif jika APP_DEBUG=true) -->
            @if(config('app.debug') && isset($exception) && $exception->getMessage())
                <div class="pt-2">
                    <details class="text-xs font-mono bg-slate-100 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 rounded-lg p-3">
                        <summary class="cursor-pointer font-bold text-slate-700 dark:text-slate-300 hover:text-blue-600 select-none">
                            Informasi Teknis Internal (Mode Pengembang Aktif)
                        </summary>
                        <div class="mt-2 text-slate-600 dark:text-slate-400 overflow-x-auto whitespace-pre-wrap break-words leading-relaxed border-t border-slate-200 dark:border-slate-800 pt-2">
<strong>Exception:</strong> {{ get_class($exception) }}
<strong>Pesan:</strong> {{ $exception->getMessage() }}
<strong>Lokasi:</strong> {{ $exception->getFile() }}:{{ $exception->getLine() }}
                        </div>
                    </details>
                </div>
            @endif

        </div>
    </main>

    <!-- Footer Universal Resmi & Segel Integritas Pengembang (Konsisten & Proporsional) -->
    <footer class="bg-white/95 dark:bg-slate-900/95 border-t border-slate-200 dark:border-slate-800/80 mt-auto shrink-0 transition-colors">
        <div class="max-w-5xl mx-auto px-4 sm:px-6 py-4">
            <div class="flex flex-col md:flex-row items-center justify-between gap-2.5 text-xs text-slate-600 dark:text-slate-400">
                <!-- Identitas Pengembang Resmi -->
                <div class="flex flex-wrap items-center justify-center md:justify-start gap-1.5">
                    <span>Dikembangkan oleh:</span>
                    <span class="font-bold text-slate-800 dark:text-slate-200">{{ $sealDevName }}</span>
                    <span>•</span>
                    <a href="mailto:{{ $sealDevEmail }}" class="text-blue-600 dark:text-blue-400 hover:underline font-medium inline-flex items-center gap-1">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                        </svg>
                        {{ $sealDevEmail }}
                    </a>
                    @if($sealDevWeb)
                        <span>•</span>
                        <a href="{{ $sealDevWeb }}" target="_blank" rel="noopener noreferrer" class="hover:text-slate-800 dark:hover:text-slate-200 transition-colors inline-flex items-center gap-1">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                            </svg>
                            Portofolio / Web
                        </a>
                    @endif
                </div>

                <!-- Hak Cipta Institusi -->
                <div class="text-slate-500 dark:text-slate-400 text-center md:text-right">
                    {{ $sealCopyright }}
                </div>
            </div>
        </div>
    </footer>

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
    </script>
</body>
</html>

