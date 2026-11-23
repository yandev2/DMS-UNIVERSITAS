<!DOCTYPE html>
<html lang="id" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $post->title }} | {{ ($settings ?? $siteSettings ?? \App\Models\LandingPageSetting::current())->university_name }}</title>
    <meta name="description" content="{{ $post->excerpt ?: Str::limit(strip_tags($post->content), 150) }}">

    @php
        $activeSettings = $settings ?? $siteSettings ?? \App\Models\LandingPageSetting::current();
        $logoUrl = $activeSettings->getLogoUrl();
        $shareUrl = $post->getPublicUrl();
        $shareText = $post->title.' - '.$shareUrl;
        $plainContent = trim(preg_replace('/\s+/', ' ', strip_tags((string) $post->content)) ?? '');
        $readingMinutes = max(1, (int) ceil(str_word_count($plainContent) / 180));
        $authorName = $post->author?->name ?? 'Tim Humas UNIVBI';
        $authorInitial = mb_strtoupper(mb_substr(trim($authorName), 0, 1)) ?: 'H';
        $authorAvatarUrl = $post->author?->getAvatarUrl();
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
    <style>
        .article-hero-title {
            font-size: clamp(1.5rem, 1.15rem + 1.6vw, 2.25rem);
        }
        .prose-content {
            font-size: 1.03rem;
            line-height: 1.8;
            overflow-wrap: anywhere;
        }
        .prose-content p {
            margin-bottom: 1.25rem;
        }
        .prose-content h2 {
            font-size: 1.4rem;
            font-weight: 700;
            margin-top: 2rem;
            margin-bottom: 0.75rem;
            color: #0f172a;
        }
        .dark .prose-content h2 {
            color: #f8fafc;
        }
        .prose-content h3 {
            font-size: 1.18rem;
            font-weight: 600;
            margin-top: 1.5rem;
            margin-bottom: 0.5rem;
            color: #1e293b;
        }
        .dark .prose-content h3 {
            color: #e2e8f0;
        }
        .prose-content ul, .prose-content ol {
            margin-bottom: 1.25rem;
            padding-left: 1.5rem;
        }
        .prose-content ul {
            list-style-type: disc;
        }
        .prose-content ol {
            list-style-type: decimal;
        }
        .prose-content li {
            margin-bottom: 0.5rem;
        }
        .prose-content blockquote {
            border-left: 4px solid #3b82f6;
            padding-left: 1rem;
            font-style: italic;
            color: #475569;
            margin: 1.5rem 0;
        }
        .dark .prose-content blockquote {
            color: #94a3b8;
        }
        .prose-content a {
            color: #2563eb;
            text-decoration: underline;
        }
        .dark .prose-content a {
            color: #60a5fa;
        }
        .prose-content img {
            max-width: 100%;
            height: auto;
            border-radius: 0.75rem;
        }
        .related-card-title {
            display: -webkit-box;
            -webkit-line-clamp: 3;
            -webkit-box-orient: vertical;
            line-clamp: 3;
            overflow: clip;
        }
    </style>
