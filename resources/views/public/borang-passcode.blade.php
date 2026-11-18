<!DOCTYPE html>
<html lang="id" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Otentikasi Sandi Asesor | Universitas Bina Insan</title>
    
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
    <div class="max-w-md w-full bg-white dark:bg-slate-900 border border-slate-200/90 dark:border-slate-800 rounded-2xl p-6 sm:p-8 shadow-sm">
        <!-- University Security Icon -->
        <div class="w-14 h-14 rounded-xl bg-blue-50 dark:bg-blue-950/50 border border-blue-200 dark:border-blue-800/60 text-blue-700 dark:text-blue-400 flex items-center justify-center mx-auto mb-4 shadow-xs">
            <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/>
            </svg>
        </div>

        <div class="text-center mb-6">
            <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold tracking-wider uppercase bg-blue-50 dark:bg-blue-950/60 text-blue-700 dark:text-blue-300 border border-blue-200 dark:border-blue-800 mb-2">
                Verifikasi Akses Asesor
            </span>
            <h1 class="text-xl font-bold text-slate-900 dark:text-white mb-1.5">
                Masukkan PIN / Sandi Akses
            </h1>
            <p class="text-xs text-slate-600 dark:text-slate-400 leading-relaxed">
                Dokumen bukti akreditasi Program Studi <strong class="text-slate-900 dark:text-slate-200 font-semibold">{{ $unit?->name }}</strong> dilindungi kode akses. Silakan masukkan PIN resmi dari lembar pengantar asesmen.
            </p>
        </div>

        @if($errors->has('passcode'))
            <div class="mb-4 p-3 bg-red-50 dark:bg-red-950/50 border border-red-200 dark:border-red-800 rounded-lg text-xs text-red-700 dark:text-red-300 text-center font-medium">
                {{ $errors->first('passcode') }}
            </div>
        @endif

        <form method="POST" action="{{ $postAction ?? (isset($document) ? route('borang.public.show', $document->uuid) : url()->current()) }}" class="space-y-4">
            @csrf
            <div>
                <label for="passcode" class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1.5">PIN Akses Asesor Eksternal</label>
                <input type="password" 
                       id="passcode" 
                       name="passcode" 
                       required 
                       autofocus
                       placeholder="Masukkan PIN / Sandi..."
                       class="w-full px-3.5 py-2.5 rounded-lg bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-700 text-slate-900 dark:text-white font-mono text-sm placeholder:text-slate-400 dark:placeholder:text-slate-600 focus:outline-none focus:ring-2 focus:ring-blue-600 focus:border-transparent transition">
            </div>

            <button type="submit" 
                    class="w-full py-2.5 rounded-lg bg-navy-900 dark:bg-blue-600 hover:bg-blue-700 dark:hover:bg-blue-500 text-white text-xs font-bold transition shadow-sm">
                Buka Dokumen Bukti
            </button>
        </form>

        <div class="mt-6 pt-4 border-t border-slate-100 dark:border-slate-800 text-[11px] text-slate-500 dark:text-slate-400 text-center">
            &copy; {{ date('Y') }} Lembaga Penjaminan Mutu &bull; Universitas Bina Insan (UNIVBI)
        </div>
    </div>
</body>
</html>
