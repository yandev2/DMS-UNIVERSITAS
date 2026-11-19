<?php

declare(strict_types=1);

namespace Tests\Feature\Filament;

use App\Enums\StampingPosition;
use App\Enums\UnitType;
use App\Enums\VisibilityLevel;
use App\Filament\Components\ButtonHint;
use App\Filament\Resources\DocumentCategories\Pages\CreateDocumentCategory;
use App\Filament\Resources\DocumentCategories\Pages\EditDocumentCategory;
use App\Filament\Resources\DocumentCategories\Pages\ListDocumentCategories;
use App\Filament\Resources\Documents\Pages\CreateDocument;
use App\Models\CategoryApprovalFlow;
use App\Models\DocumentCategory;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;
use ZipArchive;

class DocumentCategoryResourceTest extends TestCase
{
    use LazilyRefreshDatabase;

    private User $adminUser;

    private Role $adminRole;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('private');
        Storage::fake('public');

        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $permissions = [
            'view_any:document_category',
            'view:document_category',
            'create:document_category',
            'update:document_category',
            'delete:document_category',
        ];

        foreach ($permissions as $perm) {
            Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
        }

        $this->adminRole = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        $this->adminRole->syncPermissions($permissions);

        $this->adminUser = User::factory()->create([
            'is_active' => true,
        ]);
        $this->adminUser->assignRole($this->adminRole);
    }

    public function test_document_category_list_page_can_be_rendered(): void
    {
        $this->actingAs($this->adminUser);

        Livewire::test(ListDocumentCategories::class)
            ->assertSuccessful();
    }

    public function test_document_category_table_displays_template_indicator_and_action(): void
    {
        $categoryWithTemplate = DocumentCategory::create([
            'name' => 'Surat Keputusan (SK) Rektor',
            'slug' => 'surat-keputusan-sk-rektor',
            'code' => 'SK',
            'stamping_position' => StampingPosition::TopRight,
            'default_visibility' => VisibilityLevel::Internal,
            'requires_approval' => true,
            'is_active' => true,
            'template_file_path' => 'document-templates/template_sk.docx',
            'template_file_name' => 'Template_SK_Rektor_Resmi.docx',
        ]);

        $categoryWithoutTemplate = DocumentCategory::create([
            'name' => 'Surat Tugas (ST)',
            'slug' => 'surat-tugas-st',
            'code' => 'ST',
            'stamping_position' => StampingPosition::TopRight,
            'default_visibility' => VisibilityLevel::Internal,
            'requires_approval' => false,
            'is_active' => true,
            'template_file_path' => null,
            'template_file_name' => null,
        ]);

        $this->actingAs($this->adminUser);

        Livewire::test(ListDocumentCategories::class)
            ->assertSuccessful()
            ->assertCanSeeTableRecords([$categoryWithTemplate, $categoryWithoutTemplate])
            ->assertTableActionVisible('download_template', $categoryWithTemplate)
            ->assertTableActionHidden('download_template', $categoryWithoutTemplate);
    }

    public function test_can_upload_template_word_when_creating_category(): void
    {
        $this->actingAs($this->adminUser);

        $realDocxBytes = $this->createValidDocxBinary('Template Resmi BAST');
        $validWordFile = UploadedFile::fake()->createWithContent('Template_BAST_Barang.docx', $realDocxBytes);

        Livewire::test(CreateDocumentCategory::class)
            ->fillForm([
                'name' => 'Berita Acara Serah Terima',
                'slug' => 'berita-acara-serah-terima',
                'code' => 'BAST',
                'default_visibility' => VisibilityLevel::Internal,
                'number_format' => '{seq}/UNIVBI.{unit}/{cat}/{roman_month}/{year}',
                'template_file_path' => $validWordFile,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $category = DocumentCategory::where('code', 'BAST')->first();
        $this->assertNotNull($category);
        $this->assertTrue($category->hasTemplate());
        $this->assertNotNull($category->template_file_path);
        $this->assertEquals('Template_BAST_Barang.docx', $category->template_file_name);

        Storage::disk('private')->assertExists($category->template_file_path);
    }

    public function test_can_download_category_template_file_securely(): void
    {
        Storage::disk('private')->put('document-templates/sample_template.docx', 'WORD_BINARY_CONTENT');

        $category = DocumentCategory::create([
            'name' => 'Nota Dinas Internal',
            'slug' => 'nota-dinas-internal',
            'code' => 'ND',
            'stamping_position' => StampingPosition::TopRight,
            'default_visibility' => VisibilityLevel::Internal,
            'requires_approval' => false,
            'is_active' => true,
            'template_file_path' => 'document-templates/sample_template.docx',
            'template_file_name' => 'Template_Nota_Dinas_UNIVBI.docx',
        ]);

        $this->actingAs($this->adminUser);

        $response = $this->get(route('admin.document-categories.download-template', $category));

        $response->assertOk();
        $response->assertHeader('content-disposition', 'attachment; filename=Template_Nota_Dinas_UNIVBI.docx');
    }

    public function test_download_template_fails_if_category_has_no_template(): void
    {
        $category = DocumentCategory::create([
            'name' => 'Surat Edaran',
            'slug' => 'surat-edaran',
            'code' => 'SE',
            'stamping_position' => StampingPosition::TopRight,
            'default_visibility' => VisibilityLevel::Internal,
            'requires_approval' => false,
            'is_active' => true,
            'template_file_path' => null,
            'template_file_name' => null,
        ]);

        $this->actingAs($this->adminUser);

        $response = $this->get(route('admin.document-categories.download-template', $category));

        $response->assertNotFound();
    }

    public function test_download_template_requires_authentication(): void
    {
        Storage::disk('private')->put('document-templates/sample.docx', 'CONTENT');

        $category = DocumentCategory::create([
            'name' => 'Surat Pengantar',
            'slug' => 'surat-pengantar',
            'code' => 'SP',
            'stamping_position' => StampingPosition::TopRight,
            'default_visibility' => VisibilityLevel::Internal,
            'requires_approval' => false,
            'is_active' => true,
            'template_file_path' => 'document-templates/sample.docx',
            'template_file_name' => 'Template_SP.docx',
        ]);

        // Unauthenticated request should be forbidden or redirected
        $response = $this->get(route('admin.document-categories.download-template', $category));

        $this->assertTrue(in_array($response->getStatusCode(), [302, 403], true));
    }

    public function test_button_hint_creates_custom_modal_content_action(): void
    {
        $action = ButtonHint::make('Keterangan singkat pedoman naskah dinas.', 'Panduan Singkat');

        $this->assertEquals('hint_panduan_singkat', $action->getName());
        $this->assertEquals('Panduan Singkat', $action->getModalHeading());
        $this->assertNull($action->getModalDescription());
        $this->assertNotNull($action->getModalContent());
    }

    public function test_button_hint_parses_complex_numbered_token_message(): void
    {
        $message = "PANDUAN LENGKAP TOKEN & TAG ALTERNATIF BERKAS:\n\n1. Token Format Penomoran:\n   • {seq} : Nomor urut 3 digit (contoh: 001)\n   • {unit} : Kode unit kerja pengunggah\n\n2. Tag Alternatif pada Dokumen:\n   • Nomor Surat : {nomor_surat}, \${NOMOR_SURAT}";

        $parsed = ButtonHint::parseContent($message);

        $this->assertEquals('numbered_sections', $parsed['type']);
        $this->assertTrue($parsed['is_complex']);
        $this->assertTrue($parsed['has_tokens']);
        $this->assertCount(2, $parsed['sections']);
        $this->assertEquals('Token Format Penomoran', $parsed['sections'][0]['title']);
        $this->assertCount(2, $parsed['sections'][0]['items']);

        // Test rendering the blade view with the parsed data
        $renderedHtml = view('filament.components.hint-modal', [
            'title' => 'Panduan Pola Penomoran & Tag Berkas',
            'rawMessage' => $message,
            'data' => $parsed,
        ])->render();

        $this->assertStringContainsString('Token Format Penomoran', $renderedHtml);
        $this->assertStringContainsString('{seq}', $renderedHtml);
        $this->assertStringContainsString('hint-chip', $renderedHtml);
        $this->assertStringContainsString('copyToken', $renderedHtml);
    }

    public function test_replacing_clearing_and_deleting_category_removes_old_template_files(): void
    {
        Storage::disk('private')->put('document-templates/lama.docx', 'OLD_DOCX');
        Storage::disk('private')->put('document-templates/baru.docx', 'NEW_DOCX');

        $category = DocumentCategory::create([
            'name' => 'Surat Keputusan Uji',
            'slug' => 'surat-keputusan-uji',
            'code' => 'SKU',
            'default_visibility' => VisibilityLevel::Internal,
            'is_active' => true,
            'template_file_path' => 'document-templates/lama.docx',
            'template_file_name' => 'Template_Lama.docx',
        ]);

        // 1. Ganti dengan template baru -> berkas lama harus terhapus
        $category->update([
            'template_file_path' => 'document-templates/baru.docx',
            'template_file_name' => 'Template_Baru.docx',
        ]);

        Storage::disk('private')->assertMissing('document-templates/lama.docx');
        Storage::disk('private')->assertExists('document-templates/baru.docx');

        // 2. Kosongkan template -> berkas baru terhapus dan template_file_name ikut reset null
        $category->update([
            'template_file_path' => null,
        ]);

        $category->refresh();
        Storage::disk('private')->assertMissing('document-templates/baru.docx');
        $this->assertNull($category->template_file_name);

        // 3. Hapus kategori -> berkas template aktif ikut terhapus
        Storage::disk('private')->put('document-templates/akhir.docx', 'FINAL_DOCX');
        $category->update([
            'template_file_path' => 'document-templates/akhir.docx',
            'template_file_name' => 'Template_Akhir.docx',
        ]);

        $category->delete();
        Storage::disk('private')->assertMissing('document-templates/akhir.docx');
    }

    public function test_document_form_shows_download_button_only_when_physical_template_exists(): void
    {
        Permission::firstOrCreate(['name' => 'create:document', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'view:document', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'view_any:document', 'guard_name' => 'web']);
        $this->adminRole->givePermissionTo(['create:document', 'view:document', 'view_any:document']);

        $unit = Unit::create([
            'name' => 'UPT ICT',
            'code' => 'ICT',
            'type' => UnitType::Rektorat,
            'is_active' => true,
        ]);
        $this->adminUser->update(['unit_id' => $unit->id]);

        Storage::disk('private')->put('document-templates/se_resmi.docx', 'SE_DOCX');

        $categoryAvailable = DocumentCategory::create([
            'name' => 'Surat Edaran Resmi',
            'slug' => 'surat-edaran-resmi',
            'code' => 'SE',
            'default_visibility' => VisibilityLevel::Internal,
            'is_active' => true,
            'template_file_path' => 'document-templates/se_resmi.docx',
            'template_file_name' => 'Template_SE_Resmi.docx',
        ]);

        $categoryMissingFile = DocumentCategory::create([
            'name' => 'Surat Tugas Hilang',
            'slug' => 'surat-tugas-hilang',
            'code' => 'STH',
            'default_visibility' => VisibilityLevel::Internal,
            'is_active' => true,
            'template_file_path' => 'document-templates/tidak_ada.docx',
            'template_file_name' => 'Template_Hilang.docx',
        ]);

        $this->actingAs($this->adminUser);

        $livewire = Livewire::test(CreateDocument::class)
            ->assertOk()
            ->set('data.document_category_id', $categoryAvailable->id);

        $this->assertStringContainsString('Unduh Template Word', $livewire->html());
        $this->assertStringContainsString('Template_SE_Resmi.docx', $livewire->html());

        // Jika file fisik tidak ada di storage, jangan tampilkan tombol mati 404
        $livewire->set('data.document_category_id', $categoryMissingFile->id);
        $missingHtml = $livewire->html();

        $this->assertStringNotContainsString('Template_Hilang.docx', $missingHtml);
        $this->assertStringContainsString('belum tersedia di penyimpanan server', $missingHtml);
    }

    public function test_user_with_create_document_permission_can_download_template_without_master_category_permission(): void
    {
        Permission::firstOrCreate(['name' => 'create:document', 'guard_name' => 'web']);
        $operatorRole = Role::firstOrCreate(['name' => 'operator_khusus', 'guard_name' => 'web']);
        $operatorRole->syncPermissions(['create:document']);

        $operator = User::factory()->create(['is_active' => true]);
        $operator->assignRole($operatorRole);

        $noAccessUser = User::factory()->create(['is_active' => true]);

        Storage::disk('private')->put('document-templates/sk_resmi.docx', 'SK_DOCX');

        $category = DocumentCategory::create([
            'name' => 'SK Rektor',
            'slug' => 'sk-rektor',
            'code' => 'SKR',
            'default_visibility' => VisibilityLevel::Internal,
            'is_active' => true,
            'template_file_path' => 'document-templates/sk_resmi.docx',
            'template_file_name' => 'Template_SK.docx',
        ]);

        $this->actingAs($operator)
            ->get(route('admin.document-categories.download-template', $category))
            ->assertOk();

        $this->actingAs($noAccessUser)
            ->get(route('admin.document-categories.download-template', $category))
            ->assertForbidden();
    }

    public function test_can_upload_valid_legacy_doc_and_rejects_non_word_or_spoofed_files(): void
    {
        $this->actingAs($this->adminUser);

        // 1. Tolak file PDF asli
        $pdfFile = UploadedFile::fake()->createWithContent('bukan-word.pdf', '%PDF-1.4 sample content');
        Livewire::test(CreateDocumentCategory::class)
            ->fillForm([
                'name' => 'Uji Tolak PDF',
                'slug' => 'uji-tolak-pdf',
                'code' => 'PDFTEST',
                'default_visibility' => VisibilityLevel::Internal,
                'number_format' => '{seq}/UNIVBI.{unit}/{cat}/{roman_month}/{year}',
                'template_file_path' => $pdfFile,
            ])
            ->call('create')
            ->assertHasFormErrors(['template_file_path']);

        // 2. Tolak file Excel/ZIP yang diganti ekstensinya menjadi .docx
        $spoofedZipPath = tempnam(sys_get_temp_dir(), 'fake_docx_').'.docx';
        $zip = new ZipArchive;
        $zip->open($spoofedZipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString('[Content_Types].xml', '<Types/>');
        $zip->addFromString('xl/workbook.xml', '<workbook/>');
        $zip->close();
        $spoofedBytes = (string) file_get_contents($spoofedZipPath);
        @unlink($spoofedZipPath);

        $spoofedDocx = UploadedFile::fake()->createWithContent('excel-palsu.docx', $spoofedBytes);
        Livewire::test(CreateDocumentCategory::class)
            ->fillForm([
                'name' => 'Uji Tolak Spoofed DOCX',
                'slug' => 'uji-tolak-spoofed-docx',
                'code' => 'SPOOF',
                'default_visibility' => VisibilityLevel::Internal,
                'number_format' => '{seq}/UNIVBI.{unit}/{cat}/{roman_month}/{year}',
                'template_file_path' => $spoofedDocx,
            ])
            ->call('create')
            ->assertHasFormErrors(['template_file_path']);

        // 3. Tolak .docx yang disisipi macro VBA (word/vbaProject.bin)
        $macroZipPath = tempnam(sys_get_temp_dir(), 'macro_docx_').'.docx';
        file_put_contents($macroZipPath, $this->createValidDocxBinary('Template Berbahaya'));
        $zipMacro = new ZipArchive;
        $zipMacro->open($macroZipPath);
        $zipMacro->addFromString('word/vbaProject.bin', 'FAKE_VBA_MACRO');
        $zipMacro->close();
        $macroBytes = (string) file_get_contents($macroZipPath);
        @unlink($macroZipPath);

        $macroDocx = UploadedFile::fake()->createWithContent('template-macro.docx', $macroBytes);
        Livewire::test(CreateDocumentCategory::class)
            ->fillForm([
                'name' => 'Uji Tolak Macro',
                'slug' => 'uji-tolak-macro',
                'code' => 'MACRO',
                'default_visibility' => VisibilityLevel::Internal,
                'number_format' => '{seq}/UNIVBI.{unit}/{cat}/{roman_month}/{year}',
                'template_file_path' => $macroDocx,
            ])
            ->call('create')
            ->assertHasFormErrors(['template_file_path']);

        // 4. Terima file .doc legacy dengan signature biner OLE2 yang sah
        $validDocBinary = "\xD0\xCF\x11\xE0\xA1\xB1\x1A\xE1".str_repeat("\x00", 504);
        $validLegacyDoc = UploadedFile::fake()->createWithContent('Template_Surat_Legacy.doc', $validDocBinary);
        Livewire::test(CreateDocumentCategory::class)
            ->fillForm([
                'name' => 'Surat Legacy DOC',
                'slug' => 'surat-legacy-doc',
                'code' => 'DOC97',
                'default_visibility' => VisibilityLevel::Internal,
                'number_format' => '{seq}/UNIVBI.{unit}/{cat}/{roman_month}/{year}',
                'template_file_path' => $validLegacyDoc,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertNotNull(DocumentCategory::where('code', 'DOC97')->first()?->template_file_path);
    }

    public function test_can_prepend_swap_and_replace_approval_flows_on_edit_without_unique_constraint_violation(): void
    {
        Role::firstOrCreate(['name' => 'humas', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'warek', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'rektor', 'guard_name' => 'web']);

        $category = DocumentCategory::create([
            'name' => 'SK Rektor TTD Basah',
            'slug' => 'sk-rektor-ttd-basah',
            'code' => 'SKRB',
            'stamping_position' => StampingPosition::TopRight,
            'default_visibility' => VisibilityLevel::Internal,
            'number_format' => '{seq}/UNIVBI.{unit}/{cat}/{roman_month}/{year}',
            'requires_approval' => true,
            'requires_wet_signature' => true,
            'is_active' => true,
        ]);

        $existingFlow = CategoryApprovalFlow::create([
            'document_category_id' => $category->id,
            'step_order' => 1,
            'approver_role' => 'warek',
            'step_label' => 'Persetujuan Warek',
            'is_mandatory' => true,
        ]);

        $this->actingAs($this->adminUser);

        // Skenario 1: Tambahkan tahap baru (Humas) di posisi ke-1 (mendahului tahap Warek yg sudah step_order=1)
        Livewire::test(EditDocumentCategory::class, ['record' => $category->getKey()])
            ->set('data.approvalFlows', [
                'new-humas-uuid' => [
                    'step_order' => 1,
                    'approver_role' => 'humas',
                    'step_label' => 'Tinjauan Surat',
                    'is_mandatory' => true,
                ],
                "record-{$existingFlow->id}" => [
                    'step_order' => 2,
                    'approver_role' => 'warek',
                    'step_label' => 'Persetujuan Warek',
                    'is_mandatory' => true,
                ],
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $flowsAfterPrepend = CategoryApprovalFlow::query()
            ->where('document_category_id', $category->id)
            ->orderBy('step_order')
            ->get();

        $this->assertCount(2, $flowsAfterPrepend);
        $this->assertSame('humas', $flowsAfterPrepend[0]->approver_role);
        $this->assertSame(1, $flowsAfterPrepend[0]->step_order);
        $this->assertSame('warek', $flowsAfterPrepend[1]->approver_role);
        $this->assertSame(2, $flowsAfterPrepend[1]->step_order);

        // Skenario 2: Tukar urutan existing (Warek menjadi 1, Humas menjadi 2) dan tambah Rektor (3)
        Livewire::test(EditDocumentCategory::class, ['record' => $category->getKey()])
            ->set('data.approvalFlows', [
                "record-{$flowsAfterPrepend[1]->id}" => [
                    'step_order' => 1,
                    'approver_role' => 'warek',
                    'step_label' => 'Persetujuan Warek',
                    'is_mandatory' => true,
                ],
                "record-{$flowsAfterPrepend[0]->id}" => [
                    'step_order' => 2,
                    'approver_role' => 'humas',
                    'step_label' => 'Tinjauan Surat',
                    'is_mandatory' => true,
                ],
                'new-rektor-uuid' => [
                    'step_order' => 3,
                    'approver_role' => 'rektor',
                    'step_label' => 'Pengesahan Rektor',
                    'is_mandatory' => true,
                ],
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $flowsAfterSwap = CategoryApprovalFlow::query()
            ->where('document_category_id', $category->id)
            ->orderBy('step_order')
            ->get();

        $this->assertCount(3, $flowsAfterSwap);
        $this->assertSame(['warek', 'humas', 'rektor'], $flowsAfterSwap->pluck('approver_role')->all());
        $this->assertSame([1, 2, 3], $flowsAfterSwap->pluck('step_order')->all());

        // Skenario 3: Muat ulang EditDocumentCategory -> nilai form step_order pada UI wajib 1, 2, 3 (bukan 21)
        $reopened = Livewire::test(EditDocumentCategory::class, ['record' => $category->getKey()])
            ->assertSuccessful();

        $formApprovalFlows = array_values($reopened->get('data.approvalFlows'));
        $this->assertSame([1, 2, 3], array_map(
            static fn (array $item): int => (int) ($item['step_order'] ?? 0),
            $formApprovalFlows
        ));
    }

    public function test_document_category_supports_global_default_and_optional_multi_unit_scoping(): void
    {
        $unitSdm = Unit::create([
            'code' => 'SDM',
            'name' => 'Biro SDM',
            'slug' => 'biro-sdm',
            'type' => UnitType::Biro,
            'is_active' => true,
        ]);

        $unitTi = Unit::create([
            'code' => 'TI',
            'name' => 'Prodi Informatika',
            'slug' => 'prodi-informatika',
            'type' => UnitType::Prodi,
            'is_active' => true,
        ]);

        $this->actingAs($this->adminUser);

        // 1. Kategori tanpa unit (Global Default) -> tersedia untuk semua unit
        Livewire::test(CreateDocumentCategory::class)
            ->fillForm([
                'name' => 'Berita Acara Umum',
                'slug' => 'berita-acara-umum',
                'code' => 'BAU',
                'default_visibility' => VisibilityLevel::Internal,
                'number_format' => '{seq}/UNIVBI.{unit}/{cat}/{roman_month}/{year}',
                'allowedUnits' => [],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $globalCategory = DocumentCategory::where('code', 'BAU')->firstOrFail();
        $this->assertTrue($globalCategory->isAllowedForUnit($unitSdm));
        $this->assertTrue($globalCategory->isAllowedForUnit($unitTi));

        // 2. Kategori khusus SDM -> hanya tersedia untuk unit SDM
        Livewire::test(CreateDocumentCategory::class)
            ->fillForm([
                'name' => 'SK Kepegawaian Khusus SDM',
                'slug' => 'sk-kepegawaian-sdm',
                'code' => 'SKP',
                'default_visibility' => VisibilityLevel::Internal,
                'number_format' => '{seq}/UNIVBI.{unit}/{cat}/{roman_month}/{year}',
                'allowedUnits' => [$unitSdm->id],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $sdmCategory = DocumentCategory::where('code', 'SKP')->firstOrFail();
        $this->assertTrue($sdmCategory->isAllowedForUnit($unitSdm));
        $this->assertFalse($sdmCategory->isAllowedForUnit($unitTi));

        // Query scope availableForUnit
        $forSdm = DocumentCategory::query()->availableForUnit($unitSdm)->pluck('code')->all();
        $forTi = DocumentCategory::query()->availableForUnit($unitTi)->pluck('code')->all();

        $this->assertContains('BAU', $forSdm);
        $this->assertContains('SKP', $forSdm);
        $this->assertContains('BAU', $forTi);
        $this->assertNotContains('SKP', $forTi);

        // Tabel daftar kategori menampilkan badge cakupan unit & label Semua Unit
        Livewire::test(ListDocumentCategories::class)
            ->assertSuccessful()
            ->assertSee('Semua Unit')
            ->assertSee('SDM');
    }

    private function createValidDocxBinary(string $text): string
    {
        $phpWord = new PhpWord;
        $phpWord->addSection()->addText($text);
        $tempPath = tempnam(sys_get_temp_dir(), 'tpl_word_').'.docx';
        IOFactory::createWriter($phpWord, 'Word2007')->save($tempPath);

        try {
            return (string) file_get_contents($tempPath);
        } finally {
            @unlink($tempPath);
        }
    }
}
