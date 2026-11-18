<?php

declare(strict_types=1);

namespace App\Filament\Resources\AcademicYears\Schemas;

use App\Filament\Components\ButtonHint;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class AcademicYearForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Periode Tahun Akademik & Semester')
                    ->icon('heroicon-o-calendar-days')
                    ->description('Konfigurasi periode akademik aktif untuk penandaan masa berlaku dokumen dan borang.')
                    ->schema([
                        TextInput::make('name')
                            ->label('Nama Tahun Akademik')
                            ->placeholder('Contoh: 2026/2027 Ganjil')
                            ->required()
                            ->maxLength(50)
                            ->unique(ignoreRecord: true),

                        Select::make('semester')
                            ->label('Semester')
                            ->options([
                                'ganjil' => 'Semester Ganjil',
                                'genap' => 'Semester Genap',
                                'antara' => 'Semester Antara / Pendek',
                            ])
                            ->default('ganjil')
                            ->required()
                            ->native(false),

                        DatePicker::make('start_date')
                            ->label('Tanggal Mulai Semester')
                            ->native(false)
                            ->nullable(),

                        DatePicker::make('end_date')
                            ->label('Tanggal Berakhir Semester')
                            ->native(false)
                            ->nullable()
                            ->afterOrEqual('start_date'),

                        Toggle::make('is_active')
                            ->label('Jadikan Periode Tahun Akademik Aktif Saat Ini')
                            ->hintAction(ButtonHint::make(
                                'Sistem hanya mengizinkan satu periode tahun akademik yang aktif. Mengaktifkan periode ini akan otomatis menonaktifkan status aktif pada periode lainnya.',
                                'Panduan Tahun Akademik Aktif',
                            ))
                            ->default(false)
                            ->columnSpanFull(),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),
            ]);
    }
}
