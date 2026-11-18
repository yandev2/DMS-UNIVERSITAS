<?php

declare(strict_types=1);

namespace App\Filament\Resources\Posts\Pages;

use App\Enums\PostStatus;
use App\Filament\Resources\Posts\PostResource;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;

class EditPost extends EditRecord
{
    protected static string $resource = PostResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('view_public')
                ->label('Lihat di Portal Publik')
                ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                ->color('info')
                ->url(fn (): string => $this->record->getPublicUrl(), shouldOpenInNewTab: true)
                ->visible(fn (): bool => $this->record->status === PostStatus::Published),

            Action::make('download_zip')
                ->label('Unduh Paket Foto (.ZIP)')
                ->icon(Heroicon::OutlinedArrowDownTray)
                ->color('success')
                ->url(fn (): string => $this->record->getZipDownloadUrl(), shouldOpenInNewTab: true)
                ->visible(fn (): bool => $this->record->photos()->count() > 0),

            ViewAction::make(),
            DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (isset($data['status'])) {
            $isPublished = $data['status'] === PostStatus::Published->value || $data['status'] === PostStatus::Published;
            $isPending = $data['status'] === PostStatus::PendingReview->value || $data['status'] === PostStatus::PendingReview;

            if ($isPublished) {
                $data['published_at'] ??= now();
                $data['approved_by'] ??= auth()->id();
                $data['approved_at'] ??= now();
            } elseif ($isPending) {
                $data['submitted_at'] ??= now();
            }
        }

        return $data;
    }

    protected function afterSave(): void
    {
        $record = $this->record;

        $coverPhoto = $record->photos()->where('is_cover', true)->first()
            ?? $record->photos()->orderBy('order_index')->first();

        if ($coverPhoto) {
            $record->updateQuietly([
                'thumbnail_url' => $coverPhoto->thumbnail_path ?: $coverPhoto->file_path,
                'thumbnail_caption' => $coverPhoto->caption,
            ]);
        }

        $record->updateQuietly([
            'photos_count' => $record->photos()->count(),
        ]);
    }
}
