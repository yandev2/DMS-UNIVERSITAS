<!DOCTYPE html>
<html lang="id" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $document->document_number }} - Bukti Borang Akreditasi | {{ ($settings ?? $siteSettings ?? \App\Models\LandingPageSetting::current())->university_name }}</title>
    
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
        // Apply theme immediately to prevent flashing
        if (localStorage.theme === 'dark' || (!('theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            document.documentElement.classList.add('dark');
        } else {
            document.documentElement.classList.remove('dark');
        }
    </script>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        /* Custom clean scrollbar for both light & dark mode */
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

        $stampedPath = $document->primaryFile?->stamped_file_path;
        $originalPath = $document->primaryFile?->original_file_path;
        $fileName = basename($stampedPath ?? $originalPath ?? 'dokumen-akreditasi.pdf');
    @endphp

    <!-- Institutional Header -->
    <header class="bg-white dark:bg-slate-900 border-b border-slate-200 dark:border-slate-800 shrink-0 sticky top-0 z-30 shadow-xs transition-colors">
        <div class="max-w-[1920px] mx-auto px-4 sm:px-6 py-2.5 flex items-center justify-between gap-4">
            <!-- Left Branding -->
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
                            Portal Akses Asesor
                        </span>
                        <span class="hidden sm:inline-block text-slate-300 dark:text-slate-600 font-light">|</span>
                        <span class="hidden sm:inline-block text-xs font-semibold text-slate-500 dark:text-slate-400">
                            Instrumen BAN-PT / LAM
                        </span>
                    </div>
                    <div class="text-sm font-bold text-slate-900 dark:text-white truncate">
                        {{ $activeSettings->university_name ?: 'Universitas Bina Insan' }} ({{ $activeSettings->university_acronym ?: 'UNIVBI' }})
                    </div>
                </div>
            </div>

            <!-- Right Verification & Actions -->
            <div class="flex items-center gap-2 sm:gap-3 shrink-0">
                <!-- Dark / Light Mode Toggle Button -->
                <button type="button" 
                        id="themeToggleBtn"
                        onclick="toggleTheme()" 
                        class="p-2 rounded-lg text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-800 transition"
                        title="Beralih Tema Gelap / Terang">
                    <!-- Sun Icon (visible in dark mode) -->
                    <svg class="w-4 h-4 hidden dark:block" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"/>
                    </svg>
                    <!-- Moon Icon (visible in light mode) -->
                    <svg class="w-4 h-4 block dark:hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"/>
                    </svg>
                </button>

                <!-- Verified Badge -->
                <div class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold bg-emerald-50 dark:bg-emerald-950/40 text-emerald-700 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-800/60">
                    <svg class="w-4 h-4 text-emerald-600 dark:text-emerald-400 shrink-0" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                    </svg>
                    <span class="hidden md:inline">Dokumen Sah & Terverifikasi</span>
                    <span class="md:hidden">Sah</span>
                </div>

                <!-- Download PDF Button -->
                <a href="{{ route('borang.public.download', $document->uuid) }}" 
                   class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-lg text-xs font-bold bg-navy-900 hover:bg-blue-700 dark:bg-blue-600 dark:hover:bg-blue-500 text-white transition-colors shadow-sm">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                    </svg>
                    <span class="hidden sm:inline">Unduh Berkas (.PDF)</span>
                    <span class="sm:hidden">Unduh</span>
                </a>
            </div>
        </div>
    </header>

    <!-- Main Workspace (Full-height split layout on desktop) -->
    <main class="flex-1 flex flex-col lg:flex-row min-h-0 overflow-hidden bg-slate-100 dark:bg-slate-950 transition-colors">
        
        <!-- Left Sidebar: Document Identity & Accreditation Context (Fixed width, independent smooth scroll) -->
        <aside class="w-full lg:w-[420px] xl:w-[460px] shrink-0 h-auto lg:h-full overflow-y-auto bg-slate-50 dark:bg-slate-900/60 border-r border-slate-200 dark:border-slate-800 p-4 sm:p-5 lg:p-6 space-y-4 custom-scrollbar">
            
            <!-- Card 1: Identitas Naskah Dinas -->
            <div class="bg-white dark:bg-slate-900 rounded-xl border border-slate-200/80 dark:border-slate-800 p-5 shadow-xs transition-colors">
                <div class="flex items-center justify-between gap-2 mb-2">
                    <span class="text-[11px] font-bold tracking-wider text-slate-500 dark:text-slate-400 uppercase">
                        Identitas Berkas Bukti
                    </span>
                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[10px] font-semibold bg-blue-50 dark:bg-blue-950/60 text-blue-700 dark:text-blue-300 border border-blue-100 dark:border-blue-900/80">
                        <span class="w-1.5 h-1.5 rounded-full bg-blue-600 dark:bg-blue-400"></span>
                        Asli
                    </span>
                </div>

                <!-- Document Full Title -->
                <h1 class="text-base font-bold text-slate-900 dark:text-white leading-snug mb-4">
                    {{ $document->title }}
                </h1>

                <!-- Document Number Box with 1-Click Copy -->
                <div class="bg-slate-50 dark:bg-slate-950 border border-slate-200/90 dark:border-slate-800 rounded-lg p-3 mb-4 transition-colors">
                    <div class="flex items-center justify-between mb-1">
                        <span class="text-[11px] font-semibold text-slate-500 dark:text-slate-400">Nomor Naskah Resmi:</span>
                        <button type="button" 
                                id="btnCopyDocNumber"
                                onclick="copyDocNumber('{{ $document->document_number }}')"
                                class="inline-flex items-center gap-1 text-[11px] font-semibold text-blue-600 dark:text-blue-400 hover:text-blue-800 dark:hover:text-blue-300 transition">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                            </svg>
                            <span id="copyDocNumberText">Salin Nomor</span>
                        </button>
                    </div>
                    <div class="font-mono text-xs sm:text-sm font-bold text-slate-800 dark:text-slate-100 break-all select-all">
                        {{ $document->document_number }}
                    </div>
                </div>

                <!-- Metadata Key-Value Grid -->
                <div class="grid grid-cols-2 gap-3 text-xs pt-1 border-t border-slate-100 dark:border-slate-800/80">
                    <div>
                        <div class="text-slate-500 dark:text-slate-400 font-medium">Unit / Program Studi</div>
                        <div class="font-semibold text-slate-800 dark:text-slate-200 mt-0.5">{{ $document->unit?->name ?? '-' }}</div>
                    </div>
                    <div>
                        <div class="text-slate-500 dark:text-slate-400 font-medium">Tahun Akademik</div>
                        <div class="font-semibold text-slate-800 dark:text-slate-200 mt-0.5">{{ $document->academicYear?->name ?? '-' }}</div>
                    </div>
                    <div class="pt-2 border-t border-slate-100 dark:border-slate-800/80">
                        <div class="text-slate-500 dark:text-slate-400 font-medium">Tanggal Ditetapkan</div>
                        <div class="font-semibold text-slate-800 dark:text-slate-200 mt-0.5">{{ $document->issued_date?->translatedFormat('d F Y') ?? '-' }}</div>
                    </div>
                    <div class="pt-2 border-t border-slate-100 dark:border-slate-800/80">
                        <div class="text-slate-500 dark:text-slate-400 font-medium">Pejabat Pengesah</div>
                        <div class="font-semibold text-slate-800 dark:text-slate-200 mt-0.5">{{ $document->signer_name_snapshot ?? '-' }}</div>
                    </div>
                </div>
            </div>

            <!-- Card 2: Pemetaan Kriteria Akreditasi (BAN-PT / LAM) -->
            <div class="bg-white dark:bg-slate-900 rounded-xl border border-slate-200/80 dark:border-slate-800 p-5 shadow-xs transition-colors">
                <div class="flex items-center justify-between gap-2 mb-3">
                    <div class="text-[11px] font-bold tracking-wider text-slate-500 dark:text-slate-400 uppercase">
                        Pemetaan Kriteria Borang BAN-PT/LAM
                    </div>
                    <span class="text-[11px] font-bold px-2 py-0.5 rounded bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300">
                        {{ $document->accreditationCriteria->count() }} Kriteria
                    </span>
                </div>

                @if($document->accreditationCriteria->isNotEmpty())
                    <div class="space-y-2.5">
                        @foreach($document->accreditationCriteria as $criterion)
                            @php
                                $color = $criterionColors[$criterion->code] ?? ['bg' => 'bg-blue-600 dark:bg-blue-500', 'badge' => 'bg-blue-50 text-blue-700 border-blue-200'];
                            @endphp
                            <div class="p-3 rounded-lg border border-slate-200 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-950/40 hover:bg-slate-50 dark:hover:bg-slate-800/40 transition">
                                <div class="flex items-center justify-between gap-2 mb-1.5">
                                    <div class="flex items-center gap-2">
                                        <span class="inline-flex items-center justify-center px-2 py-0.5 rounded text-xs font-bold text-white {{ $color['bg'] }} shadow-xs">
                                            {{ $criterion->code }}
                                        </span>
                                        <span class="text-xs font-bold text-slate-900 dark:text-white">
                                            {{ $criterion->name }}
                                        </span>
                                    </div>
                                    @if($criterion->pivot?->indicator_code)
                                        <span class="font-mono text-[11px] font-semibold px-2 py-0.5 rounded bg-white dark:bg-slate-900 text-slate-700 dark:text-slate-300 border border-slate-200 dark:border-slate-700 shrink-0">
                                            {{ $criterion->pivot->indicator_code }}
                                        </span>
                                    @endif
                                </div>

                                @if($criterion->pivot?->note)
                                    <div class="text-[11px] text-slate-600 dark:text-slate-400 bg-white dark:bg-slate-900 p-2 rounded border border-slate-100 dark:border-slate-800 mt-2 flex items-start gap-1.5">
                                        <svg class="w-3.5 h-3.5 text-slate-400 dark:text-slate-500 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 8h10M7 12h4m1 8l-4-4H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-3l-4 4z"/>
                                        </svg>
                                        <div>
                                            <span class="font-semibold text-slate-700 dark:text-slate-300">Catatan Bukti:</span> {{ $criterion->pivot->note }}
                                        </div>
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="text-xs text-slate-500 dark:text-slate-400 italic bg-slate-50 dark:bg-slate-950 p-3 rounded-lg border border-slate-200 dark:border-slate-800">
                        Dokumen ini bertanda bukti borang umum institusi.
                    </div>
                @endif
            </div>

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
                        Naskah dinas ini merupakan salinan digital resmi yang telah divalidasi oleh Lembaga Penjaminan Mutu (LPM) Universitas Bina Insan, dilengkapi stempel digital otomatis dan QR Code penelusuran arsip resmi.
                    </div>
                </div>
            </div>

            <!-- Helpdesk Contact Info -->
            <div class="text-[11px] text-slate-500 dark:text-slate-400 text-center pt-2 pb-4">
                Lembaga Penjaminan Mutu (LPM) &bull; Gedung Rektorat Lt. 2 {{ $activeSettings->university_acronym ?: 'UNIVBI' }}<br>
                Bantuan Teknis: <a href="mailto:{{ $activeSettings->campus_email ?: 'lpm@univbi.ac.id' }}" class="text-blue-600 dark:text-blue-400 font-medium hover:underline">{{ $activeSettings->campus_email ?: 'lpm@univbi.ac.id' }}</a>
            </div>
        </aside>

        <!-- Right PDF Viewer Canvas (Full flex-1, seamless viewing) -->
        <section id="viewerContainer" class="flex-1 flex flex-col min-w-0 min-h-0 h-full relative overflow-hidden bg-slate-200/60 dark:bg-slate-950 transition-colors">
            <!-- Viewer Top Bar (Slim & Airy) -->
            <div class="h-12 shrink-0 bg-white dark:bg-slate-900 border-b border-slate-200 dark:border-slate-800 px-4 sm:px-5 flex items-center justify-between gap-3 text-xs shadow-xs transition-colors">
                <div class="flex items-center gap-2 sm:gap-2.5 min-w-0">
                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded font-bold text-[11px] bg-red-50 dark:bg-red-950/50 text-red-700 dark:text-red-400 border border-red-200 dark:border-red-900/60 shrink-0">
                        <svg class="w-3.5 h-3.5 text-red-500" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4zm2 6a1 1 0 011-1h6a1 1 0 110 2H7a1 1 0 01-1-1zm1 3a1 1 0 100 2h6a1 1 0 100-2H7z" clip-rule="evenodd"/>
                        </svg>
                        PDF
                    </span>

                    <span class="font-mono font-bold text-slate-800 dark:text-slate-100 text-xs sm:text-sm select-all shrink-0">
                        {{ $document->document_number }}
                    </span>

                    <span class="text-slate-300 dark:text-slate-600 hidden sm:inline">&bull;</span>

                    <span class="font-mono text-slate-500 dark:text-slate-400 truncate hidden sm:inline text-xs" title="{{ $fileName }}">
                        {{ $fileName }}
                    </span>
                </div>

                <div class="flex items-center gap-1.5 sm:gap-2 shrink-0">
                    <!-- Open in New Tab -->
                    <a href="{{ route('borang.public.stream', $document->uuid) }}" 
                       target="_blank"
                       rel="noopener"
                       class="inline-flex items-center gap-1 px-2.5 py-1 rounded text-xs font-semibold text-slate-700 dark:text-slate-300 bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 transition">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
                        </svg>
                        <span class="hidden sm:inline">Tab Baru</span>
                    </a>

                    <!-- Toggle Fullscreen -->
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
                <iframe src="{{ route('borang.public.stream', $document->uuid) }}#view=FitH" 
                        class="w-full h-full border-0 absolute inset-0 block bg-white"
                        title="Pratinjau Naskah Dinas PDF">
                </iframe>
            </div>

            <!-- Mobile Fallback Helper -->
            <div class="lg:hidden bg-white dark:bg-slate-900 border-t border-slate-200 dark:border-slate-800 p-2 text-center text-xs text-slate-600 dark:text-slate-400">
                Pratinjau PDF tidak tampil di ponsel? 
                <a href="{{ route('borang.public.stream', $document->uuid) }}" target="_blank" class="text-blue-600 dark:text-blue-400 font-bold underline ml-1">
                    Buka PDF Langsung
                </a>
            </div>
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

        function copyDocNumber(text) {
            const btnText = document.getElementById('copyDocNumberText');
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
    </script>
</body>
</html>
