<?php

declare(strict_types=1);

namespace App\Filament\Resources\UserShortcuts\Pages;

use App\Filament\Pages\Dashboard;
use App\Filament\Resources\UserShortcuts\UserShortcutResource;
use App\Models\UserShortcut;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;
use Filament\Support\Enums\Width;

class ManageUserShortcuts extends ManageRecords
{
    protected static string $resource = UserShortcutResource::class;

    public function getSubheading(): ?string
    {
        $count = UserShortcut::query()->where('user_id', (int) auth()->id())->count();
        $max = UserShortcut::MAX_PER_USER;

        return "Kelola tautan cepat pribadi Anda yang ditampilkan pada Card Selamat Datang di Dasbor ({$count} / {$max} pintasan terpakai). Geser baris untuk mengatur urutan tampilan.";
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('backToDashboard')
                ->label('Kembali ke Dasbor')
                ->icon('heroicon-o-arrow-left')
                ->color('gray')
                ->url(fn (): string => Dashboard::getUrl()),

            CreateAction::make()
                ->label('Tambah Pintasan')
                ->icon('heroicon-o-plus')
                ->modalHeading('Tambah Pintasan Web Pribadi')
                ->modalDescription('Pintasan ini bersifat pribadi untuk akun Anda dan akan dirender langsung pada Card Selamat Datang di Dasbor.')
                ->modalWidth(Width::Large)
                ->createAnother(false)
                ->mutateDataUsing(function (array $data): array {
                    $data['user_id'] = (int) auth()->id();

                    return $data;
                }),
        ];
    }
}
