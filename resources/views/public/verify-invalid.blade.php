<!DOCTYPE html>
<html lang="id" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Dokumen Tidak Ditemukan - Verifikasi Keabsahan Naskah | {{ ($settings ?? $siteSettings ?? \App\Models\LandingPageSetting::current())->university_name }}</title>

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
</head>
<body class="min-h-full flex flex-col font-sans antialiased text-slate-800 dark:text-slate-100 bg-slate-100 dark:bg-slate-950 selection:bg-rose-100 selection:text-rose-900 transition-colors duration-200">

    <!-- Header Institusi (Ukuran Seragam & Konsisten) -->
    <header class="bg-white/95 dark:bg-slate-900/95 backdrop-blur-md border-b border-slate-200 dark:border-slate-800/80 shrink-0 sticky top-0 z-40 shadow-xs transition-colors">
        <div class="max-w-5xl mx-auto px-4 sm:px-6 py-3 flex items-center justify-between gap-3 sm:gap-4">
            <a href="{{ route('landing.home') }}" class="flex items-center gap-3 min-w-0 group">
                @if($logoUrl)
                    <img src="{{ $logoUrl }}" alt="{{ $activeSettings->university_name }}" class="h-10 w-auto object-contain shrink-0 group-hover:scale-105 transition-transform">
                @else
                    <div class="h-10 px-3 rounded-lg bg-navy-900 dark:bg-blue-600 text-white flex items-center justify-center font-black text-xs tracking-wider shadow-xs shrink-0 group-hover:scale-105 transition-transform">
                        {{ $activeSettings->university_acronym ?: 'UNIVBI' }}
                    </div>
                @endif
                <div class="truncate">
                    <span class="block text-sm sm:text-base font-extrabold text-slate-900 dark:text-white leading-tight group-hover:text-blue-600 dark:group-hover:text-blue-400 transition-colors truncate">
                        {{ $activeSettings->university_name ?: 'Universitas Bina Insan' }}
                    </span>
                    <div class="flex items-center gap-1.5 mt-0.5 text-xs text-slate-500 dark:text-slate-400 font-medium truncate">
                        <span class="text-rose-600 dark:text-rose-400 font-semibold truncate">Peringatan Verifikasi Naskah</span>
                        <span class="hidden md:inline-block text-slate-300 dark:text-slate-600 font-light">•</span>
                        <span class="hidden md:inline-block truncate">
                            {{ $activeSettings->system_name ?: 'Pangkalan Data e-Arsip' }}
                        </span>
                    </div>
                </div>
            </a>

            <!-- Toggle Dark/Light Mode -->
            <button type="button" 
                    onclick="toggleTheme()" 
                    class="p-2 rounded-lg text-slate-500 hover:text-slate-800 dark:text-slate-400 dark:hover:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors"
                    title="Beralih Mode Gelap / Terang"
                    aria-label="Toggle Theme">
                <svg class="w-5 h-5 hidden dark:block text-amber-400" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M10 2a1 1 0 011 1v1a1 1 0 11-2 0V3a1 1 0 011-1zm4 8a4 4 0 11-8 0 4 4 0 018 0zm-.464 4.95l.707.707a1 1 0 001.414-1.414l-.707-.707a1 1 0 00-1.414 1.414zm2.12-10.607a1 1 0 010 1.414l-.706.707a1 1 0 11-1.414-1.414l.707-.707a1 1 0 011.414 0zM17 11a1 1 0 100-2h-1a1 1 0 100 2h1zm-7 4a1 1 0 011 1v1a1 1 0 11-2 0v-1a1 1 0 011-1zM5.05 6.464A1 1 0 106.465 5.05l-.708-.707a1 1 0 00-1.414 1.414l.707.707zm1.414 8.486l-.707.707a1 1 0 01-1.414-1.414l.707-.707a1 1 0 011.414 1.414zM4 11a1 1 0 100-2H3a1 1 0 000 2h1z" clip-rule="evenodd" />
                </svg>
                <svg class="w-5 h-5 block dark:hidden text-slate-600" fill="currentColor" viewBox="0 0 20 20">
                    <path d="M17.293 13.293A8 8 0 016.707 2.707a8.001 8.001 0 1010.586 10.586z" />
                </svg>
            </button>
        </div>
    </header>

    <!-- Konten Utama Invalid -->
    <main class="flex-1 max-w-3xl w-full mx-auto px-4 sm:px-6 py-8 sm:py-12 flex flex-col justify-center space-y-6">

        <div class="rounded-2xl p-6 sm:p-10 bg-white dark:bg-slate-900 border-2 border-rose-500/40 dark:border-rose-500/30 shadow-lg space-y-6 text-center sm:text-left transition-colors">
            
            <div class="flex flex-col sm:flex-row items-center sm:items-start gap-5 sm:gap-6">
                <!-- Icon Warning Red -->
                <div class="w-16 h-16 sm:w-20 sm:h-20 rounded-2xl bg-rose-600 text-white flex items-center justify-center shadow-lg shadow-rose-600/30 shrink-0">
                    <svg class="w-10 h-10 sm:w-12 sm:h-12" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                    </svg>
                </div>

                <div class="space-y-2 flex-1">
                    <div class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold uppercase tracking-wider bg-rose-100 dark:bg-rose-950 text-rose-800 dark:text-rose-300 border border-rose-300 dark:border-rose-800">
                        Peringatan Keabsahan
                    </div>
                    <h1 class="text-xl sm:text-2xl font-black text-slate-900 dark:text-white tracking-tight">
                        DOKUMEN TIDAK TERDAFTAR / TIDAK VALID
                    </h1>
                    <p class="text-sm text-slate-600 dark:text-slate-300 leading-relaxed">
                        Kode unik atau barcode QR yang Anda pindai <strong>tidak ditemukan</strong> dalam Pangkalan Data e-Arsip Resmi Universitas Bina Insan.
                    </p>
                </div>
            </div>

            <!-- Kode yang Dipindai -->
            <div class="bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-xl p-4 text-left">
                <span class="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider block mb-1">
                    Kode Naskah yang Dipindai:
                </span>
                <code class="text-xs sm:text-sm font-mono font-bold text-rose-600 dark:text-rose-400 break-all block">
                    {{ $uuid }}
                </code>
            </div>

            <!-- Himbauan Keamanan / Waspada Pemalsuan -->
            <div class="p-4 rounded-xl bg-rose-50 dark:bg-rose-950/40 border border-rose-200 dark:border-rose-800/80 text-left text-xs text-rose-900 dark:text-rose-200 leading-relaxed space-y-2">
                <div class="font-bold flex items-center gap-1.5 text-sm">
                    <svg class="w-4 h-4 text-rose-600 dark:text-rose-400" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>
                    </svg>
                    Waspada Potensi Pemalsuan Naskah Dinas:
                </div>
                <p>
                    Apabila Anda memindai barcode ini dari naskah fisik (Surat Keputusan, Surat Tugas, Sertifikat, MoU, atau Surat Keterangan) yang mengatasnamakan Universitas Bina Insan, harap berhati-hati. Naskah tersebut berpotensi <strong>tidak asli atau telah mengalami pemalsuan</strong>.
                </p>
            </div>

            <!-- Kontak Bantuan Konfirmasi -->
            <div class="border-t border-slate-200 dark:border-slate-800 pt-6 text-left space-y-3">
                <h3 class="text-xs font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wider">
                    Layanan Konfirmasi Resmi {{ $activeSettings->university_name ?: 'Universitas Bina Insan' }}
                </h3>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs text-slate-600 dark:text-slate-400">
                    <div class="p-3 rounded-lg bg-slate-50 dark:bg-slate-950/60 border border-slate-200/80 dark:border-slate-800">
                        <strong class="block text-slate-800 dark:text-slate-200">Biro Rektorat / BAAK</strong>
                        <span>Email: <a href="mailto:{{ $activeSettings->campus_email ?: 'info@univbi.ac.id' }}" class="text-blue-600 dark:text-blue-400 hover:underline">{{ $activeSettings->campus_email ?: 'info@univbi.ac.id' }}</a></span>
                    </div>
                    <div class="p-3 rounded-lg bg-slate-50 dark:bg-slate-950/60 border border-slate-200/80 dark:border-slate-800">
                        <strong class="block text-slate-800 dark:text-slate-200">Lembaga Penjaminan Mutu (LPM)</strong>
                        <span>Gedung Rektorat Kampus {{ $activeSettings->university_acronym ?: 'UNIVBI' }}</span>
                    </div>
                </div>
            </div>

            <div class="pt-2 text-center sm:text-left">
                <a href="/" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg text-xs font-bold bg-navy-900 hover:bg-blue-700 dark:bg-blue-600 dark:hover:bg-blue-500 text-white transition shadow-sm">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                    </svg>
                    <span>Kembali ke Beranda Portal</span>
                </a>
            </div>

        </div>

    </main>

    <!-- Footer Institusi -->
    <footer class="bg-white dark:bg-slate-900 border-t border-slate-200 dark:border-slate-800 py-6 px-4 sm:px-6 text-center text-xs text-slate-500 dark:text-slate-400 transition-colors">
        <div class="max-w-5xl mx-auto space-y-2">
            <p class="font-semibold text-slate-700 dark:text-slate-300">
                Layanan Verifikasi e-Arsip & Naskah Dinas Elektronik • {{ $activeSettings->university_name ?: 'Universitas Bina Insan' }} ({{ $activeSettings->university_acronym ?: 'UNIVBI' }})
            </p>
            <p>
                {{ $activeSettings->campus_address ?: 'Jl. Jenderal Besar Dr. A.H. Nasution No. 23, Kota Lubuklinggau, Sumatera Selatan 31626' }}
            </p>

            @php
                $sealDevName = \App\Support\Security\RuntimeIntegrityManifest::getDeveloperName();
                $sealDevEmail = \App\Support\Security\RuntimeIntegrityManifest::getDeveloperEmail();
                $sealDevWeb = \App\Support\Security\RuntimeIntegrityManifest::getDeveloperWebsite();
                $sealCopyright = \App\Support\Security\RuntimeIntegrityManifest::getCopyrightNotice();
            @endphp

            <!-- Sub-Footer: Informasi Developer Paten & Copyright -->
            <div class="mt-4 pt-4 border-t border-slate-100 dark:border-slate-800/80 flex flex-col md:flex-row items-center justify-between gap-3 text-[11px] text-slate-500 dark:text-slate-400">
                <div class="flex flex-wrap items-center justify-center md:justify-start gap-1.5">
                    <span>Dikembangkan oleh:</span>
                    <span class="font-bold text-slate-700 dark:text-slate-200">{{ $sealDevName }}</span>
                    <span>•</span>
                    <a href="mailto:{{ $sealDevEmail }}" class="text-blue-600 dark:text-blue-400 hover:underline font-medium inline-flex items-center gap-1">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                        </svg>
                        {{ $sealDevEmail }}
                    </a>
                    @if($sealDevWeb)
                        <span>•</span>
                        <a href="{{ $sealDevWeb }}" target="_blank" rel="noopener noreferrer" class="hover:text-slate-700 dark:hover:text-slate-200 transition-colors inline-flex items-center gap-1">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                            </svg>
                            Portofolio / Web
                        </a>
                    @endif
                </div>

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

