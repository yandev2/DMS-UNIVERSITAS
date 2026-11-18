<?php

declare(strict_types=1);

namespace Tests\Feature\Filament;

use App\Enums\PostStatus;
use App\Filament\Pages\AccreditationDataBank;
use App\Filament\Pages\ManageLandingPageSettings;
use App\Filament\Resources\Documents\DocumentResource;
use App\Filament\Resources\Documents\Pages\CreateDocument;
use App\Filament\Resources\Documents\Pages\ListDocuments;
use App\Filament\Resources\IncomingLetters\IncomingLetterResource;
use App\Filament\Resources\OutgoingLetters\OutgoingLetterResource;
use App\Filament\Resources\Posts\Pages\ViewPost;
use App\Filament\Widgets\AnnualPublishedDocumentChartWidget;
use App\Filament\Widgets\DashboardQuickActionsWidget;
use App\Filament\Widgets\DashboardWelcomeBannerWidget;
use App\Filament\Widgets\DocumentOverviewWidget;
use App\Filament\Widgets\PendingApprovalsWidget;
use App\Filament\Widgets\PostPeriodOverviewWidget;
use App\Models\IncomingLetter;
use App\Models\OutgoingLetter;
use App\Models\Permission;
use App\Models\Post;
use App\Models\PostCategory;
use App\Models\PostPhoto;
use App\Models\Role;
use App\Models\Unit;
use App\Models\User;
use Database\Seeders\MasterDataSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Tests\TestCase;

class ShieldPermissionAuditTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(MasterDataSeeder::class);
    }

    public function test_all_config_custom_permissions_are_seeded_in_database(): void
    {
        /** @var array<string, string> $configuredCustomPermissions */
        $configuredCustomPermissions = config('filament-shield.custom_permissions', []);

        $this->assertNotEmpty($configuredCustomPermissions);

        foreach (array_keys($configuredCustomPermissions) as $permissionName) {
            $this->assertDatabaseHas('permissions', [
                'name' => $permissionName,
                'guard_name' => 'web',
            ]);
        }
    }

    public function test_incoming_and_outgoing_letter_policies_are_registered_and_enforced(): void
    {
        $this->assertNotNull(Gate::getPolicyFor(IncomingLetter::class));
        $this->assertNotNull(Gate::getPolicyFor(OutgoingLetter::class));

        $humas = User::where('email', 'humas@univbi.ac.id')->firstOrFail();
        $stafTi = User::where('email', 'staf.ti@univbi.ac.id')->firstOrFail();

        // Humas dapat membuat surat masuk dan melihat seluruh surat masuk/keluar
        $this->actingAs($humas);
        $this->assertTrue(IncomingLetterResource::canViewAny());
        $this->assertTrue(IncomingLetterResource::canCreate());
        $this->assertTrue(OutgoingLetterResource::canViewAny());
        $this->assertTrue(OutgoingLetterResource::canCreate());

        // Staf unit dapat melihat daftar surat masuk (scoped) dan membuat surat keluar, tetapi TIDAK BOLEH membuat surat masuk
        $this->actingAs($stafTi);
        $this->assertTrue(IncomingLetterResource::canViewAny());
        $this->assertFalse(IncomingLetterResource::canCreate());
        $this->assertTrue(OutgoingLetterResource::canViewAny());
        $this->assertTrue(OutgoingLetterResource::canCreate());
    }

    public function test_all_12_roles_have_dashboard_widget_permissions(): void
    {
        $widgetPermissions = [
            'view:dashboard_welcome_banner_widget',
            'view:dashboard_quick_actions_widget',
            'view:document_overview_widget',
            'view:annual_published_document_chart_widget',
            'view:pending_approvals_widget',
            'view:pending_letters_alert_widget',
            'view:post_period_overview_widget',
        ];

        $roles = Role::all();
        $this->assertCount(12, $roles);

        foreach ($roles as $role) {
            foreach ($widgetPermissions as $perm) {
                $this->assertTrue(
                    $role->hasPermissionTo($perm),
                    "Role [{$role->name}] kehilangan permission widget [{$perm}]."
                );
            }
        }

        // Pastikan user non-super_admin (misal Kaprodi) dapat merender seluruh widget dashboard
        $kaprodi = User::where('email', 'kaprodi.ti@univbi.ac.id')->firstOrFail();
        $this->actingAs($kaprodi);

        $this->assertTrue(DashboardWelcomeBannerWidget::canView());
        $this->assertTrue(DashboardQuickActionsWidget::canView());
        $this->assertTrue(DocumentOverviewWidget::canView());
        $this->assertTrue(AnnualPublishedDocumentChartWidget::canView());
        $this->assertTrue(PendingApprovalsWidget::canView());
        $this->assertTrue(PostPeriodOverviewWidget::canView());
    }

    public function test_manage_landing_page_settings_allows_humas_and_blocks_regular_staff(): void
    {
        $humas = User::where('email', 'humas@univbi.ac.id')->firstOrFail();
        $rektor = User::where('email', 'rektor@univbi.ac.id')->firstOrFail();
        $stafTi = User::where('email', 'staf.ti@univbi.ac.id')->firstOrFail();

        $this->actingAs($humas);
        $this->assertTrue(ManageLandingPageSettings::canAccess());

        $this->actingAs($rektor);
        $this->assertTrue(ManageLandingPageSettings::canAccess());

        $this->actingAs($stafTi);
        $this->assertFalse(ManageLandingPageSettings::canAccess());
    }

    public function test_document_manual_numbering_field_respects_shield_permission(): void
    {
        $uptIct = User::where('email', 'ict@univbi.ac.id')->firstOrFail();
        $bauk = User::where('email', 'bauk@univbi.ac.id')->firstOrFail();

        // UPT ICT tidak memiliki izin document.manual_numbering secara default
        $this->assertFalse($uptIct->can('document.manual_numbering'));
        $this->actingAs($uptIct);

        Livewire::test(CreateDocument::class)
            ->assertFormFieldIsDisabled('is_manual_number');

        // BAUK memiliki izin document.manual_numbering
        $this->assertTrue($bauk->can('document.manual_numbering'));
        $this->actingAs($bauk);

        Livewire::test(CreateDocument::class)
            ->assertFormFieldIsEnabled('is_manual_number');
    }

    public function test_post_download_zip_action_respects_shield_permission(): void
    {
        $humas = User::where('email', 'humas@univbi.ac.id')->firstOrFail();
        $unitHumas = Unit::where('code', 'HUMAS')->firstOrFail();
        $category = PostCategory::firstOrFail();

        $post = Post::create([
            'title' => 'Berita Uji Unduh ZIP',
            'slug' => 'berita-uji-unduh-zip',
            'post_category_id' => $category->id,
            'unit_id' => $unitHumas->id,
            'author_id' => $humas->id,
            'content' => '<p>Konten berita.</p>',
            'status' => PostStatus::Published,
            'published_at' => now(),
        ]);

        PostPhoto::create([
            'post_id' => $post->id,
            'uploaded_by' => $humas->id,
            'file_path' => 'posts/photos/test.jpg',
            'caption' => 'Foto Kegiatan',
            'is_cover' => true,
        ]);

        // Humas memiliki post.download_zip -> tombol download_zip terlihat
        $this->actingAs($humas);
        Livewire::test(ViewPost::class, ['record' => $post->getKey()])
            ->assertActionVisible('download_zip');

        // Buat role terbatas yang hanya bisa view:post tanpa post.download_zip
        $viewerRole = Role::findOrCreate('pembaca_berita_terbatas', 'web');
        $viewerRole->syncPermissions([
            Permission::findOrCreate('view_any:post', 'web'),
            Permission::findOrCreate('view:post', 'web'),
        ]);

        $limitedUser = User::factory()->create([
            'unit_id' => $unitHumas->id,
            'is_active' => true,
        ]);
        $limitedUser->assignRole($viewerRole);

        $this->actingAs($limitedUser);
        Livewire::test(ViewPost::class, ['record' => $post->getKey()])
            ->assertActionHidden('download_zip');
    }

    public function test_trash_button_and_trash_page_are_hidden_for_roles_without_restore_or_force_delete(): void
    {
        $superAdmin = User::where('email', 'admin@univbi.ac.id')->firstOrFail();
        $stafTi = User::where('email', 'staf.ti@univbi.ac.id')->firstOrFail();

        // Super Admin memiliki akses penuh ke keranjang sampah
        $this->actingAs($superAdmin);
        $this->assertTrue(DocumentResource::canAccessTrash());
        Livewire::test(ListDocuments::class)
            ->assertActionVisible('trash');

        // Staf unit tidak memiliki restore:document atau force_delete:document
        $this->actingAs($stafTi);
        $this->assertFalse(DocumentResource::canAccessTrash());
        Livewire::test(ListDocuments::class)
            ->assertActionHidden('trash');

        $this->get('/admin/documents/trash')
            ->assertForbidden();
    }

    public function test_accreditation_data_bank_assessor_actions_respect_borang_configure_assessor_permission(): void
    {
        $lpm = User::where('email', 'lpm@univbi.ac.id')->firstOrFail();
        $this->assertTrue($lpm->can('borang.configure_assessor'));

        $this->actingAs($lpm);
        Livewire::test(AccreditationDataBank::class)
            ->assertActionVisible('configureAssessorAccess');
    }

    public function test_pure_shield_permission_control_revoking_and_granting_takes_immediate_effect_without_role_bypass(): void
    {
        $humas = User::where('email', 'humas@univbi.ac.id')->firstOrFail();
        $stafTi = User::where('email', 'staf.ti@univbi.ac.id')->firstOrFail();
        $unitHumas = Unit::where('code', 'HUMAS')->firstOrFail();

        $incoming = IncomingLetter::create([
            'unit_id' => $unitHumas->id,
            'registered_by' => $humas->id,
            'sender_name' => 'Instansi Uji Shield',
            'received_date' => '2026-11-14',
            'subject' => 'Uji Cabut Izin Hapus Surat Masuk',
        ]);

        // 1. Awalnya Humas bisa menghapus surat masuk dan mengakses Pengaturan Beranda CMS
        $this->actingAs($humas);
        $this->assertTrue(IncomingLetterResource::canDelete($incoming));
        $this->assertTrue(ManageLandingPageSettings::canAccess());

        // 2. Cabut izin delete:incoming_letter dan cms.manage_landing_page dari role humas (simulasi uncheck di Shield UI)
        // Walaupun Humas masih memegang role 'humas' dan izin 'letter.manage_humas', akses tersebut harus langsung tertutup!
        $humasRole = Role::findByName('humas', 'web');
        $humasRole->revokePermissionTo('delete:incoming_letter');
        $humasRole->revokePermissionTo('cms.manage_landing_page');
        $humas->unsetRelation('roles')->unsetRelation('permissions');

        $this->assertFalse(IncomingLetterResource::canDelete($incoming));
        $this->assertFalse(ManageLandingPageSettings::canAccess());
        // Namun hak catat surat masuk masih aktif karena create:incoming_letter tidak dicabut
        $this->assertTrue(IncomingLetterResource::canCreate());

        // 3. Berikan izin cms.manage_landing_page ke role staf_unit (simulasi check di Shield UI)
        $stafRole = Role::findByName('staf_unit', 'web');
        $stafRole->givePermissionTo('cms.manage_landing_page');
        $stafTi->unsetRelation('roles')->unsetRelation('permissions');

        $this->actingAs($stafTi);
        $this->assertTrue(ManageLandingPageSettings::canAccess());
    }
}
