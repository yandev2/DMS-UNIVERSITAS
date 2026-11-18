<?php

declare(strict_types=1);

namespace App\Filament\Resources\Posts\Schemas;

use App\Enums\DocumentStatus;
use App\Enums\PostPeriod;
use App\Enums\PostStatus;
use App\Enums\VisibilityLevel;
use App\Exceptions\ImageCompressionException;
use App\Filament\Components\ButtonHint;
use App\Models\AcademicYear;
use App\Models\Document;
use App\Models\Post;
use App\Rules\PublicApprovedDocumentRule;
use App\Services\PostImageCompressionService;
use Filament\Forms\Components\BaseFileUpload;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class PostForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(['lg' => 3])
            ->components([
                // Kolom Utama: Konten Berita, Galeri Foto & Lampiran Dokumen Sah (2/3 lebar)
                Group::make([
                    Section::make('Konten Berita & Liputan Kegiatan')
                        ->description('Susun judul naskah berita, ringkasan kartu pengumuman, dan artikel lengkap liputan kegiatan.')
                        ->icon(Heroicon::OutlinedDocumentText)
                        ->schema([
                            TextInput::make('title')
                                ->label('Judul Berita / Nama Acara')
                                ->placeholder('Contoh: Penandatanganan MoU Kerja Sama Riset Terapan dengan PT Telkom Indonesia')
                                ->required()
                                ->maxLength(255)
                                ->columnSpanFull()
                                ->live(onBlur: true)
                                ->afterStateUpdated(function (Set $set, ?string $state, ?string $old): void {
                                    if (blank($old) || $old === Str::slug($state ?? '')) {
                                        $set('slug', Str::slug($state ?? ''));
                                    }
                                }),

                            TextInput::make('slug')
                                ->label('Slug URL Publik')
                                ->required()
                                ->maxLength(255)
                                ->unique(Post::class, 'slug', ignoreRecord: true)
                                ->helperText('Alamat URL publik naskah berita: /berita/{slug}')
                                ->columnSpanFull(),

                            Textarea::make('excerpt')
                                ->label('Ringkasan / Abstrak Berita (Excerpt)')
                                ->placeholder('Tuliskan 1-2 kalimat ringkasan kegiatan yang menarik untuk ditampilkan pada kartu feed portal publik...')
                                ->rows(3)
                                ->maxLength(500)
                                ->columnSpanFull(),

                            RichEditor::make('content')
                                ->label('Isi Lengkap Berita / Uraian Kegiatan')
                                ->placeholder('Tuliskan narasi lengkap kegiatan, latar belakang, sambutan pimpinan, hasil acara, dan penutup...')
                                ->required()
                                ->columnSpanFull()
                                ->fileAttachmentsDisk('public')
                                ->fileAttachmentsDirectory('posts/attachments')
                                ->fileAttachmentsMaxSize(51200)
                                ->fileAttachmentsAcceptedFileTypes(PostImageCompressionService::ALLOWED_MIME_TYPES)
                                ->saveUploadedFileAttachmentUsing(function (RichEditor $component, TemporaryUploadedFile $file): ?string {
                                    try {
                                        $stored = app(PostImageCompressionService::class)->compressUploadedFileSync(
                                            $file,
                                            $component->getFileAttachmentsDiskName(),
                                            $component->getFileAttachmentsDirectory() ?? 'posts/attachments',
                                            false,
                                        );

                                        return $stored['path'];
                                    } catch (ImageCompressionException $exception) {
                                        Notification::make()
                                            ->danger()
                                            ->title('Lampiran Gambar Konten Gagal Diproses')
                                            ->body($exception->getMessage())
                                            ->persistent()
                                            ->send();

                                        throw ValidationException::withMessages([
                                            $component->getStatePath() => $exception->getMessage(),
                                        ]);
                                    }
                                }),
                        ]),

                    Section::make('Galeri Multi-Foto Dokumentasi Kegiatan')
                        ->description('Unggah dokumentasi foto kegiatan beresolusi tinggi. Anda dapat menambahkan banyak foto, takarir (caption), dan menetapkan foto sampul utama (cover).')
                        ->icon(Heroicon::OutlinedPhoto)
                        ->schema([
                            Repeater::make('photos')
                                ->relationship('photos')
                                ->orderColumn('order_index')
                                ->reorderableWithButtons()
                                ->collapsible()
                                ->cloneable()
                                ->itemLabel(fn (array $state): ?string => ! empty($state['caption']) ? $state['caption'] : 'Foto Dokumentasi')
                                ->columns(2)
                                ->schema([
                                    FileUpload::make('file_path')
                                        ->label('Berkas Foto')
                                        ->image()
                                        ->disk('public')
                                        ->directory('posts/photos')
                                        ->panelAspectRatio('1:1')
                                        ->maxSize(51200)
                                        ->acceptedFileTypes(PostImageCompressionService::ALLOWED_MIME_TYPES)
                                        ->automaticallyResizeImagesMode('contain')
                                        ->automaticallyResizeImagesToWidth('2048')
                                        ->automaticallyResizeImagesToHeight('2048')
                                        ->automaticallyUpscaleImagesWhenResizing(false)
                                        ->orientImagesFromExif(true)
                                        ->hintAction(ButtonHint::make(
                                            [
                                                '1. Batas Ukuran & Format: Unggah foto dokumentasi hingga maksimal 50 MB dalam format JPG, PNG, WebP, GIF, atau AVIF (foto HEIC/RAW harap dikonversi ke JPG terlebih dahulu).',
                                                '2. Kompresi Pintar ≤ 1 MB: Sistem secara otomatis mengatur dimensi dan mengompresi hasil akhir ke ukuran ≤ 1 MB beserta thumbnail ringan untuk portal berita.',
                                                '3. Deteksi Kegagalan Jelas: Berkas yang korup, terlalu ekstrem (> 80 MP), atau melampaui batas server akan langsung menampilkan pesan penyebab gagal yang spesifik.',
                                            ],
                                            'Panduan Unggah & Kompresi Foto',
                                        ))
                                        ->validationMessages([
                                            'required' => 'Berkas foto dokumentasi wajib diunggah.',
                                            'image' => 'Berkas harus berupa file gambar yang valid (JPG, PNG, WebP, GIF, atau AVIF).',
                                            'mimetypes' => 'Format foto tidak didukung. Gunakan format JPG, PNG, WebP, GIF, atau AVIF.',
                                            'max' => 'Ukuran foto melebihi batas maksimal 50 MB.',
                                            'uploaded' => 'Berkas foto gagal terunggah ke server. Periksa batas ukuran file atau koneksi jaringan Anda.',
                                        ])
                                        ->saveUploadedFileUsing(function (BaseFileUpload $component, TemporaryUploadedFile $file): ?string {
                                            try {
                                                $result = app(PostImageCompressionService::class)->prepareUploadForPostPhoto(
                                                    $file,
                                                    $component->getDiskName(),
                                                    $component->getDirectory() ?? 'posts/photos',
                                                );

                                                return $result['path'];
                                            } catch (ImageCompressionException $exception) {
                                                Notification::make()
                                                    ->danger()
                                                    ->title('Upload Foto Berita Gagal')
                                                    ->body($exception->getMessage())
                                                    ->persistent()
                                                    ->send();

                                                throw ValidationException::withMessages([
                                                    $component->getStatePath() => $exception->getMessage(),
                                                ]);
                                            }
                                        })
                                        ->required()
                                        ->columnSpan(1),

                                    Group::make([
                                        TextInput::make('caption')
                                            ->label('Keterangan / Momen Foto')
                                            ->placeholder('Contoh: Sesi penyerahan cinderamata oleh Rektor...')
                                            ->maxLength(255),

                                        Toggle::make('is_cover')
                                            ->label('Jadikan Foto Sampul Utama (Cover)')
                                            ->inline(false)
                                            ->helperText('Akan ditampilkan sebagai gambar utama di portal berita publik.'),
                                    ])->columnSpan(1),
                                ])
                                ->columnSpanFull(),
                        ]),

                    Section::make('Tautan Naskah Dinas Sah Terkait (Publik)')
                        ->description('Tautkan naskah dinas resmi (SK Rektor, Berita Acara, Lembar MoU) yang relevan. Sistem HANYA mengizinkan penautan dokumen yang berstatus Disahkan (Approved) atau Arsip, dan bertaraf akses Publik.')
                        ->icon(Heroicon::OutlinedDocumentCheck)
                        ->schema([
                            Repeater::make('postDocuments')
                                ->relationship('postDocuments')
                                ->orderColumn('order_index')
                                ->reorderableWithButtons()
                                ->collapsible()
                                ->itemLabel(function (array $state): ?string {
                                    if (empty($state['document_id'])) {
                                        return 'Tautan Naskah Dinas';
                                    }

                                    $doc = self::resolveDocumentSummary($state['document_id']);

                                    return $doc ? sprintf('[%s] %s', $doc->document_number ?? 'Sah', $doc->title) : 'Tautan Naskah Dinas';
                                })
                                ->columns(2)
                                ->schema([
                                    Select::make('document_id')
                                        ->label('Pilih Naskah Dinas Sah (Publik)')
                                        ->placeholder('Ketik nomor surat atau judul naskah publik...')
                                        ->searchable()
                                        ->optionsLimit(25)
                                        ->getSearchResultsUsing(function (string $search): array {
                                            $keyword = trim($search);
                                            if ($keyword === '') {
                                                return [];
                                            }

                                            $operator = DB::getDriverName() === 'pgsql' ? 'ilike' : 'like';

                                            return Document::query()
                                                ->with('unit:id,code')
                                                ->where('visibility', VisibilityLevel::Public)
                                                ->whereIn('status', [DocumentStatus::Approved, DocumentStatus::Archived])
                                                ->whereNotNull('document_number')
                                                ->where(function (Builder $query) use ($keyword, $operator): void {
                                                    $query->where('title', $operator, "%{$keyword}%")
                                                        ->orWhere('document_number', $operator, "%{$keyword}%");
                                                })
                                                ->orderByDesc('issued_date')
                                                ->limit(25)
                                                ->get(['id', 'document_number', 'title', 'unit_id'])
                                                ->mapWithKeys(fn (Document $doc): array => [
                                                    $doc->id => sprintf('[%s] %s (%s)', $doc->document_number, $doc->title, $doc->unit?->code ?? '-'),
                                                ])
                                                ->all();
                                        })
                                        ->getOptionLabelUsing(function (mixed $value): ?string {
                                            if (blank($value)) {
                                                return null;
                                            }

                                            $doc = self::resolveDocumentSummary($value);

                                            if (! $doc) {
                                                return null;
                                            }

                                            return sprintf('[%s] %s (%s)', $doc->document_number ?? '-', $doc->title, $doc->unit?->code ?? '-');
                                        })
                                        ->required()
                                        ->rules([
                                            'required',
                                            'exists:documents,id',
                                            new PublicApprovedDocumentRule,
                                        ])
                                        ->columnSpan(1),

                                    TextInput::make('custom_button_label')
                                        ->label('Label Tombol Tautan (Opsional)')
                                        ->placeholder('Contoh: Unduh Salinan SK Rektor (.PDF)')
                                        ->maxLength(150)
                                        ->columnSpan(1),
                                ])
                                ->columnSpanFull(),
                        ]),
                ])->columnSpan(['lg' => 2]),

                // Kolom Samping: Klasifikasi, Unit Kerja & Status Moderasi Humas (1/3 lebar)
                Group::make([
                    Section::make('Klasifikasi & Pelaksana Acara')
                        ->description('Tentukan kategori pengumuman, unit penyelenggara, dan waktu acara.')
                        ->icon(Heroicon::OutlinedBuildingOffice)
                        ->schema([
                            Select::make('post_category_id')
                                ->label('Kategori Berita / Acara')
                                ->relationship('category', 'name')
                                ->searchable()
                                ->preload()
                                ->required()
                                ->native(false),

                            Select::make('period')
                                ->label('Jenis Edisi Berita')
                                ->options(PostPeriod::class)
                                ->default(PostPeriod::Daily->value)
                                ->required()
                                ->native(false)
                                ->helperText('Harian: berita/liputan kegiatan harian; Mingguan: rangkuman/sorotan pekanan; Bulanan: buletin atau laporan capaian bulanan.'),

                            Select::make('unit_id')
                                ->label('Unit Kerja / Ormawa Penyelenggara')
                                ->relationship('unit', 'name')
                                ->searchable()
                                ->preload()
                                ->required()
                                ->native(false)
                                ->default(fn () => auth()->user()?->unit_id)
                                ->disabled(fn () => ! (auth()->user()?->can('document.bypass_unit_scope') ?? false))
                                ->dehydrated(),

                            Select::make('academic_year_id')
                                ->label('Tahun Akademik')
                                ->relationship('academicYear', 'name')
                                ->searchable()
                                ->preload()
                                ->native(false)
                                ->default(fn () => AcademicYear::where('is_active', true)->first()?->id)
                                ->nullable(),

                            DatePicker::make('event_date')
                                ->label('Tanggal Acara / Kegiatan')
                                ->native(false)
                                ->default(now()),

                            TextInput::make('event_location')
                                ->label('Lokasi Kegiatan')
                                ->placeholder('Contoh: Aula Gedung A UNIVBI')
                                ->maxLength(255),
                        ]),

                    Section::make('Status Moderasi & Publikasi')
                        ->description('Alur editorial terpusat oleh Tim Humas Universitas.')
                        ->icon(Heroicon::OutlinedShieldCheck)
                        ->schema([
                            Select::make('status')
                                ->label('Status Artikel')
                                ->options(function () {
                                    $user = auth()->user();
                                    $isHumasOrAdmin = $user && ($user->can('post.moderate_humas') || $user->can('document.bypass_unit_scope'));

                                    if ($isHumasOrAdmin) {
                                        return collect(PostStatus::cases())->mapWithKeys(fn (PostStatus $status) => [
                                            $status->value => $status->getLabel(),
                                        ]);
                                    }

                                    return [
                                        PostStatus::Draft->value => PostStatus::Draft->getLabel(),
                                        PostStatus::PendingReview->value => PostStatus::PendingReview->getLabel(),
                                    ];
                                })
                                ->default(PostStatus::Draft->value)
                                ->required()
                                ->native(false)
                                ->live(),

                            DateTimePicker::make('published_at')
                                ->label('Jadwal Tayang Publik')
                                ->native(false)
                                ->visible(fn (Get $get) => in_array($get('status'), [PostStatus::Published->value, PostStatus::Published], true))
                                ->default(now()),

                            Toggle::make('is_featured')
                                ->label('Berita Utama (Featured Headline)')
                                ->disabled(fn () => ! (auth()->user()?->can('post.moderate_humas') ?? false))
                                ->helperText('Tampilkan sebagai sorotan banner teratas portal.'),

                            Toggle::make('is_pinned')
                                ->label('Sematkan di Atas (Pinned)')
                                ->disabled(fn () => ! (auth()->user()?->can('post.moderate_humas') ?? false)),

                            Toggle::make('is_borang')
                                ->label('Dokumentasi Terkait Borang')
                                ->default(false)
                                ->helperText('Tandai jika liputan berita atau galeri kegiatan ini merupakan bukti dokumentasi borang akreditasi.'),

                            Toggle::make('allow_public_zip_download')
                                ->label('Izinkan Unduh Galeri ZIP Publik')
                                ->default(true)
                                ->helperText('Pengunjung portal dapat mengunduh seluruh foto kegiatan dalam 1 arsip ZIP.'),

                            Textarea::make('moderation_note')
                                ->label('Catatan Redaksional / Revisi Humas')
                                ->rows(3)
                                ->visible(fn (Get $get) => filled($get('moderation_note')) || (auth()->user()?->can('post.moderate_humas') ?? false))
                                ->disabled(fn () => ! (auth()->user()?->can('post.moderate_humas') ?? false)),
                        ]),
                ])->columnSpan(['lg' => 1]),
            ]);
    }

    /**
     * Ambil ringkasan label naskah dinas dengan memoisasi per-request agar itemLabel dan select tidak duplikat query.
     */
    private static function resolveDocumentSummary(int|string $documentId): ?Document
    {
        $id = (int) $documentId;
        if ($id <= 0) {
            return null;
        }

        $attributes = request()->attributes;
        /** @var array<int, Document|null> $cache */
        $cache = $attributes->get('_post_form_doc_summaries', []);

        if (array_key_exists($id, $cache)) {
            return $cache[$id];
        }

        $doc = Document::query()
            ->with('unit:id,code')
            ->select(['id', 'document_number', 'title', 'unit_id'])
            ->find($id);

        $cache[$id] = $doc;
        $attributes->set('_post_form_doc_summaries', $cache);

        return $doc;
    }
}
