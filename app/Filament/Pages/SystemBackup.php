<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Jobs\RunSystemBackupJob;
use App\Jobs\RunSystemRestoreJob;
use App\Models\User;
use App\Services\SystemBackupService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Spatie\Activitylog\Models\Activity;
use Throwable;
use UnitEnum;

class SystemBackup extends Page
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-server-stack';

    protected static string|UnitEnum|null $navigationGroup = 'Keamanan & Audit Trail';

    protected static ?string $navigationLabel = 'Backup & Restore Sistem';

    protected static ?string $title = 'Backup & Restore Sistem Terpadu';

    protected static ?string $slug = 'system-backup';

    protected static ?int $navigationSort = 2;

    protected string $view = 'filament.pages.system-backup';

    public static function canAccess(): bool
    {
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        return $user->hasRole('super_admin') && $user->can('system.backup_manage');
    }

    public static function canRestore(): bool
    {
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        return $user->hasRole('super_admin') && $user->can('system.backup_restore');
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);
    }

    /**
     * @return array<int, Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('createBackup')
                ->label('Buat Backup Baru')
                ->icon('heroicon-o-archive-box-arrow-down')
                ->color('primary')
                ->modalHeading('Buat Paket Backup Sistem')
                ->modalDescription('Disarankan menggunakan mode "Database SQL Saja" untuk backup harian cepat, sedangkan cadangan folder storage/ (arsip multi-GB) sebaiknya disinkronkan secara inkremental menggunakan rclone/rsync server.')
                ->modalSubmitActionLabel('Mulai Backup Sekarang')
                ->schema([
                    Select::make('backup_scope')
                        ->label('Cakupan Backup')
                        ->options([
                            SystemBackupService::SCOPE_DATABASE_ONLY => 'Database SQL Saja (Cepat & Ringan — Disarankan untuk Harian)',
                            SystemBackupService::SCOPE_FULL => 'Lengkap: Database SQL + Folder Storage (Untuk arsip utuh / migrasi)',
                        ])
                        ->default(SystemBackupService::SCOPE_DATABASE_ONLY)
                        ->native(false)
                        ->selectablePlaceholder(false)
                        ->required(),
                    Select::make('execution_mode')
                        ->label('Mode Eksekusi Backup')
                        ->options([
                            'queue' => 'Latar Belakang / Antrean (Queue — Disarankan, anti timeout HTTP)',
                            'sync' => 'Jalankan Langsung Sekarang (Sinkron — Hanya untuk data kecil)',
                        ])
                        ->default('queue')
                        ->native(false)
                        ->selectablePlaceholder(false)
                        ->required(),
                    Toggle::make('run_cleanup')
                        ->label('Jalankan pembersihan retensi lama (backup:clean) setelah selesai')
                        ->default(false),
                ])
                ->action(function (array $data, SystemBackupService $backupService): void {
                    $user = $this->requireManageActor();
                    $rawScope = $data['backup_scope'] ?? SystemBackupService::SCOPE_DATABASE_ONLY;
                    $scope = in_array($rawScope, [SystemBackupService::SCOPE_DATABASE_ONLY, SystemBackupService::SCOPE_FULL], true)
                        ? (string) $rawScope
                        : SystemBackupService::SCOPE_DATABASE_ONLY;
                    $mode = (string) ($data['execution_mode'] ?? 'queue');
                    $runCleanup = (bool) ($data['run_cleanup'] ?? false);
                    $scopeLabel = $scope === SystemBackupService::SCOPE_DATABASE_ONLY ? 'Database SQL Saja' : 'Lengkap DB + Storage';

                    if ($mode === 'queue') {
                        RunSystemBackupJob::dispatch($user->id, 'manual', $runCleanup, $scope);

                        Notification::make()
                            ->title('Proses Backup Dijadwalkan ke Latar Belakang')
                            ->body("Sistem sedang menyusun paket backup ({$scopeLabel}). Anda akan menerima notifikasi lonceng begitu berkas .ZIP selesai dibuat.")
                            ->info()
                            ->send();

                        return;
                    }

                    try {
                        $result = $backupService->runBackup($scope, $user, 'manual');
                        if ($runCleanup) {
                            $backupService->runCleanup($user);
                        }

                        $sizeHuman = $backupService->formatBytes($result['size_bytes']);

                        Notification::make()
                            ->title('Backup Sistem Berhasil')
                            ->body("Paket {$result['file_name']} ({$scopeLabel}, {$sizeHuman}) berhasil dibuat.")
                            ->success()
                            ->send();
                    } catch (Throwable $e) {
                        Notification::make()
                            ->title('Backup Sistem Gagal')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),

            Action::make('configureSchedule')
                ->label('Jadwal Otomatis')
                ->icon('heroicon-o-clock')
                ->color('gray')
                ->modalHeading('Konfigurasi Jadwal Rutin Backup Otomatis')
                ->modalDescription('Atur jadwal backup otomatis sistem berjalan sesuai zona waktu WIB (Asia/Jakarta). Untuk data storage bergiga-giga, disarankan menjadwalkan "Database SQL Saja" di sini dan rclone/rsync di server.')
                ->modalSubmitActionLabel('Simpan Jadwal')
                ->fillForm(fn (SystemBackupService $backupService): array => $backupService->getScheduleConfig())
                ->schema([
                    Toggle::make('enabled')
                        ->label('Aktifkan Jadwal Backup Otomatis')
                        ->live(),
                    Select::make('scope')
                        ->label('Cakupan Backup Terjadwal')
                        ->options([
                            SystemBackupService::SCOPE_DATABASE_ONLY => 'Database SQL Saja (Disarankan untuk harian — ringan & cepat)',
                            SystemBackupService::SCOPE_FULL => 'Lengkap: Database SQL + Folder Storage',
                        ])
                        ->default(SystemBackupService::SCOPE_DATABASE_ONLY)
                        ->native(false)
                        ->selectablePlaceholder(false)
                        ->required()
                        ->visible(fn (Get $get): bool => (bool) $get('enabled')),
                    Select::make('frequency')
                        ->label('Frekuensi Pengulangan')
                        ->options([
                            'daily' => 'Setiap Hari',
                            'weekly' => 'Setiap Minggu (Pilih Hari)',
                            'monthly' => 'Setiap Bulan (Pilih Tanggal)',
                        ])
                        ->native(false)
                        ->selectablePlaceholder(false)
                        ->required()
                        ->live()
                        ->visible(fn (Get $get): bool => (bool) $get('enabled')),
                    Select::make('day_of_week')
                        ->label('Hari Eksekusi (Mingguan)')
                        ->options([
                            0 => 'Minggu',
                            1 => 'Senin',
                            2 => 'Selasa',
                            3 => 'Rabu',
                            4 => 'Kamis',
                            5 => 'Jumat',
                            6 => 'Sabtu',
                        ])
                        ->native(false)
                        ->selectablePlaceholder(false)
                        ->required()
                        ->visible(fn (Get $get): bool => (bool) $get('enabled') && $get('frequency') === 'weekly'),
                    Select::make('day_of_month')
                        ->label('Tanggal Eksekusi (Bulanan, 1-28)')
                        ->options($this->getDayOfMonthOptions())
                        ->native(false)
                        ->selectablePlaceholder(false)
                        ->searchable()
                        ->required()
                        ->visible(fn (Get $get): bool => (bool) $get('enabled') && $get('frequency') === 'monthly'),
                    TextInput::make('time')
                        ->label('Jam Eksekusi (WIB / Asia/Jakarta)')
                        ->type('time')
                        ->required()
                        ->rule('regex:/^(?:[01]\d|2[0-3]):[0-5]\d$/')
                        ->helperText('Format 24 jam HH:MM, contoh: 01:00 atau 23:30 WIB.')
                        ->visible(fn (Get $get): bool => (bool) $get('enabled')),
                    Toggle::make('auto_clean')
                        ->label('Otomatis jalankan pembersihan retensi lama (backup:clean) setelah backup terjadwal')
                        ->visible(fn (Get $get): bool => (bool) $get('enabled')),
                ])
                ->action(function (array $data, SystemBackupService $backupService): void {
                    $user = $this->requireManageActor();
                    $saved = $backupService->saveScheduleConfig($data, $user);

                    $statusText = $saved['enabled']
                        ? "Jadwal aktif ({$this->formatScheduleHuman($saved)})."
                        : 'Jadwal backup otomatis dinonaktifkan.';

                    Notification::make()
                        ->title('Jadwal Backup Diperbarui')
                        ->body($statusText)
                        ->success()
                        ->send();
                }),

            Action::make('uploadAndRestore')
                ->label('Unggah & Restore (.ZIP)')
                ->icon('heroicon-o-arrow-up-tray')
                ->color('warning')
                ->visible(fn (): bool => static::canRestore())
                ->modalHeading('Unggah & Restore dari Paket Backup (.ZIP)')
                ->modalDescription('Unggah berkas paket backup .ZIP dari komputer Anda. Sistem akan memeriksa keamanan arsip (Anti Zip-Slip & pemblokiran file berbahaya seperti .php, .py, .sh, .exe) sebelum pemulihan dilakukan.')
                ->modalSubmitActionLabel('Validasi & Jalankan Restore')
                ->schema([
                    FileUpload::make('archive')
                        ->label('Berkas Paket Backup (.zip)')
                        ->disk('local')
                        ->directory('backup-restore-uploads')
                        ->acceptedFileTypes([
                            'application/zip',
                            'application/x-zip-compressed',
                            'application/x-zip',
                            'multipart/x-zip',
                            'application/octet-stream',
                            '.zip',
                        ])
                        ->rules(['extensions:zip'])
                        ->maxSize(fn (SystemBackupService $backupService): int => $backupService->getEffectiveBrowserUploadLimits()['max_kb'])
                        ->required()
                        ->helperText(function (SystemBackupService $backupService): string {
                            $limits = $backupService->getEffectiveBrowserUploadLimits();
                            $prefix = $limits['is_restricted_by_server']
                                ? "Batas unggah browser saat ini dibatasi server PHP: {$limits['max_human']} (PHP upload: {$limits['php_upload_max']}, POST: {$limits['php_post_max']}). "
                                : "Maksimal {$limits['max_human']} via browser (PHP upload: {$limits['php_upload_max']}, POST: {$limits['php_post_max']}). ";

                            return $prefix.'Untuk file backup yang lebih besar, salin .ZIP ke folder backup server via SFTP/rclone, klik "Segarkan Daftar", lalu Restore langsung dari tabel.';
                        }),
                    Select::make('mode')
                        ->label('Komponen yang Dipulihkan')
                        ->options([
                            'full' => 'Lengkap (Database + Struktur Folder Storage)',
                            'database' => 'Hanya Database (Tabel & Data SQL)',
                            'storage' => 'Hanya Storage (Dokumen, Lampiran Surat, Publik)',
                        ])
                        ->default('full')
                        ->native(false)
                        ->selectablePlaceholder(false)
                        ->required()
                        ->live(),
                    Select::make('storage_strategy')
                        ->label('Strategi Pemulihan Folder Storage')
                        ->options([
                            'merge' => 'Gabung & Timpa (Merge — file dari backup disalin, file baru saat ini tetap ada)',
                            'replace' => 'Ganti Total (Replace — samakan persis isi private/ dan public/ dengan arsip backup)',
                        ])
                        ->default('merge')
                        ->native(false)
                        ->selectablePlaceholder(false)
                        ->required()
                        ->visible(fn (Get $get): bool => in_array($get('mode'), ['full', 'storage'], true)),
                    Select::make('execution_mode')
                        ->label('Mode Eksekusi Restore')
                        ->options([
                            'queue' => 'Latar Belakang / Antrean (Queue — Disarankan agar aman dari timeout HTTP)',
                            'sync' => 'Jalankan Langsung Sekarang (Sinkron — Hanya untuk arsip kecil)',
                        ])
                        ->default('queue')
                        ->native(false)
                        ->selectablePlaceholder(false)
                        ->required(),
                    Toggle::make('create_safety_backup')
                        ->label('Buat backup pengaman otomatis sebelum menimpa data saat ini')
                        ->default(true),
                    TextInput::make('current_password')
                        ->label('Konfirmasi Password Super Admin')
                        ->password()
                        ->revealable()
                        ->required()
                        ->rule('current_password')
                        ->helperText('Masukkan password akun Super Admin Anda saat ini untuk otorisasi restore.'),
                ])
                ->action(function (array $data, SystemBackupService $backupService): void {
                    $user = $this->requireRestoreActor((string) ($data['current_password'] ?? ''));
                    $uploadedRelativePath = (string) ($data['archive'] ?? '');
                    $localDisk = Storage::disk('local');

                    if ($uploadedRelativePath === '' || ! $localDisk->exists($uploadedRelativePath)) {
                        Notification::make()
                            ->title('Berkas Unggahan Tidak Ditemukan')
                            ->body('Silakan unggah ulang paket backup .ZIP yang valid.')
                            ->danger()
                            ->send();

                        return;
                    }

                    $absolutePath = $localDisk->path($uploadedRelativePath);
                    $mode = (string) ($data['mode'] ?? 'full');
                    $storageStrategy = (string) ($data['storage_strategy'] ?? 'merge');
                    $createSafetyBackup = (bool) ($data['create_safety_backup'] ?? true);
                    $executionMode = (string) ($data['execution_mode'] ?? 'queue');

                    if ($executionMode === 'queue') {
                        try {
                            // Validasi keamanan arsip terlebih dahulu sebelum masuk antrean latar belakang
                            $backupService->inspectArchive($absolutePath, $user, recordSecurityAudit: true);

                            RunSystemRestoreJob::dispatch(
                                actorId: $user->id,
                                sourceType: 'uploaded_path',
                                target: $uploadedRelativePath,
                                mode: $mode,
                                storageStrategy: $storageStrategy,
                                createSafetyBackup: $createSafetyBackup,
                            );

                            Notification::make()
                                ->title('Proses Restore Dijadwalkan ke Latar Belakang')
                                ->body('Arsip tervalidasi aman dan sedang dipulihkan di antrean latar belakang. Anda akan menerima notifikasi setelah selesai.')
                                ->info()
                                ->send();
                        } catch (Throwable $e) {
                            $localDisk->delete($uploadedRelativePath);

                            Notification::make()
                                ->title('Restore Sistem Ditolak / Gagal')
                                ->body($e->getMessage())
                                ->danger()
                                ->persistent()
                                ->send();
                        }

                        return;
                    }

                    try {
                        $result = $backupService->restoreFromAbsolutePath(
                            zipAbsolutePath: $absolutePath,
                            mode: $mode,
                            storageStrategy: $storageStrategy,
                            createSafetyBackup: $createSafetyBackup,
                            actor: $user,
                            sourceLabel: 'uploaded:'.basename($uploadedRelativePath),
                        );

                        Notification::make()
                            ->title('Restore Sistem Berhasil')
                            ->body(sprintf(
                                'Pemulihan selesai (DB: %s, Berkas Storage dipulihkan: %d berkas).',
                                $result['restored_database'] ? 'Ya' : 'Tidak',
                                $result['restored_storage_files'],
                            ))
                            ->success()
                            ->send();
                    } catch (Throwable $e) {
                        Notification::make()
                            ->title('Restore Sistem Ditolak / Gagal')
                            ->body($e->getMessage())
                            ->danger()
                            ->persistent()
                            ->send();
                    } finally {
                        $localDisk->delete($uploadedRelativePath);
                    }
                }),

            Action::make('refreshBackups')
                ->label('Segarkan Daftar')
                ->icon('heroicon-o-arrow-path')
                ->color('gray')
                ->action(function (SystemBackupService $backupService): void {
                    $this->requireManageActor();
                    $backupService->invalidateBackupCaches();

                    Notification::make()
                        ->title('Daftar & Statistik Diperbarui')
                        ->body('Cache daftar arsip dan ringkasan disk telah dipindai ulang.')
                        ->success()
                        ->send();
                }),

            Action::make('cleanupBackups')
                ->label('Bersihkan Retensi')
                ->icon('heroicon-o-trash')
                ->color('danger')
                ->requiresConfirmation()
                ->modalHeading('Bersihkan Paket Backup Lama (backup:clean)')
                ->modalDescription('Paket backup lama yang melampaui kebijakan retensi di config/backup.php akan dihapus secara aman untuk menjaga ruang disk server tetap lega (minimal 1 paket terbaru selalu dipertahankan).')
                ->modalSubmitActionLabel('Jalankan Pembersihan')
                ->action(function (SystemBackupService $backupService): void {
                    $user = $this->requireManageActor();

                    try {
                        $backupService->runCleanup($user);

                        Notification::make()
                            ->title('Pembersihan Retensi Selesai')
                            ->body('Paket backup lama yang melampaui kebijakan retensi telah dibersihkan.')
                            ->success()
                            ->send();
                    } catch (Throwable $e) {
                        Notification::make()
                            ->title('Pembersihan Retensi Gagal')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),
        ];
    }

    public function restoreBackupAction(): Action
    {
        return Action::make('restoreBackup')
            ->label('Restore')
            ->icon('heroicon-o-arrow-path')
            ->color('warning')
            ->visible(fn (): bool => static::canRestore())
            ->modalHeading(fn (array $arguments): string => 'Restore Sistem dari '.basename((string) ($arguments['filename'] ?? 'Backup')))
            ->modalDescription('PERINGATAN KRITIS: Proses restore akan menimpa database dan/atau berkas storage aktif sesuai mode yang Anda pilih. Pastikan seluruh pengguna telah berhenti sementara sebelum melanjutkan.')
            ->modalSubmitActionLabel('Konfirmasi & Jalankan Restore')
            ->schema([
                Select::make('mode')
                    ->label('Komponen yang Dipulihkan')
                    ->options([
                        'full' => 'Lengkap (Database + Struktur Folder Storage)',
                        'database' => 'Hanya Database (Tabel & Data SQL)',
                        'storage' => 'Hanya Storage (Dokumen, Lampiran Surat, Publik)',
                    ])
                    ->default('full')
                    ->native(false)
                    ->selectablePlaceholder(false)
                    ->required()
                    ->live(),
                Select::make('storage_strategy')
                    ->label('Strategi Pemulihan Folder Storage')
                    ->options([
                        'merge' => 'Gabung & Timpa (Merge — file dari backup disalin, file baru saat ini dipertahankan)',
                        'replace' => 'Ganti Total (Replace — samakan persis isi private/ dan public/ dengan arsip backup)',
                    ])
                    ->default('merge')
                    ->native(false)
                    ->selectablePlaceholder(false)
                    ->required()
                    ->visible(fn (Get $get): bool => in_array($get('mode'), ['full', 'storage'], true)),
                Select::make('execution_mode')
                    ->label('Mode Eksekusi Restore')
                    ->options([
                        'queue' => 'Latar Belakang / Antrean (Queue — Disarankan, anti timeout HTTP)',
                        'sync' => 'Jalankan Langsung Sekarang (Sinkron — Hanya untuk arsip kecil)',
                    ])
                    ->default('queue')
                    ->native(false)
                    ->selectablePlaceholder(false)
                    ->required(),
                Toggle::make('create_safety_backup')
                    ->label('Buat backup pengaman otomatis sebelum restore dijalankan')
                    ->default(true),
                TextInput::make('current_password')
                    ->label('Konfirmasi Password Super Admin')
                    ->password()
                    ->revealable()
                    ->required()
                    ->rule('current_password')
                    ->helperText('Wajib memasukkan password akun Super Admin Anda saat ini.'),
            ])
            ->action(function (array $data, array $arguments, SystemBackupService $backupService): void {
                $user = $this->requireRestoreActor((string) ($data['current_password'] ?? ''));
                $filename = basename((string) ($arguments['filename'] ?? ''));

                if ($filename === '') {
                    Notification::make()
                        ->title('Parameter Berkas Tidak Valid')
                        ->danger()
                        ->send();

                    return;
                }

                $mode = (string) ($data['mode'] ?? 'full');
                $storageStrategy = (string) ($data['storage_strategy'] ?? 'merge');
                $createSafetyBackup = (bool) ($data['create_safety_backup'] ?? true);
                $executionMode = (string) ($data['execution_mode'] ?? 'queue');

                if ($executionMode === 'queue') {
                    RunSystemRestoreJob::dispatch(
                        actorId: $user->id,
                        sourceType: 'disk',
                        target: $filename,
                        mode: $mode,
                        storageStrategy: $storageStrategy,
                        createSafetyBackup: $createSafetyBackup,
                    );

                    Notification::make()
                        ->title('Proses Restore Dijadwalkan ke Latar Belakang')
                        ->body("Pemulihan paket {$filename} telah masuk antrean latar belakang. Anda akan menerima notifikasi setelah selesai.")
                        ->info()
                        ->send();

                    return;
                }

                try {
                    $result = $backupService->restoreFromDisk(
                        filename: $filename,
                        mode: $mode,
                        storageStrategy: $storageStrategy,
                        createSafetyBackup: $createSafetyBackup,
                        actor: $user,
                    );

                    Notification::make()
                        ->title('Restore Sistem Berhasil')
                        ->body(sprintf(
                            'Paket %s berhasil dipulihkan (DB: %s, Berkas Storage: %d berkas).',
                            $filename,
                            $result['restored_database'] ? 'Ya' : 'Tidak',
                            $result['restored_storage_files'],
                        ))
                        ->success()
                        ->send();
                } catch (Throwable $e) {
                    Notification::make()
                        ->title('Restore Sistem Ditolak / Gagal')
                        ->body($e->getMessage())
                        ->danger()
                        ->persistent()
                        ->send();
                }
            });
    }

    public function deleteBackupAction(): Action
    {
        return Action::make('deleteBackup')
            ->label('Hapus')
            ->icon('heroicon-o-trash')
            ->color('danger')
            ->requiresConfirmation()
            ->modalHeading(fn (array $arguments): string => 'Hapus Paket Backup '.basename((string) ($arguments['filename'] ?? '')).'?')
            ->modalDescription('Berkas .ZIP backup akan dihapus permanen dari penyimpanan server dan dicatat dalam audit log.')
            ->modalSubmitActionLabel('Ya, Hapus Permanen')
            ->action(function (array $arguments, SystemBackupService $backupService): void {
                $user = $this->requireManageActor();
                $filename = basename((string) ($arguments['filename'] ?? ''));

                if ($filename === '' || ! $backupService->deleteBackup($filename, $user)) {
                    Notification::make()
                        ->title('Berkas Backup Tidak Ditemukan')
                        ->danger()
                        ->send();

                    return;
                }

                Notification::make()
                    ->title('Paket Backup Dihapus')
                    ->body("Berkas {$filename} telah dihapus permanen.")
                    ->success()
                    ->send();
            });
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getBackups(): array
    {
        return app(SystemBackupService::class)->listBackups();
    }

    /**
     * @return array<string, mixed>
     */
    public function getStorageSummary(): array
    {
        return app(SystemBackupService::class)->getStorageSummary();
    }

    /**
     * @return array<string, mixed>
     */
    public function getScheduleConfig(): array
    {
        return app(SystemBackupService::class)->getScheduleConfig();
    }

    public function getActiveDatabaseDriver(): string
    {
        return app(SystemBackupService::class)->getActiveDatabaseDriver();
    }

    public function getRecentBackupLogs(): Collection
    {
        return Activity::query()
            ->where('log_name', 'system_backup')
            ->with('causer')
            ->latest('created_at')
            ->limit(10)
            ->get();
    }

    /**
     * @param  array<string, mixed>  $schedule
     */
    public function formatScheduleHuman(array $schedule): string
    {
        if (! ($schedule['enabled'] ?? false)) {
            return 'Nonaktif (Manual Saja)';
        }

        $days = [
            0 => 'Minggu',
            1 => 'Senin',
            2 => 'Selasa',
            3 => 'Rabu',
            4 => 'Kamis',
            5 => 'Jumat',
            6 => 'Sabtu',
        ];

        $time = (string) ($schedule['time'] ?? '01:00');
        $frequency = (string) ($schedule['frequency'] ?? 'weekly');

        return match ($frequency) {
            'daily' => "Setiap Hari pukul {$time} WIB",
            'weekly' => 'Setiap Hari '.($days[(int) ($schedule['day_of_week'] ?? 0)] ?? 'Minggu')." pukul {$time} WIB",
            'monthly' => 'Setiap Tanggal '.((int) ($schedule['day_of_month'] ?? 1))." pukul {$time} WIB",
            default => "Pukul {$time} WIB",
        };
    }

    /**
     * @return array<int, string>
     */
    protected function getDayOfMonthOptions(): array
    {
        $options = [];
        for ($day = 1; $day <= 28; $day++) {
            $options[$day] = "Tanggal {$day}";
        }

        return $options;
    }

    protected function requireManageActor(): User
    {
        /** @var User|null $user */
        $user = auth()->user();

        if (! $user || ! $user->hasRole('super_admin') || ! $user->can('system.backup_manage')) {
            abort(403, 'Hanya Super Admin dengan izin system.backup_manage yang dapat mengelola backup sistem.');
        }

        return $user;
    }

    protected function requireRestoreActor(string $plainPassword): User
    {
        /** @var User|null $user */
        $user = auth()->user();

        if (! $user || ! $user->hasRole('super_admin') || ! $user->can('system.backup_restore')) {
            abort(403, 'Hanya Super Admin dengan izin system.backup_restore yang dapat menjalankan restore sistem.');
        }

        if (! Hash::check($plainPassword, $user->password)) {
            throw ValidationException::withMessages([
                'mountedActions.0.data.current_password' => 'Password Super Admin yang Anda masukkan tidak sesuai.',
            ]);
        }

        return $user;
    }
}
