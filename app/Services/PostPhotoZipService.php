<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Post;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;
use ZipStream\CompressionMethod;
use ZipStream\ZipStream;

class PostPhotoZipService
{
    /**
     * Unduh seluruh foto dokumentasi kegiatan dalam arsip ZIP secara streaming.
     */
    public function downloadPostPhotosZip(Post $post): StreamedResponse
    {
        $filename = 'Dokumentasi-'.($post->slug ?: 'Kegiatan-UNIVBI').'.zip';

        return response()->streamDownload(
            function () use ($post): void {
                @ini_set('memory_limit', '512M');
                $outputStream = fopen('php://output', 'wb');

                $zip = new ZipStream(
                    outputStream: $outputStream,
                    defaultCompressionMethod: CompressionMethod::DEFLATE,
                    sendHttpHeaders: false
                );

                $manifest = "=================================================================\n";
                $manifest .= "DOKUMENTASI FOTO KEGIATAN UNIVERSITAS BINA INSAN (UNIVBI)\n";
                $manifest .= "=================================================================\n";
                $manifest .= "Judul Kegiatan : {$post->title}\n";
                $manifest .= 'Kategori       : '.($post->category?->name ?? '-')."\n";
                $manifest .= 'Unit Pelaksana : '.($post->unit?->name ?? '-')."\n";
                $manifest .= 'Tanggal Acara  : '.($post->event_date ? $post->event_date->translatedFormat('d F Y') : '-')."\n";
                $manifest .= 'Lokasi         : '.($post->event_location ?: '-')."\n";
                $manifest .= 'Total Foto     : '.$post->photos()->count()." Foto\n";
                $manifest .= 'Diunduh Pada   : '.now()->translatedFormat('d F Y, H:i:s')." WIB\n";
                $manifest .= 'Tautan Resmi   : '.$post->getPublicUrl()."\n";
                $manifest .= "=================================================================\n\n";
                $manifest .= "DAFTAR BERKAS FOTO DOKUMENTASI:\n\n";

                $photos = $post->photos()->orderBy('order_index')->get();
                $counter = 1;

                foreach ($photos as $photo) {
                    $diskName = $photo->disk?->value ?? (is_string($photo->disk) ? $photo->disk : 'public');
                    $disk = Storage::disk($diskName);

                    if (! $disk->exists($photo->file_path)) {
                        continue;
                    }

                    $ext = $photo->file_extension ?: pathinfo($photo->file_path, PATHINFO_EXTENSION) ?: 'jpg';
                    $entryName = sprintf(
                        '%02d-%s.%s',
                        $counter,
                        $photo->caption ? Str::slug($photo->caption) : 'foto-kegiatan',
                        $ext
                    );

                    $added = false;
                    try {
                        $stream = $disk->readStream($photo->file_path);
                        if ($stream !== false && is_resource($stream)) {
                            $zip->addFileFromStream($entryName, $stream);
                            fclose($stream);
                            $added = true;
                        }
                    } catch (\Throwable) {
                        $added = false;
                    }

                    if (! $added) {
                        try {
                            $absolutePath = $disk->path($photo->file_path);
                            if (file_exists($absolutePath)) {
                                $zip->addFileFromPath($entryName, $absolutePath);
                                $added = true;
                            }
                        } catch (\Throwable) {
                            $added = false;
                        }
                    }

                    if ($added) {
                        $photo->increment('downloads_count');

                        $manifest .= sprintf(
                            "%d. %s\n   Keterangan: %s\n   Ukuran File: %s KB\n\n",
                            $counter,
                            $entryName,
                            $photo->caption ?: 'Dokumentasi Acara',
                            number_format(($photo->file_size ?: 0) / 1024, 1)
                        );
                        $counter++;
                    }
                }

                $zip->addFile('DESKRIPSI-DOKUMENTASI.txt', $manifest);
                $zip->finish();

                if (is_resource($outputStream)) {
                    fclose($outputStream);
                }
            },
            $filename,
            [
                'Content-Type' => 'application/zip',
                'Cache-Control' => 'no-cache, no-store, must-revalidate',
            ]
        );
    }
}
