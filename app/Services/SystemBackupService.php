<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Setting;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use Spatie\Permission\PermissionRegistrar;
use Symfony\Component\Process\Process;
use Throwable;
use ZipArchive;

class SystemBackupService
{
    public const SCHEDULE_SETTING_KEY = 'system_backup_schedule';

    public const SCOPE_DATABASE_ONLY = 'database_only';

    public const SCOPE_FULL = 'full';

    /**
     * Daftar ekstensi dan nama berkas berbahaya yang diblokir mutlak saat inspeksi arsip restore.
     *
     * @var array<int, string>
     */
    private const FORBIDDEN_EXTENSIONS = [
        'php', 'phtml', 'php3', 'php4', 'php5', 'php7', 'php8', 'phps', 'phar',
        'py', 'pyc', 'pyo', 'pyw',
        'sh', 'bash', 'zsh', 'ksh', 'csh',
        'bat', 'cmd', 'com', 'exe', 'msi', 'msp', 'dll', 'so', 'dylib',
        'ps1', 'psm1', 'psd1', 'vbs', 'vbe', 'jse', 'wsf', 'wsh',
        'scr', 'cpl', 'hta', 'jar', 'war',
        'jsp', 'jspx', 'asp', 'aspx', 'ascx', 'ashx', 'asmx',
        'cgi', 'pl', 'pm', 'rb', 'erb', 'lua',
        'js', 'mjs', 'cjs', 'ts', 'tsx', 'jsx',
        'vb', 'vba', 'reg', 'inf', 'lnk', 'pif', 'scf',
        'apk', 'deb', 'rpm',
    ];

    /**
     * Ekstensi sisipan (double extension) yang tidak boleh muncul di tengah nama berkas.
     *
     * @var array<int, string>
     */
    private const FORBIDDEN_DOUBLE_EXTENSIONS = [
        'php', 'phtml', 'php3', 'php4', 'php5', 'php7', 'php8', 'phar',
        'py', 'pyc', 'sh', 'bash', 'bat', 'cmd', 'exe', 'ps1',
        'jsp', 'asp', 'aspx', 'cgi', 'vbs', 'dll', 'hta', 'pl', 'rb',
    ];

    /**
     * Nama berkas sensitif / konfigurasi server yang tidak boleh dipulihkan dari arsip.
     *
     * @var array<int, string>
     */
    private const FORBIDDEN_FILENAMES = [
        '.htaccess',
        '.htpasswd',
        '.user.ini',
        'web.config',
        '.env',
    ];

    public function getDiskName(): string
    {
        return (string) (config('backup.backup.destination.disks.0') ?? 'backups');
    }

    public function getBackupFolderName(): string
    {
        return (string) (config('backup.backup.name') ?? 'UNIVBI-Arsip-Backup');
    }

    public function getActiveDatabaseDriver(): string
    {
        $connection = (string) config('database.default', 'pgsql');

        return (string) config("database.connections.{$connection}.driver", $connection);
    }

    /**
     * Mengambil konfigurasi jadwal backup otomatis rutin.
     *
     * @return array{
     *     enabled: bool,
     *     scope: string,
     *     frequency: string,
     *     day_of_week: int,
     *     day_of_month: int,
     *     time: string,
     *     auto_clean: bool,
     *     last_scheduled_run_at: ?string
     * }
     */
    public function getScheduleConfig(): array
    {
        $defaults = [
            'enabled' => false,
            'scope' => self::SCOPE_DATABASE_ONLY,
            'frequency' => 'weekly',
            'day_of_week' => 0, // 0 = Minggu
            'day_of_month' => 1,
            'time' => '01:00',
            'auto_clean' => true,
            'last_scheduled_run_at' => null,
        ];

        try {
            $setting = Setting::query()->where('key', self::SCHEDULE_SETTING_KEY)->first();
            if (! $setting || blank($setting->value)) {
                return $defaults;
            }

            $decoded = json_decode((string) $setting->value, true);
            if (! is_array($decoded)) {
                return $defaults;
            }

            return array_merge($defaults, [
                'enabled' => (bool) ($decoded['enabled'] ?? false),
                'scope' => in_array($decoded['scope'] ?? self::SCOPE_DATABASE_ONLY, [self::SCOPE_DATABASE_ONLY, self::SCOPE_FULL], true)
                    ? (string) $decoded['scope']
                    : self::SCOPE_DATABASE_ONLY,
                'frequency' => in_array($decoded['frequency'] ?? 'weekly', ['daily', 'weekly', 'monthly'], true)
                    ? (string) $decoded['frequency']
                    : 'weekly',
                'day_of_week' => max(0, min(6, (int) ($decoded['day_of_week'] ?? 0))),
                'day_of_month' => max(1, min(28, (int) ($decoded['day_of_month'] ?? 1))),
                'time' => preg_match('/^\d{2}:\d{2}$/', (string) ($decoded['time'] ?? '')) === 1
                    ? (string) $decoded['time']
                    : '01:00',
                'auto_clean' => (bool) ($decoded['auto_clean'] ?? true),
                'last_scheduled_run_at' => isset($decoded['last_scheduled_run_at']) && is_string($decoded['last_scheduled_run_at'])
                    ? $decoded['last_scheduled_run_at']
                    : null,
            ]);
        } catch (Throwable) {
            return $defaults;
        }
    }

