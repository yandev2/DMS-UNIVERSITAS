<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Enums\DegreeLevel;
use App\Enums\DocumentStatus;
use App\Enums\UnitType;
use App\Models\AcademicYear;
use App\Models\Document;
use App\Models\DocumentCategory;
use App\Models\Unit;
use App\Models\User;
use App\Services\DocumentNamingService;
use App\Services\DocumentNumberingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DocumentNumberingServiceTest extends TestCase
{
    use RefreshDatabase;

    private DocumentNumberingService $service;

    private Unit $unitTi;

    private Unit $unitSi;

    private DocumentCategory $categorySk;

    private DocumentCategory $categorySt;

    private AcademicYear $academicYear;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new DocumentNumberingService(new DocumentNamingService);

        $this->unitTi = Unit::create([
            'code' => 'TI',
            'name' => 'Program Studi S1 Informatika',
            'slug' => 's1-informatika',
            'type' => UnitType::Prodi,
            'degree_level' => DegreeLevel::S1,
            'is_active' => true,
        ]);

        $this->unitSi = Unit::create([
            'code' => 'SI',
            'name' => 'Program Studi S1 Sistem Informasi',
            'slug' => 's1-sistem-informasi',
            'type' => UnitType::Prodi,
            'degree_level' => DegreeLevel::S1,
            'is_active' => true,
        ]);

        $this->categorySk = DocumentCategory::create([
            'code' => 'SK',
            'name' => 'Surat Keputusan',
            'slug' => 'surat-keputusan',
            'number_format' => '{seq}/UNIVBI.{unit}/{cat}/{roman_month}/{year}',
            'is_active' => true,
        ]);

        $this->categorySt = DocumentCategory::create([
            'code' => 'ST',
            'name' => 'Surat Tugas',
            'slug' => 'surat-tugas',
            'number_format' => '{seq}/UNIVBI.{unit}/{cat}/{month}/{year}',
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

        $this->user = User::factory()->create([
            'unit_id' => $this->unitTi->id,
            'is_active' => true,
        ]);
    }

    public function test_to_roman_converts_months_correctly(): void
    {
        $this->assertSame('I', $this->service->toRoman(1));
        $this->assertSame('IV', $this->service->toRoman(4));
        $this->assertSame('IX', $this->service->toRoman(9));
        $this->assertSame('XI', $this->service->toRoman(11));
        $this->assertSame('XII', $this->service->toRoman(12));
    }

    public function test_format_number_replaces_all_tokens_accurately(): void
    {
        $formatted = $this->service->formatNumber(
            pattern: '{seq}/UNIVBI.{unit}/{cat}/{roman_month}/{year}',
            sequence: 1,
            unitCode: 'TI',
            categoryCode: 'SK',
            month: 11,
            year: 2026
        );

        $this->assertSame('001/UNIVBI.TI/SK/XI/2026', $formatted);

        $formatted4Digit = $this->service->formatNumber(
            pattern: 'NO.{seq:4}/SURAT-{unit}/{month}/{year}',
            sequence: 45,
            unitCode: 'BAUK',
            categoryCode: 'BAST',
            month: 5,
            year: 2026
        );

        $this->assertSame('NO.0045/SURAT-BAUK/05/2026', $formatted4Digit);
    }

    public function test_generate_number_creates_sequential_numbers_atomically(): void
    {
        $doc1 = Document::create([
            'title' => 'SK Dosen Pembimbing Skripsi 1',
            'unit_id' => $this->unitTi->id,
            'document_category_id' => $this->categorySk->id,
            'academic_year_id' => $this->academicYear->id,
            'uploaded_by' => $this->user->id,
            'issued_date' => '2026-11-10',
            'status' => DocumentStatus::Pending,
        ]);

        $num1 = $this->service->generateNumber($doc1);
        $this->assertSame('001/UNIVBI.TI/SK/XI/2026', $num1);
        $doc1->refresh();
        $this->assertSame(1, $doc1->sequence_number);
        $this->assertSame(2026, $doc1->sequence_year);
        $this->assertSame('001/UNIVBI.TI/SK/XI/2026', $doc1->document_number);

        $doc2 = Document::create([
            'title' => 'SK Dosen Pembimbing Skripsi 2',
            'unit_id' => $this->unitTi->id,
            'document_category_id' => $this->categorySk->id,
            'academic_year_id' => $this->academicYear->id,
            'uploaded_by' => $this->user->id,
            'issued_date' => '2026-11-10',
            'status' => DocumentStatus::Pending,
        ]);

        $num2 = $this->service->generateNumber($doc2);
        $this->assertSame('002/UNIVBI.TI/SK/XI/2026', $num2);
        $doc2->refresh();
        $this->assertSame(2, $doc2->sequence_number);
    }

    public function test_sequence_is_scoped_per_unit_and_per_category(): void
    {
        // TI - SK
        $docTiSk = Document::create([
            'title' => 'SK TI 1',
            'unit_id' => $this->unitTi->id,
            'document_category_id' => $this->categorySk->id,
            'uploaded_by' => $this->user->id,
            'issued_date' => '2026-11-10',
        ]);
        $this->assertSame('001/UNIVBI.TI/SK/XI/2026', $this->service->generateNumber($docTiSk));

        // SI - SK (Unit Berbeda -> Sequence mulai dari 1)
        $docSiSk = Document::create([
            'title' => 'SK SI 1',
            'unit_id' => $this->unitSi->id,
            'document_category_id' => $this->categorySk->id,
            'uploaded_by' => $this->user->id,
            'issued_date' => '2026-11-10',
        ]);
        $this->assertSame('001/UNIVBI.SI/SK/XI/2026', $this->service->generateNumber($docSiSk));

        // TI - ST (Kategori Berbeda -> Sequence mulai dari 1)
        $docTiSt = Document::create([
            'title' => 'ST TI 1',
            'unit_id' => $this->unitTi->id,
            'document_category_id' => $this->categorySt->id,
            'uploaded_by' => $this->user->id,
            'issued_date' => '2026-11-10',
        ]);
        $this->assertSame('001/UNIVBI.TI/ST/11/2026', $this->service->generateNumber($docTiSt));
    }

    public function test_generate_number_respects_manual_number(): void
    {
        $manualDoc = Document::create([
            'title' => 'Arsip Fisik Lama 2024',
            'unit_id' => $this->unitTi->id,
            'document_category_id' => $this->categorySk->id,
            'uploaded_by' => $this->user->id,
            'issued_date' => '2024-05-12',
            'is_manual_number' => true,
            'document_number' => 'SK-MANUAL/2024/099',
        ]);

        $num = $this->service->generateNumber($manualDoc);
        $this->assertSame('SK-MANUAL/2024/099', $num);
    }

    public function test_preview_next_number_returns_formatted_string_without_mutating_database(): void
    {
        $preview = $this->service->previewNextNumber($this->categorySk, $this->unitTi, 2026, 11);
        $this->assertSame('001/UNIVBI.TI/SK/XI/2026', $preview);

        $this->assertSame(0, Document::count());
    }

    public function test_generate_number_works_when_no_previous_documents_exist(): void
    {
        $doc = Document::create([
            'title' => 'Dokumen Pertama',
            'unit_id' => $this->unitTi->id,
            'document_category_id' => $this->categorySk->id,
            'academic_year_id' => $this->academicYear->id,
            'uploaded_by' => $this->user->id,
            'issued_date' => '2026-01-01',
            'status' => DocumentStatus::Pending,
        ]);

        $number = $this->service->generateNumber($doc);
        $this->assertSame('001/UNIVBI.TI/SK/I/2026', $number);
        $doc->refresh();
        $this->assertSame(1, $doc->sequence_number);
    }
}
