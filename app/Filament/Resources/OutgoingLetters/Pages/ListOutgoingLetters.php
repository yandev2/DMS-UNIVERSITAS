<?php

declare(strict_types=1);

namespace App\Filament\Resources\OutgoingLetters\Pages;

use App\Enums\OutgoingLetterStatus;
use App\Filament\Resources\OutgoingLetters\OutgoingLetterResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListOutgoingLetters extends ListRecords
{
    protected static string $resource = OutgoingLetterResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Buat Surat Keluar Baru')
                ->icon('heroicon-o-plus'),
        ];
    }

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('Semua Surat Keluar'),

            'pending_humas' => Tab::make('Menunggu Tinjauan Humas')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('status', OutgoingLetterStatus::PendingHumas)),

            'waiting_signature' => Tab::make('Menunggu TTD Basah')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('status', OutgoingLetterStatus::WaitingSignature)),

            'published' => Tab::make('Nomor Terbit (Selesai)')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('status', OutgoingLetterStatus::Published)),

            'revision_or_rejected' => Tab::make('Perlu Revisi / Ditolak')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->whereIn('status', [
                    OutgoingLetterStatus::NeedsRevision,
                    OutgoingLetterStatus::Rejected,
                ])),
        ];
    }
}
