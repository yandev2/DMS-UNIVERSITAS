<?php

namespace Tests\Feature\Seeders;

use App\Enums\DocumentStatus;
use App\Enums\UnitType;
use App\Enums\VisibilityLevel;
use App\Models\AcademicYear;
use App\Models\AccreditationCriterion;
use App\Models\Document;
use App\Models\DocumentCategory;
use App\Models\IncomingLetter;
use App\Models\OutgoingLetter;
use App\Models\Permission;
use App\Models\Post;
use App\Models\PostCategory;
use App\Models\Role;
use App\Models\Setting;
use App\Models\Unit;
use App\Models\User;
use App\Services\DocumentApprovalService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Support\LegacyMasterDataSeeder;
use Tests\TestCase;

class DatabaseSeederTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_default_database_seeder_populates_official_univbi_structure_and_categories(): void
    {
        $this->seed(DatabaseSeeder::class);

        // Tepat 10 role fungsional sesuai matriks otorisasi UNIVBI
        $this->assertSame(10, Role::count());
        $expectedRoles = [
            'super_admin',
            'rektor',
            'warek',
            'ketua_spmi',
            'staf_spmi',
            'humas',
            'dekan',
            'kaprodi',
            'kepala_unit',
            'staf_unit',
        ];
        foreach ($expectedRoles as $roleName) {
            $this->assertTrue(Role::where('name', $roleName)->exists(), "Role {$roleName} harus tersedia.");
        }

        // Tepat 25 unit kerja hierarkis & MM berada di bawah FESH
        $this->assertSame(25, Unit::count());
        $fesh = Unit::where('code', 'FESH')->firstOrFail();
        $mm = Unit::where('code', 'MM')->firstOrFail();
        $this->assertSame($fesh->id, $mm->parent_id);

        // Tepat 57 akun pegawai aktif (mengecualikan 10 staf keamanan, kebersihan, & driver)
        $this->assertSame(57, User::count());

        // Hanya 1 akun super_admin yaitu Rian Dinata, S.Kom (tanpa akun admin@univbi.ac.id ganda)
        $this->assertSame(1, User::role('super_admin')->count());
        $this->assertNull(User::where('email', 'admin@univbi.ac.id')->first());

        $superAdmin = User::where('email', 'riandinata@univbinainsan.ac.id')->firstOrFail();
        $this->assertTrue($superAdmin->hasRole('super_admin'));
        $this->assertTrue($superAdmin->is_active);
        $this->assertSame(Unit::where('code', 'HUMAS')->firstOrFail()->id, $superAdmin->unit_id);

        // Tri Hasanah memegang multi-role kepala_unit + humas
        $triHasanah = User::where('email', 'trihasanah@univbinainsan.ac.id')->firstOrFail();
        $this->assertTrue($triHasanah->hasRole('kepala_unit'));
        $this->assertTrue($triHasanah->hasRole('humas'));

        // Kaprodi diberikan hak konfigurasi akses asesor borang
        $kaprodiTi = User::where('email', 'budisantoso@univbinainsan.ac.id')->firstOrFail();
        $this->assertTrue($kaprodiTi->hasRole('kaprodi'));
        $this->assertTrue($kaprodiTi->can('borang.configure_assessor'));

        // Tepat 11 kategori dokumen umum tersedia & terkonfigurasi lengkap
        $this->assertSame(11, DocumentCategory::count());
        foreach (['sk-rektor', 'sk-dekan', 'se-rektorat', 'st-fakultas', 'st-unit', 'mou', 'nota-dinas'] as $letterSlug) {
            $category = DocumentCategory::where('slug', $letterSlug)->with('approvalFlows')->firstOrFail();
            $this->assertTrue($category->requires_approval);
            $this->assertSame('humas', $category->approvalFlows->first()?->approver_role, "Kategori surat {$letterSlug} wajib melewati Humas pada tahap 1.");
        }

        // Pengaturan default TTD basah & cakupan multi-unit
        foreach (['sk-rektor', 'sk-dekan', 'se-rektorat', 'mou'] as $wetSlug) {
            $this->assertTrue(DocumentCategory::where('slug', $wetSlug)->firstOrFail()->requires_wet_signature);
        }
        $this->assertCount(13, DocumentCategory::where('slug', 'sk-dekan')->firstOrFail()->allowedUnits);
        $this->assertCount(13, DocumentCategory::where('slug', 'st-fakultas')->firstOrFail()->allowedUnits);
        $this->assertCount(12, DocumentCategory::where('slug', 'st-unit')->firstOrFail()->allowedUnits);
        $this->assertCount(9, DocumentCategory::where('slug', 'rps')->firstOrFail()->allowedUnits);
        $this->assertCount(0, DocumentCategory::where('slug', 'sk-rektor')->firstOrFail()->allowedUnits);

        // Data transaksional & konfigurasi operasional lainnya masih bersih dari dummy data
        $this->assertSame(0, AcademicYear::count());
        $this->assertSame(0, AccreditationCriterion::count());
        $this->assertSame(0, PostCategory::count());
        $this->assertSame(0, Setting::count());
        $this->assertSame(0, Document::count());
        $this->assertSame(0, Post::count());
        $this->assertSame(0, IncomingLetter::count());
        $this->assertSame(0, OutgoingLetter::count());

        // Super admin otomatis memiliki akses ke seluruh izin termasuk kemampuan baru via Gate::before
        $this->assertTrue($superAdmin->can('system.backup_manage'));
        $this->assertTrue($superAdmin->can('archive.bulk_export_zip'));
        $this->assertTrue($superAdmin->can('future.unseeded.arbitrary.permission'));

        $this->actingAs($superAdmin);
        $this->get('/admin')->assertSuccessful();
        $this->get('/admin/units')->assertSuccessful();
        $this->get('/admin/document-categories')->assertSuccessful();
        $this->get('/admin/documents')->assertSuccessful();
        $this->get('/admin/shield/roles')->assertSuccessful();
    }

    public function test_master_data_seeder_populates_official_units_roles_criteria_and_settings(): void
    {
        $this->seed(LegacyMasterDataSeeder::class);

        $this->assertSame(19, Unit::count());
        $this->assertSame(9, Unit::where('type', UnitType::Prodi)->count());
        $this->assertSame(12, Role::count());
        $this->assertSame(158, Permission::count());
        $this->assertSame(27, AccreditationCriterion::count());
        $this->assertSame(5, AcademicYear::count());
        $this->assertSame(4, Setting::count());
    }

    public function test_optional_approval_steps_are_skipped_cleanly_when_uploader_unit_lacks_optional_role(): void
    {
        Storage::fake('private');

        $this->seed(DatabaseSeeder::class);

        $approvalService = app(DocumentApprovalService::class);

        $skDekan = DocumentCategory::where('slug', 'sk-dekan')->firstOrFail();
        $stUnit = DocumentCategory::where('slug', 'st-unit')->firstOrFail();

        // 1. SK Dekan dari Prodi MM -> Humas (1) -> Kaprodi (2) -> Dekan (3)
        $mm = Unit::where('code', 'MM')->firstOrFail();
        $stafMm = User::where('email', 'rudiirawan@univbinainsan.ac.id')->firstOrFail();
        $docMm = Document::create([
            'title' => 'SK Panitia Yudisium Prodi MM',
            'unit_id' => $mm->id,
            'document_category_id' => $skDekan->id,
            'visibility' => VisibilityLevel::Internal,
            'status' => DocumentStatus::Draft,
            'uploaded_by' => $stafMm->id,
            'issued_date' => now()->toDateString(),
            'requires_wet_signature' => true,
        ]);

        $approvalService->initializeApprovalFlow($docMm);
        $docMm->refresh();

        $this->assertSame(3, $docMm->total_steps);
        $this->assertSame(['humas', 'kaprodi', 'dekan'], $docMm->approvals()->orderBy('step_order')->pluck('approver_role')->all());

        // 2. SK Dekan langsung dari Fakultas FESH -> skips Kaprodi -> Humas (1) -> Dekan (2)
        $fesh = Unit::where('code', 'FESH')->firstOrFail();
        $stafFesh = User::where('email', 'andesefiriansa@univbinainsan.ac.id')->firstOrFail();
        $docFesh = Document::create([
            'title' => 'SK Pengelola Jurnal FESH',
            'unit_id' => $fesh->id,
            'document_category_id' => $skDekan->id,
            'visibility' => VisibilityLevel::Internal,
            'status' => DocumentStatus::Draft,
            'uploaded_by' => $stafFesh->id,
            'issued_date' => now()->toDateString(),
            'requires_wet_signature' => true,
        ]);

        $approvalService->initializeApprovalFlow($docFesh);
        $docFesh->refresh();

        $this->assertSame(2, $docFesh->total_steps);
        $this->assertSame(['humas', 'dekan'], $docFesh->approvals()->orderBy('step_order')->pluck('approver_role')->all());

        // 3. ST Unit dari BAAK -> Humas (1) -> Kepala Unit (2) -> Warek (3)
        $baak = Unit::where('code', 'BAAK')->firstOrFail();
        $stafBaak = User::where('email', 'rahmaddani@univbinainsan.ac.id')->firstOrFail();
        $docBaak = Document::create([
            'title' => 'ST Pelatihan PDDIKTI BAAK',
            'unit_id' => $baak->id,
            'document_category_id' => $stUnit->id,
            'visibility' => VisibilityLevel::Internal,
            'status' => DocumentStatus::Draft,
            'uploaded_by' => $stafBaak->id,
            'issued_date' => now()->toDateString(),
        ]);

        $approvalService->initializeApprovalFlow($docBaak);
        $docBaak->refresh();

        $this->assertSame(3, $docBaak->total_steps);
        $this->assertSame(['humas', 'kepala_unit', 'warek'], $docBaak->approvals()->orderBy('step_order')->pluck('approver_role')->all());

        // 4. ST Unit dari Staf REKTORAT -> skips Kepala Unit -> Humas (1) -> Warek (2)
        $rektorat = Unit::where('code', 'REKTORAT')->firstOrFail();
        $stafRektor = User::where('email', 'raniryani@univbinainsan.ac.id')->firstOrFail();
        $docRektorat = Document::create([
            'title' => 'ST Protokoler Rektorat',
            'unit_id' => $rektorat->id,
            'document_category_id' => $stUnit->id,
            'visibility' => VisibilityLevel::Internal,
            'status' => DocumentStatus::Draft,
            'uploaded_by' => $stafRektor->id,
            'issued_date' => now()->toDateString(),
        ]);

        $approvalService->initializeApprovalFlow($docRektorat);
        $docRektorat->refresh();

        $this->assertSame(2, $docRektorat->total_steps);
        $this->assertSame(['humas', 'warek'], $docRektorat->approvals()->orderBy('step_order')->pluck('approver_role')->all());
    }
}
