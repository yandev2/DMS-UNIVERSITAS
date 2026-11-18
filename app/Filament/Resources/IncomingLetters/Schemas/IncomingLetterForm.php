<?php

declare(strict_types=1);

namespace App\Filament\Resources\IncomingLetters\Schemas;

use App\Enums\LetterPriority;
use App\Models\AcademicYear;
use App\Models\IncomingLetter;
use App\Models\User;
use App\Services\LetterManagementService;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\HtmlString;

class IncomingLetterForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(['lg' => 3])
            ->components([
                Group::make([
                    Section::make('Identitas & Asal Surat Masuk Eksternal')
                        ->description('Catat informasi asal surat dari pihak luar, unit tujuan utama di kampus, dan ringkasan isi surat.')
                        ->icon(Heroicon::OutlinedInboxArrowDown)
                        ->schema([
                            TextInput::make('sender_name')
                                ->label('Asal Surat / Instansi Pengirim')
                                ->placeholder('Contoh: LLDIKTI Wilayah II / Dinas Pendidikan Provinsi')
                                ->required()
                                ->maxLength(255)
                                ->columnSpanFull(),

                            TextInput::make('external_letter_number')
                                ->label('Nomor Surat dari Pengirim')
                                ->placeholder('Contoh: 1420/LL2/KP/2026')
                                ->columnSpanFull()
                                ->maxLength(150),

                            DatePicker::make('external_letter_date')
                                ->label('Tanggal Surat Pengirim')
                                ->native(false)
                                ->displayFormat('d F Y'),

                            DatePicker::make('received_date')
                                ->label('Tanggal Diterima Humas')
                                ->required()
                                ->default(now())
                                ->native(false)
                                ->displayFormat('d F Y')
                                ->live(),

                            Select::make('unit_id')
                                ->label('Unit Tujuan Utama (Basis Nomor Agenda)')
                                ->relationship('unit', 'name')
                                ->searchable()
                                ->preload()
                                ->required()
                                ->columnSpanFull()
                                ->native(false)
                                ->live(),

                            Select::make('priority')
                                ->label('Sifat / Prioritas Surat')
                                ->options(LetterPriority::class)
                                ->default(LetterPriority::Biasa)
                                ->required()
                                ->native(false),

                            Select::make('academic_year_id')
                                ->label('Periode Tahun Akademik')
                                ->relationship('academicYear', 'name')
                                ->searchable()
                                ->preload()
                                ->native(false)
                                ->default(fn () => AcademicYear::query()->where('is_active', true)->value('id')),

                            TextInput::make('subject')
                                ->label('Perihal Surat Masuk')
                                ->placeholder('Contoh: Undangan Rapat Koordinasi Pimpinan Perguruan Tinggi')
                                ->required()
                                ->maxLength(255)
                                ->columnSpanFull(),

                            Textarea::make('summary')
                                ->label('Keterangan Singkat / Ringkasan Isi Surat')
                                ->placeholder('Tuliskan poin penting isi surat untuk memudahkan pimpinan memahami konteks surat...')
                                ->rows(3)
                                ->columnSpanFull(),
                        ])
                        ->columns(2),
                ])->columnSpan(['lg' => 2]),

                Group::make([
                    Section::make('Engine Nomor & Stempel Humas')
                        ->description('Penomoran otomatis per unit tujuan & stempel QR Code verifikasi di footer pojok kanan bawah.')
                        ->icon(Heroicon::OutlinedQrCode)
                        ->schema([
                            Placeholder::make('agenda_preview')
                                ->label('Pratinjau Nomor Agenda & Stempel PDF')
                                ->content(function (Get $get, ?IncomingLetter $record): HtmlString {
                                    if ($record && filled($record->agenda_number)) {
                                        $num = e($record->agenda_number);
                                        $dateStr = e(app(LetterManagementService::class)->formatIndonesianDate($record->received_date));

                                        return new HtmlString(
                                            '<div style="padding: 0.75rem; border-radius: 0.375rem; background: rgba(16, 185, 129, 0.08); border: 1px solid rgba(16, 185, 129, 0.3); font-size: 0.8rem;">'
                                            .'<div style="font-size: 0.68rem; font-weight: 700; text-transform: uppercase; color: #059669;">Nomor Agenda Resmi Terbit</div>'
                                            .'<div style="font-family: ui-monospace, monospace; font-weight: 700; font-size: 0.88rem; margin-top: 0.2rem;">'.$num.'</div>'
                                            .'<div style="font-size: 0.72rem; color: #64748b; margin-top: 0.25rem;">Tanggal Stempel: '.$dateStr.'</div>'
                                            .'</div>'
                                        );
                                    }

                                    $unitId = $get('unit_id');
                                    if (! $unitId) {
                                        return new HtmlString(
                                            '<div style="padding: 0.75rem; border-radius: 0.375rem; background: rgba(148, 163, 184, 0.1); border: 1px dashed rgba(148, 163, 184, 0.4); font-size: 0.78rem; color: #64748b;">'
                                            .'Pilih <strong>Unit Tujuan Utama</strong> terlebih dahulu untuk melihat pratinjau nomor agenda surat masuk.'
                                            .'</div>'
                                        );
                                    }

                                    $preview = e(app(LetterManagementService::class)->previewNextIncomingNumber((int) $unitId));

                                    return new HtmlString(
                                        '<div style="padding: 0.75rem; border-radius: 0.375rem; background: rgba(37, 99, 235, 0.06); border: 1px solid rgba(37, 99, 235, 0.25); font-size: 0.8rem;">'
                                        .'<div style="font-size: 0.68rem; font-weight: 700; text-transform: uppercase; color: #2563eb;">Estimasi Nomor Agenda Berikutnya</div>'
                                        .'<div style="font-family: ui-monospace, monospace; font-weight: 700; font-size: 0.88rem; margin-top: 0.2rem;">'.$preview.'</div>'
                                        .'<div style="font-size: 0.72rem; color: #64748b; margin-top: 0.35rem; line-height: 1.35;">'
                                        .'Saat disimpan, sistem otomatis membubuhkan <strong>QR Code Verifikasi, Nomor Agenda & Tanggal Diterima</strong> di footer pojok kanan bawah seluruh halaman PDF.'
                                        .'</div>'
                                        .'</div>'
                                    );
                                }),
                        ]),

                    Section::make('Berkas Fisik Surat Masuk (PDF)')
                        ->description('Unggah pindaian (scan) surat masuk dalam format PDF untuk dibubuhi stempel QR Code di footer pojok kanan bawah.')
                        ->icon(Heroicon::OutlinedDocumentArrowUp)
                        ->schema([
                            FileUpload::make('original_file_path')
                                ->label('Berkas PDF Surat Masuk')
                                ->disk('private')
                                ->directory('incoming-letters')
                                ->acceptedFileTypes(['application/pdf'])
                                ->maxSize(20480)
                                ->storeFileNamesIn('file_name')
                                ->required(fn (string $operation): bool => $operation === 'create')
                                ->downloadable()
                                ->openable(),
                        ]),
                ])->columnSpan(['lg' => 1]),

                Section::make('Alur Distribusi & Hak Akses Penerima Berjenjang')
                    ->description('Tentukan urutan pejabat penerima surat (misal: 1. Warek -> 2. Rektor, atau langsung 1. Rektor). Pihak berikutnya akan menerima notifikasi otomatis setelah pejabat sebelumnya klik "Tandai Sudah Dibaca".')
                    ->icon(Heroicon::OutlinedUserGroup)
                    ->columnSpanFull()
                    ->schema([
                        Repeater::make('recipients')
                            ->label('Urutan Pejabat Penerima Surat Masuk')
                            ->relationship()
                            ->orderColumn('step_order')
                            ->defaultItems(1)
                            ->minItems(1)
                            ->maxItems(6)
                            ->addActionLabel('Tambah Penerima Tahap Berikutnya')
                            ->live()
                            ->afterStateUpdated(function (Repeater $component, ?array $state, Set $set): void {
                                if (! is_array($state)) {
                                    return;
                                }

                                $order = 1;
                                $hasChanges = false;

                                foreach ($state as $uuid => $itemData) {
                                    if (($itemData['step_order'] ?? null) !== $order) {
                                        $state[$uuid]['step_order'] = $order;
                                        $hasChanges = true;
                                    }
                                    $order++;
                                }

                                if ($hasChanges) {
                                    $component->rawState($state);
                                    $set('recipients', $state);
                                }
                            })
                            ->columns(['default' => 1, 'md' => 12])
                            ->columnSpanFull()
                            ->schema([
                                TextInput::make('step_order')
                                    ->label('Tahap Ke-')
                                    ->numeric()
                                    ->readOnly()
                                    ->extraInputAttributes([
                                        'tabindex' => '-1',
                                        'class' => 'bg-gray-50/70 dark:bg-white/5 cursor-default select-none pointer-events-none font-semibold',
                                    ])
                                    ->default(function (TextInput $component, Get $get): int {
                                        $statePath = (string) $component->getStatePath();
                                        $parentItems = $get('../../recipients') ?? $get('../') ?? [];

                                        if (is_array($parentItems) && count($parentItems) > 0) {
                                            $keys = array_keys($parentItems);
                                            foreach ($keys as $idx => $k) {
                                                if (str_contains($statePath, (string) $k)) {
                                                    return $idx + 1;
                                                }
                                            }

                                            return count($parentItems);
                                        }

                                        return 1;
                                    })
                                    ->formatStateUsing(function (TextInput $component, Get $get, mixed $state): int {
                                        $statePath = (string) $component->getStatePath();
                                        $parentItems = $get('../../recipients') ?? $get('../') ?? [];

                                        if (is_array($parentItems) && count($parentItems) > 0) {
                                            $keys = array_keys($parentItems);
                                            foreach ($keys as $idx => $k) {
                                                if (str_contains($statePath, (string) $k)) {
                                                    return $idx + 1;
                                                }
                                            }
                                        }

                                        return filled($state) ? (int) $state : 1;
                                    })
                                    ->required()
                                    ->columnSpan(['default' => 12, 'md' => 2]),

                                Select::make('recipient_user_id')
                                    ->label('Pejabat / Pihak Kampus Penerima')
                                    ->options(function (): array {
                                        return User::query()
                                            ->where('is_active', true)
                                            ->with('unit')
                                            ->orderBy('name')
                                            ->get()
                                            ->mapWithKeys(function (User $user): array {
                                                $pos = $user->position_title ?: ($user->unit?->name ?: 'Staf');

                                                return [$user->id => "{$user->name} — {$pos}"];
                                            })
                                            ->all();
                                    })
                                    ->searchable()
                                    ->preload()
                                    ->required()
                                    ->native(false)
                                    ->columnSpan(['default' => 12, 'md' => 5]),

                                TextInput::make('instruction_note')
                                    ->label('Catatan Pengantar Humas (Opsional)')
                                    ->placeholder('Contoh: Mohon telaah / Untuk diketahui Bapak Rektor')
                                    ->maxLength(255)
                                    ->columnSpan(['default' => 12, 'md' => 5]),
                            ]),
                    ]),
            ]);
    }
}
