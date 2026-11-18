<?php

declare(strict_types=1);

namespace App\Filament\Resources\Documents\Pages;

use App\Enums\DocumentStatus;
use App\Filament\Resources\Documents\DocumentResource;
use App\Filament\Resources\Documents\Schemas\DocumentForm;
use App\Filament\Support\DeletionGuard;
use App\Models\DocumentFile;
use App\Services\DocumentNamingService;
use App\Services\DocumentStampingService;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;

class EditDocument extends EditRecord
{
    protected static string $resource = DocumentResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $primaryFile = $this->record->primaryFile;
        if ($primaryFile && $primaryFile->original_file_path) {
            $data['attachment'] = $primaryFile->original_file_path;
        }

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data = DocumentForm::enforceCategoryExpiryPolicy($data);

        // Lindungi field klasifikasi & penomoran kritis agar tidak berubah pada dokumen yang sudah berjalan/disahkan (kecuali Super Admin)
        $isSuperAdmin = auth()->user()?->hasRole('super_admin') ?? false;
        $isEditableWorkflowState = in_array($this->record->status, [DocumentStatus::Draft, DocumentStatus::Revision], true);

        if (! $isSuperAdmin && ! $isEditableWorkflowState) {
            $data['unit_id'] = $this->record->unit_id;
            $data['document_category_id'] = $this->record->document_category_id;
            $data['is_manual_number'] = $this->record->is_manual_number;
            $data['document_number'] = $this->record->document_number;
        }

        return $data;
    }

    protected function afterSave(): void
    {

        $attachment = $this->data['attachment'] ?? null;
        $filePath = is_array($attachment) ? Arr::last($attachment) : $attachment;

        if ($filePath && is_string($filePath) && Storage::disk('private')->exists($filePath)) {
            $primaryFile = $this->record->primaryFile;

            // Jika berkas baru berbeda dengan yang tercatat sebelumnya
            if (! $primaryFile || $primaryFile->original_file_path !== $filePath) {
                $namingService = app(DocumentNamingService::class);
                $extension = pathinfo($filePath, PATHINFO_EXTENSION);
                $standardFileName = $namingService->generateStandardFileName(
                    $this->record->title,
                    $this->record->unit,
                    $this->record->category,
                    $extension,
                    $this->record->issued_date ? (int) $this->record->issued_date->format('Y') : null
                );

                $fileSize = Storage::disk('private')->size($filePath);
                $mimeType = Storage::disk('private')->mimeType($filePath) ?: 'application/octet-stream';
                $fullLocalPath = Storage::disk('private')->path($filePath);
                $hash = file_exists($fullLocalPath) ? hash_file('sha256', $fullLocalPath) : null;

                $newVersion = ($primaryFile?->version ?? 0) + 1;

                // Non-aktifkan versi berkas lama
                if ($primaryFile) {
                    $primaryFile->update(['is_active' => false]);
                }

                $newFile = DocumentFile::create([
                    'document_id' => $this->record->id,
                    'uploaded_by' => auth()->id(),
                    'is_primary' => true,
                    'label' => 'Berkas Dokumen Utama (Revisi v'.$newVersion.')',
                    'order_index' => 1,
                    'version' => $newVersion,
                    'original_file_name' => basename($filePath),
                    'standardized_file_name' => $standardFileName,
                    'original_file_path' => $filePath,
                    'disk' => 'private',
                    'mime_type' => $mimeType,
                    'file_extension' => strtolower($extension),
                    'file_size' => $fileSize ?: 0,
                    'file_hash_sha256' => $hash,
                    'is_active' => true,
                ]);

                if ($this->record->status === DocumentStatus::Approved) {
                    app(DocumentStampingService::class)->stamp($this->record, $newFile);
                }
            }
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeletionGuard::preventIfRestricted(DeleteAction::make()),
            DeletionGuard::preventIfRestricted(ForceDeleteAction::make()),
            RestoreAction::make(),
        ];
    }
}
