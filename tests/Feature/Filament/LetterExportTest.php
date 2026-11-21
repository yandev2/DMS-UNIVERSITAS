<?php

declare(strict_types=1);

namespace Tests\Feature\Filament;

use App\Enums\IncomingLetterStatus;
use App\Enums\IncomingRecipientStatus;
use App\Enums\LetterPriority;
use App\Enums\OutgoingLetterStatus;
use App\Filament\Resources\IncomingLetters\Pages\ListIncomingLetters;
use App\Filament\Resources\OutgoingLetters\Pages\ListOutgoingLetters;
use App\Jobs\GenerateIncomingLetterExportJob;
use App\Jobs\GenerateOutgoingLetterExportJob;
use App\Models\AcademicYear;
use App\Models\BulkExport;
use App\Models\IncomingLetter;
use App\Models\IncomingLetterRecipient;
use App\Models\OutgoingLetter;
use App\Models\Permission;
use App\Models\Unit;
use App\Models\User;
use App\Services\IncomingLetterQueuedExportService;
use App\Services\OutgoingLetterQueuedExportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\Support\LegacyMasterDataSeeder;
use Tests\TestCase;

class LetterExportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('private');
        $this->seed(LegacyMasterDataSeeder::class);
    }

    public function test_incoming_and_outgoing_export_actions_require_dedicated_permissions(): void
    {
        $superAdmin = User::where('email', 'admin@univbi.ac.id')->firstOrFail();
        $stafProdi = User::where('email', 'staf.ti@univbi.ac.id')->firstOrFail();

        $this->actingAs($superAdmin);
        Livewire::test(ListIncomingLetters::class)->assertActionVisible('exportExcel');
        Livewire::test(ListOutgoingLetters::class)->assertActionVisible('exportExcel');

        $this->actingAs($stafProdi);
        Livewire::test(ListIncomingLetters::class)->assertActionHidden('exportExcel');
        Livewire::test(ListOutgoingLetters::class)->assertActionHidden('exportExcel');

        Permission::findOrCreate('incoming_letter.export_excel', 'web');
        $stafProdi->givePermissionTo('incoming_letter.export_excel');
        $stafProdi->refresh();

        $this->actingAs($stafProdi);
        Livewire::test(ListIncomingLetters::class)->assertActionVisible('exportExcel');
        Livewire::test(ListOutgoingLetters::class)->assertActionHidden('exportExcel');

        Permission::findOrCreate('outgoing_letter.export_excel', 'web');
        $stafProdi->givePermissionTo('outgoing_letter.export_excel');
        $stafProdi->refresh();

        $this->actingAs($stafProdi);
        Livewire::test(ListOutgoingLetters::class)->assertActionVisible('exportExcel');
    }

    public function test_incoming_letter_export_validates_dates_dispatches_job_and_enforces_recipient_isolation(): void
    {
        Queue::fake([GenerateIncomingLetterExportJob::class]);

        $superAdmin = User::where('email', 'admin@univbi.ac.id')->firstOrFail();
        $kaprodiTi = User::where('email', 'kaprodi.ti@univbi.ac.id')->firstOrFail();
        $kaprodiSi = User::where('email', 'kaprodi.si@univbi.ac.id')->firstOrFail();
        $unitTi = Unit::where('code', 'TI')->firstOrFail();
        $academicYear = AcademicYear::firstOrFail();

        Permission::findOrCreate('incoming_letter.export_excel', 'web');
        $kaprodiTi->givePermissionTo('incoming_letter.export_excel');
        $kaprodiTi->refresh();

        $this->actingAs($kaprodiTi);

        // 1. Validasi wajib received_from & received_until
        Livewire::test(ListIncomingLetters::class)
            ->callAction('exportExcel', data: [
                'received_from' => null,
                'received_until' => null,
            ])
            ->assertHasActionErrors([
                'received_from' => 'required',
                'received_until' => 'required',
            ]);

        // 2. Hasil kosong -> warning, tidak masuk antrean
        Livewire::test(ListIncomingLetters::class)
            ->callAction('exportExcel', data: [
                'received_from' => '2026-11-01',
                'received_until' => '2026-11-30',
            ])
            ->assertHasNoActionErrors()
            ->assertNotified('Tidak Ada Surat Masuk yang Cocok');

        Queue::assertNothingPushed();

        // 3. Buat 2 surat masuk: 1 ditujukan ke Kaprodi TI (step_order 1 <= current_step 1), 1 hanya untuk Kaprodi SI
        $accessibleLetter = IncomingLetter::create([
            'unit_id' => $unitTi->id,
            'academic_year_id' => $academicYear->id,
            'registered_by' => $superAdmin->id,
            'sender_name' => '=SUM(1,2) Kemdiktisaintek',
            'external_letter_number' => '100/LLDIKTI/XI/2026',
            'external_letter_date' => '2026-11-10',
            'received_date' => '2026-11-12',
            'agenda_number' => '001/UNIVBI.HUMAS/SM/XI/2026',
            'subject' => 'Undangan Koordinasi Akreditasi',
            'priority' => LetterPriority::Penting,
            'status' => IncomingLetterStatus::InProgress,
            'current_step' => 1,
            'total_steps' => 1,
        ]);

        IncomingLetterRecipient::create([
            'incoming_letter_id' => $accessibleLetter->id,
            'step_order' => 1,
            'recipient_user_id' => $kaprodiTi->id,
            'recipient_position_snapshot' => 'Kaprodi TI',
            'disposition_note' => 'Segera tindak lanjuti',
            'status' => IncomingRecipientStatus::Unread,
        ]);

        $otherLetter = IncomingLetter::create([
            'unit_id' => $unitTi->id,
            'academic_year_id' => $academicYear->id,
            'registered_by' => $superAdmin->id,
            'sender_name' => 'Instansi Lain',
            'external_letter_number' => '200/EXT/XI/2026',
            'external_letter_date' => '2026-11-11',
            'received_date' => '2026-11-13',
            'agenda_number' => '002/UNIVBI.HUMAS/SM/XI/2026',
            'subject' => 'Surat Khusus Kaprodi SI',
            'priority' => LetterPriority::Rahasia,
            'status' => IncomingLetterStatus::InProgress,
            'current_step' => 1,
            'total_steps' => 1,
        ]);

        IncomingLetterRecipient::create([
            'incoming_letter_id' => $otherLetter->id,
            'step_order' => 1,
            'recipient_user_id' => $kaprodiSi->id,
            'status' => IncomingRecipientStatus::Unread,
        ]);

        Livewire::test(ListIncomingLetters::class)
            ->callAction('exportExcel', data: [
                'received_from' => '2026-11-01',
                'received_until' => '2026-11-30',
                'priority' => [LetterPriority::Penting->value, LetterPriority::Rahasia->value],
            ])
            ->assertHasNoActionErrors()
            ->assertNotified('Ekspor Excel Surat Masuk Diproses di Latar Belakang');

        $export = BulkExport::where('module_type', 'incoming_letters_excel')->firstOrFail();
        $this->assertSame(1, $export->total_files);

        Queue::assertPushed(GenerateIncomingLetterExportJob::class);

        $ok = app(IncomingLetterQueuedExportService::class)->process($export);
        $this->assertTrue($ok);

        $export->refresh();
        $this->assertSame('completed', $export->status);
        Storage::disk('private')->assertExists((string) $export->zip_file_path);

        $spreadsheet = IOFactory::load(Storage::disk('private')->path((string) $export->zip_file_path));
        $this->assertSame(['Rekapitulasi Surat Masuk', 'Ringkasan Rekapitulasi'], $spreadsheet->getSheetNames());

        $sheet = $spreadsheet->getSheetByName('Rekapitulasi Surat Masuk');
        $this->assertSame('001/UNIVBI.HUMAS/SM/XI/2026', $sheet->getCell('B7')->getValue());
        // Formula injection pada sender_name dinetralkan
        $this->assertSame('\'=SUM(1,2) Kemdiktisaintek', $sheet->getCell('F7')->getValue());
        $this->assertStringContainsString('Segera tindak lanjuti', (string) $sheet->getCell('L7')->getValue());
        $this->assertStringStartsWith('TOTAL', (string) $sheet->getCell('A8')->getValue());

        $this->actingAs($kaprodiTi)
            ->get(route('admin.bulk-exports.download', $export->uuid))
            ->assertOk();

        $this->actingAs($kaprodiSi)
            ->get(route('admin.bulk-exports.download', $export->uuid))
            ->assertForbidden();
    }

    public function test_outgoing_letter_export_validates_dates_dispatches_job_and_enforces_unit_tenancy(): void
    {
        Queue::fake([GenerateOutgoingLetterExportJob::class]);

        $superAdmin = User::where('email', 'admin@univbi.ac.id')->firstOrFail();
        $kaprodiTi = User::where('email', 'kaprodi.ti@univbi.ac.id')->firstOrFail();
        $kaprodiSi = User::where('email', 'kaprodi.si@univbi.ac.id')->firstOrFail();
        $academicYear = AcademicYear::firstOrFail();

        Permission::findOrCreate('outgoing_letter.export_excel', 'web');
        $kaprodiTi->givePermissionTo('outgoing_letter.export_excel');
        $kaprodiTi->refresh();

        // Surat Keluar milik Unit TI
        OutgoingLetter::create([
            'unit_id' => $kaprodiTi->unit_id,
            'academic_year_id' => $academicYear->id,
            'created_by' => $kaprodiTi->id,
            'reviewed_by' => $superAdmin->id,
            'destination_organization' => '+628123456789 Mitra Industri',
            'subject' => 'Permohonan Kerja Sama Magang',
            'letter_date' => '2026-11-18',
            'letter_number' => '005/UNIVBI.TI/SKL/XI/2026',
            'status' => OutgoingLetterStatus::Published,
            'requires_wet_signature' => true,
            'review_note' => 'Disetujui Humas',
            'reviewed_at' => '2026-11-18 10:00:00',
        ]);

        // Surat Keluar milik Unit SI (tidak boleh ikut terekspor oleh Kaprodi TI)
        OutgoingLetter::create([
            'unit_id' => $kaprodiSi->unit_id,
            'academic_year_id' => $academicYear->id,
            'created_by' => $kaprodiSi->id,
            'destination_organization' => 'Mitra SI',
            'subject' => 'Surat Internal Prodi SI',
            'letter_date' => '2026-11-18',
            'letter_number' => '006/UNIVBI.SI/SKL/XI/2026',
            'status' => OutgoingLetterStatus::Published,
            'requires_wet_signature' => false,
        ]);

        $this->actingAs($kaprodiTi);

        Livewire::test(ListOutgoingLetters::class)
            ->callAction('exportExcel', data: [
                'letter_from' => '2026-11-01',
                'letter_until' => '2026-11-30',
                'requires_wet_signature' => '1',
            ])
            ->assertHasNoActionErrors()
            ->assertNotified('Ekspor Excel Surat Keluar Diproses di Latar Belakang');

        $export = BulkExport::where('module_type', 'outgoing_letters_excel')->firstOrFail();
        $this->assertSame(1, $export->total_files);

        Queue::assertPushed(GenerateOutgoingLetterExportJob::class);

        $ok = app(OutgoingLetterQueuedExportService::class)->process($export);
        $this->assertTrue($ok);

        $export->refresh();
        $this->assertSame('completed', $export->status);
        Storage::disk('private')->assertExists((string) $export->zip_file_path);

        $spreadsheet = IOFactory::load(Storage::disk('private')->path((string) $export->zip_file_path));
        $this->assertSame(['Rekapitulasi Surat Keluar', 'Ringkasan Rekapitulasi'], $spreadsheet->getSheetNames());

        $sheet = $spreadsheet->getSheetByName('Rekapitulasi Surat Keluar');
        $this->assertSame('005/UNIVBI.TI/SKL/XI/2026', $sheet->getCell('B7')->getValue());
        // Formula injection (+628...) dinetralkan
        $this->assertSame('\'+628123456789 Mitra Industri', $sheet->getCell('E7')->getValue());
        $this->assertSame('Disetujui Humas', $sheet->getCell('L7')->getValue());
        $this->assertStringStartsWith('TOTAL', (string) $sheet->getCell('A8')->getValue());

        $this->actingAs($kaprodiTi)
            ->get(route('admin.bulk-exports.download', $export->uuid))
            ->assertOk();

        $this->actingAs($kaprodiSi)
            ->get(route('admin.bulk-exports.download', $export->uuid))
            ->assertForbidden();
    }
}
