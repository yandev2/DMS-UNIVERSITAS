<!DOCTYPE html>
<html lang="id" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Verifikasi Keabsahan Naskah Dinas: {{ $document->document_number ?? 'UNIVBI' }} | {{ ($settings ?? $siteSettings ?? \App\Models\LandingPageSetting::current())->university_name }}</title>

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
        // Set tema awal segera untuk mencegah flicker layar
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
<body class="min-h-full flex flex-col font-sans antialiased text-slate-800 dark:text-slate-100 bg-slate-100 dark:bg-slate-950 selection:bg-blue-100 selection:text-blue-900 transition-colors duration-200">

    <!-- Header Institusi Resmi (Ukuran Seragam & Konsisten) -->
    <header class="bg-white/95 dark:bg-slate-900/95 backdrop-blur-md border-b border-slate-200 dark:border-slate-800/80 shrink-0 sticky top-0 z-40 shadow-xs transition-colors">
        <div class="max-w-5xl mx-auto px-4 sm:px-6 py-3 flex items-center justify-between gap-3 sm:gap-4">
            <!-- Branding Kampus (Ukuran Identik dengan Beranda & Warta) -->
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
                        <span class="truncate">Verifikasi Naskah Dinas</span>
                        <span class="hidden md:inline-block text-slate-300 dark:text-slate-600 font-light">•</span>
                        <span class="hidden md:inline-block font-semibold text-blue-600 dark:text-blue-400 truncate">
                            {{ $activeSettings->system_name ?: 'Pangkalan Data e-Arsip' }}
                        </span>
                    </div>
                </div>
            </a>

            <!-- Tombol Pengalih Tema & Status Singkat -->
            <div class="flex items-center gap-2 sm:gap-3 shrink-0">
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

                <!-- Lencana Status Navbar -->
                @if($verificationStatus === 'valid')
                    <!-- Desktop: Badge Lengkap -->
                    <div class="hidden sm:inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold bg-emerald-50 dark:bg-emerald-950/40 text-emerald-700 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-800/60 shadow-xs">
                        <svg class="w-4 h-4 text-emerald-600 dark:text-emerald-400 shrink-0" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                        </svg>
                        <span class="font-bold">Terverifikasi Sah</span>
                    </div>
                    <!-- Mobile: Badge Ramping & Rapi -->
                    <div class="sm:hidden inline-flex items-center gap-1 px-2 py-1 rounded-md text-[10px] font-bold bg-emerald-50 dark:bg-emerald-950/50 text-emerald-700 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800/80">
                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-600 dark:bg-emerald-400"></span>
                        <span>Sah</span>
                    </div>
                @elseif($verificationStatus === 'expired')
                    <!-- Desktop -->
                    <div class="hidden sm:inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold bg-amber-50 dark:bg-amber-950/40 text-amber-700 dark:text-amber-400 border border-amber-200 dark:border-amber-800/60 shadow-xs">
                        <svg class="w-4 h-4 text-amber-600 dark:text-amber-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <span class="font-bold">Kedaluwarsa</span>
                    </div>
                    <!-- Mobile -->
                    <div class="sm:hidden inline-flex items-center gap-1 px-2 py-1 rounded-md text-[10px] font-bold bg-amber-50 dark:bg-amber-950/50 text-amber-700 dark:text-amber-300 border border-amber-200 dark:border-amber-800/80">
                        <span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span>
                        <span>Kedaluwarsa</span>
                    </div>
                @else
                    <!-- Desktop -->
                    <div class="hidden sm:inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 border border-slate-300 dark:border-slate-700 shadow-xs">
                        <span class="w-2 h-2 rounded-full bg-amber-500"></span>
                        <span class="font-bold">Belum Disahkan</span>
                    </div>
                    <!-- Mobile -->
                    <div class="sm:hidden inline-flex items-center gap-1 px-2 py-1 rounded-md text-[10px] font-bold bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 border border-slate-300 dark:border-slate-700">
                        <span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span>
                        <span>Draf</span>
                    </div>
                @endif
            </div>
        </div>
    </header>

    <!-- Konten Utama Halaman Verifikasi -->
    <main class="flex-1 max-w-5xl w-full mx-auto px-4 sm:px-6 py-6 sm:py-8 space-y-6">

        <!-- KARTU HERO STATUS VERIFIKASI -->
        @if($verificationStatus === 'valid')
            <div class="rounded-2xl p-6 sm:p-8 bg-gradient-to-br from-emerald-500/10 via-white to-blue-500/5 dark:from-emerald-950/40 dark:via-slate-900 dark:to-slate-900 border-2 border-emerald-500/40 dark:border-emerald-500/30 shadow-md">
                <div class="flex flex-col sm:flex-row items-center sm:items-start text-center sm:text-left gap-5 sm:gap-6">
                    <div class="w-16 h-16 sm:w-20 sm:h-20 rounded-2xl bg-emerald-600 text-white flex items-center justify-center shadow-lg shadow-emerald-600/20 shrink-0">
                        <svg class="w-10 h-10 sm:w-12 sm:h-12" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                        </svg>
                    </div>
                    <div class="space-y-1.5 flex-1 min-w-0">
                        <div class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[11px] font-bold uppercase tracking-wider bg-emerald-100 dark:bg-emerald-900/60 text-emerald-800 dark:text-emerald-300">
                            Jaminan Keabsahan Naskah Resmi
                        </div>
                        <h1 class="text-xl sm:text-2xl font-black text-slate-900 dark:text-white tracking-tight">
                            DOKUMEN RESMI TERVERIFIKASI
                        </h1>
                        <p class="text-sm text-slate-600 dark:text-slate-300 leading-relaxed">
                            Naskah dinas ini terdaftar secara sah pada Pangkalan Data e-Arsip Resmi Universitas Bina Insan. Seluruh stempel QR Code dan tanda tangan pejabat di bawah ini diakui secara institusional.
                        </p>
                        <div class="pt-2 flex flex-wrap items-center justify-center sm:justify-start gap-x-4 gap-y-1 text-xs text-slate-500 dark:text-slate-400">
                            <span>Waktu Verifikasi: <strong>{{ now()->translatedFormat('d F Y, H:i') }} WIB</strong></span>
                            <span>•</span>
                            <span>Total Verifikasi: <strong>{{ number_format($document->views_count) }} kali</strong></span>
                        </div>
                    </div>
                </div>
            </div>
        @elseif($verificationStatus === 'expired')
            <div class="rounded-2xl p-6 sm:p-8 bg-gradient-to-br from-amber-500/10 via-white to-amber-500/5 dark:from-amber-950/40 dark:via-slate-900 dark:to-slate-900 border-2 border-amber-500/40 dark:border-amber-500/30 shadow-md">
                <div class="flex flex-col sm:flex-row items-center sm:items-start text-center sm:text-left gap-5 sm:gap-6">
                    <div class="w-16 h-16 sm:w-20 sm:h-20 rounded-2xl bg-amber-600 text-white flex items-center justify-center shadow-lg shadow-amber-600/20 shrink-0">
                        <svg class="w-10 h-10 sm:w-12 sm:h-12" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                        </svg>
                    </div>
                    <div class="space-y-1.5 flex-1 min-w-0">
                        <div class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[11px] font-bold uppercase tracking-wider bg-amber-100 dark:bg-amber-900/60 text-amber-800 dark:text-amber-300">
                            Masa Berlaku Berakhir
                        </div>
                        <h1 class="text-xl sm:text-2xl font-black text-slate-900 dark:text-white tracking-tight">
                            MASA BERLAKU TELAH BERAKHIR (KEDALUWARSA)
                        </h1>
                        <p class="text-sm text-slate-600 dark:text-slate-300 leading-relaxed">
                            Naskah dinas ini sah pernah diterbitkan oleh Universitas Bina Insan, namun masa berlakunya telah berakhir pada <strong>{{ $document->expired_date?->translatedFormat('d F Y') ?? '-' }}</strong>. Rujukan isi naskah ini kini bersifat historis.
                        </p>
                    </div>
                </div>
            </div>
        @else
            <div class="rounded-2xl p-6 sm:p-8 bg-gradient-to-br from-blue-500/10 via-white to-slate-500/5 dark:from-slate-900 dark:via-slate-900 dark:to-slate-950 border-2 border-slate-300 dark:border-slate-700 shadow-md">
                <div class="flex flex-col sm:flex-row items-center sm:items-start text-center sm:text-left gap-5 sm:gap-6">
                    <div class="w-16 h-16 sm:w-20 sm:h-20 rounded-2xl bg-slate-600 text-white flex items-center justify-center shadow-lg shadow-slate-600/20 shrink-0">
                        <svg class="w-10 h-10 sm:w-12 sm:h-12" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    </div>
                    <div class="space-y-1.5 flex-1 min-w-0">
                        <div class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[11px] font-bold uppercase tracking-wider bg-slate-200 dark:bg-slate-800 text-slate-800 dark:text-slate-300">
                            Status: {{ $document->status->getLabel() }}
                        </div>
                        <h1 class="text-xl sm:text-2xl font-black text-slate-900 dark:text-white tracking-tight">
                            DOKUMEN BELUM DISAHKAN RESMI
                        </h1>
                        <p class="text-sm text-slate-600 dark:text-slate-300 leading-relaxed">
                            Naskah ini masih berada dalam proses perancangan internal atau alur telaah berjenjang. Dokumen ini belum disahkan secara definitif dan belum memiliki kekuatan hukum institusi.
                        </p>
                    </div>
                </div>
            </div>
        @endif

        <!-- KARTU IDENTITAS NASKAH DINAS -->
        <div class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200/90 dark:border-slate-800 p-6 sm:p-8 shadow-xs space-y-6 transition-colors">
            <div>
                <span class="text-xs font-bold tracking-wider text-slate-500 dark:text-slate-400 uppercase">
                    Rincian Naskah Dinas Resmi
                </span>
                <h2 class="text-lg sm:text-xl font-bold text-slate-900 dark:text-white mt-1 leading-snug">
                    {{ $document->title }}
                </h2>
            </div>

            <!-- Box Nomor Naskah Resmi -->
            <div class="bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-xl p-4 flex flex-col sm:flex-row sm:items-center justify-between gap-3 transition-colors">
                <div>
                    <span class="text-[11px] font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider block">
                        Nomor Naskah Resmi
                    </span>
                    <span id="docNumberText" class="text-base sm:text-lg font-mono font-bold text-slate-900 dark:text-white tracking-tight break-all">
                        {{ $document->document_number ?: '[BELUM DITERBITKAN]' }}
                    </span>
                </div>
                @if($document->document_number)
                    <button type="button" 
                            onclick="copyToClipboard('docNumberText', 'copyNumBtn')" 
                            id="copyNumBtn"
                            class="inline-flex items-center justify-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold bg-white dark:bg-slate-800 hover:bg-slate-100 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 border border-slate-300 dark:border-slate-700 transition shadow-2xs shrink-0">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 5H6a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2v-1M8 5a2 2 0 002 2h2a2 2 0 002-2M8 5a2 2 0 012-2h2a2 2 0 012 2m0 0h2a2 2 0 012 2v3m2 4H10m0 0l3-3m-3 3l3 3"/>
                        </svg>
                        <span>Salin Nomor</span>
                    </button>
                @endif
            </div>

            <!-- Tabel / Grid Rincian Metadata -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                <!-- Kategori Naskah -->
                <div class="p-4 rounded-xl bg-slate-50 dark:bg-slate-950/60 border border-slate-200/80 dark:border-slate-800 space-y-1">
                    <span class="text-xs text-slate-500 dark:text-slate-400 block font-medium">Kategori Naskah</span>
                    <div class="font-semibold text-slate-900 dark:text-slate-100 flex items-center gap-2">
                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-bold bg-blue-50 dark:bg-blue-950/60 text-blue-700 dark:text-blue-300 border border-blue-200 dark:border-blue-800/80">
                            {{ $document->category?->code ?? '-' }}
                        </span>
                        <span>{{ $document->category?->name ?? '-' }}</span>
                    </div>
                </div>

                <!-- Unit Kerja / Lembaga Penerbit -->
                <div class="p-4 rounded-xl bg-slate-50 dark:bg-slate-950/60 border border-slate-200/80 dark:border-slate-800 space-y-1">
                    <span class="text-xs text-slate-500 dark:text-slate-400 block font-medium">Unit Kerja / Fakultas Penerbit</span>
                    <div class="font-semibold text-slate-900 dark:text-slate-100">
                        {{ $document->unit?->name ?? '-' }}
                        @if($document->unit?->code)
                            <span class="text-xs font-mono text-slate-500 dark:text-slate-400">({{ $document->unit->code }})</span>
                        @endif
                    </div>
                </div>

                <!-- Pejabat Penandatangan / Pengesah -->
                <div class="p-4 rounded-xl bg-slate-50 dark:bg-slate-950/60 border border-slate-200/80 dark:border-slate-800 space-y-1">
                    <span class="text-xs text-slate-500 dark:text-slate-400 block font-medium">Pejabat Penandatangan Resmi</span>
                    <div class="font-bold text-slate-900 dark:text-white">
                        {{ $document->signer_name_snapshot ?? ($document->finalApprover?->name ?? 'Pejabat Berwenang UNIVBI') }}
                    </div>
                    <div class="text-xs text-slate-500 dark:text-slate-400">
                        {{ $document->signer_position_snapshot ?? 'Pimpinan Universitas / Unit Kerja' }}
                    </div>
                </div>

                <!-- Waktu Pengesahan & Penerbitan -->
                <div class="p-4 rounded-xl bg-slate-50 dark:bg-slate-950/60 border border-slate-200/80 dark:border-slate-800 space-y-1">
                    <span class="text-xs text-slate-500 dark:text-slate-400 block font-medium">Tanggal Pengesahan Resmi</span>
                    <div class="font-semibold text-slate-900 dark:text-slate-100">
                        {{ $document->approved_at ? $document->approved_at->translatedFormat('d F Y, H:i') . ' WIB' : ($document->issued_date ? $document->issued_date->translatedFormat('d F Y') : '-') }}
                    </div>
                    @if($document->issued_date && $document->approved_at && $document->issued_date->format('Y-m-d') !== $document->approved_at->format('Y-m-d'))
                        <div class="text-xs text-slate-500 dark:text-slate-400">
                            Tanggal Terbit Naskah: {{ $document->issued_date->translatedFormat('d F Y') }}
                        </div>
                    @endif
                </div>

                <!-- Masa Berlaku Dokumen -->
                <div class="p-4 rounded-xl bg-slate-50 dark:bg-slate-950/60 border border-slate-200/80 dark:border-slate-800 space-y-1">
                    <span class="text-xs text-slate-500 dark:text-slate-400 block font-medium">Masa Berlaku Naskah</span>
                    <div class="font-semibold text-slate-900 dark:text-slate-100">
                        @if($document->has_expiry && $document->expired_date)
                            <span>s.d. {{ $document->expired_date->translatedFormat('d F Y') }}</span>
                            @if($isExpired)
                                <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-bold bg-amber-100 dark:bg-amber-950 text-amber-800 dark:text-amber-300 border border-amber-300 dark:border-amber-800 ml-1">
                                    Kedaluwarsa
                                </span>
                            @else
                                <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-bold bg-emerald-100 dark:bg-emerald-950 text-emerald-800 dark:text-emerald-300 border border-emerald-300 dark:border-emerald-800 ml-1">
                                    Aktif
                                </span>
                            @endif
                        @else
                            <span class="text-emerald-700 dark:text-emerald-400">Berlaku Tetap (Tanpa Batas Waktu)</span>
                        @endif
                    </div>
                </div>

                <!-- Klasifikasi Privasi Naskah -->
                <div class="p-4 rounded-xl bg-slate-50 dark:bg-slate-950/60 border border-slate-200/80 dark:border-slate-800 space-y-1">
                    <span class="text-xs text-slate-500 dark:text-slate-400 block font-medium">Klasifikasi Akses Dokumen</span>
                    <div class="font-semibold text-slate-900 dark:text-slate-100 flex items-center gap-2">
                        @if($document->visibility === \App\Enums\VisibilityLevel::Public)
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-xs font-semibold bg-emerald-50 dark:bg-emerald-950/50 text-emerald-700 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800">
                                <span class="w-1.5 h-1.5 rounded-full bg-emerald-600"></span>
                                Publik (Terbuka Umum)
                            </span>
                        @elseif($document->visibility === \App\Enums\VisibilityLevel::Internal)
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-xs font-semibold bg-blue-50 dark:bg-blue-950/50 text-blue-700 dark:text-blue-300 border border-blue-200 dark:border-blue-800">
                                <span class="w-1.5 h-1.5 rounded-full bg-blue-600"></span>
                                Internal Sivitas Akademika
                            </span>
                        @else
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-xs font-semibold bg-rose-50 dark:bg-rose-950/50 text-rose-700 dark:text-rose-300 border border-rose-200 dark:border-rose-800">
                                <span class="w-1.5 h-1.5 rounded-full bg-rose-600"></span>
                                Rahasia Pimpinan (Confidential)
                            </span>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <!-- KARTU INTEGRITAS KRIPTOGRAFIS (SHA-256) -->
        @php
            $effectiveSha256 = $primaryFile?->getEffectiveSha256Hash();
        @endphp
        @if($primaryFile && $effectiveSha256)
            <div class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200/90 dark:border-slate-800 p-6 sm:p-8 shadow-xs space-y-4 transition-colors">
                <div class="flex items-center justify-between gap-3">
                    <div class="flex items-center gap-2">
                        <svg class="w-5 h-5 text-indigo-600 dark:text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                        </svg>
                        <h3 class="text-sm font-bold text-slate-900 dark:text-white uppercase tracking-wider">
                            Jaminan Integritas Kriptografis Berkas Final
                        </h3>
                    </div>
                    <span class="text-[10px] font-bold uppercase tracking-wider px-2 py-0.5 rounded bg-indigo-50 dark:bg-indigo-950/60 text-indigo-700 dark:text-indigo-300 border border-indigo-200 dark:border-indigo-800">
                        SHA-256 Otentik
                    </span>
                </div>

                <p class="text-xs text-slate-500 dark:text-slate-400 leading-relaxed">
                    Berkas final naskah dinas ini dilindungi oleh hash digital SHA-256. Nilai ini membuktikan bahwa dokumen PDF/berkas resmi yang diunduh belum pernah dimodifikasi atau diubah sejak disahkan dan dibubuhi QR verifikasi.
                </p>

                <!-- Hash Box with Copy -->
                <div class="bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-xl p-3.5 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                    <div class="min-w-0 flex-1">
                        <span class="text-[10px] font-semibold text-slate-400 dark:text-slate-500 uppercase tracking-wider block">
                            {{ filled($primaryFile->stamped_file_hash_sha256) ? 'Fingerprint SHA-256 Checksum (File Final Ber-QR)' : 'Fingerprint SHA-256 Checksum' }}
                        </span>
                        <code id="hashText" class="text-xs font-mono font-bold text-indigo-700 dark:text-indigo-300 break-all select-all block mt-0.5">
                            {{ $effectiveSha256 }}
                        </code>
                    </div>
                    <button type="button" 
                            onclick="copyToClipboard('hashText', 'copyHashBtn')" 
                            id="copyHashBtn"
                            class="inline-flex items-center justify-center gap-1 px-3 py-1.5 rounded-lg text-xs font-semibold bg-white dark:bg-slate-800 hover:bg-slate-100 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 border border-slate-300 dark:border-slate-700 transition shadow-2xs shrink-0">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 5H6a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2v-1M8 5a2 2 0 002 2h2a2 2 0 002-2M8 5a2 2 0 012-2h2a2 2 0 012 2m0 0h2a2 2 0 012 2v3m2 4H10m0 0l3-3m-3 3l3 3"/>
                        </svg>
                        <span>Salin Hash</span>
                    </button>
                </div>

                <!-- Verifikasi Lokal Tanpa Upload ke Server -->
                <div class="pt-1 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                    <label for="verifyLocalFileInput" class="inline-flex items-center justify-center gap-1.5 px-3.5 py-2 rounded-lg text-xs font-semibold bg-indigo-50 dark:bg-indigo-950/60 hover:bg-indigo-100 dark:hover:bg-indigo-900/60 text-indigo-700 dark:text-indigo-300 border border-indigo-200 dark:border-indigo-800 cursor-pointer transition shrink-0">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        <span>Uji Keaslian File di Perangkat Anda</span>
                        <input id="verifyLocalFileInput" type="file" accept=".pdf,.docx,.doc" class="hidden" onchange="verifyLocalFileSha256(event)">
                    </label>
                    <p id="verifyLocalResult" class="text-xs text-slate-500 dark:text-slate-400">
                        Pilih berkas yang Anda miliki untuk mencocokkan sidik jari SHA-256 langsung di browser (tanpa mengunggah file ke server).
                    </p>
                </div>
            </div>
        @endif

        <!-- AREA PRATINJAU LEMBAR NASKAH DINAS -->
        @if($canStreamOrDownload)
            <div class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200/90 dark:border-slate-800 p-6 sm:p-8 shadow-xs space-y-4 transition-colors">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pb-3 border-b border-slate-200 dark:border-slate-800">
                    <div>
                        <h3 class="text-base font-bold text-slate-900 dark:text-white">
                            Pratinjau Lembar Berkas Naskah Dinas Resmi
                        </h3>
                        <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                            Dokumen bertaraf publik ini dapat ditelaah dan diunduh salinannya secara terbuka.
                        </p>
                    </div>

                    <div class="flex items-center gap-2 shrink-0">
                        <a href="{{ route('document.verify.stream', $document->uuid) }}" 
                           target="_blank" 
                           class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 transition">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
                            </svg>
                            <span>Tab Baru</span>
                        </a>

                        <a href="{{ route('document.verify.download', $document->uuid) }}" 
                           class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-lg text-xs font-bold bg-navy-900 hover:bg-blue-700 dark:bg-blue-600 dark:hover:bg-blue-500 text-white transition shadow-sm">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                            </svg>
                            <span>Unduh Salinan (.PDF)</span>
                        </a>
                    </div>
                </div>

                <!-- Iframe Viewer Berkas -->
                <div class="w-full h-[550px] sm:h-[700px] rounded-xl overflow-hidden border border-slate-200 dark:border-slate-800 bg-slate-100 dark:bg-slate-950 relative">
                    <iframe src="{{ route('document.verify.stream', $document->uuid) }}#view=FitH" 
                            class="w-full h-full border-0" 
                            title="Pratinjau Naskah Dinas Resmi">
                    </iframe>
                </div>
            </div>
        @elseif($isApproved && $document->visibility !== \App\Enums\VisibilityLevel::Public)
            <!-- Proteksi Privasi Institusi untuk Dokumen Non-Publik -->
            <div class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200/90 dark:border-slate-800 p-6 sm:p-8 shadow-xs transition-colors">
                <div class="flex items-start gap-4">
                    <div class="w-12 h-12 rounded-xl bg-blue-50 dark:bg-blue-950/60 text-blue-700 dark:text-blue-300 border border-blue-200 dark:border-blue-800/80 flex items-center justify-center shrink-0">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                        </svg>
                    </div>
                    <div class="space-y-1">
                        <h3 class="text-base font-bold text-slate-900 dark:text-white">
                            Konten Naskah Dilindungi Kebijakan Privasi Institusi
                        </h3>
                        <p class="text-sm text-slate-600 dark:text-slate-300 leading-relaxed">
                            Naskah dinas ini berklasifikasi <strong>{{ $document->visibility->getLabel() }}</strong>. Demi kepatuhan terhadap kebijakan perlindungan data sivitas akademika Universitas Bina Insan, berkas fisik naskah tidak dipublikasikan secara terbuka. Laman ini telah memvalidasi bahwa lembar fisik bernomor resmi di atas adalah sah, otentik, dan diterbitkan oleh Universitas Bina Insan.
                        </p>
                    </div>
                </div>
            </div>
        @endif

    </main>

    <!-- Footer Institusional Resmi -->
    <footer class="bg-white dark:bg-slate-900 border-t border-slate-200 dark:border-slate-800 py-6 px-4 sm:px-6 text-center text-xs text-slate-500 dark:text-slate-400 mt-12 transition-colors">
        <div class="max-w-5xl mx-auto space-y-2">
            <p class="font-semibold text-slate-700 dark:text-slate-300">
                Layanan Verifikasi e-Arsip & Naskah Dinas Elektronik • {{ $activeSettings->university_name ?: 'Universitas Bina Insan' }} ({{ $activeSettings->university_acronym ?: 'UNIVBI' }})
            </p>
            <p>
                {{ $activeSettings->campus_address ?: 'Jl. Jenderal Besar Dr. A.H. Nasution No. 23, Kota Lubuklinggau, Sumatera Selatan 31626' }}
            </p>
            <p class="text-[11px] text-slate-400 dark:text-slate-500">
                Untuk pertanyaan atau konfirmasi keabsahan naskah cetak, silakan hubungi Biro Administrasi Akademik (BAAK) atau Lembaga Penjaminan Mutu (LPM) di <a href="mailto:{{ $activeSettings->campus_email ?: 'info@univbi.ac.id' }}" class="text-blue-600 dark:text-blue-400 hover:underline">{{ $activeSettings->campus_email ?: 'info@univbi.ac.id' }}</a>
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

    <!-- Script Interaktif (Dark Mode & Copy Clipboard) -->
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

        function copyToClipboard(elementId, buttonId) {
            const textToCopy = document.getElementById(elementId)?.innerText?.trim();
            if (!textToCopy) return;

            navigator.clipboard.writeText(textToCopy).then(() => {
                const btn = document.getElementById(buttonId);
                if (btn) {
                    const originalHtml = btn.innerHTML;
                    btn.innerHTML = `
                        <svg class="w-3.5 h-3.5 text-emerald-600 dark:text-emerald-400" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                        </svg>
                        <span class="text-emerald-700 dark:text-emerald-300 font-bold">Tersalin!</span>
                    `;
                    setTimeout(() => {
                        btn.innerHTML = originalHtml;
                    }, 2000);
                }
            }).catch(err => {
                console.error('Gagal menyalin:', err);
            });
        }

        async function verifyLocalFileSha256(event) {
            const file = event?.target?.files?.[0];
            const resultEl = document.getElementById('verifyLocalResult');
            const expectedHash = document.getElementById('hashText')?.innerText?.trim()?.toLowerCase();

            if (!file || !resultEl || !expectedHash) {
                return;
            }

            if (!window.crypto?.subtle) {
                resultEl.className = 'text-xs font-medium text-amber-600 dark:text-amber-400';
                resultEl.textContent = 'Browser tidak mendukung Web Crypto API pada koneksi non-HTTPS. Silakan cek checksum secara manual.';
                return;
            }

            resultEl.className = 'text-xs text-slate-500 dark:text-slate-400';
            resultEl.textContent = `Menghitung SHA-256 untuk "${file.name}"...`;

            try {
                const buffer = await file.arrayBuffer();
                const digest = await window.crypto.subtle.digest('SHA-256', buffer);
                const actualHash = Array.from(new Uint8Array(digest))
                    .map((b) => b.toString(16).padStart(2, '0'))
                    .join('');

                if (actualHash === expectedHash) {
                    resultEl.className = 'text-xs font-semibold text-emerald-700 dark:text-emerald-400';
                    resultEl.textContent = `COCOK — Berkas "${file.name}" 100% asli dan identik dengan dokumen sah di server.`;
                } else {
                    resultEl.className = 'text-xs font-semibold text-rose-700 dark:text-rose-400';
                    resultEl.textContent = `TIDAK COCOK — Berkas "${file.name}" berbeda atau pernah dimodifikasi (SHA-256 lokal: ${actualHash.slice(0, 16)}...).`;
                }
            } catch (err) {
                resultEl.className = 'text-xs font-medium text-rose-600 dark:text-rose-400';
                resultEl.textContent = 'Gagal membaca berkas di browser.';
            } finally {
                event.target.value = '';
            }
        }
    </script>
</body>
</html>
