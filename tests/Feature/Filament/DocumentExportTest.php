<?php

declare(strict_types=1);

namespace Tests\Feature\Filament;

use App\Enums\DocumentStatus;
use App\Enums\VisibilityLevel;
use App\Filament\Resources\Documents\Pages\ListDocuments;
use App\Jobs\GenerateDocumentExportJob;
use App\Models\AcademicYear;
use App\Models\BulkExport;
use App\Models\Document;
use App\Models\DocumentCategory;
use App\Models\Permission;
use App\Models\Unit;
use App\Models\User;
use App\Services\DocumentQueuedExportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\Support\LegacyMasterDataSeeder;
use Tests\TestCase;

class DocumentExportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('private');
        $this->seed(LegacyMasterDataSeeder::class);
    }

    public function test_export_excel_action_is_only_visible_for_users_with_permission(): void
    {
        $superAdmin = User::where('email', 'admin@univbi.ac.id')->firstOrFail();
        $stafProdi = User::where('email', 'staf.ti@univbi.ac.id')->firstOrFail();

        $this->actingAs($superAdmin);
        Livewire::test(ListDocuments::class)
            ->assertActionVisible('exportExcel');

        // Staf tanpa permission document.export_excel tidak melihat & tidak bisa mengeksekusi aksi
        $this->actingAs($stafProdi);
        Livewire::test(ListDocuments::class)
            ->assertActionHidden('exportExcel');

        // Setelah diberi permission secara langsung, tombol langsung muncul
        Permission::findOrCreate('document.export_excel', 'web');
        $stafProdi->givePermissionTo('document.export_excel');
        $stafProdi->refresh();

        $this->actingAs($stafProdi);
        Livewire::test(ListDocuments::class)
            ->assertActionVisible('exportExcel');
    }

    public function test_export_excel_requires_issued_date_range_and_handles_empty_matches(): void
    {
        Queue::fake([GenerateDocumentExportJob::class]);

        $superAdmin = User::where('email', 'admin@univbi.ac.id')->firstOrFail();
        $this->actingAs($superAdmin);

        // Wajib mengisi issued_from dan issued_until
        Livewire::test(ListDocuments::class)
            ->callAction('exportExcel', data: [
                'issued_from' => null,
                'issued_until' => null,
            ])
            ->assertHasActionErrors([
                'issued_from' => 'required',
                'issued_until' => 'required',
            ]);

        // issued_until tidak boleh lebih awal dari issued_from
        Livewire::test(ListDocuments::class)
            ->callAction('exportExcel', data: [
                'issued_from' => '2026-11-20',
                'issued_until' => '2026-11-10',
            ])
            ->assertHasActionErrors(['issued_until']);

        // Tidak ada dokumen pada rentang tahun 2020 -> kirim warning, jangan antrekan job
        Livewire::test(ListDocuments::class)
            ->callAction('exportExcel', data: [
                'issued_from' => '2020-01-01',
                'issued_until' => '2020-01-31',
            ])
            ->assertHasNoActionErrors()
            ->assertNotified('Tidak Ada Dokumen yang Cocok');

        Queue::assertNothingPushed();
        $this->assertDatabaseCount('bulk_exports', 0);
    }

    public function test_export_excel_dispatches_job_and_generates_valid_multi_sheet_workbook(): void
    {
        Queue::fake([GenerateDocumentExportJob::class]);

        $superAdmin = User::where('email', 'admin@univbi.ac.id')->firstOrFail();
        $unit = Unit::where('code', 'TI')->first() ?? Unit::firstOrFail();
        $category = DocumentCategory::firstOrFail();
        $academicYear = AcademicYear::firstOrFail();

        $matchingDoc = Document::create([
            'title' => '=CMD("calc") Naskah Resmi Tahun 2026',
            'document_number' => '010/UNIVBI.TI/SK/XI/2026',
            'unit_id' => $unit->id,
            'document_category_id' => $category->id,
            'academic_year_id' => $academicYear->id,
            'uploaded_by' => $superAdmin->id,
            'issued_date' => '2026-11-15',
            'status' => DocumentStatus::Approved,
            'visibility' => VisibilityLevel::Internal,
            'is_borang' => true,
            'has_expiry' => false,
            'requires_wet_signature' => false,
        ]);

        // Di luar rentang tanggal terbit -> tidak boleh ikut terekspor
        Document::create([
            'title' => 'Dokumen Bulan Lalu',
            'document_number' => '009/UNIVBI.TI/SK/X/2026',
            'unit_id' => $unit->id,
            'document_category_id' => $category->id,
            'academic_year_id' => $academicYear->id,
            'uploaded_by' => $superAdmin->id,
            'issued_date' => '2026-10-05',
            'status' => DocumentStatus::Approved,
            'visibility' => VisibilityLevel::Internal,
        ]);

        $this->actingAs($superAdmin);

        Livewire::test(ListDocuments::class)
            ->callAction('exportExcel', data: [
                'issued_from' => '2026-11-01',
                'issued_until' => '2026-11-30',
                'unit_id' => null,
                'document_category_id' => null,
                'academic_year_id' => null,
                'status' => [],
                'visibility' => null,
                'is_borang' => null,
                'has_expiry' => null,
            ])
            ->assertHasNoActionErrors()
            ->assertNotified('Ekspor Excel Dokumen Diproses di Latar Belakang');

        $export = BulkExport::where('module_type', 'documents_excel')->first();
        $this->assertNotNull($export);
        $this->assertSame(1, $export->total_files);

        Queue::assertPushed(GenerateDocumentExportJob::class, function (GenerateDocumentExportJob $job) use ($export): bool {
            return $job->bulkExport->id === $export->id;
        });

        // Jalankan service antrean dan verifikasi file .xlsx + notifikasi database + proteksi Formula Injection
        $result = app(DocumentQueuedExportService::class)->process($export);
        $this->assertTrue($result);

        $export->refresh();
        $this->assertSame('completed', $export->status);
        $this->assertNotNull($export->zip_file_path);
        Storage::disk('private')->assertExists($export->zip_file_path);

        $spreadsheet = IOFactory::load(Storage::disk('private')->path($export->zip_file_path));
        $this->assertSame(['Rekapitulasi Dokumen', 'Ringkasan Rekapitulasi'], $spreadsheet->getSheetNames());

        $mainSheet = $spreadsheet->getSheetByName('Rekapitulasi Dokumen');
        $this->assertNotNull($mainSheet);
        $this->assertSame($matchingDoc->document_number, $mainSheet->getCell('B7')->getValue());
        // Formula injection pada judul wajib dinetralkan dengan quote prefix apostrophe
        $this->assertSame('\'=CMD("calc") Naskah Resmi Tahun 2026', $mainSheet->getCell('C7')->getValue());
        $this->assertSame('15/11/2026', $mainSheet->getCell('G7')->getValue());
        $this->assertSame('Disahkan', $mainSheet->getCell('H7')->getValue());

        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $superAdmin->id,
            'notifiable_type' => User::class,
        ]);
    }

    public function test_queued_document_export_enforces_unit_tenancy_and_download_authorization(): void
    {
        Queue::fake([GenerateDocumentExportJob::class]);

        $superAdmin = User::where('email', 'admin@univbi.ac.id')->firstOrFail();
        $prodiTi = User::where('email', 'kaprodi.ti@univbi.ac.id')->firstOrFail();
        $prodiLain = User::where('email', 'kaprodi.si@univbi.ac.id')->firstOrFail();

        $tiUnitId = (int) $prodiTi->unit_id;
        $otherUnitId = (int) $prodiLain->unit_id;
        $category = DocumentCategory::firstOrFail();
        $academicYear = AcademicYear::firstOrFail();

        Permission::findOrCreate('document.export_excel', 'web');
        $prodiTi->givePermissionTo('document.export_excel');
        $prodiTi->refresh();

        // Dokumen unit sendiri (boleh terekspor)
        Document::create([
            'title' => 'Naskah Internal Prodi TI',
            'document_number' => 'TI-001/2026',
            'unit_id' => $tiUnitId,
            'document_category_id' => $category->id,
            'academic_year_id' => $academicYear->id,
            'uploaded_by' => $prodiTi->id,
            'issued_date' => '2026-11-18',
            'status' => DocumentStatus::Approved,
            'visibility' => VisibilityLevel::Internal,
        ]);

        // Dokumen internal & rahasia unit lain (TIDAK boleh bocor ke file Excel Prodi TI)
        Document::create([
            'title' => 'Naskah Internal Prodi SI',
            'document_number' => 'SI-001/2026',
            'unit_id' => $otherUnitId,
            'document_category_id' => $category->id,
            'academic_year_id' => $academicYear->id,
            'uploaded_by' => $prodiLain->id,
            'issued_date' => '2026-11-18',
            'status' => DocumentStatus::Approved,
            'visibility' => VisibilityLevel::Internal,
        ]);
        Document::create([
            'title' => 'Naskah Rahasia Pimpinan',
            'document_number' => 'SEC-001/2026',
            'unit_id' => $tiUnitId,
            'document_category_id' => $category->id,
            'academic_year_id' => $academicYear->id,
            'uploaded_by' => $superAdmin->id,
            'issued_date' => '2026-11-18',
            'status' => DocumentStatus::Approved,
            'visibility' => VisibilityLevel::Confidential,
        ]);

        $this->actingAs($prodiTi);

        Livewire::test(ListDocuments::class)
            ->callAction('exportExcel', data: [
                'issued_from' => '2026-11-01',
                'issued_until' => '2026-11-30',
            ])
            ->assertHasNoActionErrors();

        $export = BulkExport::where('user_id', $prodiTi->id)->where('module_type', 'documents_excel')->firstOrFail();
        $this->assertSame(1, $export->total_files);

        app(DocumentQueuedExportService::class)->process($export);
        $export->refresh();

        $spreadsheet = IOFactory::load(Storage::disk('private')->path((string) $export->zip_file_path));
        $mainSheet = $spreadsheet->getSheetByName('Rekapitulasi Dokumen');
        $this->assertSame('TI-001/2026', $mainSheet->getCell('B7')->getValue());
        // Baris 8 harus berupa baris TOTAL, bukan dokumen SI-001/2026 maupun SEC-001/2026
        $this->assertStringStartsWith('TOTAL', (string) $mainSheet->getCell('A8')->getValue());

        // Endpoint download mengizinkan pemilik ekspor, tetapi menolak user lain tanpa hak
        $this->actingAs($prodiTi)
            ->get(route('admin.bulk-exports.download', $export->uuid))
            ->assertOk();

        $this->actingAs($prodiLain)
            ->get(route('admin.bulk-exports.download', $export->uuid))
            ->assertForbidden();
    }
}
