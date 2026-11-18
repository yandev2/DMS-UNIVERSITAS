<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\BulkExport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BulkExportDownloadController extends Controller
{
    /**
     * Mengunduh berkas paket ZIP ekspor massal yang terproteksi khusus Super Admin.
     */
    public function download(Request $request, string $uuid): StreamedResponse
    {
        $user = $request->user();

        if (! $user) {
            abort(403, 'Silakan masuk terlebih dahulu untuk mengunduh berkas ekspor.');
        }

        if (! Str::isUuid($uuid)) {
            abort(404);
        }

        /** @var BulkExport $export */
        $export = BulkExport::where('uuid', $uuid)->firstOrFail();

        $isAuthorized = match ($export->module_type) {
            'borang_excel' => ((int) $export->user_id === (int) $user->id || $user->hasRole('super_admin'))
                && ($user->can('borang.export_excel') || $user->hasRole('super_admin')),
            'borang_zip' => ((int) $export->user_id === (int) $user->id || $user->hasRole('super_admin'))
                && ($user->can('borang.download_bulk_zip') || $user->hasRole('super_admin')),
            'posts_excel' => ((int) $export->user_id === (int) $user->id || $user->hasRole('super_admin'))
                && ($user->can('post.moderate_humas') || $user->can('view_any:post') || $user->hasRole('super_admin')),
            default => $user->hasRole('super_admin') && $user->can('archive.bulk_export_zip'),
        };

        if (! $isAuthorized) {
            abort(403, 'Anda tidak memiliki hak akses untuk mengunduh berkas ekspor ini.');
        }

        if ($export->isExpired()) {
            abort(410, 'Tautan unduhan berkas ekspor telah kedaluwarsa (melebihi batas retensi 24 jam). Silakan lakukan ekspor ulang.');
        }

        if (! $export->isReady()) {
            abort(404, 'Berkas ekspor belum siap diunduh atau mengalami kegagalan pada proses antrean.');
        }

        $disk = Storage::disk($export->disk ?: 'private');

        if (! $disk->exists((string) $export->zip_file_path)) {
            abort(404, 'Berkas fisik hasil ekspor tidak ditemukan pada penyimpanan server.');
        }

        // Catat pengunduhan berkas ke log audit sistem
        $auditChannel = match ($export->module_type) {
            'borang_excel', 'borang_zip' => 'borang_export',
            'posts_excel' => 'cms_export',
            default => 'bulk_zip_export',
        };
        activity($auditChannel)
            ->causedBy($user)
            ->performedOn($export)
            ->event('downloaded')
            ->withProperties([
                'module' => $export->module_type,
                'file_name' => $export->zip_file_name,
                'file_size_bytes' => $export->file_size_bytes,
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ])
            ->log("User mengunduh berkas ekspor: {$export->zip_file_name}");

        $export->update(['downloaded_at' => now()]);

        return $disk->download(
            (string) $export->zip_file_path,
            $export->zip_file_name,
            [
                'Cache-Control' => 'private, no-cache, no-store, must-revalidate',
                'Pragma' => 'no-cache',
            ]
        );
    }
}
