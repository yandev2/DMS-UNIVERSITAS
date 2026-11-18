<?php

declare(strict_types=1);

namespace Tests\Feature\Filament;

use App\Enums\DegreeLevel;
use App\Enums\DocumentStatus;
use App\Enums\PostPeriod;
use App\Enums\PostStatus;
use App\Enums\StampingPosition;
use App\Enums\UnitType;
use App\Enums\VisibilityLevel;
use App\Filament\Resources\Posts\Pages\CreatePost;
use App\Filament\Resources\Posts\Pages\EditPost;
use App\Filament\Resources\Posts\Pages\ListPosts;
use App\Filament\Resources\Posts\Pages\ViewPost;
use App\Filament\Widgets\PostPeriodOverviewWidget;
use App\Jobs\GeneratePostExportJob;
use App\Models\AcademicYear;
use App\Models\BulkExport;
use App\Models\Document;
use App\Models\DocumentCategory;
use App\Models\Permission;
use App\Models\Post;
use App\Models\PostCategory;
use App\Models\PostPhoto;
use App\Models\Role;
use App\Models\Unit;
use App\Models\User;
use App\Services\PostExportService;
use App\Services\PostQueuedExportService;
use Filament\Notifications\DatabaseNotification;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class PostResourceTest extends TestCase
{
    use LazilyRefreshDatabase;

    private Unit $unitA;

    private Unit $unitB;

    private PostCategory $category;

    private AcademicYear $academicYear;

    private Role $roleStaff;

    private Role $roleHumas;

    private Role $roleSuperAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $this->unitA = Unit::create([
            'code' => 'TI',
            'name' => 'Prodi Informatika',
            'slug' => 'prodi-informatika',
            'type' => UnitType::Prodi,
            'degree_level' => DegreeLevel::S1,
            'is_active' => true,
        ]);

        $this->unitB = Unit::create([
            'code' => 'SI',
            'name' => 'Prodi Sistem Informasi',
            'slug' => 'prodi-sistem-informasi',
            'type' => UnitType::Prodi,
            'degree_level' => DegreeLevel::S1,
            'is_active' => true,
        ]);

        $this->category = PostCategory::create([
            'name' => 'Warta Kampus',
            'slug' => 'warta-kampus',
            'color' => 'blue',
            'is_active' => true,
            'order_index' => 1,
        ]);

        $this->academicYear = AcademicYear::create([
            'code' => '2026/2027-1',
            'name' => '2026/2027 Ganjil',
            'is_active' => true,
        ]);

        // Setup permissions & roles
        $permissions = [
            'view_any:post',
            'view:post',
            'create:post',
            'update:post',
            'delete:post',
            'post.moderate_humas',
            'post.download_zip',
            'document.bypass_unit_scope',
        ];

        foreach ($permissions as $perm) {
            Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
        }

        $this->roleStaff = Role::firstOrCreate(['name' => 'staf_unit', 'guard_name' => 'web']);
        $this->roleStaff->syncPermissions(['view_any:post', 'view:post', 'create:post', 'update:post', 'delete:post']);

        $this->roleHumas = Role::firstOrCreate(['name' => 'humas', 'guard_name' => 'web']);
        $this->roleHumas->syncPermissions($permissions);

        $this->roleSuperAdmin = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        $this->roleSuperAdmin->syncPermissions($permissions);
    }

    public function test_post_resource_list_page_can_be_rendered(): void
    {
        $user = User::factory()->create([
            'unit_id' => $this->unitA->id,
            'is_active' => true,
        ]);
        $user->assignRole($this->roleStaff);

        $this->actingAs($user);

        Livewire::test(ListPosts::class)
            ->assertSuccessful();
    }

    public function test_unit_tenancy_scopes_posts_for_regular_staff(): void
    {
        $staffA = User::factory()->create([
            'unit_id' => $this->unitA->id,
            'is_active' => true,
        ]);
        $staffA->assignRole($this->roleStaff);

        $staffB = User::factory()->create([
            'unit_id' => $this->unitB->id,
            'is_active' => true,
        ]);
        $staffB->assignRole($this->roleStaff);

        $postA = Post::create([
            'title' => 'Post Draf Milik Unit TI',
            'slug' => 'post-draf-milik-unit-ti',
            'post_category_id' => $this->category->id,
            'unit_id' => $this->unitA->id,
            'author_id' => $staffA->id,
            'content' => '<p>Konten TI</p>',
            'status' => PostStatus::Draft,
        ]);

        $postB = Post::create([
            'title' => 'Post Draf Milik Unit SI',
            'slug' => 'post-draf-milik-unit-si',
            'post_category_id' => $this->category->id,
            'unit_id' => $this->unitB->id,
            'author_id' => $staffB->id,
            'content' => '<p>Konten SI</p>',
            'status' => PostStatus::Draft,
        ]);

        $this->actingAs($staffA);

        Livewire::test(ListPosts::class)
            ->assertCanSeeTableRecords([$postA])
            ->assertCanNotSeeTableRecords([$postB]);
    }

    public function test_humas_can_see_all_units_posts_and_tabs(): void
    {
        $humasUser = User::factory()->create([
            'unit_id' => $this->unitA->id,
            'is_active' => true,
        ]);
        $humasUser->assignRole($this->roleHumas);

        $postA = Post::create([
            'title' => 'Post Pending Unit TI',
            'slug' => 'post-pending-unit-ti',
            'post_category_id' => $this->category->id,
            'unit_id' => $this->unitA->id,
            'author_id' => $humasUser->id,
            'content' => '<p>Konten</p>',
            'status' => PostStatus::PendingReview,
        ]);

        $postB = Post::create([
            'title' => 'Post Pending Unit SI',
            'slug' => 'post-pending-unit-si',
            'post_category_id' => $this->category->id,
            'unit_id' => $this->unitB->id,
            'author_id' => $humasUser->id,
            'content' => '<p>Konten</p>',
            'status' => PostStatus::PendingReview,
        ]);

        $this->actingAs($humasUser);

        Livewire::test(ListPosts::class)
            ->assertCanSeeTableRecords([$postA, $postB]);
    }

    public function test_can_view_post_record(): void
    {
        $user = User::factory()->create([
            'unit_id' => $this->unitA->id,
            'is_active' => true,
        ]);
        $user->assignRole($this->roleStaff);

        $post = Post::create([
            'title' => 'Liputan Workshop Kurikulum',
            'slug' => 'liputan-workshop-kurikulum',
            'post_category_id' => $this->category->id,
            'unit_id' => $this->unitA->id,
            'author_id' => $user->id,
            'content' => '<p>Workshop OBE.</p>',
            'status' => PostStatus::Draft,
        ]);

        $this->actingAs($user);

        Livewire::test(ViewPost::class, ['record' => $post->getKey()])
            ->assertSuccessful();
    }

    public function test_can_edit_post_record(): void
    {
        $user = User::factory()->create([
            'unit_id' => $this->unitA->id,
            'is_active' => true,
        ]);
        $user->assignRole($this->roleStaff);

        $post = Post::create([
            'title' => 'Judul Sebelum Diedit',
            'slug' => 'judul-sebelum-diedit',
            'post_category_id' => $this->category->id,
            'unit_id' => $this->unitA->id,
            'author_id' => $user->id,
            'content' => '<p>Konten lama.</p>',
            'status' => PostStatus::Draft,
        ]);

        $this->actingAs($user);

        Livewire::test(EditPost::class, ['record' => $post->getKey()])
            ->fillForm([
                'title' => 'Judul Baru Setelah Diedit',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertEquals('Judul Baru Setelah Diedit', $post->fresh()->title);
    }

    public function test_post_form_strictly_rejects_non_public_or_unapproved_documents(): void
    {
        $user = User::factory()->create([
            'unit_id' => $this->unitA->id,
            'is_active' => true,
        ]);
        $user->assignRole($this->roleStaff);

        $docCategory = DocumentCategory::create([
            'code' => 'CONF',
            'name' => 'Dokumen Rahasia',
            'slug' => 'dokumen-rahasia',
            'stamping_position' => StampingPosition::TopRight,
            'requires_approval' => true,
            'is_active' => true,
        ]);

        $confidentialDoc = Document::create([
            'title' => 'Berkas Nilai dan Rekap Rahasia',
            'document_number' => '009/CONF/2026',
            'unit_id' => $this->unitA->id,
            'document_category_id' => $docCategory->id,
            'academic_year_id' => $this->academicYear->id,
            'uploaded_by' => $user->id,
            'issued_date' => now()->toDateString(),
            'visibility' => VisibilityLevel::Confidential,
            'status' => DocumentStatus::Approved,
        ]);

        $unapprovedPublicDoc = Document::create([
            'title' => 'SK Draf Belum Disahkan',
            'document_number' => null,
            'unit_id' => $this->unitA->id,
            'document_category_id' => $docCategory->id,
            'academic_year_id' => $this->academicYear->id,
            'uploaded_by' => $user->id,
            'issued_date' => now()->toDateString(),
            'visibility' => VisibilityLevel::Public,
            'status' => DocumentStatus::Draft,
        ]);

        $post = Post::create([
            'title' => 'Liputan Kegiatan Seminar',
            'slug' => 'liputan-kegiatan-seminar',
            'post_category_id' => $this->category->id,
            'unit_id' => $this->unitA->id,
            'author_id' => $user->id,
            'content' => '<p>Seminar.</p>',
            'status' => PostStatus::Draft,
        ]);

        $this->actingAs($user);

        // Uji penautan dokumen Confidential (Harus Gagal Validasi)
        Livewire::test(EditPost::class, ['record' => $post->getKey()])
            ->fillForm([
                'postDocuments' => [
                    [
                        'document_id' => $confidentialDoc->id,
                        'custom_button_label' => 'Tautan Rahasia',
                    ],
                ],
            ])
            ->call('save')
            ->assertHasFormErrors(['postDocuments.0.document_id']);

        // Uji penautan dokumen Draf Belum Disahkan (Harus Gagal Validasi)
        Livewire::test(EditPost::class, ['record' => $post->getKey()])
            ->fillForm([
                'postDocuments' => [
                    [
                        'document_id' => $unapprovedPublicDoc->id,
                        'custom_button_label' => 'Tautan Draf',
                    ],
                ],
            ])
            ->call('save')
            ->assertHasFormErrors(['postDocuments.0.document_id']);
    }

    public function test_can_toggle_is_borang_field_on_post(): void
    {
        $user = User::factory()->create([
            'unit_id' => $this->unitA->id,
            'is_active' => true,
        ]);
        $user->assignRole($this->roleStaff);

        $post = Post::create([
            'title' => 'Dokumentasi Akreditasi LAM INFOKOM',
            'slug' => 'dokumentasi-akreditasi-lam-infokom',
            'post_category_id' => $this->category->id,
            'unit_id' => $this->unitA->id,
            'author_id' => $user->id,
            'content' => '<p>Dokumentasi akreditasi prodi.</p>',
            'status' => PostStatus::Draft,
            'is_borang' => false,
        ]);

        $this->actingAs($user);

        Livewire::test(EditPost::class, ['record' => $post->getKey()])
            ->fillForm([
                'is_borang' => true,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertTrue($post->refresh()->is_borang);
    }

    public function test_view_post_renders_informative_layout_with_banner_and_tabs(): void
    {
        $user = User::factory()->create([
            'unit_id' => $this->unitA->id,
            'is_active' => true,
        ]);
        $user->assignRole($this->roleStaff);

        $post = Post::create([
            'title' => 'Mahasiswa S1 Informatika UNIVBI Raih Juara 1 Hackathon',
            'slug' => 'mahasiswa-s1-informatika-univbi-raih-juara-1-hackathon',
            'post_category_id' => $this->category->id,
            'unit_id' => $this->unitA->id,
            'author_id' => $user->id,
            'academic_year_id' => $this->academicYear->id,
            'event_date' => now()->toDateString(),
            'event_location' => 'Auditorium Kampus Utama',
            'excerpt' => 'Tim mahasiswa prodi informatika sukses membawa pulang piala juara pertama.',
            'content' => '<p>Artikel lengkap prestasi mahasiswa dalam kompetisi nasional 2026.</p>',
            'status' => PostStatus::Published,
            'is_featured' => true,
            'is_pinned' => true,
            'is_borang' => true,
            'published_at' => now(),
        ]);

        PostPhoto::create([
            'post_id' => $post->id,
            'uploaded_by' => $user->id,
            'file_path' => 'posts/photos/sample.jpg',
            'caption' => 'Sesi penyerahan trofi juara hackathon',
            'is_cover' => true,
        ]);

        $this->actingAs($user);

        Livewire::test(ViewPost::class, ['record' => $post->getKey()])
            ->assertSuccessful()
            ->assertSee('Mahasiswa S1 Informatika UNIVBI Raih Juara 1 Hackathon')
            ->assertSee('Tim mahasiswa prodi informatika sukses membawa pulang piala juara pertama.')
            ->assertSee('Artikel lengkap prestasi mahasiswa dalam kompetisi nasional 2026.')
            ->assertSee('BERITA UTAMA')
            ->assertSee('DISEMATKAN')
            ->assertSee('BUKTI BORANG')
            ->assertSee('Sesi penyerahan trofi juara hackathon')
            ->assertSee('Naskah Berita & Informasi Lengkap')
            ->assertSee('Galeri Multi-Foto Dokumentasi')
            ->assertSee('Tautan Naskah Dinas Sah Terkait')
            ->assertSee('Riwayat & Log Moderasi Humas');
    }

    public function test_author_can_submit_post_for_review_from_view_page(): void
    {
        $user = User::factory()->create([
            'unit_id' => $this->unitA->id,
            'is_active' => true,
        ]);
        $user->assignRole($this->roleStaff);

        $post = Post::create([
            'title' => 'Draf Berita Milik Penulis',
            'slug' => 'draf-berita-milik-penulis',
            'post_category_id' => $this->category->id,
            'unit_id' => $this->unitA->id,
            'author_id' => $user->id,
            'content' => '<p>Draf naskah berita.</p>',
            'status' => PostStatus::Draft,
        ]);

        $this->actingAs($user);

        Livewire::test(ViewPost::class, ['record' => $post->getKey()])
            ->callAction('submit_review')
            ->assertHasNoActionErrors();

        $post->refresh();
        $this->assertEquals(PostStatus::PendingReview, $post->status);
        $this->assertNotNull($post->submitted_at);
    }

    public function test_humas_can_publish_post_from_view_page(): void
    {
        $humas = User::factory()->create([
            'unit_id' => $this->unitA->id,
            'is_active' => true,
        ]);
        $humas->assignRole($this->roleHumas);

        $post = Post::create([
            'title' => 'Naskah Menunggu Persetujuan Humas',
            'slug' => 'naskah-menunggu-persetujuan-humas',
            'post_category_id' => $this->category->id,
            'unit_id' => $this->unitA->id,
            'author_id' => $humas->id,
            'content' => '<p>Naskah menunggu persetujuan.</p>',
            'status' => PostStatus::PendingReview,
            'submitted_at' => now()->subHour(),
        ]);

        $this->actingAs($humas);

        Livewire::test(ViewPost::class, ['record' => $post->getKey()])
            ->callAction('publish')
            ->assertHasNoActionErrors();

        $post->refresh();
        $this->assertEquals(PostStatus::Published, $post->status);
        $this->assertEquals($humas->id, $post->approved_by);
        $this->assertNotNull($post->approved_at);
        $this->assertNotNull($post->published_at);
    }

    public function test_humas_can_request_revision_with_note_from_view_page(): void
    {
        $humas = User::factory()->create([
            'unit_id' => $this->unitA->id,
            'is_active' => true,
        ]);
        $humas->assignRole($this->roleHumas);

        $post = Post::create([
            'title' => 'Naskah Perlu Perbaikan',
            'slug' => 'naskah-perlu-perbaikan',
            'post_category_id' => $this->category->id,
            'unit_id' => $this->unitA->id,
            'author_id' => $humas->id,
            'content' => '<p>Naskah draft perbaikan.</p>',
            'status' => PostStatus::PendingReview,
            'submitted_at' => now()->subHour(),
        ]);

        $this->actingAs($humas);

        Livewire::test(ViewPost::class, ['record' => $post->getKey()])
            ->callAction('request_revision', [
                'moderation_note' => 'Mohon tambahkan daftar nama dosen pembimbing kegiatan.',
            ])
            ->assertHasNoActionErrors();

        $post->refresh();
        $this->assertEquals(PostStatus::Revision, $post->status);
        $this->assertEquals('Mohon tambahkan daftar nama dosen pembimbing kegiatan.', $post->moderation_note);
    }

    public function test_post_document_select_uses_lazy_search_without_preload_and_filters_public_approved(): void
    {
        $user = User::factory()->create([
            'unit_id' => $this->unitA->id,
            'is_active' => true,
        ]);
        $user->assignRole($this->roleStaff);

        $docCategory = DocumentCategory::create([
            'code' => 'SE_PUB',
            'name' => 'Surat Edaran Publik',
            'slug' => 'surat-edaran-publik',
            'requires_approval' => false,
            'is_active' => true,
        ]);

        $publicApproved = Document::create([
            'title' => 'Edaran Wisuda Tahun 2026',
            'document_number' => '801/UNIVBI.TI/SE/XI/2026',
            'unit_id' => $this->unitA->id,
            'document_category_id' => $docCategory->id,
            'academic_year_id' => $this->academicYear->id,
            'uploaded_by' => $user->id,
            'issued_date' => now()->toDateString(),
            'visibility' => VisibilityLevel::Public,
            'status' => DocumentStatus::Approved,
        ]);

        $confidentialApproved = Document::create([
            'title' => 'Edaran Rahasia Internal 2026',
            'document_number' => '802/UNIVBI.TI/SE/XI/2026',
            'unit_id' => $this->unitA->id,
            'document_category_id' => $docCategory->id,
            'academic_year_id' => $this->academicYear->id,
            'uploaded_by' => $user->id,
            'issued_date' => now()->toDateString(),
            'visibility' => VisibilityLevel::Confidential,
            'status' => DocumentStatus::Approved,
        ]);

        $post = Post::create([
            'title' => 'Berita Persiapan Wisuda',
            'slug' => 'berita-persiapan-wisuda',
            'post_category_id' => $this->category->id,
            'unit_id' => $this->unitA->id,
            'author_id' => $user->id,
            'content' => '<p>Wisuda.</p>',
            'status' => PostStatus::Draft,
        ]);

        $this->actingAs($user);

        $editPost = Livewire::test(EditPost::class, ['record' => $post->getKey()])
            ->fillForm([
                'postDocuments' => [
                    [
                        'document_id' => $publicApproved->id,
                        'custom_button_label' => 'Unduh Edaran',
                    ],
                ],
            ]);

        $docSelect = $editPost->instance()->form->getFlatFields(withHidden: true)['postDocuments.0.document_id'];

        $this->assertFalse($docSelect->isPreloaded());
        $this->assertSame(25, $docSelect->getOptionsLimit());

        $results = $docSelect->getSearchResults('Edaran');
        $this->assertArrayHasKey($publicApproved->id, $results);
        $this->assertArrayNotHasKey($confidentialApproved->id, $results);
        $this->assertStringContainsString('801/UNIVBI.TI/SE/XI/2026', $results[$publicApproved->id]);
    }

    public function test_post_period_daily_weekly_monthly_form_tabs_filter_widget_and_export_work_cleanly(): void
    {
        $humas = User::factory()->create(['unit_id' => $this->unitA->id, 'is_active' => true]);
        $humas->assignRole($this->roleHumas);

        $this->actingAs($humas);

        // 1. Default period on create form is Daily, can create Weekly & Monthly explicitly
        Livewire::test(CreatePost::class)
            ->fillForm([
                'title' => 'Buletin Riset & Pengabdian Bulanan',
                'slug' => 'buletin-riset-pengabdian-bulanan',
                'post_category_id' => $this->category->id,
                'period' => PostPeriod::Monthly->value,
                'unit_id' => $this->unitA->id,
                'content' => '<p>Rekap riset bulan ini.</p>',
                'status' => PostStatus::Published->value,
                'published_at' => now()->toDateTimeString(),
                'photos' => [],
                'postDocuments' => [],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $monthlyPost = Post::where('slug', 'buletin-riset-pengabdian-bulanan')->firstOrFail();
        $this->assertSame(PostPeriod::Monthly, $monthlyPost->period);

        $dailyPost = Post::create([
            'title' => 'Liputan Seminar Harian',
            'slug' => 'liputan-seminar-harian',
            'post_category_id' => $this->category->id,
            'unit_id' => $this->unitA->id,
            'author_id' => $humas->id,
            'content' => '<p>Berita harian.</p>',
            'status' => PostStatus::Published,
            'published_at' => now()->subHours(2),
        ]);
        $this->assertSame(PostPeriod::Daily, $dailyPost->fresh()->period);

        $weeklyPost = Post::create([
            'title' => 'Sorotan Kampus Mingguan',
            'slug' => 'sorotan-kampus-mingguan',
            'post_category_id' => $this->category->id,
            'period' => PostPeriod::Weekly,
            'unit_id' => $this->unitA->id,
            'author_id' => $humas->id,
            'content' => '<p>Berita mingguan.</p>',
            'status' => PostStatus::PendingReview,
        ]);

        // 2. Tabs & SelectFilter on ListPosts isolate periods accurately
        Livewire::test(ListPosts::class)
            ->assertSuccessful()
            ->set('activeTab', 'weekly')
            ->assertCanSeeTableRecords([$weeklyPost])
            ->assertCanNotSeeTableRecords([$dailyPost, $monthlyPost])
            ->set('activeTab', 'all')
            ->filterTable('period', PostPeriod::Monthly->value)
            ->assertCanSeeTableRecords([$monthlyPost])
            ->assertCanNotSeeTableRecords([$dailyPost, $weeklyPost]);

        // 3. ViewPost displays period badge
        Livewire::test(ViewPost::class, ['record' => $weeklyPost->getRouteKey()])
            ->assertSuccessful()
            ->assertSee('BERITA MINGGUAN');

        // 4. Widget renders accurate period summary cards
        Livewire::test(PostPeriodOverviewWidget::class)
            ->assertSuccessful()
            ->assertSee('Berita Harian')
            ->assertSee('Berita Mingguan')
            ->assertSee('Berita Bulanan')
            ->assertSee('Tayang Bulan Ini');

        // 5. PostExportService generates clean Excel workbook from filtered query
        $spreadsheet = app(PostExportService::class)->buildSpreadsheetFromQuery(
            Post::query()->where('period', PostPeriod::Monthly),
            PostPeriod::Monthly
        );
        $this->assertSame('Rekapitulasi Berita', $spreadsheet->getSheet(0)->getTitle());
        $this->assertSame('Berita Bulanan', $spreadsheet->getSheet(0)->getCell('B5')->getValue());
        $this->assertSame('Buletin Riset & Pengabdian Bulanan', $spreadsheet->getSheet(0)->getCell('D5')->getValue());
    }

    public function test_export_excel_always_dispatches_to_background_queue_with_one_day_retention(): void
    {
        Queue::fake();

        $humas = User::factory()->create(['unit_id' => $this->unitA->id, 'is_active' => true]);
        $humas->assignRole($this->roleHumas);

        Post::create([
            'title' => 'Laporan Bulanan Capaian Kinerja',
            'slug' => 'laporan-bulanan-capaian-kinerja',
            'post_category_id' => $this->category->id,
            'period' => PostPeriod::Monthly,
            'unit_id' => $this->unitA->id,
            'author_id' => $humas->id,
            'content' => '<p>Konten laporan bulanan.</p>',
            'status' => PostStatus::Published,
            'published_at' => now()->subDay(),
        ]);

        $this->actingAs($humas);

        Livewire::test(ListPosts::class)
            ->set('activeTab', 'monthly')
            ->set('tableSearch', 'Capaian Kinerja')
            ->callAction('exportExcel')
            ->assertNotified('Ekspor Rekapitulasi Berita Sedang Diproses');

        Queue::assertPushed(GeneratePostExportJob::class, function (GeneratePostExportJob $job) use ($humas): bool {
            $export = $job->bulkExport;
            $hoursUntilExpiry = now()->diffInHours($export->expires_at, false);

            return $export->user_id === $humas->id
                && $export->module_type === 'posts_excel'
                && $export->status === 'pending'
                && $export->disk === 'private'
                && ($export->record_ids['active_tab'] ?? null) === 'monthly'
                && ($export->record_ids['search'] ?? null) === 'Capaian Kinerja'
                && $hoursUntilExpiry >= 23 && $hoursUntilExpiry <= 24;
        });
    }

    public function test_queued_post_export_service_generates_xlsx_notifies_user_and_enforces_one_day_download(): void
    {
        Notification::fake();
        Storage::fake('private');

        $humas = User::factory()->create(['unit_id' => $this->unitA->id, 'is_active' => true]);
        $humas->assignRole($this->roleHumas);

        $otherUserWithoutPermission = User::factory()->create(['unit_id' => $this->unitB->id, 'is_active' => true]);

        Post::create([
            'title' => 'Warta Mingguan Riset Fakultas',
            'slug' => 'warta-mingguan-riset-fakultas',
            'post_category_id' => $this->category->id,
            'period' => PostPeriod::Weekly,
            'unit_id' => $this->unitA->id,
            'author_id' => $humas->id,
            'content' => '<p>Berita mingguan.</p>',
            'status' => PostStatus::Published,
            'published_at' => now()->subHour(),
        ]);

        Post::create([
            'title' => 'Warta Harian Lainnya',
            'slug' => 'warta-harian-lainnya',
            'post_category_id' => $this->category->id,
            'period' => PostPeriod::Daily,
            'unit_id' => $this->unitA->id,
            'author_id' => $humas->id,
            'content' => '<p>Berita harian.</p>',
            'status' => PostStatus::Published,
            'published_at' => now()->subHour(),
        ]);

        $bulkExport = BulkExport::create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $humas->id,
            'module_type' => 'posts_excel',
            'record_ids' => [
                'user_id' => $humas->id,
                'active_tab' => 'weekly',
                'period' => PostPeriod::Weekly->value,
            ],
            'total_files' => 0,
            'zip_file_name' => 'Rekapitulasi-Berita-WEEKLY.xlsx',
            'disk' => 'private',
            'status' => 'pending',
            'expires_at' => now()->addDay(),
        ]);

        $processed = app(PostQueuedExportService::class)->process($bulkExport);

        $this->assertTrue($processed);
        $bulkExport->refresh();

        $this->assertSame('completed', $bulkExport->status);
        $this->assertSame(1, $bulkExport->total_files);
        $this->assertGreaterThan(0, $bulkExport->file_size_bytes);
        $this->assertNotNull($bulkExport->zip_file_path);
        Storage::disk('private')->assertExists($bulkExport->zip_file_path);

        Notification::assertSentTo($humas, DatabaseNotification::class);

        // Pembuat yang berwenang dapat mengunduh berkas Excel melalui route aman
        $this->actingAs($humas)
            ->get($bulkExport->getDownloadUrl())
            ->assertOk()
            ->assertHeader('content-disposition', 'attachment; filename=Rekapitulasi-Berita-WEEKLY.xlsx');

        // Pengguna tanpa hak akses ditolak (403)
        $this->actingAs($otherUserWithoutPermission)
            ->get($bulkExport->getDownloadUrl())
            ->assertForbidden();

        // Tautan yang melewati masa retensi 1 hari (24 jam) ditolak (410)
        $bulkExport->update(['expires_at' => now()->subMinute()]);
        $this->actingAs($humas)
            ->get($bulkExport->getDownloadUrl())
            ->assertStatus(410);
    }

    public function test_post_stats_cache_invalidates_on_save_delete_and_scalability_indexes_exist(): void
    {
        config(['cache.default' => 'database']);

        $initialVersion = Post::getStatsCacheVersion();

        $author = User::factory()->create(['unit_id' => $this->unitA->id, 'is_active' => true]);
        $author->assignRole($this->roleHumas);

        $post = Post::create([
            'title' => 'Uji Invalidasi Cache Statistik Berita',
            'slug' => 'uji-invalidasi-cache-statistik-berita',
            'post_category_id' => $this->category->id,
            'period' => PostPeriod::Daily,
            'unit_id' => $this->unitA->id,
            'author_id' => $author->id,
            'content' => '<p>Konten berita.</p>',
            'status' => PostStatus::Published,
            'published_at' => now(),
        ]);

        $versionAfterCreate = Post::getStatsCacheVersion();
        $this->assertGreaterThan($initialVersion, $versionAfterCreate);

        // Render widget dua kali dengan driver cache database (memastikan cache hit tanpa error __PHP_Incomplete_Class)
        $this->actingAs($author);
        Livewire::test(PostPeriodOverviewWidget::class)
            ->assertSuccessful()
            ->assertSee('Total 1');

        Livewire::test(PostPeriodOverviewWidget::class)
            ->assertSuccessful()
            ->assertSee('Total 1');

        $post->delete();
        $versionAfterDelete = Post::getStatsCacheVersion();
        $this->assertGreaterThan($versionAfterCreate, $versionAfterDelete);

        Livewire::test(PostPeriodOverviewWidget::class)
            ->assertSuccessful()
            ->assertSee('Total 0');

        $this->assertTrue(Schema::hasIndex('posts', 'idx_posts_created_at'));
        $this->assertTrue(Schema::hasIndex('posts', 'idx_posts_period_created'));
        $this->assertTrue(Schema::hasIndex('posts', 'idx_posts_status_created'));
        $this->assertTrue(Schema::hasIndex('posts', 'idx_posts_unit_created'));
        $this->assertTrue(Schema::hasIndex('posts', 'idx_posts_category_created'));
    }
}
