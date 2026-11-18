@extends('errors.layout')

@section('title', '419 - Sesi Formulir Berakhir')

@section('icon_container_classes', 'bg-orange-50 dark:bg-orange-950/60 border-orange-200 dark:border-orange-800 text-orange-700 dark:text-orange-400')

@section('icon')
    <svg class="w-7 h-7 sm:w-8 sm:h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
    </svg>
@endsection

@section('badge_classes', 'bg-orange-50 dark:bg-orange-950/60 text-orange-900 dark:text-orange-300 border-orange-300 dark:border-orange-800')
@section('badge_dot_classes', 'bg-orange-600 dark:bg-orange-400')
@section('badge_text', 'HTTP 419 • SESI KEDALUWARSA')

@section('heading', 'Sesi Keamanan Formulir Telah Berakhir')

@section('description')
    Demi menjaga integritas dan perlindungan data terhadap serangan pemalsuan permintaan antar-situs (CSRF), token sesi formulir Anda telah berakhir karena tidak ada interaksi dalam durasi tertentu.
@endsection

@section('diagnostic_title', 'Panduan Pembaruan Sesi')

@section('diagnostic_items')
    <li class="flex items-start gap-2.5">
        <svg class="w-4 h-4 text-orange-600 dark:text-orange-400 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" />
        </svg>
        <span>Ini adalah mekanisme keamanan standar perbankan/akademik untuk mencegah pihak ketiga menyalahgunakan sesi login Anda saat peramban ditinggal.</span>
    </li>
    <li class="flex items-start gap-2.5">
        <svg class="w-4 h-4 text-orange-600 dark:text-orange-400 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" />
        </svg>
        <span>Silakan muat ulang halaman untuk memperbarui token keamanan sistem secara otomatis.</span>
    </li>
@endsection

@section('actions')
    <button type="button" onclick="window.location.reload()" class="inline-flex items-center gap-2 px-4 py-2.5 rounded-lg text-xs sm:text-sm font-bold bg-orange-600 hover:bg-orange-700 text-white border border-orange-700 shadow-2xs hover:shadow-xs transition-all">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
        </svg>
        <span>Muat Ulang Halaman</span>
    </button>

    <a href="{{ url('/') }}" class="inline-flex items-center gap-2 px-4 py-2.5 rounded-lg text-xs sm:text-sm font-semibold bg-white dark:bg-slate-800 hover:bg-slate-50 dark:hover:bg-slate-700 text-slate-800 dark:text-slate-100 border border-slate-300 dark:border-slate-700 shadow-2xs transition-colors">
        <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
        </svg>
        <span>Beranda Utama</span>
    </a>
@endsection

