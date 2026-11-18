@php
    try {
        $settings = \App\Models\LandingPageSetting::current();
        $logoUrl = $settings?->getLogoUrl();
        $universityName = $settings?->university_name ?: 'Universitas Bina Insan';
        $universityAcronym = $settings?->university_acronym ?: 'UNIVBI';
    } catch (\Throwable) {
        $settings = null;
        $logoUrl = null;
        $universityName = 'Universitas Bina Insan';
        $universityAcronym = 'UNIVBI';
    }
    $brandName = filament()->getBrandName() ?? 'E-Arsip & Borang UNIVBI';

    $sealDevName = \App\Support\Security\RuntimeIntegrityManifest::getDeveloperName();
    $sealDevEmail = \App\Support\Security\RuntimeIntegrityManifest::getDeveloperEmail();
    $sealDevWeb = \App\Support\Security\RuntimeIntegrityManifest::getDeveloperWebsite();
    $sealCopyright = \App\Support\Security\RuntimeIntegrityManifest::getCopyrightNotice();
@endphp

<div class="custom-login-container min-h-screen w-full flex flex-col lg:grid lg:grid-cols-12 relative overflow-hidden font-sans antialiased selection:bg-blue-600 selection:text-white">
    {{-- Global Ambient Glow (Latar Belakang Halus) --}}
    <div class="absolute inset-0 pointer-events-none overflow-hidden z-0">
        <div class="absolute top-1/2 -right-20 w-80 h-80 bg-blue-500/10 dark:bg-blue-400/10 rounded-full blur-3xl"></div>
        <div class="absolute -bottom-24 right-1/4 w-96 h-96 bg-indigo-500/10 dark:bg-indigo-500/15 rounded-full blur-3xl"></div>
    </div>

    {{-- ========================================================================= --}}
    {{-- KOLOM KIRI: HERO BANNER DENGAN BRANDING, HEADLINE, ROCKET SVG & BADGES     --}}
    {{-- ========================================================================= --}}
    <div class="custom-login-hero relative z-10 lg:col-span-7 flex flex-col justify-between overflow-hidden p-4 sm:p-6 lg:px-8 lg:py-5 xl:px-10 xl:py-6 text-white min-h-0 lg:h-screen lg:max-h-screen">
        {{-- Tekstur Grid Kotak-Kotak & Ambient Glow di Bagian Kotak Biru Kiri --}}
        <div class="absolute inset-0 pointer-events-none overflow-hidden z-0">
            {{-- Pola Kotak-Kotak Presisi --}}
            <svg class="absolute inset-0 w-full h-full text-white opacity-[0.14]" xmlns="http://www.w3.org/2000/svg" width="100%" height="100%">
                <defs>
                    <pattern id="login-hero-grid" width="40" height="40" patternUnits="userSpaceOnUse">
                        <path d="M 40 0 L 0 0 0 40" fill="none" stroke="currentColor" stroke-width="1.2" />
                    </pattern>
                </defs>
                <rect width="100%" height="100%" fill="url(#login-hero-grid)" />
            </svg>

            {{-- Ambient Glow Lights Khusus Banner Biru --}}
            <div class="absolute -top-32 -left-32 w-96 h-96 bg-cyan-400/25 rounded-full blur-3xl"></div>
            <div class="absolute top-1/2 -right-20 w-80 h-80 bg-blue-300/20 rounded-full blur-3xl"></div>
            <div class="absolute -bottom-24 left-1/4 w-96 h-96 bg-indigo-400/30 rounded-full blur-3xl"></div>
        </div>

        {{-- Top Header: Brand Logo (from CMS) or Fallback Monogram Badge (UNIVBI) --}}
        <div class="relative z-10 flex items-center justify-between gap-3">
            <div class="flex items-center gap-2.5">
                @if($logoUrl)
                    <img src="{{ $logoUrl }}" alt="{{ $universityName }}" class="h-8 sm:h-9 w-auto object-contain shrink-0 drop-shadow-md">
                @else
                    <div class="h-8 sm:h-9 px-2.5 rounded-lg bg-white text-blue-700 font-black text-xs tracking-wider flex items-center justify-center shadow-md shadow-blue-900/30 ring-2 ring-white/20 select-none shrink-0">
                        {{ $universityAcronym ?: 'UNIVBI' }}
                    </div>
                @endif
                <div>
                    <span class="block text-[9.5px] sm:text-[10px] font-bold tracking-widest text-blue-200 uppercase leading-none">{{ $universityName }}</span>
                    <h2 class="text-sm sm:text-base font-black tracking-tight text-white leading-tight mt-0.5">{{ $universityAcronym }} E-Arsip</h2>
                </div>
            </div>

            {{-- Status Pill --}}
            <div class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-white/10 backdrop-blur-md border border-white/20 text-[10px] sm:text-[11px] font-semibold text-blue-100 shadow-xs">
                <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse"></span>
                <span class="hidden sm:inline">Sistem Terpadu Aktif</span>
                <span class="sm:hidden">Aktif</span>
            </div>
        </div>

        {{-- Center Content (Desktop Only: Headline & Modern Rocket Illustration) --}}
        <div class="relative z-10 my-auto py-2 xl:py-4 hidden lg:flex flex-col xl:flex-row items-center justify-between gap-5 xl:gap-8">
            {{-- Left Text --}}
            <div class="max-w-md xl:max-w-lg text-left space-y-2.5">
                <div class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md bg-blue-500/30 border border-blue-300/30 text-[11px] font-semibold text-blue-100">
                    <span>🚀</span> Platform Tata Kelola Digital Resmi
                </div>

                <h1 class="text-2xl sm:text-3xl xl:text-4xl font-black tracking-tight text-white leading-tight">
                    Sistem E-Arsip & Bank Data Borang
                </h1>

                <p class="text-xs sm:text-sm text-blue-100/90 leading-relaxed font-normal line-clamp-3">
                    Pusat repositori naskah dinas, penomoran otomatis, tanda tangan digital (TTE), serta bank data akreditasi 9 kriteria BAN-PT & LAM untuk civitas akademika Universitas Bina Insan.
                </p>
            </div>

            {{-- Right Rocket & Digital Illustration (Proporsional & Ringkas untuk Layar 14 Inci) --}}
            <div class="relative flex-shrink-0 w-36 h-36 sm:w-44 sm:h-44 lg:w-44 lg:h-44 xl:w-52 xl:h-52 flex items-center justify-center">
                {{-- Floating Cloud Base --}}
                <div class="absolute -bottom-2 w-44 h-14 bg-white/15 backdrop-blur-md rounded-full blur-xs"></div>

                {{-- Orbiting Data Cards --}}
                <div class="absolute -top-1 -left-2 px-2 py-1 rounded-md bg-white/15 backdrop-blur-md border border-white/25 text-[10px] font-semibold text-white shadow-md flex items-center gap-1 animate-bounce [animation-duration:3s]">
                    <span class="text-emerald-300 font-bold">✓</span> Naskah Sah & Tervalidasi
                </div>

                <div class="absolute bottom-4 -right-1 px-2 py-1 rounded-md bg-white/15 backdrop-blur-md border border-white/25 text-[10px] font-semibold text-white shadow-md flex items-center gap-1 animate-pulse [animation-duration:2.5s]">
                    <span class="text-amber-300 font-bold">★</span> Kriteria 1 - 9 BAN-PT
                </div>

                {{-- Rocket SVG --}}
                <svg class="w-full h-full drop-shadow-xl transition-transform duration-500 hover:scale-105" viewBox="0 0 320 320" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <!-- Clouds Left & Right -->
                    <path d="M40 250C30 250 20 240 22 230C25 215 45 210 55 220C62 205 85 205 92 222C100 220 110 230 105 242C102 248 95 250 88 250H40Z" fill="white" fill-opacity="0.35"/>
                    <path d="M210 260C200 260 190 250 192 240C195 225 215 220 225 230C232 215 255 215 262 232C270 230 280 240 275 252C272 258 265 260 258 260H210Z" fill="white" fill-opacity="0.3"/>
                    <ellipse cx="160" cy="275" rx="90" ry="22" fill="white" fill-opacity="0.25"/>

                    <!-- Rocket Fire Thrust -->
                    <path d="M125 215 Q 160 295 195 215 Q 160 260 125 215 Z" fill="url(#fireGradient)" />
                    <path d="M138 215 Q 160 270 182 215 Q 160 248 138 215 Z" fill="#FDE047" />

                    <!-- Rocket Fins -->
                    <path d="M110 180 L 85 215 Q 115 218 130 195 Z" fill="#EA580C" />
                    <path d="M210 180 L 235 215 Q 205 218 190 195 Z" fill="#EA580C" />

                    <!-- Rocket Body (Pill Capsule Shape) -->
                    <path d="M160 55 C 195 90 205 155 200 215 H 120 C 115 155 125 90 160 55 Z" fill="url(#rocketBodyGradient)" />

                    <!-- Rocket Nose Cone -->
                    <path d="M160 55 C 180 75 192 105 195 125 H 125 C 128 105 140 75 160 55 Z" fill="#DC2626" />

                    <!-- Center Window / Porthole -->
                    <circle cx="160" cy="145" r="26" fill="#0284C7" stroke="white" stroke-width="5" />
                    <circle cx="160" cy="145" r="20" fill="#38BDF8" />
                    <ellipse cx="154" cy="138" rx="6" ry="10" fill="white" fill-opacity="0.7" transform="rotate(-30 154 138)" />

                    <!-- Rocket Details / Trim -->
                    <rect x="135" y="195" width="50" height="6" rx="3" fill="#DC2626" />

                    <!-- Little Floating Stars / Sparkles -->
                    <path d="M65 90 L 70 85 L 75 90 L 70 95 Z" fill="#FDE047" />
                    <path d="M255 110 L 260 103 L 265 110 L 260 117 Z" fill="#FDE047" />
                    <circle cx="270" cy="70" r="3" fill="white" />
                    <circle cx="50" cy="150" r="2.5" fill="white" />

                    <defs>
                        <linearGradient id="rocketBodyGradient" x1="120" y1="55" x2="200" y2="215" gradientUnits="userSpaceOnUse">
                            <stop stop-color="#FFFFFF" />
                            <stop offset="0.7" stop-color="#F1F5F9" />
                            <stop offset="1" stop-color="#CBD5E1" />
                        </linearGradient>
                        <linearGradient id="fireGradient" x1="160" y1="215" x2="160" y2="295" gradientUnits="userSpaceOnUse">
                            <stop stop-color="#F97316" />
                            <stop offset="0.6" stop-color="#EF4444" />
                            <stop offset="1" stop-color="#DC2626" stop-opacity="0" />
                        </linearGradient>
                    </defs>
                </svg>
            </div>
        </div>

        {{-- Bottom Footer: Trust Badges (Desktop Only - Compact Grid) --}}
        <div class="relative z-10 pt-3 border-t border-white/15 hidden lg:grid grid-cols-3 gap-2.5">
            <div class="flex items-center gap-2 p-2 rounded-lg bg-white/10 backdrop-blur-md border border-white/10 hover:bg-white/15 transition">
                <div class="w-7 h-7 rounded-md bg-blue-500/30 flex items-center justify-center text-sm text-emerald-300 flex-shrink-0">
                    🛡️
                </div>
                <div class="min-w-0">
                    <p class="text-[11px] font-bold text-white truncate">TTE Digital Sah</p>
                    <p class="text-[9.5px] text-blue-200 truncate">QR Verifikasi & Stempel</p>
                </div>
            </div>

            <div class="flex items-center gap-2 p-2 rounded-lg bg-white/10 backdrop-blur-md border border-white/10 hover:bg-white/15 transition">
                <div class="w-7 h-7 rounded-md bg-blue-500/30 flex items-center justify-center text-sm text-amber-300 flex-shrink-0">
                    🏛️
                </div>
                <div class="min-w-0">
                    <p class="text-[11px] font-bold text-white truncate">Standar 9 Kriteria</p>
                    <p class="text-[9.5px] text-blue-200 truncate">BAN-PT & LAM Borang</p>
                </div>
            </div>

            <div class="flex items-center gap-2 p-2 rounded-lg bg-white/10 backdrop-blur-md border border-white/10 hover:bg-white/15 transition">
                <div class="w-7 h-7 rounded-md bg-blue-500/30 flex items-center justify-center text-sm text-cyan-300 flex-shrink-0">
                    ⚡
                </div>
                <div class="min-w-0">
                    <p class="text-[11px] font-bold text-white truncate">Audit Trail Aktif</p>
                    <p class="text-[9.5px] text-blue-200 truncate">Pencatatan Log Otomatis</p>
                </div>
            </div>
        </div>
    </div>

    {{-- ========================================================================= --}}
    {{-- KOLOM KANAN: FLOATING LOGIN CARD (BERSIH & SEMI-TRANSPARAN DI MOBILE)     --}}
    {{-- ========================================================================= --}}
    <div class="custom-login-card-area relative z-10 lg:col-span-5 flex flex-col items-center justify-center p-3 sm:p-5 lg:px-6 lg:py-4 xl:px-8 xl:py-6 min-h-0 lg:h-screen lg:max-h-screen my-auto">
        {{-- Floating Glassmorphic Card Container (Compact Fit untuk 14 Inch) --}}
        <div class="custom-login-card w-full max-w-[390px] xl:max-w-[420px] rounded-2xl p-4 sm:p-5 lg:p-5 xl:p-6 transition-all">
            {{-- Header Row: "Selamat Datang di ..." & Dark Mode Toggle --}}
            <div class="flex items-start justify-between gap-3 mb-3 sm:mb-4">
                <div>
                    <p class="text-[10px] sm:text-xs font-semibold text-blue-600 dark:text-blue-400 tracking-wider uppercase">
                        Selamat Datang di
                    </p>
                    <h2 class="text-xl sm:text-2xl font-black tracking-tight text-slate-900 dark:text-white mt-0.5">
                        {{ $brandName }}
                    </h2>
                    <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-0.5">
                        Silakan gunakan akun resmi Anda untuk masuk
                    </p>
                </div>

                {{-- Interactive Theme Toggle Button --}}
                <div
                    x-data="{
                        isDark: document.documentElement.classList.contains('dark'),
                        toggle() {
                            this.isDark = !this.isDark;
                            const newTheme = this.isDark ? 'dark' : 'light';
                            localStorage.setItem('filament:theme:mode', newTheme);
                            if (this.isDark) {
                                document.documentElement.classList.add('dark');
                            } else {
                                document.documentElement.classList.remove('dark');
                            }
                            $dispatch('theme-changed', newTheme);
                        }
                    }"
                    class="flex-shrink-0"
                >
                    <button
                        type="button"
                        x-on:click="toggle()"
                        class="p-2 rounded-lg bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-600 dark:text-slate-300 transition-colors shadow-2xs"
                        title="Ganti Mode Gelap / Terang"
                        aria-label="Toggle Dark Mode"
                    >
                        {{-- Sun Icon (Shown in Dark Mode) --}}
                        <svg x-show="isDark" class="w-3.5 h-3.5 text-amber-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z" />
                        </svg>
                        {{-- Moon Icon (Shown in Light Mode) --}}
                        <svg x-show="!isDark" class="w-3.5 h-3.5 text-slate-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z" />
                        </svg>
                    </button>
                </div>
            </div>

            {{-- Divider --}}
            <div class="h-px bg-slate-200/80 dark:bg-slate-800/80 w-full mb-3 sm:mb-4"></div>

            {{-- Form Wrapper: Injects Filament Form Components --}}
            <div class="fi-custom-login-form">
                {{ $this->content }}
            </div>

            {{-- Security & Verification Note at Bottom of Card --}}
            <div class="mt-3.5 pt-2.5 border-t border-slate-200/80 dark:border-slate-800/80 text-center">
                <div class="flex items-center justify-center gap-1.5 text-[10.5px] text-slate-600 dark:text-slate-400 font-medium">
                    <svg class="w-3 h-3 text-blue-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                    </svg>
                    <span>Koneksi Aman & Akses Terproteksi SSL</span>
                </div>
                <p class="text-[9.5px] text-slate-500 dark:text-slate-400 mt-0.5">
                    Lembaga Penjaminan Mutu & UPT ICT • Universitas Bina Insan
                </p>
            </div>
        </div>

        {{-- Mobile-Only Compact Trust Badges (Stacked under the Card on Blue Canvas) --}}
        <div class="flex lg:hidden flex-wrap items-center justify-center gap-2 text-[10.5px] text-blue-100 font-medium mt-3 pb-1">
            <span class="inline-flex items-center gap-1 bg-white/10 backdrop-blur-md px-2 py-0.5 rounded-md border border-white/15 shadow-2xs">
                🛡️ TTE Sah
            </span>
            <span class="inline-flex items-center gap-1 bg-white/10 backdrop-blur-md px-2 py-0.5 rounded-md border border-white/15 shadow-2xs">
                🏛️ 9 Kriteria BAN-PT
            </span>
            <span class="inline-flex items-center gap-1 bg-white/10 backdrop-blur-md px-2 py-0.5 rounded-md border border-white/15 shadow-2xs">
                ⚡ Audit Trail
            </span>
        </div>

        {{-- Sub-Footer: Informasi Developer & Hak Cipta Resmi (Anti-Tamper) --}}
        <div class="w-full max-w-[390px] xl:max-w-[420px] mt-2.5 pt-2 border-t border-white/20 lg:border-slate-200/80 dark:border-slate-800/80 text-center text-xs text-blue-200 lg:text-slate-500 dark:text-slate-400 space-y-1 transition-colors">
            <div class="flex flex-wrap items-center justify-center gap-1 text-[10.5px]">
                <span>Dikembangkan oleh:</span>
                <span class="font-bold text-white lg:text-slate-800 dark:text-slate-200">{{ $sealDevName }}</span>
                <span>•</span>
                <a href="mailto:{{ $sealDevEmail }}" class="text-blue-300 lg:text-blue-600 dark:text-blue-400 hover:underline font-medium inline-flex items-center gap-0.5">
                    <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                    </svg>
                    {{ $sealDevEmail }}
                </a>
                @if($sealDevWeb)
                    <span>•</span>
                    <a href="{{ $sealDevWeb }}" target="_blank" rel="noopener noreferrer" class="hover:text-white lg:hover:text-slate-800 dark:hover:text-slate-200 transition-colors inline-flex items-center gap-0.5">
                        <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                        </svg>
                        Portofolio / Web
                    </a>
                @endif
            </div>

            <div class="text-[9.5px] text-blue-200/80 lg:text-slate-400 dark:text-slate-500">
                {{ $sealCopyright }}
            </div>
        </div>
    </div>

    {{-- Fallback Explicit CSS Rules for Direct Layout, Fields, & Buttons --}}
    <style>
        /* Overall Page Layout */
        .custom-login-container {
            min-height: 100vh;
            width: 100%;
            display: flex;
            flex-direction: column;
            background: linear-gradient(135deg, #1d4ed8 0%, #2563eb 40%, #0f172a 100%) !important;
        }

        /* Mobile Glassmorphic Card (Semi-transparent & stacked directly on blue background) */
        .custom-login-card {
            background: rgba(255, 255, 255, 0.90) !important;
            backdrop-filter: blur(20px) !important;
            -webkit-backdrop-filter: blur(20px) !important;
            border: 1px solid rgba(255, 255, 255, 0.6) !important;
            box-shadow: 0 20px 50px -10px rgba(0, 0, 0, 0.35) !important;
        }
        html.dark .custom-login-card {
            background: rgba(15, 23, 42, 0.84) !important;
            backdrop-filter: blur(20px) !important;
            -webkit-backdrop-filter: blur(20px) !important;
            border: 1px solid rgba(255, 255, 255, 0.14) !important;
            box-shadow: 0 20px 50px -10px rgba(0, 0, 0, 0.6) !important;
        }

        /* Desktop Split Layout Rules (>= 1024px) */
        @media (min-width: 1024px) {
            .custom-login-container {
                display: grid !important;
                grid-template-columns: repeat(12, minmax(0, 1fr)) !important;
                background: #f8fafc !important;
                height: 100vh !important;
                max-height: 100vh !important;
                overflow: hidden !important;
            }
            html.dark .custom-login-container {
                background: #020617 !important;
            }
            .custom-login-hero {
                grid-column: span 7 / span 7 !important;
                background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 45%, #0f172a 100%) !important;
                height: 100vh !important;
                max-height: 100vh !important;
                overflow: hidden !important;
            }
            .custom-login-card-area {
                grid-column: span 5 / span 5 !important;
                background-color: rgba(241, 245, 249, 0.7) !important;
                height: 100vh !important;
                max-height: 100vh !important;
                overflow-y: auto !important;
                display: flex !important;
                flex-direction: column !important;
                justify-content: center !important;
                align-items: center !important;
            }
            html.dark .custom-login-card-area {
                background-color: #020617 !important;
            }
            .custom-login-card {
                background: #ffffff !important;
                border: 1px solid #e2e8f0 !important;
                box-shadow: 0 15px 35px -10px rgba(15, 23, 42, 0.12) !important;
            }
            html.dark .custom-login-card {
                background: #0f172a !important;
                border: 1px solid #1e293b !important;
                box-shadow: 0 15px 35px -10px rgba(0, 0, 0, 0.45) !important;
            }
        }

        /* Field Wrapper Margin (Ringkas & Proporsional untuk Layar 14 Inci) */
        .fi-custom-login-form .fi-fo-field-wrp {
            margin-bottom: 0.65rem !important;
        }

        /* Field Labels */
        .fi-custom-login-form .fi-fo-field-wrp-label span,
        .fi-custom-login-form label span {
            font-size: 0.78rem !important;
            font-weight: 600 !important;
            color: #334155 !important; /* slate-700 */
        }
        html.dark .fi-custom-login-form .fi-fo-field-wrp-label span,
        html.dark .fi-custom-login-form label span {
            color: #cbd5e1 !important; /* slate-300 */
        }

        /* Input Container Box (Light Mode & Dark Mode) */
        .fi-custom-login-form .fi-input-wrp {
            display: flex !important;
            align-items: center !important;
            border-radius: 0.625rem !important;
            background-color: #f8fafc !important; /* slate-50 in Light Mode */
            border: 1.5px solid #cbd5e1 !important; /* slate-300 clear border in Light Mode */
            box-shadow: 0 1px 2px 0 rgba(15, 23, 42, 0.05) !important;
            transition: all 0.2s ease-in-out !important;
            overflow: hidden !important;
        }
        .fi-custom-login-form .fi-input-wrp:hover {
            border-color: #94a3b8 !important; /* slate-400 */
            background-color: #ffffff !important;
        }
        .fi-custom-login-form .fi-input-wrp:focus-within {
            border-color: #2563eb !important; /* blue-600 */
            background-color: #ffffff !important;
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.15), 0 1px 2px 0 rgba(15, 23, 42, 0.05) !important;
        }

        /* Dark Mode Input Container Box */
        html.dark .fi-custom-login-form .fi-input-wrp {
            background-color: #1e293b !important; /* slate-800 */
            border: 1.5px solid #334155 !important; /* slate-700 */
            box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.3) !important;
        }
        html.dark .fi-custom-login-form .fi-input-wrp:hover {
            border-color: #475569 !important; /* slate-600 */
            background-color: #1e293b !important;
        }
        html.dark .fi-custom-login-form .fi-input-wrp:focus-within {
            border-color: #3b82f6 !important; /* blue-500 */
            background-color: #0f172a !important; /* slate-900 */
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.25) !important;
        }

        /* Actual Text Inputs: Proporsional & Pas di Layar 14 Inci */
        .fi-custom-login-form .fi-input-wrp input,
        .fi-custom-login-form input[type="text"],
        .fi-custom-login-form input[type="email"],
        .fi-custom-login-form input[type="password"] {
            display: block !important;
            width: 100% !important;
            padding: 0.5rem 0.85rem !important;
            font-size: 0.875rem !important;
            line-height: 1.35 !important;
            color: #0f172a !important; /* slate-900 */
            background: transparent !important;
            border: none !important;
            outline: none !important;
            box-shadow: none !important;
        }
        html.dark .fi-custom-login-form .fi-input-wrp input,
        html.dark .fi-custom-login-form input[type="text"],
        html.dark .fi-custom-login-form input[type="email"],
        html.dark .fi-custom-login-form input[type="password"] {
            color: #f8fafc !important; /* slate-50 */
        }

        .fi-custom-login-form input::placeholder {
            color: #94a3b8 !important; /* slate-400 */
        }
        html.dark .fi-custom-login-form input::placeholder {
            color: #64748b !important; /* slate-500 */
        }

        /* Password Reveal Suffix Button */
        .fi-custom-login-form .fi-input-wrp-suffix,
        .fi-custom-login-form .fi-input-wrp-actions {
            padding-right: 0.75rem !important;
            display: flex !important;
            align-items: center !important;
        }
        .fi-custom-login-form .fi-input-wrp-suffix button,
        .fi-custom-login-form .fi-input-wrp-actions button {
            color: #64748b !important;
            padding: 0.2rem !important;
            border-radius: 0.375rem !important;
            transition: color 0.15s ease-in-out !important;
        }
        .fi-custom-login-form .fi-input-wrp-suffix button:hover,
        .fi-custom-login-form .fi-input-wrp-actions button:hover {
            color: #1e293b !important;
        }
        html.dark .fi-custom-login-form .fi-input-wrp-suffix button:hover,
        html.dark .fi-custom-login-form .fi-input-wrp-actions button:hover {
            color: #f1f5f9 !important;
        }

        /* Checkbox Styling */
        .fi-custom-login-form .fi-checkbox-wrp {
            display: flex !important;
            align-items: center !important;
            gap: 0.5rem !important;
            margin-top: 0.2rem !important;
            margin-bottom: 0.65rem !important;
        }
        .fi-custom-login-form .fi-checkbox-wrp label span {
            font-size: 0.78rem !important;
        }
        .fi-custom-login-form .fi-checkbox-wrp input[type="checkbox"] {
            width: 1rem !important;
            height: 1rem !important;
            border-radius: 0.3rem !important;
            border: 1.5px solid #cbd5e1 !important;
            color: #2563eb !important;
        }
        html.dark .fi-custom-login-form .fi-checkbox-wrp input[type="checkbox"] {
            border-color: #475569 !important;
            background-color: #1e293b !important;
        }

        /* Action Submit Button (Presisi & Ringkas) */
        .fi-custom-login-form .fi-btn {
            width: 100% !important;
            background-color: #2563eb !important;
            color: #ffffff !important;
            border-radius: 0.625rem !important;
            padding: 0.6rem 1.25rem !important;
            font-size: 0.875rem !important;
            font-weight: 700 !important;
            letter-spacing: 0.01em !important;
            box-shadow: 0 4px 14px -3px rgba(37, 99, 235, 0.4) !important;
            transition: all 0.2s ease-in-out !important;
            justify-content: center !important;
            display: inline-flex !important;
            align-items: center !important;
            cursor: pointer !important;
        }
        .fi-custom-login-form .fi-btn:hover {
            background-color: #1d4ed8 !important;
            transform: translateY(-1px);
            box-shadow: 0 8px 18px -3px rgba(37, 99, 235, 0.5) !important;
        }
    </style>
</div>
