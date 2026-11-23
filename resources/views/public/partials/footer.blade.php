@php
    $footerSettings = $settings ?? $activeSettings ?? \App\Models\LandingPageSetting::current();
    $footerLogoUrl = $footerSettings->getLogoUrl();
    $currentRouteName = request()->route() ? request()->route()->getName() : '';
    $isHome = request()->is('/') || $currentRouteName === 'landing.home';
    $hasAccreditationFooterLink = isset($accreditationShowcase)
        ? (($accreditationShowcase['total_count'] ?? 0) > 0)
        : \App\Models\Unit::hasPublicAccreditedUnits();
@endphp

<!-- Footer Institusi & Developer Resmi (Lengkap, Seragam & Terstruktur) -->
<footer class="bg-slate-900 text-slate-300 border-t border-slate-800 mt-auto shrink-0 transition-colors">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12 sm:py-16">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-8 lg:gap-12">
            <!-- Kolom 1 & 2: Profil Universitas & Sistem -->
            <div class="lg:col-span-2">
                <div class="flex items-center gap-3 mb-4">
                    @if($footerLogoUrl)
                        <img src="{{ $footerLogoUrl }}" alt="{{ $footerSettings->university_name }}" class="h-10 w-auto object-contain">
                    @else
                        <div class="h-10 px-3 rounded-lg bg-blue-600 text-white flex items-center justify-center font-black text-xs tracking-wider">
                            {{ $footerSettings->university_acronym ?: 'UNIVBI' }}
                        </div>
                    @endif
                    <div>
                        <span class="block text-base font-extrabold text-white leading-tight">
                            {{ $footerSettings->university_name }}
                        </span>
                        <span class="block text-xs text-slate-400 mt-0.5">
                            {{ $footerSettings->system_name }}
                        </span>
                    </div>
                </div>

                <p class="text-xs sm:text-sm text-slate-400 leading-relaxed mb-6 max-w-md">
                    {{ $footerSettings->system_tagline ?: 'Pangkalan Data Naskah Dinas Sah, Akreditasi Borang & Portal Warta Kampus' }}
                </p>

                <!-- Alamat & Narahubung -->
                <div class="space-y-2 text-xs text-slate-400">
                    @if($footerSettings->campus_address)
                        <div class="flex items-start gap-2.5">
                            <svg class="w-4 h-4 text-slate-500 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                            </svg>
                            <span>{{ $footerSettings->campus_address }}</span>
                        </div>
                    @endif

                    @if($footerSettings->campus_phone)
                        <div class="flex items-center gap-2.5">
                            <svg class="w-4 h-4 text-slate-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" />
                            </svg>
                            <span>{{ $footerSettings->campus_phone }}</span>
                        </div>
                    @endif

                    @if($footerSettings->campus_email)
                        <div class="flex items-center gap-2.5">
                            <svg class="w-4 h-4 text-slate-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                            </svg>
                            <a href="mailto:{{ $footerSettings->campus_email }}" class="hover:text-blue-400 transition-colors">
                                {{ $footerSettings->campus_email }}
                            </a>
                        </div>
                    @endif
                </div>
            </div>

            <!-- Kolom 3: Tautan Pintas Sistem -->
            <div>
                <h4 class="text-xs font-bold uppercase tracking-wider text-white mb-4">
                    Tautan Pintas Sistem
                </h4>
                <ul class="space-y-2 text-xs text-slate-400">
                    <li>
                        <a href="{{ route('landing.home') }}" class="hover:text-white transition-colors">
                            Beranda Utama
                        </a>
                    </li>
                    <li>
                        <a href="{{ $isHome ? '#dokumen-publik' : route('landing.home') . '#dokumen-publik' }}" class="hover:text-white transition-colors">
                            Daftar Dokumen Publik
                        </a>
                    </li>
                    @if ($hasAccreditationFooterLink)
                        <li>
                            <a href="{{ $isHome ? '#status-akreditasi' : route('landing.home') . '#status-akreditasi' }}" class="hover:text-white transition-colors">
                                Status Akreditasi
                            </a>
                        </li>
                    @endif
                    <li>
                        <a href="{{ route('public.posts.index') }}" class="hover:text-white transition-colors">
                            Warta & Dokumentasi Kegiatan
                        </a>
                    </li>
                    <li>
                        <a href="{{ route('filament.admin.auth.login') }}" class="hover:text-white transition-colors">
                            Portal Masuk Staf / Admin
                        </a>
                    </li>
                </ul>
            </div>

            <!-- Kolom 4: Media Sosial Resmi Universitas -->
            <div>
                <h4 class="text-xs font-bold uppercase tracking-wider text-white mb-4">
                    Media Sosial Resmi
                </h4>
                <p class="text-xs text-slate-400 leading-relaxed mb-4">
                    Ikuti kanal media sosial resmi {{ $footerSettings->university_name }} untuk berita terbaru dan agenda kampus.
                </p>
                <div class="flex flex-wrap items-center gap-2.5">
                    @if($footerSettings->social_instagram)
                        <a href="{{ $footerSettings->social_instagram }}" target="_blank" rel="noopener" class="p-2.5 rounded-lg bg-slate-800 text-slate-400 hover:text-pink-400 hover:bg-slate-700 transition-colors" title="Instagram Resmi">
                            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z"/>
                            </svg>
                        </a>
                    @endif

                    @if($footerSettings->social_youtube)
                        <a href="{{ $footerSettings->social_youtube }}" target="_blank" rel="noopener" class="p-2.5 rounded-lg bg-slate-800 text-slate-400 hover:text-red-500 hover:bg-slate-700 transition-colors" title="YouTube Resmi">
                            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M23.498 6.186a3.016 3.016 0 0 0-2.122-2.136C19.505 3.545 12 3.545 12 3.545s-7.505 0-9.377.505A3.017 3.017 0 0 0 .502 6.186C0 8.07 0 12 0 12s0 3.93.502 5.814a3.016 3.016 0 0 0 2.122 2.136c1.871.505 9.376.505 9.376.505s7.505 0 9.377-.505a3.015 3.015 0 0 0 2.122-2.136C24 15.93 24 12 24 12s0-3.93-.502-5.814zM9.545 15.568V8.432L15.818 12l-6.273 3.568z"/>
                            </svg>
                        </a>
                    @endif

                    @if($footerSettings->social_facebook)
                        <a href="{{ $footerSettings->social_facebook }}" target="_blank" rel="noopener" class="p-2.5 rounded-lg bg-slate-800 text-slate-400 hover:text-blue-500 hover:bg-slate-700 transition-colors" title="Facebook Resmi">
                            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/>
                            </svg>
                        </a>
                    @endif

                    @if($footerSettings->social_linkedin)
                        <a href="{{ $footerSettings->social_linkedin }}" target="_blank" rel="noopener" class="p-2.5 rounded-lg bg-slate-800 text-slate-400 hover:text-sky-400 hover:bg-slate-700 transition-colors" title="LinkedIn Resmi">
                            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M19 0h-14c-2.761 0-5 2.239-5 5v14c0 2.761 2.239 5 5 5h14c2.762 0 5-2.239 5-5v-14c0-2.761-2.238-5-5-5zm-11 19h-3v-11h3v11zm-1.5-12.268c-.966 0-1.75-.79-1.75-1.764s.784-1.764 1.75-1.764 1.75.79 1.75 1.764-.783 1.764-1.75 1.764zm13.5 12.268h-3v-5.604c0-3.368-4-3.113-4 0v5.604h-3v-11h3v1.765c1.396-2.586 7-2.777 7 2.476v6.759z"/>
                            </svg>
                        </a>
                    @endif

                    @if($footerSettings->social_twitter)
                        <a href="{{ $footerSettings->social_twitter }}" target="_blank" rel="noopener" class="p-2.5 rounded-lg bg-slate-800 text-slate-400 hover:text-white hover:bg-slate-700 transition-colors" title="Twitter / X Resmi">
                            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/>
                            </svg>
                        </a>
                    @endif
                </div>
            </div>
        </div>

        @php
            $sealDevName = \App\Support\Security\RuntimeIntegrityManifest::getDeveloperName();
            $sealDevEmail = \App\Support\Security\RuntimeIntegrityManifest::getDeveloperEmail();
            $sealDevWeb = \App\Support\Security\RuntimeIntegrityManifest::getDeveloperWebsite();
            $sealCopyright = \App\Support\Security\RuntimeIntegrityManifest::getCopyrightNotice();
        @endphp

        <!-- Sub-Footer: Informasi Developer Paten & Copyright -->
        <div class="mt-12 pt-8 border-t border-slate-800 flex flex-col md:flex-row items-center justify-between gap-4 text-xs text-slate-400">
            <!-- Identitas Pengembang -->
            <div class="flex flex-wrap items-center justify-center md:justify-start gap-2">
                <span>Dikembangkan oleh:</span>
                <span class="font-bold text-slate-200">{{ $sealDevName }}</span>
                <span>•</span>
                <a href="mailto:{{ $sealDevEmail }}" class="text-blue-400 hover:text-blue-300 transition-colors font-medium inline-flex items-center gap-1">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                    </svg>
                    {{ $sealDevEmail }}
                </a>
                @if($sealDevWeb)
                    <span>•</span>
                    <a href="{{ $sealDevWeb }}" target="_blank" rel="noopener noreferrer" class="hover:text-slate-200 transition-colors">
                        Portofolio / Web
                    </a>
                @endif
            </div>

            <!-- Hak Cipta -->
            <div class="text-slate-500 text-center md:text-right">
                {{ $sealCopyright }}
            </div>
        </div>
    </div>
</footer>

