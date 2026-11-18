<?php

declare(strict_types=1);

namespace Tests\Feature\Filament;

use App\Enums\DocumentStatus;
use App\Enums\StorageDisk;
use App\Filament\Pages\AccreditationDataBank;
use App\Jobs\GenerateBorangExportJob;
use App\Models\AcademicYear;
use App\Models\AccreditationCriterion;
use App\Models\BulkExport;
use App\Models\Document;
use App\Models\DocumentCategory;
use App\Models\DocumentFile;
use App\Models\Unit;
use App\Models\User;
use App\Services\BorangExportService;
use App\Services\BorangQueuedExportService;
use App\Services\BorangZipExportService;
use Database\Seeders\MasterDataSeeder;
use Filament\Tables\Enums\PaginationMode;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class AccreditationDataBankTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function createBorangDocument(Unit $unit): Document
    {
        $criterionK6 = $unit->getApplicableCriteria()->firstWhere('code', 'K6')
            ?? AccreditationCriterion::where('code', 'K6')->firstOrFail();
        $category = DocumentCategory::firstOrFail();
        $academicYear = AcademicYear::firstOrFail();
        $uploader = User::firstOrFail();

        $document = Document::create([
            'title' => 'SK Kurikulum Informatika 2026',
            'document_number' => '001/UNIVBI.TI/SK/XI/2026',
            'unit_id' => $unit->id,
            'document_category_id' => $category->id,
            'academic_year_id' => $academicYear->id,
            'is_borang' => true,
            'status' => DocumentStatus::Approved,
            'issued_date' => now()->toDateString(),
            'uploaded_by' => $uploader->id,
        ]);

        $document->accreditationCriteria()->attach($criterionK6->id, [
            'indicator_code' => 'Butir 6.a',
            'order_index' => 1,
        ]);

        $dummyPdfPath = 'documents/2026/11/TI/test-sk-kurikulum.pdf';
        Storage::disk(StorageDisk::Private->value)->put($dummyPdfPath, '%PDF-1.4 dummy content');

        DocumentFile::create([
            'document_id' => $document->id,
            'disk' => StorageDisk::Private,
            'original_file_path' => $dummyPdfPath,
            'original_file_name' => 'test-sk-kurikulum.pdf',
            'standardized_file_name' => '2026-TI-SK-test.pdf',
            'file_extension' => 'pdf',
            'file_hash_sha256' => hash('sha256', 'dummy'),
            'file_size' => 1024,
            'mime_type' => 'application/pdf',
            'is_primary' => true,
            'uploaded_by' => $uploader->id,
        ]);

        return $document;
    }

    public function test_super_admin_and_lpm_can_access_accreditation_data_bank_page(): void
    {
        $this->seed(MasterDataSeeder::class);

        $superAdmin = User::where('email', 'admin@univbi.ac.id')->firstOrFail();
        $this->actingAs($superAdmin)
            ->get('/admin/accreditation-data-bank')
            ->assertOk();

        $lpmUser = User::where('email', 'lpm@univbi.ac.id')->firstOrFail();
        $this->actingAs($lpmUser)
            ->get('/admin/accreditation-data-bank')
            ->assertOk();

        $stafTi = User::where('email', 'staf.ti@univbi.ac.id')->firstOrFail();
        $this->actingAs($stafTi)
            ->get('/admin/accreditation-data-bank')
            ->assertForbidden();
    }

    public function test_accreditation_data_bank_renders_borang_records_in_table(): void
    {
        $this->seed(MasterDataSeeder::class);
        Storage::fake(StorageDisk::Private->value);

        $superAdmin = User::where('email', 'admin@univbi.ac.id')->firstOrFail();
        $this->actingAs($superAdmin);

        $unitTi = Unit::where('code', 'TI')->firstOrFail();
        $document = $this->createBorangDocument($unitTi);

        Livewire::test(AccreditationDataBank::class)
            ->assertCanSeeTableRecords([$document])
            ->assertSee('001/UNIVBI.TI/SK/XI/2026')
            ->assertSee('SK Kurikulum Informatika 2026')
            ->assertSee('K6');
    }

    public function test_can_configure_assessor_access_via_header_action(): void
    {
        $this->seed(MasterDataSeeder::class);

        $superAdmin = User::where('email', 'admin@univbi.ac.id')->firstOrFail();
        $this->actingAs($superAdmin);

        $unitTi = Unit::where('code', 'TI')->firstOrFail();

        Livewire::test(AccreditationDataBank::class)
            ->callAction('configureAssessorAccess', data: [
                'unit_id' => $unitTi->id,
                'is_assessor_access_active' => true,
                'assessor_access_start' => '2026-11-01',
                'assessor_access_end' => '2026-12-31',
                'assessor_passcode' => 'TI2026',
                'assessor_notes' => 'Selamat datang Asesor LAM-INFOKOM',
            ])
            ->assertHasNoActionErrors();

        $unitTi->refresh();
        $this->assertTrue($unitTi->is_assessor_access_active);
        $this->assertSame('2026-11-01', $unitTi->assessor_access_start?->format('Y-m-d'));
        $this->assertSame('2026-12-31', $unitTi->assessor_access_end?->format('Y-m-d'));
        $this->assertSame('TI2026', $unitTi->assessor_passcode);
        $this->assertSame('Selamat datang Asesor LAM-INFOKOM', $unitTi->assessor_notes);
        $this->assertTrue($unitTi->isAssessorAccessOpen());
    }

    public function test_can_close_assessor_access_via_action_and_displays_open_portals(): void
    {
        $this->seed(MasterDataSeeder::class);

        $superAdmin = User::where('email', 'admin@univbi.ac.id')->firstOrFail();
        $this->actingAs($superAdmin);

        $unitTi = Unit::where('code', 'TI')->firstOrFail();
        $unitTi->update([
            'is_assessor_access_active' => true,
            'assessor_access_end' => now()->addDays(30),
            'assessor_passcode' => 'TI2026',
        ]);

        $component = Livewire::test(AccreditationDataBank::class)
            ->assertSee('Portal Akses Asesor Sedang Terbuka')
            ->assertSee($unitTi->name)
            ->callAction('closeAssessorAccess', arguments: ['unit_id' => $unitTi->id])
            ->assertHasNoActionErrors()
            ->assertNotified('Akses Asesor Ditutup');

        $unitTi->refresh();
        $this->assertFalse($unitTi->is_assessor_access_active);

        // When closed, empty state is displayed
        $component->assertSee('Seluruh portal akses asesor sedang tertutup');
    }

    public function test_can_trigger_export_excel_action(): void
    {
        $this->seed(MasterDataSeeder::class);
        Storage::fake(StorageDisk::Private->value);

        $superAdmin = User::where('email', 'admin@univbi.ac.id')->firstOrFail();
        $this->actingAs($superAdmin);

        $unitTi = Unit::where('code', 'TI')->firstOrFail();
        $this->createBorangDocument($unitTi);

        Livewire::test(AccreditationDataBank::class)
            ->callAction('exportExcel')
            ->assertFileDownloaded();
    }

    public function test_can_trigger_filtered_export_excel_action(): void
    {
        $this->seed(MasterDataSeeder::class);
        Storage::fake(StorageDisk::Private->value);

        $superAdmin = User::where('email', 'admin@univbi.ac.id')->firstOrFail();
        $this->actingAs($superAdmin);

        $unitTi = Unit::where('code', 'TI')->firstOrFail();
        $this->createBorangDocument($unitTi);

        $criterionK6 = $unitTi->getApplicableCriteria()->firstWhere('code', 'K6')
            ?? AccreditationCriterion::where('code', 'K6')->firstOrFail();

        Livewire::test(AccreditationDataBank::class)
            ->set('tableFilters.unit_id.value', (string) $unitTi->id)
            ->call('filterByCriterionId', $criterionK6->id)
            ->callAction('exportExcel')
            ->assertFileDownloaded('Rekapitulasi-Borang-Akreditasi-UNIVBI-'.now()->format('Ymd').'.xlsx');
    }

    public function test_can_trigger_download_all_zip_action(): void
    {
        $this->seed(MasterDataSeeder::class);
        Storage::fake(StorageDisk::Private->value);

        $superAdmin = User::where('email', 'admin@univbi.ac.id')->firstOrFail();
        $this->actingAs($superAdmin);

        $unitTi = Unit::where('code', 'TI')->firstOrFail();
        $this->createBorangDocument($unitTi);

        Livewire::test(AccreditationDataBank::class)
            ->callAction('downloadAllZip')
            ->assertFileDownloaded();
    }

    public function test_accreditation_data_bank_renders_criteria_statistics_cards(): void
    {
        $this->seed(MasterDataSeeder::class);
        Storage::fake(StorageDisk::Private->value);

        $superAdmin = User::where('email', 'admin@univbi.ac.id')->firstOrFail();
        $this->actingAs($superAdmin);

        $unitTi = Unit::where('code', 'TI')->firstOrFail();
        $this->createBorangDocument($unitTi);

        Livewire::test(AccreditationDataBank::class)
            ->assertSee('Bank Data Bukti Fisik Borang Akreditasi (9 Kriteria)')
            ->assertSee('Distribusi Bukti Fisik per Kriteria Akreditasi')
            ->assertSee('Semua Kriteria')
            ->assertSee('K1')
            ->assertSee('K6')
            ->assertSee('Visi Misi')
            ->assertSee('Pendidikan');
    }

    public function test_clicking_criteria_card_filters_table_records_and_updates_counts(): void
    {
        $this->seed(MasterDataSeeder::class);
        Storage::fake(StorageDisk::Private->value);

        $superAdmin = User::where('email', 'admin@univbi.ac.id')->firstOrFail();
        $this->actingAs($superAdmin);

        $unitTi = Unit::where('code', 'TI')->firstOrFail();
        $unitSi = Unit::where('code', 'SI')->firstOrFail();

        // Document 1 in TI under K6
        $docK6 = $this->createBorangDocument($unitTi);

        // Document 2 in SI under K1
        $criterionK1 = $unitSi->getApplicableCriteria()->firstWhere('code', 'K1')
            ?? AccreditationCriterion::where('code', 'K1')->firstOrFail();
        $category = DocumentCategory::firstOrFail();
        $academicYear = AcademicYear::firstOrFail();
        $docK1 = Document::create([
            'title' => 'Renstra Sistem Informasi 2026',
            'document_number' => '002/UNIVBI.SI/SK/XI/2026',
            'unit_id' => $unitSi->id,
            'document_category_id' => $category->id,
            'academic_year_id' => $academicYear->id,
            'is_borang' => true,
            'status' => DocumentStatus::Approved,
            'issued_date' => now()->toDateString(),
            'uploaded_by' => $superAdmin->id,
        ]);
        $docK1->accreditationCriteria()->attach($criterionK1->id, ['order_index' => 1]);

        $criterionK6 = $unitTi->getApplicableCriteria()->firstWhere('code', 'K6')
            ?? AccreditationCriterion::where('code', 'K6')->firstOrFail();

        // 1. Initially both documents are visible
        $component = Livewire::test(AccreditationDataBank::class)
            ->assertCanSeeTableRecords([$docK6, $docK1]);

        $initialStats = $component->instance()->getCriteriaStatistics();
        $this->assertSame(2, $initialStats['total']);

        // 2. Filter by Unit TI -> Total becomes 1, K6 = 1, K1 = 0
        $component->set('tableFilters.unit_id.value', (string) $unitTi->id);
        $tiStats = $component->instance()->getCriteriaStatistics();
        $this->assertSame(1, $tiStats['total']);
        $k6Item = collect($tiStats['criteria'])->firstWhere('code', 'K6');
        $k1Item = collect($tiStats['criteria'])->firstWhere('code', 'K1');
        $this->assertSame(1, $k6Item['count']);
        $this->assertSame(0, $k1Item['count']);

        // 3. Reset Unit filter, click Card K6
        $component->set('tableFilters.unit_id.value', null);
        $component->call('filterByCriterionId', $criterionK6->id)
            ->assertCanSeeTableRecords([$docK6])
            ->assertCanNotSeeTableRecords([$docK1]);

        $k6SelectedStats = $component->instance()->getCriteriaStatistics();
        $this->assertSame($criterionK6->id, $k6SelectedStats['selected_id']);
        $this->assertSame('K6', $k6SelectedStats['selected_code']);

        // 4. Click Reset Criteria filter -> Both documents visible again
        $component->call('resetCriteriaFilter')
            ->assertCanSeeTableRecords([$docK6, $docK1]);

        $resetStats = $component->instance()->getCriteriaStatistics();
        $this->assertNull($resetStats['selected_id']);
    }

    public function test_table_has_copy_assessor_url_and_view_detail_record_actions(): void
    {
        $this->seed(MasterDataSeeder::class);
        Storage::fake(StorageDisk::Private->value);

        $superAdmin = User::where('email', 'admin@univbi.ac.id')->firstOrFail();
        $this->actingAs($superAdmin);

        $unitTi = Unit::where('code', 'TI')->firstOrFail();
        $doc = $this->createBorangDocument($unitTi);

        Livewire::test(AccreditationDataBank::class)
            ->assertTableActionExists('copyAssessorUrl')
            ->assertTableActionExists('viewDetail')
            ->callTableAction('copyAssessorUrl', $doc)
            ->assertNotified('Tautan Asesor Berhasil Disalin');
    }

    public function test_accreditation_data_bank_uses_simple_pagination_and_chunked_query_exports(): void
    {
        $this->seed(MasterDataSeeder::class);
        Storage::fake(StorageDisk::Private->value);

        $superAdmin = User::where('email', 'admin@univbi.ac.id')->firstOrFail();
        $this->actingAs($superAdmin);

        $unitTi = Unit::where('code', 'TI')->firstOrFail();
        $doc = $this->createBorangDocument($unitTi);

        $component = Livewire::test(AccreditationDataBank::class)
            ->assertSuccessful()
            ->searchTable('Kurikulum Informatika')
            ->assertCanSeeTableRecords([$doc]);

        $table = $component->instance()->getTable();
        $this->assertSame(PaginationMode::Simple, $table->getPaginationMode());
        $this->assertSame('500ms', $table->getSearchDebounce());
        $this->assertSame(10, $table->getDefaultPaginationPageOption());

        // Verify chunked Excel spreadsheet builder produces exact sheets & row count
        $query = Document::query()->where('is_borang', true)->where('status', DocumentStatus::Approved);
        $spreadsheet = app(BorangExportService::class)->buildSpreadsheetFromQuery($query, $unitTi);
        $this->assertSame('Rekapitulasi Borang', $spreadsheet->getSheet(0)->getTitle());
        $this->assertSame('SK Kurikulum Informatika 2026', $spreadsheet->getSheet(0)->getCell('F7')->getValue());
        $this->assertSame('Ringkasan & Portal Asesor', $spreadsheet->getSheet(1)->getTitle());

        // Verify chunked ZIP streamer returns valid StreamedResponse
        $zipResponse = app(BorangZipExportService::class)->downloadZipFromQuery($query, 'test-borang.zip');
        $this->assertSame(200, $zipResponse->getStatusCode());
        $this->assertStringContainsString('application/zip', (string) $zipResponse->headers->get('Content-Type'));
    }

    public function test_heavy_borang_export_dispatches_background_queue_job(): void
    {
        Queue::fake();
        $this->seed(MasterDataSeeder::class);
        Storage::fake(StorageDisk::Private->value);

        $lpmUser = User::where('email', 'lpm@univbi.ac.id')->firstOrFail();
        $unitTi = Unit::where('code', 'TI')->firstOrFail();
        $category = DocumentCategory::firstOrFail();
        $academicYear = AcademicYear::firstOrFail();

        for ($i = 1; $i <= AccreditationDataBank::HEAVY_ZIP_THRESHOLD + 1; $i++) {
            Document::create([
                'title' => "Dokumen Antrean Dispatch {$i}",
                'document_number' => str_pad((string) $i, 3, '0', STR_PAD_LEFT).'/UNIVBI.TI/SK/XI/2026',
                'unit_id' => $unitTi->id,
                'document_category_id' => $category->id,
                'academic_year_id' => $academicYear->id,
                'is_borang' => true,
                'status' => DocumentStatus::Approved,
                'issued_date' => now()->toDateString(),
                'uploaded_by' => $lpmUser->id,
            ]);
        }

        $this->actingAs($lpmUser);
        Livewire::test(AccreditationDataBank::class)
            ->callAction('downloadAllZip')
            ->assertNotified('Ekspor Paket Bundel ZIP Diproses di Latar Belakang');

        Queue::assertPushed(GenerateBorangExportJob::class, function (GenerateBorangExportJob $job) use ($lpmUser): bool {
            return $job->bulkExport->user_id === $lpmUser->id
                && $job->bulkExport->module_type === 'borang_zip'
                && $job->bulkExport->status === 'pending';
        });
    }

    public function test_queued_borang_export_service_generates_files_notifications_and_enforces_download_access(): void
    {
        $this->seed(MasterDataSeeder::class);
        Storage::fake(StorageDisk::Private->value);

        $lpmUser = User::where('email', 'lpm@univbi.ac.id')->firstOrFail();
        $stafTi = User::where('email', 'staf.ti@univbi.ac.id')->firstOrFail();
        $unitTi = Unit::where('code', 'TI')->firstOrFail();

        $criterionK6 = $unitTi->getApplicableCriteria()->firstWhere('code', 'K6')
            ?? AccreditationCriterion::where('code', 'K6')->firstOrFail();

        $dummyPdfPath = 'documents/2026/11/TI/heavy-export-sample.pdf';
        Storage::disk(StorageDisk::Private->value)->put($dummyPdfPath, '%PDF-1.4 sample content');

        $category = DocumentCategory::firstOrFail();
        $academicYear = AcademicYear::firstOrFail();

        $doc = Document::create([
            'title' => 'Dokumen Antrean Berat Pemrosesan',
            'document_number' => '901/UNIVBI.TI/SK/XI/2026',
            'unit_id' => $unitTi->id,
            'document_category_id' => $category->id,
            'academic_year_id' => $academicYear->id,
            'is_borang' => true,
            'status' => DocumentStatus::Approved,
            'issued_date' => now()->toDateString(),
            'uploaded_by' => $lpmUser->id,
        ]);
        $doc->accreditationCriteria()->attach($criterionK6->id, ['order_index' => 1]);

        DocumentFile::create([
            'document_id' => $doc->id,
            'disk' => StorageDisk::Private,
            'original_file_path' => $dummyPdfPath,
            'original_file_name' => 'heavy-export-sample.pdf',
            'standardized_file_name' => '2026-TI-HEAVY-901.pdf',
            'file_extension' => 'pdf',
            'file_hash_sha256' => hash('sha256', 'dummy-901'),
            'file_size' => 512,
            'mime_type' => 'application/pdf',
            'is_primary' => true,
            'uploaded_by' => $lpmUser->id,
        ]);

        // 1. Process queued ZIP export ('borang_zip')
        $zipExport = BulkExport::create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $lpmUser->id,
            'module_type' => 'borang_zip',
            'record_ids' => [
                'bypass_unit_scope' => true,
                'scope_unit_id' => $lpmUser->unit_id,
                'unit_id' => $unitTi->id,
            ],
            'total_files' => 1,
            'zip_file_name' => 'Bundel-Borang-Antrean.zip',
            'disk' => 'private',
            'status' => 'pending',
            'expires_at' => now()->addHours(24),
        ]);

        $this->assertTrue(app(BorangQueuedExportService::class)->process($zipExport));

        $zipExport->refresh();
        $this->assertSame('completed', $zipExport->status);
        $this->assertSame(1, $zipExport->total_files);
        Storage::disk(StorageDisk::Private->value)->assertExists((string) $zipExport->zip_file_path);

        // Unauthorized staff cannot download someone else's borang export
        $this->actingAs($stafTi)
            ->get(route('admin.bulk-exports.download', $zipExport->uuid))
            ->assertForbidden();

        // Owner LPM with borang.download_bulk_zip permission can download
        $this->actingAs($lpmUser)
            ->get(route('admin.bulk-exports.download', $zipExport->uuid))
            ->assertOk();

        // 2. Process queued Excel export ('borang_excel')
        $excelExport = BulkExport::create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $lpmUser->id,
            'module_type' => 'borang_excel',
            'record_ids' => [
                'bypass_unit_scope' => true,
                'scope_unit_id' => $lpmUser->unit_id,
                'unit_id' => $unitTi->id,
            ],
            'total_files' => 1,
            'zip_file_name' => 'Rekap-Borang-Antrean.xlsx',
            'disk' => 'private',
            'status' => 'pending',
            'expires_at' => now()->addHours(24),
        ]);

        $this->assertTrue(app(BorangQueuedExportService::class)->process($excelExport));

        $excelExport->refresh();
        $this->assertSame('completed', $excelExport->status);
        Storage::disk(StorageDisk::Private->value)->assertExists((string) $excelExport->zip_file_path);
        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $lpmUser->id,
            'notifiable_type' => User::class,
        ]);

        $this->actingAs($lpmUser)
            ->get(route('admin.bulk-exports.download', $excelExport->uuid))
            ->assertOk();
    }
}
