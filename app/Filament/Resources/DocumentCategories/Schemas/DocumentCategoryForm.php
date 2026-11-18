<?php

declare(strict_types=1);

namespace App\Filament\Resources\DocumentCategories\Schemas;

use App\Enums\VisibilityLevel;
use App\Filament\Components\ButtonHint;
use App\Models\Role;
use App\Rules\WordDocumentFileRule;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class DocumentCategoryForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Identitas & Klasifikasi Naskah Dinas')
                    ->icon('heroicon-o-folder')
                    ->description('Tentukan nama jenis naskah dinas, kode singkatan, dan visibilitas akses default.')
                    ->schema([
                        TextInput::make('name')
                            ->label('Nama Kategori Dokumen')
                            ->placeholder('Contoh: Surat Keputusan (SK) Dekan')
                            ->required()
                            ->maxLength(150)
                            ->live(onBlur: true)
                            ->afterStateUpdated(function (?string $state, Set $set, string $operation): void {
                                if ($operation === 'create' && filled($state)) {
                                    $set('slug', Str::slug($state));
                                }
                            }),

                        TextInput::make('slug')
                            ->label('Slug Identifier')
                            ->placeholder('otomatis-dari-nama')
                            ->required()
                            ->maxLength(150)
                            ->unique(ignoreRecord: true),

                        TextInput::make('code')
                            ->label('Kode Jenis Surat')
                            ->placeholder('Contoh: SK, SE, MOU, BAST, SOP')
                            ->required()
                            ->maxLength(30)
                            ->hintAction(ButtonHint::make(
                                'Singkatan jenis dokumen yang akan disisipkan ke token {cat} pada penomoran surat.',
                                'Panduan Kode Jenis Surat',
                            )),

                        Select::make('default_visibility')
                            ->label('Tingkat Aksesibilitas Default')
                            ->options(VisibilityLevel::class)
                            ->default(VisibilityLevel::Internal)
                            ->required()
                            ->native(false),

                        Textarea::make('description')
                            ->label('Deskripsi & Pedoman Kategori')
                            ->placeholder('Tuliskan rincian atau pedoman naskah dinas yang termasuk dalam kategori ini...')
                            ->rows(2)
                            ->columnSpanFull(),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),

                Section::make('Template Baku Naskah Dinas (Word .docx / .doc)')
                    ->icon('heroicon-o-document-arrow-up')
                    ->description('Unggah berkas draf format baku surat (.docx atau .doc). Sivitas akademika dapat mengunduh berkas template ini sebagai rujukan format naskah saat menyusun dokumen pada kategori ini.')
                    ->schema([
                        FileUpload::make('template_file_path')
                            ->label('Berkas Template Naskah (Word)')
                            ->disk('private')
                            ->directory('document-templates')
                            ->acceptedFileTypes([
                                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                                'application/msword',
                            ])
                            ->mimeTypeMap([
                                'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                                'doc' => 'application/msword',
                            ])
                            ->rules(['mimes:docx,doc', new WordDocumentFileRule])
                            ->maxSize(20480)
                            ->downloadable()
                            ->openable()
                            ->storeFileNamesIn('template_file_name')
                            ->validationMessages([
                                'mimes' => 'Hanya berkas Microsoft Word (.docx atau .doc) yang diizinkan.',
                                'mimetypes' => 'Format berkas tidak valid. Unggah hanya file Microsoft Word (.docx atau .doc).',
                                'max' => 'Ukuran berkas template maksimal 20 MB.',
                                'uploaded' => 'Berkas template gagal diunggah ke server. Periksa ukuran file atau koneksi jaringan Anda.',
                            ])
                            ->helperText('Hanya menerima berkas Microsoft Word (.docx atau .doc), maksimal 20 MB. File selain Word (PDF, gambar, Excel, arsip ZIP, atau berkas bermacro) otomatis ditolak.')
                            ->columnSpanFull(),
                    ])
                    ->collapsible()
                    ->columnSpanFull(),

                Section::make('Format Penomoran Surat')
                    ->icon('heroicon-o-qr-code')
                    ->description('Konfigurasi pola nomor surat resmi otomatis dan tag naskah dokumen.')
                    ->schema([
                        TextInput::make('number_format')
                            ->label('Pola Penomoran Surat Otomatis')
                            ->default('{seq}/UNIVBI.{unit}/{cat}/{roman_month}/{year}')
                            ->maxLength(150)
                            ->columnSpanFull()
                            ->hintAction(ButtonHint::make(
                                "PANDUAN LENGKAP TOKEN & TAG ALTERNATIF BERKAS:\n\n"
                                ."1. Token Format Penomoran:\n"
                                ."   • {seq} : Nomor urut 3 digit (contoh: 001)\n"
                                ."   • {unit} : Kode unit kerja pengunggah (contoh: BAAK, FIK, TI)\n"
                                ."   • {cat} : Kode kategori surat dinas (contoh: SK, ST, SE)\n"
                                ."   • {roman_month} : Bulan terbit format Romawi (contoh: I - XII)\n"
                                ."   • {month} : Bulan terbit format 2 digit angka (contoh: 01 - 12)\n"
                                ."   • {year} : Tahun 4 digit (contoh: 2026)\n\n"
                                ."2. Tag Alternatif pada Dokumen Word (.docx):\n"
                                ."   Sistem otomatis mengganti tag di dalam naskah berkas .docx dengan data resmi:\n"
                                ."   • Nomor Surat : {nomor_surat}, {no_surat}, \${NOMOR_SURAT}, {nomor}, atau pola {seq}/UNIVBI.{unit}/{cat}/{roman_month}/{year}\n"
                                ."   • Tanggal Sah : {tanggal_sah}, {tgl_sah}, \${TANGGAL_SAH}\n"
                                ."   • Pejabat Penandatangan : {pejabat}, \${PEJABAT}, {signer}\n\n"
                                ."3. Stempel Barcode QR Code & Pengesahan Otomatis:\n"
                                ."   • Pada berkas Word (.docx) maupun PDF, sistem otomatis menyematkan QR Code verifikasi berukuran ringkas di pojok kanan bawah (footer) pada seluruh halaman tanpa mengganggu tata letak isi.\n"
                                .'   • Khusus berkas PDF, sistem juga membubuhkan Nomor Surat Resmi & Tanggal Sah di sisi kiri QR Code pada footer pojok kanan bawah.',
                                'Panduan Pola Penomoran & Tag Berkas',
                            )),

                    ])
                    ->columns(2)
                    ->columnSpanFull(),

                Section::make('Kebijakan & Tata Kelola Naskah')
                    ->icon('heroicon-o-shield-check')
                    ->description('Konfigurasi aturan alur persetujuan, fleksibilitas input manual untuk arsip lama, dan batas kedaluwarsa.')
                    ->schema([
                        Toggle::make('requires_approval')
                            ->label('Wajib Melalui Alur Persetujuan Pejabat')
                            ->helperText('Dokumen baru pada kategori ini harus disetujui pejabat sebelum disahkan dan nomor surat diterbitkan.')
                            ->default(false)
                            ->live(),

                        Toggle::make('allow_manual_number')
                            ->label('Izinkan Input Nomor Manual (Arsip Fisik/Backdate)')
                            ->helperText('Memungkinkan pengunggah memasukkan nomor surat manual ketika mendigitalkan arsip masa lalu.')
                            ->default(true),

                        Toggle::make('has_expiry')
                            ->label('Memiliki Masa Berlaku & Pengingat Kedaluwarsa')
                            ->helperText('Aktifkan jika dokumen memiliki masa berlaku terbatas (MoU, PKS, Sertifikat Akreditasi, SK Periodik).')
                            ->default(false),

                        Toggle::make('is_active')
                            ->label('Status Kategori Aktif')
                            ->helperText('Kategori aktif dapat dipilih oleh pengguna saat membuat atau menyunting dokumen arsip.')
                            ->default(true),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),

                Section::make('Alur Persetujuan Berjenjang (Multi-Level Approval)')
                    ->icon('heroicon-o-clipboard-document-check')
                    ->description('Urutan pejabat peninjau dan pengesah naskah (Tingkat 1 s/d Tingkat 3).')
                    ->visible(fn (Get $get): bool => (bool) $get('requires_approval'))
                    ->schema([
                        Repeater::make('approvalFlows')
                            ->label('Daftar Tahapan Persetujuan')
                            ->relationship()
                            ->orderColumn('step_order')
                            ->maxItems(3)
                            ->defaultItems(0)
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
                                    $set('approvalFlows', $state);
                                }
                            })
                            ->schema([
                                TextInput::make('step_order')
                                    ->label('Tingkat Ke-')
                                    ->numeric()
                                    ->readOnly()
                                    ->extraInputAttributes([
                                        'tabindex' => '-1',
                                        'class' => 'bg-gray-50/70 dark:bg-white/5 cursor-default select-none pointer-events-none font-medium',
                                    ])
                                    ->default(function (TextInput $component, Get $get): int {
                                        $statePath = (string) $component->getStatePath();
                                        $parentItems = $get('../../approvalFlows') ?? $get('../') ?? [];

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
                                        $parentItems = $get('../../approvalFlows') ?? $get('../') ?? [];

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
                                    ->minValue(1)
                                    ->maxValue(3)
                                    ->required(),

                                Select::make('approver_role')
                                    ->label('Role Pejabat Penyetuju')
                                    ->options(fn (): array => Role::query()->orderBy('name')->pluck('name', 'name')->all())
                                    ->required()
                                    ->searchable()
                                    ->native(false),

                                TextInput::make('step_label')
                                    ->label('Label Tahapan')
                                    ->placeholder('Contoh: Pengesahan Dekan FIK')
                                    ->required()
                                    ->maxLength(100),

                                Toggle::make('is_mandatory')
                                    ->label('Wajib Disetujui')
                                    ->default(true)
                                    ->extraFieldWrapperAttributes([
                                        'class' => 'doc-repeater-toggle-cell',
                                    ]),
                            ])
                            ->columns(4)
                            ->columnSpanFull(),
                    ])
                    ->columnSpanFull(),
            ]);
    }
}
