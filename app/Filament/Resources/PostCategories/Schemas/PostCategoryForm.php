<?php

declare(strict_types=1);

namespace App\Filament\Resources\PostCategories\Schemas;

use App\Filament\Components\ButtonHint;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class PostCategoryForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Kategori Publikasi & Berita Kampus')
                    ->icon('heroicon-o-newspaper')
                    ->description('Pengelompokan artikel kegiatan, pengumuman akademik, dan galeri dokumentasi pada Portal Informasi Publik.')
                    ->schema([
                        TextInput::make('name')
                            ->label('Nama Kategori Berita')
                            ->placeholder('Contoh: Berita Akademik, Pengumuman, Prestasi')
                            ->required()
                            ->maxLength(100)
                            ->live(onBlur: true)
                            ->afterStateUpdated(function (?string $state, Set $set, string $operation): void {
                                if ($operation === 'create' && filled($state)) {
                                    $set('slug', Str::slug($state));
                                }
                            }),

                        TextInput::make('slug')
                            ->label('Slug URL')
                            ->placeholder('otomatis-dari-nama')
                            ->required()
                            ->maxLength(120)
                            ->unique(ignoreRecord: true),

                        Select::make('color')
                            ->label('Warna Badge Label')
                            ->options([
                                'blue' => 'Biru (Blue)',
                                'red' => 'Merah (Red)',
                                'green' => 'Hijau (Green)',
                                'amber' => 'Kuning / Amber',
                                'purple' => 'Ungu (Purple)',
                                'orange' => 'Oranye (Orange)',
                                'primary' => 'Biru Utama (Primary)',
                                'info' => 'Biru Muda (Info)',
                                'success' => 'Hijau (Success)',
                                'warning' => 'Kuning (Warning)',
                                'danger' => 'Merah (Danger)',
                                'gray' => 'Abu-abu (Gray)',
                            ])
                            ->default('blue')
                            ->required()
                            ->native(false)
                            ->hintAction(ButtonHint::make(
                                'Pilih warna aksen visual untuk label badge kategori ini pada tabel dan kartu berita di portal informasi publik.',
                                'Panduan Warna Badge Kategori',
                            )),

                        TextInput::make('order_index')
                            ->label('Urutan Tampil')
                            ->numeric()
                            ->default(1)
                            ->required(),

                        Toggle::make('is_active')
                            ->label('Status Kategori Aktif')
                            ->helperText('Kategori aktif akan muncul pada portal publik dan form penulisan berita.')
                            ->default(true)
                            ->columnSpanFull(),

                        Textarea::make('description')
                            ->label('Deskripsi Kategori')
                            ->placeholder('Keterangan singkat cakupan artikel dan berita dalam kategori ini...')
                            ->rows(3)
                            ->columnSpanFull(),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),
            ]);
    }
}