    /**
     * Menyimpan pengaturan jadwal backup otomatis dan mencatat log audit.
     *
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function saveScheduleConfig(array $input, User $actor): array
    {
        $this->authorizeManage($actor);

        $current = $this->getScheduleConfig();

        $rawScope = $input['scope'] ?? self::SCOPE_DATABASE_ONLY;
        $scope = in_array($rawScope, [self::SCOPE_DATABASE_ONLY, self::SCOPE_FULL], true)
            ? (string) $rawScope
            : self::SCOPE_DATABASE_ONLY;
        $frequency = in_array($input['frequency'] ?? 'weekly', ['daily', 'weekly', 'monthly'], true)
            ? (string) $input['frequency']
            : 'weekly';
        $dayOfWeek = max(0, min(6, (int) ($input['day_of_week'] ?? 0)));
        $dayOfMonth = max(1, min(28, (int) ($input['day_of_month'] ?? 1)));
        $time = preg_match('/^\d{2}:\d{2}$/', (string) ($input['time'] ?? '')) === 1
            ? (string) $input['time']
            : '01:00';

        $timingChanged = ($current['frequency'] ?? null) !== $frequency
            || (int) ($current['day_of_week'] ?? 0) !== $dayOfWeek
            || (int) ($current['day_of_month'] ?? 1) !== $dayOfMonth
            || ($current['time'] ?? null) !== $time;

        $normalized = [
            'enabled' => (bool) ($input['enabled'] ?? false),
            'scope' => $scope,
            'frequency' => $frequency,
            'day_of_week' => $dayOfWeek,
            'day_of_month' => $dayOfMonth,
            'time' => $time,
            'auto_clean' => (bool) ($input['auto_clean'] ?? true),
            'last_scheduled_run_at' => $timingChanged ? null : ($current['last_scheduled_run_at'] ?? null),
        ];

        Setting::updateOrCreate(
            ['key' => self::SCHEDULE_SETTING_KEY],
            [
                'value' => json_encode($normalized, JSON_THROW_ON_ERROR),
                'group' => 'backup',
                'display_name' => 'Jadwal Rutin Backup Otomatis Sistem',
                'type' => 'json',
                'is_public' => false,
            ]
        );

        activity('system_backup')
            ->causedBy($actor)
            ->event('schedule_updated')
            ->withProperties($normalized)
            ->log('Super Admin memperbarui konfigurasi jadwal backup otomatis.');

        return $normalized;
    }

    /**
     * Memeriksa apakah jadwal otomatis saat ini sudah jatuh tempo untuk dijalankan.
     */
    public function isScheduleDue(CarbonInterface $now): bool
    {
        $config = $this->getScheduleConfig();

        if (! $config['enabled']) {
            return false;
        }

        $targetTime = $config['time'];
        $currentTime = $now->format('H:i');

        if ($currentTime < $targetTime) {
            return false;
        }

        $lastRun = $config['last_scheduled_run_at']
            ? Carbon::parse($config['last_scheduled_run_at'], $now->getTimezone())
            : null;

        return match ($config['frequency']) {
            'daily' => $lastRun === null || ! $lastRun->isSameDay($now),
            'weekly' => (int) $now->dayOfWeek === (int) $config['day_of_week']
                && ($lastRun === null || ! $lastRun->isSameDay($now)),
            'monthly' => (int) $now->day === (int) $config['day_of_month']
                && ($lastRun === null || ! $lastRun->isSameMonth($now)),
            default => false,
        };
    }

    public function markScheduledRun(CarbonInterface $now): void
    {
        $config = $this->getScheduleConfig();
        $config['last_scheduled_run_at'] = $now->toIso8601String();

        Setting::updateOrCreate(
            ['key' => self::SCHEDULE_SETTING_KEY],
            [
                'value' => json_encode($config, JSON_THROW_ON_ERROR),
                'group' => 'backup',
                'display_name' => 'Jadwal Rutin Backup Otomatis Sistem',
                'type' => 'json',
                'is_public' => false,
            ]
        );
    }

    /**
     * Menjalankan backup sistem sesuai cakupan (`database_only` atau `full`) dengan kunci anti-tumpuk.
     *
     * @return array{file_name: string, path: string, size_bytes: int, scope: string, output: string}
     */
    public function runBackup(
        string $scope = self::SCOPE_DATABASE_ONLY,
        ?User $actor = null,
        string $trigger = 'manual'
    ): array {
        if ($actor !== null) {
            $this->authorizeManage($actor);
        }

        if (! in_array($scope, [self::SCOPE_DATABASE_ONLY, self::SCOPE_FULL], true)) {
            throw new RuntimeException("Cakupan backup '{$scope}' tidak valid.");
        }

        @set_time_limit(0);

        $lockHandle = $this->acquireFileLock('system-backup.lock', 'Proses backup sistem lain sedang berjalan. Mohon tunggu hingga selesai sebelum memulai backup baru.');

        try {
            $diskName = $this->getDiskName();
            $disk = Storage::disk($diskName);
            $beforeFiles = collect($disk->allFiles())
                ->filter(fn (string $f): bool => str_ends_with(strtolower($f), '.zip'))
                ->values()
                ->all();

            $timestampPrefix = now('Asia/Jakarta')->format('Y-m-d-H-i-s');
            $scopeSuffix = $scope === self::SCOPE_DATABASE_ONLY ? '-db-only' : '';
            $triggerSuffix = $trigger === 'pre_restore_safety' ? '-pre-restore-safety-'.bin2hex(random_bytes(2)) : '';
            $targetFilename = "{$timestampPrefix}{$scopeSuffix}{$triggerSuffix}.zip";

            // Hindari tabrakan nama berkas apabila dua backup dijalankan pada detik yang sama
            $existingBasenames = array_map('basename', $beforeFiles);
            if (in_array($targetFilename, $existingBasenames, true)) {
                $targetFilename = $timestampPrefix.$scopeSuffix.($triggerSuffix !== '' ? $triggerSuffix : '-'.bin2hex(random_bytes(2))).'.zip';
            }

            $artisanParams = [
                '--disable-notifications' => true,
                '--filename' => $targetFilename,
            ];

            if ($scope === self::SCOPE_DATABASE_ONLY) {
                $artisanParams['--only-db'] = true;
            }

            $exitCode = Artisan::call('backup:run', $artisanParams);

            $output = trim(Artisan::output());

            if ($exitCode !== 0) {
                if ($actor !== null) {
                    activity('system_backup')
                        ->causedBy($actor)
                        ->event('backup_failed')
                        ->withProperties([
                            'trigger' => $trigger,
                            'scope' => $scope,
                            'exit_code' => $exitCode,
                            'output' => Str::limit($output, 1500),
                        ])
                        ->log('Proses backup sistem gagal dijalankan.');
                }

                throw new RuntimeException('Gagal membuat paket backup sistem: '.($output !== '' ? Str::limit($output, 400) : "Kode keluar {$exitCode}"));
            }

            $afterFiles = collect($disk->allFiles())
                ->filter(fn (string $f): bool => str_ends_with(strtolower($f), '.zip'))
                ->sortByDesc(fn (string $f): int => $disk->lastModified($f))
                ->values();

            $newFiles = $afterFiles->diff($beforeFiles);
            $createdRelativePath = $newFiles->first() ?? $afterFiles->first();

            if (! $createdRelativePath) {
                throw new RuntimeException('Proses backup selesai namun berkas arsip ZIP tidak ditemukan di disk backup.');
            }

            $sizeBytes = (int) $disk->size($createdRelativePath);
            $fileName = basename($createdRelativePath);

            $this->invalidateBackupCaches();

            $activity = activity('system_backup')
                ->event('backup_created')
                ->withProperties([
                    'trigger' => $trigger,
                    'scope' => $scope,
                    'file_name' => $fileName,
                    'path' => $createdRelativePath,
                    'size_bytes' => $sizeBytes,
                    'database_driver' => $this->getActiveDatabaseDriver(),
                ]);

            if ($actor !== null) {
                $activity->causedBy($actor);
            }

            $scopeLabel = $scope === self::SCOPE_DATABASE_ONLY ? 'Database Saja' : 'Lengkap DB + Storage';
            $activity->log("Paket backup sistem ({$scopeLabel} / {$trigger}) berhasil dibuat: {$fileName}");

            return [
                'file_name' => $fileName,
                'path' => $createdRelativePath,
                'size_bytes' => $sizeBytes,
                'scope' => $scope,
                'output' => $output,
            ];
        } finally {
            $this->releaseFileLock($lockHandle);
        }
    }

