<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Document;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentStreamController extends Controller
{
    /**
     * Menyajikan pratinjau inline berkas fisik dokumen privat secara aman ke browser.
     */
    public function stream(Request $request, Document $document): StreamedResponse
    {
        Gate::authorize('view', $document);

        $file = $document->primaryFile ?? $document->files()->where('is_active', true)->first();

        if (! $file) {
            abort(404, 'Dokumen ini belum memiliki berkas fisik yang diunggah.');
        }

        $disk = $file->disk?->value ?? 'private';
        $path = $file->getEffectiveFilePath();
        $downloadName = $file->getEffectiveDownloadFileName();

        if (! Storage::disk($disk)->exists($path)) {
            abort(404, 'Berkas fisik dokumen tidak ditemukan pada sistem penyimpanan.');
        }

        $document->increment('views_count');

        return Storage::disk($disk)->response(
            $path,
            $downloadName,
            [
                'X-Content-Type-Options' => 'nosniff',
                'Cache-Control' => 'private, no-cache, must-revalidate',
            ],
            'inline'
        );
    }

    /**
     * Mengunduh berkas fisik dokumen privat setelah memvalidasi izin unduh.
     */
    public function download(Request $request, Document $document): StreamedResponse
    {
        Gate::authorize('downloadPrivate', $document);

        $file = $document->primaryFile ?? $document->files()->where('is_active', true)->first();

        if (! $file) {
            abort(404, 'Dokumen ini belum memiliki berkas fisik yang diunggah.');
        }

        $disk = $file->disk?->value ?? 'private';
        $path = $file->getEffectiveFilePath();
        $downloadName = $file->getEffectiveDownloadFileName();

        if (! Storage::disk($disk)->exists($path)) {
            abort(404, 'Berkas fisik dokumen tidak ditemukan pada sistem penyimpanan.');
        }

        $document->increment('downloads_count');

        if (auth()->check()) {
            activity('document_audit')
                ->performedOn($document)
                ->causedBy(auth()->user())
                ->event('downloaded')
                ->withProperties([
                    'file_name' => $downloadName,
                    'disk' => $disk,
                ])
                ->log("Mengunduh berkas fisik: {$downloadName}");
        }

        return Storage::disk($disk)->download(
            $path,
            $downloadName,
            [
                'Cache-Control' => 'private, no-cache, must-revalidate',
            ]
        );
    }
}
