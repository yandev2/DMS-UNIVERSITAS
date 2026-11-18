<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Filament\Pages\SystemBackup;
use App\Models\User;
use App\Services\SystemBackupService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Throwable;

class RunSystemBackupJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Jumlah percobaan maksimal agar proses backup berat tidak berulang ganda saat gagal.
     */
    public int $tries = 1;

    /**
     * Batas waktu eksekusi job backup (detik).
     */
    public int $timeout = 3600;

    public function __construct(
        public ?int $actorId = null,
        public string $trigger = 'manual',
        public bool $runCleanup = false,
        public string $scope = SystemBackupService::SCOPE_DATABASE_ONLY,
    ) {}

    public function handle(SystemBackupService $backupService): void
    {
        $actor = $this->actorId ? User::find($this->actorId) : null;
        $recipients = $this->resolveRecipients($actor);
        $pageUrl = $this->resolvePageUrl();

        try {
            $result = $backupService->runBackup($this->scope, $actor, $this->trigger);

            if ($this->runCleanup) {
                $backupService->runCleanup($actor);
            }

            if ($this->trigger === 'scheduled') {
                $backupService->markScheduledRun(now('Asia/Jakarta'));
            }

            $sizeHuman = $backupService->formatBytes($result['size_bytes']);
            $labelTrigger = $this->trigger === 'scheduled' ? 'Terjadwal Otomatis' : 'Manual Latar Belakang';
            $scopeLabel = $this->scope === SystemBackupService::SCOPE_DATABASE_ONLY ? 'Database SQL Saja' : 'Lengkap DB + Storage';

            foreach ($recipients as $recipient) {
                Notification::make()
                    ->title("Backup Sistem Selesai ({$labelTrigger})")
                    ->body("Paket backup {$scopeLabel} ({$result['file_name']}, {$sizeHuman}) berhasil dibuat dan siap diunduh atau direstore.")
                    ->success()
                    ->actions([
                        Action::make('open_backup_page')
                            ->label('Lihat Paket Backup')
                            ->button()
                            ->url($pageUrl),
                    ])
                    ->sendToDatabase($recipient);
            }
        } catch (Throwable $e) {
            Log::error('RunSystemBackupJob gagal dieksekusi.', [
                'actor_id' => $this->actorId,
                'trigger' => $this->trigger,
                'error' => $e->getMessage(),
            ]);

            foreach ($recipients as $recipient) {
                Notification::make()
                    ->title('Backup Sistem Gagal')
                    ->body('Proses pembuatan paket backup sistem gagal: '.$e->getMessage())
                    ->danger()
                    ->actions([
                        Action::make('open_backup_page')
                            ->label('Periksa Halaman Backup')
                            ->url($pageUrl),
                    ])
                    ->sendToDatabase($recipient);
            }

            throw $e;
        }
    }

    /**
     * @return Collection<int, User>
     */
    protected function resolveRecipients(?User $actor): Collection
    {
        if ($actor !== null) {
            return collect([$actor]);
        }

        return User::role('super_admin')->get();
    }

    protected function resolvePageUrl(): string
    {
        try {
            return SystemBackup::getUrl(panel: 'admin');
        } catch (Throwable) {
            return url('/admin/system-backup');
        }
    }
}
