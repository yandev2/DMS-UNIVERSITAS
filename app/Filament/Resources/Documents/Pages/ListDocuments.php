<?php

declare(strict_types=1);

namespace App\Filament\Resources\Documents\Pages;

use App\Enums\DocumentStatus;
use App\Filament\Resources\Documents\DocumentResource;
use App\Services\DocumentApprovalService;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListDocuments extends ListRecords
{
    protected static string $resource = DocumentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DocumentResource::trashAction(),
            CreateAction::make(),
        ];
    }

    public function getTabs(): array
    {
        $user = auth()->user();
        $approvalService = app(DocumentApprovalService::class);
        $pendingCount = $user ? $approvalService->countPendingApprovalsForUser($user) : 0;

        return [
            'all' => Tab::make('Semua Dokumen')
                ->icon('heroicon-o-document-text'),

            'my_pending_approval' => Tab::make('Menunggu Persetujuan Saya')
                ->icon('heroicon-o-clock')
                ->badge($pendingCount > 0 ? (string) $pendingCount : null)
                ->badgeColor('warning')
                ->modifyQueryUsing(function (Builder $query) use ($user, $approvalService): Builder {
                    if (! $user) {
                        return $query->whereRaw('1 = 0');
                    }

                    $pendingSubquery = $approvalService->getPendingApprovalsQueryForUser($user)->select('documents.id');

                    return $query->whereIn('documents.id', $pendingSubquery);
                }),

            'approved' => Tab::make('Disahkan')
                ->icon('heroicon-o-check-badge')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('documents.status', DocumentStatus::Approved)),

            'draft_revision' => Tab::make('Draf & Revisi')
                ->icon('heroicon-o-pencil-square')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->whereIn('documents.status', [
                    DocumentStatus::Draft,
                    DocumentStatus::Revision,
                ])),

            'archived_expired' => Tab::make('Arsip Pasif & Kedaluwarsa')
                ->icon('heroicon-o-archive-box')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->whereIn('documents.status', [
                    DocumentStatus::Archived,
                    DocumentStatus::Expired,
                ])),
        ];
    }
}
