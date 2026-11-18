<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Filament\Components\ButtonHint;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Identitas Pejabat / Staf & Penempatan Unit Kerja')
                    ->description('Nama lengkap dan jabatan resmi akan dicatat sebagai snapshot permanen saat pejabat mengesahkan dokumen.')
                    ->schema([
                        TextInput::make('name')
                            ->label('Nama Lengkap & Gelar')
                            ->required()
                            ->maxLength(255),

                        TextInput::make('email')
                            ->label('Alamat Email Resmi')
                            ->email()
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true),

                        TextInput::make('nidn_nip')
                            ->label('NIDN / NIP / NUPTK')
                            ->maxLength(50)
                            ->nullable()
                            ->unique(ignoreRecord: true),

                        TextInput::make('position_title')
                            ->label('Jabatan Struktural / Fungsional')
                            ->maxLength(150)
                            ->nullable()
                            ->hintAction(ButtonHint::make(
                                'Tuliskan nama jabatan resmi pejabat atau staf, contoh: Dekan Fakultas Ilmu Komputer, Kaprodi S1 Informatika, Kepala UPT Komputer.',
                                'Panduan Jabatan Struktural',
                            )),

                        Select::make('unit_id')
                            ->label('Unit Kerja / Program Studi')
                            ->relationship('unit', 'name')
                            ->searchable()
                            ->preload()
                            ->nullable()
                            ->hintAction(ButtonHint::make(
                                'Pilih unit kerja tempat pegawai bertugas. Menentukan lingkup arsip yang dapat diakses pengguna.',
                                'Panduan Unit Kerja Pengguna',
                            )),

                        TextInput::make('phone')
                            ->label('Nomor Telepon / WhatsApp')
                            ->tel()
                            ->maxLength(30)
                            ->nullable(),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),

                Section::make('Keamanan Akun & Role RBAC (Filament Shield)')
                    ->schema([
                        Select::make('roles')
                            ->label('Peran Hak Akses (Spatie Roles)')
                            ->relationship('roles', 'name')
                            ->multiple()
                            ->preload()
                            ->searchable()
                            ->hintAction(ButtonHint::make(
                                'Tentukan satu atau beberapa role Spatie untuk mengatur hak akses menu dan alur persetujuan dokumen.',
                                'Panduan Role Pengguna',
                            )),

                        TextInput::make('password')
                            ->label('Kata Sandi (Password)')
                            ->password()
                            ->revealable()
                            ->required(fn (string $operation): bool => $operation === 'create')
                            ->dehydrated(fn (?string $state): bool => filled($state))
                            ->minLength(8)
                            ->hintAction(ButtonHint::make(
                                'Panjang minimal 8 karakter. Saat mengedit akun, biarkan bidang ini kosong apabila tidak ingin mengubah kata sandi.',
                                'Panduan Kata Sandi Akun',
                            )),

                        Toggle::make('is_active')
                            ->label('Status Akun Aktif (Izinkan Login ke Panel)')
                            ->default(true)
                            ->columnSpanFull(),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),
            ]);
    }
}
