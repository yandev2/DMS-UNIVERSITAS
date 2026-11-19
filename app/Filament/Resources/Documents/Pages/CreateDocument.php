<?php

declare(strict_types=1);

namespace App\Filament\Resources\Documents\Pages;

use App\Enums\DocumentStatus;
use App\Filament\Resources\Documents\DocumentResource;
use App\Filament\Resources\Documents\Schemas\DocumentForm;
use App\Models\DocumentFile;
use App\Services\DocumentApprovalService;
use App\Services\DocumentNamingService;
use App\Services\DocumentStampingService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;

class CreateDocument extends CreateRecord
{
    protected static string $resource = DocumentResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $user = auth()->user();
        $data = DocumentForm::enforceCategoryExpiryPolicy($data);
        $data['uploaded_by'] = $user?->id;

        if ($user && $user->unit_id) {
            $requestedUnitId = filled($data['unit_id'] ?? null) ? (int) $data['unit_id'] : null;

            if ($requestedUnitId === null || ! $user->canAccessUnit($requestedUnitId)) {
                $data['unit_id'] = $user->unit_id;
            }
        }

        if (empty($data['status'])) {
            $data['status'] = DocumentStatus::Draft;
        }

        return $data;
    }

    protected function afterCreate(): void
    {
        $attachment = $this->data['attachment'] ?? null;
        $filePath = is_array($attachment) ? Arr::first($attachment) : $attachment;

        if ($filePath && is_string($filePath) && Storage::disk('private')->exists($filePath)) {
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

            $fileRecord = DocumentFile::create([
                'document_id' => $this->record->id,
                'uploaded_by' => auth()->id(),
                'is_primary' => true,
                'label' => 'Berkas Dokumen Utama',
                'order_index' => 1,
                'version' => 1,
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

            if (! $this->record->is_manual_number) {
                app(DocumentApprovalService::class)->initializeApprovalFlow($this->record);
            } elseif ($this->record->status === DocumentStatus::Approved) {
                app(DocumentStampingService::class)->stamp($this->record, $fileRecord);
            }
        }
    }
}
