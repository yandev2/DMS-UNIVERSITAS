<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\DocumentStatus;
use App\Enums\IncomingLetterStatus;
use App\Enums\LetterPriority;
use App\Enums\OutgoingLetterStatus;
use App\Filament\Resources\Documents\Pages\ListDocuments;
use App\Filament\Resources\IncomingLetters\Pages\ListIncomingLetters;
use App\Filament\Resources\OutgoingLetters\Pages\ListOutgoingLetters;
use App\Jobs\GenerateBulkZipArchiveJob;
use App\Models\AcademicYear;
use App\Models\BulkExport;
use App\Models\Document;
use App\Models\DocumentCategory;
use App\Models\DocumentFile;
use App\Models\IncomingLetter;
use App\Models\OutgoingLetter;
use App\Models\Unit;
use App\Models\User;
use App\Services\BulkExportZipService;
use Database\Seeders\MasterDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Livewire;
use setasign\Fpdi\Fpdi;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class BulkZipExportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('private');
        $this->seed(MasterDataSeeder::class);
    }

    protected function createValidPdf(string $content = 'Arsip Kampus'): string
    {
        $pdf = new Fpdi;
        $pdf->AddPage('P', [210, 297]);
        $pdf->SetFont('Helvetica', '', 12);
        $pdf->Cell(0, 10, $content);

        return $pdf->Output('S');
    }

    public function test_only_super_admin_sees_bulk_zip_action_on_tables(): void
    {
        $superAdmin = User::where('email', 'admin@univbi.ac.id')->firstOrFail();
        $humas = User::where('email', 'humas@univbi.ac.id')->firstOrFail();

        // 1. Tabel Dokumen: Super Admin vs Humas
        $this->actingAs($superAdmin);
        Livewire::test(ListDocuments::class)
            ->assertTableBulkActionVisible('downloadZip');

        $this->actingAs($humas);
        Livewire::test(ListDocuments::class)
            ->assertTableBulkActionHidden('downloadZip');

        // 2. Tabel Surat Masuk: Super Admin vs Humas
        $this->actingAs($superAdmin);
        Livewire::test(ListIncomingLetters::class)
            ->assertTableBulkActionVisible('downloadZip');

        $this->actingAs($humas);
        Livewire::test(ListIncomingLetters::class)
            ->assertTableBulkActionHidden('downloadZip');

        // 3. Tabel Surat Keluar: Super Admin vs Humas
        $this->actingAs($superAdmin);
        Livewire::test(ListOutgoingLetters::class)
            ->assertTableBulkActionVisible('downloadZip');

        $this->actingAs($humas);
        Livewire::test(ListOutgoingLetters::class)
            ->assertTableBulkActionHidden('downloadZip');
    }

    public function test_bulk_action_dispatches_job_and_creates_bulk_export_record(): void
    {
        Queue::fake();

        $superAdmin = User::where('email', 'admin@univbi.ac.id')->firstOrFail();
        $unit = Unit::firstOrFail();
        $category = DocumentCategory::firstOrFail();
        $academicYear = AcademicYear::firstOrFail();

        $doc1 = Document::create([
            'title' => 'Dokumen Pertama',
            'unit_id' => $unit->id,
            'document_category_id' => $category->id,
            'academic_year_id' => $academicYear->id,
            'uploaded_by' => $superAdmin->id,
            'issued_date' => now(),
            'status' => DocumentStatus::Approved,
        ]);
        $doc2 = Document::create([
            'title' => 'Dokumen Kedua',
            'unit_id' => $unit->id,
            'document_category_id' => $category->id,
            'academic_year_id' => $academicYear->id,
            'uploaded_by' => $superAdmin->id,
            'issued_date' => now(),
            'status' => DocumentStatus::Approved,
        ]);

        $this->actingAs($superAdmin);

        Livewire::test(ListDocuments::class)
            ->callTableBulkAction('downloadZip', [$doc1, $doc2])
            ->assertHasNoTableBulkActionErrors();

        $this->assertDatabaseHas('bulk_exports', [
            'user_id' => $superAdmin->id,
            'module_type' => 'documents',
            'status' => 'pending',
            'total_files' => 2,
        ]);

        Queue::assertPushed(GenerateBulkZipArchiveJob::class, function ($job) use ($superAdmin) {
            return $job->bulkExport->user_id === $superAdmin->id && $job->bulkExport->module_type === 'documents';
        });
    }

    public function test_service_packages_documents_creates_zip_and_sends_notification(): void
    {
        $superAdmin = User::where('email', 'admin@univbi.ac.id')->firstOrFail();
        $unit = Unit::firstOrFail();
        $category = DocumentCategory::firstOrFail();
        $academicYear = AcademicYear::firstOrFail();

        $pdfBytes = $this->createValidPdf('Dokumen SK Rektor');
        $filePath = 'documents/sk-rektor.pdf';
        Storage::disk('private')->put($filePath, $pdfBytes);

        $doc = Document::create([
            'title' => 'SK Pengangkatan Pejabat Struktural',
            'unit_id' => $unit->id,
            'document_category_id' => $category->id,
            'academic_year_id' => $academicYear->id,
            'uploaded_by' => $superAdmin->id,
            'issued_date' => now(),
            'document_number' => '001/UNIVBI/SK/XI/2026',
            'status' => DocumentStatus::Approved,
        ]);

        DocumentFile::create([
            'document_id' => $doc->id,
            'original_file_name' => 'sk-rektor.pdf',
            'standardized_file_name' => '001-UNIVBI-SK-XI-2026.pdf',
            'file_extension' => 'pdf',
            'file_path' => $filePath,
            'original_file_path' => $filePath,
            'stamped_file_path' => $filePath,
            'disk' => 'private',
            'file_size' => strlen($pdfBytes),
            'mime_type' => 'application/pdf',
            'is_primary' => true,
            'is_active' => true,
        ]);

        $bulkExport = BulkExport::create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $superAdmin->id,
            'module_type' => 'documents',
            'record_ids' => [$doc->id],
            'total_files' => 1,
            'zip_file_name' => 'Arsip-Dokumen-Test.zip',
            'disk' => 'private',
            'status' => 'pending',
            'expires_at' => now()->addHours(24),
        ]);

        $service = app(BulkExportZipService::class);
        $result = $service->process($bulkExport);

        $this->assertTrue($result);
        $bulkExport->refresh();

        $this->assertSame('completed', $bulkExport->status);
        $this->assertSame(1, $bulkExport->total_files);
        $this->assertNotNull($bulkExport->zip_file_path);
        $this->assertGreaterThan(0, $bulkExport->file_size_bytes);

        // Verifikasi fisik berkas ZIP tersimpan pada private disk
        Storage::disk('private')->assertExists($bulkExport->zip_file_path);

        // Verifikasi notifikasi lonceng terkirim ke database Super Admin
        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $superAdmin->id,
            'notifiable_type' => User::class,
        ]);

        // Verifikasi entri tercatat ke tabel activity_log
        $activity = Activity::where('log_name', 'bulk_zip_export')
            ->where('event', 'completed')
            ->first();

        $this->assertNotNull($activity);
        $this->assertSame($superAdmin->id, $activity->causer_id);
        $this->assertSame('documents', $activity->properties['module']);
        $this->assertSame(1, $activity->properties['total_files']);
    }

    public function test_service_packages_incoming_and_outgoing_letters(): void
    {
        $superAdmin = User::where('email', 'admin@univbi.ac.id')->firstOrFail();
        $unit = Unit::firstOrFail();
        $academicYear = AcademicYear::firstOrFail();

        // 1. Surat Masuk
        $pdfIncoming = $this->createValidPdf('Surat Masuk LLDIKTI');
        Storage::disk('private')->put('incoming-letters/surat-in.pdf', $pdfIncoming);

        $incoming = IncomingLetter::create([
            'agenda_number' => '001/UNIVBI.REK/SM/XI/2026',
            'sender_name' => 'LLDIKTI Wilayah II',
            'external_letter_number' => 'LL/098/2026',
            'external_letter_date' => now()->subDays(2),
            'received_date' => now()->subDay(),
            'subject' => 'Pemberitahuan Monev Mutu Perguruan Tinggi',
            'original_file_path' => 'incoming-letters/surat-in.pdf',
            'file_name' => 'surat-in.pdf',
            'file_size' => strlen($pdfIncoming),
            'priority' => LetterPriority::Biasa,
            'unit_id' => $unit->id,
            'academic_year_id' => $academicYear->id,
            'status' => IncomingLetterStatus::Completed,
            'registered_by' => $superAdmin->id,
        ]);

        $exportIncoming = BulkExport::create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $superAdmin->id,
            'module_type' => 'incoming_letters',
            'record_ids' => [$incoming->id],
            'total_files' => 1,
            'zip_file_name' => 'Arsip-Surat-Masuk-Test.zip',
            'disk' => 'private',
            'status' => 'pending',
            'expires_at' => now()->addHours(24),
        ]);

        $service = app(BulkExportZipService::class);
        $this->assertTrue($service->process($exportIncoming));

        $exportIncoming->refresh();
        $this->assertSame('completed', $exportIncoming->status);
        $this->assertSame(1, $exportIncoming->total_files);
        Storage::disk('private')->assertExists($exportIncoming->zip_file_path);

        // 2. Surat Keluar
        $pdfOutgoing = $this->createValidPdf('Surat Keluar Humas');
        Storage::disk('private')->put('outgoing-letters/surat-out.pdf', $pdfOutgoing);

        $outgoing = OutgoingLetter::create([
            'letter_number' => '001/UNIVBI.TI/SKL/XI/2026',
            'unit_id' => $unit->id,
            'academic_year_id' => $academicYear->id,
            'destination_organization' => 'Kepala Balai Diklat Kejuruan',
            'letter_date' => now(),
            'subject' => 'Permohonan Kerjasama Sertifikasi IT',
            'original_file_path' => 'outgoing-letters/surat-out.pdf',
            'file_name' => 'surat-out.pdf',
            'file_size' => strlen($pdfOutgoing),
            'status' => OutgoingLetterStatus::Published,
            'created_by' => $superAdmin->id,
        ]);

        $exportOutgoing = BulkExport::create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $superAdmin->id,
            'module_type' => 'outgoing_letters',
            'record_ids' => [$outgoing->id],
            'total_files' => 1,
            'zip_file_name' => 'Arsip-Surat-Keluar-Test.zip',
            'disk' => 'private',
            'status' => 'pending',
            'expires_at' => now()->addHours(24),
        ]);

        $this->assertTrue($service->process($exportOutgoing));

        $exportOutgoing->refresh();
        $this->assertSame('completed', $exportOutgoing->status);
        $this->assertSame(1, $exportOutgoing->total_files);
        Storage::disk('private')->assertExists($exportOutgoing->zip_file_path);
    }

    public function test_download_endpoint_authorization_and_audit_logging(): void
    {
        $superAdmin = User::where('email', 'admin@univbi.ac.id')->firstOrFail();
        $humas = User::where('email', 'humas@univbi.ac.id')->firstOrFail();

        // Buat dummy zip file
        $dummyZipPath = 'temp-exports/dummy-export.zip';
        Storage::disk('private')->put($dummyZipPath, 'DUMMY_ZIP_CONTENT');

        $export = BulkExport::create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $superAdmin->id,
            'module_type' => 'documents',
            'record_ids' => [1],
            'total_files' => 1,
            'zip_file_name' => 'Arsip-Dokumen-2026.zip',
            'zip_file_path' => $dummyZipPath,
            'disk' => 'private',
            'file_size_bytes' => 100,
            'status' => 'completed',
            'expires_at' => now()->addHours(24),
        ]);

        // 1. Akses oleh role non-Super Admin (Humas) harus ditolak 403
        $this->actingAs($humas)
            ->get(route('admin.bulk-exports.download', $export->uuid))
            ->assertStatus(403);

        // 2. Akses oleh Super Admin harus sukses 200 Stream Download
        $response = $this->actingAs($superAdmin)
            ->get(route('admin.bulk-exports.download', $export->uuid));

        $response->assertOk();
        $this->assertStringContainsString('attachment', (string) $response->headers->get('Content-Disposition'));
        $this->assertStringContainsString('Arsip-Dokumen-2026.zip', (string) $response->headers->get('Content-Disposition'));

        $export->refresh();
        $this->assertNotNull($export->downloaded_at);

        // 3. Verifikasi tercatat di activity_log
        $downloadLog = Activity::where('log_name', 'bulk_zip_export')
            ->where('event', 'downloaded')
            ->first();

        $this->assertNotNull($downloadLog);
        $this->assertSame($superAdmin->id, $downloadLog->causer_id);
    }

    public function test_download_endpoint_rejects_expired_exports(): void
    {
        $superAdmin = User::where('email', 'admin@univbi.ac.id')->firstOrFail();

        $dummyZipPath = 'temp-exports/expired-export.zip';
        Storage::disk('private')->put($dummyZipPath, 'DUMMY_ZIP_CONTENT');

        // Export yang sudah kedaluwarsa 25 jam lalu
        $expiredExport = BulkExport::create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $superAdmin->id,
            'module_type' => 'documents',
            'record_ids' => [1],
            'total_files' => 1,
            'zip_file_name' => 'Arsip-Expired.zip',
            'zip_file_path' => $dummyZipPath,
            'disk' => 'private',
            'file_size_bytes' => 100,
            'status' => 'completed',
            'expires_at' => now()->subHours(25),
        ]);

        $this->actingAs($superAdmin)
            ->get(route('admin.bulk-exports.download', $expiredExport->uuid))
            ->assertStatus(410);
    }

    public function test_cleanup_command_removes_expired_zip_files(): void
    {
        $superAdmin = User::where('email', 'admin@univbi.ac.id')->firstOrFail();

        $expiredZipPath = 'temp-exports/expired-file.zip';
        $activeZipPath = 'temp-exports/active-file.zip';

        Storage::disk('private')->put($expiredZipPath, 'EXPIRED_CONTENT');
        Storage::disk('private')->put($activeZipPath, 'ACTIVE_CONTENT');

        $expiredExport = BulkExport::create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $superAdmin->id,
            'module_type' => 'documents',
            'record_ids' => [1],
            'total_files' => 1,
            'zip_file_name' => 'Arsip-Kedaluwarsa.zip',
            'zip_file_path' => $expiredZipPath,
            'disk' => 'private',
            'file_size_bytes' => 100,
            'status' => 'completed',
            'expires_at' => now()->subDay(),
        ]);

        $activeExport = BulkExport::create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $superAdmin->id,
            'module_type' => 'documents',
            'record_ids' => [2],
            'total_files' => 1,
            'zip_file_name' => 'Arsip-Aktif.zip',
            'zip_file_path' => $activeZipPath,
            'disk' => 'private',
            'file_size_bytes' => 100,
            'status' => 'completed',
            'expires_at' => now()->addDay(),
        ]);

        $this->artisan('bulk-exports:clean-expired')
            ->assertSuccessful();

        // Berkas kedaluwarsa harus terhapus secara fisik dan path di DB di-null-kan
        Storage::disk('private')->assertMissing($expiredZipPath);
        $expiredExport->refresh();
        $this->assertNull($expiredExport->zip_file_path);

        // Berkas aktif harus tetap ada
        Storage::disk('private')->assertExists($activeZipPath);
        $activeExport->refresh();
        $this->assertNotNull($activeExport->zip_file_path);
    }
}
