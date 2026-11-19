<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Enums\DocumentStatus;
use App\Enums\OutgoingLetterStatus;
use App\Enums\PostStatus;
use App\Enums\VisibilityLevel;
use App\Filament\Pages\AccreditationDataBank;
use App\Models\Document;
use App\Models\DocumentCategory;
use App\Models\OutgoingLetter;
use App\Models\Post;
use App\Models\PostCategory;
use App\Models\Unit;
use App\Models\User;
use Database\Seeders\CampusStructureSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class TenantIsolationSecurityTest extends TestCase
{
    use LazilyRefreshDatabase;

    private DocumentCategory $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CampusStructureSeeder::class);

        $this->category = DocumentCategory::create([
            'name' => 'Dokumen Operasional & Borang',
            'code' => 'DOK',
            'slug' => 'dokumen-operasional-borang',
            'is_active' => true,
        ]);
    }

    public function test_faculty_dean_can_access_own_faculty_and_child_prodis_only_without_cross_faculty_leak(): void
    {
        $dekanFesh = User::where('email', 'dheo_rimbano@univbinainsan.ac.id')->firstOrFail();
        $kaprodiMnj = User::where('email', 'irmaidayati@univbinainsan.ac.id')->firstOrFail();
        $kaprodiMm = User::where('email', 'herman_paleni@univbinainsan.ac.id')->firstOrFail();
        $kaprodiTi = User::where('email', 'budisantoso@univbinainsan.ac.id')->firstOrFail();
        $dekanFit = User::where('email', 'rudi.kurniawan@univbinainsan.ac.id')->firstOrFail();
        $kaprodiAgt = User::where('email', 'mellyfebriyanti@univbinainsan.ac.id')->firstOrFail();

        $fesh = Unit::where('code', 'FESH')->firstOrFail();
        $mnj = Unit::where('code', 'MNJ')->firstOrFail();
        $mm = Unit::where('code', 'MM')->firstOrFail();
        $ti = Unit::where('code', 'TI')->firstOrFail();
        $fit = Unit::where('code', 'FIT')->firstOrFail();
        $agt = Unit::where('code', 'AGT')->firstOrFail();

        // 1. Verifikasi cakupan unit masing-masing pejabat
        $this->assertTrue($dekanFesh->canAccessUnit($fesh->id));
        $this->assertTrue($dekanFesh->canAccessUnit($mnj->id));
        $this->assertTrue($dekanFesh->canAccessUnit($mm->id));
        $this->assertFalse($dekanFesh->canAccessUnit($fit->id));
        $this->assertFalse($dekanFesh->canAccessUnit($ti->id));
        $this->assertFalse($dekanFesh->canAccessUnit($agt->id));

        // 2. Dokumen internal biasa & dokumen internal bertanda is_borang=true tidak boleh bocor antar fakultas/prodi
        $docFesh = $this->createDoc($fesh->id, $dekanFesh->id, VisibilityLevel::Internal, false);
        $borangMnj = $this->createDoc($mnj->id, $kaprodiMnj->id, VisibilityLevel::Internal, true);
        $borangMm = $this->createDoc($mm->id, $kaprodiMm->id, VisibilityLevel::Internal, true);
        $docFit = $this->createDoc($fit->id, $dekanFit->id, VisibilityLevel::Internal, false);
        $borangTi = $this->createDoc($ti->id, $kaprodiTi->id, VisibilityLevel::Internal, true);
        $borangAgt = $this->createDoc($agt->id, $kaprodiAgt->id, VisibilityLevel::Internal, true);
        $publicTi = $this->createDoc($ti->id, $kaprodiTi->id, VisibilityLevel::Public, false);

        // Dekan FESH melihat dokumen FESH, MNJ, dan MM (bawahannya), PLUS dokumen Publik lintas unit, TIDAK melihat Internal/Borang FIT/TI/AGT
        $visibleToDekanFesh = Document::query()->accessibleFor($dekanFesh)->pluck('id')->all();
        $this->assertContains($docFesh->id, $visibleToDekanFesh);
        $this->assertContains($borangMnj->id, $visibleToDekanFesh);
        $this->assertContains($borangMm->id, $visibleToDekanFesh);
        $this->assertContains($publicTi->id, $visibleToDekanFesh);
        $this->assertNotContains($docFit->id, $visibleToDekanFesh);
        $this->assertNotContains($borangTi->id, $visibleToDekanFesh);
        $this->assertNotContains($borangAgt->id, $visibleToDekanFesh);

        // Policy view langsung juga menolak akses lintas fakultas walaupun is_borang = true
        $this->assertTrue($dekanFesh->can('view', $borangMnj));
        $this->assertTrue($dekanFesh->can('view', $borangMm));
        $this->assertFalse($dekanFesh->can('view', $borangTi));
        $this->assertFalse($dekanFesh->can('view', $borangAgt));

        // Kaprodi TI di FIT hanya boleh melihat unit TI (tidak bisa melihat prodi lain maupun fakultas lain)
        $visibleToKaprodiTi = Document::query()->accessibleFor($kaprodiTi)->pluck('id')->all();
        $this->assertContains($borangTi->id, $visibleToKaprodiTi);
        $this->assertNotContains($borangMnj->id, $visibleToKaprodiTi);
        $this->assertNotContains($borangMm->id, $visibleToKaprodiTi);
        $this->assertNotContains($docFit->id, $visibleToKaprodiTi);
        $this->assertFalse($kaprodiTi->can('view', $borangMnj));
    }

    public function test_accreditation_data_bank_scopes_dean_to_child_prodis_and_blocks_cross_faculty_assessor_config(): void
    {
        $dekanFesh = User::where('email', 'dheo_rimbano@univbinainsan.ac.id')->firstOrFail();
        $kaprodiMnj = User::where('email', 'irmaidayati@univbinainsan.ac.id')->firstOrFail();
        $kaprodiTi = User::where('email', 'budisantoso@univbinainsan.ac.id')->firstOrFail();

        $mnj = Unit::where('code', 'MNJ')->firstOrFail();
        $mm = Unit::where('code', 'MM')->firstOrFail();
        $ti = Unit::where('code', 'TI')->firstOrFail();

        $borangMnj = $this->createDoc($mnj->id, $kaprodiMnj->id, VisibilityLevel::Internal, true);
        $borangMm = $this->createDoc($mm->id, $dekanFesh->id, VisibilityLevel::Internal, true);
        $borangTi = $this->createDoc($ti->id, $kaprodiTi->id, VisibilityLevel::Internal, true);

        $this->actingAs($dekanFesh);

        // Dekan FESH melihat dokumen borang prodi bawah FESH (MNJ, MM), tetapi TIDAK melihat TI (FIT)
        Livewire::test(AccreditationDataBank::class)
            ->assertCanSeeTableRecords([$borangMnj, $borangMm])
            ->assertCanNotSeeTableRecords([$borangTi])
            ->callAction('configureAssessorAccess', [
                'unit_id' => $ti->id,
                'is_assessor_access_active' => true,
                'assessor_passcode' => 'HACK123',
            ]);

        // Upaya mengatur akses asesor unit fakultas lain harus ditolak di sisi server
        $ti->refresh();
        $this->assertFalse((bool) $ti->is_assessor_access_active);
        $this->assertNull($ti->assessor_passcode);

        // Dekan FESH BOLEH membuka akses asesor prodi bawahnya (MNJ)
        Livewire::test(AccreditationDataBank::class)
            ->callAction('configureAssessorAccess', [
                'unit_id' => $mnj->id,
                'is_assessor_access_active' => true,
                'assessor_passcode' => 'MNJ2026',
            ]);

        $mnj->refresh();
        $this->assertTrue((bool) $mnj->is_assessor_access_active);
        $this->assertSame('MNJ2026', $mnj->assessor_passcode);
    }

    public function test_confidential_documents_and_outgoing_letters_and_draft_posts_are_strictly_isolated(): void
    {
        $rektor = User::where('email', 'dr.sardiyo@univbinainsan.ac.id')->firstOrFail();
        $ketuaSpmi = User::where('email', 'muhammadirvai@univbinainsan.ac.id')->firstOrFail();
        $stafBaak = User::where('email', 'rahmaddani@univbinainsan.ac.id')->firstOrFail();
        $stafSdm = User::where('email', 'herlinawidiastuti@univbinainsan.ac.id')->firstOrFail();
        $dekanFit = User::where('email', 'rudi.kurniawan@univbinainsan.ac.id')->firstOrFail();
        $kaprodiTi = User::where('email', 'budisantoso@univbinainsan.ac.id')->firstOrFail();

        $baak = Unit::where('code', 'BAAK')->firstOrFail();
        $sdm = Unit::where('code', 'SDM-KEU')->firstOrFail();
        $ti = Unit::where('code', 'TI')->firstOrFail();

        // Dokumen Rahasia milik BAAK hanya boleh dilihat uploader, Rektor, Warek, Super Admin (Ketua SPMI tidak punya view_confidential)
        $confidentialBaak = $this->createDoc($baak->id, $stafBaak->id, VisibilityLevel::Confidential, false);
        $this->assertTrue($stafBaak->can('view', $confidentialBaak));
        $this->assertTrue($rektor->can('view', $confidentialBaak));
        $this->assertFalse($ketuaSpmi->can('view', $confidentialBaak));
        $this->assertFalse($stafSdm->can('view', $confidentialBaak));
        $this->assertFalse($dekanFit->can('view', $confidentialBaak));

        // Surat Keluar & Draft Berita antar unit/fakultas terisolasi
        $letterSdm = OutgoingLetter::create([
            'unit_id' => $sdm->id,
            'created_by' => $stafSdm->id,
            'letter_date' => now()->toDateString(),
            'destination_organization' => 'BPJS Ketenagakerjaan',
            'subject' => 'Surat Internal SDM',
            'status' => OutgoingLetterStatus::PendingHumas,
        ]);

        $letterTi = OutgoingLetter::create([
            'unit_id' => $ti->id,
            'created_by' => $kaprodiTi->id,
            'letter_date' => now()->toDateString(),
            'destination_organization' => 'Mitra Industri IT',
            'subject' => 'Surat Kerja Sama Prodi TI',
            'status' => OutgoingLetterStatus::PendingHumas,
        ]);

        $this->assertTrue($stafSdm->can('view', $letterSdm));
        $this->assertFalse($stafBaak->can('view', $letterSdm));
        $this->assertFalse($dekanFit->can('view', $letterSdm));
        $this->assertTrue($dekanFit->can('view', $letterTi));

        $postCategory = PostCategory::create([
            'name' => 'Berita Kegiatan',
            'slug' => 'berita-kegiatan',
            'is_active' => true,
        ]);

        $draftPostTi = Post::create([
            'title' => 'Draft Berita Rahasia Prodi TI',
            'slug' => 'draft-berita-prodi-ti-'.uniqid(),
            'content' => 'Konten internal',
            'post_category_id' => $postCategory->id,
            'status' => PostStatus::Draft,
            'unit_id' => $ti->id,
            'author_id' => $kaprodiTi->id,
        ]);

        $this->assertTrue($kaprodiTi->can('view', $draftPostTi));
        $this->assertTrue($dekanFit->can('view', $draftPostTi));
        $this->assertFalse($stafBaak->can('view', $draftPostTi));
        $this->assertFalse($stafSdm->can('view', $draftPostTi));
    }

    private function createDoc(int $unitId, int $uploaderId, VisibilityLevel $visibility, bool $isBorang): Document
    {
        return Document::create([
            'document_number' => 'DOC-'.uniqid(),
            'title' => 'Naskah Uji '.uniqid(),
            'unit_id' => $unitId,
            'document_category_id' => $this->category->id,
            'visibility' => $visibility,
            'status' => DocumentStatus::Approved,
            'uploaded_by' => $uploaderId,
            'is_borang' => $isBorang,
            'issued_date' => now()->toDateString(),
        ]);
    }
}
