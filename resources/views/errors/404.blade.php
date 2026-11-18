@extends('errors.layout')

@section('title', '404 - Halaman Tidak Ditemukan')

@section('icon_container_classes', 'bg-blue-50 dark:bg-blue-950/60 border-blue-200 dark:border-blue-800 text-blue-700 dark:text-blue-400')

@section('icon')
    <svg class="w-7 h-7 sm:w-8 sm:h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
    </svg>
@endsection

@section('badge_classes', 'bg-blue-50 dark:bg-blue-950/60 text-blue-800 dark:text-blue-300 border-blue-200 dark:border-blue-800')
@section('badge_dot_classes', 'bg-blue-600 dark:bg-blue-400')
@section('badge_text', 'HTTP 404 • TIDAK DITEMUKAN')

@section('heading', 'Halaman atau Naskah Dinas Tidak Ditemukan')

@section('description')
    Tautan web yang Anda tuju tidak terdaftar pada pangkalan data, telah dipindahkan ke klasifikasi arsip lain, atau nomor naskah yang Anda cari tidak tersedia untuk akses publik.
@endsection

@section('diagnostic_title', 'Petunjuk Penelusuran & Verifikasi')

@section('diagnostic_items')
    <li class="flex items-start gap-2.5">
        <svg class="w-4 h-4 text-blue-600 dark:text-blue-400 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" />
        </svg>
        <span>Periksa kembali penulisan alamat URL pada bilah peramban Anda untuk memastikan tidak ada karakter yang salah ketik.</span>
    </li>
    <li class="flex items-start gap-2.5">
        <svg class="w-4 h-4 text-blue-600 dark:text-blue-400 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" />
        </svg>
        <span>Jika Anda mencari naskah dinas bertaraf publik, gunakan kolom pencarian dan filter kategori di Beranda e-Arsip.</span>
    </li>
    <li class="flex items-start gap-2.5">
        <svg class="w-4 h-4 text-blue-600 dark:text-blue-400 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" />
        </svg>
        <span>Bila tautan ini berasal dari dokumen cetak ber-QR Code, naskah mungkin telah dicabut, direvisi, atau memerlukan verifikasi staf BAAK.</span>
    </li>
@endsection

@section('actions')
    <a href="{{ url('/') }}" class="inline-flex items-center gap-2 px-4 py-2.5 rounded-lg text-xs sm:text-sm font-bold bg-blue-700 hover:bg-blue-800 text-white border border-blue-800 shadow-2xs hover:shadow-xs transition-all">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
        </svg>
        <span>Beranda Utama</span>
    </a>

    <a href="{{ url('/#dokumen-publik') }}" class="inline-flex items-center gap-2 px-4 py-2.5 rounded-lg text-xs sm:text-sm font-semibold bg-white dark:bg-slate-800 hover:bg-slate-50 dark:hover:bg-slate-700 text-slate-800 dark:text-slate-100 border border-slate-300 dark:border-slate-700 shadow-2xs transition-colors">
        <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
        </svg>
        <span>Cari Dokumen Publik</span>
    </a>

    <button type="button" onclick="window.history.back()" class="inline-flex items-center gap-2 px-3.5 py-2.5 rounded-lg text-xs sm:text-sm font-semibold text-slate-600 dark:text-slate-300 hover:text-slate-900 dark:hover:text-white hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
        </svg>
        <span>Kembali Sebelumnya</span>
    </button>
@endsection

