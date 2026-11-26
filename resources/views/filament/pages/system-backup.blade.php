<x-filament-panels::page>
    <link rel="stylesheet"
        href="{{ asset('css/system-backup.css') }}?v={{ @filemtime(public_path('css/system-backup.css')) ?: time() }}">

    @php
        $summary = $this->getStorageSummary();
        $schedule = $this->getScheduleConfig();
        $backups = $this->getBackups();
        $recentLogs = $this->getRecentBackupLogs();
        $canRestore = \App\Filament\Pages\SystemBackup::canRestore();
        $driver = strtoupper($this->getActiveDatabaseDriver());
    @endphp

    <div class="sb-page">
        {{-- Banner Keamanan & Ringkasan --}}
        <div class="sb-banner">
            <div class="sb-banner-content">
                <div class="sb-banner-left">
                    <div class="sb-banner-icon">
                        <x-filament::icon icon="heroicon-o-shield-check" />
                    </div>
                    <div>
                        <div class="sb-banner-title-row">
                            <h2 class="sb-banner-title">
                                Pusat Backup &amp; Pemulihan Sistem Terpadu (DB + Storage)
                            </h2>
                            <x-filament::badge color="success" size="xs">
                                Khusus Super Admin
                            </x-filament::badge>
                            <x-filament::badge :color="$schedule['enabled'] ? 'info' : 'gray'" size="xs">
                                Jadwal: {{ $this->formatScheduleHuman($schedule) }}
                            </x-filament::badge>
                        </div>
                        <p class="sb-banner-desc">
                            Mendukung mode <strong>Database SQL Saja ({{ $driver }})</strong> untuk backup harian
                            super cepat maupun paket lengkap
                            <strong>Database + storage/</strong> (arsip <code>private</code> &amp; <code>public</code>)
                            dalam arsip <code>.zip</code>.
                            Untuk data dokumen bergiga-giga, pisahkan jadwal DB harian di sini dan sinkronkan folder
                            <code>storage/</code> via <code>rclone</code> / <code>rsync</code>.
                        </p>
                    </div>
                </div>

                @if (!empty($schedule['last_scheduled_run_at']))
                    <div class="sb-last-run-box">
                        <div class="sb-last-run-label">Eksekusi Terjadwal Terakhir</div>
                        <div>
                            {{ \Carbon\Carbon::parse($schedule['last_scheduled_run_at'])->timezone('Asia/Jakarta')->translatedFormat('d M Y, H:i') }}
                            WIB</div>
                    </div>
                @endif
            </div>
        </div>

        {{-- Kartu Ringkasan Penyimpanan --}}
        <div class="sb-stats-grid">
            <div class="sb-stat-card">
                <div class="sb-stat-label">Total Paket Backup</div>
                <div class="sb-stat-bottom">
                    <span class="sb-stat-value">{{ $summary['backup_count'] }}</span>
                    <span class="sb-stat-meta">Arsip .ZIP</span>
                </div>
            </div>

            <div class="sb-stat-card">
                <div class="sb-stat-label">Ruang Disk Backup</div>
                <div class="sb-stat-bottom">
                    <span class="sb-stat-value sb-stat-value--primary">{{ $summary['backup_human'] }}</span>
                    <span class="sb-stat-meta">disk: backups</span>
                </div>
            </div>

            <div class="sb-stat-card">
                <div class="sb-stat-label">Arsip Storage Aktif</div>
                <div class="sb-stat-bottom">
                    <span class="sb-stat-value">{{ $summary['storage_human'] }}</span>
                    <span class="sb-stat-meta">
                        {{ number_format($summary['private_files'] + $summary['public_files']) }} berkas
                    </span>
                </div>
            </div>

            <div class="sb-stat-card">
                <div class="sb-stat-label">Koneksi Database Aktif</div>
                <div class="sb-stat-bottom">
                    <span class="sb-stat-value sb-stat-value--emerald">{{ $driver }}</span>
                    <span class="sb-stat-meta">Siap Dump &amp; Restore</span>
                </div>
            </div>
        </div>

        {{-- Daftar Paket Backup di Server --}}
        <div class="sb-panel">
            <div class="sb-panel-header">
                <div>
                    <h3 class="sb-panel-title">Daftar Paket Backup Tersimpan</h3>
                    <p class="sb-panel-subtitle">Lokasi:
                        <code>storage/app/backups/{{ config('backup.backup.name') }}</code></p>
                </div>
                <x-filament::badge color="gray" size="xs">
                    {{ count($backups) }} paket
                </x-filament::badge>
            </div>

            @if (empty($backups))
                <div class="sb-empty">
                    <x-filament::icon icon="heroicon-o-archive-box" class="sb-empty-icon" />
                    <p class="sb-empty-title">Belum ada paket backup tersimpan.</p>
                    <p class="sb-empty-desc">
                        Klik tombol <strong>Buat Backup Sekarang (DB + Storage)</strong> di kanan atas untuk membuat
                        paket cadangan pertama.
                    </p>
                </div>
            @else
                <div class="sb-table-wrap">
                    <table class="sb-table">
                        <thead>
                            <tr>
                                <th>Nama Paket (.ZIP)</th>
                                <th>Waktu Dibuat (WIB)</th>
                                <th>Ukuran</th>
                                <th>Isi &amp; Keamanan Arsip</th>
                                <th style="text-align: right;">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($backups as $backup)
                                <tr>
                                    <td>
                                        <span class="sb-filename">{{ $backup['file_name'] }}</span>
                                    </td>
                                    <td>
                                        <div class="sb-time-main">
                                            {{ $backup['created_at']->translatedFormat('d M Y, H:i:s') }} WIB</div>
                                        <div class="sb-time-sub">{{ $backup['created_at']->diffForHumans() }}</div>
                                    </td>
                                    <td>
                                        <span class="sb-size">{{ $backup['size_human'] }}</span>
                                    </td>
                                    <td>
                                        <div class="sb-badges">
                                            @if (($backup['scope'] ?? '') === \App\Services\SystemBackupService::SCOPE_DATABASE_ONLY)
                                                <x-filament::badge color="primary" size="xs">DB SQL
                                                    Saja</x-filament::badge>
                                            @elseif (($backup['scope'] ?? '') === \App\Services\SystemBackupService::SCOPE_FULL)
                                                <x-filament::badge color="gray" size="xs">Lengkap (DB +
                                                    Storage)</x-filament::badge>
                                            @endif
                                            @if ($backup['has_database'])
                                                <x-filament::badge color="info" size="xs">Database
                                                    SQL</x-filament::badge>
                                            @endif
                                            @if ($backup['has_storage'])
                                                <x-filament::badge color="success" size="xs">
                                                    Storage ({{ number_format($backup['storage_files_count']) }} file)
                                                </x-filament::badge>
                                            @endif
                                            @if (!$backup['is_safe'])
                                                <x-filament::badge color="danger" size="xs">
                                                    Diblokir Keamanan
                                                </x-filament::badge>
                                            @endif
                                        </div>
                                        @if (!$backup['is_safe'] && !empty($backup['security_note']))
                                            <p class="sb-security-note">{{ $backup['security_note'] }}</p>
                                        @endif
                                    </td>
                                    <td>
                                        <div class="sb-actions">
                                            <a href="{{ route('admin.system-backups.download', ['filename' => $backup['file_name']]) }}"
                                                class="sb-download-btn">
                                                <x-filament::icon icon="heroicon-o-arrow-down-tray" />
                                                Unduh
                                            </a>

                                            @if ($canRestore && $backup['is_safe'])
                                                <x-filament::button size="xs" color="warning"
                                                    icon="heroicon-o-arrow-path"
                                                    wire:click="mountAction('restoreBackup', {{ \Illuminate\Support\Js::from(['filename' => $backup['file_name']]) }})">
                                                    Restore
                                                </x-filament::button>
                                            @endif

                                            <x-filament::button size="xs" color="danger" outlined
                                                icon="heroicon-o-trash"
                                                wire:click="mountAction('deleteBackup', {{ \Illuminate\Support\Js::from(['filename' => $backup['file_name']]) }})">
                                                Hapus
                                            </x-filament::button>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        {{-- Riwayat Aktivitas Backup & Restore --}}
        <div class="sb-panel">
            <div class="sb-panel-header">
                <h3 class="sb-panel-title">Riwayat Audit Backup &amp; Restore Terbaru</h3>
            </div>

            @if ($recentLogs->isEmpty())
                <div class="sb-log-empty">
                    Belum ada aktivitas backup atau restore yang tercatat.
                </div>
            @else
                <div class="sb-log-list">
                    @foreach ($recentLogs as $log)
                        <div class="sb-log-item">
                            <div class="sb-log-main">
                                <x-filament::badge :color="match ($log->event) {
                                    'backup_created' => 'success',
                                    'backup_restored' => 'warning',
                                    'backup_failed', 'restore_failed', 'restore_security_blocked' => 'danger',
                                    'backup_deleted' => 'gray',
                                    default => 'info',
                                }" size="xs">
                                    {{ $log->event }}
                                </x-filament::badge>
                                <span class="sb-log-desc">{{ $log->description }}</span>
                            </div>
                            <div class="sb-log-meta">
                                {{ $log->causer?->name ?? 'Sistem / Scheduler' }} •
                                {{ $log->created_at?->timezone('Asia/Jakarta')->translatedFormat('d M Y, H:i') }} WIB
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</x-filament-panels::page>