</head>
<body class="min-h-full flex flex-col font-sans antialiased text-slate-800 dark:text-slate-100 bg-slate-50 dark:bg-slate-950 selection:bg-blue-100 selection:text-blue-900 transition-colors duration-200">

    <!-- Header Institusi Resmi (Seragam & Terstruktur) -->
    @include('public.partials.header')

    <!-- Main Container: Lebar Penuh 2 Kolom Editorial Responsif -->
    <main class="flex-1 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 sm:py-8 w-full">
        <!-- Breadcrumb Navigation -->
        <nav aria-label="Breadcrumb" class="flex flex-wrap items-center gap-x-2 gap-y-1.5 text-xs text-slate-500 dark:text-slate-400 mb-6 leading-relaxed">
            <a href="{{ route('landing.home') }}" class="inline-flex items-center gap-1 shrink-0 whitespace-nowrap hover:text-blue-600 dark:hover:text-blue-400 font-medium transition-colors">
                <svg class="w-3.5 h-3.5 text-slate-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                </svg>
                <span>Beranda</span>
            </a>
            <span class="text-slate-300 dark:text-slate-600 shrink-0">/</span>
            <a href="{{ route('public.posts.index') }}" class="shrink-0 whitespace-nowrap hover:text-blue-600 dark:hover:text-blue-400 font-medium transition-colors">
                Warta & Liputan
            </a>
            @if($post->category)
                <span class="text-slate-300 dark:text-slate-600 shrink-0">/</span>
                <a href="{{ route('public.posts.index', ['kategori' => $post->category->slug]) }}" class="shrink-0 whitespace-nowrap hover:text-blue-600 dark:hover:text-blue-400 font-medium transition-colors">
                    {{ $post->category->name }}
                </a>
            @endif
            <span class="text-slate-300 dark:text-slate-600 shrink-0">/</span>
            <span class="text-slate-800 dark:text-slate-200 font-semibold break-words">
                {{ $post->title }}
            </span>
        </nav>

        <!-- Layout 2 Kolom (Kiri: Artikel Utama 8/12, Kanan: Sidebar Berita Terkait 4/12) -->
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-start">
            <!-- KOLOM KIRI: Konten Utama Artikel -->
            <div class="lg:col-span-8 min-w-0">
                <article class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-xs overflow-hidden transition-colors">
                    <!-- Article Header Section -->
                    <header class="p-5 sm:p-8 lg:p-9 pb-6 border-b border-slate-100 dark:border-slate-800">
                        <div class="flex flex-wrap items-center gap-2 mb-3.5">
                            <span class="px-2.5 py-1 rounded-md text-xs font-semibold uppercase tracking-wider bg-blue-100 text-blue-800 dark:bg-blue-950 dark:text-blue-300">
                                {{ $post->category?->name ?? 'Kegiatan' }}
                            </span>
                            @if($post->period)
                                <a href="{{ route('public.posts.index', ['edisi' => $post->period->value]) }}"
                                   class="px-2.5 py-1 rounded-md text-xs font-semibold uppercase tracking-wider bg-slate-100 hover:bg-slate-200 text-slate-700 dark:bg-slate-800 dark:hover:bg-slate-700 dark:text-slate-200 transition-colors">
                                    {{ $post->period->getLabel() }}
                                </a>
                            @endif
                            @if($post->is_featured)
                                <span class="px-2.5 py-1 rounded-md text-xs font-bold uppercase tracking-wider bg-rose-600 text-white shadow-xs">
                                    ★ Berita Utama
                                </span>
                            @endif
                        </div>

                        <h1 class="article-hero-title font-extrabold text-slate-900 dark:text-white tracking-tight leading-tight">
                            {{ $post->title }}
                        </h1>

                        <!-- Author, Tanggal & Statistik Inline (Seperti Referensi Editorial) -->
                        <div class="mt-5 flex flex-wrap items-center gap-x-3 gap-y-2 text-xs sm:text-sm text-slate-600 dark:text-slate-400">
                            <div class="flex items-center gap-2 min-w-0">
                                @if($authorAvatarUrl)
                                    <img src="{{ $authorAvatarUrl }}"
                                         alt="{{ $authorName }}"
                                         class="w-7 h-7 rounded-full object-cover shrink-0 border border-slate-200 dark:border-slate-700 shadow-2xs"
                                         loading="lazy">
                                @else
                                    <span class="w-7 h-7 rounded-full bg-blue-600 text-white text-xs font-bold inline-flex items-center justify-center shrink-0" aria-hidden="true">
                                        {{ $authorInitial }}
                                    </span>
                                @endif
                                <span class="font-semibold text-slate-900 dark:text-white truncate">{{ $authorName }}</span>
                            </div>

                            <span class="text-slate-300 dark:text-slate-700" aria-hidden="true">•</span>
                            <time datetime="{{ $post->published_at?->toIso8601String() }}">
                                {{ $post->published_at ? $post->published_at->translatedFormat('d F Y') : '-' }}
                            </time>

                            <span class="text-slate-300 dark:text-slate-700" aria-hidden="true">•</span>
                            <span class="inline-flex items-center gap-1 font-medium text-slate-700 dark:text-slate-300">
                                <svg class="w-4 h-4 text-slate-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                </svg>
                                {{ number_format($post->views_count) }} kali dibaca
                            </span>

                            <span class="text-slate-300 dark:text-slate-700" aria-hidden="true">•</span>
                            <span>{{ $readingMinutes }} menit baca</span>
                        </div>

                        <!-- Tag Metadata Unit / Acara / Lokasi (Pill Ringkas) -->
                        <div class="mt-4 flex flex-wrap items-center gap-2 text-xs">
                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-slate-100 dark:bg-slate-800/80 text-slate-700 dark:text-slate-300 border border-slate-200/70 dark:border-slate-700/70">
                                <svg class="w-3.5 h-3.5 text-blue-600 dark:text-blue-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                                </svg>
                                <span>{{ $post->unit?->name ?? 'UNIVBI' }}</span>
                            </span>

                            @if($post->event_date)
                                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-slate-100 dark:bg-slate-800/80 text-slate-700 dark:text-slate-300 border border-slate-200/70 dark:border-slate-700/70">
                                    <svg class="w-3.5 h-3.5 text-emerald-600 dark:text-emerald-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                    </svg>
                                    <span>Acara: {{ $post->event_date->translatedFormat('d M Y') }}</span>
                                </span>
                            @endif

                            @if($post->event_location)
                                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-slate-100 dark:bg-slate-800/80 text-slate-700 dark:text-slate-300 border border-slate-200/70 dark:border-slate-700/70">
                                    <svg class="w-3.5 h-3.5 text-rose-600 dark:text-rose-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                                    </svg>
                                    <span>{{ $post->event_location }}</span>
                                </span>
                            @endif
                        </div>

                        <!-- Action & Share Bar Seragam (Rapi & Responsif) -->
                        <div class="mt-6 pt-5 border-t border-slate-100 dark:border-slate-800 flex flex-wrap items-center justify-between gap-3">
                            <div class="flex flex-wrap items-center gap-2">
                                @if($post->photos->isNotEmpty())
                                    <span class="inline-flex items-center gap-1.5 h-9 px-3 rounded-lg bg-slate-50 dark:bg-slate-800/70 border border-slate-200 dark:border-slate-700 text-xs font-semibold text-slate-700 dark:text-slate-200">
                                        <svg class="w-4 h-4 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                        </svg>
                                        <span>{{ $post->photos->count() }} Foto Dokumentasi</span>
                                    </span>
                                @endif
                                @if($post->publicDocuments->isNotEmpty())
                                    <span class="inline-flex items-center gap-1.5 h-9 px-3 rounded-lg bg-emerald-50/70 dark:bg-emerald-950/50 border border-emerald-200 dark:border-emerald-800/70 text-xs font-semibold text-emerald-700 dark:text-emerald-300">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                        </svg>
                                        <span>{{ $post->publicDocuments->count() }} Dokumen Sah</span>
                                    </span>
                                @endif
                            </div>

                            <div class="flex flex-wrap items-center gap-2">
                                <span class="text-[11px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500 mr-0.5">Bagikan:</span>

                                <a href="https://api.whatsapp.com/send?text={{ urlencode($shareText) }}"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    aria-label="Bagikan ke WhatsApp"
                                    title="Bagikan ke WhatsApp"
                                    class="w-9 h-9 inline-flex items-center justify-center rounded-lg border border-emerald-200 dark:border-emerald-800/70 bg-emerald-50 dark:bg-emerald-950/50 text-emerald-600 dark:text-emerald-400 hover:bg-emerald-600 hover:text-white hover:border-emerald-600 dark:hover:bg-emerald-600 dark:hover:text-white transition-colors shrink-0">
                                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                        <path d="M12.031 2c-5.516 0-9.969 4.453-9.969 9.969 0 1.766.469 3.484 1.344 5l-1.406 5.156 5.281-1.391c1.469.797 3.109 1.219 4.75 1.219 5.516 0 9.969-4.453 9.969-9.969S17.547 2 12.031 2zm5.813 14.125c-.25.703-1.438 1.344-2 1.422-.516.078-1.172.109-1.891-.125-.438-.141-.984-.328-1.703-.641-2.984-1.297-4.938-4.313-5.094-4.516-.141-.203-1.219-1.625-1.219-3.094s.766-2.188 1.047-2.484c.266-.297.594-.375.797-.375h.563c.188 0 .438-.078.672.516.25.609.844 2.078.922 2.234.078.156.125.328.031.531-.109.203-.156.328-.313.516-.141.172-.313.391-.438.516-.141.156-.297.313-.125.609.172.297.766 1.266 1.656 2.047 1.141 1.016 2.094 1.328 2.391 1.484.297.141.469.125.641-.078.172-.203.75-.875.953-1.172.203-.297.391-.25.672-.141.281.094 1.75.828 2.047.969.297.156.5.219.578.344.078.125.078.734-.172 1.438z"/>
                                    </svg>
                                </a>

                                <a href="https://www.facebook.com/sharer/sharer.php?u={{ urlencode($shareUrl) }}"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    aria-label="Bagikan ke Facebook"
                                    title="Bagikan ke Facebook"
                                    class="w-9 h-9 inline-flex items-center justify-center rounded-lg border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800/70 text-slate-600 dark:text-slate-300 hover:bg-blue-600 hover:text-white hover:border-blue-600 dark:hover:bg-blue-600 dark:hover:text-white transition-colors shrink-0">
                                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                        <path d="M13.5 9H16l-.5 3h-2v9h-3v-9H9V9h1.5V7.25C10.5 5.18 11.69 4 14 4c.96 0 1.75.08 2 .12v2.45h-1.38c-1.02 0-1.12.44-1.12 1.18V9z"/>
                                    </svg>
                                </a>

                                <a href="https://twitter.com/intent/tweet?text={{ urlencode($post->title) }}&url={{ urlencode($shareUrl) }}"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    aria-label="Bagikan ke X"
                                    title="Bagikan ke X"
                                    class="w-9 h-9 inline-flex items-center justify-center rounded-lg border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800/70 text-slate-600 dark:text-slate-300 hover:bg-slate-900 hover:text-white hover:border-slate-900 dark:hover:bg-white dark:hover:text-slate-900 transition-colors shrink-0">
                                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                        <path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/>
                                    </svg>
                                </a>

                                <button type="button"
                                    data-copy-url="{{ $shareUrl }}"
                                    onclick="copyPostShareLink(this)"
                                    class="js-copy-link-btn h-9 px-3 inline-flex items-center justify-center gap-1.5 rounded-lg border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800/70 text-xs font-semibold text-slate-700 dark:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-800 hover:border-slate-300 dark:hover:border-slate-600 transition-colors shrink-0"
                                    title="Salin Tautan Berita">
                                    <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
                                    </svg>
                                    <span class="js-copy-label">Salin Tautan</span>
                                </button>
                            </div>
                        </div>
                    </header>

                    <!-- Konten & Media Utama -->
                    <div class="p-5 sm:p-8 lg:p-9 space-y-7">
                        <!-- Kutipan Lead / Excerpt (Gaya Editorial Seperti Referensi) -->
                        @if($post->excerpt && strcasecmp(trim($post->excerpt), trim($post->title)) !== 0)
                            <blockquote class="border-l-4 border-blue-600 dark:border-blue-500 pl-4 sm:pl-5 py-1 text-slate-700 dark:text-slate-300 text-base sm:text-lg italic leading-relaxed bg-blue-50/40 dark:bg-blue-950/20 rounded-r-xl pr-4">
                                {{ $post->excerpt }}
                            </blockquote>
                        @endif

                        <!-- Foto Sampul Utama -->
                        @if($post->getThumbnailUrl())
                            <figure class="rounded-2xl overflow-hidden bg-slate-950 border border-slate-200/80 dark:border-slate-800 shadow-xs">
                                <img src="{{ $post->getThumbnailUrl() }}"
                                    alt="{{ $post->title }}"
                                    fetchpriority="high"
                                    class="w-full max-h-[500px] object-contain mx-auto">
                                @if($post->thumbnail_caption)
                                    <figcaption class="px-4 py-2.5 bg-slate-900/90 text-slate-300 text-xs italic text-center border-t border-slate-800">
                                        {{ $post->thumbnail_caption }}
                                    </figcaption>
                                @endif
                            </figure>
                        @endif

                        <!-- Narasi Berita Lengkap -->
                        <div class="prose-content text-slate-700 dark:text-slate-300">
                            {!! $post->content !!}
                        </div>
                    </div>

                    <!-- SECTION 1: Galeri Multi-Foto Dokumentasi Kegiatan -->
                    @if($post->photos->isNotEmpty())
                        <section class="p-5 sm:p-8 lg:p-9 border-t border-slate-100 dark:border-slate-800 bg-slate-50/60 dark:bg-slate-950/50">
                            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
                                <div>
                                    <div class="inline-flex items-center gap-1.5 text-xs font-bold text-blue-600 dark:text-blue-400 uppercase tracking-wider">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                        </svg>
                                        Galeri Dokumentasi Kegiatan
                                    </div>
                                    <h2 class="text-lg sm:text-xl font-bold text-slate-900 dark:text-white mt-1">
                                        Dokumentasi Visual Acara ({{ $post->photos->count() }} Foto)
                                    </h2>
                                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                                        Klik pada foto untuk melihat ukuran penuh resolusi tinggi.
                                    </p>
                                </div>

                                @if($post->allow_public_zip_download)
                                    <a href="{{ route('public.posts.download_photos', $post->slug) }}"
                                        class="inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl bg-navy-900 hover:bg-navy-800 dark:bg-blue-600 dark:hover:bg-blue-700 text-white text-xs sm:text-sm font-bold shadow-md hover:shadow-lg transition-all shrink-0">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                                        </svg>
                                        <span>Unduh Paket Foto (.ZIP)</span>
                                        <span class="px-2 py-0.5 rounded-md text-[10px] bg-white/20 text-white font-semibold">
                                            {{ $post->photos->count() }}
                                        </span>
                                    </a>
                                @endif
                            </div>

                            <!-- Grid Galeri Foto Responsif -->
                            <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-4 sm:gap-5">
                                @foreach($post->photos as $photo)
                                    <div class="bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-800 shadow-xs overflow-hidden group flex flex-col justify-between p-3 hover:border-slate-300 dark:hover:border-slate-700 transition-all">
                                        <button type="button"
                                            data-lightbox-url="{{ $photo->getUrl() }}"
                                            data-lightbox-caption="{{ $photo->caption ?: 'Dokumentasi Acara' }}"
                                            onclick="openLightboxFromButton(this)"
                                            class="relative aspect-4/3 bg-slate-950 rounded-lg overflow-hidden cursor-pointer mb-2.5 w-full block text-left">
                                            <img src="{{ $photo->getThumbnailUrl() }}"
                                                alt="{{ $photo->caption ?: $post->title }}"
                                                loading="lazy"
                                                class="absolute inset-0 w-full h-full object-cover object-center group-hover:scale-105 transition-transform duration-300">
                                            <div class="absolute inset-0 bg-slate-950/20 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center">
                                                <div class="p-2.5 rounded-full bg-white/90 dark:bg-slate-900/90 text-slate-900 dark:text-white shadow-lg">
                                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0zM10 7v3m0 0v3m0-3h3m-3 0H7" />
                                                    </svg>
                                                </div>
                                            </div>
                                            @if($photo->is_cover)
                                                <span class="absolute top-2 left-2 px-2 py-0.5 rounded-md text-[10px] font-bold bg-navy-900/90 text-white">
                                                    Foto Sampul
                                                </span>
                                            @endif
                                        </button>
                                        <div class="px-0.5 flex items-center justify-between gap-2 text-xs">
                                            <p class="text-slate-600 dark:text-slate-400 font-medium line-clamp-2 min-w-0">
                                                {{ $photo->caption ?: 'Foto Dokumentasi Kegiatan' }}
                                            </p>
                                            <a href="{{ $photo->getUrl() }}"
                                                target="_blank"
                                                rel="noopener noreferrer"
                                                class="text-blue-600 dark:text-blue-400 hover:underline shrink-0 font-semibold"
                                                title="Buka Resolusi Asli">
                                                Buka
                                            </a>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </section>
                    @endif

                    <!-- SECTION 2: Tautan Naskah Dinas Sah Terkait -->
                    @if($post->publicDocuments->isNotEmpty())
                        <section class="p-5 sm:p-8 lg:p-9 border-t border-slate-100 dark:border-slate-800 bg-white dark:bg-slate-900">
                            <div class="mb-6">
                                <div class="inline-flex items-center gap-1.5 text-xs font-bold text-emerald-600 dark:text-emerald-400 uppercase tracking-wider">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                                    </svg>
                                    Jaminan Keabsahan Naskah Resmi
                                </div>
                                <h2 class="text-lg sm:text-xl font-bold text-slate-900 dark:text-white mt-1">
                                    Naskah Dinas Sah & Lampiran Resmi Terkait
                                </h2>
                                <p class="text-xs sm:text-sm text-slate-500 dark:text-slate-400 mt-1">
                                    Naskah dinas di bawah ini diterbitkan secara sah oleh Universitas Bina Insan dan dapat diverifikasi melalui Portal QR-Code.
                                </p>
                            </div>

                            <div class="space-y-4">
                                @foreach($post->publicDocuments as $doc)
                                    <div class="p-4 sm:p-5 rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950/60 flex flex-col md:flex-row md:items-center justify-between gap-4 hover:border-blue-400 dark:hover:border-blue-600 transition-colors">
                                        <div class="flex items-start gap-3.5 min-w-0">
                                            <div class="w-11 h-11 rounded-xl bg-blue-100 dark:bg-blue-950/80 text-blue-700 dark:text-blue-300 flex items-center justify-center shrink-0">
                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                                </svg>
                                            </div>
                                            <div class="min-w-0">
                                                <div class="flex flex-wrap items-center gap-2 mb-1">
                                                    <span class="font-mono text-xs font-bold text-slate-900 dark:text-white bg-white dark:bg-slate-900 px-2 py-0.5 rounded-md border border-slate-200 dark:border-slate-700">
                                                        {{ $doc->document_number }}
                                                    </span>
                                                    <span class="inline-flex items-center gap-1 text-[11px] font-semibold text-emerald-700 dark:text-emerald-400 bg-emerald-50 dark:bg-emerald-950/60 px-2 py-0.5 rounded-md border border-emerald-200 dark:border-emerald-800">
                                                        <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20">
                                                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                                                        </svg>
                                                        Dokumen Sah
                                                    </span>
                                                    <span class="text-xs text-slate-500 dark:text-slate-400 truncate">
                                                        • Unit: {{ $doc->unit?->name ?? 'UNIVBI' }}
                                                    </span>
                                                </div>

                                                <h3 class="text-sm sm:text-base font-bold text-slate-900 dark:text-white leading-snug">
                                                    {{ $doc->title }}
                                                </h3>
                                                @if($doc->issued_date)
                                                    <div class="text-xs text-slate-500 dark:text-slate-400 mt-1">
                                                        Tanggal Terbit: {{ $doc->issued_date->translatedFormat('d F Y') }}
                                                    </div>
                                                @endif
                                            </div>
                                        </div>

                                        <div class="flex flex-wrap items-center gap-2 shrink-0 self-start md:self-center">
                                            <a href="{{ route('document.verify.stream', $doc->uuid) }}"
                                                target="_blank"
                                                class="px-3.5 py-2 rounded-lg bg-blue-600 hover:bg-blue-700 text-white text-xs font-semibold shadow-xs transition-all inline-flex items-center gap-1.5">
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                                </svg>
                                                {{ $doc->pivot->custom_button_label ?: 'Pratinjau Salinan (.PDF)' }}
                                            </a>

                                            <a href="{{ $doc->getVerificationUrl() }}"
                                                target="_blank"
                                                class="px-3 py-2 rounded-lg border border-emerald-600 dark:border-emerald-500 text-emerald-700 dark:text-emerald-400 hover:bg-emerald-50 dark:hover:bg-emerald-950/40 text-xs font-semibold transition-colors inline-flex items-center gap-1.5">
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z" />
                                                </svg>
                                                Validasi QR
                                            </a>

                                            <a href="{{ route('document.verify.download', $doc->uuid) }}"
                                                class="p-2 rounded-lg border border-slate-300 dark:border-slate-700 text-slate-600 dark:text-slate-400 hover:bg-slate-200 dark:hover:bg-slate-800 transition-colors"
                                                title="Unduh Berkas Resmi">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                                                </svg>
                                            </a>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </section>
                    @endif

                    <!-- Article Footer / Share Bar -->
                    <footer class="p-5 sm:p-8 lg:p-9 border-t border-slate-100 dark:border-slate-800 flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-slate-50/40 dark:bg-slate-900/60">
                        <a href="{{ route('public.posts.index') }}" class="inline-flex items-center gap-1.5 text-xs font-semibold text-slate-600 dark:text-slate-300 hover:text-blue-600 dark:hover:text-blue-400 transition-colors">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                            </svg>
                            <span>Kembali ke Daftar Warta</span>
                        </a>

                        <!-- Bagikan Berita (Footer Konsisten) -->
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="text-[11px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500 mr-0.5">Bagikan:</span>

                            <a href="https://api.whatsapp.com/send?text={{ urlencode($shareText) }}"
                                target="_blank"
                                rel="noopener noreferrer"
                                aria-label="Bagikan ke WhatsApp"
                                title="Bagikan ke WhatsApp"
                                class="w-9 h-9 inline-flex items-center justify-center rounded-lg border border-emerald-200 dark:border-emerald-800/70 bg-emerald-50 dark:bg-emerald-950/50 text-emerald-600 dark:text-emerald-400 hover:bg-emerald-600 hover:text-white hover:border-emerald-600 dark:hover:bg-emerald-600 dark:hover:text-white transition-colors shrink-0">
                                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path d="M12.031 2c-5.516 0-9.969 4.453-9.969 9.969 0 1.766.469 3.484 1.344 5l-1.406 5.156 5.281-1.391c1.469.797 3.109 1.219 4.75 1.219 5.516 0 9.969-4.453 9.969-9.969S17.547 2 12.031 2zm5.813 14.125c-.25.703-1.438 1.344-2 1.422-.516.078-1.172.109-1.891-.125-.438-.141-.984-.328-1.703-.641-2.984-1.297-4.938-4.313-5.094-4.516-.141-.203-1.219-1.625-1.219-3.094s.766-2.188 1.047-2.484c.266-.297.594-.375.797-.375h.563c.188 0 .438-.078.672.516.25.609.844 2.078.922 2.234.078.156.125.328.031.531-.109.203-.156.328-.313.516-.141.172-.313.391-.438.516-.141.156-.297.313-.125.609.172.297.766 1.266 1.656 2.047 1.141 1.016 2.094 1.328 2.391 1.484.297.141.469.125.641-.078.172-.203.75-.875.953-1.172.203-.297.391-.25.672-.141.281.094 1.75.828 2.047.969.297.156.5.219.578.344.078.125.078.734-.172 1.438z"/>
                                </svg>
                            </a>

                            <a href="https://www.facebook.com/sharer/sharer.php?u={{ urlencode($shareUrl) }}"
                                target="_blank"
                                rel="noopener noreferrer"
                                aria-label="Bagikan ke Facebook"
                                title="Bagikan ke Facebook"
                                class="w-9 h-9 inline-flex items-center justify-center rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800/70 text-slate-600 dark:text-slate-300 hover:bg-blue-600 hover:text-white hover:border-blue-600 dark:hover:bg-blue-600 dark:hover:text-white transition-colors shrink-0">
                                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path d="M13.5 9H16l-.5 3h-2v9h-3v-9H9V9h1.5V7.25C10.5 5.18 11.69 4 14 4c.96 0 1.75.08 2 .12v2.45h-1.38c-1.02 0-1.12.44-1.12 1.18V9z"/>
                                </svg>
                            </a>

                            <a href="https://twitter.com/intent/tweet?text={{ urlencode($post->title) }}&url={{ urlencode($shareUrl) }}"
                                target="_blank"
                                rel="noopener noreferrer"
                                aria-label="Bagikan ke X"
                                title="Bagikan ke X"
                                class="w-9 h-9 inline-flex items-center justify-center rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800/70 text-slate-600 dark:text-slate-300 hover:bg-slate-900 hover:text-white hover:border-slate-900 dark:hover:bg-white dark:hover:text-slate-900 transition-colors shrink-0">
                                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/>
                                </svg>
                            </a>

                            <button type="button"
                                data-copy-url="{{ $shareUrl }}"
                                onclick="copyPostShareLink(this)"
                                class="js-copy-link-btn h-9 px-3 inline-flex items-center justify-center gap-1.5 rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800/70 text-xs font-semibold text-slate-700 dark:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-800 hover:border-slate-300 dark:hover:border-slate-600 transition-colors shrink-0"
                                title="Salin Tautan Berita">
                                <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
                                </svg>
                                <span class="js-copy-label">Salin Tautan</span>
                            </button>
                        </div>
                    </footer>
                </article>
            </div>

            <!-- KOLOM KANAN: Sidebar Sticky Berita Terkait -->
            <aside class="lg:col-span-4 min-w-0 lg:sticky lg:top-24" aria-label="Berita Terkait">
                <div class="pb-3 mb-4 border-b border-slate-200 dark:border-slate-800 flex items-center justify-between gap-2">
                    <h2 class="text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">
                        Berita Terkait
                    </h2>
                    <a href="{{ route('public.posts.index') }}" class="text-xs font-semibold text-blue-600 dark:text-blue-400 hover:underline">
                        Semua Warta
                    </a>
                </div>

                @if($relatedPosts->isNotEmpty())
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-1 gap-4">
                        @foreach($relatedPosts as $related)
                            <article class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 p-3.5 shadow-xs hover:shadow-md hover:border-slate-300 dark:hover:border-slate-700 transition-all group">
                                <a href="{{ route('public.posts.show', $related->slug) }}" class="block space-y-3">
                                    <div class="relative aspect-video rounded-xl overflow-hidden bg-slate-900">
                                        @if($related->getThumbnailUrl())
                                            <img src="{{ $related->getThumbnailUrl() }}"
                                                alt="{{ $related->title }}"
                                                loading="lazy"
                                                class="absolute inset-0 w-full h-full object-cover object-center group-hover:scale-105 transition-transform duration-300">
                                        @else
                                            <div class="absolute inset-0 w-full h-full flex items-center justify-center bg-slate-800 text-slate-500">
                                                <svg class="w-9 h-9 opacity-40" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                                </svg>
                                            </div>
                                        @endif

                                        @if(($related->photos_count ?? 0) > 0)
                                            <span class="absolute bottom-2 right-2 px-2 py-0.5 rounded-md text-[10px] font-semibold bg-slate-900/85 text-white backdrop-blur-xs">
                                                {{ $related->photos_count }} Foto
                                            </span>
                                        @endif
                                    </div>

                                    <div class="px-0.5 space-y-1.5 min-w-0">
                                        <div class="flex items-center justify-between gap-2 text-[11px] text-slate-500 dark:text-slate-400">
                                            <span>{{ $related->published_at ? $related->published_at->translatedFormat('d M Y') : '-' }}</span>
                                            @if($related->period)
                                                <span class="font-medium">{{ $related->period->getShortLabel() }}</span>
                                            @endif
                                        </div>

                                        <h3 class="related-card-title text-sm font-bold text-slate-900 dark:text-white group-hover:text-blue-600 dark:group-hover:text-blue-400 transition-colors leading-snug">
                                            {{ $related->title }}
                                        </h3>

                                        <div class="flex items-center justify-between gap-2 pt-1 text-[11px]">
                                            <span class="font-semibold text-blue-600 dark:text-blue-400 truncate">
                                                {{ $related->category?->name ?? 'Warta Kampus' }}
                                            </span>
                                            @if($related->unit)
                                                <span class="text-slate-400 dark:text-slate-500 truncate max-w-[110px]">
                                                    {{ $related->unit->code ?? $related->unit->name }}
                                                </span>
                                            @endif
                                        </div>
                                    </div>
                                </a>
                            </article>
                        @endforeach
                    </div>
                @else
                    <div class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 p-6 text-center shadow-xs">
                        <p class="text-xs text-slate-500 dark:text-slate-400 leading-relaxed">
                            Belum ada berita terkait lainnya yang diterbitkan saat ini.
                        </p>
                        <a href="{{ route('public.posts.index') }}" class="mt-3 inline-flex items-center gap-1.5 px-3.5 py-2 rounded-lg text-xs font-semibold bg-blue-600 hover:bg-blue-700 text-white transition-colors">
                            Lihat Semua Warta
                        </a>
                    </div>
                @endif
            </aside>
        </div>
    </main>

    <!-- Lightbox Modal untuk Foto Resolusi Penuh -->
    <div id="lightboxModal" class="fixed inset-0 z-50 bg-black/90 backdrop-blur-xs hidden items-center justify-center p-4" onclick="closeLightbox()">
        <div class="relative max-w-5xl w-full max-h-[90dvh] flex flex-col items-center" onclick="event.stopPropagation()">
            <button type="button" onclick="closeLightbox()" class="absolute -top-12 right-0 p-2 text-white/80 hover:text-white focus:outline-hidden" aria-label="Tutup pratinjau">
                <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
            <img id="lightboxImg" src="" alt="Pratinjau Foto" class="max-w-full max-h-[80dvh] object-contain rounded-lg shadow-2xl">
            <p id="lightboxCaption" class="mt-3 text-white text-sm text-center font-medium"></p>
        </div>
    </div>

    <!-- Footer Institusi & Developer Resmi -->
    @include('public.partials.footer')

    <script>
        function toggleDarkMode() {
            if (document.documentElement.classList.contains('dark')) {
                document.documentElement.classList.remove('dark');
                localStorage.theme = 'light';
            } else {
                document.documentElement.classList.add('dark');
                localStorage.theme = 'dark';
            }
        }

        function fallbackCopyText(text) {
            const textarea = document.createElement('textarea');
            textarea.value = text;
            textarea.setAttribute('readonly', '');
            textarea.style.position = 'fixed';
            textarea.style.top = '-9999px';
            textarea.style.left = '-9999px';
            document.body.appendChild(textarea);
            textarea.focus();
            textarea.select();

            let copied = false;
            try {
                copied = document.execCommand('copy');
            } catch (_) {
                copied = false;
            } finally {
                document.body.removeChild(textarea);
            }

            return copied;
        }

        async function copyPostShareLink(buttonEl) {
            const targetUrl = buttonEl?.dataset?.copyUrl || window.location.href;
            let copied = false;

            if (navigator.clipboard && typeof navigator.clipboard.writeText === 'function' && window.isSecureContext) {
                try {
                    await navigator.clipboard.writeText(targetUrl);
                    copied = true;
                } catch (_) {
                    copied = fallbackCopyText(targetUrl);
                }
            } else {
                copied = fallbackCopyText(targetUrl);
            }

            const labelEl = buttonEl?.querySelector('.js-copy-label');
            if (!buttonEl || !labelEl) {
                return;
            }

            if (copied) {
                const originalText = labelEl.textContent;
                labelEl.textContent = 'Tersalin!';
                buttonEl.classList.add('border-emerald-500', 'text-emerald-700', 'dark:text-emerald-300', 'bg-emerald-50', 'dark:bg-emerald-950/50');
                setTimeout(() => {
                    labelEl.textContent = originalText;
                    buttonEl.classList.remove('border-emerald-500', 'text-emerald-700', 'dark:text-emerald-300', 'bg-emerald-50', 'dark:bg-emerald-950/50');
                }, 2200);
            } else {
                window.prompt('Salin tautan berita berikut:', targetUrl);
            }
        }

        function openLightboxFromButton(btnEl) {
            const imgSrc = btnEl?.dataset?.lightboxUrl;
            const caption = btnEl?.dataset?.lightboxCaption || '';
            if (!imgSrc) {
                return;
            }

            openLightbox(imgSrc, caption);
        }

        function openLightbox(imgSrc, caption) {
            const modal = document.getElementById('lightboxModal');
            const img = document.getElementById('lightboxImg');
            const cap = document.getElementById('lightboxCaption');

            img.src = imgSrc;
            cap.textContent = caption || '';
            modal.classList.remove('hidden');
            modal.classList.add('flex');
            document.body.style.overflow = 'hidden';
        }

        function closeLightbox() {
            const modal = document.getElementById('lightboxModal');
            modal.classList.add('hidden');
            modal.classList.remove('flex');
            document.body.style.overflow = '';
        }

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
                closeLightbox();
            }
        });
    </script>
</body>
</html>
