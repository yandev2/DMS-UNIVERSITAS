<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\IncomingLetter;
use App\Models\OutgoingLetter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LetterFileController extends Controller
{
    public function streamIncoming(Request $request, IncomingLetter $incomingLetter): StreamedResponse
    {
        if (! $incomingLetter->canUserAccess($request->user())) {
            abort(403, 'Anda belum memiliki hak akses untuk melihat berkas surat masuk ini.');
        }

        $path = $incomingLetter->getActiveFilePath();

        if (! $path || ! Storage::disk('private')->exists($path)) {
            abort(404, 'Berkas fisik surat masuk tidak ditemukan.');
        }

        $fileName = $incomingLetter->file_name ?: basename($path);

        return Storage::disk('private')->response(
            $path,
            $fileName,
            [
                'X-Content-Type-Options' => 'nosniff',
                'Cache-Control' => 'private, no-cache, must-revalidate',
            ],
            'inline'
        );
    }

    public function downloadIncoming(Request $request, IncomingLetter $incomingLetter): StreamedResponse
    {
        if (! $incomingLetter->canUserAccess($request->user())) {
            abort(403, 'Anda belum memiliki hak akses untuk mengunduh berkas surat masuk ini.');
        }

        $path = $incomingLetter->getActiveFilePath();

        if (! $path || ! Storage::disk('private')->exists($path)) {
            abort(404, 'Berkas fisik surat masuk tidak ditemukan.');
        }

        $fileName = $incomingLetter->file_name ?: basename($path);

        return Storage::disk('private')->download(
            $path,
            $fileName,
            [
                'Cache-Control' => 'private, no-cache, must-revalidate',
            ]
        );
    }

    public function streamOutgoing(Request $request, OutgoingLetter $outgoingLetter): StreamedResponse
    {
        if (! $outgoingLetter->canUserAccess($request->user())) {
            abort(403, 'Anda tidak memiliki hak akses untuk melihat berkas surat keluar ini.');
        }

        $path = $outgoingLetter->getActiveFilePath();

        if (! $path || ! Storage::disk('private')->exists($path)) {
            abort(404, 'Berkas fisik surat keluar tidak ditemukan.');
        }

        $fileName = $outgoingLetter->file_name ?: basename($path);

        return Storage::disk('private')->response(
            $path,
            $fileName,
            [
                'X-Content-Type-Options' => 'nosniff',
                'Cache-Control' => 'private, no-cache, must-revalidate',
            ],
            'inline'
        );
    }

    public function downloadOutgoing(Request $request, OutgoingLetter $outgoingLetter): StreamedResponse
    {
        if (! $outgoingLetter->canUserAccess($request->user())) {
            abort(403, 'Anda tidak memiliki hak akses untuk mengunduh berkas surat keluar ini.');
        }

        $path = $outgoingLetter->getActiveFilePath();

        if (! $path || ! Storage::disk('private')->exists($path)) {
            abort(404, 'Berkas fisik surat keluar tidak ditemukan.');
        }

        $fileName = $outgoingLetter->file_name ?: basename($path);

        return Storage::disk('private')->download(
            $path,
            $fileName,
            [
                'Cache-Control' => 'private, no-cache, must-revalidate',
            ]
        );
    }
}
