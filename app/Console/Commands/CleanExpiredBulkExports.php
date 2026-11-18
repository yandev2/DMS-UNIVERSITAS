<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\BulkExport;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class CleanExpiredBulkExports extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'bulk-exports:clean-expired';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Membersihkan berkas ZIP ekspor massal sementara yang telah melewati batas retensi 24 jam.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Memulai pembersihan berkas ZIP ekspor massal kedaluwarsa...');

        $expiredExports = BulkExport::where('expires_at', '<', now())
            ->whereNotNull('zip_file_path')
            ->get();

        $deletedCount = 0;

        foreach ($expiredExports as $export) {
            $disk = Storage::disk($export->disk ?: 'private');

            if (filled($export->zip_file_path) && $disk->exists($export->zip_file_path)) {
                $disk->delete($export->zip_file_path);
                $deletedCount++;
            }

            $export->update([
                'zip_file_path' => null,
            ]);
        }

        // Sapu bersih seluruh berkas orphan di direktori temp-exports yang lebih lama dari 24 jam
        $disk = Storage::disk('private');
        if ($disk->exists('temp-exports')) {
            $allFiles = $disk->files('temp-exports');
            $cutoff = now()->subHours(24)->timestamp;

            foreach ($allFiles as $file) {
                if (str_ends_with($file, '.zip') || str_ends_with($file, '.xlsx')) {
                    $mtime = $disk->lastModified($file);
                    if ($mtime < $cutoff) {
                        $disk->delete($file);
                        $deletedCount++;
                    }
                }
            }
        }

        $message = "Pembersihan selesai: {$deletedCount} berkas ZIP kedaluwarsa berhasil dihapus dari penyimpanan.";
        $this->info($message);
        Log::info("Command CleanExpiredBulkExports: {$message}");

        return self::SUCCESS;
    }
}
