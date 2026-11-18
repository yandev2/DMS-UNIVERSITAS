<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\BulkExport;
use App\Models\Document;
use App\Models\IncomingLetter;
use App\Models\OutgoingLetter;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;
use ZipArchive;

class BulkExportZipService
{
    /**
     * Memproses pengemasan berkas massal ke dalam arsip ZIP di latar belakang.
     */
    public function process(BulkExport $bulkExport): bool
    {
        try {
            $bulkExport->update(['status' => 'processing']);

            $disk = Storage::disk($bulkExport->disk ?: 'private');
            $disk->makeDirectory('temp-exports');

            $zipRelativePath = "temp-exports/{$bulkExport->uuid}.zip";
            $zipAbsolutePath = $disk->path($zipRelativePath);

            $zip = new ZipArchive;
            $opened = $zip->open($zipAbsolutePath, ZipArchive::CREATE | ZipArchive::OVERWRITE);

            if ($opened !== true) {
                throw new \RuntimeException("Gagal menginisialisasi berkas ZIP pada penyimpanan. Kode kesalahan: {$opened}");
            }

            $user = $bulkExport->user;
            $moduleLabel = $this->getModuleLabel($bulkExport->module_type);

            $manifest = "=================================================================\n";
            $manifest .= "REKAPITULASI ARSIP DOKUMEN & SURAT KAMPUS\n";
            $manifest .= "UNIVERSITAS BINA INSAN (UNIVBI)\n";
            $manifest .= "=================================================================\n";
            $manifest .= "Modul Sumber    : {$moduleLabel}\n";
            $manifest .= 'Waktu Ekspor    : '.now()->translatedFormat('d F Y, H:i:s')." WIB\n";
            $manifest .= 'Pemohon Ekspor  : '.($user?->name ?? 'Super Admin').' ('.($user?->email ?? '-').")\n";
            $manifest .= "Masa Berlaku    : 24 Jam sejak waktu pembuatan\n";
            $manifest .= "=================================================================\n\n";
            $manifest .= "DAFTAR BERKAS TERLAMPIR:\n\n";

            $addedFilesCount = 0;
            $usedEntryNames = [];

            if ($bulkExport->module_type === 'documents') {
                $addedFilesCount = $this->packDocuments($zip, $bulkExport->record_ids, $manifest, $usedEntryNames);
            } elseif ($bulkExport->module_type === 'incoming_letters') {
                $addedFilesCount = $this->packIncomingLetters($zip, $bulkExport->record_ids, $manifest, $usedEntryNames);
            } elseif ($bulkExport->module_type === 'outgoing_letters') {
                $addedFilesCount = $this->packOutgoingLetters($zip, $bulkExport->record_ids, $manifest, $usedEntryNames);
            }

            $manifest .= "-----------------------------------------------------------------\n";
            $manifest .= "Total Berkas Berhasil Dikemas: {$addedFilesCount} Berkas\n";
            $manifest .= "=================================================================\n";

            $zip->addFromString('DAFTAR-ARSIP-BERKAS.txt', $manifest);
            $zip->close();

            $fileSizeBytes = file_exists($zipAbsolutePath) ? (int) filesize($zipAbsolutePath) : 0;

            $bulkExport->update([
                'total_files' => $addedFilesCount,
                'zip_file_path' => $zipRelativePath,
                'file_size_bytes' => $fileSizeBytes,
                'status' => 'completed',
                'expires_at' => now()->addHours(24),
            ]);

            // Catat ke log audit aktivitas
            activity('bulk_zip_export')
                ->causedBy($user)
                ->performedOn($bulkExport)
                ->event('completed')
                ->withProperties([
                    'module' => $bulkExport->module_type,
                    'total_files' => $addedFilesCount,
                    'file_name' => $bulkExport->zip_file_name,
                    'file_size_bytes' => $fileSizeBytes,
                    'record_ids' => $bulkExport->record_ids,
                ])
                ->log("Super Admin berhasil mengekspor {$addedFilesCount} berkas {$moduleLabel} ke dalam arsip ZIP.");

            // Kirim notifikasi lonceng ke akun Super Admin
            if ($user) {
                $humanSize = $this->formatBytes($fileSizeBytes);

                Notification::make()
                    ->success()
                    ->title('Paket ZIP Siap Diunduh')
                    ->body("Paket arsip {$moduleLabel} berisi {$addedFilesCount} berkas ({$humanSize}) telah siap. Tautan unduh berlaku 24 jam.")
                    ->actions([
                        Action::make('download')
                            ->label('Unduh File ZIP')
                            ->icon('heroicon-o-arrow-down-tray')
                            ->button()
                            ->url($bulkExport->getDownloadUrl())
                            ->openUrlInNewTab(),
                    ])
                    ->sendToDatabase($user);
            }

            return true;
        } catch (Throwable $e) {
            Log::error('BulkExportZipService Error: '.$e->getMessage(), [
                'bulk_export_id' => $bulkExport->id,
                'trace' => $e->getTraceAsString(),
            ]);

            $bulkExport->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);

            if ($bulkExport->user) {
                Notification::make()
                    ->danger()
                    ->title('Pengemasan ZIP Gagal')
                    ->body('Terjadi kendala saat mengemas berkas arsip: '.$e->getMessage())
                    ->sendToDatabase($bulkExport->user);
            }

            return false;
        }
    }

    /**
     * Mengemas kumpulan Dokumen Arsip & Borang.
     *
     * @param  array<int>  $recordIds
     * @param  array<string, int>  $usedEntryNames
     */
    protected function packDocuments(ZipArchive $zip, array $recordIds, string &$manifest, array &$usedEntryNames): int
    {
        $documents = Document::with(['primaryFile', 'files', 'unit'])
            ->whereIn('id', $recordIds)
            ->get();

        $count = 0;
        $counter = 1;

        foreach ($documents as $doc) {
            $file = $doc->primaryFile ?? $doc->files()->where('is_active', true)->first();
            if (! $file) {
                continue;
            }

            $diskName = $file->disk?->value ?? 'private';
            $relativeFilePath = $file->stamped_file_path ?: $file->original_file_path;

            if (! $relativeFilePath || ! Storage::disk($diskName)->exists($relativeFilePath)) {
                continue;
            }

            $absolutePath = Storage::disk($diskName)->path($relativeFilePath);
            if (! file_exists($absolutePath)) {
                continue;
            }

            $safeNumber = str_replace(['/', '\\', ':', '*', '?', '"', '<', '>', '|'], '-', (string) ($doc->document_number ?: 'DOK-'.$doc->id));
            $safeTitle = Str::limit(Str::slug($doc->title), 40, '');
            $extension = pathinfo($absolutePath, PATHINFO_EXTENSION) ?: 'pdf';
            $entryName = "{$safeNumber}_{$safeTitle}.{$extension}";

            $entryName = $this->resolveUniqueEntryName($entryName, $usedEntryNames);

            $zip->addFile($absolutePath, $entryName);

            $unitName = $doc->unit?->name ?? '-';
            $statusLabel = $doc->status?->getLabel() ?? 'Disahkan';
            $manifest .= "{$counter}. [{$doc->document_number}] {$doc->title}\n";
            $manifest .= "   Unit: {$unitName} | Status: {$statusLabel} | File: {$entryName}\n\n";

            $count++;
            $counter++;
        }

        return $count;
    }

    /**
     * Mengemas kumpulan Surat Masuk (Eksternal).
     *
     * @param  array<int>  $recordIds
     * @param  array<string, int>  $usedEntryNames
     */
    protected function packIncomingLetters(ZipArchive $zip, array $recordIds, string &$manifest, array &$usedEntryNames): int
    {
        $letters = IncomingLetter::with(['unit'])
            ->whereIn('id', $recordIds)
            ->get();

        $count = 0;
        $counter = 1;
        $disk = Storage::disk('private');

        foreach ($letters as $letter) {
            $relativeFilePath = $letter->getActiveFilePath();
            if (! $relativeFilePath || ! $disk->exists($relativeFilePath)) {
                continue;
            }

            $absolutePath = $disk->path($relativeFilePath);
            if (! file_exists($absolutePath)) {
                continue;
            }

            $safeAgenda = str_replace(['/', '\\', ':', '*', '?', '"', '<', '>', '|'], '-', (string) ($letter->agenda_number ?: 'SM-'.$letter->id));
            $sender = $letter->sender_name ?: 'Pengirim';
            $safeSender = Str::limit(Str::slug($sender), 30, '');
            $safeSubject = Str::limit(Str::slug($letter->subject), 40, '');
            $extension = pathinfo($absolutePath, PATHINFO_EXTENSION) ?: 'pdf';
            $entryName = "{$safeAgenda}_{$safeSender}_{$safeSubject}.{$extension}";

            $entryName = $this->resolveUniqueEntryName($entryName, $usedEntryNames);

            $zip->addFile($absolutePath, $entryName);

            $targetUnit = $letter->unit?->name ?? 'Seluruh Kampus';
            $manifest .= "{$counter}. [Agenda: {$letter->agenda_number}] {$letter->subject}\n";
            $manifest .= "   Pengirim: {$sender} | Unit Tujuan: {$targetUnit} | File: {$entryName}\n\n";

            $count++;
            $counter++;
        }

        return $count;
    }

    /**
     * Mengemas kumpulan Surat Keluar (Humas).
     *
     * @param  array<int>  $recordIds
     * @param  array<string, int>  $usedEntryNames
     */
    protected function packOutgoingLetters(ZipArchive $zip, array $recordIds, string &$manifest, array &$usedEntryNames): int
    {
        $letters = OutgoingLetter::with(['unit'])
            ->whereIn('id', $recordIds)
            ->get();

        $count = 0;
        $counter = 1;
        $disk = Storage::disk('private');

        foreach ($letters as $letter) {
            $relativeFilePath = $letter->getActiveFilePath();
            if (! $relativeFilePath || ! $disk->exists($relativeFilePath)) {
                continue;
            }

            $absolutePath = $disk->path($relativeFilePath);
            if (! file_exists($absolutePath)) {
                continue;
            }

            $safeNumber = str_replace(['/', '\\', ':', '*', '?', '"', '<', '>', '|'], '-', (string) ($letter->letter_number ?: 'SK-'.$letter->id));
            $safeUnit = Str::limit(Str::slug($letter->unit?->name ?? 'unit'), 25, '');
            $safeSubject = Str::limit(Str::slug($letter->subject), 40, '');
            $extension = pathinfo($absolutePath, PATHINFO_EXTENSION) ?: 'pdf';
            $entryName = "{$safeNumber}_{$safeUnit}_{$safeSubject}.{$extension}";

            $entryName = $this->resolveUniqueEntryName($entryName, $usedEntryNames);

            $zip->addFile($absolutePath, $entryName);

            $unitName = $letter->unit?->name ?? '-';
            $destination = $letter->destination_organization ?: '-';
            $manifest .= "{$counter}. [Nomor: {$letter->letter_number}] {$letter->subject}\n";
            $manifest .= "   Unit Pengaju: {$unitName} | Tujuan: {$destination} | File: {$entryName}\n\n";

            $count++;
            $counter++;
        }

        return $count;
    }

    /**
     * Pastikan nama entri di dalam ZIP tidak tumpang tindih.
     *
     * @param  array<string, int>  $usedEntryNames
     */
    protected function resolveUniqueEntryName(string $entryName, array &$usedEntryNames): string
    {
        if (! isset($usedEntryNames[$entryName])) {
            $usedEntryNames[$entryName] = 1;

            return $entryName;
        }

        $usedEntryNames[$entryName]++;
        $extension = pathinfo($entryName, PATHINFO_EXTENSION);
        $filename = pathinfo($entryName, PATHINFO_FILENAME);

        return "{$filename}_(".$usedEntryNames[$entryName].").{$extension}";
    }

    /**
     * Dapatkan label modul yang ramah pengguna.
     */
    protected function getModuleLabel(string $moduleType): string
    {
        return match ($moduleType) {
            'documents' => 'Dokumen Arsip & Borang',
            'incoming_letters' => 'Surat Masuk (Eksternal)',
            'outgoing_letters' => 'Surat Keluar (Humas)',
            default => 'Berkas Arsip',
        };
    }

    /**
     * Format ukuran berkas ke satuan human-readable.
     */
    protected function formatBytes(int $bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= (1 << (10 * $pow));

        return round($bytes, $precision).' '.$units[$pow];
    }
}
