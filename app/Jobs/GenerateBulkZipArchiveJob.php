<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\BulkExport;
use App\Services\BulkExportZipService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class GenerateBulkZipArchiveJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Jumlah percobaan maksimal jika terjadi kegagalan sistem antrean.
     */
    public int $tries = 2;

    /**
     * Waktu timeout eksekusi pembuatan ZIP (detik).
     */
    public int $timeout = 300;

    /**
     * Buat instance job ekspor arsip ZIP massal.
     */
    public function __construct(
        public BulkExport $bulkExport
    ) {}

    /**
     * Eksekusi pemrosesan pembuatan arsip ZIP di latar belakang.
     */
    public function handle(BulkExportZipService $service): void
    {
        $service->process($this->bulkExport);
    }
}
