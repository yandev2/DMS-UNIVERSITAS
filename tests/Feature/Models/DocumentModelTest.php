<?php

namespace Tests\Feature\Models;

use App\Enums\ApprovalStatus;
use App\Enums\DegreeLevel;
use App\Enums\DocumentStatus;
use App\Enums\StampingPosition;
use App\Enums\StorageDisk;
use App\Enums\UnitType;
use App\Enums\VisibilityLevel;
use App\Models\AcademicYear;
use App\Models\AccreditationCriterion;
use App\Models\ActivityLog;
use App\Models\Document;
use App\Models\DocumentApproval;
use App\Models\DocumentCategory;
use App\Models\DocumentFile;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class DocumentModelTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_creates_document_with_auto_generated_uuid_slug_and_audit_log(): void
    {
        $unit = Unit::create([
            'code' => 'IF',
            'name' => 'Prodi Informatika',
            'slug' => 'prodi-informatika',
            'type' => UnitType::Prodi,
            'degree_level' => DegreeLevel::S1,
            'is_active' => true,
        ]);

        $user = User::factory()->create([
            'unit_id' => $unit->id,
            'position_title' => 'Kaprodi Informatika',
        ]);

        $category = DocumentCategory::create([
            'code' => 'SK',
            'name' => 'Surat Keputusan',
            'slug' => 'surat-keputusan',
            'stamping_position' => StampingPosition::TopRight,
            'requires_approval' => true,
            'is_active' => true,
        ]);

        $academicYear = AcademicYear::create([
            'code' => '2025/2026-1',
            'name' => '2025/2026 Ganjil',
            'semester' => 'Ganjil',
            'start_date' => '2025-09-01',
            'end_date' => '2026-02-28',
            'is_active' => true,
        ]);

        $document = Document::create([
            'title' => 'SK Kurikulum OBE Informatika 2026',
            'unit_id' => $unit->id,
            'document_category_id' => $category->id,
            'academic_year_id' => $academicYear->id,
            'uploaded_by' => $user->id,
            'document_number' => '001/SK/IF/UNBI/XI/2026',
            'visibility' => VisibilityLevel::Public,
            'status' => DocumentStatus::Approved,
            'is_borang' => true,
            'has_expiry' => true,
            'issued_date' => '2026-11-01',
            'expired_date' => '2030-11-01',
            'reminder_intervals' => ['h60', 'h30', 'h7'],
        ]);

        $this->assertNotEmpty($document->uuid);
        $this->assertSame('sk-kurikulum-obe-informatika-2026', $document->slug);
        $this->assertSame(VisibilityLevel::Public, $document->visibility);
        $this->assertSame(DocumentStatus::Approved, $document->status);
        $this->assertSame(['h60', 'h30', 'h7'], $document->reminder_intervals);

        $auditLog = ActivityLog::query()
            ->where('subject_type', Document::class)
            ->where('subject_id', $document->id)
            ->where('event', 'created')
            ->first();

        $this->assertNotNull($auditLog);
        $this->assertSame('document_audit', $auditLog->log_name);
    }

    public function test_returns_latest_active_primary_file_and_persists_google_drive_sync_fields(): void
    {
        $this->travelTo('2026-11-09 20:00:00');

        $unit = Unit::create([
            'code' => 'SI',
            'name' => 'Prodi Sistem Informasi',
            'slug' => 'prodi-sistem-informasi',
            'type' => UnitType::Prodi,
            'degree_level' => DegreeLevel::S1,
            'is_active' => true,
        ]);

        $user = User::factory()->create(['unit_id' => $unit->id]);

        $category = DocumentCategory::create([
            'code' => 'PED',
            'name' => 'Pedoman Akademik',
            'slug' => 'pedoman-akademik',
            'stamping_position' => StampingPosition::TopRight,
            'requires_approval' => false,
            'is_active' => true,
        ]);

        $document = Document::create([
            'title' => 'Pedoman Skripsi SI',
            'unit_id' => $unit->id,
            'document_category_id' => $category->id,
            'uploaded_by' => $user->id,
            'visibility' => VisibilityLevel::Internal,
            'status' => DocumentStatus::Approved,
            'issued_date' => '2026-11-09',
        ]);

        DocumentFile::create([
            'document_id' => $document->id,
            'uploaded_by' => $user->id,
            'is_primary' => true,
            'label' => 'Naskah Utama V1',
            'order_index' => 1,
            'version' => 1,
            'original_file_name' => 'pedoman-v1.pdf',
            'standardized_file_name' => 'UNBI_PED_SI_v1.pdf',
            'original_file_path' => 'documents/2026/11/pedoman-v1.pdf',
            'disk' => StorageDisk::Private,
            'mime_type' => 'application/pdf',
            'file_extension' => 'pdf',
            'file_size' => 12400,
            'is_active' => false,
            'is_synced' => false,
        ]);

        $latestFile = DocumentFile::create([
            'document_id' => $document->id,
            'uploaded_by' => $user->id,
            'is_primary' => true,
            'label' => 'Naskah Utama V2 (Revisi)',
            'order_index' => 1,
            'version' => 2,
            'original_file_name' => 'pedoman-v2.pdf',
            'standardized_file_name' => 'UNBI_PED_SI_v2.pdf',
            'original_file_path' => 'documents/2026/11/pedoman-v2.pdf',
            'stamped_file_path' => 'documents/2026/11/pedoman-v2-stamped.pdf',
            'disk' => StorageDisk::Private,
            'mime_type' => 'application/pdf',
            'file_extension' => 'pdf',
            'file_size' => 15800,
            'is_active' => true,
            'google_drive_id' => '1AbCdEfGhIjKlMnOpQrStUvWxYz',
            'google_drive_url' => 'https://drive.google.com/file/d/1AbCdEfGhIjKlMnOpQrStUvWxYz/view',
            'is_synced' => true,
            'synced_at' => now(),
        ]);

        $resolvedPrimary = $document->primaryFile;

        $this->assertNotNull($resolvedPrimary);
        $this->assertSame($latestFile->id, $resolvedPrimary->id);
        $this->assertSame(2, $resolvedPrimary->version);
        $this->assertSame(StorageDisk::Private, $resolvedPrimary->disk);
        $this->assertTrue($resolvedPrimary->is_synced);
        $this->assertSame('1AbCdEfGhIjKlMnOpQrStUvWxYz', $resolvedPrimary->google_drive_id);
        $this->assertSame('2026-11-09 20:00:00', $resolvedPrimary->synced_at?->format('Y-m-d H:i:s'));
    }

    public function test_orders_approval_steps_sequentially_and_attaches_borang_criteria_with_pivot_data(): void
    {
        $unit = Unit::create([
            'code' => 'LPM',
            'name' => 'Lembaga Penjaminan Mutu',
            'slug' => 'lpm',
            'type' => UnitType::Lembaga,
            'is_active' => true,
        ]);

        $user = User::factory()->create(['unit_id' => $unit->id]);

        $category = DocumentCategory::create([
            'code' => 'SOP',
            'name' => 'Standar Operasional Prosedur',
            'slug' => 'sop',
            'stamping_position' => StampingPosition::TopRight,
            'requires_approval' => true,
            'is_active' => true,
        ]);

        $criterion = AccreditationCriterion::create([
            'code' => 'K2',
            'name' => 'Kriteria 2 - Tata Pamong, Tata Kelola, dan Kerjasama',
            'instrument_type' => 'BAN-PT 9 Kriteria',
            'order_index' => 2,
            'is_active' => true,
        ]);

        $document = Document::create([
            'title' => 'SOP Audit Mutu Internal',
            'unit_id' => $unit->id,
            'document_category_id' => $category->id,
            'uploaded_by' => $user->id,
            'visibility' => VisibilityLevel::Internal,
            'status' => DocumentStatus::Pending,
            'current_step' => 1,
            'total_steps' => 2,
            'is_borang' => true,
            'issued_date' => '2026-11-09',
        ]);

        DocumentApproval::create([
            'document_id' => $document->id,
            'revision_round' => 1,
            'step_order' => 2,
            'approver_role' => 'rektor',
            'status' => ApprovalStatus::Waiting,
        ]);

        DocumentApproval::create([
            'document_id' => $document->id,
            'revision_round' => 1,
            'step_order' => 1,
            'approver_role' => 'ketua_lpm',
            'status' => ApprovalStatus::Approved,
            'approved_by' => $user->id,
            'approver_position_snapshot' => 'Ketua LPM UNBI',
            'approved_at' => '2026-11-09 10:00:00',
        ]);

        $document->accreditationCriteria()->attach($criterion->id, [
            'indicator_code' => '2.4.a',
            'order_index' => 1,
            'note' => 'Bukti pelaksanaan AMI tahunan',
        ]);

        $approvals = $document->approvals;
        $attachedCriteria = $document->accreditationCriteria;

        $this->assertCount(2, $approvals);
        $this->assertSame(1, $approvals[0]->step_order);
        $this->assertSame(ApprovalStatus::Approved, $approvals[0]->status);
        $this->assertSame(2, $approvals[1]->step_order);
        $this->assertSame(ApprovalStatus::Waiting, $approvals[1]->status);

        $this->assertCount(1, $attachedCriteria);
        $this->assertSame('2.4.a', $attachedCriteria[0]->pivot->indicator_code);
        $this->assertSame('Bukti pelaksanaan AMI tahunan', $attachedCriteria[0]->pivot->note);
    }
}
