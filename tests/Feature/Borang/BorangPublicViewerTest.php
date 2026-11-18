<?php

declare(strict_types=1);

namespace Tests\Feature\Borang;

use App\Enums\DocumentStatus;
use App\Enums\StorageDisk;
use App\Models\AcademicYear;
use App\Models\AccreditationCriterion;
use App\Models\Document;
use App\Models\DocumentCategory;
use App\Models\DocumentFile;
use App\Models\Unit;
use App\Models\User;
use Database\Seeders\MasterDataSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BorangPublicViewerTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function createBorangDocument(Unit $unit): Document
    {
        $criterionK6 = AccreditationCriterion::where('code', 'K6')->firstOrFail();
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

    public function test_assessor_can_view_document_when_access_is_open(): void
    {
        $this->seed(MasterDataSeeder::class);
        Storage::fake(StorageDisk::Private->value);

        $unitTi = Unit::where('code', 'TI')->firstOrFail();
        $unitTi->update([
            'is_assessor_access_active' => true,
            'assessor_access_start' => now()->subDays(5)->toDateString(),
            'assessor_access_end' => now()->addDays(30)->toDateString(),
        ]);

        $document = $this->createBorangDocument($unitTi);

        $response = $this->get("/bukti-borang/{$document->uuid}");

        $response->assertOk();
        $response->assertSee('SK Kurikulum Informatika 2026');
        $response->assertSee('001/UNIVBI.TI/SK/XI/2026');
        $response->assertSee('K6');
        $response->assertSee('Dokumen Sah');
    }

    public function test_assessor_access_is_blocked_when_access_is_inactive(): void
    {
        $this->seed(MasterDataSeeder::class);
        Storage::fake(StorageDisk::Private->value);

        $unitTi = Unit::where('code', 'TI')->firstOrFail();
        $unitTi->update([
            'is_assessor_access_active' => false,
        ]);

        $document = $this->createBorangDocument($unitTi);

        $response = $this->get("/bukti-borang/{$document->uuid}");

        $response->assertStatus(403);
        $response->assertSee('Masa Asesmen Tidak Aktif');
        $response->assertSee('lpm@univbi.ac.id');
    }

    public function test_assessor_access_is_blocked_when_outside_date_window(): void
    {
        $this->seed(MasterDataSeeder::class);
        Storage::fake(StorageDisk::Private->value);

        $unitTi = Unit::where('code', 'TI')->firstOrFail();
        $unitTi->update([
            'is_assessor_access_active' => true,
            'assessor_access_start' => now()->subDays(30)->toDateString(),
            'assessor_access_end' => now()->subDays(1)->toDateString(), // expired yesterday
        ]);

        $document = $this->createBorangDocument($unitTi);

        $response = $this->get("/bukti-borang/{$document->uuid}");

        $response->assertStatus(403);
        $response->assertSee('Masa Asesmen Tidak Aktif');
    }

    public function test_assessor_access_prompts_passcode_and_unlocks_with_correct_pin(): void
    {
        $this->seed(MasterDataSeeder::class);
        Storage::fake(StorageDisk::Private->value);

        $unitTi = Unit::where('code', 'TI')->firstOrFail();
        $unitTi->update([
            'is_assessor_access_active' => true,
            'assessor_passcode' => 'TI2026',
        ]);

        $document = $this->createBorangDocument($unitTi);

        // 1. First visit -> prompts passcode
        $response = $this->get("/bukti-borang/{$document->uuid}");
        $response->assertOk();
        $response->assertSee('Masukkan PIN / Sandi Akses');

        // 2. Submit wrong passcode
        $failedResponse = $this->post("/bukti-borang/{$document->uuid}", [
            'passcode' => 'WRONG_PIN',
        ]);
        $failedResponse->assertSessionHasErrors('passcode');

        // 3. Submit correct passcode
        $successResponse = $this->post("/bukti-borang/{$document->uuid}", [
            'passcode' => 'TI2026',
        ]);
        $successResponse->assertRedirect("/bukti-borang/{$document->uuid}");

        // 4. Follow redirect -> authenticated and sees document
        $viewerResponse = $this->get("/bukti-borang/{$document->uuid}");
        $viewerResponse->assertOk();
        $viewerResponse->assertSee('SK Kurikulum Informatika 2026');
    }

    public function test_assessor_can_stream_and_download_file(): void
    {
        $this->seed(MasterDataSeeder::class);
        Storage::fake(StorageDisk::Private->value);

        $unitTi = Unit::where('code', 'TI')->firstOrFail();
        $unitTi->update([
            'is_assessor_access_active' => true,
        ]);

        $document = $this->createBorangDocument($unitTi);

        // Stream
        $streamResponse = $this->get("/bukti-borang/{$document->uuid}/stream");
        $streamResponse->assertOk();
        $this->assertStringContainsString('application/pdf', (string) $streamResponse->headers->get('Content-Type'));

        // Download
        $downloadResponse = $this->get("/bukti-borang/{$document->uuid}/download");
        $downloadResponse->assertOk();
        $this->assertStringContainsString('attachment', (string) $downloadResponse->headers->get('Content-Disposition'));
    }

    public function test_single_document_viewer_does_not_render_multi_document_list(): void
    {
        $this->seed(MasterDataSeeder::class);
        Storage::fake(StorageDisk::Private->value);

        $unitTi = Unit::where('code', 'TI')->firstOrFail();
        $unitTi->update([
            'is_assessor_access_active' => true,
        ]);

        $docA = $this->createBorangDocument($unitTi);

        $response = $this->get("/bukti-borang/{$docA->uuid}");
        $response->assertOk();
        $response->assertDontSee('Daftar Bukti Fisik Program Studi');
    }

    public function test_assessor_can_access_full_unit_portal_and_see_all_unit_documents(): void
    {
        $this->seed(MasterDataSeeder::class);
        Storage::fake(StorageDisk::Private->value);

        $unitTi = Unit::where('code', 'TI')->firstOrFail();
        $unitTi->update([
            'is_assessor_access_active' => true,
            'assessor_access_start' => now()->subDays(5)->toDateString(),
            'assessor_access_end' => now()->addDays(30)->toDateString(),
        ]);

        $docA = $this->createBorangDocument($unitTi);

        // Create second document in the same unit
        $criterionK2 = AccreditationCriterion::where('code', 'K2')->firstOrFail();
        $category = DocumentCategory::firstOrFail();
        $academicYear = AcademicYear::firstOrFail();
        $uploader = User::firstOrFail();
        $docB = Document::create([
            'title' => 'MoU Kerja Sama TI 2026',
            'document_number' => '002/UNIVBI.TI/MOU/XI/2026',
            'unit_id' => $unitTi->id,
            'document_category_id' => $category->id,
            'academic_year_id' => $academicYear->id,
            'is_borang' => true,
            'status' => DocumentStatus::Approved,
            'issued_date' => now()->toDateString(),
            'uploaded_by' => $uploader->id,
        ]);
        $docB->accreditationCriteria()->attach($criterionK2->id, ['order_index' => 1]);

        $response = $this->get("/bukti-borang/unit/{$unitTi->code}");

        $response->assertOk();
        $response->assertSee('Portal Dossier Asesor');
        $response->assertSee($unitTi->name);
        $response->assertSee('SK Kurikulum Informatika 2026');
        $response->assertSee('MoU Kerja Sama TI 2026');
        $response->assertSee('2 Berkas');
    }

    public function test_assessor_can_switch_active_document_in_unit_portal(): void
    {
        $this->seed(MasterDataSeeder::class);
        Storage::fake(StorageDisk::Private->value);

        $unitTi = Unit::where('code', 'TI')->firstOrFail();
        $unitTi->update([
            'is_assessor_access_active' => true,
        ]);

        $docA = $this->createBorangDocument($unitTi);
        $category = DocumentCategory::firstOrFail();
        $academicYear = AcademicYear::firstOrFail();
        $uploader = User::firstOrFail();

        $docB = Document::create([
            'title' => 'SK Laboratorium TI 2026',
            'document_number' => '003/UNIVBI.TI/SK/XI/2026',
            'unit_id' => $unitTi->id,
            'document_category_id' => $category->id,
            'academic_year_id' => $academicYear->id,
            'is_borang' => true,
            'status' => DocumentStatus::Approved,
            'issued_date' => now()->toDateString(),
            'uploaded_by' => $uploader->id,
        ]);

        $response = $this->get("/bukti-borang/unit/{$unitTi->code}?doc={$docB->uuid}");

        $response->assertOk();
        $response->assertSee('SK Laboratorium TI 2026');
        $response->assertSee('003/UNIVBI.TI/SK/XI/2026');
    }

    public function test_unit_portal_respects_access_window_and_passcode(): void
    {
        $this->seed(MasterDataSeeder::class);
        Storage::fake(StorageDisk::Private->value);

        $unitTi = Unit::where('code', 'TI')->firstOrFail();
        $unitTi->update([
            'is_assessor_access_active' => false,
        ]);

        // 1. Inactive -> 403
        $blockedResponse = $this->get("/bukti-borang/unit/{$unitTi->code}");
        $blockedResponse->assertStatus(403);
        $blockedResponse->assertSee('Masa Asesmen Tidak Aktif');

        // 2. Passcode protected
        $unitTi->update([
            'is_assessor_access_active' => true,
            'assessor_passcode' => 'TI999',
        ]);

        $pinPromptResponse = $this->get("/bukti-borang/unit/{$unitTi->code}");
        $pinPromptResponse->assertOk();
        $pinPromptResponse->assertSee('Masukkan PIN / Sandi Akses');

        // Submit wrong PIN
        $wrongResponse = $this->post("/bukti-borang/unit/{$unitTi->code}", ['passcode' => 'WRONG']);
        $wrongResponse->assertSessionHasErrors('passcode');

        // Submit correct PIN
        $correctResponse = $this->post("/bukti-borang/unit/{$unitTi->code}", ['passcode' => 'TI999']);
        $correctResponse->assertRedirect("/bukti-borang/unit/{$unitTi->code}");
    }

    public function test_unit_portal_paginates_filters_by_criterion_year_search_and_tracks_views(): void
    {
        $this->seed(MasterDataSeeder::class);
        Storage::fake(StorageDisk::Private->value);

        $unitTi = Unit::where('code', 'TI')->firstOrFail();
        $unitTi->update([
            'is_assessor_access_active' => true,
        ]);

        $criterionK5 = AccreditationCriterion::where('code', 'K5')->firstOrFail();
        $criterionK6 = AccreditationCriterion::where('code', 'K6')->firstOrFail();

        $academicYears = AcademicYear::orderBy('id')->get();
        $yearA = $academicYears->first();
        $yearB = AcademicYear::create([
            'name' => '2025/2026',
            'start_date' => '2025-09-01',
            'end_date' => '2026-08-31',
            'is_active' => false,
        ]);

        $category = DocumentCategory::firstOrFail();
        $uploader = User::firstOrFail();

        $createdDocs = [];
        for ($i = 1; $i <= 30; $i++) {
            $padded = str_pad((string) $i, 3, '0', STR_PAD_LEFT);
            $doc = Document::create([
                'title' => "Dokumen Uji Skala Borang {$padded}",
                'document_number' => "{$padded}/UNIVBI.TI/SK/XI/2026",
                'unit_id' => $unitTi->id,
                'document_category_id' => $category->id,
                'academic_year_id' => ($i === 30 ? $yearB->id : $yearA->id),
                'is_borang' => true,
                'status' => DocumentStatus::Approved,
                'issued_date' => now()->toDateString(),
                'uploaded_by' => $uploader->id,
            ]);

            $doc->accreditationCriteria()->attach(
                ($i === 30 ? $criterionK5->id : $criterionK6->id),
                ['order_index' => 1]
            );
            $createdDocs[] = $doc;
        }

        // 1. Page 1 renders max 25 items + shows true total (30 Berkas) + Academic Year badge
        $page1 = $this->get("/bukti-borang/unit/{$unitTi->code}");
        $page1->assertOk();
        $page1->assertSee('30 Berkas');
        $page1->assertSee('Dokumen Uji Skala Borang 001');
        $page1->assertSee('Dokumen Uji Skala Borang 025');
        $page1->assertDontSee('Dokumen Uji Skala Borang 030');
        $page1->assertSee('TA '.$yearA->name);
        $page1->assertSee('Berikutnya');

        // 2. Page 2 renders remaining items (026..030)
        $page2 = $this->get("/bukti-borang/unit/{$unitTi->code}?page=2");
        $page2->assertOk();
        $page2->assertSee('Dokumen Uji Skala Borang 026');
        $page2->assertSee('Dokumen Uji Skala Borang 030');
        $page2->assertDontSee('Dokumen Uji Skala Borang 001');
        $page2->assertSee('Sebelumnya');

        // 3. Filter by Criterion (K5) + Year + Search finds doc #030 directly on page 1
        $filtered = $this->get("/bukti-borang/unit/{$unitTi->code}?criterion=K5&year={$yearB->id}&q=030");
        $filtered->assertOk();
        $filtered->assertSee('Dokumen Uji Skala Borang 030');
        $filtered->assertSee('TA 2025/2026');
        $filtered->assertDontSee('Dokumen Uji Skala Borang 001');

        // 4. Async track view increments views_count cleanly
        $targetDoc = $createdDocs[29]->fresh();
        $initialViews = (int) $targetDoc->views_count;
        $this->postJson("/bukti-borang/unit/{$unitTi->code}/track/{$targetDoc->uuid}")
            ->assertOk()
            ->assertJson(['ok' => true]);
        $this->assertSame($initialViews + 1, (int) $targetDoc->fresh()->views_count);
    }
}
