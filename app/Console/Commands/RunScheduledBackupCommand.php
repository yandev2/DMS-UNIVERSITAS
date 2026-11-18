<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Filament\Pages\SystemBackup;
use App\Models\User;
use App\Services\SystemBackupService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

class RunScheduledBackupCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'system:run-scheduled-backup {--force : Paksa eksekusi backup terjadwal tanpa menunggu jam jadwal}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Menjalankan backup sistem terpadu (Database + Storage) secara otomatis sesuai jadwal rutin yang dikonfigurasi Super Admin.';

    public function handle(SystemBackupService $backupService): int
    {
        $now = now('Asia/Jakarta');
        $force = (bool) $this->option('force');
        $config = $backupService->getScheduleConfig();

        if (! $force && ! $backupService->isScheduleDue($now)) {
            $this->line('Belum memasuki jadwal rutin backup otomatis.');

            return self::SUCCESS;
        }

        $scope = (string) ($config['scope'] ?? SystemBackupService::SCOPE_DATABASE_ONLY);
        $scopeLabel = $scope === SystemBackupService::SCOPE_DATABASE_ONLY ? 'Database SQL Saja' : 'Database + Storage';

        $this->info("Memulai eksekusi backup sistem terjadwal ({$scopeLabel})...");

        try {
            $result = $backupService->runBackup($scope, null, 'scheduled');

            if ($config['auto_clean']) {
                $backupService->runCleanup();
            }

            $backupService->markScheduledRun($now);

            $sizeHuman = $backupService->formatBytes($result['size_bytes']);
            $pageUrl = url('/admin/system-backup');
            if (class_exists(SystemBackup::class)) {
                try {
                    $pageUrl = SystemBackup::getUrl(panel: 'admin');
                } catch (Throwable) {
                    // Gunakan fallback URL statis jika konteks panel belum terpasang
                }
            }

            foreach (User::role('super_admin')->get() as $superAdmin) {
                Notification::make()
                    ->title('Backup Otomatis Terjadwal Berhasil')
                    ->body("Paket backup rutin {$result['file_name']} ({$sizeHuman}) selesai dibuat.")
                    ->success()
                    ->actions([
                        Action::make('open_backup_page')
                            ->label('Buka Backup Sistem')
                            ->url($pageUrl),
                    ])
                    ->sendToDatabase($superAdmin);
            }

            $this->info("Backup terjadwal selesai: {$result['file_name']} ({$sizeHuman}).");

            return self::SUCCESS;
        } catch (Throwable $e) {
            Log::error('Eksekusi system:run-scheduled-backup gagal.', [
                'error' => $e->getMessage(),
            ]);

            foreach (User::role('super_admin')->get() as $superAdmin) {
                Notification::make()
                    ->title('Backup Otomatis Terjadwal Gagal')
                    ->body('Sistem gagal menjalankan jadwal backup otomatis: '.$e->getMessage())
                    ->danger()
                    ->sendToDatabase($superAdmin);
            }

            $this->error('Backup terjadwal gagal: '.$e->getMessage());

            return self::FAILURE;
        }
    }
}
