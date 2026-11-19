<?php

namespace Tests\Feature\Filament\Widgets;

use App\Enums\ApprovalStatus;
use App\Enums\DegreeLevel;
use App\Enums\DocumentStatus;
use App\Enums\OutgoingLetterStatus;
use App\Enums\PostPeriod;
use App\Enums\PostStatus;
use App\Enums\UnitType;
use App\Enums\VisibilityLevel;
use App\Filament\Pages\Dashboard;
use App\Filament\Resources\Documents\DocumentResource;
use App\Filament\Resources\Documents\Pages\ListDocuments;
use App\Filament\Resources\IncomingLetters\IncomingLetterResource;
use App\Filament\Resources\IncomingLetters\Pages\ListIncomingLetters;
use App\Filament\Resources\OutgoingLetters\OutgoingLetterResource;
use App\Filament\Resources\OutgoingLetters\Pages\ListOutgoingLetters;
use App\Filament\Widgets\AnnualPublishedDocumentChartWidget;
use App\Filament\Widgets\DashboardQuickActionsWidget;
use App\Filament\Widgets\DashboardWelcomeBannerWidget;
use App\Filament\Widgets\DocumentLifecycleStatsWidget;
use App\Filament\Widgets\DocumentOverviewWidget;
use App\Filament\Widgets\PendingApprovalsWidget;
use App\Filament\Widgets\PendingLettersAlertWidget;
use App\Filament\Widgets\PostPeriodOverviewWidget;
use App\Models\AcademicYear;
use App\Models\Document;
use App\Models\DocumentApproval;
use App\Models\DocumentCategory;
use App\Models\IncomingLetter;
use App\Models\OutgoingLetter;
use App\Models\Permission;
use App\Models\Post;
use App\Models\PostCategory;
use App\Models\Role;
use App\Models\Unit;
use App\Models\User;
use App\Support\DashboardPeriodFilter;
use BezhanSalleh\FilamentShield\Facades\FilamentShield;
use Filament\Tables\Enums\PaginationMode;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class DashboardWidgetsTest extends TestCase
{
    use LazilyRefreshDatabase;

    private Unit $unitA;

    private Unit $unitB;

    private DocumentCategory $category;

    private AcademicYear $academicYear;

    protected function setUp(): void
    {
        parent::setUp();

        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $this->unitA = Unit::create([
            'code' => 'TI',
            'name' => 'Program Studi S1 Informatika',
            'slug' => 'prodi-s1-informatika',
            'type' => UnitType::Prodi,
            'degree_level' => DegreeLevel::S1,
            'is_active' => true,
        ]);

        $this->unitB = Unit::create([
            'code' => 'SI',
            'name' => 'Program Studi S1 Sistem Informasi',
            'slug' => 'prodi-s1-sistem-informasi',
            'type' => UnitType::Prodi,
            'degree_level' => DegreeLevel::S1,
            'is_active' => true,
        ]);

        $this->category = DocumentCategory::create([
            'code' => 'SK',
            'name' => 'Surat Keputusan',
            'slug' => 'surat-keputusan',
            'is_active' => true,
        ]);

        $this->academicYear = AcademicYear::create([
            'name' => '2026/2027 Ganjil',
            'semester' => 'Ganjil',
            'is_active' => true,
        ]);
    }

    public function test_dashboard_widgets_are_discovered_by_filament_shield(): void
    {
        $discovered = FilamentShield::getWidgets();

        $this->assertArrayHasKey(DashboardWelcomeBannerWidget::class, $discovered);
        $this->assertArrayHasKey(DashboardQuickActionsWidget::class, $discovered);
        $this->assertArrayHasKey(DocumentOverviewWidget::class, $discovered);
        $this->assertArrayHasKey(DocumentLifecycleStatsWidget::class, $discovered);
        $this->assertArrayHasKey(AnnualPublishedDocumentChartWidget::class, $discovered);
        $this->assertArrayHasKey(PendingApprovalsWidget::class, $discovered);

        $this->assertArrayHasKey('view:dashboard_welcome_banner_widget', $discovered[DashboardWelcomeBannerWidget::class]['permissions']);
        $this->assertArrayHasKey('view:dashboard_quick_actions_widget', $discovered[DashboardQuickActionsWidget::class]['permissions']);
        $this->assertArrayHasKey('view:document_overview_widget', $discovered[DocumentOverviewWidget::class]['permissions']);
        $this->assertArrayHasKey('view:document_lifecycle_stats_widget', $discovered[DocumentLifecycleStatsWidget::class]['permissions']);
        $this->assertArrayHasKey('view:annual_published_document_chart_widget', $discovered[AnnualPublishedDocumentChartWidget::class]['permissions']);
        $this->assertArrayHasKey('view:pending_approvals_widget', $discovered[PendingApprovalsWidget::class]['permissions']);
    }

    public function test_dashboard_welcome_banner_widget_renders_user_name_roles_position_and_logout(): void
    {
        $permission = Permission::findOrCreate('view:dashboard_welcome_banner_widget', 'web');
        $superAdminRole = Role::findOrCreate('super_admin', 'web');
        $superAdminRole->givePermissionTo($permission);

        $user = User::factory()->create([
            'name' => 'Prof. Dr. H. Sardiyo, M.M.',
            'position_title' => 'Rektor Universitas Bina Insan',
            'nidn_nip' => '0211088901',
            'unit_id' => $this->unitA->id,
            'is_active' => true,
        ]);
        $user->assignRole($superAdminRole);

        $this->actingAs($user);

        Livewire::test(DashboardWelcomeBannerWidget::class)
            ->assertSuccessful()
            ->assertSee('Prof. Dr. H. Sardiyo, M.M.')
            ->assertSee('Super Admin')
            ->assertSee('Rektor Universitas Bina Insan')
            ->assertSee('Waktu Sistem')
            ->assertSee('Keluar')
            ->assertSee('Konfirmasi Keluar Sistem')
            ->assertSee('Batal')
            ->assertSee('Ya, Keluar');
    }

    public function test_document_overview_widget_renders_expected_metrics_for_super_admin(): void
    {
        $permission = Permission::findOrCreate('view:document_overview_widget', 'web');
        $superAdminRole = Role::findOrCreate('super_admin', 'web');
        $superAdminRole->givePermissionTo($permission);

        $superAdmin = User::factory()->create(['is_active' => true]);
        $superAdmin->assignRole($superAdminRole);

        // Setup documents
        Document::create([
            'title' => 'SK Pending 1',
            'slug' => 'sk-pending-1',
            'unit_id' => $this->unitA->id,
            'document_category_id' => $this->category->id,
            'academic_year_id' => $this->academicYear->id,
            'status' => DocumentStatus::Pending,
            'is_borang' => false,
            'uploaded_by' => $superAdmin->id,
            'issued_date' => '2026-11-10',
        ]);

        Document::create([
            'title' => 'SK Revision 1',
            'slug' => 'sk-revision-1',
            'unit_id' => $this->unitB->id,
            'document_category_id' => $this->category->id,
            'academic_year_id' => $this->academicYear->id,
            'status' => DocumentStatus::Revision,
            'is_borang' => true,
            'uploaded_by' => $superAdmin->id,
            'issued_date' => '2026-11-10',
        ]);

        Document::create([
            'title' => 'SK Approved 1',
            'slug' => 'sk-approved-1',
            'unit_id' => $this->unitA->id,
            'document_category_id' => $this->category->id,
            'academic_year_id' => $this->academicYear->id,
            'status' => DocumentStatus::Approved,
            'is_borang' => true,
            'uploaded_by' => $superAdmin->id,
            'issued_date' => '2026-11-10',
        ]);

        // Setup active assessor on unit A
        $this->unitA->update([
            'is_assessor_access_active' => true,
            'assessor_access_start' => now()->subDay(),
            'assessor_access_end' => now()->addDays(5),
        ]);

        $this->actingAs($superAdmin);

        Livewire::test(DocumentOverviewWidget::class)
            ->assertSuccessful()
            ->assertSee('Menunggu Persetujuan')
            ->assertSee('Naskah Sah & Resmi')
            ->assertSee('Bukti Borang Akreditasi')
            ->assertSee('Portal Asesor Terbuka')
            ->assertSee('tab=all&amp;filters%5Bstatus%5D%5Bvalues%5D%5B0%5D=pending&amp;filters%5Bstatus%5D%5Bvalues%5D%5B1%5D=revision', false)
            ->assertSee('tab=all&amp;filters%5Bstatus%5D%5Bvalues%5D%5B0%5D=approved&amp;filters%5Bstatus%5D%5Bvalues%5D%5B1%5D=archived&amp;filters%5Bstatus%5D%5Bvalues%5D%5B2%5D=expired', false)
            ->assertSee('tab=all&amp;filters%5Bis_borang%5D%5Bvalue%5D=1', false)
            ->assertSee('accreditation-data-bank');
    }

    public function test_pending_approvals_widget_renders_pending_and_revision_documents(): void
    {
        $permission = Permission::findOrCreate('view:pending_approvals_widget', 'web');
        $superAdminRole = Role::findOrCreate('super_admin', 'web');
        $superAdminRole->givePermissionTo($permission);

        $superAdmin = User::factory()->create(['is_active' => true]);
        $superAdmin->assignRole($superAdminRole);

        $docPending = Document::create([
            'title' => 'SK Tugas Mengajar Semester Ganjil',
            'slug' => 'sk-tugas-mengajar-semester-ganjil',
            'document_number' => '001/UNIVBI.TI/SK/XI/2026',
            'unit_id' => $this->unitA->id,
            'document_category_id' => $this->category->id,
            'academic_year_id' => $this->academicYear->id,
            'status' => DocumentStatus::Pending,
            'current_step' => 1,
            'uploaded_by' => $superAdmin->id,
            'issued_date' => '2026-11-10',
        ]);

        $docRevision = Document::create([
            'title' => 'Laporan Akreditasi Perlu Perbaikan',
            'slug' => 'laporan-akreditasi-perlu-perbaikan',
            'document_number' => '002/UNIVBI.SI/SK/XI/2026',
            'unit_id' => $this->unitB->id,
            'document_category_id' => $this->category->id,
            'academic_year_id' => $this->academicYear->id,
            'status' => DocumentStatus::Revision,
            'current_step' => 2,
            'uploaded_by' => $superAdmin->id,
            'issued_date' => '2026-11-10',
        ]);

        $docApproved = Document::create([
            'title' => 'SK Yang Sudah Sah',
            'slug' => 'sk-yang-sudah-sah',
            'document_number' => '003/UNIVBI.TI/SK/XI/2026',
            'unit_id' => $this->unitA->id,
            'document_category_id' => $this->category->id,
            'academic_year_id' => $this->academicYear->id,
            'status' => DocumentStatus::Approved,
            'uploaded_by' => $superAdmin->id,
            'issued_date' => '2026-11-10',
        ]);

        $this->actingAs($superAdmin);

        Livewire::test(PendingApprovalsWidget::class)
            ->assertSuccessful()
            ->assertCanSeeTableRecords([$docPending, $docRevision])
            ->assertCanNotSeeTableRecords([$docApproved]);
    }

    public function test_pending_approvals_widget_renders_dynamic_step_label_based_on_approver_role(): void
    {
        $permission = Permission::findOrCreate('view:pending_approvals_widget', 'web');
        $superAdminRole = Role::findOrCreate('super_admin', 'web');
        $superAdminRole->givePermissionTo($permission);

        $superAdmin = User::factory()->create(['is_active' => true]);
        $superAdmin->assignRole($superAdminRole);

        $docKaprodi = Document::create([
            'title' => 'RPS Mata Kuliah AI Ganjil 2026',
            'slug' => 'rps-ai-ganjil-2026-test',
            'unit_id' => $this->unitA->id,
            'document_category_id' => $this->category->id,
            'academic_year_id' => $this->academicYear->id,
            'status' => DocumentStatus::Pending,
            'current_step' => 1,
            'total_steps' => 1,
            'uploaded_by' => $superAdmin->id,
            'issued_date' => '2026-11-10',
        ]);

        DocumentApproval::create([
            'document_id' => $docKaprodi->id,
            'revision_round' => 1,
            'step_order' => 1,
            'approver_role' => 'kaprodi',
            'status' => ApprovalStatus::Pending,
        ]);

        $this->assertSame('Tingkat 1 (Kaprodi)', $docKaprodi->getCurrentStepLabel());

        $this->actingAs($superAdmin);

        Livewire::test(PendingApprovalsWidget::class)
            ->assertSuccessful()
            ->assertSee('Tingkat 1 (Kaprodi)')
            ->assertDontSee('Tingkat 1 (Warek)');
    }

    public function test_pending_approvals_widget_shows_empty_state_when_no_pending_documents(): void
    {
        $permission = Permission::findOrCreate('view:pending_approvals_widget', 'web');
        $superAdminRole = Role::findOrCreate('super_admin', 'web');
        $superAdminRole->givePermissionTo($permission);

        $superAdmin = User::factory()->create(['is_active' => true]);
        $superAdmin->assignRole($superAdminRole);

        $this->actingAs($superAdmin);

        Livewire::test(PendingApprovalsWidget::class)
            ->assertSuccessful()
            ->assertSee('Semua Naskah Tuntas Diproses');
    }

    public function test_dashboard_renders_both_widgets_on_admin_index(): void
    {
        $widgetBannerPermission = Permission::findOrCreate('view:dashboard_welcome_banner_widget', 'web');
        $widgetOverviewPermission = Permission::findOrCreate('view:document_overview_widget', 'web');
        $widgetTablePermission = Permission::findOrCreate('view:pending_approvals_widget', 'web');

        $superAdminRole = Role::findOrCreate('super_admin', 'web');
        $superAdminRole->givePermissionTo([$widgetBannerPermission, $widgetOverviewPermission, $widgetTablePermission]);

        $superAdmin = User::factory()->create(['is_active' => true]);
        $superAdmin->assignRole($superAdminRole);

        $response = $this->actingAs($superAdmin)->get('/admin');

        $response->assertOk();
        $response->assertSeeText('Waktu Sistem');
        $response->assertSeeText('Menunggu Persetujuan');
        $response->assertSeeText('Antrean Pengesahan & Naskah Butuh Tindak Lanjut');
    }

    public function test_widgets_respect_shield_permissions_can_view(): void
    {
        $userWithoutPermissions = User::factory()->create(['is_active' => true]);
        $this->actingAs($userWithoutPermissions);

        $this->assertFalse(DashboardWelcomeBannerWidget::canView());
        $this->assertFalse(DocumentOverviewWidget::canView());
        $this->assertFalse(PendingApprovalsWidget::canView());

        $widgetOverviewPermission = Permission::findOrCreate('view:document_overview_widget', 'web');
        $widgetTablePermission = Permission::findOrCreate('view:pending_approvals_widget', 'web');

        $userWithOverview = User::factory()->create(['is_active' => true]);
        $userWithOverview->givePermissionTo($widgetOverviewPermission);

        $this->actingAs($userWithOverview);
        $this->assertTrue(DocumentOverviewWidget::canView());
        $this->assertFalse(PendingApprovalsWidget::canView());

        $userWithBoth = User::factory()->create(['is_active' => true]);
        $userWithBoth->givePermissionTo([$widgetOverviewPermission, $widgetTablePermission]);

        $this->actingAs($userWithBoth);
        $this->assertTrue(DocumentOverviewWidget::canView());
        $this->assertTrue(PendingApprovalsWidget::canView());
    }

    public function test_pending_approvals_widget_respects_unit_scoping_for_prodi_user(): void
    {
        $prodiUser = User::factory()->create([
            'unit_id' => $this->unitA->id,
            'is_active' => true,
        ]);

        $permission = Permission::findOrCreate('view:pending_approvals_widget', 'web');
        $prodiUser->givePermissionTo($permission);

        $docUnitA = Document::create([
            'title' => 'SK Magang Prodi TI',
            'slug' => 'sk-magang-prodi-ti',
            'document_number' => '010/UNIVBI.TI/SK/XI/2026',
            'unit_id' => $this->unitA->id,
            'document_category_id' => $this->category->id,
            'academic_year_id' => $this->academicYear->id,
            'status' => DocumentStatus::Pending,
            'current_step' => 1,
            'uploaded_by' => $prodiUser->id,
            'issued_date' => '2026-11-10',
        ]);

        $otherUser = User::factory()->create([
            'unit_id' => $this->unitB->id,
            'is_active' => true,
        ]);

        $docUnitB = Document::create([
            'title' => 'SK Magang Prodi SI',
            'slug' => 'sk-magang-prodi-si',
            'document_number' => '011/UNIVBI.SI/SK/XI/2026',
            'unit_id' => $this->unitB->id,
            'document_category_id' => $this->category->id,
            'academic_year_id' => $this->academicYear->id,
            'status' => DocumentStatus::Pending,
            'current_step' => 1,
            'uploaded_by' => $otherUser->id,
            'issued_date' => '2026-11-10',
        ]);

        $this->actingAs($prodiUser);

        Livewire::test(PendingApprovalsWidget::class)
            ->assertSuccessful()
            ->assertCanSeeTableRecords([$docUnitA])
            ->assertCanNotSeeTableRecords([$docUnitB]);
    }

    public function test_confidential_document_is_hidden_from_users_without_permission_in_widgets(): void
    {
        $uploader = User::factory()->create([
            'unit_id' => $this->unitA->id,
            'is_active' => true,
        ]);

        $colleague = User::factory()->create([
            'unit_id' => $this->unitA->id,
            'is_active' => true,
        ]);

        $confidentialDoc = Document::create([
            'title' => 'Naskah Rahasia Pimpinan Kampus',
            'slug' => 'naskah-rahasia-pimpinan-kampus',
            'document_number' => '099/UNIVBI/RAHASIA/2026',
            'unit_id' => $this->unitA->id,
            'document_category_id' => $this->category->id,
            'academic_year_id' => $this->academicYear->id,
            'status' => DocumentStatus::Pending,
            'visibility' => VisibilityLevel::Confidential,
            'uploaded_by' => $uploader->id,
            'issued_date' => '2026-11-10',
        ]);

        $permissionWidget = Permission::findOrCreate('view:pending_approvals_widget', 'web');
        $uploader->givePermissionTo($permissionWidget);
        $colleague->givePermissionTo($permissionWidget);

        // Rekan 1 unit yang tidak memiliki izin document.view_confidential dan bukan uploader TIDAK boleh melihat
        $this->actingAs($colleague);
        Livewire::test(PendingApprovalsWidget::class)
            ->assertSuccessful()
            ->assertCanNotSeeTableRecords([$confidentialDoc]);

        // Pengunggah dokumen rahasia tersebut BISA melihat
        $this->actingAs($uploader);
        Livewire::test(PendingApprovalsWidget::class)
            ->assertSuccessful()
            ->assertCanSeeTableRecords([$confidentialDoc]);

        // Pejabat dengan izin document.view_confidential BISA melihat
        $confidentialPermission = Permission::findOrCreate('document.view_confidential', 'web');
        $colleague->givePermissionTo($confidentialPermission);

        $this->actingAs($colleague);
        Livewire::test(PendingApprovalsWidget::class)
            ->assertSuccessful()
            ->assertCanSeeTableRecords([$confidentialDoc]);
    }

    public function test_document_overview_widget_scopes_counts_to_user_access_strictly(): void
    {
        $userUnitA = User::factory()->create([
            'unit_id' => $this->unitA->id,
            'is_active' => true,
        ]);

        $permissionWidget = Permission::findOrCreate('view:document_overview_widget', 'web');
        $userUnitA->givePermissionTo($permissionWidget);

        // Dokumen Unit A (Internal Sah)
        Document::create([
            'title' => 'SK Unit A',
            'slug' => 'sk-unit-a',
            'unit_id' => $this->unitA->id,
            'document_category_id' => $this->category->id,
            'academic_year_id' => $this->academicYear->id,
            'status' => DocumentStatus::Approved,
            'visibility' => VisibilityLevel::Internal,
            'uploaded_by' => $userUnitA->id,
            'issued_date' => '2026-11-10',
            'is_borang' => false,
        ]);

        // Dokumen Unit B (Internal Sah - Tidak boleh diakses oleh user Unit A)
        Document::create([
            'title' => 'SK Unit B Internal',
            'slug' => 'sk-unit-b-internal',
            'unit_id' => $this->unitB->id,
            'document_category_id' => $this->category->id,
            'academic_year_id' => $this->academicYear->id,
            'status' => DocumentStatus::Approved,
            'visibility' => VisibilityLevel::Internal,
            'uploaded_by' => User::factory()->create(['unit_id' => $this->unitB->id])->id,
            'issued_date' => '2026-11-10',
            'is_borang' => false,
        ]);

        $this->actingAs($userUnitA);

        // Hanya dokumen Unit A yang dihitung untuk user Unit A
        $approvedCount = Document::query()
            ->accessibleFor($userUnitA)
            ->where('status', DocumentStatus::Approved)
            ->count();

        $this->assertSame(1, $approvedCount);
    }

    public function test_stats_filter_urls_are_accessible_and_actually_filter_documents_list(): void
    {
        $superAdminRole = Role::findOrCreate('super_admin', 'web');
        $viewAnyDocPermission = Permission::findOrCreate('view_any:document', 'web');
        $viewAnyIncomingPermission = Permission::findOrCreate('view_any:incoming_letter', 'web');
        $viewAnyOutgoingPermission = Permission::findOrCreate('view_any:outgoing_letter', 'web');
        $superAdminRole->givePermissionTo([
            $viewAnyDocPermission,
            $viewAnyIncomingPermission,
            $viewAnyOutgoingPermission,
        ]);

        $superAdmin = User::factory()->create(['is_active' => true]);
        $superAdmin->assignRole($superAdminRole);

        $docPending = Document::create([
            'title' => 'Naskah Pending Uji Filter',
            'slug' => 'naskah-pending-uji-filter',
            'unit_id' => $this->unitA->id,
            'document_category_id' => $this->category->id,
            'academic_year_id' => $this->academicYear->id,
            'status' => DocumentStatus::Pending,
            'is_borang' => false,
            'uploaded_by' => $superAdmin->id,
            'issued_date' => '2026-11-10',
        ]);

        $docRevision = Document::create([
            'title' => 'Naskah Revisi Uji Filter',
            'slug' => 'naskah-revisi-uji-filter',
            'unit_id' => $this->unitA->id,
            'document_category_id' => $this->category->id,
            'academic_year_id' => $this->academicYear->id,
            'status' => DocumentStatus::Revision,
            'is_borang' => false,
            'uploaded_by' => $superAdmin->id,
            'issued_date' => '2026-11-10',
        ]);

        $docApproved = Document::create([
            'title' => 'Naskah Approved Borang Uji Filter',
            'slug' => 'naskah-approved-borang-uji-filter',
            'unit_id' => $this->unitA->id,
            'document_category_id' => $this->category->id,
            'academic_year_id' => $this->academicYear->id,
            'status' => DocumentStatus::Approved,
            'is_borang' => true,
            'uploaded_by' => $superAdmin->id,
            'issued_date' => '2026-11-10',
        ]);

        $docArchived = Document::create([
            'title' => 'Naskah Archived Uji Filter',
            'slug' => 'naskah-archived-uji-filter',
            'unit_id' => $this->unitA->id,
            'document_category_id' => $this->category->id,
            'academic_year_id' => $this->academicYear->id,
            'status' => DocumentStatus::Archived,
            'is_borang' => false,
            'uploaded_by' => $superAdmin->id,
            'issued_date' => '2026-11-10',
        ]);

        $this->actingAs($superAdmin);

        $parseQuery = static function (string $url): array {
            $query = [];
            parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

            return $query;
        };

        // 1. Kartu Menunggu Persetujuan (Pending + Revision)
        $pendingUrl = DocumentResource::getUrl('index', [
            'tab' => 'all',
            'filters' => [
                'status' => [
                    'values' => [DocumentStatus::Pending->value, DocumentStatus::Revision->value],
                ],
            ],
        ]);
        $this->get($pendingUrl)->assertOk();
        Livewire::withQueryParams($parseQuery($pendingUrl))
            ->test(ListDocuments::class)
            ->assertSet('activeTab', 'all')
            ->assertCanSeeTableRecords([$docPending, $docRevision])
            ->assertCanNotSeeTableRecords([$docApproved, $docArchived]);

        // 2. Kartu Naskah Sah & Resmi (Approved + Archived + Expired)
        $approvedUrl = DocumentResource::getUrl('index', [
            'tab' => 'all',
            'filters' => [
                'status' => [
                    'values' => [
                        DocumentStatus::Approved->value,
                        DocumentStatus::Archived->value,
                        DocumentStatus::Expired->value,
                    ],
                ],
            ],
        ]);
        $this->get($approvedUrl)->assertOk();
        Livewire::withQueryParams($parseQuery($approvedUrl))
            ->test(ListDocuments::class)
            ->assertSet('activeTab', 'all')
            ->assertCanSeeTableRecords([$docApproved, $docArchived])
            ->assertCanNotSeeTableRecords([$docPending, $docRevision]);

        // 3. Kartu Bukti Borang Akreditasi (is_borang = 1)
        $borangUrl = DocumentResource::getUrl('index', [
            'tab' => 'all',
            'filters' => ['is_borang' => ['value' => '1']],
        ]);
        $this->get($borangUrl)->assertOk();
        Livewire::withQueryParams($parseQuery($borangUrl))
            ->test(ListDocuments::class)
            ->assertSet('activeTab', 'all')
            ->assertCanSeeTableRecords([$docApproved])
            ->assertCanNotSeeTableRecords([$docPending, $docRevision, $docArchived]);

        // 4. Widget Surat Masuk & Keluar alert tab links
        $incomingTabUrl = IncomingLetterResource::getUrl('index', ['tab' => 'my_turn']);
        Livewire::withQueryParams($parseQuery($incomingTabUrl))
            ->test(ListIncomingLetters::class)
            ->assertSet('activeTab', 'my_turn');

        $outgoingTabUrl = OutgoingLetterResource::getUrl('index', ['tab' => 'pending_humas']);
        Livewire::withQueryParams($parseQuery($outgoingTabUrl))
            ->test(ListOutgoingLetters::class)
            ->assertSet('activeTab', 'pending_humas');
    }

    public function test_annual_published_document_chart_widget_respects_permissions_and_scoping(): void
    {
        $roleWithPermission = Role::findOrCreate('pimpinan', 'web');
        $permission = Permission::findOrCreate('view:annual_published_document_chart_widget', 'web');
        $roleWithPermission->givePermissionTo($permission);

        $userWithAccess = User::factory()->create([
            'unit_id' => $this->unitA->id,
            'is_active' => true,
        ]);
        $userWithAccess->assignRole($roleWithPermission);

        $userWithoutAccess = User::factory()->create([
            'unit_id' => $this->unitB->id,
            'is_active' => true,
        ]);

        // User tanpa izin tidak dapat melihat widget
        $this->actingAs($userWithoutAccess);
        $this->assertFalse(AnnualPublishedDocumentChartWidget::canView());

        // User dengan izin dapat melihat widget
        $this->actingAs($userWithAccess);
        $this->assertTrue(AnnualPublishedDocumentChartWidget::canView());

        // Buat data dokumen sah untuk Unit A dan Unit B
        $docA = Document::create([
            'title' => 'Naskah Sah Unit A',
            'slug' => 'naskah-sah-unit-a',
            'unit_id' => $this->unitA->id,
            'document_category_id' => $this->category->id,
            'academic_year_id' => $this->academicYear->id,
            'status' => DocumentStatus::Approved,
            'visibility' => VisibilityLevel::Internal,
            'uploaded_by' => $userWithAccess->id,
            'issued_date' => date('Y-m-d'),
            'approved_at' => now(),
            'is_borang' => false,
        ]);

        $docB = Document::create([
            'title' => 'Naskah Sah Unit B',
            'slug' => 'naskah-sah-unit-b',
            'unit_id' => $this->unitB->id,
            'document_category_id' => $this->category->id,
            'academic_year_id' => $this->academicYear->id,
            'status' => DocumentStatus::Approved,
            'visibility' => VisibilityLevel::Internal,
            'uploaded_by' => $userWithoutAccess->id,
            'issued_date' => date('Y-m-d'),
            'approved_at' => now(),
            'is_borang' => false,
        ]);

        // Pengguna Unit A hanya melihat naskah terbit Unit A
        $component = Livewire::test(AnnualPublishedDocumentChartWidget::class);
        $component->assertOk();
        $component->assertSee('Penerbitan Naskah Dinas Sah');
        $component->assertSee('Total Terbit');

        $viewData = $component->instance()->getViewData();
        $this->assertSame(1, $viewData['totalYearly']);
        $this->assertCount(12, $viewData['months']);
        $this->assertSame(1, $viewData['currentMonthCount']);

        // Sebagai super_admin, melihat kedua dokumen (Total = 2)
        $superAdminRole = Role::findOrCreate('super_admin', 'web');
        $superAdminRole->givePermissionTo($permission);
        $superAdmin = User::factory()->create(['is_active' => true]);
        $superAdmin->assignRole($superAdminRole);

        $this->actingAs($superAdmin);
        $adminComponent = Livewire::test(AnnualPublishedDocumentChartWidget::class);
        $adminComponent->assertOk();

        $adminData = $adminComponent->instance()->getViewData();
        $this->assertSame(2, $adminData['totalYearly']);
        $this->assertSame(2, $adminData['currentMonthCount']);

        // Naskah yang diarsipkan (Archived) tetap diakui sebagai naskah sah terbit
        Document::create([
            'title' => 'Naskah Pasif Arsip Unit A',
            'slug' => 'naskah-pasif-arsip-unit-a',
            'unit_id' => $this->unitA->id,
            'document_category_id' => $this->category->id,
            'academic_year_id' => $this->academicYear->id,
            'status' => DocumentStatus::Archived,
            'visibility' => VisibilityLevel::Internal,
            'uploaded_by' => $userWithAccess->id,
            'issued_date' => date('Y-m-d'),
            'approved_at' => now(),
            'is_borang' => false,
        ]);

        $adminComponentUpdated = Livewire::test(AnnualPublishedDocumentChartWidget::class);
        $updatedData = $adminComponentUpdated->instance()->getViewData();
        $this->assertSame(3, $updatedData['totalYearly']);
        $this->assertSame(3, $updatedData['currentMonthCount']);
    }

    public function test_quick_actions_widget_renders_for_super_admin_with_all_authorized_actions(): void
    {
        $widgetPermission = Permission::findOrCreate('view:dashboard_quick_actions_widget', 'web');
        $superAdminRole = Role::findOrCreate('super_admin', 'web');
        $superAdminRole->givePermissionTo($widgetPermission);

        $admin = User::factory()->create([
            'name' => 'Admin Sistem',
            'unit_id' => $this->unitA->id,
            'is_active' => true,
        ]);
        $admin->assignRole($superAdminRole);

        $this->actingAs($admin);

        $component = Livewire::test(DashboardQuickActionsWidget::class);
        $component->assertOk();
        $component->assertSee('Pintasan Aksi Cepat');
        $component->assertSee('Registrasi Naskah');
        $component->assertSee('Akses Asesor');
        $component->assertSee('Cari Naskah');
        $component->assertSee('Warta Kampus');
        $component->assertSee('Log Audit');
        $component->assertDontSee('Bank Borang');
        $component->assertDontSee('Antrean Pengesahan');
        $component->assertSee('Aksi Tersedia');

        $actions = $component->instance()->getViewData()['actions'];
        $this->assertCount(5, $actions);
    }

    public function test_quick_actions_widget_respects_shield_and_role_permissions_for_unit_staff(): void
    {
        $widgetPermission = Permission::findOrCreate('view:dashboard_quick_actions_widget', 'web');
        $createDocPermission = Permission::findOrCreate('create:document', 'web');
        $viewAnyDocPermission = Permission::findOrCreate('view_any:document', 'web');

        $staffRole = Role::findOrCreate('staf_unit', 'web');
        $staffRole->syncPermissions([
            $widgetPermission,
            $createDocPermission,
            $viewAnyDocPermission,
        ]);

        $staffUser = User::factory()->create([
            'name' => 'Staf Prodi TI',
            'unit_id' => $this->unitA->id,
            'is_active' => true,
        ]);
        $staffUser->assignRole($staffRole);

        $this->actingAs($staffUser);

        $component = Livewire::test(DashboardQuickActionsWidget::class);
        $component->assertOk();
        $component->assertSee('Registrasi Naskah');
        $component->assertSee('Cari Naskah');

        // Staf biasa tidak boleh melihat aksi pimpinan & admin
        $component->assertDontSee('Log Audit');
        $component->assertDontSee('Warta Kampus');
        $component->assertDontSee('Akses Asesor');

        $actionIds = collect($component->instance()->getViewData()['actions'])->pluck('id')->all();
        $this->assertContains('create_document', $actionIds);
        $this->assertContains('search_documents', $actionIds);
        $this->assertNotContains('audit_logs', $actionIds);
        $this->assertNotContains('create_post', $actionIds);
        $this->assertNotContains('assessor_portal', $actionIds);
    }

    public function test_quick_actions_widget_displays_assessor_open_badge_when_active(): void
    {
        $widgetPermission = Permission::findOrCreate('view:dashboard_quick_actions_widget', 'web');
        $viewAnyDocPermission = Permission::findOrCreate('view_any:document', 'web');

        $dekanRole = Role::findOrCreate('dekan', 'web');
        $dekanRole->syncPermissions([
            $widgetPermission,
            $viewAnyDocPermission,
        ]);

        $dekan = User::factory()->create([
            'name' => 'Dekan Fakultas',
            'unit_id' => $this->unitA->id,
            'is_active' => true,
        ]);
        $dekan->assignRole($dekanRole);

        // Buka akses asesor pada Unit A
        $this->unitA->update([
            'is_assessor_access_active' => true,
            'assessor_access_start' => now()->subDay()->toDateString(),
            'assessor_access_end' => now()->addDays(7)->toDateString(),
            'assessor_passcode' => '12345',
        ]);

        $this->actingAs($dekan);

        $component = Livewire::test(DashboardQuickActionsWidget::class);
        $component->assertOk();
        $component->assertSee('Akses Asesor');
        $component->assertSee('1 Unit');
        $component->assertDontSee('Bank Borang');
        $component->assertDontSee('Antrean Pengesahan');
    }

    public function test_dashboard_widgets_use_database_cache_safely_and_isolate_confidential_scopes(): void
    {
        Cache::setDefaultDriver('database');

        $overviewPerm = Permission::findOrCreate('view:document_overview_widget', 'web');
        $chartPerm = Permission::findOrCreate('view:annual_published_document_chart_widget', 'web');
        $confidentialPerm = Permission::findOrCreate('document.view_confidential', 'web');

        $roleWithConfidential = Role::findOrCreate('dekan', 'web');
        $roleWithConfidential->syncPermissions([$overviewPerm, $chartPerm, $confidentialPerm]);

        $roleWithoutConfidential = Role::findOrCreate('staf_unit', 'web');
        $roleWithoutConfidential->syncPermissions([$overviewPerm, $chartPerm]);

        $dekan = User::factory()->create(['unit_id' => $this->unitA->id, 'is_active' => true]);
        $dekan->assignRole($roleWithConfidential);

        $staf = User::factory()->create(['unit_id' => $this->unitA->id, 'is_active' => true]);
        $staf->assignRole($roleWithoutConfidential);

        Document::create([
            'title' => 'Naskah Internal Biasa',
            'slug' => 'naskah-internal-biasa',
            'unit_id' => $this->unitA->id,
            'document_category_id' => $this->category->id,
            'academic_year_id' => $this->academicYear->id,
            'status' => DocumentStatus::Approved,
            'visibility' => VisibilityLevel::Internal,
            'uploaded_by' => $dekan->id,
            'issued_date' => date('Y-m-d'),
            'approved_at' => now(),
            'is_borang' => true,
        ]);

        Document::create([
            'title' => 'Naskah Rahasia Dekan',
            'slug' => 'naskah-rahasia-dekan',
            'unit_id' => $this->unitA->id,
            'document_category_id' => $this->category->id,
            'academic_year_id' => $this->academicYear->id,
            'status' => DocumentStatus::Approved,
            'visibility' => VisibilityLevel::Confidential,
            'uploaded_by' => $dekan->id,
            'issued_date' => date('Y-m-d'),
            'approved_at' => now(),
            'is_borang' => false,
        ]);

        // 1. User berizin Confidential melihat total 2 dokumen sah (diuji 2x untuk cache hit database)
        $this->actingAs($dekan);
        Livewire::test(DocumentOverviewWidget::class)->assertSuccessful();
        Livewire::test(DocumentOverviewWidget::class)->assertSuccessful();

        $chartDekan = Livewire::test(AnnualPublishedDocumentChartWidget::class);
        $this->assertSame(2, $chartDekan->instance()->getViewData()['totalYearly']);
        $chartDekanHit = Livewire::test(AnnualPublishedDocumentChartWidget::class);
        $this->assertSame(2, $chartDekanHit->instance()->getViewData()['totalYearly']);

        // 2. Staf di unit yang sama TANPA izin Confidential tidak boleh terkena bocoran hitungan cache
        $this->actingAs($staf);
        $chartStaf = Livewire::test(AnnualPublishedDocumentChartWidget::class);
        $this->assertSame(1, $chartStaf->instance()->getViewData()['totalYearly']);

        // 3. Tambah dokumen baru -> versi cache otomatis naik dan grafik terbarui
        $prevVersion = Document::getStatsCacheVersion();
        Document::create([
            'title' => 'Naskah Baru Pasca Cache',
            'slug' => 'naskah-baru-pasca-cache',
            'unit_id' => $this->unitA->id,
            'document_category_id' => $this->category->id,
            'academic_year_id' => $this->academicYear->id,
            'status' => DocumentStatus::Approved,
            'visibility' => VisibilityLevel::Internal,
            'uploaded_by' => $staf->id,
            'issued_date' => date('Y-m-d'),
            'approved_at' => now(),
        ]);
        $this->assertGreaterThan($prevVersion, Document::getStatsCacheVersion());

        $chartStafUpdated = Livewire::test(AnnualPublishedDocumentChartWidget::class);
        $this->assertSame(2, $chartStafUpdated->instance()->getViewData()['totalYearly']);
    }

    public function test_pending_letters_and_assessor_cache_invalidate_automatically_and_memoize(): void
    {
        Cache::setDefaultDriver('database');

        $alertPerm = Permission::findOrCreate('view:pending_letters_alert_widget', 'web');
        $humasPerm = Permission::findOrCreate('letter.manage_humas', 'web');

        $humasRole = Role::findOrCreate('humas', 'web');
        $humasRole->syncPermissions([$alertPerm, $humasPerm]);

        $humas = User::factory()->create(['unit_id' => $this->unitA->id, 'is_active' => true]);
        $humas->assignRole($humasRole);

        $letter = OutgoingLetter::create([
            'unit_id' => $this->unitA->id,
            'academic_year_id' => $this->academicYear->id,
            'created_by' => $humas->id,
            'destination_organization' => 'LLDIKTI Wilayah II',
            'subject' => 'Permohonan Verifikasi Data',
            'letter_date' => now()->toDateString(),
            'status' => OutgoingLetterStatus::PendingHumas,
        ]);

        $this->actingAs($humas);
        $this->assertTrue(PendingLettersAlertWidget::canView());

        // Memoization per-request & database cache memastikan pemanggilan berulang tidak menembak query ke tabel surat lagi
        DB::enableQueryLog();
        DB::flushQueryLog();
        $counts1 = PendingLettersAlertWidget::getPendingLetterCounts($humas);
        $counts2 = PendingLettersAlertWidget::getPendingLetterCounts($humas);
        $letterTableQueries = collect(DB::getQueryLog())->filter(
            static fn (array $entry): bool => str_contains($entry['query'], 'incoming_letters') || str_contains($entry['query'], 'outgoing_letters')
        );
        $this->assertSame(1, $counts1['outgoingHumasCount']);
        $this->assertSame($counts1, $counts2);
        $this->assertCount(0, $letterTableQueries);
        DB::disableQueryLog();

        // Update status surat -> cache otomatis terinvalidasi
        $letterVersionBefore = IncomingLetter::getStatsCacheVersion();
        $letter->update(['status' => OutgoingLetterStatus::Published]);
        $this->assertGreaterThan($letterVersionBefore, IncomingLetter::getStatsCacheVersion());
        $this->assertFalse(PendingLettersAlertWidget::canView());

        // Assessor cache invalidation pada Unit
        $unitVersionBefore = Unit::getAssessorCacheVersion();
        $this->unitA->update([
            'is_assessor_access_active' => true,
            'assessor_access_start' => now()->subDay()->toDateString(),
            'assessor_access_end' => now()->addDays(5)->toDateString(),
        ]);
        $this->assertGreaterThan($unitVersionBefore, Unit::getAssessorCacheVersion());
        $this->assertSame(1, Unit::countActiveAssessorUnitsForUser($humas));
    }

    public function test_pending_approvals_widget_uses_simple_pagination_and_subquery_scope(): void
    {
        $tablePerm = Permission::findOrCreate('view:pending_approvals_widget', 'web');
        $kaprodiRole = Role::findOrCreate('kaprodi', 'web');
        $kaprodiRole->syncPermissions([$tablePerm]);

        $kaprodiA = User::factory()->create(['unit_id' => $this->unitA->id, 'is_active' => true]);
        $kaprodiA->assignRole($kaprodiRole);

        $stafB = User::factory()->create(['unit_id' => $this->unitB->id, 'is_active' => true]);

        $docUnitA = Document::create([
            'title' => 'Menunggu Kaprodi Unit A',
            'slug' => 'menunggu-kaprodi-unit-a',
            'unit_id' => $this->unitA->id,
            'document_category_id' => $this->category->id,
            'academic_year_id' => $this->academicYear->id,
            'status' => DocumentStatus::Pending,
            'current_step' => 1,
            'total_steps' => 1,
            'uploaded_by' => $kaprodiA->id,
            'issued_date' => date('Y-m-d'),
        ]);

        DocumentApproval::create([
            'document_id' => $docUnitA->id,
            'revision_round' => 1,
            'step_order' => 1,
            'approver_role' => 'kaprodi',
            'target_unit_id' => $this->unitA->id,
            'status' => ApprovalStatus::Pending,
        ]);

        Document::create([
            'title' => 'Pending Unit Lain Tidak Boleh Bocor',
            'slug' => 'pending-unit-lain-tidak-boleh-bocor',
            'unit_id' => $this->unitB->id,
            'document_category_id' => $this->category->id,
            'academic_year_id' => $this->academicYear->id,
            'status' => DocumentStatus::Pending,
            'current_step' => 1,
            'uploaded_by' => $stafB->id,
            'issued_date' => date('Y-m-d'),
        ]);

        $queryWithEager = PendingApprovalsWidget::getPendingQueryForUser($kaprodiA, withEagerLoads: true);
        $this->assertArrayHasKey('unit', $queryWithEager->getEagerLoads());
        $this->assertArrayNotHasKey('uploader', $queryWithEager->getEagerLoads());

        $queryCountOnly = PendingApprovalsWidget::getPendingQueryForUser($kaprodiA, withEagerLoads: false);
        $this->assertSame([], $queryCountOnly->getEagerLoads());
        $this->assertSame(1, $queryCountOnly->reorder()->count());

        $this->actingAs($kaprodiA);
        $component = Livewire::test(PendingApprovalsWidget::class);
        $component->assertSuccessful()
            ->assertSee('Menunggu Kaprodi Unit A')
            ->assertDontSee('Pending Unit Lain Tidak Boleh Bocor');

        $this->assertSame(PaginationMode::Simple, $component->instance()->getTable()->getPaginationMode());
    }

    public function test_document_lifecycle_stats_widget_counts_expiry_permanent_archived_expired_and_rejected_with_period_filter(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $permLifecycle = Permission::findOrCreate('view:document_lifecycle_stats_widget', 'web');
        $permOverview = Permission::findOrCreate('view:document_overview_widget', 'web');

        $superAdminRole = Role::findOrCreate('super_admin', 'web');
        $superAdminRole->givePermissionTo([$permLifecycle, $permOverview]);

        $stafRole = Role::findOrCreate('staf_unit', 'web');
        $stafRole->givePermissionTo([$permLifecycle, $permOverview]);

        $superAdmin = User::factory()->create(['unit_id' => $this->unitA->id, 'is_active' => true]);
        $superAdmin->assignRole($superAdminRole);

        $stafUnitA = User::factory()->create(['unit_id' => $this->unitA->id, 'is_active' => true]);
        $stafUnitA->assignRole($stafRole);

        $now = now();
        $currentMonthDate = $now->toDateString();
        $prevMonth = $now->copy()->subMonthNoOverflow();
        $prevMonthDate = $prevMonth->toDateString();

        // Dokumen bulan berjalan Unit A: bermasa berlaku & aktif
        Document::create([
            'title' => 'MoU Aktif Bulan Ini Unit A',
            'slug' => 'mou-aktif-bulan-ini-unit-a',
            'unit_id' => $this->unitA->id,
            'document_category_id' => $this->category->id,
            'academic_year_id' => $this->academicYear->id,
            'status' => DocumentStatus::Approved,
            'visibility' => VisibilityLevel::Internal,
            'has_expiry' => true,
            'expired_date' => $now->copy()->addYear()->toDateString(),
            'uploaded_by' => $superAdmin->id,
            'issued_date' => $currentMonthDate,
        ]);

        // Dokumen bulan berjalan Unit A: bermasa berlaku & kedaluwarsa (dihitung di Bermasa Berlaku DAN Arsip)
        Document::create([
            'title' => 'MoU Kedaluwarsa Bulan Ini Unit A',
            'slug' => 'mou-kedaluwarsa-bulan-ini-unit-a',
            'unit_id' => $this->unitA->id,
            'document_category_id' => $this->category->id,
            'academic_year_id' => $this->academicYear->id,
            'status' => DocumentStatus::Expired,
            'visibility' => VisibilityLevel::Internal,
            'has_expiry' => true,
            'expired_date' => $now->copy()->subDay()->toDateString(),
            'uploaded_by' => $superAdmin->id,
            'issued_date' => $currentMonthDate,
        ]);

        // Dokumen bulan berjalan Unit B: permanen & diarsipkan pasif (Archived)
        Document::create([
            'title' => 'SK Permanen Arsip Pasif Unit B',
            'slug' => 'sk-permanen-arsip-pasif-unit-b',
            'unit_id' => $this->unitB->id,
            'document_category_id' => $this->category->id,
            'academic_year_id' => $this->academicYear->id,
            'status' => DocumentStatus::Archived,
            'visibility' => VisibilityLevel::Internal,
            'has_expiry' => false,
            'uploaded_by' => $superAdmin->id,
            'issued_date' => $currentMonthDate,
        ]);

        // Dokumen bulan berjalan Unit B: permanen & ditolak
        Document::create([
            'title' => 'Usulan SK Ditolak Bulan Ini Unit B',
            'slug' => 'usulan-sk-ditolak-bulan-ini-unit-b',
            'unit_id' => $this->unitB->id,
            'document_category_id' => $this->category->id,
            'academic_year_id' => $this->academicYear->id,
            'status' => DocumentStatus::Rejected,
            'visibility' => VisibilityLevel::Internal,
            'has_expiry' => false,
            'uploaded_by' => $superAdmin->id,
            'issued_date' => $currentMonthDate,
        ]);

        // Dokumen bulan sebelumnya Unit A: permanen & disahkan (tidak boleh masuk hitungan default bulan berjalan)
        Document::create([
            'title' => 'SK Permanen Bulan Lalu Unit A',
            'slug' => 'sk-permanen-bulan-lalu-unit-a',
            'unit_id' => $this->unitA->id,
            'document_category_id' => $this->category->id,
            'academic_year_id' => $this->academicYear->id,
            'status' => DocumentStatus::Approved,
            'visibility' => VisibilityLevel::Internal,
            'has_expiry' => false,
            'uploaded_by' => $superAdmin->id,
            'issued_date' => $prevMonthDate,
        ]);

        // 1. Default bulan berjalan untuk Super Admin (Unit A + Unit B pada bulan ini saja)
        $this->actingAs($superAdmin);
        $component = Livewire::test(DocumentLifecycleStatsWidget::class);
        $component->assertSuccessful()
            ->assertSee('Bermasa Berlaku')
            ->assertSee('Dokumen Permanen')
            ->assertSee('Dokumen Arsip')
            ->assertSee('Dokumen Ditolak');

        $stats = collect($component->viewData('stats'))->keyBy('key');
        $this->assertSame(2, $stats['has_expiry']['value']);
        $this->assertSame(2, $stats['permanent']['value']);
        $this->assertSame(2, $stats['archived']['value']); // 1 Expired + 1 Archived
        $this->assertSame(1, $stats['rejected']['value']);

        // 2. Ubah pageFilters menjadi bulan lalu -> sekarang menghitung dokumen bulan lalu (1 permanen, lainnya 0)
        $prevMonthComponent = Livewire::test(DocumentLifecycleStatsWidget::class, [
            'pageFilters' => [
                'start_month' => $prevMonth->month,
                'start_year' => $prevMonth->year,
                'end_month' => $prevMonth->month,
                'end_year' => $prevMonth->year,
            ],
        ]);
        $prevStats = collect($prevMonthComponent->viewData('stats'))->keyBy('key');
        $this->assertSame(0, $prevStats['has_expiry']['value']);
        $this->assertSame(1, $prevStats['permanent']['value']);
        $this->assertSame(0, $prevStats['archived']['value']);
        $this->assertSame(0, $prevStats['rejected']['value']);

        // 3. Staf Unit A pada rentang 2 bulan hanya melihat dokumen Unit A (tidak bocor Unit B)
        $this->actingAs($stafUnitA);
        $rangeComponent = Livewire::test(DocumentLifecycleStatsWidget::class, [
            'pageFilters' => [
                'start_month' => $prevMonth->month,
                'start_year' => $prevMonth->year,
                'end_month' => $now->month,
                'end_year' => $now->year,
            ],
        ]);
        $rangeStats = collect($rangeComponent->viewData('stats'))->keyBy('key');
        $this->assertSame(2, $rangeStats['has_expiry']['value']);
        $this->assertSame(1, $rangeStats['permanent']['value']);
        $this->assertSame(1, $rangeStats['archived']['value']);
        $this->assertSame(0, $rangeStats['rejected']['value']);
    }

    public function test_dashboard_period_filter_defaults_normalization_and_cross_widget_effects(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $permissions = [
            Permission::findOrCreate('view:document_overview_widget', 'web'),
            Permission::findOrCreate('view:document_lifecycle_stats_widget', 'web'),
            Permission::findOrCreate('view:annual_published_document_chart_widget', 'web'),
            Permission::findOrCreate('view:post_period_overview_widget', 'web'),
            Permission::findOrCreate('view:pending_approvals_widget', 'web'),
        ];

        $superAdminRole = Role::findOrCreate('super_admin', 'web');
        $superAdminRole->givePermissionTo($permissions);

        $admin = User::factory()->create(['unit_id' => $this->unitA->id, 'is_active' => true]);
        $admin->assignRole($superAdminRole);

        // Uji normalisasi rentang terbalik
        $resolvedReversed = DashboardPeriodFilter::resolve([
            'start_month' => 11,
            'start_year' => 2026,
            'end_month' => 3,
            'end_year' => 2025,
        ]);
        $this->assertSame(3, $resolvedReversed['start_month']);
        $this->assertSame(2025, $resolvedReversed['start_year']);
        $this->assertSame(11, $resolvedReversed['end_month']);
        $this->assertSame(2026, $resolvedReversed['end_year']);

        $prevYear = now()->copy()->subYear();
        $prevYearDate = $prevYear->toDateString();

        Document::create([
            'title' => 'Naskah Sah Tahun Lalu',
            'slug' => 'naskah-sah-tahun-lalu',
            'unit_id' => $this->unitA->id,
            'document_category_id' => $this->category->id,
            'academic_year_id' => $this->academicYear->id,
            'status' => DocumentStatus::Approved,
            'is_borang' => true,
            'has_expiry' => false,
            'uploaded_by' => $admin->id,
            'issued_date' => $prevYearDate,
        ]);

        Document::create([
            'title' => 'Naskah Pending Bulan Ini Tetap Tampil',
            'slug' => 'naskah-pending-bulan-ini-tetap-tampil',
            'unit_id' => $this->unitA->id,
            'document_category_id' => $this->category->id,
            'academic_year_id' => $this->academicYear->id,
            'status' => DocumentStatus::Pending,
            'current_step' => 1,
            'uploaded_by' => $admin->id,
            'issued_date' => now()->toDateString(),
        ]);

        $postCategory = PostCategory::create([
            'name' => 'Berita Kampus',
            'slug' => 'berita-kampus',
            'is_active' => true,
        ]);

        Post::create([
            'title' => 'Berita Tayang Tahun Lalu',
            'slug' => 'berita-tayang-tahun-lalu',
            'post_category_id' => $postCategory->id,
            'period' => PostPeriod::Daily,
            'unit_id' => $this->unitA->id,
            'author_id' => $admin->id,
            'content' => '<p>Berita lama.</p>',
            'status' => PostStatus::Published,
            'published_at' => $prevYear->copy()->startOfDay(),
        ]);

        $this->actingAs($admin);

        // Render halaman Dashboard dan pastikan Filter Periode Statistik muncul dengan default bulan & tahun saat ini
        $dashboard = Livewire::test(Dashboard::class);
        $dashboard->assertSuccessful()
            ->assertSee('Filter Periode Statistik')
            ->assertSet('filters.start_month', now()->month)
            ->assertSet('filters.start_year', now()->year)
            ->assertSet('filters.end_month', now()->month)
            ->assertSet('filters.end_year', now()->year);

        $prevYearFilters = [
            'start_month' => $prevYear->month,
            'start_year' => $prevYear->year,
            'end_month' => $prevYear->month,
            'end_year' => $prevYear->year,
        ];

        // DocumentOverviewWidget: Menunggu Persetujuan tetap 1 (live), sedangkan Sah & Borang mengikuti filter tahun lalu
        $overviewDefault = collect(Livewire::test(DocumentOverviewWidget::class)->viewData('stats'))->keyBy('key');
        $this->assertSame(1, $overviewDefault['pending']['value']);
        $this->assertSame(0, $overviewDefault['approved']['value']);

        $overviewFiltered = collect(Livewire::test(DocumentOverviewWidget::class, ['pageFilters' => $prevYearFilters])->viewData('stats'))->keyBy('key');
        $this->assertSame(1, $overviewFiltered['pending']['value']);
        $this->assertSame(1, $overviewFiltered['approved']['value']);
        $this->assertSame(1, $overviewFiltered['borang']['value']);

        // AnnualPublishedDocumentChartWidget mengikuti tahun dari filter
        $chartFiltered = Livewire::test(AnnualPublishedDocumentChartWidget::class, ['pageFilters' => $prevYearFilters])->instance()->getViewData();
        $this->assertSame($prevYear->year, $chartFiltered['year']);
        $this->assertSame(1, $chartFiltered['totalYearly']);
        $this->assertSame(1, $chartFiltered['currentMonthCount']);

        // PostPeriodOverviewWidget mengikuti rentang filter
        $postWidgetFiltered = Livewire::test(PostPeriodOverviewWidget::class, ['pageFilters' => $prevYearFilters]);
        $postWidgetFiltered->assertSee('Tayang Periode Ini');
        $postStats = $postWidgetFiltered->viewData('stats');
        $this->assertSame(1, $postStats[0]['value']);
        $this->assertSame(1, $postStats[3]['value']);
    }
}
