<?php

declare(strict_types=1);

namespace Tests\Feature\Public;

use App\Enums\DegreeLevel;
use App\Enums\DocumentStatus;
use App\Enums\PostPeriod;
use App\Enums\PostStatus;
use App\Enums\StampingPosition;
use App\Enums\StorageDisk;
use App\Enums\UnitType;
use App\Enums\VisibilityLevel;
use App\Models\AcademicYear;
use App\Models\Document;
use App\Models\DocumentCategory;
use App\Models\DocumentFile;
use App\Models\Post;
use App\Models\PostCategory;
use App\Models\PostPhoto;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PostPublicViewerTest extends TestCase
{
    use LazilyRefreshDatabase;

    private Unit $unit;

    private PostCategory $catBerita;

    private PostCategory $catPrestasi;

    private AcademicYear $academicYear;

    private User $author;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        $this->unit = Unit::create([
            'code' => 'TI',
            'name' => 'Program Studi Informatika',
            'slug' => 'program-studi-informatika',
            'type' => UnitType::Prodi,
            'degree_level' => DegreeLevel::S1,
            'is_active' => true,
        ]);

        $this->catBerita = PostCategory::create([
            'name' => 'Berita Kegiatan',
            'slug' => 'berita-kegiatan',
            'color' => 'blue',
            'is_active' => true,
            'order_index' => 1,
        ]);

        $this->catPrestasi = PostCategory::create([
            'name' => 'Prestasi Mahasiswa',
            'slug' => 'prestasi-mahasiswa',
            'color' => 'emerald',
            'is_active' => true,
            'order_index' => 2,
        ]);

        $this->academicYear = AcademicYear::create([
            'code' => '2026/2027-1',
            'name' => '2026/2027 Ganjil',
            'is_active' => true,
        ]);

        $this->author = User::factory()->create([
            'unit_id' => $this->unit->id,
            'is_active' => true,
        ]);
    }

    public function test_public_can_view_published_posts_index(): void
    {
        $publishedPost = Post::create([
            'title' => 'Kuliah Umum Kecerdasan Buatan Bersama Industri',
            'slug' => 'kuliah-umum-kecerdasan-buatan-bersama-industri',
            'post_category_id' => $this->catBerita->id,
            'unit_id' => $this->unit->id,
            'academic_year_id' => $this->academicYear->id,
            'author_id' => $this->author->id,
            'content' => '<p>Kuliah umum sukses dihadiri 200 mahasiswa.</p>',
            'status' => PostStatus::Published,
            'published_at' => now()->subDay(),
        ]);

        $draftPost = Post::create([
            'title' => 'Draf Berita Rahasia yang Belum Disahkan',
            'slug' => 'draf-berita-rahasia-yang-belum-disahkan',
            'post_category_id' => $this->catBerita->id,
            'unit_id' => $this->unit->id,
            'academic_year_id' => $this->academicYear->id,
            'author_id' => $this->author->id,
            'content' => '<p>Draf ini tidak boleh tayang.</p>',
            'status' => PostStatus::Draft,
            'published_at' => null,
        ]);

        $response = $this->get(route('public.posts.index'));

        $response->assertOk()
            ->assertSee('Kuliah Umum Kecerdasan Buatan Bersama Industri')
            ->assertDontSee('Draf Berita Rahasia yang Belum Disahkan');
    }

    public function test_public_can_filter_posts_by_category(): void
    {
        $postBerita = Post::create([
            'title' => 'Kunjungan Industri ke Pabrik Semen Baturaja',
            'slug' => 'kunjungan-industri-ke-pabrik-semen-baturaja',
            'post_category_id' => $this->catBerita->id,
            'unit_id' => $this->unit->id,
            'author_id' => $this->author->id,
            'content' => '<p>Isi berita kegiatan...</p>',
            'status' => PostStatus::Published,
            'published_at' => now()->subDay(),
        ]);

        $postPrestasi = Post::create([
            'title' => 'Mahasiswa TI Raih Juara 1 Hackathon Nasional',
            'slug' => 'mahasiswa-ti-raih-juara-1-hackathon-nasional',
            'post_category_id' => $this->catPrestasi->id,
            'unit_id' => $this->unit->id,
            'author_id' => $this->author->id,
            'content' => '<p>Prestasi membanggakan mahasiswa...</p>',
            'status' => PostStatus::Published,
            'published_at' => now()->subDay(),
        ]);

        $response = $this->get(route('public.posts.index', ['kategori' => 'prestasi-mahasiswa']));

        $response->assertOk()
            ->assertSee('Mahasiswa TI Raih Juara 1 Hackathon Nasional')
            ->assertDontSee('Kunjungan Industri ke Pabrik Semen Baturaja');
    }

    public function test_public_can_search_posts_by_keyword(): void
    {
        Post::create([
            'title' => 'Seminar Nasional IoT dan Cyber Security',
            'slug' => 'seminar-nasional-iot-dan-cyber-security',
            'post_category_id' => $this->catBerita->id,
            'unit_id' => $this->unit->id,
            'author_id' => $this->author->id,
            'content' => '<p>Seminar membahas topik jaringan dan keamanan data.</p>',
            'status' => PostStatus::Published,
            'published_at' => now()->subDay(),
        ]);

        Post::create([
            'title' => 'Pelatihan Desain Grafis Mahasiswa Baru',
            'slug' => 'pelatihan-desain-grafis-mahasiswa-baru',
            'post_category_id' => $this->catBerita->id,
            'unit_id' => $this->unit->id,
            'author_id' => $this->author->id,
            'content' => '<p>Pelatihan Photoshop dan Illustrator.</p>',
            'status' => PostStatus::Published,
            'published_at' => now()->subDay(),
        ]);

        $response = $this->get(route('public.posts.index', ['q' => 'Cyber Security']));

        $response->assertOk()
            ->assertSee('Seminar Nasional IoT dan Cyber Security')
            ->assertDontSee('Pelatihan Desain Grafis Mahasiswa Baru');
    }

    public function test_public_can_view_post_detail_with_photo_gallery_and_linked_public_documents(): void
    {
        $docCategory = DocumentCategory::create([
            'code' => 'MOU',
            'name' => 'Nota Kesepahaman Bersama',
            'slug' => 'nota-kesepahaman-bersama',
            'stamping_position' => StampingPosition::TopRight,
            'requires_approval' => true,
            'is_active' => true,
        ]);

        $doc = Document::create([
            'title' => 'MoU Kerja Sama Magang Industri dengan PT Telkom',
            'document_number' => '001/UNIVBI-FTK/MOU/XI/2026',
            'unit_id' => $this->unit->id,
            'document_category_id' => $docCategory->id,
            'academic_year_id' => $this->academicYear->id,
            'uploaded_by' => $this->author->id,
            'issued_date' => now()->subDays(5)->toDateString(),
            'visibility' => VisibilityLevel::Public,
            'status' => DocumentStatus::Approved,
            'approved_at' => now()->subDays(4),
            'approved_by' => $this->author->id,
        ]);

        DocumentFile::create([
            'document_id' => $doc->id,
            'uploaded_by' => $this->author->id,
            'is_primary' => true,
            'original_file_name' => 'mou-telkom.pdf',
            'standardized_file_name' => '2026-MOU-001.pdf',
            'original_file_path' => 'documents/sample.pdf',
            'disk' => StorageDisk::Public,
            'mime_type' => 'application/pdf',
            'file_extension' => 'pdf',
            'file_size' => 12345,
            'is_active' => true,
        ]);

        $post = Post::create([
            'title' => 'Seremoni Penandatanganan MoU dengan PT Telkom',
            'slug' => 'seremoni-penandatanganan-mou-dengan-pt-telkom',
            'post_category_id' => $this->catBerita->id,
            'unit_id' => $this->unit->id,
            'author_id' => $this->author->id,
            'content' => '<p>Rektor menandatangani nota kesepahaman magang industri.</p>',
            'status' => PostStatus::Published,
            'published_at' => now()->subDay(),
            'allow_public_zip_download' => true,
        ]);

        // Hubungkan dokumen publik
        $post->documents()->attach($doc->id, [
            'custom_button_label' => 'Unduh Salinan Lembar MoU (.PDF)',
            'order_index' => 1,
        ]);

        // Simpan foto dokumentasi
        $photoPath = 'posts/photos/sample-foto.jpg';
        Storage::disk('public')->put($photoPath, 'fake-image-bytes');

        PostPhoto::create([
            'post_id' => $post->id,
            'original_file_name' => 'foto-penyerahan.jpg',
            'standardized_file_name' => '2026-TI-foto-penyerahan.jpg',
            'file_path' => $photoPath,
            'disk' => StorageDisk::Public,
            'caption' => 'Penyerahan cinderamata oleh Rektor',
            'mime_type' => 'image/jpeg',
            'file_extension' => 'jpg',
            'file_size' => 1024,
            'order_index' => 1,
            'is_cover' => true,
        ]);

        $response = $this->get(route('public.posts.show', $post->slug));

        $response->assertOk()
            ->assertSee('Seremoni Penandatanganan MoU dengan PT Telkom')
            ->assertSee('Penyerahan cinderamata oleh Rektor')
            ->assertSee('001/UNIVBI-FTK/MOU/XI/2026')
            ->assertSee('Unduh Salinan Lembar MoU (.PDF)')
            ->assertSee('Unduh Paket Foto (.ZIP)');
    }

    public function test_public_cannot_view_draft_or_unapproved_posts(): void
    {
        $draftPost = Post::create([
            'title' => 'Draf Kegiatan Belum Disetujui Humas',
            'slug' => 'draf-kegiatan-belum-disetujui-humas',
            'post_category_id' => $this->catBerita->id,
            'unit_id' => $this->unit->id,
            'author_id' => $this->author->id,
            'content' => '<p>Draf...</p>',
            'status' => PostStatus::PendingReview,
            'published_at' => null,
        ]);

        $response = $this->get(route('public.posts.show', $draftPost->slug));

        $response->assertNotFound();
    }

    public function test_public_can_download_zip_of_all_photos_when_enabled(): void
    {
        $post = Post::create([
            'title' => 'Festival Budaya dan Expo Kreatif Kampus',
            'slug' => 'festival-budaya-dan-expo-kreatif-kampus',
            'post_category_id' => $this->catBerita->id,
            'unit_id' => $this->unit->id,
            'author_id' => $this->author->id,
            'content' => '<p>Dokumentasi festival budaya nusantara.</p>',
            'status' => PostStatus::Published,
            'published_at' => now()->subDay(),
            'allow_public_zip_download' => true,
        ]);

        $photo1 = 'posts/photos/festival-01.jpg';
        $photo2 = 'posts/photos/festival-02.jpg';
        Storage::disk('public')->put($photo1, 'dummy-photo-1-bytes');
        Storage::disk('public')->put($photo2, 'dummy-photo-2-bytes');

        PostPhoto::create([
            'post_id' => $post->id,
            'original_file_name' => 'festival-01.jpg',
            'file_path' => $photo1,
            'caption' => 'Pawai Budaya Nusantara',
            'mime_type' => 'image/jpeg',
            'file_extension' => 'jpg',
            'file_size' => 2048,
            'order_index' => 1,
            'is_cover' => true,
        ]);

        PostPhoto::create([
            'post_id' => $post->id,
            'original_file_name' => 'festival-02.jpg',
            'file_path' => $photo2,
            'caption' => 'Pentas Tari Tradisional',
            'mime_type' => 'image/jpeg',
            'file_extension' => 'jpg',
            'file_size' => 2048,
            'order_index' => 2,
        ]);

        $response = $this->get(route('public.posts.download_photos', $post->slug));

        $response->assertOk();
        $this->assertEquals('application/zip', $response->headers->get('content-type'));

        $this->assertEquals(1, $post->fresh()->zip_downloads_count);
    }

    public function test_zip_download_is_blocked_when_allow_public_zip_download_is_false(): void
    {
        $post = Post::create([
            'title' => 'Rapat Tertutup Senat Akademik Universitas',
            'slug' => 'rapat-tertutup-senat-akademik-universitas',
            'post_category_id' => $this->catBerita->id,
            'unit_id' => $this->unit->id,
            'author_id' => $this->author->id,
            'content' => '<p>Rapat senat akademik tahunan.</p>',
            'status' => PostStatus::Published,
            'published_at' => now()->subDay(),
            'allow_public_zip_download' => false,
        ]);

        $response = $this->get(route('public.posts.download_photos', $post->slug));

        $response->assertForbidden();
    }

    public function test_post_view_counter_increments_on_visit(): void
    {
        $post = Post::create([
            'title' => 'Peluncuran Inkubator Bisnis Mahasiswa',
            'slug' => 'peluncuran-inkubator-bisnis-mahasiswa',
            'post_category_id' => $this->catBerita->id,
            'unit_id' => $this->unit->id,
            'author_id' => $this->author->id,
            'content' => '<p>Inkubator bisnis resmi beroperasi.</p>',
            'status' => PostStatus::Published,
            'published_at' => now()->subDay(),
            'views_count' => 0,
        ]);

        $this->get(route('public.posts.show', $post->slug))->assertOk();
        $this->assertEquals(1, $post->fresh()->views_count);

        $this->get(route('public.posts.show', $post->slug))->assertOk();
        $this->assertEquals(2, $post->fresh()->views_count);
    }

    public function test_public_can_filter_posts_by_period_and_sees_period_badges(): void
    {
        $dailyPost = Post::create([
            'title' => 'Berita Harian Kampus Hari Ini',
            'slug' => 'berita-harian-kampus-hari-ini',
            'post_category_id' => $this->catBerita->id,
            'period' => PostPeriod::Daily,
            'unit_id' => $this->unit->id,
            'author_id' => $this->author->id,
            'content' => '<p>Konten harian.</p>',
            'status' => PostStatus::Published,
            'published_at' => now()->subHours(1),
        ]);

        $weeklyPost = Post::create([
            'title' => 'Rangkuman Pekan Akademik UNIVBI',
            'slug' => 'rangkuman-pekan-akademik-univbi',
            'post_category_id' => $this->catPrestasi->id,
            'period' => PostPeriod::Weekly,
            'unit_id' => $this->unit->id,
            'author_id' => $this->author->id,
            'content' => '<p>Konten mingguan.</p>',
            'status' => PostStatus::Published,
            'published_at' => now()->subHours(2),
        ]);

        $monthlyPost = Post::create([
            'title' => 'Buletin Capaian Bulanan LPPM',
            'slug' => 'buletin-capaian-bulanan-lppm',
            'post_category_id' => $this->catBerita->id,
            'period' => PostPeriod::Monthly,
            'unit_id' => $this->unit->id,
            'author_id' => $this->author->id,
            'content' => '<p>Konten bulanan.</p>',
            'status' => PostStatus::Published,
            'published_at' => now()->subHours(3),
        ]);

        // Filter edisi=weekly
        $this->get(route('public.posts.index', ['edisi' => PostPeriod::Weekly->value]))
            ->assertOk()
            ->assertSee($weeklyPost->title)
            ->assertDontSee($dailyPost->title)
            ->assertDontSee($monthlyPost->title);

        // Combine edisi=monthly & kategori=berita-kegiatan
        $this->get(route('public.posts.index', ['edisi' => PostPeriod::Monthly->value, 'kategori' => $this->catBerita->slug]))
            ->assertOk()
            ->assertSee($monthlyPost->title)
            ->assertDontSee($dailyPost->title)
            ->assertDontSee($weeklyPost->title);

        // Article detail & landing page display period badge
        $this->get(route('public.posts.show', $weeklyPost->slug))
            ->assertOk()
            ->assertSee('Berita Mingguan');

        $this->get(route('landing.home'))
            ->assertOk()
            ->assertSee('Harian');
    }

    public function test_featured_headline_card_displays_dense_metadata_and_gallery_thumbnails(): void
    {
        Storage::disk('public')->put('posts/photos/thumb-1.webp', 'IMG1');
        Storage::disk('public')->put('posts/photos/thumb-2.webp', 'IMG2');

        $featured = Post::create([
            'title' => 'Silahturahmi Rektor Pembahasan MoU Strategis',
            'slug' => 'silahturahmi-rektor-pembahasan-mou-strategis',
            'post_category_id' => $this->catBerita->id,
            'period' => PostPeriod::Daily,
            'unit_id' => $this->unit->id,
            'author_id' => $this->author->id,
            'excerpt' => 'Silahturahmi Rektor Pembahasan MoU Strategis',
            'content' => '<p>Pertemuan pimpinan membahas penguatan riset terapan dan magang mahasiswa bersama mitra strategis nasional.</p>',
            'event_date' => '2026-11-14',
            'event_location' => 'Ruang Rapat Rektorat Lt. 2',
            'status' => PostStatus::Published,
            'published_at' => now(),
            'is_featured' => true,
            'allow_public_zip_download' => true,
            'views_count' => 42,
        ]);

        PostPhoto::create([
            'post_id' => $featured->id,
            'uploaded_by' => $this->author->id,
            'file_path' => 'posts/photos/thumb-1.webp',
            'thumbnail_path' => 'posts/photos/thumb-1.webp',
            'disk' => StorageDisk::Public,
            'is_cover' => true,
            'order_index' => 1,
        ]);

        PostPhoto::create([
            'post_id' => $featured->id,
            'uploaded_by' => $this->author->id,
            'file_path' => 'posts/photos/thumb-2.webp',
            'thumbnail_path' => 'posts/photos/thumb-2.webp',
            'disk' => StorageDisk::Public,
            'is_cover' => false,
            'order_index' => 2,
        ]);

        $response = $this->get(route('public.posts.index'));

        $response->assertOk()
            ->assertSee('Pertemuan pimpinan membahas penguatan riset terapan')
            ->assertSee('Ruang Rapat Rektorat Lt. 2')
            ->assertSee($this->author->name)
            ->assertSee('42× dilihat')
            ->assertSee('mnt baca')
            ->assertSee('Unduh Foto')
            ->assertSee('thumb-2.webp');
    }

    public function test_post_detail_displays_related_sidebar_and_accessible_share_controls(): void
    {
        $mainPost = Post::create([
            'title' => 'Artikel Utama Kerja Sama Akademik',
            'slug' => 'artikel-utama-kerja-sama-akademik',
            'post_category_id' => $this->catBerita->id,
            'period' => PostPeriod::Daily,
            'unit_id' => $this->unit->id,
            'author_id' => $this->author->id,
            'excerpt' => 'Ringkasan penguatan kolaborasi lintas fakultas bersama industri.',
            'content' => '<p>Detail lengkap kerja sama akademik.</p>',
            'status' => PostStatus::Published,
            'published_at' => now(),
        ]);

        $sameCategoryPost = Post::create([
            'title' => 'Berita Sekategori Terkait Pertama',
            'slug' => 'berita-sekategori-terkait-pertama',
            'post_category_id' => $this->catBerita->id,
            'unit_id' => $this->unit->id,
            'author_id' => $this->author->id,
            'content' => '<p>Konten terkait 1.</p>',
            'status' => PostStatus::Published,
            'published_at' => now()->subHour(),
        ]);

        $otherCategoryPost = Post::create([
            'title' => 'Prestasi Lintas Kategori Sebagai Fallback',
            'slug' => 'prestasi-lintas-kategori-sebagai-fallback',
            'post_category_id' => $this->catPrestasi->id,
            'unit_id' => $this->unit->id,
            'author_id' => $this->author->id,
            'content' => '<p>Konten prestasi.</p>',
            'status' => PostStatus::Published,
            'published_at' => now()->subHours(2),
        ]);

        $response = $this->get(route('public.posts.show', $mainPost->slug));

        $response->assertOk()
            ->assertSee('Berita Terkait')
            ->assertSee($sameCategoryPost->title)
            ->assertSee($otherCategoryPost->title)
            ->assertSee('copyPostShareLink')
            ->assertSee('Salin Tautan')
            ->assertSee('Bagikan ke WhatsApp');
    }

    public function test_post_detail_displays_author_avatar_when_present_and_falls_back_to_initial_badge(): void
    {
        Storage::disk('public')->put('avatars/dr-andi.jpg', 'avatar contents');

        $authorWithAvatar = User::factory()->create([
            'name' => 'Dr. Andi Pratama',
            'avatar_url' => 'avatars/dr-andi.jpg',
            'unit_id' => $this->unit->id,
            'is_active' => true,
        ]);

        $postWithAvatar = Post::create([
            'title' => 'Riset Dosen Memenangkan Hibah Internasional',
            'slug' => 'riset-dosen-memenangkan-hibah-internasional',
            'post_category_id' => $this->catBerita->id,
            'unit_id' => $this->unit->id,
            'author_id' => $authorWithAvatar->id,
            'content' => '<p>Prestasi riset kelas dunia.</p>',
            'status' => PostStatus::Published,
            'published_at' => now(),
        ]);

        $responseWithAvatar = $this->get(route('public.posts.show', $postWithAvatar->slug));

        $responseWithAvatar->assertOk()
            ->assertSee('Dr. Andi Pratama')
            ->assertSee(Storage::disk('public')->url('avatars/dr-andi.jpg'));

        $authorWithoutAvatar = User::factory()->create([
            'name' => 'Budi Santoso',
            'avatar_url' => null,
            'unit_id' => $this->unit->id,
            'is_active' => true,
        ]);

        $postWithoutAvatar = Post::create([
            'title' => 'Kegiatan Pengabdian Masyarakat di Desa Binaan',
            'slug' => 'kegiatan-pengabdian-masyarakat-di-desa-binaan',
            'post_category_id' => $this->catBerita->id,
            'unit_id' => $this->unit->id,
            'author_id' => $authorWithoutAvatar->id,
            'content' => '<p>Pengabdian masyarakat berjalan lancar.</p>',
            'status' => PostStatus::Published,
            'published_at' => now(),
        ]);

        $responseWithoutAvatar = $this->get(route('public.posts.show', $postWithoutAvatar->slug));

        $responseWithoutAvatar->assertOk()
            ->assertSee('Budi Santoso')
            ->assertSee('B');
    }
}
