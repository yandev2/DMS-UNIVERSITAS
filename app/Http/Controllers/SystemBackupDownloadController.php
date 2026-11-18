<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\SystemBackupService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class SystemBackupDownloadController extends Controller
{
    /**
     * Mengunduh paket backup sistem (.zip) yang terproteksi khusus Super Admin.
     */
    public function download(Request $request, string $filename, SystemBackupService $backupService): BinaryFileResponse
    {
        $user = $request->user();

        if (! $user || ! $user->hasRole('super_admin') || ! $user->can('system.backup_manage')) {
            abort(403, 'Akses unduh paket backup sistem dibatasi hanya untuk Super Admin.');
        }

        $absolutePath = $backupService->resolveBackupFileOnDisk($filename);

        if ($absolutePath === null || ! is_file($absolutePath)) {
            abort(404, 'Berkas paket backup tidak ditemukan pada penyimpanan server.');
        }

        $safeFileName = basename($absolutePath);
        $sizeBytes = (int) filesize($absolutePath);

        activity('system_backup')
            ->causedBy($user)
            ->event('backup_downloaded')
            ->withProperties([
                'file_name' => $safeFileName,
                'size_bytes' => $sizeBytes,
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ])
            ->log("Super Admin mengunduh paket backup sistem: {$safeFileName}");

        return response()->download(
            $absolutePath,
            $safeFileName,
            [
                'Cache-Control' => 'private, no-cache, no-store, must-revalidate',
                'Pragma' => 'no-cache',
            ]
        );
    }
}
