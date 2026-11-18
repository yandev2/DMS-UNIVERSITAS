<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Document;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;
use ZipArchive;
use ZipStream\CompressionMethod;
use ZipStream\ZipStream;

class BorangZipExportService
{
    /**
     * Download bundled borang documents from an in-memory collection as a streamed ZIP archive.
     *
     * @param  Collection<int, Document>  $documents
     */
    public function downloadZipResponse(
        Collection $documents,
        string $filename = 'Paket-Dokumen-Bukti-Borang.zip'
    ): StreamedResponse {
        return response()->streamDownload(
            function () use ($documents): void {
                @ini_set('memory_limit', '512M');
                @set_time_limit(0);

                $this->streamZipArchive(function (ZipStream $zip, string &$catalog, int &$counter, array &$addedPaths) use ($documents): void {
                    $this->appendDocumentsToTarget(
                        $documents,
                        fn (string $entryPath, string $absPath) => $zip->addFileFromPath($entryPath, $absPath),
                        $catalog,
                        $counter,
                        $addedPaths
                    );
                }, $documents->count());
            },
            $filename,
            [
                'Content-Type' => 'application/zip',
                'Cache-Control' => 'no-cache, no-store, must-revalidate',
            ]
        );
    }

    /**
     * Download bundled borang documents from an Eloquent query using chunked streaming
     * to keep memory usage bounded even for > 5,000 exported documents.
     */
    public function downloadZipFromQuery(
        Builder $query,
        string $filename = 'Paket-Dokumen-Bukti-Borang.zip'
    ): StreamedResponse {
        return response()->streamDownload(
            function () use ($query): void {
                @ini_set('memory_limit', '512M');
                @set_time_limit(0);

                $totalDocuments = (clone $query)->count();

                $this->streamZipArchive(function (ZipStream $zip, string &$catalog, int &$counter, array &$addedPaths) use ($query): void {
                    (clone $query)
                        ->select(['documents.id', 'documents.uuid', 'documents.title', 'documents.document_number'])
                        ->with(['accreditationCriteria:id,code,name', 'primaryFile'])
                        ->chunkById(200, function (Collection $chunk) use ($zip, &$catalog, &$counter, &$addedPaths): void {
                            $this->appendDocumentsToTarget(
                                $chunk,
                                fn (string $entryPath, string $absPath) => $zip->addFileFromPath($entryPath, $absPath),
                                $catalog,
                                $counter,
                                $addedPaths
                            );
                        }, 'documents.id', 'id');
                }, $totalDocuments);
            },
            $filename,
            [
                'Content-Type' => 'application/zip',
                'Cache-Control' => 'no-cache, no-store, must-revalidate',
            ]
        );
    }

