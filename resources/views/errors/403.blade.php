@extends('errors.layout')

@section('title', '403 - Akses Dibatasi')

@section('icon_container_classes', 'bg-amber-50 dark:bg-amber-950/60 border-amber-200 dark:border-amber-800 text-amber-700 dark:text-amber-400')

@section('icon')
    <svg class="w-7 h-7 sm:w-8 sm:h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
    </svg>
@endsection

@section('badge_classes', 'bg-amber-50 dark:bg-amber-950/60 text-amber-900 dark:text-amber-300 border-amber-300 dark:border-amber-800')
@section('badge_dot_classes', 'bg-amber-600 dark:bg-amber-400')
@section('badge_text', 'HTTP 403 • AKSES DITOLAK')

@section('heading', 'Akses Dibatasi atau Memerlukan Hak Akses Khusus')

@section('description')
    Sistem mendeteksi bahwa peramban Anda tidak memiliki izin atau kredensial yang memadai untuk membuka halaman atau naskah dinas ini.
@endsection

@section('additional_alert')
    @if(isset($exception) && $exception->getMessage())
        <div class="bg-amber-50/80 dark:bg-amber-950/40 border border-amber-300 dark:border-amber-800/80 rounded-xl p-3.5 flex items-start gap-3">
            <svg class="w-5 h-5 text-amber-700 dark:text-amber-400 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <div class="text-xs sm:text-sm font-medium text-amber-900 dark:text-amber-200">
                <span class="font-bold">Keterangan Otorisasi:</span> {{ $exception->getMessage() }}
            </div>
        </div>
    @endif
@endsection

@section('diagnostic_title', 'Panduan Otorisasi & Akses')

@section('diagnostic_items')
    <li class="flex items-start gap-2.5">
        <svg class="w-4 h-4 text-amber-600 dark:text-amber-400 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" />
        </svg>
        <span>Naskah atau tautan yang Anda tuju merupakan dokumen berkategori internal unit kerja, pimpinan universitas, atau auditor akreditasi.</span>
    </li>
    <li class="flex items-start gap-2.5">
        <svg class="w-4 h-4 text-amber-600 dark:text-amber-400 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" />
        </svg>
        <span>Jika Anda adalah staf pengelola atau pimpinan yang berhak, pastikan Anda telah masuk (login) ke Portal Staf / Admin dengan akun yang sesuai.</span>
    </li>
    <li class="flex items-start gap-2.5">
        <svg class="w-4 h-4 text-amber-600 dark:text-amber-400 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" />
        </svg>
        <span>Hubungi BAAK atau Administrator Sistem jika unit kerja Anda memerlukan penyesuaian hak akses terhadap naskah dinas ini.</span>
    </li>
@endsection

@section('actions')
    <a href="{{ url('/admin/login') }}" class="inline-flex items-center gap-2 px-4 py-2.5 rounded-lg text-xs sm:text-sm font-bold bg-amber-600 hover:bg-amber-700 text-white border border-amber-700 shadow-2xs hover:shadow-xs transition-all">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1" />
        </svg>
        <span>Masuk Portal Staf / Admin</span>
    </a>

    <a href="{{ url('/') }}" class="inline-flex items-center gap-2 px-4 py-2.5 rounded-lg text-xs sm:text-sm font-semibold bg-white dark:bg-slate-800 hover:bg-slate-50 dark:hover:bg-slate-700 text-slate-800 dark:text-slate-100 border border-slate-300 dark:border-slate-700 shadow-2xs transition-colors">
        <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
        </svg>
        <span>Beranda Utama</span>
    </a>

    <button type="button" onclick="window.history.back()" class="inline-flex items-center gap-2 px-3.5 py-2.5 rounded-lg text-xs sm:text-sm font-semibold text-slate-600 dark:text-slate-300 hover:text-slate-900 dark:hover:text-white hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
        </svg>
        <span>Kembali Sebelumnya</span>
    </button>
@endsection

