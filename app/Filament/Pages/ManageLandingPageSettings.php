<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Models\LandingPageSetting;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

class ManageLandingPageSettings extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedComputerDesktop;

    protected static string|UnitEnum|null $navigationGroup = 'Publikasi & Informasi Publik';

    protected static ?string $navigationLabel = 'Pengaturan Beranda CMS';

    protected static ?string $title = 'Pengaturan Beranda & Konten CMS';

    protected static ?int $navigationSort = 3;

    protected string $view = 'filament.pages.manage-landing-page-settings';

    /**
     * Data form state yang terikat ke Livewire.
     *
     * @var array<string, mixed>
     */
    public ?array $data = [];

    public static function canAccess(): bool
    {
        $user = auth()->user();
        if (! $user) {
            return false;
        }

        return $user->hasRole('super_admin')
            || $user->can('cms.manage_landing_page')
            || $user->can('manage_landing_page_settings');
    }

    public function mount(): void
    {
        $settings = LandingPageSetting::current();
        $this->form->fill($settings->attributesToArray());
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Tabs::make('Pengaturan Beranda CMS')
                    ->tabs([
                        // Tab 1: Identitas Institusi & Header
                        Tab::make('Identitas Kampus & Header')
                            ->icon(Heroicon::OutlinedBuildingLibrary)
                            ->schema([
                                Section::make('Identitas Resmi Universitas & Sistem')
                                    ->description('Konfigurasi nama resmi institusi, akronim monogram, nama sistem e-Arsip, dan logo resmi.')
                                    ->schema([
                                        TextInput::make('university_name')
                                            ->label('Nama Universitas')
                                            ->placeholder('Contoh: Universitas Bina Insan')
                                            ->required()
                                            ->maxLength(255),

                                        TextInput::make('university_acronym')
                                            ->label('Akronim Monogram')
                                            ->placeholder('Contoh: UNIVBI')
                                            ->required()
                                            ->maxLength(20),

                                        TextInput::make('system_name')
                                            ->label('Nama Sistem e-Arsip')
                                            ->placeholder('Contoh: Sistem Informasi e-Arsip Terpadu & Publikasi Kampus')
                                            ->required()
                                            ->maxLength(255)
                                            ->columnSpanFull(),

                                        TextInput::make('system_tagline')
                                            ->label('Slogan / Tagline Institusi')
                                            ->placeholder('Contoh: Pangkalan Data Naskah Dinas Sah, Akreditasi Borang & Portal Warta Kampus')
                                            ->maxLength(255)
                                            ->columnSpanFull(),

                                        FileUpload::make('university_logo_path')
                                            ->label('Logo Resmi Universitas')
                                            ->helperText('Format: PNG, JPG, WEBP, atau SVG. Disarankan berlatar transparan dengan resolusi tajam (maks. 2MB).')
                                            ->image()
                                            ->disk('public')
                                            ->directory('cms/logos')
                                            ->maxSize(2048),

                                        FileUpload::make('favicon_path')
                                            ->label('Favicon Tab Browser')
                                            ->helperText('Ikon kecil untuk tab peramban (format: PNG atau ICO, maks. 1MB).')
                                            ->image()
                                            ->disk('public')
                                            ->directory('cms/favicons')
                                            ->maxSize(1024),
                                    ])
                                    ->columns(2),
                            ]),

                        // Tab 2: Banner Hero & Call-to-Action
                        Tab::make('Hero Banner & CTA')
                            ->icon(Heroicon::OutlinedPhoto)
                            ->schema([
                                Section::make('Banner Utama & Sambutan Beranda')
                                    ->description('Kelola teks sambutan hero, lencana institusi, tombol aksi (CTA), dan foto banner utama beranda.')
                                    ->schema([
                                        TextInput::make('hero_badge')
                                            ->label('Teks Lencana (Badge) Hero')
                                            ->placeholder('Contoh: Pusat Publikasi & Arsip Resmi Kampus')
                                            ->required()
                                            ->maxLength(100),

                                        TextInput::make('hero_title')
                                            ->label('Judul Utama (Headline)')
                                            ->placeholder('Contoh: Satu Gerbang Naskah Resmi, Dokumentasi & Akreditasi')
                                            ->required()
                                            ->maxLength(255),

                                        Textarea::make('hero_subtitle')
                                            ->label('Sub-judul Sambutan (Tagline Deskripsi)')
                                            ->placeholder('Uraian singkat tentang fungsi portal publik e-Arsip dan warta institusi.')
                                            ->rows(3)
                                            ->columnSpanFull(),

                                        FileUpload::make('hero_banner_path')
                                            ->label('Foto Banner Latar Hero')
                                            ->helperText('Gambar resolusi tinggi gedung rektorat / kampus (format: JPG, PNG, WEBP, maks. 5MB).')
                                            ->image()
                                            ->disk('public')
                                            ->directory('cms/banners')
                                            ->maxSize(5120)
                                            ->columnSpanFull(),

                                        TextInput::make('hero_cta_primary_label')
                                            ->label('Label Tombol Utama (CTA 1)')
                                            ->default('Jelajahi Dokumen Publik')
                                            ->required()
                                            ->maxLength(100),

                                        TextInput::make('hero_cta_primary_url')
                                            ->label('Tautan Target Tombol Utama (CTA 1)')
                                            ->default('#dokumen-publik')
                                            ->required()
                                            ->maxLength(255),

                                        TextInput::make('hero_cta_secondary_label')
                                            ->label('Label Tombol Sekunder (CTA 2)')
                                            ->default('Verifikasi Naskah QR')
                                            ->required()
                                            ->maxLength(100),

                                        TextInput::make('hero_cta_secondary_url')
                                            ->label('Tautan Target Tombol Sekunder (CTA 2)')
                                            ->default('/verify')
                                            ->required()
                                            ->maxLength(255),
                                    ])
                                    ->columns(2),
                            ]),

                        // Tab 3: Seksi Dokumen Publik & Berita Kampus
                        Tab::make('Seksi Dokumen & Berita')
                            ->icon(Heroicon::OutlinedDocumentDuplicate)
                            ->schema([
                                Section::make('Seksi Tabel Dokumen Publik')
                                    ->description('Atur judul, keterangan, dan jumlah naskah dinas bertaraf akses publik per halaman.')
                                    ->schema([
                                        TextInput::make('docs_section_badge')
                                            ->label('Badge Seksi Dokumen')
                                            ->default('Transparansi Informasi Publik')
                                            ->required()
                                            ->maxLength(100),

                                        TextInput::make('docs_section_title')
                                            ->label('Judul Seksi Dokumen')
                                            ->default('Daftar Naskah Dinas & Dokumen Publik')
                                            ->required()
                                            ->maxLength(255),

                                        Textarea::make('docs_section_description')
                                            ->label('Deskripsi Seksi Dokumen')
                                            ->rows(2)
                                            ->columnSpanFull(),

                                        TextInput::make('docs_per_page')
                                            ->label('Jumlah Dokumen Per Halaman (Paginasi)')
                                            ->numeric()
                                            ->minValue(5)
                                            ->maxValue(50)
                                            ->default(10)
                                            ->required(),
                                    ])
                                    ->columns(2),

                                Section::make('Seksi Warta & Dokumentasi Kegiatan Kampus')
                                    ->description('Konfigurasi tampilan berita kampus di halaman utama (maksimal 5 item).')
                                    ->schema([
                                        TextInput::make('news_section_badge')
                                            ->label('Badge Seksi Berita')
                                            ->default('Kabar & Kegiatan')
                                            ->required()
                                            ->maxLength(100),

                                        TextInput::make('news_section_title')
                                            ->label('Judul Seksi Berita')
                                            ->default('Warta & Publikasi Kampus Terbaru')
                                            ->required()
                                            ->maxLength(255),

                                        Textarea::make('news_section_description')
                                            ->label('Deskripsi Seksi Berita')
                                            ->rows(2)
                                            ->columnSpanFull(),

                                        TextInput::make('news_max_items')
                                            ->label('Maksimal Berita di Beranda (1 - 5 Item)')
                                            ->helperText('Dibatasi maksimal 5 postingan agar tata letak beranda tetap rapi dan seimbang.')
                                            ->numeric()
                                            ->minValue(1)
                                            ->maxValue(5)
                                            ->default(5)
                                            ->required(),

                                        TextInput::make('news_view_all_label')
                                            ->label('Label Tombol Lihat Semua')
                                            ->default('Lihat Semua Warta Kampus')
                                            ->required()
                                            ->maxLength(100),
                                    ])
                                    ->columns(2),
                            ]),

                        // Tab 4: Kontak Kampus & Media Sosial
                        Tab::make('Kontak & Media Sosial')
                            ->icon(Heroicon::OutlinedGlobeAlt)
                            ->schema([
                                Section::make('Kontak Resmi Institusi')
                                    ->description('Informasi narahubung yang ditampilkan pada footer halaman beranda.')
                                    ->schema([
                                        Textarea::make('campus_address')
                                            ->label('Alamat Lengkap Kampus')
                                            ->placeholder('Jl. Jend. Besar Dr. A.H. Nasution No. 298...')
                                            ->rows(2)
                                            ->columnSpanFull(),

                                        TextInput::make('campus_phone')
                                            ->label('Nomor Telepon Resmi')
                                            ->placeholder('(0733) 322442')
                                            ->maxLength(50),

                                        TextInput::make('campus_email')
                                            ->label('Alamat Email Resmi Kampus')
                                            ->placeholder('humas@univbi.ac.id')
                                            ->email()
                                            ->maxLength(255),

                                        TextInput::make('campus_maps_url')
                                            ->label('Tautan Google Maps Kampus')
                                            ->placeholder('https://maps.google.com/...')
                                            ->url()
                                            ->columnSpanFull(),
                                    ])
                                    ->columns(2),

                                Section::make('Akun Media Sosial Resmi Universitas')
                                    ->description('Tautan ke kanal media sosial resmi untuk memudahkan sivitas akademika dan publik.')
                                    ->schema([
                                        TextInput::make('social_instagram')
                                            ->label('Akun Instagram Resmi')
                                            ->placeholder('https://instagram.com/univbi_official')
                                            ->url(),

                                        TextInput::make('social_youtube')
                                            ->label('Kanal YouTube Resmi')
                                            ->placeholder('https://youtube.com/@univbi_official')
                                            ->url(),

                                        TextInput::make('social_facebook')
                                            ->label('Halaman Facebook Resmi')
                                            ->placeholder('https://facebook.com/univbi.official')
                                            ->url(),

                                        TextInput::make('social_linkedin')
                                            ->label('Profil LinkedIn Resmi')
                                            ->placeholder('https://linkedin.com/school/universitas-bina-insan')
                                            ->url(),

                                        TextInput::make('social_twitter')
                                            ->label('Akun Twitter / X Resmi')
                                            ->placeholder('https://x.com/univbi_official')
                                            ->url()
                                            ->columnSpanFull(),
                                    ])
                                    ->columns(2),
                            ]),

                        // Tab 5: Info Developer & Hak Cipta
                        Tab::make('Developer & Hak Cipta')
                            ->hidden()
                            ->icon(Heroicon::OutlinedCodeBracket)
                            ->schema([
                                Section::make('Informasi Tim Pengembang & Hak Cipta Sistem')
                                    ->description('Kredit nama pengembang sistem, email pengembang, dan pernyataan hak cipta resmi.')
                                    ->schema([
                                        TextInput::make('developer_name')
                                            ->label('Nama Pengembang / Tim Pengembang')
                                            ->placeholder('Tim Pengembang IT & Pangkalan Data UNIVBI')
                                            ->required()
                                            ->maxLength(255),

                                        TextInput::make('developer_email')
                                            ->label('Alamat Email Pengembang')
                                            ->placeholder('developer@univbi.ac.id')
                                            ->email()
                                            ->required()
                                            ->maxLength(255),

                                        TextInput::make('developer_website')
                                            ->label('Tautan Portofolio / Website Developer')
                                            ->placeholder('https://univbi.ac.id')
                                            ->url()
                                            ->columnSpanFull(),

                                        TextInput::make('copyright_text')
                                            ->label('Teks Pernyataan Hak Cipta (Copyright)')
                                            ->placeholder('© 2026 Universitas Bina Insan. Hak Cipta Dilindungi Undang-Undang.')
                                            ->required()
                                            ->maxLength(255)
                                            ->columnSpanFull(),
                                    ])
                                    ->columns(2),
                            ]),
                    ])
                    ->columnSpanFull(),
            ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('save')
                ->label('Simpan Perubahan Pengaturan')
                ->icon(Heroicon::OutlinedCheck)
                ->color('primary')
                ->action('save'),
        ];
    }

    /**
     * Simpan pembaruan formulir ke tabel landing_page_settings dan perbarui cache.
     */
    public function save(): void
    {
        $validatedData = $this->form->getState();

        $settings = LandingPageSetting::current();
        $settings->update($validatedData);

        Notification::make()
            ->title('Pengaturan Berhasil Disimpan')
            ->body('Konfigurasi halaman utama beranda (Landing Page) dan konten CMS telah berhasil diperbarui secara instan.')
            ->success()
            ->send();
    }
}
