<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\BulkExport;
use App\Services\OutgoingLetterQueuedExportService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class GenerateOutgoingLetterExportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Jumlah percobaan maksimal jika terjadi kegagalan antrean.
     */
    public int $tries = 2;

    /**
     * Timeout eksekusi ekspor Surat Keluar di latar belakang (detik).
     */
    public int $timeout = 600;

    public function __construct(
        public BulkExport $bulkExport
    ) {}

    public function handle(OutgoingLetterQueuedExportService $service): void
    {
        $service->process($this->bulkExport);
    }
}
