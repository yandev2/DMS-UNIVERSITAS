<?php

declare(strict_types=1);

namespace App\Filament\Resources\Documents\Schemas;

use App\Enums\DocumentStatus;
use App\Enums\ReminderInterval;
use App\Enums\VisibilityLevel;
use App\Models\AcademicYear;
use App\Models\AccreditationCriterion;
use App\Models\Document;
use App\Models\DocumentCategory;
use App\Models\Unit;
use App\Models\User;
use App\Services\DocumentNamingService;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
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
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;

class DocumentForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(['lg' => 3])
            ->components([
                // Kolom Utama: Konten Dokumen & Klasifikasi (2 dari 3 kolom di desktop)
                Group::make([
                    Section::make('Klasifikasi & Metadata Dokumen Arsip')
                        ->description('Tentukan judul resmi, unit pemilik arsip, kategori naskah dinas, dan periode akademik.')
                        ->icon(Heroicon::OutlinedDocumentText)
                        ->schema([

                            TextInput::make('title')
                                ->label('Judul Dokumen / Nama Naskah Dinas')
                                ->placeholder('Contoh: SK Rektor tentang Penetapan Kalender Akademik 2026/2027')
                                ->required()
                                ->maxLength(255)
                                ->columnSpanFull(),

                            Select::make('unit_id')
                                ->label('Unit Kerja / Program Studi Pemilik')
                                ->relationship('unit', 'name')
                                ->searchable()
                                ->preload()
                                ->required()
                                ->columnSpanFull()
                                ->native(false)
                                ->live()
                                ->afterStateUpdated(function (mixed $state, Get $get, Set $set): void {
                                    $currentCatId = $get('document_category_id');
                                    if (blank($currentCatId)) {
                                        return;
                                    }

                                    $category = DocumentCategory::query()->with('allowedUnits:id')->find($currentCatId);
                                    if ($category && ! $category->isAllowedForUnit(filled($state) ? (int) $state : null)) {
                                        $set('document_category_id', null);
                                        $set('has_expiry', false);
                                        $set('requires_wet_signature', false);
                                        $set('expired_date', null);
                                        $set('reminder_intervals', []);
                                        $set('reminder_recipients', []);
                                    }
                                })
                                ->default(fn () => auth()->user()?->unit_id)
                                ->disabled(fn (?Document $record): bool => ! (auth()->user()?->can('document.bypass_unit_scope') ?? false) || self::isWorkflowClassificationLocked($record))
                                ->dehydrated(),

                            Select::make('document_category_id')
                                ->label('Kategori Dokumen')
                                ->options(function (Get $get, ?Document $record): array {
                                    $unitId = $get('unit_id') ?: auth()->user()?->unit_id;
                                    $unitIdInt = filled($unitId) ? (int) $unitId : null;

                                    $query = DocumentCategory::query();

                                    if ($record && filled($record->document_category_id)) {
                                        $query->where(function (Builder $outer) use ($unitIdInt, $record): void {
                                            $outer->availableForUnit($unitIdInt)
                                                ->orWhere('document_categories.id', $record->document_category_id);
                                        });
                                    } else {
                                        $query->availableForUnit($unitIdInt);
                                    }

                                    return $query
                                        ->orderBy('name')
                                        ->pluck('name', 'id')
                                        ->all();
                                })
                                ->searchable()
                                ->native(false)
                                ->columnSpanFull()
                                ->required()
                                ->live()
                                ->rule(static function (Get $get, ?Document $record): \Closure {
                                    return static function (string $attribute, mixed $value, \Closure $fail) use ($get, $record): void {
                                        if (blank($value)) {
                                            return;
                                        }

                                        if ($record && (int) $record->document_category_id === (int) $value) {
                                            return;
                                        }

                                        $category = DocumentCategory::query()->with('allowedUnits:id')->find($value);
                                        if (! $category) {
                                            return;
                                        }

                                        $unitId = $get('unit_id') ?: auth()->user()?->unit_id;
                                        $unitIdInt = filled($unitId) ? (int) $unitId : null;

                                        if (! $category->isAllowedForUnit($unitIdInt)) {
                                            $unitName = $unitIdInt
                                                ? (Unit::query()->whereKey($unitIdInt)->value('name') ?? 'unit terpilih')
                                                : 'unit Anda';
                                            $fail("Kategori \"{$category->name}\" khusus untuk unit tertentu dan tidak tersedia untuk {$unitName}.");
                                        }
                                    };
                                })
                                ->disabled(fn (?Document $record): bool => self::isWorkflowClassificationLocked($record))
                                ->dehydrated()
                                ->hintAction(
                                    Action::make('download_category_template')
                                        ->label('Unduh Template Word')
                                        ->icon('heroicon-o-arrow-down-tray')
                                        ->color('success')
                                        ->url(fn (Get $get): ?string => self::resolveSelectedCategory($get)?->getTemplateDownloadUrl())
                                        ->openUrlInNewTab(true)
                                        ->visible(fn (Get $get): bool => (bool) self::resolveSelectedCategory($get)?->hasExistingTemplateFile())
                                )
                                ->helperText(function (Get $get): ?HtmlString {
                                    $category = self::resolveSelectedCategory($get);

                                    if (! $category || ! $category->hasTemplate()) {
                                        return null;
                                    }

                                    if (! $category->hasExistingTemplateFile()) {
                                        return new HtmlString(
                                            "<span class='inline-flex items-center gap-1.5 text-xs text-amber-600 dark:text-amber-400 font-medium'>"
                                            .'Berkas template untuk kategori ini belum tersedia di penyimpanan server. Hubungi administrator untuk mengunggah ulang.'
                                            .'</span>'
                                        );
                                    }

                                    $url = e((string) $category->getTemplateDownloadUrl());
                                    $name = e($category->template_file_name ?? 'Template Word');

                                    return new HtmlString(
                                        "<span class='inline-flex items-center gap-1.5 text-xs text-emerald-600 dark:text-emerald-400 font-semibold'>".
                                        "<svg class='w-3.5 h-3.5' fill='none' viewBox='0 0 24 24' stroke='currentColor'><path stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4'/></svg>".
                                        "Format baku tersedia: <a href='{$url}' target='_blank' class='underline hover:text-emerald-700 dark:hover:text-emerald-300'>Unduh {$name}</a></span>"
                                    );
                                })
                                ->afterStateUpdated(function (mixed $state, Set $set, string $operation): void {
                                    if (! $state) {
                                        $set('has_expiry', false);
                                        $set('requires_wet_signature', false);
                                        $set('expired_date', null);
                                        $set('reminder_intervals', []);
                                        $set('reminder_recipients', []);

                                        return;
                                    }

                                    $category = DocumentCategory::query()->find($state);
                                    $hasExpiry = (bool) ($category?->has_expiry ?? false);
                                    $set('has_expiry', $hasExpiry);
                                    $set('requires_wet_signature', (bool) ($category?->requires_wet_signature ?? false));

                                    if ($operation === 'create' && $category?->default_visibility) {
                                        $set('visibility', $category->default_visibility->value);
                                    }

                                    if ($category && ! $category->allow_manual_number) {
                                        $set('is_manual_number', false);
                                        $set('document_number', null);
                                    }

                                    if (! $hasExpiry) {
                                        $set('expired_date', null);
                                        $set('reminder_intervals', []);
                                        $set('reminder_recipients', []);
                                    }
                                }),

                            Placeholder::make('category_template_flags')
                                ->hiddenLabel()
                                ->content(function (Get $get): HtmlString {
                                    $category = self::resolveSelectedCategory($get);
                                    if (! $category) {
                                        return new HtmlString('');
                                    }

                                    $unitId = $get('unit_id') ?: auth()->user()?->unit_id;
                                    $unitCode = filled($unitId)
                                        ? Unit::query()->whereKey($unitId)->value('code')
                                        : null;

                                    return new HtmlString(view('filament.components.document-category-flags', [
                                        'category' => $category,
                                        'unitCode' => $unitCode,
                                    ])->render());
                                })
                                ->visible(fn (Get $get): bool => self::resolveSelectedCategory($get) !== null)
                                ->columnSpanFull(),

                            DatePicker::make('issued_date')
                                ->label('Tanggal Terbit / Pengesahan')
                                ->default(now())
                                ->native(false)
                                ->required(),

                            Select::make('academic_year_id')
                                ->label('Tahun Akademik')
                                ->relationship('academicYear', 'name')
                                ->searchable()
                                ->preload()
                                ->native(false)
                                ->default(fn () => AcademicYear::where('is_active', true)->first()?->id)
                                ->nullable(),

                            Select::make('visibility')
                                ->label('Tingkat Aksesibilitas Berkas')
                                ->options(VisibilityLevel::class)
                                ->default(VisibilityLevel::Internal)
                                ->required()
                                ->native(false)
                                ->columnSpanFull(),

                            Textarea::make('description')
                                ->label('Ringkasan / Abstrak Dokumen')
                                ->placeholder('Tuliskan ringkasan isi atau latar belakang penerbitan dokumen resmi...')
                                ->rows(3)
                                ->columnSpanFull()
                                ->nullable(),
                        ])
                        ->columns(2),

                    Section::make('Unggah Berkas Fisik Dokumen (Storage Privat)')
                        ->description('Berkas disimpan aman di direktori terenkripsi privat dengan penamaan baku otomatis.')
                        ->icon(Heroicon::OutlinedArrowUpTray)
                        ->schema([
                            FileUpload::make('attachment')
                                ->label('Berkas Dokumen Primer (PDF atau Word .docx)')
                                ->helperText('Format PDF atau Word (.docx), maks. 25MB. Berkas Word mendukung penggantian tag nomor/tanggal/pejabat + QR footer kanan bawah; berkas PDF otomatis dibubuhi QR Code, Nomor Surat & Tanggal Sah di footer kanan bawah semua halaman.')
                                ->disk('private')
                                ->directory(fn (Get $get): string => app(DocumentNamingService::class)->generateDirectory($get('unit_id')))
                                ->acceptedFileTypes([
                                    'application/pdf',
                                    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                                    'application/msword',
                                ])
                                ->maxSize(25600)
                                ->required(fn (string $operation): bool => $operation === 'create')
                                ->columnSpanFull(),
                        ]),

                    Section::make('Borang Akreditasi & Masa Berlaku')
                        ->description('Pengaturan keterkaitan dengan instrumen borang BAN-PT/LAM serta pengingat kedaluwarsa dokumen.')
                        ->icon(Heroicon::OutlinedAcademicCap)
                        ->schema([
                            Toggle::make('is_borang')
                                ->label('Tandai sebagai Berkas Bukti Borang Akreditasi')
                                ->helperText('Dokumen akan otomatis dipetakan ke Bank Data Borang Akreditasi institusi.')
                                ->live()
                                ->default(false)
                                ->columnSpanFull(),

                            Select::make('accreditationCriteria')
                                ->label('Kriteria Borang Akreditasi Terkait')
                                ->relationship(
                                    name: 'accreditationCriteria',
                                    titleAttribute: 'name',
                                    modifyQueryUsing: function (Builder $query, Get $get, ?Document $record): Builder {
                                        $unitId = $get('unit_id') ?: auth()->user()?->unit_id;
                                        $unit = $unitId ? Unit::find($unitId) : null;
                                        $instrument = $unit?->effective_accreditation_instrument;

                                        $query->where('is_active', true);

                                        if ($instrument) {
                                            $query->where(function (Builder $q) use ($instrument, $record): void {
                                                $q->where('instrument_source', $instrument);

                                                if ($record && $record->exists) {
                                                    $attachedIds = $record->accreditationCriteria()->pluck('accreditation_criteria.id')->all();
                                                    if (! empty($attachedIds)) {
                                                        $q->orWhereIn('accreditation_criteria.id', $attachedIds);
                                                    }
                                                }
                                            });
                                        }

                                        return $query->orderBy('order_index');
                                    }
                                )
                                ->getOptionLabelFromRecordUsing(fn (AccreditationCriterion $record): string => "{$record->code} - {$record->name} [{$record->instrument_source}]"
                                )
                                ->multiple()
                                ->preload()
                                ->searchable()
                                ->native(false)
                                ->helperText('Kriteria otomatis disesuaikan dengan standar instrumen akreditasi unit kerja pemilik.')
                                ->visible(fn (Get $get): bool => (bool) $get('is_borang'))
                                ->columnSpanFull(),

                            Toggle::make('has_expiry')
                                ->label('Status Masa Berlaku (Otomatis Mengikuti Kategori)')
                                ->helperText(function (Get $get): string {
                                    if (blank($get('document_category_id'))) {
                                        return 'Pilih Kategori Dokumen terlebih dahulu. Masa berlaku ditentukan otomatis oleh pengaturan kategori.';
                                    }

                                    return self::resolvesCategoryHasExpiry($get)
                                        ? 'Aktif otomatis dari Kategori: Wajib mengisi Tanggal Berakhir / Kedaluwarsa di bawah.'
                                        : 'Nonaktif otomatis dari Kategori: Dokumen bersifat Tetap / Permanen dan tidak dapat diberi tanggal kedaluwarsa.';
                                })
                                ->disabled()
                                ->dehydrated()
                                ->default(false)
                                ->columnSpanFull(),

                            DatePicker::make('expired_date')
                                ->label('Berlaku Sampai Tanggal / Kedaluwarsa')
                                ->visible(fn (Get $get): bool => self::resolvesCategoryHasExpiry($get))
                                ->required(fn (Get $get): bool => self::resolvesCategoryHasExpiry($get))
                                ->native(false)
                                ->afterOrEqual('issued_date')
                                ->prefixIcon('heroicon-o-calendar')
                                ->columnSpanFull(),

                            CheckboxList::make('reminder_intervals')
                                ->label('Jadwal Notifikasi Pengingat Kedaluwarsa')
                                ->options(ReminderInterval::class)
                                ->default([
                                    ReminderInterval::H60->value,
                                    ReminderInterval::H30->value,
                                    ReminderInterval::H7->value,
                                ])
                                ->visible(fn (Get $get): bool => self::resolvesCategoryHasExpiry($get))
                                ->columns(3)
                                ->columnSpanFull(),

                            Select::make('reminder_recipients')
                                ->label('Daftar Penerima Notifikasi Pengingat')
                                ->helperText(fn (): string => (auth()->user()?->can('reminder.manage_recipients') ?? false)
                                    ? 'Pilih akun pengguna yang menerima notifikasi email & lonceng panel. Jika kosong, sistem otomatis mengirimkan pengingat ke pengunggah dokumen.'
                                    : 'Pengaturan penerima kustom memerlukan izin reminder.manage_recipients. Pengingat otomatis dialihkan ke pengunggah dokumen.')
                                ->disabled(fn (): bool => ! (auth()->user()?->can('reminder.manage_recipients') ?? false))
                                ->dehydrated(fn (): bool => auth()->user()?->can('reminder.manage_recipients') ?? false)
                                ->multiple()
                                ->searchable()
                                ->preload()
                                ->native(false)
                                ->options(function (?Document $record): array {
                                    $query = User::query()->where('is_active', true);

                                    if ($record && ! empty($record->reminder_recipients)) {
                                        $userIds = array_map('intval', (array) $record->reminder_recipients);
                                        $query->orWhereIn('id', $userIds);
                                    }

                                    return $query
                                        ->orderBy('name')
                                        ->get()
                                        ->mapWithKeys(fn (User $user): array => [
                                            $user->id => "{$user->name} ({$user->email})".($user->position_title ? " - {$user->position_title}" : ''),
                                        ])
                                        ->all();
                                })
                                ->placeholder('Pilih akun pengguna penerima notifikasi email...')
                                ->visible(fn (Get $get): bool => self::resolvesCategoryHasExpiry($get))
                                ->columnSpanFull(),
                        ])
                        ->columns(2),
                ])->columnSpan(['lg' => 2]),

                // Kolom Samping: Tata Kelola Penomoran & Rantai Dokumen (1 dari 3 kolom di desktop)
                Group::make([
                    Section::make('Tata Kelola Penomoran')
                        ->description('Pengaturan penomoran resmi naskah dinas.')
                        ->icon(Heroicon::OutlinedLockClosed)
                        ->schema([
                            Toggle::make('is_manual_number')
                                ->label('Gunakan Nomor Manual / Backdate')
                                ->helperText(function (Get $get): string {
                                    if (! (auth()->user()?->can('document.manual_numbering') ?? false)) {
                                        return 'Input nomor manual memerlukan izin document.manual_numbering.';
                                    }

                                    if (! self::resolvesCategoryAllowManualNumber($get)) {
                                        return 'Kategori dokumen ini mewajibkan penomoran otomatis sistem (input nomor manual dinonaktifkan).';
                                    }

                                    return 'Aktifkan jika naskah dinas sudah memiliki nomor surat fisik terdahulu.';
                                })
                                ->disabled(fn (Get $get, ?Document $record): bool => ! (auth()->user()?->can('document.manual_numbering') ?? false)
                                    || self::isWorkflowClassificationLocked($record)
                                    || ! self::resolvesCategoryAllowManualNumber($get))
                                ->dehydrated()
                                ->live()
                                ->default(false),

                            TextInput::make('document_number')
                                ->label('Nomor Surat Resmi')
                                ->placeholder('Contoh: 001/UNIVBI.REKTORAT/SE/XI/2026')
                                ->disabled(fn (?Document $record): bool => ! (auth()->user()?->can('document.manual_numbering') ?? false) || self::isWorkflowClassificationLocked($record))
                                ->dehydrated()
                                ->visible(fn (Get $get): bool => (bool) $get('is_manual_number'))
                                ->required(fn (Get $get): bool => (bool) $get('is_manual_number'))
                                ->maxLength(150)
                                ->unique('documents', 'document_number', ignoreRecord: true),

                            Placeholder::make('auto_number_notice')
                                ->label('Penomoran Otomatis Sistem')
                                ->content('Nomor surat resmi akan digenerate otomatis oleh sistem saat naskah dinas disetujui penuh oleh pejabat penandatangan.')
                                ->visible(fn (Get $get): bool => ! (bool) $get('is_manual_number')),

                            Toggle::make('requires_wet_signature')
                                ->label('Memerlukan TTD Basah, Cap & Scan Final')
                                ->helperText('Jika aktif, setelah disetujui pejabat dan nomor terbit, berkas wajib dicetak untuk TTD basah & cap lalu diunggah scan PDF finalnya (oleh Unit Pengaju atau Humas).')
                                ->disabled(fn (?Document $record): bool => self::isWorkflowClassificationLocked($record))
                                ->dehydrated()
                                ->visible(fn (Get $get): bool => ! (bool) $get('is_manual_number'))
                                ->default(false),
                        ]),

                    Section::make('Rantai Dokumen (Pembaruan / Pencabutan)')
                        ->description('Hubungkan naskah dinas ini dengan dokumen terdahulu jika berlaku.')
                        ->icon(Heroicon::OutlinedArrowPath)
                        ->schema([
                            Select::make('replaces_document_id')
                                ->label('Menggantikan / Mencabut Dokumen Sebelumnya')
                                ->placeholder('Ketik nomor surat atau judul dokumen lama...')
                                ->relationship(
                                    name: 'replacedDocument',
                                    titleAttribute: 'title',
                                    modifyQueryUsing: fn (Builder $query, ?Document $record): Builder => $record
                                        ? $query->where('documents.id', '!=', $record->id)
                                        : $query,
                                )
                                ->getOptionLabelFromRecordUsing(fn (Document $record): string => filled($record->document_number)
                                    ? "[{$record->document_number}] {$record->title}"
                                    : $record->title)
                                ->searchable(['title', 'document_number'])
                                ->optionsLimit(25)
                                ->nullable()
                                ->native(false)
                                ->helperText('Pilih naskah terdahulu yang digantikan atau dicabut (ketik kata kunci pencarian). Saat dokumen ini resmi Disahkan (Approved), status naskah terdahulu otomatis berubah menjadi Diarsipkan (Pasif).'),
                        ]),
                ])->columnSpan(['lg' => 1]),
            ]);
    }

    /**
     * Memastikan status masa berlaku dokumen selalu mengikuti pengaturan kategori di server-side.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function enforceCategoryExpiryPolicy(array $data): array
    {
        $categoryId = $data['document_category_id'] ?? null;
        $category = filled($categoryId)
            ? DocumentCategory::query()->find($categoryId)
            : null;
        $categoryHasExpiry = (bool) ($category?->has_expiry ?? false);

        $data['has_expiry'] = $categoryHasExpiry;

        if (! $categoryHasExpiry) {
            $data['expired_date'] = null;
            $data['reminder_intervals'] = null;
            $data['reminder_recipients'] = null;
        }

        if ($category && ! $category->allow_manual_number) {
            $data['is_manual_number'] = false;
            $data['document_number'] = null;
        }

        $isManualNumber = (bool) ($data['is_manual_number'] ?? false);
        if ($isManualNumber) {
            $data['requires_wet_signature'] = false;
        } elseif (! array_key_exists('requires_wet_signature', $data)) {
            $data['requires_wet_signature'] = (bool) ($category?->requires_wet_signature ?? false);
        } else {
            $data['requires_wet_signature'] = (bool) $data['requires_wet_signature'];
        }

        return $data;
    }

    private static function resolveSelectedCategory(Get $get): ?DocumentCategory
    {
        $categoryId = $get('document_category_id');
        if (blank($categoryId)) {
            return null;
        }

        return DocumentCategory::query()->find($categoryId);
    }

    private static function resolvesCategoryHasExpiry(Get $get): bool
    {
        return (bool) self::resolveSelectedCategory($get)?->has_expiry;
    }

    private static function resolvesCategoryAllowManualNumber(Get $get): bool
    {
        $category = self::resolveSelectedCategory($get);

        return $category ? (bool) $category->allow_manual_number : true;
    }

    private static function isWorkflowClassificationLocked(?Document $record): bool
    {
        if (! $record || ! $record->exists) {
            return false;
        }

        if (auth()->user()?->hasRole('super_admin') ?? false) {
            return false;
        }

        return ! in_array($record->status, [DocumentStatus::Draft, DocumentStatus::Revision], true);
    }
}
