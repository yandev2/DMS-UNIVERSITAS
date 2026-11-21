<x-filament-widgets::widget class="fi-dashboard-welcome-banner db-welcome-banner">
    <div
        x-data="{
            showLogoutModal: false,
            time: '',
            date: '',
            updateClock() {
                const now = new Date();
                this.time = now.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit', second: '2-digit' }) + ' WIB';
                this.date = now.toLocaleDateString('id-ID', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' });
            }
        }"
        x-init="updateClock(); setInterval(() => updateClock(), 1000)"
        class="db-banner-wrapper"
    >
        {{-- Card Banner Utama --}}
        <div class="db-banner-card">
            {{-- Ambient decorative background glow --}}
            <div class="db-banner-glow-1"></div>
            <div class="db-banner-glow-2"></div>

            {{-- SISI PALING KANAN POJOK ATAS: Button Logout --}}
            <div class="db-logout-top-corner">
                <button
                    type="button"
                    @click="showLogoutModal = true"
                    class="db-logout-btn"
                    title="Keluar dari akun dan sistem"
                >
                    <svg style="width: 16px; height: 16px; min-width: 16px; min-height: 16px; flex-shrink: 0;" viewBox="0 0 24 24" fill="none" stroke="currentColor" class="db-logout-icon">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                    </svg>
                    <span>Keluar</span>
                </button>
            </div>

            {{-- KONTEN BANNER: Sisi Kiri (User Info) & Sisi Kanan (Jam & Tanggal) --}}
            <div class="db-banner-body">
                {{-- SISI KIRI: Informasi User --}}
                <div class="db-profile-section">
                    {{-- Avatar / Logo --}}
                    <div class="db-avatar-wrap">
                        @if($avatarUrl)
                            <img src="{{ $avatarUrl }}" alt="{{ $userName }}" class="db-avatar-img">
                        @elseif($logoUrl)
                            <div class="db-avatar-logo">
                                <img src="{{ $logoUrl }}" alt="Logo" style="max-width: 100%; max-height: 100%; object-fit: contain;">
                            </div>
                        @else
                            <div class="db-avatar-fallback">
                                {{ $initials }}
                            </div>
                        @endif
                    </div>

                    {{-- Data Diri User --}}
                    <div class="db-profile-details">
                        <div class="db-greeting-row">
                            <span class="db-greeting-text">
                                {{ $greetingTime }}, Selamat Datang 👋
                            </span>
                            <span class="db-role-badge">
                                {{ $userRoles }}
                            </span>
                        </div>

                        <h1 class="db-user-name">
                            {{ $userName }}
                        </h1>

                        <div class="db-meta-row">
                            <span class="db-meta-title">
                                {{ $positionTitle }}
                            </span>
                            @if($unitName && $unitName !== $positionTitle)
                                <span class="db-meta-dot">•</span>
                                <span>{{ $unitName }}</span>
                            @endif
                            @if($nidnNip)
                                <span class="db-meta-dot">•</span>
                                <span>NIDN/NIP: {{ $nidnNip }}</span>
                            @endif
                        </div>
                    </div>
                </div>

                {{-- SISI KANAN: Jam Digital & Tanggal Indonesia --}}
                <div class="db-clock-card">
                    <div class="db-clock-header">
                        <span class="db-clock-status">
                            <span class="db-pulse-dot">
                                <span class="db-pulse-ping"></span>
                                <span class="db-pulse-solid"></span>
                            </span>
                            Waktu Sistem
                        </span>
                        <svg style="width: 16px; height: 16px; min-width: 16px; min-height: 16px; flex-shrink: 0;" viewBox="0 0 24 24" fill="none" stroke="currentColor" class="db-clock-icon">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>

                    {{-- Live Digital Clock --}}
                    <div class="db-clock-time" x-text="time">
                        {{ now()->translatedFormat('H:i:s') }} WIB
                    </div>

                    {{-- Live Date --}}
                    <div class="db-clock-date" x-text="date">
                        {{ now()->translatedFormat('l, d F Y') }}
                    </div>
                </div>
            </div>

            {{-- BAGIAN BAWAH CARD WELCOME: DAFTAR PINTASAN WEB PRIBADI (MINI-CARD TILE NON-PILL) --}}
            @php
                $shortcutItems = $shortcuts ?? collect();
            @endphp
            @if($shortcutItems->isNotEmpty())
                <div class="db-shortcuts-section">
                    <div class="db-shortcuts-header">
                        <div class="db-shortcuts-heading-wrap">
                            <span class="db-shortcuts-heading-icon">
                                <svg style="width: 13px; height: 13px;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1" />
                                </svg>
                            </span>
                            <span class="db-shortcuts-heading-text">Pintasan Web Saya</span>
                            <span class="db-shortcuts-count-tag">{{ $shortcutItems->count() }}/{{ \App\Models\UserShortcut::MAX_PER_USER }}</span>
                        </div>

                        @if(!empty($manageShortcutsUrl))
                            <a href="{{ $manageShortcutsUrl }}" class="db-shortcuts-manage-link">
                                <span>Kelola Pintasan</span>
                                <svg style="width: 13px; height: 13px;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                                </svg>
                            </a>
                        @endif
                    </div>

                    <div class="db-shortcuts-grid">
                        @foreach($shortcutItems as $shortcut)
                            <a
                                href="{{ $shortcut->url }}"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="db-shortcut-tile"
                                title="{{ $shortcut->title }} ({{ $shortcut->url }})"
                            >
                                <div class="db-shortcut-icon-box">
                                    <span>{{ $shortcut->getInitial() }}</span>
                                </div>

                                <div class="db-shortcut-content">
                                    <div class="db-shortcut-title">{{ $shortcut->title }}</div>
                                    <div class="db-shortcut-domain">{{ $shortcut->getHostDomain() }}</div>
                                </div>

                                <div class="db-shortcut-arrow">
                                    <svg style="width: 14px; height: 14px;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M7 17L17 7M17 7H8M17 7V16" />
                                    </svg>
                                </div>
                            </a>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>

        {{-- MODAL KONFIRMASI LOGOUT --}}
        <div
            x-show="showLogoutModal"
            x-cloak
            @keydown.escape.window="showLogoutModal = false"
            class="db-modal-overlay"
            style="display: none;"
        >
            {{-- Backdrop click area --}}
            <div
                class="db-modal-backdrop"
                @click="showLogoutModal = false"
            ></div>

            {{-- Modal Card --}}
            <div
                class="db-modal-card"
                x-show="showLogoutModal"
                x-transition:enter="db-transition-enter"
                x-transition:enter-start="db-transition-enter-start"
                x-transition:enter-end="db-transition-enter-end"
                x-transition:leave="db-transition-leave"
                x-transition:leave-start="db-transition-leave-start"
                x-transition:leave-end="db-transition-leave-end"
                @click.stop
            >
                <div class="db-modal-body">
                    {{-- Icon Warning / Logout --}}
                    <div class="db-modal-icon-wrap">
                        <svg style="width: 28px; height: 28px; min-width: 28px; min-height: 28px; flex-shrink: 0;" viewBox="0 0 24 24" fill="none" stroke="currentColor" class="db-modal-icon">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                        </svg>
                    </div>

                    <div class="db-modal-text">
                        <h3 class="db-modal-title">Konfirmasi Keluar Sistem</h3>
                        <p class="db-modal-desc">
                            Apakah Anda yakin ingin mengakhiri sesi kerja Anda di Portal E-Arsip & Borang UNIVBI? Sesi aktif Anda akan ditutup.
                        </p>
                    </div>
                </div>

                {{-- Action Buttons --}}
                <div class="db-modal-actions">
                    <button
                        type="button"
                        @click="showLogoutModal = false"
                        class="db-modal-btn-cancel"
                    >
                        Batal
                    </button>

                    <form action="{{ $logoutUrl }}" method="post" style="display: inline-flex; margin: 0;">
                        @csrf
                        <button
                            type="submit"
                            class="db-modal-btn-confirm"
                        >
                            <svg style="width: 16px; height: 16px; min-width: 16px; min-height: 16px; flex-shrink: 0;" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                            </svg>
                            <span>Ya, Keluar</span>
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-filament-widgets::widget>
