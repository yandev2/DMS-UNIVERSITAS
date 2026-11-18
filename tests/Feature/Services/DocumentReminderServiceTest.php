<?php

declare(strict_types=1);

namespace Tests\Feature\Services;

use App\Enums\DocumentStatus;
use App\Enums\ReminderInterval;
use App\Enums\VisibilityLevel;
use App\Models\AcademicYear;
use App\Models\Document;
use App\Models\DocumentCategory;
use App\Models\ReminderLog;
use App\Models\Unit;
use App\Models\User;
use App\Notifications\DocumentExpiryReminderNotification;
use App\Services\DocumentReminderService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class DocumentReminderServiceTest extends TestCase
{
    use LazilyRefreshDatabase;

    private DocumentReminderService $service;

    private Unit $unit;

    private DocumentCategory $category;

    private AcademicYear $academicYear;

    private User $uploader;

    private User $recipient1;

    private User $recipient2;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new DocumentReminderService;

        $this->unit = Unit::create([
            'code' => 'TI',
            'name' => 'Program Studi S1 Informatika',
            'slug' => 's1-informatika',
            'is_active' => true,
        ]);

        $this->category = DocumentCategory::create([
            'code' => 'MOU',
            'name' => 'Nota Kesepahaman (MoU)',
            'slug' => 'mou',
            'has_expiry' => true,
            'is_active' => true,
        ]);

        $this->academicYear = AcademicYear::create([
            'code' => '2026/2027-1',
            'name' => '2026/2027 Ganjil',
            'semester' => 'Ganjil',
            'start_date' => '2026-09-01',
            'end_date' => '2027-02-28',
            'is_active' => true,
        ]);

        $this->uploader = User::factory()->create([
            'name' => 'Staf Pengunggah',
            'email' => 'uploader@univbi.ac.id',
            'unit_id' => $this->unit->id,
            'is_active' => true,
        ]);

        $this->recipient1 = User::factory()->create([
            'name' => 'Dekan Fakultas',
            'email' => 'dekan@univbi.ac.id',
            'unit_id' => $this->unit->id,
            'is_active' => true,
        ]);

        $this->recipient2 = User::factory()->create([
            'name' => 'Kaprodi TI',
            'email' => 'kaprodi@univbi.ac.id',
            'unit_id' => $this->unit->id,
            'is_active' => true,
        ]);
    }

    private function createExpiringDocument(array $attributes = []): Document
    {
        static $seq = 1;

        return Document::create(array_merge([
            'title' => 'MoU Kerja Sama Industri PT Teknologi',
            'unit_id' => $this->unit->id,
            'document_category_id' => $this->category->id,
            'academic_year_id' => $this->academicYear->id,
            'uploaded_by' => $this->uploader->id,
            'document_number' => sprintf('00%d/UNIVBI.TI/MOU/XI/2026', $seq++),
            'visibility' => VisibilityLevel::Public,
            'status' => DocumentStatus::Approved,
            'has_expiry' => true,
            'issued_date' => '2026-11-01',
            'expired_date' => '2026-12-12', // 30 hari dari 12 Nov 2026
            'reminder_intervals' => ['h60', 'h30', 'h7'],
            'reminder_recipients' => [$this->recipient1->id, $this->recipient2->id],
        ], $attributes));
    }

    public function test_service_sends_notification_at_due_intervals_and_records_reminder_logs(): void
    {
        Notification::fake();

        $today = Carbon::parse('2026-11-12'); // Tepat 30 hari sebelum 2026-12-12

        $document = $this->createExpiringDocument([
            'expired_date' => '2026-12-12',
        ]);

        $result = $this->service->run($today);

        $this->assertSame(1, $result->scannedCount);
        $this->assertSame(2, $result->sentCount); // Dikirim ke 2 penerima
        $this->assertSame(0, $result->failedCount);

        Notification::assertSentTo(
            [$this->recipient1, $this->recipient2],
            DocumentExpiryReminderNotification::class,
            function (DocumentExpiryReminderNotification $notification) use ($document) {
                return $notification->document->id === $document->id
                    && $notification->interval === ReminderInterval::H30
                    && $notification->daysRemaining === 30;
            }
        );

        $logDekan = ReminderLog::where('document_id', $document->id)
            ->where('recipient_email', 'dekan@univbi.ac.id')
            ->first();

        $this->assertNotNull($logDekan);
        $this->assertSame('h30', $logDekan->interval_type->value);
        $this->assertSame('2026-12-12', $logDekan->expired_date_snapshot->format('Y-m-d'));
        $this->assertTrue($logDekan->is_success);

        $logKaprodi = ReminderLog::where('document_id', $document->id)
            ->where('recipient_email', 'kaprodi@univbi.ac.id')
            ->first();

        $this->assertNotNull($logKaprodi);
        $this->assertSame('h30', $logKaprodi->interval_type->value);
        $this->assertSame('2026-12-12', $logKaprodi->expired_date_snapshot->format('Y-m-d'));
        $this->assertTrue($logKaprodi->is_success);
    }

    public function test_service_anti_duplicate_skips_when_already_sent_for_same_expiry_cycle(): void
    {
        Notification::fake();

        $today = Carbon::parse('2026-11-12');
        $document = $this->createExpiringDocument(['expired_date' => '2026-12-12']);

        // Jalankan pertama kali
        $this->service->run($today);
        $this->assertSame(2, ReminderLog::count());

        // Jalankan keesokan harinya (sisa 29 hari, interval H30 sudah dikirim)
        $tomorrow = Carbon::parse('2026-11-13');
        $secondResult = $this->service->run($tomorrow);

        $this->assertSame(0, $secondResult->sentCount);
        $this->assertSame(2, $secondResult->skippedCount);
        $this->assertSame(2, ReminderLog::count()); // Tidak ada duplikasi log
    }

    public function test_service_resends_new_cycle_when_document_expired_date_is_extended(): void
    {
        Notification::fake();

        $today = Carbon::parse('2026-11-12');
        $document = $this->createExpiringDocument(['expired_date' => '2026-12-12']);

        $this->service->run($today);
        $this->assertSame(2, ReminderLog::count());

        // Dokumen diperpanjang hingga tahun depan (2027-12-12)
        $document->update(['expired_date' => '2027-12-12']);

        // Setahun kemudian pada H-30 siklus baru (2027-11-12)
        $nextYearH30 = Carbon::parse('2027-11-12');
        $nextYearResult = $this->service->run($nextYearH30);

        $this->assertSame(2, $nextYearResult->sentCount);
        $this->assertSame(4, ReminderLog::count());

        $newCycleLog = ReminderLog::where('document_id', $document->id)
            ->where('recipient_email', 'dekan@univbi.ac.id')
            ->where('expired_date_snapshot', 'like', '2027-12-12%')
            ->first();

        $this->assertNotNull($newCycleLog);
        $this->assertSame('h30', $newCycleLog->interval_type->value);
        $this->assertTrue($newCycleLog->is_success);
    }

    public function test_service_catch_up_mode_sends_most_urgent_interval_when_multiple_intervals_passed(): void
    {
        Notification::fake();

        // Dokumen memiliki interval h60, h30, h7. Dievaluasi saat tersisa 5 hari (H7)
        $today = Carbon::parse('2026-12-07');
        $document = $this->createExpiringDocument(['expired_date' => '2026-12-12']);

        $result = $this->service->run($today);

        // Hanya H7 yang dikirim (tidak mengirimkan H60 dan H30 sekaligus)
        $this->assertSame(2, $result->sentCount);

        Notification::assertSentTo(
            $this->recipient1,
            DocumentExpiryReminderNotification::class,
            function (DocumentExpiryReminderNotification $notification) {
                return $notification->interval === ReminderInterval::H7
                    && $notification->daysRemaining === 5;
            }
        );

        $this->assertDatabaseHas('reminder_logs', [
            'document_id' => $document->id,
            'recipient_email' => 'dekan@univbi.ac.id',
            'interval_type' => 'h7',
        ]);

        $this->assertDatabaseMissing('reminder_logs', [
            'document_id' => $document->id,
            'interval_type' => 'h30',
        ]);
    }

    public function test_service_auto_marks_expired_documents_when_past_expired_date(): void
    {
        $today = Carbon::parse('2026-12-13'); // 1 hari setelah 2026-12-12

        $document = $this->createExpiringDocument([
            'status' => DocumentStatus::Approved,
            'expired_date' => '2026-12-12',
        ]);

        $result = $this->service->run($today);

        $this->assertSame(1, $result->expiredMarkedCount);

        $document->refresh();
        $this->assertSame(DocumentStatus::Expired, $document->status);

        $this->assertDatabaseHas('activity_log', [
            'subject_type' => Document::class,
            'subject_id' => $document->id,
        ]);
    }

    public function test_service_falls_back_to_uploader_when_reminder_recipients_is_empty(): void
    {
        Notification::fake();

        $today = Carbon::parse('2026-11-12');

        $document = $this->createExpiringDocument([
            'expired_date' => '2026-12-12',
            'reminder_recipients' => [], // Kosong
        ]);

        $result = $this->service->run($today);

        $this->assertSame(1, $result->sentCount); // Dikirim ke uploader

        Notification::assertSentTo(
            $this->uploader,
            DocumentExpiryReminderNotification::class,
            function (DocumentExpiryReminderNotification $notification) use ($document) {
                return $notification->document->id === $document->id;
            }
        );

        $this->assertDatabaseHas('reminder_logs', [
            'document_id' => $document->id,
            'recipient_email' => 'uploader@univbi.ac.id',
        ]);
    }

    public function test_service_ignores_inactive_users_and_soft_deleted_documents(): void
    {
        Notification::fake();

        $this->recipient1->update(['is_active' => false]);

        $today = Carbon::parse('2026-11-12');

        $docActive = $this->createExpiringDocument(['expired_date' => '2026-12-12']);

        $docDeleted = $this->createExpiringDocument([
            'title' => 'Dokumen Dihapus',
            'expired_date' => '2026-12-12',
        ]);
        $docDeleted->delete();

        $result = $this->service->run($today);

        // Hanya dikirim ke recipient2 yang aktif pada docActive
        $this->assertSame(1, $result->sentCount);

        Notification::assertNotSentTo($this->recipient1, DocumentExpiryReminderNotification::class);
        Notification::assertSentTo($this->recipient2, DocumentExpiryReminderNotification::class);

        $this->assertDatabaseMissing('reminder_logs', [
            'document_id' => $docDeleted->id,
        ]);
    }

    public function test_service_dry_run_does_not_modify_database_or_send_notifications(): void
    {
        Notification::fake();

        $today = Carbon::parse('2026-11-12');
        $document = $this->createExpiringDocument(['expired_date' => '2026-12-12']);

        $result = $this->service->run($today, dryRun: true);

        $this->assertSame(2, $result->sentCount);
        $this->assertSame('would_send', $result->details[0]['status']);

        Notification::assertNothingSent();
        $this->assertSame(0, ReminderLog::count());
    }

    public function test_service_ignores_archived_passive_documents_for_expiry_and_reminders(): void
    {
        Notification::fake();

        $today = Carbon::parse('2026-11-12');

        // Dokumen pasif dengan tanggal lampau dan H-30 tidak boleh diproses
        $archivedPast = $this->createExpiringDocument([
            'status' => DocumentStatus::Archived,
            'expired_date' => '2026-11-01',
        ]);

        $archivedUpcoming = $this->createExpiringDocument([
            'status' => DocumentStatus::Archived,
            'expired_date' => '2026-12-12',
        ]);

        $result = $this->service->run($today);

        $this->assertSame(0, $result->expiredMarkedCount);
        $this->assertSame(0, $result->scannedCount);
        $this->assertSame(0, $result->sentCount);

        $this->assertSame(DocumentStatus::Archived, $archivedPast->fresh()->status);
        $this->assertSame(DocumentStatus::Archived, $archivedUpcoming->fresh()->status);

        Notification::assertNothingSent();
        $this->assertSame(0, ReminderLog::count());
    }
}
