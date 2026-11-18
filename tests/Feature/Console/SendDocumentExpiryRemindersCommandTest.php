<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use App\Enums\DocumentStatus;
use App\Enums\VisibilityLevel;
use App\Models\AcademicYear;
use App\Models\Document;
use App\Models\DocumentCategory;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class SendDocumentExpiryRemindersCommandTest extends TestCase
{
    use LazilyRefreshDatabase;

    private Unit $unit;

    private DocumentCategory $category;

    private AcademicYear $academicYear;

    private User $uploader;

    private User $recipient;

    protected function setUp(): void
    {
        parent::setUp();

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

        $this->recipient = User::factory()->create([
            'name' => 'Dekan Fakultas',
            'email' => 'dekan@univbi.ac.id',
            'unit_id' => $this->unit->id,
            'is_active' => true,
        ]);
    }

    public function test_command_executes_successfully_and_displays_summary_table(): void
    {
        Notification::fake();

        Document::create([
            'title' => 'MoU Kerja Sama PT Solusi Mandiri',
            'unit_id' => $this->unit->id,
            'document_category_id' => $this->category->id,
            'academic_year_id' => $this->academicYear->id,
            'uploaded_by' => $this->uploader->id,
            'document_number' => '002/UNIVBI.TI/MOU/XI/2026',
            'visibility' => VisibilityLevel::Public,
            'status' => DocumentStatus::Approved,
            'has_expiry' => true,
            'issued_date' => now()->toDateString(),
            'expired_date' => now()->addDays(30)->toDateString(),
            'reminder_intervals' => ['h30'],
            'reminder_recipients' => [$this->recipient->id],
        ]);

        $this->artisan('documents:send-expiry-reminders')
            ->expectsOutputToContain('Menjalankan Evaluasi Pengingat Masa Berlaku Dokumen')
            ->expectsOutputToContain('Dokumen Aktif Diperiksa')
            ->expectsOutputToContain('Pemeriksaan pengingat kedaluwarsa dokumen selesai dilaksanakan.')
            ->assertSuccessful();
    }

    public function test_command_accepts_date_option_for_simulation(): void
    {
        Notification::fake();

        Document::create([
            'title' => 'MoU Kerja Sama Tanggal Khusus',
            'unit_id' => $this->unit->id,
            'document_category_id' => $this->category->id,
            'academic_year_id' => $this->academicYear->id,
            'uploaded_by' => $this->uploader->id,
            'document_number' => '003/UNIVBI.TI/MOU/XI/2026',
            'visibility' => VisibilityLevel::Public,
            'status' => DocumentStatus::Approved,
            'has_expiry' => true,
            'issued_date' => '2026-11-01',
            'expired_date' => '2026-12-15',
            'reminder_intervals' => ['h30'],
            'reminder_recipients' => [$this->recipient->id],
        ]);

        $this->artisan('documents:send-expiry-reminders --date=2026-11-15')
            ->expectsOutputToContain('Tanggal Evaluasi: 15 November 2026')
            ->assertSuccessful();
    }

    public function test_command_accepts_dry_run_option_without_side_effects(): void
    {
        Notification::fake();

        Document::create([
            'title' => 'MoU Kerja Sama Dry Run',
            'unit_id' => $this->unit->id,
            'document_category_id' => $this->category->id,
            'academic_year_id' => $this->academicYear->id,
            'uploaded_by' => $this->uploader->id,
            'document_number' => '004/UNIVBI.TI/MOU/XI/2026',
            'visibility' => VisibilityLevel::Public,
            'status' => DocumentStatus::Approved,
            'has_expiry' => true,
            'issued_date' => now()->toDateString(),
            'expired_date' => now()->addDays(30)->toDateString(),
            'reminder_intervals' => ['h30'],
            'reminder_recipients' => [$this->recipient->id],
        ]);

        $this->artisan('documents:send-expiry-reminders --dry-run')
            ->expectsOutputToContain('[DRY-RUN / SIMULASI]')
            ->assertSuccessful();

        Notification::assertNothingSent();
    }

    public function test_command_fails_on_invalid_date_format(): void
    {
        $this->artisan('documents:send-expiry-reminders --date=bukan-tanggal')
            ->expectsOutputToContain('Format parameter --date tidak valid')
            ->assertFailed();
    }

    public function test_command_is_registered_in_console_schedule(): void
    {
        $schedule = app(Schedule::class);

        $events = collect($schedule->events());

        $reminderEvent = $events->first(function (Event $event): bool {
            return str_contains($event->command, 'documents:send-expiry-reminders');
        });

        $this->assertNotNull($reminderEvent, 'Command documents:send-expiry-reminders belum terdaftar di Schedule console.');
        $this->assertSame('0 7 * * *', $reminderEvent->expression);
        $this->assertSame('Asia/Jakarta', $reminderEvent->timezone);
    }
}