    /**
     * Menjalankan proses backup lengkap 1 paket (Database + Struktur Folder Storage) via spatie/laravel-backup.
     *
     * @return array{file_name: string, path: string, size_bytes: int, scope: string, output: string}
     */
    public function runFullBackup(?User $actor = null, string $trigger = 'manual'): array
    {
        return $this->runBackup(self::SCOPE_FULL, $actor, $trigger);
    }

    /**
     * Membersihkan arsip backup lama sesuai strategi retensi spatie/laravel-backup.
     */
    public function runCleanup(?User $actor = null): string
    {
        if ($actor !== null) {
            $this->authorizeManage($actor);
        }

        $exitCode = Artisan::call('backup:clean', [
            '--disable-notifications' => true,
        ]);

        $output = trim(Artisan::output());

        if ($exitCode !== 0) {
            throw new RuntimeException('Pembersihan arsip backup gagal: '.($output !== '' ? Str::limit($output, 300) : "Kode keluar {$exitCode}"));
        }

        $this->invalidateBackupCaches();

        $activity = activity('system_backup')
            ->event('backup_cleaned')
            ->withProperties([
                'output' => Str::limit($output, 1000),
            ]);

        if ($actor !== null) {
            $activity->causedBy($actor);
        }

        $activity->log('Pembersihan retensi paket backup lama berhasil dijalankan.');

        return $output;
    }

    public function getBackupCacheVersion(): int
    {
        return (int) Cache::rememberForever('system_backup_cache_version', static fn (): int => 1);
    }

    public function invalidateBackupCaches(): void
    {
        Cache::forever('system_backup_cache_version', $this->getBackupCacheVersion() + 1);
    }

    /**
     * Mengembalikan daftar semua paket backup `.zip` pada disk `backups` (ter-cache berbasis versi agar cepat).
     *
     * @return array<int, array{
     *     file_name: string,
     *     relative_path: string,
     *     size_bytes: int,
     *     size_human: string,
     *     created_at: Carbon,
     *     has_database: bool,
     *     has_storage: bool,
     *     scope: string,
     *     storage_files_count: int,
     *     is_encrypted: bool,
     *     is_safe: bool,
     *     security_note: ?string
     * }>
     */
    public function listBackups(): array
    {
        $diskName = $this->getDiskName();
        $disk = Storage::disk($diskName);
        $version = $this->getBackupCacheVersion();
        $diskSignature = substr(sha1($diskName.'|'.(string) $disk->path('')), 0, 12);
        $cacheKey = "system_backup_list:v{$version}:{$diskSignature}";

        /** @var array<int, array<string, mixed>> $cachedRows */
        $cachedRows = Cache::remember($cacheKey, now()->addMinutes(10), function () use ($disk): array {
            $files = collect($disk->allFiles())
                ->filter(fn (string $path): bool => str_ends_with(strtolower($path), '.zip'))
                ->sortByDesc(fn (string $path): int => $disk->lastModified($path))
                ->values();

            $items = [];

            foreach ($files as $relativePath) {
                $absolutePath = $disk->path($relativePath);
                $sizeBytes = (int) $disk->size($relativePath);
                $createdAtTs = (int) $disk->lastModified($relativePath);

                $hasDatabase = false;
                $hasStorage = false;
                $storageFilesCount = 0;
                $isEncrypted = false;
                $isSafe = true;
                $securityNote = null;

                try {
                    $inspection = $this->inspectArchive($absolutePath, recordSecurityAudit: false);
                    $hasDatabase = $inspection['has_database'];
                    $hasStorage = $inspection['has_storage'];
                    $storageFilesCount = $inspection['storage_files_count'];
                    $isEncrypted = $inspection['is_encrypted'];
                } catch (Throwable $e) {
                    $isSafe = false;
                    $securityNote = $e->getMessage();
                }

                $scope = match (true) {
                    $hasDatabase && $hasStorage => self::SCOPE_FULL,
                    $hasDatabase => self::SCOPE_DATABASE_ONLY,
                    $hasStorage => 'storage_only',
                    default => 'unknown',
                };

                $items[] = [
                    'file_name' => basename($relativePath),
                    'relative_path' => $relativePath,
                    'size_bytes' => $sizeBytes,
                    'size_human' => $this->formatBytes($sizeBytes),
                    'created_at_ts' => $createdAtTs,
                    'has_database' => $hasDatabase,
                    'has_storage' => $hasStorage,
                    'scope' => $scope,
                    'storage_files_count' => $storageFilesCount,
                    'is_encrypted' => $isEncrypted,
                    'is_safe' => $isSafe,
                    'security_note' => $securityNote,
                ];
            }

            return $items;
        });

        return array_map(static function (array $item): array {
            $item['created_at'] = Carbon::createFromTimestamp((int) ($item['created_at_ts'] ?? time()), 'Asia/Jakarta');
            unset($item['created_at_ts']);

            return $item;
        }, $cachedRows);
    }

    /**
     * Mendapatkan path absolut berkas backup secara aman dari disk `backups`.
     */
    public function resolveBackupFileOnDisk(string $filename): ?string
    {
        $safeFilename = basename(str_replace('\\', '/', $filename));
        if ($safeFilename === '' || ! str_ends_with(strtolower($safeFilename), '.zip')) {
            return null;
        }

        $disk = Storage::disk($this->getDiskName());

        foreach ($disk->allFiles() as $relativePath) {
            if (basename($relativePath) === $safeFilename) {
                return $disk->path($relativePath);
            }
        }

        return null;
    }

