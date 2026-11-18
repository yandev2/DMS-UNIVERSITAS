<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\DocumentReminderService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class SendDocumentExpiryReminders extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'documents:send-expiry-reminders
                            {--date= : Simulasi tanggal evaluasi pengingat (format: YYYY-MM-DD)}
                            {--dry-run : Menjalankan pengecekan tanpa mengirim notifikasi atau mengubah status}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Memindai dokumen mendekati kedaluwarsa dan mengirimkan notifikasi pengingat resmi ke penerima';

    /**
     * Execute the console command.
     */
    public function handle(DocumentReminderService $service): int
    {
        $dateOption = $this->option('date');
        $dryRun = (bool) $this->option('dry-run');

        $evaluationDate = now();

        if (filled($dateOption)) {
            try {
                $evaluationDate = Carbon::createFromFormat('Y-m-d', (string) $dateOption)->startOfDay();
            } catch (\Throwable) {
                $this->error("Format parameter --date tidak valid: '{$dateOption}'. Gunakan format YYYY-MM-DD.");

                return self::FAILURE;
            }
        }

        $modeLabel = $dryRun ? '<fg=yellow>[DRY-RUN / SIMULASI]</>' : '<fg=green>[LIVE / EKSEKUSI]</>';
        $this->info("Menjalankan Evaluasi Pengingat Masa Berlaku Dokumen {$modeLabel}");
        $this->line("Tanggal Evaluasi: <fg=cyan>{$evaluationDate->format('d F Y')}</>");

        $result = $service->run($evaluationDate, $dryRun);

        $this->newLine();
        $this->table(
            ['Metrik', 'Jumlah'],
            [
                ['Dokumen Aktif Diperiksa', $result->scannedCount],
                ['Notifikasi Dikirim'.($dryRun ? ' (Simulasi)' : ''), $result->sentCount],
                ['Penerima Dilewati (Belum Jatuh Tempo / Sudah Terkirim)', $result->skippedCount],
                ['Pengiriman Gagal', $result->failedCount],
                ['Dokumen Kedaluwarsa Baru Dialihkan (Expired)', $result->expiredMarkedCount],
            ]
        );

        if (! empty($result->details)) {
            $this->newLine();
            $this->info('Rincian Transaksi Pengingat:');

            $rows = array_map(function (array $item): array {
                $statusColor = match ($item['status']) {
                    'sent' => '<fg=green>TERKIRIM</>',
                    'would_send' => '<fg=yellow>SIMULASI</>',
                    'failed' => '<fg=red>GAGAL</>',
                    default => $item['status'],
                };

                return [
                    $item['document_id'],
                    $item['document_number'] ?: '-',
                    $item['recipient_email'],
                    strtoupper($item['interval']),
                    "{$item['days_remaining']} Hari",
                    $statusColor,
                ];
            }, $result->details);

            $this->table(
                ['ID', 'Nomor Dokumen', 'Email Penerima', 'Interval', 'Sisa Waktu', 'Status'],
                $rows
            );
        }

        if (! empty($result->errors)) {
            $this->newLine();
            $this->error('Daftar Kesalahan Pengiriman:');
            foreach ($result->errors as $err) {
                $this->line(" - {$err}");
            }
        }

        $this->newLine();
        $this->info('Pemeriksaan pengingat kedaluwarsa dokumen selesai dilaksanakan.');

        return $result->failedCount > 0 ? self::FAILURE : self::SUCCESS;
    }
}
