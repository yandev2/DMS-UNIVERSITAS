<?php

declare(strict_types=1);

namespace App\Filament\Resources\IncomingLetters\Pages;

use App\Enums\IncomingLetterStatus;
use App\Filament\Resources\IncomingLetters\IncomingLetterResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListIncomingLetters extends ListRecords
{
    protected static string $resource = IncomingLetterResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Registrasi Surat Masuk Baru')
                ->icon('heroicon-o-plus')
                ->visible(fn (): bool => IncomingLetterResource::canCreate()),
        ];
    }

    public function getTabs(): array
    {
        $user = auth()->user();

        return [
            'all' => Tab::make('Semua Surat Masuk'),

            'my_turn' => Tab::make('Menunggu Dibaca Saya')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->pendingTurnForUser($user)),

            'in_progress' => Tab::make('Dalam Distribusi Baca')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('status', IncomingLetterStatus::InProgress)),

            'completed' => Tab::make('Selesai Dibaca')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('status', IncomingLetterStatus::Completed)),
        ];
    }
}
