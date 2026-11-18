<?php

declare(strict_types=1);

namespace Tests\Feature\Filament\Pages;

use App\Enums\DegreeLevel;
use App\Enums\PostStatus;
use App\Enums\UnitType;
use App\Filament\Pages\Borang\BorangDocumentationPosts;
use App\Filament\Resources\Posts\Pages\ListPosts;
use App\Filament\Resources\Posts\PostResource;
use App\Models\AcademicYear;
use App\Models\Permission;
use App\Models\Post;
use App\Models\PostCategory;
use App\Models\Role;
use App\Models\Unit;
use App\Models\User;
use Filament\Tables\Enums\PaginationMode;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class BorangDocumentationPostsTest extends TestCase
{
    use LazilyRefreshDatabase;

    private Unit $unit;

    private PostCategory $category;

    private AcademicYear $academicYear;

    private User $adminUser;

    protected function setUp(): void
    {
        parent::setUp();

        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $this->unit = Unit::create([
            'code' => 'TI',
            'name' => 'Prodi Informatika',
            'slug' => 'prodi-informatika',
            'type' => UnitType::Prodi,
            'degree_level' => DegreeLevel::S1,
            'is_active' => true,
        ]);

        $this->category = PostCategory::create([
            'name' => 'Kegiatan Borang',
            'slug' => 'kegiatan-borang',
            'color' => 'blue',
            'is_active' => true,
        ]);

        $this->academicYear = AcademicYear::create([
            'name' => '2026/2027',
            'start_date' => '2026-09-01',
            'end_date' => '2027-08-31',
            'is_active' => true,
        ]);

        $permissions = [
            'view_any:post',
            'view:post',
            'create:post',
            'update:post',
            'delete:post',
            'post.moderate_humas',
            'borang.export_excel',
            'borang.download_bulk_zip',
        ];

        foreach ($permissions as $perm) {
            Permission::findOrCreate($perm, 'web');
        }

        $adminRole = Role::findOrCreate('super_admin', 'web');
        $adminRole->syncPermissions($permissions);

        $this->adminUser = User::factory()->create([
            'unit_id' => $this->unit->id,
            'is_active' => true,
        ]);
        $this->adminUser->assignRole($adminRole);
    }

    private function createPost(bool $isBorang, string $title): Post
    {
        return Post::create([
            'title' => $title,
            'slug' => str()->slug($title),
            'post_category_id' => $this->category->id,
            'unit_id' => $this->unit->id,
            'academic_year_id' => $this->academicYear->id,
            'author_id' => $this->adminUser->id,
            'excerpt' => 'Ringkasan kegiatan untuk uji akreditasi.',
            'content' => '<p>Uraian lengkap kegiatan untuk bukti dokumentasi akreditasi.</p>',
            'status' => PostStatus::Published,
            'is_borang' => $isBorang,
            'event_date' => now()->toDateString(),
            'published_at' => now(),
        ]);
    }

    public function test_borang_documentation_page_can_be_rendered_successfully(): void
    {
        $this->actingAs($this->adminUser);

        Livewire::test(BorangDocumentationPosts::class)->assertSuccessful();
    }

    public function test_borang_documentation_page_displays_only_posts_with_is_borang_true(): void
    {
        $this->actingAs($this->adminUser);

        $borangPost = $this->createPost(true, 'Workshop Penyusunan Kurikulum OBE untuk Borang');
        $generalPost = $this->createPost(false, 'Pengumuman Libur Nasional Kampus');

        Livewire::test(BorangDocumentationPosts::class)
            ->assertCanSeeTableRecords([$borangPost])
            ->assertCanNotSeeTableRecords([$generalPost]);
    }

    public function test_navigation_menu_contains_dokumentasi_borang_under_borang_akreditasi(): void
    {
        $this->actingAs($this->adminUser);

        $response = $this->get('/admin');
        $response->assertSuccessful();

        $response->assertSee('Bank Data Borang');
        $response->assertSee('Dokumentasi Borang');
    }

    public function test_actions_and_row_url_point_to_post_resource(): void
    {
        $this->actingAs($this->adminUser);

        $borangPost = $this->createPost(true, 'Rapat Asesmen Lapangan Bersama Asesor');

        $viewUrl = PostResource::getUrl('view', ['record' => $borangPost]);
        $editUrl = PostResource::getUrl('edit', ['record' => $borangPost]);

        $this->assertStringContainsString('/admin/posts/'.$borangPost->id, $viewUrl);
        $this->assertStringContainsString('/admin/posts/'.$borangPost->id.'/edit', $editUrl);
    }

    public function test_borang_documentation_page_does_not_have_redundant_is_borang_filter(): void
    {
        $this->actingAs($this->adminUser);

        $pageComponent = Livewire::test(BorangDocumentationPosts::class);
        $this->assertArrayNotHasKey('is_borang', $pageComponent->instance()->getTable()->getFilters());

        $mainPostResourceComponent = Livewire::test(ListPosts::class);
        $this->assertArrayHasKey('is_borang', $mainPostResourceComponent->instance()->getTable()->getFilters());
    }

    public function test_borang_documentation_posts_table_uses_simple_pagination_and_search_debounce(): void
    {
        $this->actingAs($this->adminUser);

        $borangPost = $this->createPost(true, 'Pengesahan Dokumen Akreditasi Prodi');

        $component = Livewire::test(BorangDocumentationPosts::class)
            ->assertSuccessful()
            ->searchTable('Akreditasi Prodi')
            ->assertCanSeeTableRecords([$borangPost]);

        $table = $component->instance()->getTable();
        $this->assertSame(PaginationMode::Simple, $table->getPaginationMode());
        $this->assertSame('500ms', $table->getSearchDebounce());
        $this->assertSame(10, $table->getDefaultPaginationPageOption());
    }
}
