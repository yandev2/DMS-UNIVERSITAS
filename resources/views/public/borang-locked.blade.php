<!DOCTYPE html>
<html lang="id" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Akses Ditutup - Masa Asesmen Akreditasi | Universitas Bina Insan</title>
    
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
<body class="h-full flex flex-col items-center justify-center font-sans antialiased text-slate-800 dark:text-slate-100 bg-slate-100 dark:bg-slate-950 p-4 sm:p-6 selection:bg-blue-100 selection:text-blue-900 transition-colors">
    <div class="max-w-md w-full bg-white dark:bg-slate-900 border border-slate-200/90 dark:border-slate-800 rounded-2xl p-6 sm:p-8 shadow-sm text-center">
        <!-- University Crest / Mark -->
        <div class="w-14 h-14 rounded-xl bg-amber-50 dark:bg-amber-950/50 border border-amber-200 dark:border-amber-800/60 text-amber-600 dark:text-amber-400 flex items-center justify-center mx-auto mb-5 shadow-xs">
            <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
            </svg>
        </div>

        <div class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded text-[11px] font-bold tracking-wider uppercase bg-amber-50 dark:bg-amber-950/60 text-amber-800 dark:text-amber-300 border border-amber-200 dark:border-amber-800 mb-3">
            Pemberitahuan Sistem
        </div>
        
        <h1 class="text-xl font-bold text-slate-900 dark:text-white mb-2">
            Masa Asesmen Tidak Aktif
        </h1>
        
        <p class="text-xs text-slate-600 dark:text-slate-400 leading-relaxed mb-6">
            Tautan akses publik terhadap dokumen bukti borang ini saat ini sedang dinonaktifkan atau berada di luar jadwal asesmen resmi Program Studi 
            <strong class="text-slate-900 dark:text-slate-200 font-semibold">{{ $unit?->name ?? 'Terkait' }}</strong>.
        </p>

        @if($unit?->assessor_notes)
            <div class="p-3.5 bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-lg text-xs text-slate-700 dark:text-slate-300 text-left mb-6 italic leading-relaxed">
                "{{ $unit->assessor_notes }}"
            </div>
        @endif

        <div class="border-t border-slate-100 dark:border-slate-800 pt-5 text-xs text-slate-500 dark:text-slate-400 space-y-1">
            <div class="font-semibold text-slate-800 dark:text-slate-200">Butuh Informasi Pembukaan Akses?</div>
            <div>Silakan hubungi Lembaga Penjaminan Mutu (LPM) Universitas Bina Insan:</div>
            <div class="pt-1">
                <a href="mailto:lpm@univbi.ac.id" class="font-mono text-xs font-bold text-blue-600 dark:text-blue-400 hover:text-blue-800 dark:hover:text-blue-300 hover:underline">
                    lpm@univbi.ac.id
                </a>
            </div>
        </div>

        <div class="mt-6 pt-4 border-t border-slate-100 dark:border-slate-800 text-[11px] text-slate-500 dark:text-slate-400">
            &copy; {{ date('Y') }} Lembaga Penjaminan Mutu &bull; Universitas Bina Insan (UNIVBI)
        </div>
    </div>
</body>
</html>
