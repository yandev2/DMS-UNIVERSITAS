@extends('errors.layout')

@section('title', '500 - Kendala Teknis Server')

@section('icon_container_classes', 'bg-rose-50 dark:bg-rose-950/60 border-rose-200 dark:border-rose-800 text-rose-700 dark:text-rose-400')

@section('icon')
    <svg class="w-7 h-7 sm:w-8 sm:h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
    </svg>
@endsection

@section('badge_classes', 'bg-rose-50 dark:bg-rose-950/60 text-rose-900 dark:text-rose-300 border-rose-300 dark:border-rose-800')
@section('badge_dot_classes', 'bg-rose-600 dark:bg-rose-400')
@section('badge_text', 'HTTP 500 • KENDALA TEKNIS SERVER')

@section('heading', 'Terjadi Kendala Teknis Sementara pada Server')

@section('description')
    Sistem mendeteksi adanya kendala internal saat memproses permintaan Anda. Seluruh berkas naskah dinas Anda tetap aman, dan tim IT sistem telah menerima catatan anomali ini.
@endsection

@section('diagnostic_title', 'Status Infrastruktur & Tindak Lanjut')

@section('diagnostic_items')
    <li class="flex items-start gap-2.5">
        <svg class="w-4 h-4 text-rose-600 dark:text-rose-400 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" />
        </svg>
        <span>Basis data dan naskah dinas berada dalam penyimpanan terlindungi; tidak ada data yang hilang atau rusak akibat kendala ini.</span>
    </li>
    <li class="flex items-start gap-2.5">
        <svg class="w-4 h-4 text-rose-600 dark:text-rose-400 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" />
        </svg>
        <span>Kendala umumnya bersifat temporer karena kepadatan antrean komputasi atau penyegaran koneksi pangkalan data.</span>
    </li>
    <li class="flex items-start gap-2.5">
        <svg class="w-4 h-4 text-rose-600 dark:text-rose-400 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" />
        </svg>
        <span>Silakan tekan tombol <strong>Muat Ulang Halaman</strong> dalam beberapa saat. Bila berlanjut, hubungi Helpdesk IT Kampus.</span>
    </li>
@endsection

@section('actions')
    <button type="button" onclick="window.location.reload()" class="inline-flex items-center gap-2 px-4 py-2.5 rounded-lg text-xs sm:text-sm font-bold bg-rose-600 hover:bg-rose-700 text-white border border-rose-700 shadow-2xs hover:shadow-xs transition-all">
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

    <button type="button" onclick="window.history.back()" class="inline-flex items-center gap-2 px-3.5 py-2.5 rounded-lg text-xs sm:text-sm font-semibold text-slate-600 dark:text-slate-300 hover:text-slate-900 dark:hover:text-white hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
        </svg>
        <span>Kembali Sebelumnya</span>
    </button>
@endsection

