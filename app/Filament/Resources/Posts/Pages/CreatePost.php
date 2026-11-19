<?php

declare(strict_types=1);

namespace App\Filament\Resources\Posts\Pages;

use App\Enums\PostStatus;
use App\Filament\Resources\Posts\PostResource;
use Filament\Resources\Pages\CreateRecord;

class CreatePost extends CreateRecord
{
    protected static string $resource = PostResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $user = auth()->user();
        $data['author_id'] = $user?->id;

        if ($user && $user->unit_id) {
            $requestedUnitId = filled($data['unit_id'] ?? null) ? (int) $data['unit_id'] : null;
            $hasGlobalPostScope = $user->can('post.moderate_humas') || $user->can('document.bypass_unit_scope');

            if ($requestedUnitId === null || (! $hasGlobalPostScope && ! $user->canAccessUnit($requestedUnitId))) {
                $data['unit_id'] = $user->unit_id;
            }
        }

        if (empty($data['status'])) {
            $data['status'] = PostStatus::Draft->value;
        }

        if ($data['status'] === PostStatus::Published->value || $data['status'] === PostStatus::Published) {
            $data['published_at'] ??= now();
            $data['approved_by'] ??= auth()->id();
            $data['approved_at'] ??= now();
        } elseif ($data['status'] === PostStatus::PendingReview->value || $data['status'] === PostStatus::PendingReview) {
            $data['submitted_at'] ??= now();
        }

        return $data;
    }

    protected function afterCreate(): void
    {
        $record = $this->record;

        $coverPhoto = $record->photos()->where('is_cover', true)->first()
            ?? $record->photos()->orderBy('order_index')->first();

        if ($coverPhoto && empty($record->thumbnail_url)) {
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