    /**
     * Memeriksa keamanan arsip ZIP sebelum proses restore dijalankan:
     * 1. Validasi integritas ZIP
     * 2. Anti Zip-Slip (path traversal `../` atau path absolut)
     * 3. Anti berkas/ekstensi berbahaya (`.php`, `.py`, `.sh`, `.exe`, `.bat`, double-extension, `.htaccess`, `.env`, dll.)
     *
     * @return array{
     *     valid: bool,
     *     file_count: int,
     *     total_uncompressed_bytes: int,
     *     has_database: bool,
     *     db_dumps: array<int, string>,
     *     has_storage: bool,
     *     storage_files_count: int,
     *     is_encrypted: bool
     * }
     */
    public function inspectArchive(string $zipAbsolutePath, ?User $actor = null, bool $recordSecurityAudit = true): array
    {
        if (! is_file($zipAbsolutePath)) {
            throw new RuntimeException('Berkas arsip backup ZIP tidak ditemukan.');
        }

        $zip = new ZipArchive;
        $openResult = $zip->open($zipAbsolutePath);

        if ($openResult !== true) {
            throw new RuntimeException("Berkas arsip ZIP rusak atau tidak dapat dibuka (kode galat: {$openResult}).");
        }

        $dbDumps = [];
        $storageFilesCount = 0;
        $totalUncompressedBytes = 0;
        $isEncrypted = false;
        $securityViolations = [];

        try {
            $numFiles = $zip->numFiles;

            for ($i = 0; $i < $numFiles; $i++) {
                $stat = $zip->statIndex($i);
                $rawName = $zip->getNameIndex($i);

                if ($rawName === false || $stat === false) {
                    continue;
                }

                if (($stat['encryption_method'] ?? 0) > 0) {
                    $isEncrypted = true;
                }

                $normalized = str_replace('\\', '/', $rawName);

                // Lewati direktori kosong
                if (str_ends_with($normalized, '/')) {
                    $this->assertSafeZipPath($normalized, $securityViolations);

                    continue;
                }

                $totalUncompressedBytes += (int) ($stat['size'] ?? 0);

                if (! $this->assertSafeZipPath($normalized, $securityViolations)) {
                    continue;
                }

                $this->assertSafeFileExtension($normalized, $securityViolations);

                if ($this->isDatabaseDumpEntry($normalized)) {
                    $dbDumps[] = $normalized;
                } elseif ($this->isRestorableStorageEntry($normalized)) {
                    $storageFilesCount++;
                }
            }

            if ($securityViolations !== []) {
                $sampleList = implode(', ', array_slice($securityViolations, 0, 5));
                $extraCount = count($securityViolations) > 5 ? ' (+'.(count($securityViolations) - 5).' lainnya)' : '';
                $message = "Keamanan Diblokir: Ditemukan berkas berbahaya atau pola path terlarang di dalam arsip ZIP ({$sampleList}{$extraCount}).";

                if ($recordSecurityAudit) {
                    $log = activity('system_backup')
                        ->event('restore_security_blocked')
                        ->withProperties([
                            'archive' => basename($zipAbsolutePath),
                            'violations' => array_slice($securityViolations, 0, 20),
                        ]);

                    if ($actor !== null) {
                        $log->causedBy($actor);
                    }

                    $log->log($message);
                }

                throw new RuntimeException($message);
            }

            return [
                'valid' => true,
                'file_count' => $numFiles,
                'total_uncompressed_bytes' => $totalUncompressedBytes,
                'has_database' => $dbDumps !== [],
                'db_dumps' => $dbDumps,
                'has_storage' => $storageFilesCount > 0,
                'storage_files_count' => $storageFilesCount,
                'is_encrypted' => $isEncrypted,
            ];
        } finally {
            $zip->close();
        }
    }

    /**
     * Memulihkan data dari paket backup yang tersimpan di disk `backups`.
     *
     * @return array{
     *     mode: string,
     *     restored_database: bool,
     *     restored_storage_files: int,
     *     safety_backup_file: ?string,
     *     archive_name: string
     * }
     */
    public function restoreFromDisk(
        string $filename,
        string $mode,
        string $storageStrategy,
        bool $createSafetyBackup,
        User $actor
    ): array {
        $this->authorizeRestore($actor);

        $absolutePath = $this->resolveBackupFileOnDisk($filename);
        if (! $absolutePath) {
            throw new RuntimeException("Arsip backup '{$filename}' tidak ditemukan di penyimpanan server.");
        }

        return $this->restoreFromAbsolutePath(
            zipAbsolutePath: $absolutePath,
            mode: $mode,
            storageStrategy: $storageStrategy,
            createSafetyBackup: $createSafetyBackup,
            actor: $actor,
            sourceLabel: 'server_archive'
        );
    }

    /**
     * Memulihkan database dan/atau berkas storage dari berkas arsip `.zip` tervalidasi.
     *
     * @return array{
     *     mode: string,
     *     restored_database: bool,
     *     restored_storage_files: int,
     *     safety_backup_file: ?string,
     *     archive_name: string
     * }
     */
    public function restoreFromAbsolutePath(
        string $zipAbsolutePath,
        string $mode,
        string $storageStrategy,
        bool $createSafetyBackup,
        User $actor,
        string $sourceLabel = 'uploaded_archive'
    ): array {
        $this->authorizeRestore($actor);

        if (! in_array($mode, ['database', 'storage', 'full'], true)) {
            throw new RuntimeException("Mode pemulihan '{$mode}' tidak valid.");
        }

        if (! in_array($storageStrategy, ['merge', 'replace'], true)) {
            throw new RuntimeException("Strategi pemulihan berkas '{$storageStrategy}' tidak valid.");
        }

        @set_time_limit(0);

        $restoreLockHandle = $this->acquireFileLock('system-restore.lock', 'Proses restore sistem lain sedang berjalan. Mohon tunggu hingga selesai sebelum menjalankan restore baru.');

        try {
            // 1. Inspeksi & validasi keamanan arsip terlebih dahulu (Anti Zip-Slip + Anti .php/.py/executable)
            $inspection = $this->inspectArchive($zipAbsolutePath, $actor, recordSecurityAudit: true);

            if (in_array($mode, ['database', 'full'], true) && ! $inspection['has_database']) {
                throw new RuntimeException('Arsip ZIP tidak memiliki berkas dump database (.sql / .sql.gz) di folder db-dumps/.');
            }

            if (in_array($mode, ['storage', 'full'], true) && ! $inspection['has_storage']) {
                throw new RuntimeException('Arsip ZIP ini merupakan paket Database Saja (tidak berisi berkas storage). Pilih komponen pemulihan "Hanya Database" untuk memulihkan arsip ini.');
            }

            // 2. Safety Backup Pra-Restore (cakupan disesuaikan dengan komponen yang dipulihkan agar tidak lambat)
            $safetyBackupFile = null;
            if ($createSafetyBackup) {
                $safetyScope = $mode === 'database' ? self::SCOPE_DATABASE_ONLY : self::SCOPE_FULL;
                $safetyResult = $this->runBackup($safetyScope, $actor, 'pre_restore_safety');
                $safetyBackupFile = $safetyResult['file_name'];
            }

            $stagingDir = storage_path('app/backup-temp/restore-'.Str::uuid()->toString());
            File::ensureDirectoryExists($stagingDir);

            $restoredDatabase = false;
            $restoredStorageFiles = 0;

            try {
                $zip = new ZipArchive;
                $openResult = $zip->open($zipAbsolutePath);
                if ($openResult !== true) {
                    throw new RuntimeException("Gagal membuka arsip ZIP untuk ekstraksi (kode: {$openResult}).");
                }

                if ($inspection['is_encrypted']) {
                    $archivePassword = (string) config('backup.backup.password', '');
                    if ($archivePassword === '' || ! $zip->setPassword($archivePassword)) {
                        $zip->close();
                        throw new RuntimeException('Arsip ZIP terenkripsi dan password dekripsi tidak valid atau belum dikonfigurasi.');
                    }
                }

                try {
                    // 3. Pulihkan File Storage terlebih dahulu (bila dipilih)
                    if (in_array($mode, ['storage', 'full'], true)) {
                        $restoredStorageFiles = $this->extractAndRestoreStorage($zip, $stagingDir, $storageStrategy);
                    }

                    // 4. Pulihkan Database (bila dipilih)
                    if (in_array($mode, ['database', 'full'], true)) {
                        $dumpEntry = $inspection['db_dumps'][0];
                        $this->extractAndRestoreDatabase($zip, $dumpEntry, $stagingDir);
                        $restoredDatabase = true;
                    }
                } finally {
                    $zip->close();
                }
            } finally {
                File::deleteDirectory($stagingDir);
            }

            // 5. Bersihkan cache sistem, sisa lock lama, daftar backup & izin Shield lalu catat audit log pasca-pemulihan
            try {
                if (DB::getSchemaBuilder()->hasTable('cache_locks')) {
                    DB::table('cache_locks')
                        ->where('key', 'like', '%system_backup_execution_lock%')
                        ->delete();
                }
                app(PermissionRegistrar::class)->forgetCachedPermissions();
                Cache::flush();
                $this->invalidateBackupCaches();
            } catch (Throwable) {
                // Abaikan error pembersihan cache non-kritis
            }

            try {
                activity('system_backup')
                    ->causedBy($actor)
                    ->event('backup_restored')
                    ->withProperties([
                        'source' => $sourceLabel,
                        'archive_name' => basename($zipAbsolutePath),
                        'mode' => $mode,
                        'storage_strategy' => $storageStrategy,
                        'restored_database' => $restoredDatabase,
                        'restored_storage_files' => $restoredStorageFiles,
                        'safety_backup_file' => $safetyBackupFile,
                        'database_driver' => $this->getActiveDatabaseDriver(),
                    ])
                    ->log('Super Admin menjalankan pemulihan sistem dari arsip '.basename($zipAbsolutePath)." (mode: {$mode}).");
            } catch (Throwable) {
                // Pastikan proses restore tetap dianggap berhasil jika log audit gagal ditulis pasca reset schema
            }

            return [
                'mode' => $mode,
                'restored_database' => $restoredDatabase,
                'restored_storage_files' => $restoredStorageFiles,
                'safety_backup_file' => $safetyBackupFile,
                'archive_name' => basename($zipAbsolutePath),
            ];
        } finally {
            $this->releaseFileLock($restoreLockHandle);
        }
    }

