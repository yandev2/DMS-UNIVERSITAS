<?php

declare(strict_types=1);

namespace App\Filament\Resources\OutgoingLetters\Schemas;

use App\Enums\OutgoingLetterStatus;
use App\Filament\Components\ButtonHint;
use App\Models\AcademicYear;
use App\Models\OutgoingLetter;
use App\Services\LetterManagementService;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\HtmlString;

class OutgoingLetterForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(['lg' => 3])
            ->components([
                Group::make([
                    Section::make('Identitas & Tujuan Surat Keluar')
                        ->description('Lengkapi informasi unit pengaju, instansi eksternal yang dituju, tanggal surat, dan perihal.')
                        ->icon(Heroicon::OutlinedPaperAirplane)
                        ->schema([
                            Select::make('unit_id')
                                ->label('Unit Pengaju (Basis Nomor Surat Keluar)')
                                ->relationship('unit', 'name')
                                ->searchable()
                                ->preload()
                                ->required()
                                ->native(false)
                                ->live()
                                ->columnSpanFull()
                                ->default(fn () => auth()->user()?->unit_id)
                                ->disabled(fn (): bool => ! (auth()->user()?->can('letter.manage_humas') || auth()->user()?->can('document.bypass_unit_scope')))
                                ->dehydrated(),

                            Select::make('academic_year_id')
                                ->label('Periode Tahun Akademik')
                                ->relationship('academicYear', 'name')
                                ->searchable()
                                ->preload()
                                ->native(false)
                                ->default(fn () => AcademicYear::query()->where('is_active', true)->value('id')),

                            DatePicker::make('letter_date')
                                ->label('Tanggal Surat')
                                ->required()
                                ->default(now())
                                ->native(false)
                                ->displayFormat('d F Y')
                                ->live(),

                            TextInput::make('destination_organization')
                                ->label('Tujuan Surat (Instansi / Pihak Eksternal)')
                                ->placeholder('Contoh: Kepala LLDIKTI Wilayah II / PT Telkom Indonesia')
                                ->required()
                                ->columnSpanFull()
                                ->maxLength(255),

                            TextInput::make('subject')
                                ->label('Perihal Surat Keluar')
                                ->placeholder('Contoh: Permohonan Kerja Sama Magang Mahasiswa Tahun 2026')
                                ->required()
                                ->maxLength(255)
                                ->columnSpanFull(),

                            Textarea::make('description')
                                ->label('Keterangan Singkat (Opsional)')
                                ->placeholder('Tuliskan keterangan singkat atau catatan pengantar untuk peninjau Humas...')
                                ->rows(3)
                                ->columnSpanFull(),
                        ])
                        ->columns(2),
                ])->columnSpan(['lg' => 2]),

                Group::make([
                    Section::make('Engine Nomor Surat Keluar Humas')
                        ->description('Penomoran otomatis per unit pengaju oleh Bagian Humas.')
                        ->icon(Heroicon::OutlinedQrCode)
                        ->schema([
                            Placeholder::make('number_preview')
                                ->label('Status & Pratinjau Nomor Surat')
                                ->hintAction(ButtonHint::make(
                                    "PANDUAN PENOMORAN & TAG TEMPLATE SURAT KELUAR:\n\n"
                                    ."1. Format Nomor Per Unit:\n"
                                    ."   • {seq}/UNIVBI.{unit}/SKL/{roman_month}/{year}\n\n"
                                    ."2. Jika Mengunggah Berkas PDF (.pdf):\n"
                                    ."   • Saat disahkan Humas, sistem otomatis membubuhkan QR Code Verifikasi beserta Nomor Surat & Tanggal di footer pojok kanan bawah seluruh halaman.\n\n"
                                    ."3. Jika Mengunggah Berkas Word (.docx):\n"
                                    ."   • QR Code Verifikasi otomatis disematkan di footer pojok kanan bawah seluruh halaman, sementara flag/pola penomor di naskah akan diisi otomatis saat Humas menerbitkan nomor:\n"
                                    ."   • Nomor Surat : \${NOMOR_SURAT}, {nomor_surat}, atau {seq}/UNIVBI.{unit}/SKL/{roman_month}/{year}\n"
                                    .'   • Tanggal Sah : ${TANGGAL_SAH}, {tanggal_sah}, atau {tanggal_surat}',
                                    'Panduan Nomor & Tag Berkas Surat Keluar'
                                ))
                                ->content(function (Get $get, ?OutgoingLetter $record): HtmlString {
                                    if ($record && filled($record->letter_number)) {
                                        $num = e($record->letter_number);
                                        $dateStr = e(app(LetterManagementService::class)->formatIndonesianDate($record->letter_date));

                                        return new HtmlString(
                                            '<div style="padding: 0.75rem; border-radius: 0.375rem; background: rgba(16, 185, 129, 0.08); border: 1px solid rgba(16, 185, 129, 0.3); font-size: 0.8rem;">'
                                            .'<div style="font-size: 0.68rem; font-weight: 700; text-transform: uppercase; color: #059669;">Nomor Surat Resmi Terbit</div>'
                                            .'<div style="font-family: ui-monospace, monospace; font-weight: 700; font-size: 0.88rem; margin-top: 0.2rem;">'.$num.'</div>'
                                            .'<div style="font-size: 0.72rem; color: #64748b; margin-top: 0.25rem;">Tanggal Surat: '.$dateStr.'</div>'
                                            .'</div>'
                                        );
                                    }

                                    $unitId = $get('unit_id') ?: auth()->user()?->unit_id;
                                    if (! $unitId) {
                                        return new HtmlString(
                                            '<div style="padding: 0.75rem; border-radius: 0.375rem; background: rgba(148, 163, 184, 0.1); border: 1px dashed rgba(148, 163, 184, 0.4); font-size: 0.78rem; color: #64748b;">'
                                            .'Pilih <strong>Unit Pengaju</strong> untuk melihat pratinjau nomor surat keluar.'
                                            .'</div>'
                                        );
                                    }

                                    $preview = e(app(LetterManagementService::class)->previewNextOutgoingNumber((int) $unitId));
                                    $isHumas = auth()->user()?->can('letter.manage_humas') ?? false;
                                    $requiresWetSignature = (bool) $get('requires_wet_signature');

                                    $flowInfo = match (true) {
                                        $isHumas && $requiresWetSignature => 'Karena Anda login sebagai <strong>Bagian Humas</strong> dan opsi TTD basah aktif, nomor surat resmi langsung diterbitkan lalu berstatus <strong>Menunggu TTD Basah &amp; Cap</strong> hingga scan PDF final diunggah.',
                                        $isHumas => 'Karena Anda login sebagai <strong>Bagian Humas</strong>, nomor surat resmi dan stempel akan langsung diterbitkan otomatis begitu disimpan.',
                                        $requiresWetSignature => 'Setelah disimpan, surat diteruskan ke <strong>Bagian Humas</strong> untuk dinomori. Setelah nomor terbit, Unit atau Humas mengunggah hasil scan PDF ber-TTD basah &amp; cap.',
                                        default => 'Setelah disimpan, surat akan diteruskan ke <strong>Bagian Humas</strong> untuk ditinjau dan diterbitkan nomor resminya secara otomatis.',
                                    };

                                    return new HtmlString(
                                        '<div style="padding: 0.75rem; border-radius: 0.375rem; background: rgba(37, 99, 235, 0.06); border: 1px solid rgba(37, 99, 235, 0.25); font-size: 0.8rem;">'
                                        .'<div style="font-size: 0.68rem; font-weight: 700; text-transform: uppercase; color: #2563eb;">Estimasi Nomor Surat Berikutnya</div>'
                                        .'<div style="font-family: ui-monospace, monospace; font-weight: 700; font-size: 0.88rem; margin-top: 0.2rem;">'.$preview.'</div>'
                                        .'<div style="font-size: 0.72rem; color: #64748b; margin-top: 0.35rem; line-height: 1.35;">'.$flowInfo.'</div>'
                                        .'</div>'
                                    );
                                }),

                            Toggle::make('requires_wet_signature')
                                ->label('Perlu TTD Basah, Cap & Scan Final')
                                ->helperText('Aktifkan bila surat keluar perlu dicetak setelah penomoran untuk TTD basah pimpinan & cap Humas, lalu diunggah hasil scan PDF finalnya (oleh Unit Pengaju atau Humas).')
                                ->default(false)
                                ->live()
                                ->disabled(fn (?OutgoingLetter $record): bool => $record !== null
                                    && in_array($record->status, [OutgoingLetterStatus::WaitingSignature, OutgoingLetterStatus::Published], true))
                                ->dehydrated(),
                        ]),

                    Section::make('Unggah Berkas Surat Keluar')
                        ->description('Unggah berkas surat dalam format PDF (.pdf) atau Microsoft Word (.docx).')
                        ->icon(Heroicon::OutlinedDocumentArrowUp)
                        ->schema([
                            FileUpload::make('original_file_path')
                                ->label('Berkas Surat (.pdf / .docx)')
                                ->disk('private')
                                ->directory('outgoing-letters')
                                ->acceptedFileTypes([
                                    'application/pdf',
                                    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                                ])
                                ->maxSize(20480)
                                ->storeFileNamesIn('file_name')
                                ->required(fn (string $operation): bool => $operation === 'create')
                                ->downloadable()
                                ->openable(),
                        ]),
                ])->columnSpan(['lg' => 1]),
            ]);
    }
}
