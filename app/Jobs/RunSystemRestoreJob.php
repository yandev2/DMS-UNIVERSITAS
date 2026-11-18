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
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class RunSystemRestoreJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Restore sistem hanya boleh dijalankan 1 kali tanpa pengulangan otomatis bila gagal.
     */
    public int $tries = 1;

    /**
     * Timeout job restore latar belakang (1 jam).
     */
    public int $timeout = 3600;

    public function __construct(
        public int $actorId,
        public string $sourceType,
        public string $target,
        public string $mode,
        public string $storageStrategy = 'merge',
        public bool $createSafetyBackup = true,
    ) {}

    public function handle(SystemBackupService $backupService): void
    {
        $actor = User::find($this->actorId);
        if (! $actor) {
            throw new RuntimeException('Akun Super Admin pemicu restore tidak ditemukan.');
        }

        $pageUrl = $this->resolvePageUrl();

        try {
            if ($this->sourceType === 'disk') {
                $result = $backupService->restoreFromDisk(
                    filename: $this->target,
                    mode: $this->mode,
                    storageStrategy: $this->storageStrategy,
                    createSafetyBackup: $this->createSafetyBackup,
                    actor: $actor,
                );
            } else {
                $localDisk = Storage::disk('local');
                if ($this->target === '' || ! $localDisk->exists($this->target)) {
                    throw new RuntimeException('Berkas unggahan arsip restore tidak ditemukan di penyimpanan sementara.');
                }

                $result = $backupService->restoreFromAbsolutePath(
                    zipAbsolutePath: $localDisk->path($this->target),
                    mode: $this->mode,
                    storageStrategy: $this->storageStrategy,
                    createSafetyBackup: $this->createSafetyBackup,
                    actor: $actor,
                    sourceLabel: 'uploaded:'.basename($this->target),
                );
            }

            // Ambil ulang daftar penerima setelah restore agar tetap valid pasca-impor SQL
            $recipients = $this->resolvePostRestoreRecipients();

            foreach ($recipients as $recipient) {
                Notification::make()
                    ->title('Restore Sistem Selesai')
                    ->body(sprintf(
                        'Pemulihan dari %s selesai (DB: %s, Berkas Storage: %d berkas).',
                        $result['archive_name'],
                        $result['restored_database'] ? 'Ya' : 'Tidak',
                        $result['restored_storage_files'],
                    ))
                    ->success()
                    ->actions([
                        Action::make('open_backup_page')
                            ->label('Lihat Halaman Backup')
                            ->button()
                            ->url($pageUrl),
                    ])
                    ->sendToDatabase($recipient);
            }
        } catch (Throwable $e) {
            Log::error('RunSystemRestoreJob gagal dieksekusi.', [
                'actor_id' => $this->actorId,
                'source_type' => $this->sourceType,
                'target' => $this->target,
                'mode' => $this->mode,
                'error' => $e->getMessage(),
            ]);

            foreach ($this->resolvePostRestoreRecipients() as $recipient) {
                Notification::make()
                    ->title('Restore Sistem Gagal')
                    ->body('Proses pemulihan sistem gagal dijalankan: '.$e->getMessage())
                    ->danger()
                    ->actions([
                        Action::make('open_backup_page')
                            ->label('Periksa Halaman Backup')
                            ->url($pageUrl),
                    ])
                    ->sendToDatabase($recipient);
            }

            throw $e;
        } finally {
            if ($this->sourceType === 'uploaded_path' && $this->target !== '') {
                Storage::disk('local')->delete($this->target);
            }
        }
    }

    /**
     * @return Collection<int, User>
     */
    protected function resolvePostRestoreRecipients(): Collection
    {
        $actor = User::find($this->actorId);
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