    /**
     * Menghapus arsip backup secara aman dan mencatat audit log.
     */
    public function deleteBackup(string $filename, User $actor): bool
    {
        $this->authorizeManage($actor);

        $safeFilename = basename(str_replace('\\', '/', $filename));
        $disk = Storage::disk($this->getDiskName());

        foreach ($disk->allFiles() as $relativePath) {
            if (basename($relativePath) === $safeFilename) {
                $deleted = $disk->delete($relativePath);

                if ($deleted) {
                    $this->invalidateBackupCaches();

                    activity('system_backup')
                        ->causedBy($actor)
                        ->event('backup_deleted')
                        ->withProperties([
                            'file_name' => $safeFilename,
                            'path' => $relativePath,
                        ])
                        ->log("Super Admin menghapus paket arsip backup: {$safeFilename}");
                }

                return $deleted;
            }
        }

        return false;
    }

    /**
     * Mengembalikan ringkasan ukuran folder storage aplikasi dan ruang backup terpakai.
     *
     * @return array{
     *     private_files: int,
     *     private_bytes: int,
     *     public_files: int,
     *     public_bytes: int,
     *     backup_count: int,
     *     backup_bytes: int,
     *     backup_human: string,
     *     storage_human: string
     * }
     */
    public function getStorageSummary(): array
    {
        $diskName = $this->getDiskName();
        $disk = Storage::disk($diskName);
        $version = $this->getBackupCacheVersion();
        $privateRoot = storage_path('app/private');
        $publicRoot = storage_path('app/public');
        $summaryKey = substr(sha1($diskName.'|'.(string) $disk->path('').'|'.$privateRoot.'|'.$publicRoot), 0, 12);
        $cacheKey = "system_backup_summary:v{$version}:{$summaryKey}";

        /** @var array{private_files: int, private_bytes: int, public_files: int, public_bytes: int, backup_count: int, backup_bytes: int, backup_human: string, storage_human: string} $summary */
        $summary = Cache::remember($cacheKey, now()->addMinutes(10), function () use ($privateRoot, $publicRoot, $disk): array {
            $privateStats = $this->measureDirectory($privateRoot, ['temp-exports', 'livewire-tmp']);
            $publicStats = $this->measureDirectory($publicRoot);

            $backupFiles = collect($disk->allFiles())
                ->filter(fn (string $f): bool => str_ends_with(strtolower($f), '.zip'));

            $backupBytes = 0;
            foreach ($backupFiles as $file) {
                $backupBytes += (int) $disk->size($file);
            }

            return [
                'private_files' => $privateStats['files'],
                'private_bytes' => $privateStats['bytes'],
                'public_files' => $publicStats['files'],
                'public_bytes' => $publicStats['bytes'],
                'backup_count' => $backupFiles->count(),
                'backup_bytes' => $backupBytes,
                'backup_human' => $this->formatBytes($backupBytes),
                'storage_human' => $this->formatBytes($privateStats['bytes'] + $publicStats['bytes']),
            ];
        });

        return $summary;
    }

    public function formatBytes(int $bytes): string
    {
        if ($bytes <= 0) {
            return '0 B';
        }

        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $power = (int) floor(log($bytes, 1024));
        $power = min($power, count($units) - 1);

        return number_format($bytes / (1024 ** $power), $power === 0 ? 0 : 2).' '.$units[$power];
    }

    /**
     * Memperoleh kunci berkas OS (`flock`) non-blocking agar backup/restore tidak memakai tabel DB `cache_locks`.
     *
     * @return resource
     */
    protected function acquireFileLock(string $lockFileName, string $busyMessage): mixed
    {
        $lockDir = storage_path('app/backup-temp');
        File::ensureDirectoryExists($lockDir);

        $handle = @fopen($lockDir.DIRECTORY_SEPARATOR.$lockFileName, 'c+');
        if ($handle === false || ! @flock($handle, LOCK_EX | LOCK_NB)) {
            if (is_resource($handle)) {
                @fclose($handle);
            }

            throw new RuntimeException($busyMessage);
        }

        return $handle;
    }

    /**
     * Melepaskan kunci berkas OS (`flock`).
     */
    protected function releaseFileLock(mixed $handle): void
    {
        if (is_resource($handle)) {
            @flock($handle, LOCK_UN);
            @fclose($handle);
        }
    }

