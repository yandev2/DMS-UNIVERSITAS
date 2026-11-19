<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\StampingPosition;
use App\Enums\VisibilityLevel;
use App\Models\CategoryApprovalFlow;
use App\Models\DocumentCategory;
use App\Models\Unit;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DocumentCategorySeeder extends Seeder
{
    /**
     * Mengisi 11 kategori dokumen umum Universitas Bina Insan beserta alur persetujuan berjenjang
     * dan batasan unit kerja (seluruh kategori surat wajib melalui Humas terlebih dahulu).
     */
    public function run(): void
    {
        $academicUnitCodes = [
            'FESH', 'FIT', 'FITH',
            'MM', 'MNJ', 'AKT', 'HKM',
            'TI', 'RSK', 'SI',
            'AGT', 'IKN', 'LAB-FITH',
        ];

        $prodiUnitCodes = [
            'MM', 'MNJ', 'AKT', 'HKM',
            'TI', 'RSK', 'SI',
            'AGT', 'IKN',
        ];

        $nonAcademicUnitCodes = [
            'REKTORAT',
            'SPMI', 'HUMAS', 'BAAK', 'KMHS', 'SDM-KEU',
            'LPPM', 'PUSKOM', 'PERPUS',
            'LSP', 'YPDTP', 'PELET',
        ];

        $unitIdMap = Unit::query()->pluck('id', 'code')->all();

        $categories = [
            [
                'slug' => 'sk-rektor',
                'name' => 'Surat Keputusan (SK) Rektor',
                'code' => 'SK',
                'description' => 'Surat Keputusan resmi tingkat universitas yang disahkan oleh Rektor.',
                'number_format' => '{seq}/UNIVBI.{unit}/{cat}/{roman_month}/{year}',
                'requires_approval' => true,
                'requires_wet_signature' => true,
                'allow_manual_number' => true,
                'has_expiry' => false,
                'default_visibility' => VisibilityLevel::Internal,
                'stamp_position' => StampingPosition::Footer,
                'unit_codes' => [],
                'flows' => [
                    ['step_order' => 1, 'approver_role' => 'humas', 'step_label' => 'Verifikasi Redaksi & Tata Naskah Humas', 'is_mandatory' => true],
                    ['step_order' => 2, 'approver_role' => 'warek', 'step_label' => 'Verifikasi Wakil Rektor', 'is_mandatory' => true],
                    ['step_order' => 3, 'approver_role' => 'rektor', 'step_label' => 'Pengesahan Final Rektor', 'is_mandatory' => true],
                ],
            ],
            [
                'slug' => 'sk-dekan',
                'name' => 'Surat Keputusan (SK) Dekan Fakultas',
                'code' => 'SK-F',
                'description' => 'Surat Keputusan tingkat fakultas dan program studi yang disahkan oleh Dekan.',
                'number_format' => '{seq}/UNIVBI.{unit}/{cat}/{roman_month}/{year}',
                'requires_approval' => true,
                'requires_wet_signature' => true,
                'allow_manual_number' => true,
                'has_expiry' => false,
                'default_visibility' => VisibilityLevel::Internal,
                'stamp_position' => StampingPosition::Footer,
                'unit_codes' => $academicUnitCodes,
                'flows' => [
                    ['step_order' => 1, 'approver_role' => 'humas', 'step_label' => 'Verifikasi Redaksi & Tata Naskah Humas', 'is_mandatory' => true],
                    ['step_order' => 2, 'approver_role' => 'kaprodi', 'step_label' => 'Verifikasi Ketua Program Studi', 'is_mandatory' => false],
                    ['step_order' => 3, 'approver_role' => 'dekan', 'step_label' => 'Pengesahan Final Dekan Fakultas', 'is_mandatory' => true],
                ],
            ],
            [
                'slug' => 'se-rektorat',
                'name' => 'Surat Edaran (SE) Resmi Universitas',
                'code' => 'SE',
                'description' => 'Surat Edaran resmi universitas yang dipublikasikan untuk sivitas akademika dan publik.',
                'number_format' => '{seq}/UNIVBI.{unit}/{cat}/{roman_month}/{year}',
                'requires_approval' => true,
                'requires_wet_signature' => true,
                'allow_manual_number' => true,
                'has_expiry' => false,
                'default_visibility' => VisibilityLevel::Public,
                'stamp_position' => StampingPosition::Footer,
                'unit_codes' => [],
                'flows' => [
                    ['step_order' => 1, 'approver_role' => 'humas', 'step_label' => 'Verifikasi Redaksi & Tata Naskah Humas', 'is_mandatory' => true],
                    ['step_order' => 2, 'approver_role' => 'warek', 'step_label' => 'Pemeriksaan Wakil Rektor', 'is_mandatory' => true],
                    ['step_order' => 3, 'approver_role' => 'rektor', 'step_label' => 'Pengesahan Final Rektor', 'is_mandatory' => true],
                ],
            ],
            [
                'slug' => 'st-fakultas',
                'name' => 'Surat Tugas (ST) Fakultas & Program Studi',
                'code' => 'ST',
                'description' => 'Surat Tugas penugasan dosen, peneliti, atau tenaga kependidikan di lingkungan fakultas dan prodi.',
                'number_format' => '{seq}/UNIVBI.{unit}/{cat}/{roman_month}/{year}',
                'requires_approval' => true,
                'requires_wet_signature' => false,
                'allow_manual_number' => true,
                'has_expiry' => false,
                'default_visibility' => VisibilityLevel::Internal,
                'stamp_position' => StampingPosition::Footer,
                'unit_codes' => $academicUnitCodes,
                'flows' => [
                    ['step_order' => 1, 'approver_role' => 'humas', 'step_label' => 'Verifikasi Redaksi & Tata Naskah Humas', 'is_mandatory' => true],
                    ['step_order' => 2, 'approver_role' => 'kaprodi', 'step_label' => 'Persetujuan Awal Kaprodi', 'is_mandatory' => false],
                    ['step_order' => 3, 'approver_role' => 'dekan', 'step_label' => 'Pengesahan Dekan Fakultas', 'is_mandatory' => true],
                ],
            ],
            [
                'slug' => 'st-unit',
                'name' => 'Surat Tugas (ST) Pimpinan / Unit Non-Akademik',
                'code' => 'ST-U',
                'description' => 'Surat Tugas resmi untuk pegawai pada Biro, UPT, Lembaga, dan unit non-akademik.',
                'number_format' => '{seq}/UNIVBI.{unit}/{cat}/{roman_month}/{year}',
                'requires_approval' => true,
                'requires_wet_signature' => false,
                'allow_manual_number' => true,
                'has_expiry' => false,
                'default_visibility' => VisibilityLevel::Internal,
                'stamp_position' => StampingPosition::Footer,
                'unit_codes' => $nonAcademicUnitCodes,
                'flows' => [
                    ['step_order' => 1, 'approver_role' => 'humas', 'step_label' => 'Verifikasi Redaksi & Tata Naskah Humas', 'is_mandatory' => true],
                    ['step_order' => 2, 'approver_role' => 'kepala_unit', 'step_label' => 'Persetujuan Kepala Unit Kerja', 'is_mandatory' => false],
                    ['step_order' => 3, 'approver_role' => 'warek', 'step_label' => 'Pengesahan Wakil Rektor', 'is_mandatory' => true],
                ],
            ],
            [
                'slug' => 'mou',
                'name' => 'Nota Kesepahaman (MoU) / Perjanjian Kerja Sama (PKS)',
                'code' => 'MOU',
                'description' => 'Naskah kerja sama kelembagaan dengan mitra eksternal beserta pengingat masa berlaku.',
                'number_format' => '{seq}/UNIVBI.{unit}/{cat}/{roman_month}/{year}',
                'requires_approval' => true,
                'requires_wet_signature' => true,
                'allow_manual_number' => true,
                'has_expiry' => true,
                'default_visibility' => VisibilityLevel::Internal,
                'stamp_position' => StampingPosition::Footer,
                'unit_codes' => [],
                'flows' => [
                    ['step_order' => 1, 'approver_role' => 'humas', 'step_label' => 'Telaah Redaksi & Kemitraan Humas', 'is_mandatory' => true],
                    ['step_order' => 2, 'approver_role' => 'warek', 'step_label' => 'Verifikasi Wakil Rektor', 'is_mandatory' => true],
                    ['step_order' => 3, 'approver_role' => 'rektor', 'step_label' => 'Pengesahan Final Rektor', 'is_mandatory' => true],
                ],
            ],
            [
                'slug' => 'nota-dinas',
                'name' => 'Nota Dinas / Surat Usulan Antar Unit',
                'code' => 'ND',
                'description' => 'Surat komunikasi dan usulan kedinasan resmi antar unit kerja internal kampus.',
                'number_format' => '{seq}/UNIVBI.{unit}/{cat}/{roman_month}/{year}',
                'requires_approval' => true,
                'requires_wet_signature' => false,
                'allow_manual_number' => true,
                'has_expiry' => false,
                'default_visibility' => VisibilityLevel::Internal,
                'stamp_position' => StampingPosition::Footer,
                'unit_codes' => [],
                'flows' => [
                    ['step_order' => 1, 'approver_role' => 'humas', 'step_label' => 'Verifikasi Redaksi & Tata Naskah Humas', 'is_mandatory' => true],
                    ['step_order' => 2, 'approver_role' => 'warek', 'step_label' => 'Disposisi / Persetujuan Wakil Rektor', 'is_mandatory' => true],
                ],
            ],
            [
                'slug' => 'dokumen-spmi',
                'name' => 'Dokumen Mutu, Kebijakan, Standar & SOP (SPMI)',
                'code' => 'SPMI',
                'description' => 'Dokumen sistem penjaminan mutu internal (kebijakan, manual, standar, SOP, formulir mutu).',
                'number_format' => '{seq}/UNIVBI.{unit}/{cat}/{roman_month}/{year}',
                'requires_approval' => true,
                'requires_wet_signature' => false,
                'allow_manual_number' => true,
                'has_expiry' => true,
                'default_visibility' => VisibilityLevel::Internal,
                'stamp_position' => StampingPosition::Footer,
                'unit_codes' => [],
                'flows' => [
                    ['step_order' => 1, 'approver_role' => 'ketua_spmi', 'step_label' => 'Verifikasi & Validasi Ketua SPMI', 'is_mandatory' => true],
                    ['step_order' => 2, 'approver_role' => 'warek', 'step_label' => 'Pemeriksaan Wakil Rektor', 'is_mandatory' => true],
                    ['step_order' => 3, 'approver_role' => 'rektor', 'step_label' => 'Penetapan Rektor', 'is_mandatory' => true],
                ],
            ],
            [
                'slug' => 'rps',
                'name' => 'Rencana Pembelajaran Semester (RPS) & Perangkat Ajar',
                'code' => 'RPS',
                'description' => 'Perangkat perkuliahan program studi yang divalidasi oleh Ketua Program Studi.',
                'number_format' => '{seq}/UNIVBI.{unit}/{cat}/{roman_month}/{year}',
                'requires_approval' => true,
                'requires_wet_signature' => false,
                'allow_manual_number' => true,
                'has_expiry' => false,
                'default_visibility' => VisibilityLevel::Internal,
                'stamp_position' => StampingPosition::Footer,
                'unit_codes' => $prodiUnitCodes,
                'flows' => [
                    ['step_order' => 1, 'approver_role' => 'kaprodi', 'step_label' => 'Verifikasi & Pengesahan Kaprodi', 'is_mandatory' => true],
                ],
            ],
            [
                'slug' => 'berita-acara',
                'name' => 'Berita Acara (Kegiatan, Rapat, Yudisium & Asesmen)',
                'code' => 'BA',
                'description' => 'Berita acara rapat, yudisium, asesmen, atau kegiatan unit kerja tanpa alur pengesahan berjenjang.',
                'number_format' => '{seq}/UNIVBI.{unit}/{cat}/{roman_month}/{year}',
                'requires_approval' => false,
                'requires_wet_signature' => false,
                'allow_manual_number' => true,
                'has_expiry' => false,
                'default_visibility' => VisibilityLevel::Internal,
                'stamp_position' => StampingPosition::Footer,
                'unit_codes' => [],
                'flows' => [],
            ],
            [
                'slug' => 'arsip-umum',
                'name' => 'Arsip Digital / Sertifikat / Laporan Periodik & Referensi',
                'code' => 'ARSIP',
                'description' => 'Digitalisasi arsip fisik terdahulu, sertifikat akreditasi, laporan unit, dan dokumen referensi langsung.',
                'number_format' => '{seq}/UNIVBI.{unit}/{cat}/{roman_month}/{year}',
                'requires_approval' => false,
                'requires_wet_signature' => false,
                'allow_manual_number' => true,
                'has_expiry' => true,
                'default_visibility' => VisibilityLevel::Internal,
                'stamp_position' => StampingPosition::Footer,
                'unit_codes' => [],
                'flows' => [],
            ],
        ];

        DB::transaction(function () use ($categories, $unitIdMap): void {
            foreach ($categories as $item) {
                $flows = $item['flows'];
                $unitCodes = $item['unit_codes'];
                unset($item['flows'], $item['unit_codes']);

                $category = DocumentCategory::updateOrCreate(
                    ['slug' => $item['slug']],
                    array_merge($item, ['is_active' => true])
                );

                // Bersihkan seluruh tahapan lama sebelum menyinkronkan step_order 1..N secara bersih
                CategoryApprovalFlow::query()
                    ->where('document_category_id', $category->id)
                    ->delete();

                foreach ($flows as $flow) {
                    CategoryApprovalFlow::create([
                        'document_category_id' => $category->id,
                        'step_order' => $flow['step_order'],
                        'approver_role' => $flow['approver_role'],
                        'step_label' => $flow['step_label'],
                        'is_mandatory' => $flow['is_mandatory'],
                    ]);
                }

                $allowedUnitIds = [];
                foreach ($unitCodes as $code) {
                    if (isset($unitIdMap[$code])) {
                        $allowedUnitIds[] = (int) $unitIdMap[$code];
                    }
                }

                $category->allowedUnits()->sync($allowedUnitIds);
            }
        });
    }
}
