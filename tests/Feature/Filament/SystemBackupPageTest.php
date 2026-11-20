<?php

declare(strict_types=1);

namespace Tests\Feature\Filament;

use App\Enums\UnitType;
use App\Filament\Pages\SystemBackup;
use App\Jobs\RunSystemBackupJob;
use App\Jobs\RunSystemRestoreJob;
use App\Models\Permission;
use App\Models\Setting;
use App\Models\Unit;
use App\Models\User;
use App\Services\SystemBackupService;
use Carbon\Carbon;
use Filament\Notifications\DatabaseNotification;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use RuntimeException;
use Spatie\Activitylog\Models\Activity;
use Spatie\Backup\Tasks\Backup\DbDumperFactory;
use Spatie\DbDumper\DbDumper;
use Spatie\Permission\PermissionRegistrar;
use Tests\Support\LegacyMasterDataSeeder;
use Tests\Support\SqlitePdoTestDumper;
use Tests\TestCase;
use ZipArchive;

class SystemBackupPageTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected string $isolatedBasePath = '';

    protected string $isolatedStoragePath = '';

    protected string $originalStoragePath = '';

    protected function setUp(): void
    {
        parent::setUp();

        $this->originalStoragePath = storage_path();
        $this->isolatedBasePath = sys_get_temp_dir().DIRECTORY_SEPARATOR.'univbi_backup_test_'.bin2hex(random_bytes(5));
        $this->isolatedStoragePath = $this->isolatedBasePath.DIRECTORY_SEPARATOR.'storage';

        foreach ([
            'app/backups',
            'app/backup-temp',
            'app/private/documents/2026/11',
            'app/private/incoming-letters/2026',
            'app/private/temp-exports',
            'app/private/livewire-tmp',
            'app/public/landing/hero',
            'framework/cache/data',
            'framework/sessions',
            'framework/views',
            'logs',
        ] as $subDir) {
            File::ensureDirectoryExists($this->isolatedStoragePath.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $subDir));
        }

        $this->app->useStoragePath($this->isolatedStoragePath);

        config([
            'filesystems.disks.backups.root' => $this->isolatedStoragePath.DIRECTORY_SEPARATOR.'app'.DIRECTORY_SEPARATOR.'backups',
            'filesystems.disks.private.root' => $this->isolatedStoragePath.DIRECTORY_SEPARATOR.'app'.DIRECTORY_SEPARATOR.'private',
            'filesystems.disks.public.root' => $this->isolatedStoragePath.DIRECTORY_SEPARATOR.'app'.DIRECTORY_SEPARATOR.'public',
            'filesystems.disks.local.root' => $this->isolatedStoragePath.DIRECTORY_SEPARATOR.'app'.DIRECTORY_SEPARATOR.'private',
            'backup.backup.source.files.include' => [$this->isolatedStoragePath],
            'backup.backup.source.files.relative_path' => $this->isolatedBasePath,
            'backup.backup.source.files.exclude' => [
                $this->isolatedStoragePath.DIRECTORY_SEPARATOR.'app'.DIRECTORY_SEPARATOR.'backups',
                $this->isolatedStoragePath.DIRECTORY_SEPARATOR.'app'.DIRECTORY_SEPARATOR.'backup-temp',
                $this->isolatedStoragePath.DIRECTORY_SEPARATOR.'app'.DIRECTORY_SEPARATOR.'private'.DIRECTORY_SEPARATOR.'backup-restore-uploads',
                $this->isolatedStoragePath.DIRECTORY_SEPARATOR.'app'.DIRECTORY_SEPARATOR.'private'.DIRECTORY_SEPARATOR.'temp-exports',
                $this->isolatedStoragePath.DIRECTORY_SEPARATOR.'app'.DIRECTORY_SEPARATOR.'private'.DIRECTORY_SEPARATOR.'livewire-tmp',
                $this->isolatedStoragePath.DIRECTORY_SEPARATOR.'framework',
                $this->isolatedStoragePath.DIRECTORY_SEPARATOR.'logs',
            ],
            'backup.backup.destination.disks' => ['backups'],
            'backup.backup.temporary_directory' => $this->isolatedStoragePath.DIRECTORY_SEPARATOR.'app'.DIRECTORY_SEPARATOR.'backup-temp',
            'view.compiled' => $this->isolatedStoragePath.DIRECTORY_SEPARATOR.'framework'.DIRECTORY_SEPARATOR.'views',
        ]);

        Storage::forgetDisk('backups');
        Storage::forgetDisk('private');
        Storage::forgetDisk('public');
        Storage::forgetDisk('local');

        $this->registerPdoSqliteDumper();
        $this->seed(LegacyMasterDataSeeder::class);
    }

    protected function tearDown(): void
    {
        $this->app->useStoragePath($this->originalStoragePath);

        if ($this->isolatedBasePath !== '' && File::isDirectory($this->isolatedBasePath)) {
            File::deleteDirectory($this->isolatedBasePath);
        }

        parent::tearDown();
    }

    public function test_only_super_admin_with_backup_permission_can_access_page_and_download(): void
    {
        $superAdmin = $this->makeUserWithRole('super_admin');
        $rektor = $this->makeUserWithRole('rektor');
        $humas = $this->makeUserWithRole('humas');
        $ict = $this->makeUserWithRole('upt_ict');
        $stafTi = $this->makeUserWithRole('staf_unit');

        $this->actingAs($superAdmin)
            ->get('/admin/system-backup')
            ->assertOk()
            ->assertSee('Pusat Backup & Pemulihan Sistem Terpadu')
            ->assertSee('css/system-backup.css');

        foreach ([$rektor, $humas, $ict, $stafTi] as $nonSuperAdmin) {
            $this->actingAs($nonSuperAdmin)
                ->get('/admin/system-backup')
                ->assertForbidden();

            $this->actingAs($nonSuperAdmin)
                ->get(route('admin.system-backups.download', ['filename' => '2026-11-14-01-00-00.zip']))
                ->assertForbidden();
        }

        // Super Admin otomatis memiliki akses ke semua modul via Gate::before meskipun izin eksplisit dicabut
        $superAdminRole = $superAdmin->roles()->firstOrFail();
        $superAdminRole->revokePermissionTo('system.backup_manage');
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $superAdmin->refresh();

        $this->actingAs($superAdmin)
            ->get('/admin/system-backup')
            ->assertOk();

        // Sekalipun punya izin system.backup_manage tanpa role super_admin, tetap ditolak 403
        Permission::findOrCreate('system.backup_manage', 'web');
        $ict->givePermissionTo('system.backup_manage');
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->actingAs($ict->fresh())
            ->get('/admin/system-backup')
            ->assertForbidden();
    }

    public function test_full_backup_packages_database_and_full_storage_structure_excluding_cache_and_backups(): void
    {
        $superAdmin = $this->makeUserWithRole('super_admin');
        $service = app(SystemBackupService::class);

        File::put(storage_path('app/private/documents/2026/11/sk-rektor.pdf'), '%PDF-1.4 TEST PRIVATE DOC');
        File::put(storage_path('app/private/incoming-letters/2026/surat-masuk.pdf'), '%PDF-1.4 TEST LETTER');
        File::put(storage_path('app/public/landing/hero/banner.png'), 'PNG_BINARY_CONTENT');
        // Direktori yang wajib dikecualikan
        File::put(storage_path('app/private/temp-exports/should-skip.zip'), 'SKIP');
        File::put(storage_path('logs/laravel.log'), 'LOG_SKIP');
        File::put(storage_path('framework/cache/data/cache-skip'), 'CACHE_SKIP');

        $result = $service->runFullBackup($superAdmin, 'manual');

        $this->assertNotEmpty($result['file_name']);
        $this->assertGreaterThan(0, $result['size_bytes']);

        $zipPath = $service->resolveBackupFileOnDisk($result['file_name']);
        $this->assertNotNull($zipPath);
        $this->assertFileExists($zipPath);

        $inspection = $service->inspectArchive($zipPath, $superAdmin);
        $this->assertTrue($inspection['has_database']);
        $this->assertTrue($inspection['has_storage']);
        $this->assertGreaterThanOrEqual(3, $inspection['storage_files_count']);

        $entries = $this->collectZipEntries($zipPath);

        $this->assertTrue(collect($entries)->contains(fn (string $e): bool => str_starts_with($e, 'db-dumps/') && str_ends_with($e, '.sql')));
        $this->assertTrue(collect($entries)->contains(fn (string $e): bool => str_ends_with($e, 'app/private/documents/2026/11/sk-rektor.pdf')));
        $this->assertTrue(collect($entries)->contains(fn (string $e): bool => str_ends_with($e, 'app/private/incoming-letters/2026/surat-masuk.pdf')));
        $this->assertTrue(collect($entries)->contains(fn (string $e): bool => str_ends_with($e, 'app/public/landing/hero/banner.png')));

        $this->assertFalse(collect($entries)->contains(fn (string $e): bool => str_contains($e, 'should-skip.zip')));
        $this->assertFalse(collect($entries)->contains(fn (string $e): bool => str_contains($e, 'laravel.log')));
        $this->assertFalse(collect($entries)->contains(fn (string $e): bool => str_contains($e, 'cache-skip')));

        // Uji download lewat controller terproteksi
        $this->actingAs($superAdmin)
            ->get(route('admin.system-backups.download', ['filename' => $result['file_name']]))
            ->assertOk()
            ->assertHeader('content-disposition', 'attachment; filename='.$result['file_name']);

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'system_backup',
            'event' => 'backup_downloaded',
            'causer_id' => $superAdmin->id,
        ]);
    }

    public function test_archive_security_scanner_blocks_dangerous_extensions_and_zip_slip(): void
    {
        $superAdmin = $this->makeUserWithRole('super_admin');
        $service = app(SystemBackupService::class);

        $maliciousPayloads = [
            'php_webshell' => ['storage/app/private/shell.php' => '<?php system($_GET["c"]);'],
            'xml_payload' => ['storage/app/private/config.xml' => '<?xml version="1.0"?><!DOCTYPE foo>'],
            'svg_xss' => ['storage/app/public/xss.svg' => '<svg onload="alert(1)"/>'],
            'html_script' => ['storage/app/public/phish.html' => '<script>alert(1)</script>'],
            'txt_unknown' => ['storage/app/private/notes.txt' => 'arbitrary text file'],
            'extensionless_file' => ['storage/app/private/binaryfile' => 'ELF_DATA'],
            'python_script' => ['storage/app/public/exploit.py' => 'import os; print("x")'],
            'shell_script' => ['storage/app/private/run.sh' => '#!/bin/sh'],
            'double_extension_php' => ['storage/app/public/avatar.php.jpg' => 'payload'],
            'double_extension_xml' => ['storage/app/private/dokumen.xml.pdf' => 'payload'],
            'htaccess' => ['storage/app/public/.htaccess' => 'AddType application/x-httpd-php .jpg'],
            'zip_slip' => ['../../public/backdoor.txt' => 'traversal'],
            'unexpected_sql' => ['storage/app/private/hijack.sql' => 'DROP TABLE users;'],
            'nested_zip_in_documents' => ['storage/app/private/documents/2026/11/unexpected.zip' => 'PK_ARCHIVE'],
        ];

        foreach ($maliciousPayloads as $label => $files) {
            $maliciousZip = $this->isolatedStoragePath."/malicious_{$label}.zip";
            $zip = new ZipArchive;
            $this->assertTrue($zip->open($maliciousZip, ZipArchive::CREATE | ZipArchive::OVERWRITE));
            foreach ($files as $entryName => $content) {
                $zip->addFromString($entryName, $content);
            }
            $zip->close();

            try {
                $service->inspectArchive($maliciousZip, $superAdmin, recordSecurityAudit: true);
                $this->fail("Seharusnya arsip berbahaya [{$label}] ditolak oleh scanner keamanan.");
            } catch (RuntimeException $e) {
                $this->assertStringContainsString('keamanan', strtolower($e->getMessage()));
            }
        }

        $blockedCount = Activity::query()
            ->where('log_name', 'system_backup')
            ->where('event', 'restore_security_blocked')
            ->count();

        $this->assertSame(count($maliciousPayloads), $blockedCount);

        // Pastikan seluruh ekstensi whitelist sah (pdf, doc, docx, gambar aman, db-dumps .sql, .gitignore) lolos inspeksi
        $safeZipPath = $this->isolatedStoragePath.'/safe_whitelist.zip';
        $safeZip = new ZipArchive;
        $this->assertTrue($safeZip->open($safeZipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE));
        $safeZip->addFromString('db-dumps/postgresql-unbi.sql', 'SELECT 1;');
        $safeZip->addFromString('storage/app/private/.gitignore', "*\n!.gitignore\n");
        $safeZip->addFromString('storage/app/private/backup-restore-uploads/legacy-temp.zip', 'TEMP_UPLOAD_ARCHIVE');
        $safeZip->addFromString('storage/app/private/documents/2026/11/sk.pdf', '%PDF-1.4 SAFE');
        $safeZip->addFromString('storage/app/private/documents/2026/11/surat.doc', 'DOC_SAFE');
        $safeZip->addFromString('storage/app/private/documents/2026/11/naskah.docx', 'DOCX_SAFE');
        $safeZip->addFromString('storage/app/public/landing/foto.jpg', 'JPG_SAFE');
        $safeZip->addFromString('storage/app/public/landing/logo.png', 'PNG_SAFE');
        $safeZip->addFromString('storage/app/public/landing/banner.webp', 'WEBP_SAFE');
        $safeZip->addFromString('storage/app/public/landing/anim.gif', 'GIF_SAFE');
        $safeZip->addFromString('storage/app/public/landing/modern.avif', 'AVIF_SAFE');
        $safeZip->close();

        $inspection = $service->inspectArchive($safeZipPath, $superAdmin, recordSecurityAudit: false);
        $this->assertTrue($inspection['valid']);
        $this->assertTrue($inspection['has_database']);
        $this->assertTrue($inspection['has_storage']);
        $this->assertSame(9, $inspection['storage_files_count']);
    }

    public function test_restore_storage_merge_and_replace_and_database_restore_work_safely(): void
    {
        $superAdmin = $this->makeUserWithRole('super_admin');
        $service = app(SystemBackupService::class);

        // Siapkan kondisi awal DB + Storage
        Unit::create([
            'code' => 'BACKUP-KEEP',
            'name' => 'Unit Tetap Dipertahankan Saat Restore',
            'type' => UnitType::cases()[0],
            'is_active' => true,
        ]);

        File::put(storage_path('app/private/documents/2026/11/dokumen-asli.pdf'), 'ORIGINAL_PDF_BYTES');
        File::put(storage_path('app/public/landing/logo-kampus.png'), 'ORIGINAL_LOGO');

        $backup = $service->runFullBackup($superAdmin, 'manual');

        // Modifikasi kondisi setelah backup (hapus file asli, tambah unit & file pasca-backup)
        File::delete(storage_path('app/private/documents/2026/11/dokumen-asli.pdf'));
        File::put(storage_path('app/private/documents/2026/11/dokumen-baru.pdf'), 'NEW_AFTER_BACKUP');
        Unit::create([
            'code' => 'POST-BACKUP-ONLY',
            'name' => 'Unit Setelah Backup',
            'type' => UnitType::cases()[0],
            'is_active' => true,
        ]);

        $this->assertFileDoesNotExist(storage_path('app/private/documents/2026/11/dokumen-asli.pdf'));
        $this->assertDatabaseHas('units', ['code' => 'POST-BACKUP-ONLY']);

        // 1. Uji Restore Storage (Merge): file asli kembali & file baru pasca-backup tetap ada
        $mergeResult = $service->restoreFromDisk(
            filename: $backup['file_name'],
            mode: 'storage',
            storageStrategy: 'merge',
            createSafetyBackup: false,
            actor: $superAdmin,
        );

        $this->assertGreaterThan(0, $mergeResult['restored_storage_files']);
        $this->assertFalse($mergeResult['restored_database']);
        $this->assertSame('ORIGINAL_PDF_BYTES', File::get(storage_path('app/private/documents/2026/11/dokumen-asli.pdf')));
        $this->assertFileExists(storage_path('app/private/documents/2026/11/dokumen-baru.pdf'));

        // 2. Uji Restore Storage (Replace): file pasca-backup dihapus agar sama persis dengan paket backup
        $service->restoreFromDisk(
            filename: $backup['file_name'],
            mode: 'storage',
            storageStrategy: 'replace',
            createSafetyBackup: false,
            actor: $superAdmin,
        );

        $this->assertFileExists(storage_path('app/private/documents/2026/11/dokumen-asli.pdf'));
        $this->assertFileDoesNotExist(storage_path('app/private/documents/2026/11/dokumen-baru.pdf'));

        // 3. Uji Restore Database (Full / DB): Unit pasca-backup hilang, unit saat backup tetap ada
        $dbResult = $service->restoreFromDisk(
            filename: $backup['file_name'],
            mode: 'database',
            storageStrategy: 'merge',
            createSafetyBackup: true,
            actor: $superAdmin,
        );

        $this->assertTrue($dbResult['restored_database']);
        $this->assertNotNull($dbResult['safety_backup_file']);
        $this->assertDatabaseHas('units', ['code' => 'BACKUP-KEEP']);
        $this->assertDatabaseMissing('units', ['code' => 'POST-BACKUP-ONLY']);
    }

    public function test_configurable_schedule_evaluates_daily_weekly_monthly_and_command_runs(): void
    {
        Queue::fake();
        $superAdmin = $this->makeUserWithRole('super_admin');
        $service = app(SystemBackupService::class);

        // Weekly: Senin (dayOfWeek = 1) pukul 01:30 WIB
        $service->saveScheduleConfig([
            'enabled' => true,
            'frequency' => 'weekly',
            'day_of_week' => 1,
            'time' => '01:30',
            'auto_clean' => false,
        ], $superAdmin);

        $sunday = Carbon::create(2026, 11, 15, 1, 30, 0, 'Asia/Jakarta'); // Minggu (0)
        $mondayEarly = Carbon::create(2026, 11, 16, 1, 29, 0, 'Asia/Jakarta'); // Senin (1) sebelum 01:30
        $mondayDue = Carbon::create(2026, 11, 16, 1, 30, 0, 'Asia/Jakarta'); // Senin (1) pukul 01:30

        $this->assertFalse($service->isScheduleDue($sunday));
        $this->assertFalse($service->isScheduleDue($mondayEarly));
        $this->assertTrue($service->isScheduleDue($mondayDue));

        $service->markScheduledRun($mondayDue);
        $this->assertFalse($service->isScheduleDue($mondayDue->copy()->addMinutes(5)));

        // Monthly: Tanggal 25 pukul 02:00 WIB
        $service->saveScheduleConfig([
            'enabled' => true,
            'frequency' => 'monthly',
            'day_of_month' => 25,
            'time' => '02:00',
            'auto_clean' => true,
        ], $superAdmin);

        $nov24 = Carbon::create(2026, 11, 24, 2, 0, 0, 'Asia/Jakarta');
        $nov25 = Carbon::create(2026, 11, 25, 2, 0, 0, 'Asia/Jakarta');

        $this->assertFalse($service->isScheduleDue($nov24));
        $this->assertTrue($service->isScheduleDue($nov25));

        // Uji Livewire Page Action simpan jadwal & trigger backup antrean
        $this->actingAs($superAdmin);

        Livewire::test(SystemBackup::class)
            ->callAction('configureSchedule', data: [
                'enabled' => true,
                'frequency' => 'daily',
                'time' => '03:15',
                'auto_clean' => true,
            ])
            ->assertHasNoActionErrors()
            ->callAction('createBackup', data: [
                'execution_mode' => 'queue',
                'run_cleanup' => false,
            ])
            ->assertHasNoActionErrors();

        Queue::assertPushed(RunSystemBackupJob::class);

        $stored = Setting::where('key', SystemBackupService::SCHEDULE_SETTING_KEY)->firstOrFail();
        $decoded = json_decode((string) $stored->value, true);
        $this->assertTrue($decoded['enabled']);
        $this->assertSame('daily', $decoded['frequency']);
        $this->assertSame('03:15', $decoded['time']);

        // Uji artisan command --force menjalankan backup terjadwal
        $exitCode = Artisan::call('system:run-scheduled-backup', ['--force' => true]);
        $this->assertSame(0, $exitCode);
    }

    public function test_livewire_restore_action_rejects_wrong_password(): void
    {
        $superAdmin = $this->makeUserWithRole('super_admin');
        $service = app(SystemBackupService::class);

        File::put(storage_path('app/private/documents/2026/11/test.pdf'), 'PDF');
        $backup = $service->runFullBackup($superAdmin, 'manual');

        $this->actingAs($superAdmin);

        Livewire::test(SystemBackup::class)
            ->callAction('restoreBackup', data: [
                'mode' => 'storage',
                'storage_strategy' => 'merge',
                'create_safety_backup' => false,
                'current_password' => 'PasswordSalah123!',
            ], arguments: [
                'filename' => $backup['file_name'],
            ])
            ->assertHasActionErrors(['current_password']);
    }

    public function test_database_only_backup_excludes_storage_files_and_restores_sql_safely(): void
    {
        $superAdmin = $this->makeUserWithRole('super_admin');
        $service = app(SystemBackupService::class);

        Unit::create([
            'code' => 'DB-ONLY-KEEP',
            'name' => 'Unit Cadangan SQL Saja',
            'type' => UnitType::cases()[0],
            'is_active' => true,
        ]);

        File::put(storage_path('app/private/documents/2026/11/file-storage-besar.pdf'), 'LARGE_BINARY_SHOULD_EXCLUDE');

        $result = $service->runBackup(SystemBackupService::SCOPE_DATABASE_ONLY, $superAdmin, 'manual');

        $this->assertStringContainsString('-db-only', $result['file_name']);
        $this->assertSame(SystemBackupService::SCOPE_DATABASE_ONLY, $result['scope']);

        $zipPath = $service->resolveBackupFileOnDisk($result['file_name']);
        $this->assertNotNull($zipPath);

        $inspection = $service->inspectArchive($zipPath, $superAdmin);
        $this->assertTrue($inspection['has_database']);
        $this->assertFalse($inspection['has_storage']);
        $this->assertSame(0, $inspection['storage_files_count']);

        $entries = $this->collectZipEntries($zipPath);
        $this->assertTrue(collect($entries)->contains(fn (string $e): bool => str_starts_with($e, 'db-dumps/') && str_ends_with($e, '.sql')));
        $this->assertFalse(collect($entries)->contains(fn (string $e): bool => str_contains($e, 'file-storage-besar.pdf')));

        // Pastikan restore storage ditolak dengan pesan jelas pada paket database_only
        try {
            $service->restoreFromDisk(
                filename: $result['file_name'],
                mode: 'storage',
                storageStrategy: 'merge',
                createSafetyBackup: false,
                actor: $superAdmin,
            );
            $this->fail('Restore storage seharusnya ditolak untuk paket Database Saja.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('Database Saja', $e->getMessage());
        }

        // Tambah unit pasca-backup, lalu pulihkan DB saja -> unit pasca-backup terhapus, unit saat backup kembali
        Unit::create([
            'code' => 'DB-ONLY-AFTER',
            'name' => 'Unit Pasca Backup SQL',
            'type' => UnitType::cases()[0],
            'is_active' => true,
        ]);

        $restoreResult = $service->restoreFromDisk(
            filename: $result['file_name'],
            mode: 'database',
            storageStrategy: 'merge',
            createSafetyBackup: true,
            actor: $superAdmin,
        );

        $this->assertTrue($restoreResult['restored_database']);
        $this->assertStringContainsString('-db-only', (string) $restoreResult['safety_backup_file']);
        $this->assertDatabaseHas('units', ['code' => 'DB-ONLY-KEEP']);
        $this->assertDatabaseMissing('units', ['code' => 'DB-ONLY-AFTER']);
    }

    public function test_backup_listing_and_storage_summary_work_cleanly_with_database_cache_and_manual_refresh(): void
    {
        Cache::setDefaultDriver('database');

        $superAdmin = $this->makeUserWithRole('super_admin');
        $service = app(SystemBackupService::class);

        File::put(storage_path('app/private/documents/2026/11/cached-sample.pdf'), 'PDF_BYTES');
        $service->runBackup(SystemBackupService::SCOPE_DATABASE_ONLY, $superAdmin, 'manual');

        // Panggilan ke-1 & ke-2 dengan cache database (memastikan bebas __PHP_Incomplete_Class / Carbon error)
        $listFirst = $service->listBackups();
        $listSecond = $service->listBackups();
        $summaryFirst = $service->getStorageSummary();
        $summarySecond = $service->getStorageSummary();

        $this->assertCount(1, $listSecond);
        $this->assertInstanceOf(Carbon::class, $listSecond[0]['created_at']);
        $this->assertSame(SystemBackupService::SCOPE_DATABASE_ONLY, $listSecond[0]['scope']);
        $this->assertSame($summaryFirst['backup_count'], $summarySecond['backup_count']);

        $this->actingAs($superAdmin);
        Livewire::test(SystemBackup::class)
            ->callAction('refreshBackups')
            ->assertNotified('Daftar & Statistik Diperbarui');
    }

    public function test_queued_restore_job_dispatches_and_executes_streaming_restore_cleanly(): void
    {
        Queue::fake();

        $superAdmin = $this->makeUserWithRole('super_admin');
        $service = app(SystemBackupService::class);

        File::put(storage_path('app/private/documents/2026/11/stream-restore.pdf'), 'STREAM_ORIGINAL_BYTES');
        $backup = $service->runFullBackup($superAdmin, 'manual');

        $this->actingAs($superAdmin);

        Livewire::test(SystemBackup::class)
            ->callAction('restoreBackup', data: [
                'mode' => 'full',
                'storage_strategy' => 'merge',
                'execution_mode' => 'queue',
                'create_safety_backup' => false,
                'current_password' => 'password',
            ], arguments: [
                'filename' => $backup['file_name'],
            ])
            ->assertHasNoActionErrors()
            ->assertNotified('Proses Restore Dijadwalkan ke Latar Belakang');

        Queue::assertPushed(RunSystemRestoreJob::class, function (RunSystemRestoreJob $job) use ($superAdmin, $backup): bool {
            return $job->actorId === $superAdmin->id
                && $job->sourceType === 'disk'
                && $job->target === $backup['file_name']
                && $job->mode === 'full';
        });

        // Jalankan job restore secara langsung terhadap salinan arsip yang diunggah untuk uji streaming & pembersihan berkas sementara
        Notification::fake();
        File::delete(storage_path('app/private/documents/2026/11/stream-restore.pdf'));

        $uploadedRelPath = 'backup-restore-uploads/manual-upload.zip';
        $diskPath = (string) $service->resolveBackupFileOnDisk($backup['file_name']);
        Storage::disk('local')->makeDirectory('backup-restore-uploads');
        File::copy($diskPath, Storage::disk('local')->path($uploadedRelPath));

        $restoreJob = new RunSystemRestoreJob(
            actorId: $superAdmin->id,
            sourceType: 'uploaded_path',
            target: $uploadedRelPath,
            mode: 'storage',
            storageStrategy: 'merge',
            createSafetyBackup: false,
        );
        $restoreJob->handle($service);

        $this->assertSame('STREAM_ORIGINAL_BYTES', File::get(storage_path('app/private/documents/2026/11/stream-restore.pdf')));
        $this->assertFalse(Storage::disk('local')->exists($uploadedRelPath));
        Notification::assertSentTo($superAdmin, DatabaseNotification::class);
    }

    public function test_backup_uses_os_file_lock_without_cache_locks_deadlock_and_queue_retry_after_exceeds_timeout(): void
    {
        $this->assertGreaterThan(3600, (int) config('queue.connections.database.retry_after'));

        $superAdmin = $this->makeUserWithRole('super_admin');
        $service = app(SystemBackupService::class);

        // Simulasikan sisa baris lock lama di tabel database cache_locks akibat restore dump versi sebelumnya
        DB::table('cache_locks')->insert([
            'key' => 'e-arsip-unbi-cache-system_backup_execution_lock',
            'owner' => 'legacy-stuck-owner',
            'expiration' => now()->addHour()->timestamp,
        ]);

        // Backup & restore dengan safety backup tetap wajib sukses tanpa terblokir oleh tabel cache_locks
        $backup = $service->runBackup(SystemBackupService::SCOPE_DATABASE_ONLY, $superAdmin, 'manual');

        $restoreResult = $service->restoreFromDisk(
            filename: $backup['file_name'],
            mode: 'database',
            storageStrategy: 'merge',
            createSafetyBackup: true,
            actor: $superAdmin,
        );

        $this->assertTrue($restoreResult['restored_database']);
        $this->assertDatabaseMissing('cache_locks', [
            'key' => 'e-arsip-unbi-cache-system_backup_execution_lock',
        ]);
    }

    public function test_effective_browser_upload_limits_and_upload_and_restore_action_work_cleanly(): void
    {
        Queue::fake();

        $service = app(SystemBackupService::class);

        // Simulasi batas PHP sempit (2M) harus langsung terdeteksi sebagai pembatas
        $restricted = $service->getEffectiveBrowserUploadLimits('2M', '8M', ['required', 'file', 'max:524288']);
        $this->assertTrue($restricted['is_restricted_by_server']);
        $this->assertSame(2048, $restricted['max_kb']);

        // Simulasi batas 512M di PHP + Livewire (sesuai config aplikasi)
        $aligned = $service->getEffectiveBrowserUploadLimits('512M', '528M', config('livewire.temporary_file_upload.rules'));
        $this->assertFalse($aligned['is_restricted_by_server']);
        $this->assertSame(524288, $aligned['max_kb']);

        // Buat arsip .zip backup berukuran > 2 MB menggunakan data acak tak terkompresi untuk uji batas unggah
        $superAdmin = $this->makeUserWithRole('super_admin');
        File::put(storage_path('app/private/documents/2026/11/upload-check.pdf'), random_bytes(2_500_000));
        $backup = $service->runFullBackup($superAdmin, 'manual');
        $zipAbsolutePath = (string) $service->resolveBackupFileOnDisk($backup['file_name']);
        $this->assertGreaterThan(2 * 1024 * 1024, filesize($zipAbsolutePath));

        $uploadedFile = UploadedFile::fake()->createWithContent(
            $backup['file_name'],
            (string) file_get_contents($zipAbsolutePath)
        );

        $this->actingAs($superAdmin);

        Livewire::test(SystemBackup::class)
            ->callAction('uploadAndRestore', data: [
                'archive' => $uploadedFile,
                'mode' => 'full',
                'storage_strategy' => 'merge',
                'execution_mode' => 'queue',
                'create_safety_backup' => false,
                'current_password' => 'password',
            ])
            ->assertHasNoActionErrors()
            ->assertNotified('Proses Restore Dijadwalkan ke Latar Belakang');

        Queue::assertPushed(RunSystemRestoreJob::class, function (RunSystemRestoreJob $job) use ($superAdmin): bool {
            return $job->actorId === $superAdmin->id
                && $job->sourceType === 'uploaded_path'
                && $job->mode === 'full';
        });
    }

    protected function makeUserWithRole(string $role): User
    {
        $unit = Unit::first();
        $user = User::factory()->create([
            'unit_id' => $unit?->id,
        ]);
        $user->assignRole($role);

        return $user->fresh();
    }

    /**
     * Daftarkan dumper PDO murni untuk koneksi SQLite `:memory:` pada lingkungan test PHPUnit.
     */
    protected function registerPdoSqliteDumper(): void
    {
        DbDumperFactory::extend('sqlite', function (): DbDumper {
            return (new SqlitePdoTestDumper)->setDbName('unbi_test_sqlite');
        });
    }

    /**
     * @return array<int, string>
     */
    protected function collectZipEntries(string $zipPath): array
    {
        $zip = new ZipArchive;
        $zip->open($zipPath);
        $entries = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $entries[] = str_replace('\\', '/', (string) $zip->getNameIndex($i));
        }
        $zip->close();

        return $entries;
    }
}
