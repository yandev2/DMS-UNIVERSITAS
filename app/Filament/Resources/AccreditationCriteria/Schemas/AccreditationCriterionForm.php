<?php

declare(strict_types=1);

namespace App\Filament\Resources\AccreditationCriteria\Schemas;

use App\Enums\AccreditationInstrument;
use App\Filament\Components\ButtonHint;
use App\Models\AccreditationCriterion;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Validation\Rules\Unique;

class AccreditationCriterionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Konfigurasi Kriteria Borang Akreditasi')
                    ->icon('heroicon-o-academic-cap')
                    ->description('Master standar instrumen penilaian akreditasi (Kriteria 1 s/d Kriteria 9) untuk pengelompokan dokumen bukti fisik.')
                    ->schema([
                        Select::make('instrument_source')
                            ->label('Sumber Instrumen Akreditasi')
                            ->options(function (): array {
                                $presets = AccreditationInstrument::options();
                                $existingFromDb = AccreditationCriterion::query()
                                    ->whereNotNull('instrument_source')
                                    ->distinct()
                                    ->pluck('instrument_source', 'instrument_source')
                                    ->toArray();

                                return array_merge($presets, $existingFromDb);
                            })
                            ->default(AccreditationInstrument::BanPt->value)
                            ->required()
                            ->searchable()
                            ->native(false)
                            ->live()
                            ->createOptionForm([
                                TextInput::make('custom_instrument')
                                    ->label('Nama Instrumen Akreditasi Baru')
                                    ->placeholder('Contoh: ASIIN, ABET, LAM-PTKes')
                                    ->required()
                                    ->maxLength(100),
                            ])
                            ->createOptionUsing(fn (array $data): string => trim((string) $data['custom_instrument']))
                            ->hintAction(ButtonHint::make(
                                'Pilih lembaga/instrumen akreditasi standar (BAN-PT, LAM-INFOKOM, LAMEMBA, dll.) atau buat instrumen baru jika menggunakan standar akreditasi khusus/internasional.',
                                'Panduan Instrumen Akreditasi',
                            )),

                        TextInput::make('code')
                            ->label('Kode Kriteria')
                            ->placeholder('Contoh: K1, K2, ..., K9')
                            ->required()
                            ->maxLength(20)
                            ->unique(
                                table: 'accreditation_criteria',
                                column: 'code',
                                ignoreRecord: true,
                                modifyRuleUsing: fn (Unique $rule, Get $get) => $rule->where('instrument_source', $get('instrument_source'))
                            )
                            ->hintAction(ButtonHint::make(
                                'Kode singkatan standar borang akreditasi, contoh: K1 (Visi Misi), K2 (Tata Pamong), K9 (Luaran & Capaian Tridharma). Kode unik dalam cakupan instrumen akreditasi yang sama.',
                                'Panduan Kode Kriteria Borang',
                            )),

                        TextInput::make('name')
                            ->label('Nama Standar / Kriteria Akreditasi')
                            ->placeholder('Contoh: Visi, Misi, Tujuan, dan Strategi')
                            ->required()
                            ->maxLength(200)
                            ->columnSpan(2),

                        TextInput::make('order_index')
                            ->label('Urutan Tampil')
                            ->numeric()
                            ->default(1)
                            ->required(),

                        Toggle::make('is_active')
                            ->label('Status Kriteria Aktif')
                            ->helperText('Kriteria aktif akan muncul sebagai opsi pilihan kriteria borang pada dokumen arsip.')
                            ->default(true),

                        Textarea::make('description')
                            ->label('Rincian Bukti Fisik yang Dibutuhkan')
                            ->placeholder('Tuliskan rincian bukti fisik pendukung yang relevan untuk kriteria ini...')
                            ->rows(3)
                            ->columnSpanFull(),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),
            ]);
    }
}
