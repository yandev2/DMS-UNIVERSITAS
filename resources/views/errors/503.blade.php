@extends('errors.layout')

@section('title', '503 - Pemeliharaan Sistem')

@section('icon_container_classes', 'bg-sky-50 dark:bg-sky-950/60 border-sky-200 dark:border-sky-800 text-sky-700 dark:text-sky-400')

@section('icon')
    <svg class="w-7 h-7 sm:w-8 sm:h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
    </svg>
@endsection

@section('badge_classes', 'bg-sky-50 dark:bg-sky-950/60 text-sky-900 dark:text-sky-300 border-sky-300 dark:border-sky-800')
@section('badge_dot_classes', 'bg-sky-600 dark:bg-sky-400')
@section('badge_text', 'HTTP 503 • PEMELIHARAAN SISTEM')

@section('heading', 'Sistem Sedang Dalam Pemeliharaan Berkala')

@section('description')
    Pangkalan Data e-Arsip dan Portal Naskah Dinas Terpadu Universitas Bina Insan sedang menjalani peningkatan kapasitas server dan pemeliharaan rutin.
@endsection

@section('diagnostic_title', 'Status Layanan & Perkiraan')

@section('diagnostic_items')
    <li class="flex items-start gap-2.5">
        <svg class="w-4 h-4 text-sky-600 dark:text-sky-400 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" />
        </svg>
        <span>Pemeliharaan dilakukan untuk memastikan stabilitas akses borang akreditasi, verifikasi QR Code, dan keandalan data naskah.</span>
    </li>
    <li class="flex items-start gap-2.5">
        <svg class="w-4 h-4 text-sky-600 dark:text-sky-400 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" />
        </svg>
        <span>Layanan publik akan segera kembali aktif secara normal setelah proses pemeliharaan selesai dilaksanakan.</span>
    </li>
@endsection

@section('actions')
    <button type="button" onclick="window.location.reload()" class="inline-flex items-center gap-2 px-4 py-2.5 rounded-lg text-xs sm:text-sm font-bold bg-sky-700 hover:bg-sky-800 text-white border border-sky-800 shadow-2xs hover:shadow-xs transition-all">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
        </svg>
        <span>Cek Status / Muat Ulang</span>
    </button>
@endsection