    protected function authorizeManage(User $actor): void
    {
        if (! $actor->hasRole('super_admin') || ! $actor->can('system.backup_manage')) {
            throw new AuthorizationException('Hanya Super Admin dengan izin system.backup_manage yang berwenang mengelola backup sistem.');
        }
    }

    protected function authorizeRestore(User $actor): void
    {
        if (! $actor->hasRole('super_admin') || ! $actor->can('system.backup_restore')) {
            throw new AuthorizationException('Hanya Super Admin dengan izin system.backup_restore yang berwenang memulihkan sistem.');
        }
    }

    /**
     * @param  array<int, string>  $violations
     */
    protected function assertSafeZipPath(string $normalizedPath, array &$violations): bool
    {
        if ($normalizedPath === '' || str_contains($normalizedPath, "\0")) {
            $violations[] = '[null-byte/empty]';

            return false;
        }

        if (str_starts_with($normalizedPath, '/') || preg_match('/^[A-Za-z]:/', $normalizedPath) === 1) {
            $violations[] = $normalizedPath;

            return false;
        }

        $segments = explode('/', trim($normalizedPath, '/'));
        if (in_array('..', $segments, true) || in_array('.', $segments, true)) {
            $violations[] = $normalizedPath;

            return false;
        }

        return true;
    }

    /**
     * Memastikan tidak ada skrip atau berkas berbahaya (.php, .py, .sh, .exe, .htaccess, double-extension, dll.).
     *
     * @param  array<int, string>  $violations
     */
    protected function assertSafeFileExtension(string $normalizedPath, array &$violations): void
    {
        $base = basename($normalizedPath);
        $baseLower = strtolower($base);

        if (in_array($baseLower, self::FORBIDDEN_FILENAMES, true)) {
            $violations[] = $normalizedPath;

            return;
        }

        // Cek ekstensi akhir
        $ext = strtolower(pathinfo($baseLower, PATHINFO_EXTENSION));
        if ($ext !== '' && in_array($ext, self::FORBIDDEN_EXTENSIONS, true)) {
            $violations[] = $normalizedPath;

            return;
        }

        // Cek ekstensi sisipan berbahaya (misal: shell.php.jpg, script.py.pdf)
        $parts = explode('.', $baseLower);
        if (count($parts) > 2) {
            // Abaikan bagian pertama (nama berkas) dan terakhir (ekstensi sudah dicek di atas)
            $middleSegments = array_slice($parts, 1, -1);
            foreach ($middleSegments as $segment) {
                if (in_array($segment, self::FORBIDDEN_DOUBLE_EXTENSIONS, true)) {
                    $violations[] = $normalizedPath;

                    return;
                }
            }
        }

        // File .sql/.sql.gz/.sqlite hanya diperbolehkan di dalam folder db-dumps/
        $isDbFile = str_ends_with($baseLower, '.sql')
            || str_ends_with($baseLower, '.sql.gz')
            || str_ends_with($baseLower, '.sqlite');

        if ($isDbFile && ! str_starts_with($normalizedPath, 'db-dumps/')) {
            $violations[] = $normalizedPath;
        }
    }

    protected function isDatabaseDumpEntry(string $normalizedPath): bool
    {
        if (! str_starts_with($normalizedPath, 'db-dumps/')) {
            return false;
        }

        $lower = strtolower($normalizedPath);

        return str_ends_with($lower, '.sql')
            || str_ends_with($lower, '.sql.gz')
            || str_ends_with($lower, '.sqlite');
    }

    protected function isRestorableStorageEntry(string $normalizedPath): bool
    {
        return $this->mapZipEntryToStorageRelativePath($normalizedPath) !== null;
    }

    /**
     * Memetakan entry di dalam ZIP (misal `storage/app/private/documents/a.pdf`) menjadi path relatif terhadap `storage_path()`
     * sambil mengecualikan folder sensitif/sementara seperti `backups`, `backup-temp`, `framework`, `logs`, `temp-exports`.
     */
    protected function mapZipEntryToStorageRelativePath(string $normalizedPath): ?string
    {
        $relative = null;

        if (str_starts_with($normalizedPath, 'storage/app/private/')) {
            $relative = substr($normalizedPath, strlen('storage/'));
        } elseif (str_starts_with($normalizedPath, 'storage/app/public/')) {
            $relative = substr($normalizedPath, strlen('storage/'));
        } elseif (str_starts_with($normalizedPath, 'app/private/') || str_starts_with($normalizedPath, 'app/public/')) {
            $relative = $normalizedPath;
        }

        if ($relative === null || $relative === '') {
            return null;
        }

        $blockedPrefixes = [
            'app/backups/',
            'app/backup-temp/',
            'app/private/temp-exports/',
            'app/private/livewire-tmp/',
            'framework/',
            'logs/',
        ];

        foreach ($blockedPrefixes as $prefix) {
            if (str_starts_with($relative, $prefix)) {
                return null;
            }
        }

        return $relative;
    }

    protected function extractAndRestoreStorage(ZipArchive $zip, string $stagingDir, string $storageStrategy): int
    {
        $restoredCount = 0;

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $rawName = $zip->getNameIndex($i);
            if ($rawName === false) {
                continue;
            }

            $normalized = str_replace('\\', '/', $rawName);
            if (str_ends_with($normalized, '/')) {
                continue;
            }

            $storageRelative = $this->mapZipEntryToStorageRelativePath($normalized);
            if ($storageRelative === null) {
                continue;
            }

            $inStream = $zip->getStream($rawName);
            if ($inStream === false) {
                throw new RuntimeException("Gagal membuka stream berkas arsip: {$normalized}");
            }

            $stagedFilePath = $stagingDir.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $storageRelative);
            File::ensureDirectoryExists(dirname($stagedFilePath));

            $outHandle = fopen($stagedFilePath, 'wb');
            if ($outHandle === false) {
                fclose($inStream);
                throw new RuntimeException("Gagal menulis berkas staging pemulihan: {$normalized}");
            }

            try {
                if (stream_copy_to_stream($inStream, $outHandle) === false) {
                    throw new RuntimeException("Gagal menyalin stream berkas arsip: {$normalized}");
                }
            } finally {
                fclose($inStream);
                fclose($outHandle);
            }

