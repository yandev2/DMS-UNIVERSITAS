<?php

declare(strict_types=1);

namespace Tests\Feature\Public;

use App\Enums\DegreeLevel;
use App\Enums\DocumentStatus;
use App\Enums\PostStatus;
use App\Enums\StampingPosition;
use App\Enums\UnitType;
use App\Enums\VisibilityLevel;
use App\Filament\Pages\ManageLandingPageSettings;
use App\Models\AcademicYear;
use App\Models\Document;
use App\Models\DocumentCategory;
use App\Models\LandingPageSetting;
use App\Models\Post;
use App\Models\PostCategory;
use App\Models\Unit;
use App\Models\User;
use App\Support\Security\RuntimeIntegrityManifest;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class LandingPageTest extends TestCase
{
    use LazilyRefreshDatabase;

    private Unit $unit;

    private DocumentCategory $docCategory;

    private PostCategory $postCategory;

    private AcademicYear $academicYear;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        Cache::forget(LandingPageSetting::CACHE_KEY);

        $this->unit = Unit::create([
            'code' => 'TI',
            'name' => 'Program Studi Informatika',
            'slug' => 'program-studi-informatika',
            'type' => UnitType::Prodi,
            'degree_level' => DegreeLevel::S1,
            'accreditation_grade' => 'Unggul',
            'is_active' => true,
        ]);

        $this->academicYear = AcademicYear::create([
            'name' => '2026/2027 Ganjil',
            'code' => '20261',
            'is_active' => true,
            'is_current' => true,
            'start_date' => now()->startOfYear(),
            'end_date' => now()->endOfYear(),
        ]);

        $this->docCategory = DocumentCategory::create([
            'code' => 'SK',
            'name' => 'Surat Keputusan Rektor',
            'slug' => 'surat-keputusan-rektor',
            'description' => 'Naskah dinas penetapan kebijakan',
            'is_active' => true,
            'stamping_position' => StampingPosition::TopRight,
        ]);

        $this->postCategory = PostCategory::create([
            'name' => 'Warta Akademik',
            'slug' => 'warta-akademik',
            'is_active' => true,
        ]);

        $this->user = User::factory()->create([
            'unit_id' => $this->unit->id,
            'is_active' => true,
        ]);

        // Inisialisasi Setting CMS default
        LandingPageSetting::createDefault();
    }

    public function test_landing_page_renders_successfully_with_cms_content(): void
    {
        $response = $this->get(route('landing.home'));

        $response->assertOk();
        $response->assertSee('Universitas Bina Insan');
        $response->assertSee('Satu Gerbang Naskah Resmi, Dokumentasi & Akreditasi');
        $response->assertSee('Pusat Publikasi & Arsip Resmi Kampus');
        $response->assertSee('Jelajahi Dokumen Publik');
        $response->assertSee('Warta & Kegiatan Kampus');
        $response->assertDontSee('href="/verify"', false);
        $response->assertDontSee('href="/bukti-borang"', false);
    }

    public function test_landing_page_only_shows_public_approved_documents_with_numbers(): void
    {
        // 1. Dokumen Publik Sah dengan nomor naskah (HARUS MUNCUL)
        $publicDoc = Document::create([
            'title' => 'Panduan Kurikulum OBE Berbasis AI 2026',
            'unit_id' => $this->unit->id,
            'document_category_id' => $this->docCategory->id,
            'academic_year_id' => $this->academicYear->id,
            'document_number' => '001/UNIVBI/SK/XI/2026',
            'visibility' => VisibilityLevel::Public,
            'status' => DocumentStatus::Approved,
            'issued_date' => now()->subDay()->toDateString(),
            'uploaded_by' => $this->user->id,
            'approved_at' => now()->subDay(),
        ]);

        // 2. Dokumen Internal (DILARANG MUNCUL)
        $internalDoc = Document::create([
            'title' => 'Dokumen Rahasia Anggaran Internal',
            'unit_id' => $this->unit->id,
            'document_category_id' => $this->docCategory->id,
            'academic_year_id' => $this->academicYear->id,
            'document_number' => '002/UNIVBI/INT/XI/2026',
            'visibility' => VisibilityLevel::Internal,
            'status' => DocumentStatus::Approved,
            'issued_date' => now()->subDay()->toDateString(),
            'uploaded_by' => $this->user->id,
            'approved_at' => now()->subDay(),
        ]);

        // 3. Dokumen Draf (DILARANG MUNCUL)
        $draftDoc = Document::create([
            'title' => 'Draf Rencana Strategis Kampus',
            'unit_id' => $this->unit->id,
            'document_category_id' => $this->docCategory->id,
            'academic_year_id' => $this->academicYear->id,
            'document_number' => '003/UNIVBI/DRAFT/XI/2026',
            'visibility' => VisibilityLevel::Public,
            'status' => DocumentStatus::Draft,
            'issued_date' => now()->toDateString(),
            'uploaded_by' => $this->user->id,
        ]);

        // 4. Dokumen tanpa Nomor Naskah Resmi (DILARANG MUNCUL)
        $noNumberDoc = Document::create([
            'title' => 'Dokumen Publik Tanpa Nomor',
            'unit_id' => $this->unit->id,
            'document_category_id' => $this->docCategory->id,
            'academic_year_id' => $this->academicYear->id,
            'document_number' => null,
            'visibility' => VisibilityLevel::Public,
            'status' => DocumentStatus::Approved,
            'issued_date' => now()->subDay()->toDateString(),
            'uploaded_by' => $this->user->id,
            'approved_at' => now()->subDay(),
        ]);

        $response = $this->get(route('landing.home'));

        $response->assertOk();
        $response->assertSee('Panduan Kurikulum OBE Berbasis AI 2026');
        $response->assertSee('001/UNIVBI/SK/XI/2026');

        $response->assertDontSee('Dokumen Rahasia Anggaran Internal');
        $response->assertDontSee('Draf Rencana Strategis Kampus');
        $response->assertDontSee('Dokumen Publik Tanpa Nomor');
    }

    public function test_landing_page_document_search_filters_correctly(): void
    {
        Document::create([
            'title' => 'Pedoman Skripsi Mahasiswa Informatika',
            'unit_id' => $this->unit->id,
            'document_category_id' => $this->docCategory->id,
            'academic_year_id' => $this->academicYear->id,
            'document_number' => 'SK/2026/010',
            'visibility' => VisibilityLevel::Public,
            'status' => DocumentStatus::Approved,
            'issued_date' => now()->toDateString(),
            'uploaded_by' => $this->user->id,
            'approved_at' => now(),
        ]);

        Document::create([
            'title' => 'Surat Edaran Libur Semester Ganjil',
            'unit_id' => $this->unit->id,
            'document_category_id' => $this->docCategory->id,
            'academic_year_id' => $this->academicYear->id,
            'document_number' => 'SE/2026/088',
            'visibility' => VisibilityLevel::Public,
            'status' => DocumentStatus::Approved,
            'issued_date' => now()->toDateString(),
            'uploaded_by' => $this->user->id,
            'approved_at' => now(),
        ]);

        // Cari "Skripsi"
        $response = $this->get(route('landing.home', ['q' => 'Skripsi']));
        $response->assertOk();
        $response->assertSee('Pedoman Skripsi Mahasiswa Informatika');
        $response->assertDontSee('Surat Edaran Libur Semester Ganjil');

        // Cari Nomor "SE/2026/088"
        $response2 = $this->get(route('landing.home', ['q' => 'SE/2026/088']));
        $response2->assertOk();
        $response2->assertSee('Surat Edaran Libur Semester Ganjil');
        $response2->assertDontSee('Pedoman Skripsi Mahasiswa Informatika');
    }

    public function test_landing_page_only_shows_maximum_5_published_news(): void
    {
        // Buat 7 Berita Terbit
        for ($i = 1; $i <= 7; $i++) {
            Post::create([
                'title' => "Berita Kampus Ke-{$i} Terbaru",
                'slug' => "berita-kampus-ke-{$i}-terbaru",
                'post_category_id' => $this->postCategory->id,
                'unit_id' => $this->unit->id,
                'academic_year_id' => $this->academicYear->id,
                'author_id' => $this->user->id,
                'content' => "Isi liputan berita kegiatan kampus ke-{$i}",
                'status' => PostStatus::Published,
                'published_at' => now()->subMinutes(10 * $i),
            ]);
        }

        // 1 Berita Draft (DILARANG MUNCUL)
        Post::create([
            'title' => 'Draft Berita Internal Belum Rilis',
            'slug' => 'draft-berita-internal-belum-rilis',
            'post_category_id' => $this->postCategory->id,
            'unit_id' => $this->unit->id,
            'academic_year_id' => $this->academicYear->id,
            'author_id' => $this->user->id,
            'content' => 'Isi draf rahasia',
            'status' => PostStatus::Draft,
        ]);

        $response = $this->get(route('landing.home'));

        $response->assertOk();
        // Berita 1 s.d. 5 (5 teratas terbaru) harus muncul
        $response->assertSee('Berita Kampus Ke-1 Terbaru');
        $response->assertSee('Berita Kampus Ke-2 Terbaru');
        $response->assertSee('Berita Kampus Ke-3 Terbaru');
        $response->assertSee('Berita Kampus Ke-4 Terbaru');
        $response->assertSee('Berita Kampus Ke-5 Terbaru');

        // Berita 6 dan 7 harus terpotong oleh batasan limit 5
        $response->assertDontSee('Berita Kampus Ke-6 Terbaru');
        $response->assertDontSee('Berita Kampus Ke-7 Terbaru');

        // Berita draft tidak boleh muncul sama sekali
        $response->assertDontSee('Draft Berita Internal Belum Rilis');
    }

    public function test_landing_page_view_all_news_button_links_to_news_portal(): void
    {
        $response = $this->get(route('landing.home'));

        $response->assertOk();
        $response->assertSee(route('public.posts.index'));
        $response->assertSee('Lihat Semua Warta Kampus');
    }

    public function test_landing_page_footer_displays_developer_and_social_media_info(): void
    {
        $settings = LandingPageSetting::current();
        $settings->update([
            'social_instagram' => 'https://instagram.com/univbi_test',
        ]);

        $response = $this->get(route('landing.home'));

        $response->assertOk();
        $response->assertSee(RuntimeIntegrityManifest::getDeveloperName());
        $response->assertSee('mailto:'.RuntimeIntegrityManifest::getDeveloperEmail(), false);
        $response->assertSee('https://instagram.com/univbi_test', false);
        $response->assertSee(RuntimeIntegrityManifest::getCopyrightNotice());
    }

    public function test_filament_admin_can_access_manage_landing_page_settings(): void
    {
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        $this->user->assignRole('super_admin');

        $response = $this->actingAs($this->user)->get('/admin/manage-landing-page-settings');

        $response->assertOk();
        $response->assertSee('Pengaturan Beranda CMS');
        $response->assertSee('Identitas Kampus & Header');
        $response->assertSee('Hero Banner & CTA');
        $response->assertSee('Seksi Dokumen & Berita');
        $response->assertSee('Kontak & Media Sosial');
    }

    public function test_filament_admin_can_save_settings_via_livewire(): void
    {
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        $this->user->assignRole('super_admin');

        $this->actingAs($this->user);

        Livewire::test(ManageLandingPageSettings::class)
            ->fillForm([
                'university_name' => 'Universitas Bina Insan Unggul',
                'hero_title' => 'Gerbang Baru e-Arsip & Riset',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('Universitas Bina Insan Unggul', LandingPageSetting::current()->university_name);
        $this->assertSame('Gerbang Baru e-Arsip & Riset', LandingPageSetting::current()->hero_title);
    }

    public function test_landing_page_renders_cms_logo_image_when_configured_and_fallback_when_empty(): void
    {
        // 1. Uji Saat Logo Tersedia di CMS
        Storage::disk('public')->put('landing-settings/test-logo.png', 'fake-logo-content');
        $setting = LandingPageSetting::current();
        $setting->update([
            'university_logo_path' => 'landing-settings/test-logo.png',
            'university_acronym' => 'UNIVBI',
        ]);
        Cache::forget(LandingPageSetting::CACHE_KEY);

        $responseWithLogo = $this->get(route('landing.home'));
        $responseWithLogo->assertOk();
        $responseWithLogo->assertSee('test-logo.png');

        // 2. Uji Saat Logo Kosong -> Menggunakan Fallback Monogram
        $setting->update([
            'university_logo_path' => null,
            'university_acronym' => 'UNIVBI-KAMPUS',
        ]);
        Cache::forget(LandingPageSetting::CACHE_KEY);

        $responseFallback = $this->get(route('landing.home'));
        $responseFallback->assertOk();
        $responseFallback->assertSee('UNIVBI-KAMPUS');
    }

    public function test_landing_page_hero_section_supports_light_mode_and_dark_mode_classes(): void
    {
        $response = $this->get(route('landing.home'));

        $response->assertOk();
        // Memastikan kelas Light Mode & Dark Mode terpasang secara adaptif pada Hero Section
        $response->assertSee('dark:from-navy-950', false);
        $response->assertSee('dark:text-white', false);
        $response->assertSee('text-slate-900', false);
        $response->assertSee('dark:via-slate-950', false);
        $response->assertSee('bg-white/80 dark:bg-slate-900/60', false);
        $response->assertSee('Naskah resmi terbuka');
        $response->assertSee('Liputan resmi terbit');
        $response->assertSee('Tervalidasi QR digital');
        $response->assertDontSee('>0+<', false);
    }

    public function test_landing_page_uses_balanced_adaptive_news_layouts_for_one_to_five_posts(): void
    {
        $createPost = function (int $index): Post {
            return Post::create([
                'title' => "Berita Uji Adaptif Ke-{$index}",
                'slug' => "berita-uji-adaptif-ke-{$index}",
                'post_category_id' => $this->postCategory->id,
                'unit_id' => $this->unit->id,
                'academic_year_id' => $this->academicYear->id,
                'author_id' => $this->user->id,
                'content' => "Konten liputan uji tata letak adaptif berita ke-{$index}.",
                'event_location' => 'Kampus Utama UNIVBI',
                'status' => PostStatus::Published,
                'published_at' => now()->subMinutes(10 * $index),
            ]);
        };

        // 1 Berita: Spotlight Split Card (bukan full-width col-span-12 vertical raksasa)
        $createPost(1);
        $res1 = $this->get(route('landing.home'));
        $res1->assertOk();
        $res1->assertSee('Berita Uji Adaptif Ke-1');
        $res1->assertSee('grid-cols-1 lg:grid-cols-12 gap-5 lg:gap-7', false);
        $res1->assertDontSee('grid-cols-1 md:grid-cols-2 gap-6 items-stretch', false);
        $res1->assertSee('mt-8 text-center md:hidden', false);

        // 2 Berita: 2 Kolom Sejajar Identik (mencegah card kanan melar ekstrem)
        $createPost(2);
        $res2 = $this->get(route('landing.home'));
        $res2->assertOk();
        $res2->assertSee('Berita Uji Adaptif Ke-1');
        $res2->assertSee('Berita Uji Adaptif Ke-2');
        $res2->assertSee('grid-cols-1 md:grid-cols-2 gap-6 items-stretch', false);
        $res2->assertDontSee('lg:col-span-5 flex flex-col gap-5', false);
        $res2->assertSee('truncate min-w-0 flex-1', false);
        $res2->assertSee('aspect-[16/10] w-full rounded-lg overflow-hidden', false);
        $res2->assertSee('absolute inset-0 w-full h-full flex flex-col', false);

        // 3 Berita: Layout Editorial 1 Kiri Utama + 2 Stack Kanan
        $createPost(3);
        $res3 = $this->get(route('landing.home'));
        $res3->assertOk();
        $res3->assertSee('lg:col-span-7 flex', false);
        $res3->assertSee('lg:col-span-5 flex flex-col gap-5', false);
        $res3->assertSee('TA 2026/2027 Ganjil');

        // 4 Berita: 1 Spotlight Atas + 3 Grid Sejajar Bawah (tanpa slot kosong separuh baris)
        $createPost(4);
        $res4 = $this->get(route('landing.home'));
        $res4->assertOk();
        $res4->assertSee('grid-cols-1 md:grid-cols-3 gap-5 items-stretch', false);
        $res4->assertSee('Berita Uji Adaptif Ke-4');

        // 5 Berita: Layout Editorial 1 Kiri + 2 Stack Kanan + 2 Baris Tambahan
        $createPost(5);
        $res5 = $this->get(route('landing.home'));
        $res5->assertOk();
        $res5->assertSee('lg:col-span-7 flex', false);
        $res5->assertSee('lg:col-span-5 flex flex-col gap-5', false);
        $res5->assertSee('grid-cols-1 md:grid-cols-2 gap-5 mt-5 items-stretch', false);
        $res5->assertSee('Berita Uji Adaptif Ke-5');
    }

    public function test_landing_page_hides_accreditation_section_when_no_active_unit_has_accreditation(): void
    {
        // Unit bawaan pada setUp memiliki accreditation_grade = 'Unggul', kosongkan terlebih dahulu
        $this->unit->update(['accreditation_grade' => null]);

        // Unit non-aktif dengan akreditasi tetap tidak boleh tampil di publik
        Unit::create([
            'code' => 'NONAKTIF',
            'name' => 'Program Studi Nonaktif',
            'type' => UnitType::Prodi,
            'degree_level' => DegreeLevel::S1,
            'accreditation_grade' => 'Unggul',
            'is_active' => false,
        ]);

        $response = $this->get(route('landing.home'));

        $response->assertOk();
        $response->assertDontSee('Status Akreditasi Institusi &amp; Program Studi', false);
        $response->assertDontSee('Program Studi Nonaktif');
        $response->assertDontSee('#status-akreditasi', false);

        // Pastikan halaman publik lain (misal portal berita) juga tidak menampilkan menu Akreditasi
        $postsResponse = $this->get(route('public.posts.index'));
        $postsResponse->assertOk();
        $postsResponse->assertDontSee('#status-akreditasi', false);
    }

    public function test_landing_page_renders_accredited_units_with_status_badges_security_and_cache_invalidation(): void
    {
        $privateDoc = Document::create([
            'title' => 'SK Akreditasi Internal Rahasia',
            'unit_id' => $this->unit->id,
            'document_category_id' => $this->docCategory->id,
            'academic_year_id' => $this->academicYear->id,
            'document_number' => 'PRIV/2026/001',
            'visibility' => VisibilityLevel::Internal,
            'status' => DocumentStatus::Approved,
            'issued_date' => now()->toDateString(),
            'uploaded_by' => $this->user->id,
            'approved_at' => now(),
        ]);

        $publicDoc = Document::create([
            'title' => 'SK Akreditasi Prodi Terbuka',
            'unit_id' => $this->unit->id,
            'document_category_id' => $this->docCategory->id,
            'academic_year_id' => $this->academicYear->id,
            'document_number' => 'PUB/2026/009',
            'visibility' => VisibilityLevel::Public,
            'status' => DocumentStatus::Approved,
            'issued_date' => now()->toDateString(),
            'uploaded_by' => $this->user->id,
            'approved_at' => now(),
        ]);

        // 1. Unit Institusi (Rektorat) berstatus Aktif, bertaut ke dokumen Internal (tautan SK tidak boleh bocor)
        Unit::create([
            'code' => 'UNIVBI-PT',
            'name' => 'Universitas Bina Insan (Institusi)',
            'type' => UnitType::Rektorat,
            'accreditation_grade' => 'Unggul',
            'accreditation_body' => 'BAN-PT',
            'accreditation_sk_number' => '100/SK/BAN-PT/Akred/PT/XI/2026',
            'accreditation_expired_date' => now()->addYears(3),
            'accreditation_document_id' => $privateDoc->id,
            'is_assessor_access_active' => true,
            'assessor_passcode' => 'RAHASIA-ASESOR-999',
            'assessor_notes' => 'Catatan Rahasia Internal Asesor',
            'is_active' => true,
        ]);

        // 2. Unit Prodi dengan masa berlaku lewat -> wajib memunculkan badge "Masa Perpanjangan" & link SK Publik
        $this->unit->update([
            'accreditation_grade' => 'Baik Sekali',
            'accreditation_body' => 'LAM INFOKOM',
            'accreditation_sk_number' => '205/SK/LAM-INFOKOM/XI/2021',
            'accreditation_expired_date' => now()->subDays(10),
            'accreditation_document_id' => $publicDoc->id,
        ]);

        $response = $this->get(route('landing.home'));

        $response->assertOk();
        $response->assertSee('Status Akreditasi Institusi &amp; Program Studi', false);
        $response->assertSee('Akreditasi Perguruan Tinggi');
        $response->assertSee('Universitas Bina Insan (Institusi)');
        $response->assertSee('100/SK/BAN-PT/Akred/PT/XI/2026');
        $response->assertSee('205/SK/LAM-INFOKOM/XI/2021');
        $response->assertSee('LAM INFOKOM');
        $response->assertSee('Aktif');
        $response->assertSee('Masa Perpanjangan');
        $response->assertSee(route('document.verify.stream', $publicDoc->uuid), false);
        $response->assertSee('href="#status-akreditasi"', false);

        // Pastikan menu Akreditasi pada halaman /berita mengarah kembali ke beranda + anchor #status-akreditasi
        $postsResponse = $this->get(route('public.posts.index'));
        $postsResponse->assertOk();
        $postsResponse->assertSee('href="'.route('landing.home').'#status-akreditasi"', false);

        // Keamanan: dilarang membocorkan dokumen non-publik maupun passcode/notes asesor
        $response->assertDontSee($privateDoc->uuid);
        $response->assertDontSee('RAHASIA-ASESOR-999');
        $response->assertDontSee('Catatan Rahasia Internal Asesor');

        // Verifikasi invalidasi cache otomatis ketika unit diubah
        $this->unit->update(['accreditation_grade' => 'A']);
        $responseAfterUpdate = $this->get(route('landing.home'));
        $responseAfterUpdate->assertOk();
        $responseAfterUpdate->assertSee('2 Peringkat Unggul / A');
    }
}
