@props([
    'post',
    'settings',
])

@php
    $plainContent = trim(preg_replace('/\s+/', ' ', strip_tags((string) $post->content)) ?? '');
    $leadSummary = $post->excerpt ?: ($plainContent !== '' ? Str::limit($plainContent, 200) : 'Liputan kegiatan resmi Universitas Bina Insan.');
@endphp

<article
    class="bg-white dark:bg-slate-900 rounded-xl border border-slate-300 dark:border-slate-800 p-4 sm:p-5 shadow-xs hover:shadow-md transition-all duration-200 group">
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-5 lg:gap-7 items-stretch">
        <!-- Thumbnail Foto Spotlight (Kiri 7 Kolom) -->
        <div class="lg:col-span-7">
            <a href="{{ route('public.posts.show', $post->slug) }}"
                class="block relative aspect-[16/10] w-full rounded-lg overflow-hidden bg-slate-100 dark:bg-slate-800 border border-slate-200/80 dark:border-slate-700/80">
                @if ($thumb = $post->getThumbnailUrl())
                    <img src="{{ $thumb }}" alt="{{ $post->title }}"
                        class="absolute inset-0 w-full h-full object-cover object-center group-hover:scale-105 transition-transform duration-300">
                @else
                    <div
                        class="absolute inset-0 w-full h-full flex flex-col items-center justify-center bg-gradient-to-br from-navy-900 to-slate-900 text-white p-6 text-center">
                        <span class="text-xs font-bold uppercase tracking-wider text-blue-400">
                            {{ $settings->university_acronym ?: 'UNIVBI' }} Warta
                        </span>
                        <span class="text-base font-semibold mt-1 line-clamp-2">{{ $post->title }}</span>
                    </div>
                @endif

                <div class="absolute top-3 left-3 right-3 z-10 flex flex-wrap items-center gap-1.5">
                    @if ($post->is_pinned)
                        <span
                            class="px-2 py-0.5 rounded text-[10px] font-extrabold uppercase tracking-wider bg-amber-500 text-white shadow-2xs shrink-0">
                            PINNED
                        </span>
                    @elseif ($post->is_featured)
                        <span
                            class="px-2 py-0.5 rounded text-[10px] font-extrabold uppercase tracking-wider bg-rose-600 text-white shadow-2xs shrink-0">
                            ★ BERITA UTAMA
                        </span>
                    @endif
                    @if ($post->category)
                        <span
                            class="px-2 py-0.5 rounded text-[10px] font-bold bg-navy-900/90 text-white backdrop-blur-xs shadow-2xs truncate max-w-[220px]">
                            {{ $post->category->name }}
                        </span>
                    @endif
                    @if ($post->period)
                        <span
                            class="px-2 py-0.5 rounded text-[10px] font-bold bg-blue-600/90 text-white backdrop-blur-xs shadow-2xs shrink-0">
                            {{ $post->period->getShortLabel() }}
                        </span>
                    @endif
                </div>

                @if ($post->photos_count > 1)
                    <div
                        class="absolute bottom-3 right-3 px-2.5 py-1 rounded text-[10px] font-bold bg-black/75 text-white backdrop-blur-xs flex items-center gap-1 shadow-2xs">
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                            aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                        </svg>
                        <span>{{ $post->photos_count }} Foto</span>
                    </div>
                @endif
            </a>
        </div>

        <!-- Konten Narasi & Metadata (Kanan 5 Kolom) -->
        <div class="lg:col-span-5 flex flex-col justify-between gap-4 min-w-0 py-0.5">
            <div class="space-y-3 min-w-0">
                <div class="flex flex-wrap items-center gap-x-2 gap-y-0.5 text-xs text-slate-500 dark:text-slate-400">
                    <span class="font-medium">
                        {{ $post->published_at?->translatedFormat('d M Y') ?? $post->created_at->translatedFormat('d M Y') }}
                    </span>
                    @if ($post->academicYear)
                        <span>•</span>
                        <span>TA {{ $post->academicYear->name }}</span>
                    @endif
                </div>

                <h3
                    class="text-lg sm:text-xl lg:text-2xl font-bold text-slate-900 dark:text-white group-hover:text-blue-600 dark:group-hover:text-blue-400 transition-colors leading-snug line-clamp-3">
                    <a href="{{ route('public.posts.show', $post->slug) }}">
                        {{ $post->title }}
                    </a>
                </h3>

                <p class="text-xs sm:text-sm text-slate-600 dark:text-slate-400 line-clamp-3 leading-relaxed">
                    {{ $leadSummary }}
                </p>

                <div
                    class="pt-3 border-t border-slate-100 dark:border-slate-800/80 flex flex-wrap items-center gap-x-4 gap-y-2 text-xs text-slate-600 dark:text-slate-400">
                    @if ($post->event_location)
                        <div class="inline-flex items-center gap-1.5 truncate max-w-[260px]"
                            title="Lokasi: {{ $post->event_location }}">
                            <svg class="w-3.5 h-3.5 text-rose-500 shrink-0" fill="none" stroke="currentColor"
                                viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                            </svg>
                            <span class="truncate">{{ $post->event_location }}</span>
                        </div>
                    @endif

                    @if ($post->event_date)
                        <div class="inline-flex items-center gap-1.5" title="Waktu Pelaksanaan Acara">
                            <svg class="w-3.5 h-3.5 text-blue-500 shrink-0" fill="none" stroke="currentColor"
                                viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                            </svg>
                            <span>Acara: {{ $post->event_date->translatedFormat('d M Y') }}</span>
                        </div>
                    @endif

                    <div class="inline-flex items-center gap-1.5" title="Jumlah Pembaca">
                        <svg class="w-3.5 h-3.5 text-amber-500 shrink-0" fill="none" stroke="currentColor"
                            viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                        </svg>
                        <span>{{ number_format($post->views_count) }}x Dibaca</span>
                    </div>

                    @if ($post->photos_count > 0)
                        <div class="inline-flex items-center gap-1.5" title="Galeri Foto Dokumentasi">
                            <svg class="w-3.5 h-3.5 text-emerald-500 shrink-0" fill="none" stroke="currentColor"
                                viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                            </svg>
                            <span>{{ $post->photos_count }} Foto Arsip</span>
                        </div>
                    @endif
                </div>
            </div>

            <!-- Footer -->
            <footer
                class="pt-3 border-t border-slate-200/80 dark:border-slate-800/80 flex items-center justify-between gap-3 text-xs text-slate-500 dark:text-slate-400">
                <span class="font-medium truncate min-w-0 flex-1"
                    title="{{ $post->unit?->name ?? 'Universitas Bina Insan' }}">
                    {{ $post->unit?->name ?? 'Universitas Bina Insan' }}
                </span>
                <a href="{{ route('public.posts.show', $post->slug) }}"
                    class="inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-lg font-semibold bg-blue-600 hover:bg-blue-700 text-white shadow-2xs transition-colors shrink-0 whitespace-nowrap">
                    <span>Baca Selengkapnya</span>
                    <span class="sr-only"> tentang {{ $post->title }}</span>
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                        aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                    </svg>
                </a>
            </footer>
        </div>
    </div>
</article>