            $restoredCount++;
        }

        if ($restoredCount === 0) {
            throw new RuntimeException('Tidak ada berkas storage valid yang ditemukan pada arsip backup.');
        }

        if ($storageStrategy === 'replace') {
            $this->cleanStorageDataDirectoriesForReplace();
        }

        foreach (['app/private', 'app/public'] as $subDir) {
            $stagedSub = $stagingDir.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $subDir);
            if (File::isDirectory($stagedSub)) {
                $targetSub = storage_path($subDir);
                File::ensureDirectoryExists($targetSub);
                $this->moveDirectoryContents($stagedSub, $targetSub);
            }
        }

        return $restoredCount;
    }

    /**
     * Memindahkan isi folder staging ke direktori tujuan secara hemat I/O (atomic rename dengan fallback copy).
     */
    protected function moveDirectoryContents(string $sourceDir, string $targetDir): void
    {
        File::ensureDirectoryExists($targetDir);

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($sourceDir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );

        $sourceLen = strlen(rtrim($sourceDir, DIRECTORY_SEPARATOR)) + 1;

        foreach ($iterator as $item) {
            $relative = substr($item->getPathname(), $sourceLen);
            $destination = rtrim($targetDir, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.$relative;

            if ($item->isDir()) {
                File::ensureDirectoryExists($destination);

                continue;
            }

            File::ensureDirectoryExists(dirname($destination));

            if (! @rename($item->getPathname(), $destination)) {
                File::copy($item->getPathname(), $destination);
                @unlink($item->getPathname());
            }
        }
    }

    protected function cleanStorageDataDirectoriesForReplace(): void
    {
        $privateRoot = storage_path('app/private');
        if (File::isDirectory($privateRoot)) {
            $preserveDirs = ['temp-exports', 'livewire-tmp', '.gitignore'];
            foreach (File::directories($privateRoot) as $dir) {
                if (! in_array(basename($dir), $preserveDirs, true)) {
                    File::deleteDirectory($dir);
                }
            }
            foreach (File::files($privateRoot) as $file) {
                if (! in_array($file->getFilename(), $preserveDirs, true)) {
                    File::delete($file->getPathname());
                }
            }
        }

        $publicRoot = storage_path('app/public');
        if (File::isDirectory($publicRoot)) {
            foreach (File::directories($publicRoot) as $dir) {
                File::deleteDirectory($dir);
            }
            foreach (File::files($publicRoot) as $file) {
                if ($file->getFilename() !== '.gitignore') {
                    File::delete($file->getPathname());
                }
            }
        }
    }

    protected function extractAndRestoreDatabase(ZipArchive $zip, string $dumpEntry, string $stagingDir): void
    {
        $matchedRawName = null;
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $rawName = $zip->getNameIndex($i);
            if ($rawName !== false && str_replace('\\', '/', $rawName) === $dumpEntry) {
                $matchedRawName = $rawName;
                break;
            }
        }

        $stream = $matchedRawName !== null ? $zip->getStream($matchedRawName) : false;
        if ($stream === false) {
            throw new RuntimeException("Gagal membaca berkas dump database '{$dumpEntry}' dari arsip ZIP.");
        }

        $rawDumpPath = $stagingDir.DIRECTORY_SEPARATOR.'dump_raw_'.basename($dumpEntry);
        $outHandle = fopen($rawDumpPath, 'wb');
        if ($outHandle === false) {
            fclose($stream);
            throw new RuntimeException('Gagal menulis berkas sementara dump database ke direktori staging.');
        }

        while (! feof($stream)) {
            $chunk = fread($stream, 8192);
            if ($chunk !== false) {
                fwrite($outHandle, $chunk);
            }
        }
        fclose($stream);
        fclose($outHandle);

        $sqlFilePath = $rawDumpPath;

        if (str_ends_with(strtolower($rawDumpPath), '.gz')) {
            $sqlFilePath = $stagingDir.DIRECTORY_SEPARATOR.'restore_uncompressed.sql';
            $gz = gzopen($rawDumpPath, 'rb');
            $sqlOut = fopen($sqlFilePath, 'wb');
            if ($gz === false || $sqlOut === false) {
                throw new RuntimeException('Gagal mendekompresi berkas dump SQL terkompresi (.sql.gz).');
            }

            while (! gzeof($gz)) {
                fwrite($sqlOut, (string) gzread($gz, 8192));
            }
            gzclose($gz);
            fclose($sqlOut);
        }

        $connectionName = (string) config('database.default');
        /** @var array<string, mixed> $dbConfig */
        $dbConfig = config("database.connections.{$connectionName}", []);
        $driver = strtolower((string) ($dbConfig['driver'] ?? $connectionName));

        match ($driver) {
            'pgsql' => $this->restorePostgres($connectionName, $dbConfig, $sqlFilePath, $stagingDir),
            'mysql', 'mariadb' => $this->restoreMysql($connectionName, $dbConfig, $sqlFilePath, $stagingDir),
            'sqlite' => $this->restoreSqlite($connectionName, $dbConfig, $sqlFilePath),
            default => throw new RuntimeException("Driver database '{$driver}' belum didukung untuk pemulihan otomatis."),
        };
    }

    /**
     * @param  array<string, mixed>  $dbConfig
     */
    protected function restorePostgres(string $connectionName, array $dbConfig, string $sqlFilePath, string $stagingDir): void
    {
        if (! is_file($sqlFilePath) || filesize($sqlFilePath) === 0) {
            throw new RuntimeException('Berkas dump PostgreSQL kosong atau tidak dapat dibaca.');
        }

        $psqlBinary = $this->resolveDatabaseBinary('psql', (string) ($dbConfig['dump']['dump_binary_path'] ?? ''));

        $escapedSqlPath = str_replace("'", "''", str_replace('\\', '/', $sqlFilePath));
        $wrapperPath = $stagingDir.DIRECTORY_SEPARATOR.'pg_atomic_restore.sql';
        $wrapperContent = implode(PHP_EOL, [
            'DROP SCHEMA IF EXISTS public CASCADE;',
            'CREATE SCHEMA public;',
            "\\i '{$escapedSqlPath}'",
        ]).PHP_EOL;
        File::put($wrapperPath, $wrapperContent);

        $database = (string) ($dbConfig['database'] ?? '');
        $username = (string) ($dbConfig['username'] ?? 'postgres');
        $password = (string) ($dbConfig['password'] ?? '');
        $host = (string) ($dbConfig['host'] ?? '127.0.0.1');
        $port = (string) ($dbConfig['port'] ?? 5432);

        DB::disconnect($connectionName);

        try {
            // Gunakan transaksi tunggal (-1) agar jika ada kegagalan di tengah impor, DROP SCHEMA ikut di-rollback
            $process = new Process(
                [
                    $psqlBinary,
                    '-h', $host,
                    '-p', $port,
                    '-U', $username,
                    '-d', $database,
                    '-1',
                    '-v', 'ON_ERROR_STOP=1',
                    '-f', $wrapperPath,
                ],
                null,
                array_filter([
                    'PGPASSWORD' => $password,
                    'SystemRoot' => getenv('SystemRoot') ?: null,
                    'TEMP' => sys_get_temp_dir(),
                    'TMP' => sys_get_temp_dir(),
                ], fn ($v): bool => $v !== null),
                null,
                3600
            );
            $process->setIdleTimeout(null);
            $process->run();

            if (! $process->isSuccessful()) {
                $err = trim($process->getErrorOutput() ?: $process->getOutput());
                throw new RuntimeException('Pemulihan PostgreSQL gagal (database dipertahankan pada kondisi sebelumnya): '.Str::limit($err, 600));
            }
        } finally {
            @unlink($wrapperPath);
            DB::purge($connectionName);
            DB::reconnect($connectionName);
        }
    }

    /**
     * @param  array<string, mixed>  $dbConfig
     */
    protected function restoreMysql(string $connectionName, array $dbConfig, string $sqlFilePath, string $stagingDir): void
    {
        if (! is_file($sqlFilePath) || filesize($sqlFilePath) === 0) {
            throw new RuntimeException('Berkas dump MySQL/MariaDB kosong atau tidak dapat dibaca.');
        }

        $mysqlBinary = $this->resolveDatabaseBinary('mysql', (string) ($dbConfig['dump']['dump_binary_path'] ?? ''));

        $defaultsFile = $stagingDir.DIRECTORY_SEPARATOR.'mysql_restore.cnf';
        $cnfContent = implode(PHP_EOL, [
            '[client]',
            'user="'.addslashes((string) ($dbConfig['username'] ?? 'root')).'"',
            'password="'.addslashes((string) ($dbConfig['password'] ?? '')).'"',
            'host="'.addslashes((string) ($dbConfig['host'] ?? '127.0.0.1')).'"',
            'port="'.(int) ($dbConfig['port'] ?? 3306).'"',
        ]).PHP_EOL;
        File::put($defaultsFile, $cnfContent);

        DB::disconnect($connectionName);

        $sqlStream = fopen($sqlFilePath, 'rb');
        if ($sqlStream === false) {
            DB::purge($connectionName);
            DB::reconnect($connectionName);
            throw new RuntimeException('Gagal membuka berkas SQL untuk pemulihan MySQL/MariaDB.');
        }

        try {
            $process = new Process(
                [
                    $mysqlBinary,
                    '--defaults-extra-file='.$defaultsFile,
                    (string) ($dbConfig['database'] ?? ''),
                ],
                null,
                array_filter([
                    'SystemRoot' => getenv('SystemRoot') ?: null,
                    'TEMP' => sys_get_temp_dir(),
                    'TMP' => sys_get_temp_dir(),
                ], fn ($v): bool => $v !== null),
                $sqlStream,
                3600
            );
            $process->setIdleTimeout(null);
            $process->run();

            if (! $process->isSuccessful()) {
                $err = trim($process->getErrorOutput() ?: $process->getOutput());
                throw new RuntimeException('Pemulihan MySQL/MariaDB gagal: '.Str::limit($err, 600));
            }
        } finally {
            if (is_resource($sqlStream)) {
                fclose($sqlStream);
            }
            @unlink($defaultsFile);
            DB::purge($connectionName);
            DB::reconnect($connectionName);
        }
    }

    /**
     * @param  array<string, mixed>  $dbConfig
     */
    protected function restoreSqlite(string $connectionName, array $dbConfig, string $sqlFilePath): void
    {
        $targetDb = (string) ($dbConfig['database'] ?? '');
        $pdo = DB::connection($connectionName)->getPdo();

        if (str_ends_with(strtolower($sqlFilePath), '.sqlite')) {
            if ($targetDb === ':memory:') {
                throw new RuntimeException('Berkas biner .sqlite tidak dapat langsung mengganti database SQLite :memory:.');
            }

            DB::disconnect($connectionName);
            File::copy($sqlFilePath, $targetDb);
            DB::purge($connectionName);
            DB::reconnect($connectionName);

            return;
        }

        $sql = (string) File::get($sqlFilePath);
        $cleanedSql = preg_replace('/^\s*(BEGIN\s+(IMMEDIATE|TRANSACTION)?|COMMIT|PRAGMA\s+foreign_keys[^;]*);\s*$/im', '', $sql);
        if (trim((string) $cleanedSql) === '') {
            throw new RuntimeException('Berkas dump SQLite tidak mengandung perintah SQL yang valid.');
        }

        // Bersihkan seluruh tabel eksisting di SQLite sebelum eksekusi skrip SQL (urut terbalik agar child table dihapus lebih dulu)
        $pdo->exec('PRAGMA foreign_keys = OFF;');
        $pdo->exec('PRAGMA defer_foreign_keys = ON;');
        $tables = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%' ORDER BY rowid DESC")?->fetchAll(\PDO::FETCH_COLUMN) ?: [];
        foreach ($tables as $tableName) {
            $escaped = str_replace('"', '""', (string) $tableName);
            $pdo->exec("DROP TABLE IF EXISTS \"{$escaped}\";");
        }

        // Jalankan perintah SQL dari dump (bersihkan BEGIN/COMMIT wrapper jika diparse oleh PDO)
        $pdo->exec((string) $cleanedSql);
        $pdo->exec('PRAGMA defer_foreign_keys = OFF;');
        $pdo->exec('PRAGMA foreign_keys = ON;');
    }

    protected function resolveDatabaseBinary(string $binaryName, string $configuredDir = ''): string
    {
        $isWindows = DIRECTORY_SEPARATOR === '\\';
        $exeName = $isWindows ? "{$binaryName}.exe" : $binaryName;

        if ($configuredDir !== '') {
            $candidate = rtrim(str_replace('\\', '/', $configuredDir), '/').'/'.$exeName;
            if (is_file($candidate)) {
                return $candidate;
            }
        }

        if ($isWindows && $binaryName === 'psql') {
            foreach (['18', '17', '16', '15', '14'] as $ver) {
                $pgCandidate = "C:/Program Files/PostgreSQL/{$ver}/bin/psql.exe";
                if (is_file($pgCandidate)) {
                    return $pgCandidate;
                }
            }
        }

        return $exeName;
    }

    /**
     * @param  array<int, string>  $excludedDirs
     * @return array{files: int, bytes: int}
     */
    protected function measureDirectory(string $path, array $excludedDirs = []): array
    {
        if (! File::isDirectory($path)) {
            return ['files' => 0, 'bytes' => 0];
        }

        $files = 0;
        $bytes = 0;

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($path, RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $fileInfo) {
            if (! $fileInfo->isFile()) {
                continue;
            }

            $relative = str_replace('\\', '/', substr($fileInfo->getPathname(), strlen($path) + 1));
            $topSegment = explode('/', $relative)[0] ?? '';

            if (in_array($topSegment, $excludedDirs, true) || $fileInfo->getFilename() === '.gitignore') {
                continue;
            }

            $files++;
            $bytes += (int) $fileInfo->getSize();
        }

        return ['files' => $files, 'bytes' => $bytes];
    }
}
