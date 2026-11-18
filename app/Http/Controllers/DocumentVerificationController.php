<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\DocumentStatus;
use App\Enums\VisibilityLevel;
use App\Models\Document;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class DocumentVerificationController extends Controller
{
    /**
     * Tampilkan halaman portal verifikasi publik keabsahan naskah dinas via QR-Code.
     */
    public function show(Request $request, string $uuid): Response
    {
        $document = Document::query()
            ->where('uuid', $uuid)
            ->with(['unit', 'category', 'academicYear', 'primaryFile', 'finalApprover'])
            ->first();

        if (! $document) {
            return response()->view('public.verify-invalid', [
                'uuid' => $uuid,
            ], 404);
        }

        $isExpired = ($document->status === DocumentStatus::Expired)
            || ($document->has_expiry && $document->expired_date !== null && $document->expired_date->isPast());

        $isApproved = in_array($document->status, [DocumentStatus::Approved, DocumentStatus::Archived], true);

        $isDraftOrPending = in_array($document->status, [
            DocumentStatus::Draft,
            DocumentStatus::Pending,
            DocumentStatus::Revision,
            DocumentStatus::Rejected,
        ], true);

        $verificationStatus = match (true) {
            $isDraftOrPending => 'unapproved',
            $isExpired => 'expired',
            $isApproved => 'valid',
            default => 'unapproved',
        };

        if ($isApproved) {
            activity('document_audit')
                ->performedOn($document)
                ->event('verified')
                ->withProperties([
                    'ip' => $request->ip(),
                    'user_agent' => (string) $request->userAgent(),
                    'document_number' => $document->document_number,
                    'status' => $document->status->value,
                ])
                ->log("Verifikasi publik naskah dinas {$document->document_number} dipindai");

            $document->increment('views_count');
        }

        $primaryFile = $document->primaryFile;
        $isPublicVisible = ($document->visibility === VisibilityLevel::Public);
        $canStreamOrDownload = $isApproved && $isPublicVisible && $primaryFile !== null;

        return response()->view('public.document-verification', [
            'document' => $document,
            'verificationStatus' => $verificationStatus,
            'isExpired' => $isExpired,
            'isApproved' => $isApproved,
            'isDraftOrPending' => $isDraftOrPending,
            'canStreamOrDownload' => $canStreamOrDownload,
            'primaryFile' => $primaryFile,
        ]);
    }

    /**
     * Alirkan (stream) berkas PDF inline untuk pratinjau publik (hanya untuk dokumen bertaraf publik).
     */
    public function streamFile(Request $request, string $uuid): Response|BinaryFileResponse
    {
        $document = Document::query()
            ->where('uuid', $uuid)
            ->with(['primaryFile'])
            ->firstOrFail();

        if (! in_array($document->status, [DocumentStatus::Approved, DocumentStatus::Archived], true)) {
            abort(403, 'Akses berkas ditolak: Naskah dinas belum disahkan secara resmi.');
        }

        if ($document->visibility !== VisibilityLevel::Public) {
            abort(403, 'Akses berkas dibatasi oleh kebijakan privasi dan kerahasiaan institusi.');
        }

        $fileRecord = $document->primaryFile;
        if (! $fileRecord) {
            abort(404, 'Berkas fisik naskah dinas tidak ditemukan.');
        }

        $relativeFilePath = $fileRecord->stamped_file_path ?? $fileRecord->original_file_path;
        $diskName = is_string($fileRecord->disk) ? $fileRecord->disk : ($fileRecord->disk?->value ?? 'private');
        $disk = Storage::disk($diskName);

        if (! $relativeFilePath || ! $disk->exists($relativeFilePath)) {
            abort(404, 'Berkas fisik naskah dinas tidak ditemukan pada media penyimpanan.');
        }

        $absolutePath = $disk->path($relativeFilePath);
        $mimeType = mime_content_type($absolutePath) ?: 'application/pdf';

        return response()->file($absolutePath, [
            'Content-Type' => $mimeType,
            'Content-Disposition' => 'inline; filename="'.basename($absolutePath).'"',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }

    /**
     * Unduh berkas resmi PDF naskah dinas (hanya untuk dokumen bertaraf publik).
     */
    public function downloadFile(Request $request, string $uuid): BinaryFileResponse|Response
    {
        $document = Document::query()
            ->where('uuid', $uuid)
            ->with(['primaryFile'])
            ->firstOrFail();

        if (! in_array($document->status, [DocumentStatus::Approved, DocumentStatus::Archived], true)) {
            abort(403, 'Akses berkas ditolak: Naskah dinas belum disahkan secara resmi.');
        }

        if ($document->visibility !== VisibilityLevel::Public) {
            abort(403, 'Akses unduhan dibatasi oleh kebijakan privasi dan kerahasiaan institusi.');
        }

        $fileRecord = $document->primaryFile;
        if (! $fileRecord) {
            abort(404, 'Berkas fisik naskah dinas tidak ditemukan.');
        }

        $relativeFilePath = $fileRecord->stamped_file_path ?? $fileRecord->original_file_path;
        $diskName = is_string($fileRecord->disk) ? $fileRecord->disk : ($fileRecord->disk?->value ?? 'private');
        $disk = Storage::disk($diskName);

        if (! $relativeFilePath || ! $disk->exists($relativeFilePath)) {
            abort(404, 'Berkas fisik naskah dinas tidak ditemukan pada media penyimpanan.');
        }

        $absolutePath = $disk->path($relativeFilePath);
        $document->increment('downloads_count');

        $extension = strtolower((string) pathinfo($relativeFilePath, PATHINFO_EXTENSION)) ?: 'pdf';
        $downloadFilename = str_replace(['/', '\\'], '-', $document->document_number ?? 'DOK').".{$extension}";

        return response()->download($absolutePath, $downloadFilename, [
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
        ]);
    }
}
