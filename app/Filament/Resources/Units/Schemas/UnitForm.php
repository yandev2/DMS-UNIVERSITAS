<?php

declare(strict_types=1);

namespace App\Filament\Resources\Units\Schemas;

use App\Enums\AccreditationInstrument;
use App\Enums\DegreeLevel;
use App\Enums\UnitType;
use App\Filament\Components\ButtonHint;
use App\Models\AccreditationCriterion;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

class UnitForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Identitas & Struktur Unit Organisasi')
                    ->icon('heroicon-o-building-office-2')
                    ->description('Konfigurasi nama unit, kode naskah dinas resmi, hierarki kampus, dan penunjukan pejabat kepala unit.')
                    ->schema([
                        TextInput::make('name')
                            ->label('Nama Unit / Fakultas / Program Studi')
                            ->placeholder('Contoh: Program Studi S1 Informatika, Biro Administrasi Umum')
                            ->required()
                            ->maxLength(150),

                        TextInput::make('code')
                            ->label('Kode Singkatan Naskah Dinas')
                            ->placeholder('Contoh: TI, FIK, BAUK, REKTORAT')
                            ->required()
                            ->maxLength(30)
                            ->unique(ignoreRecord: true)
                            ->hintAction(ButtonHint::make(
                                'Digunakan pada penomoran surat otomatis & kode inventaris (contoh: TI, FIK, BAAK, REKTORAT).',
                                'Panduan Kode Naskah Dinas',
                            )),

                        Select::make('type')
                            ->label('Jenis Unit Organisasi')
                            ->options(UnitType::class)
                            ->required()
                            ->live()
                            ->native(false)
                            ->hintAction(ButtonHint::make(
                                'Tentukan klasifikasi unit kerja sesuai statuta kampus. Klasifikasi ini mempengaruhi alur persetujuan surat dan instrumen borang akreditasi.',
                                'Panduan Jenis Unit Organisasi',
                            )),

                        Select::make('degree_level')
                            ->label('Jenjang Pendidikan (Khusus Prodi)')
                            ->options(DegreeLevel::class)
                            ->nullable()
                            ->native(false)
                            ->visible(fn (Get $get): bool => in_array($get('type'), [UnitType::Prodi->value, UnitType::Prodi, 'prodi'], true))
                            ->hintAction(ButtonHint::make(
                                'Pilih jenjang pendidikan jika unit ini adalah Program Studi (D3, S1, S2, Profesi). Kosongkan jika berupa fakultas atau unit non-akademik.',
                                'Panduan Jenjang Pendidikan',
                            )),

                        Select::make('parent_id')
                            ->label('Unit Induk / Atasan (Fakultas / Rektorat)')
                            ->relationship('parent', 'name')
                            ->searchable()
                            ->preload()
                            ->nullable()
                            ->hintAction(ButtonHint::make(
                                'Tentukan unit atasan dalam struktur hierarki kampus. Contoh: Program Studi berinduk ke Fakultas, dan Fakultas berinduk ke Rektorat.',
                                'Panduan Unit Induk',
                            )),

                        Select::make('head_user_id')
                            ->label('Pejabat Kepala Unit / Dekan / Kaprodi')
                            ->relationship('headUser', 'name')
                            ->searchable()
                            ->preload()
                            ->nullable()
                            ->hintAction(ButtonHint::make(
                                'Pilih akun pejabat yang menjabat sebagai kepala unit kerja ini untuk keperluan tanda tangan digital dan alur persetujuan dokumen.',
                                'Panduan Pejabat Kepala Unit',
                            )),

                        Toggle::make('is_active')
                            ->label('Status Unit Aktif')
                            ->helperText('Unit aktif dapat dipilih sebagai unit pemilik arsip dan penugasan pengguna.')
                            ->default(true)
                            ->columnSpanFull(),

                        Textarea::make('description')
                            ->label('Keterangan Unit')
                            ->placeholder('Catatan atau keterangan tugas pokok dan fungsi unit kerja...')
                            ->rows(2)
                            ->columnSpanFull(),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),

                Section::make('Informasi Status Akreditasi (BAN-PT / LAM)')
                    ->icon('heroicon-o-academic-cap')
                    ->description('Data peringkat, lembaga penilai, nomor SK, dan masa kedaluwarsa akreditasi institusi atau prodi.')
                    ->schema([
                        Select::make('accreditation_grade')
                            ->label('Peringkat Akreditasi')
                            ->options([
                                'Unggul' => 'Unggul',
                                'Baik Sekali' => 'Baik Sekali',
                                'Baik' => 'Baik',
                                'A' => 'A',
                                'B' => 'B',
                                'C' => 'C',
                                'Terakreditasi Sementara' => 'Terakreditasi Sementara',
                            ])
                            ->nullable()
                            ->native(false),

                        Select::make('accreditation_body')
                            ->label('Lembaga Akreditasi')
                            ->options([
                                'BAN-PT' => 'BAN-PT',
                                'LAM INFOKOM' => 'LAM INFOKOM',
                                'LAMEMBA' => 'LAMEMBA',
                                'LAMPTTK' => 'LAMPTTK',
                                'LAMSPK' => 'LAMSPK',
                            ])
                            ->nullable()
                            ->native(false)
                            ->live()
                            ->afterStateUpdated(function (?string $state, Set $set, Get $get): void {
                                if (filled($state) && blank($get('accreditation_instrument'))) {
                                    $set('accreditation_instrument', AccreditationInstrument::normalize($state));
                                }
                            }),

                        Select::make('accreditation_instrument')
                            ->label('Standar Instrumen Kriteria Borang')
                            ->options(function (): array {
                                $presets = AccreditationInstrument::options();
                                $existingFromDb = AccreditationCriterion::query()
                                    ->whereNotNull('instrument_source')
                                    ->distinct()
                                    ->pluck('instrument_source', 'instrument_source')
                                    ->toArray();

                                return array_merge($presets, $existingFromDb);
                            })
                            ->searchable()
                            ->native(false)
                            ->nullable()
                            ->createOptionForm([
                                TextInput::make('custom_instrument')
                                    ->label('Nama Instrumen Akreditasi Baru')
                                    ->placeholder('Contoh: ASIIN, ABET, LAM-PTKes')
                                    ->required()
                                    ->maxLength(100),
                            ])
                            ->createOptionUsing(fn (array $data): string => trim((string) $data['custom_instrument']))
                            ->hintAction(ButtonHint::make(
                                'Menentukan standar instrumen kriteria borang (K1–K9) yang digunakan unit/prodi ini saat mengunggah berkas bukti akreditasi.',
                                'Panduan Instrumen Borang',
                            )),

                        TextInput::make('accreditation_sk_number')
                            ->label('Nomor SK Sertifikat Akreditasi')
                            ->placeholder('Contoh: 1234/SK/BAN-PT/Akred/S/XI/2024')
                            ->maxLength(100)
                            ->nullable(),

                        DatePicker::make('accreditation_expired_date')
                            ->label('Masa Berlaku Akreditasi Sampai')
                            ->native(false)
                            ->nullable(),

                        Toggle::make('is_assessor_access_active')
                            ->label('Buka Akses Dokumen untuk Asesor Eksternal')
                            ->helperText('Aktifkan agar tautan publik dokumen borang dapat diakses asesor BAN-PT/LAM selama masa penilaian. Matikan untuk mengunci akses setelah penilaian selesai.')
                            ->default(false)
                            ->live()
                            ->columnSpanFull(),

                        DatePicker::make('assessor_access_start')
                            ->label('Tanggal Mulai Masa Asesmen')
                            ->native(false)
                            ->nullable()
                            ->visible(fn (Get $get): bool => (bool) $get('is_assessor_access_active')),

                        DatePicker::make('assessor_access_end')
                            ->label('Tanggal Selesai Masa Asesmen')
                            ->native(false)
                            ->nullable()
                            ->visible(fn (Get $get): bool => (bool) $get('is_assessor_access_active')),

                        TextInput::make('assessor_passcode')
                            ->label('PIN / Sandi Akses Asesor (Opsional)')
                            ->placeholder('Contoh: TI2026')
                            ->maxLength(50)
                            ->nullable()
                            ->visible(fn (Get $get): bool => (bool) $get('is_assessor_access_active')),

                        Textarea::make('assessor_notes')
                            ->label('Catatan Petunjuk untuk Asesor (Opsional)')
                            ->placeholder('Contoh: Selamat datang Asesor BAN-PT pada akreditasi Prodi TI UNIVBI...')
                            ->rows(2)
                            ->nullable()
                            ->visible(fn (Get $get): bool => (bool) $get('is_assessor_access_active')),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),
            ]);
    }
}