    /**
     * Write a chunked ZIP archive directly to disk (used by background queue jobs).
     */
    public function writeZipFromQueryToPath(Builder $query, string $absoluteZipPath): int
    {
        @ini_set('memory_limit', '512M');
        @set_time_limit(0);

        $totalDocuments = (clone $query)->count();

        $zip = new ZipArchive;
        $opened = $zip->open($absoluteZipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        if ($opened !== true) {
            throw new \RuntimeException("Gagal membuat arsip ZIP borang pada disk. Kode: {$opened}");
        }

        $catalog = $this->buildCatalogHeader($totalDocuments);
        $counter = 1;
        $addedPaths = [];
        $packedDocuments = 0;

        (clone $query)
            ->select(['documents.id', 'documents.uuid', 'documents.title', 'documents.document_number'])
            ->with(['accreditationCriteria:id,code,name', 'primaryFile'])
            ->chunkById(200, function (Collection $chunk) use ($zip, &$catalog, &$counter, &$addedPaths, &$packedDocuments): void {
                $packedDocuments += $this->appendDocumentsToTarget(
                    $chunk,
                    fn (string $entryPath, string $absPath) => $zip->addFile($absPath, $entryPath),
                    $catalog,
                    $counter,
                    $addedPaths
                );
            }, 'documents.id', 'id');

        $zip->addFromString('KATALOG-BUKTI-BORANG.txt', $catalog);
        $zip->close();

        return $packedDocuments;
    }

    /**
     * @param  callable(ZipStream, string&, int&, array<string, int>&): void  $writerCallback
     */
    private function streamZipArchive(callable $writerCallback, int $totalDocuments): void
    {
        $outputStream = fopen('php://output', 'wb');

        $zip = new ZipStream(
            outputStream: $outputStream,
            defaultCompressionMethod: CompressionMethod::DEFLATE,
            sendHttpHeaders: false
        );

        $catalog = $this->buildCatalogHeader($totalDocuments);
        $counter = 1;
        $addedPaths = [];

        $writerCallback($zip, $catalog, $counter, $addedPaths);

        $zip->addFile('KATALOG-BUKTI-BORANG.txt', $catalog);
        $zip->finish();

        if (is_resource($outputStream)) {
            fclose($outputStream);
        }
    }

    private function buildCatalogHeader(int $totalDocuments): string
    {
        $catalog = "=================================================================\n";
        $catalog .= "DAFTAR REKAPITULASI DOKUMEN BUKTI FISIK BORANG AKREDITASI\n";
        $catalog .= "UNIVERSITAS BINA INSAN (UNIVBI)\n";
        $catalog .= 'Waktu Ekspor: '.now()->translatedFormat('d F Y, H:i:s')." WIB\n";
        $catalog .= 'Total Dokumen: '.$totalDocuments." Berkas\n";
        $catalog .= "=================================================================\n\n";

        return $catalog;
    }

    /**
     * @param  iterable<int, Document>  $documents
     * @param  callable(string, string): mixed  $addFileCallback
     * @param  array<string, int>  $addedPaths
     */
    private function appendDocumentsToTarget(
        iterable $documents,
        callable $addFileCallback,
        string &$catalog,
        int &$counter,
        array &$addedPaths
    ): int {
        $packedDocuments = 0;

        foreach ($documents as $document) {
            $fileRecord = $document->primaryFile;
            if (! $fileRecord) {
                continue;
            }

            $relativeFilePath = $fileRecord->stamped_file_path ?? $fileRecord->original_file_path;
            if (! $relativeFilePath) {
                continue;
            }

            $disk = Storage::disk($fileRecord->disk->value);
            if (! $disk->exists($relativeFilePath)) {
                continue;
            }

            $absoluteFilePath = $disk->path($relativeFilePath);
            if (! file_exists($absoluteFilePath)) {
                continue;
            }

            $criteria = $document->accreditationCriteria;
            if ($criteria->isEmpty()) {
                $criteria = collect([null]);
            }

            $safeTitle = Str::limit(Str::slug($document->title), 60, '');
            $cleanDocNumber = str_replace(['/', '\\'], '-', $document->document_number ?? 'DOK-'.substr($document->uuid, 0, 8));
            $extension = pathinfo($absoluteFilePath, PATHINFO_EXTENSION) ?: 'pdf';
            $entryFileName = "{$cleanDocNumber}_{$safeTitle}.{$extension}";

            $hasPackedThisDoc = false;

            foreach ($criteria as $criterion) {
                $folderName = $criterion
                    ? "{$criterion->code} - ".Str::slug($criterion->name)
                    : 'Umum';

                $baseEntryPath = "{$folderName}/{$entryFileName}";
                $seenCount = $addedPaths[$baseEntryPath] ?? 0;
                $zipEntryPath = $seenCount > 0
                    ? "{$folderName}/".($seenCount + 1)."_{$entryFileName}"
                    : $baseEntryPath;
                $addedPaths[$baseEntryPath] = $seenCount + 1;

                $addFileCallback($zipEntryPath, $absoluteFilePath);
                $hasPackedThisDoc = true;

                $indicator = $criterion?->pivot?->indicator_code ? " [{$criterion->pivot->indicator_code}]" : '';
                $catalog .= "{$counter}. [{$folderName}]{$indicator} {$document->document_number} - {$document->title}\n";
                $catalog .= '   Tautan Asesor: '.$document->getAssessorUrl()."\n\n";
                $counter++;
            }

            if ($hasPackedThisDoc) {
                $packedDocuments++;
            }
        }

        return $packedDocuments;
    }
}
