<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\AccreditationInstrument;
use App\Enums\DegreeLevel;
use App\Enums\UnitType;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\PermissionRegistrar;

class CampusStructureSeeder extends Seeder
{
    /**
     * Seeder resmi struktur organisasi Universitas Bina Insan:
     * 10 role fungsional, 25 unit hierarkis, dan 57 akun pegawai aktif
     * (dengan 1 akun super_admin atas nama Rian Dinata, S.Kom).
     */
    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $allPermissions = self::ensurePermissionsExist();
        $this->seedRoles($allPermissions);

        $units = $this->seedUnits();
        $usersByEmail = $this->seedUsers($units);
        $this->assignUnitHeads($units, $usersByEmail);

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }

    /**
     * @param  list<string>  $allPermissions
     */
    private function seedRoles(array $allPermissions): void
    {
        $widgetPermissions = [
            'view:dashboard_welcome_banner_widget',
            'view:dashboard_quick_actions_widget',
            'view:document_overview_widget',
            'view:document_lifecycle_stats_widget',
            'view:annual_published_document_chart_widget',
            'view:pending_approvals_widget',
            'view:pending_letters_alert_widget',
            'view:post_period_overview_widget',
        ];

        $masterRead = [
            'view_any:accreditation_criterion',
            'view:accreditation_criterion',
            'view_any:document_category',
            'view:document_category',
            'view_any:academic_year',
            'view:academic_year',
            'view_any:unit',
            'view:unit',
        ];

        $documentCrudBase = [
            'view_any:document',
            'view:document',
            'create:document',
            'update:document',
        ];

        $letterReadOnly = [
            'view_any:incoming_letter',
            'view:incoming_letter',
            'view_any:outgoing_letter',
            'view:outgoing_letter',
        ];

        $outgoingCrud = [
            'view_any:outgoing_letter',
            'view:outgoing_letter',
            'create:outgoing_letter',
            'update:outgoing_letter',
            'delete:outgoing_letter',
        ];

        $postAuthor = [
            'view_any:post',
            'view:post',
            'create:post',
            'update:post',
            'view_any:post_category',
            'view:post_category',
        ];

        $auditReadOnly = [
            'view_any:activity_log',
            'view:activity_log',
        ];

        $unitLeaderPermissions = array_merge(
            $widgetPermissions,
            $masterRead,
            $documentCrudBase,
            ['delete:document'],
            ['view_any:incoming_letter', 'view:incoming_letter'],
            $outgoingCrud,
            $postAuthor,
            [
                'document.download_private',
                'document.approve',
                'document.manual_numbering',
                'borang.export_excel',
                'borang.download_bulk_zip',
                'borang.configure_assessor',
                'reminder.manage_recipients',
                'post.download_zip',
            ],
        );

        $rektorWarekPermissions = array_merge(
            $widgetPermissions,
            $masterRead,
            $documentCrudBase,
            $letterReadOnly,
            ['view_any:post', 'view:post'],
            $auditReadOnly,
            [
                'document.view_confidential',
                'document.download_private',
                'document.approve',
                'document.manual_numbering',
                'document.bypass_unit_scope',
                'borang.export_excel',
                'borang.download_bulk_zip',
                'borang.configure_assessor',
                'reminder.manage_recipients',
                'post.download_zip',
                'cms.manage_landing_page',
            ],
        );

        $spmiBasePermissions = array_merge(
            $widgetPermissions,
            $masterRead,
            $documentCrudBase,
            ['view_any:incoming_letter', 'view:incoming_letter'],
            ['view_any:outgoing_letter', 'view:outgoing_letter', 'create:outgoing_letter', 'update:outgoing_letter'],
            ['create:accreditation_criterion', 'update:accreditation_criterion'],
            ['view_any:post', 'view:post', 'create:post', 'update:post'],
            [
                'document.download_private',
                'document.manual_numbering',
                'document.bypass_unit_scope',
                'borang.export_excel',
                'borang.download_bulk_zip',
                'borang.configure_assessor',
                'post.download_zip',
            ],
        );

        $rolePermissions = [
            'super_admin' => $allPermissions,
            'rektor' => $rektorWarekPermissions,
            'warek' => $rektorWarekPermissions,
            'ketua_spmi' => array_merge($spmiBasePermissions, $auditReadOnly, [
                'document.approve',
                'reminder.manage_recipients',
            ]),
            'staf_spmi' => $spmiBasePermissions,
            'humas' => array_merge(
                $widgetPermissions,
                $masterRead,
                $documentCrudBase,
                [
                    'view_any:incoming_letter',
                    'view:incoming_letter',
                    'create:incoming_letter',
                    'update:incoming_letter',
                    'delete:incoming_letter',
                    'delete_any:incoming_letter',
                    'view_any:outgoing_letter',
                    'view:outgoing_letter',
                    'create:outgoing_letter',
                    'update:outgoing_letter',
                    'delete:outgoing_letter',
                    'delete_any:outgoing_letter',
                    'view_any:post',
                    'view:post',
                    'create:post',
                    'update:post',
                    'delete:post',
                    'view_any:post_category',
                    'view:post_category',
                    'create:post_category',
                    'update:post_category',
                    'letter.manage_humas',
                    'post.moderate_humas',
                    'post.download_zip',
                    'cms.manage_landing_page',
                    'document.download_private',
                    'document.manual_numbering',
                    'reminder.manage_recipients',
                ],
            ),
            'dekan' => $unitLeaderPermissions,
            'kaprodi' => $unitLeaderPermissions,
            'kepala_unit' => $unitLeaderPermissions,
            'staf_unit' => array_merge(
                $widgetPermissions,
                $masterRead,
                $documentCrudBase,
                ['view_any:incoming_letter', 'view:incoming_letter'],
                $outgoingCrud,
                $postAuthor,
                [
                    'document.download_private',
                    'document.manual_numbering',
                    'borang.export_excel',
                    'borang.download_bulk_zip',
                    'reminder.manage_recipients',
                    'post.download_zip',
                ],
            ),
        ];

        foreach ($rolePermissions as $roleName => $permissions) {
            $role = Role::findOrCreate($roleName, 'web');
            $role->syncPermissions(array_values(array_unique($permissions)));
        }
    }

    /**
     * @return array<string, Unit>
     */
    private function seedUnits(): array
    {
        $units = [];

        $units['REKTORAT'] = Unit::updateOrCreate(
            ['code' => 'REKTORAT'],
            [
                'name' => 'Rektorat Universitas Bina Insan',
                'type' => UnitType::Rektorat,
                'degree_level' => null,
                'parent_id' => null,
                'accreditation_body' => 'BAN-PT',
                'accreditation_instrument' => AccreditationInstrument::BanPt->value,
                'is_active' => true,
            ]
        );

        $underRektorat = [
            ['code' => 'FESH', 'name' => 'Fakultas Ilmu Ekonomi dan Sosial Humaniora', 'type' => UnitType::Fakultas, 'instrument' => AccreditationInstrument::Lamemba->value],
            ['code' => 'FIT', 'name' => 'Fakultas Ilmu Teknik', 'type' => UnitType::Fakultas, 'instrument' => AccreditationInstrument::LamInfokom->value],
            ['code' => 'FITH', 'name' => 'Fakultas Ilmu Tanaman dan Hewani', 'type' => UnitType::Fakultas, 'instrument' => AccreditationInstrument::BanPt->value],
            ['code' => 'SPMI', 'name' => 'Satuan Penjaminan Mutu Internal (SPMI)', 'type' => UnitType::Lembaga, 'instrument' => AccreditationInstrument::BanPt->value],
            ['code' => 'HUMAS', 'name' => 'Bagian Tata Laksana, Humas, Marketing, Bisnis & IPD', 'type' => UnitType::Biro, 'instrument' => null],
            ['code' => 'BAAK', 'name' => 'Bagian Administrasi Akademik', 'type' => UnitType::Biro, 'instrument' => null],
            ['code' => 'KMHS', 'name' => 'Bagian Kemahasiswaan dan Tracer Study', 'type' => UnitType::Biro, 'instrument' => null],
            ['code' => 'SDM-KEU', 'name' => 'Bagian SDM dan Keuangan', 'type' => UnitType::Biro, 'instrument' => null],
            ['code' => 'LPPM', 'name' => 'UPT LPPM dan Kerja Sama', 'type' => UnitType::Upt, 'instrument' => null],
            ['code' => 'PUSKOM', 'name' => 'UPT Laboratorium Terpadu dan Pusat Komputer', 'type' => UnitType::Upt, 'instrument' => null],
            ['code' => 'PERPUS', 'name' => 'UPT Perpustakaan', 'type' => UnitType::Upt, 'instrument' => null],
            ['code' => 'LSP', 'name' => 'Lembaga Sertifikasi Profesi (LSP) Bina Insan', 'type' => UnitType::Lembaga, 'instrument' => null],
            ['code' => 'YPDTP', 'name' => 'Unit Operasional & Sarpras Yayasan (YPDTP)', 'type' => UnitType::UnitPendukung, 'instrument' => null],
            ['code' => 'PELET', 'name' => 'Unit Produksi dan Operasional Pabrik Pelet UNIVBI', 'type' => UnitType::UnitPendukung, 'instrument' => null],
        ];

        foreach ($underRektorat as $item) {
            $units[$item['code']] = Unit::updateOrCreate(
                ['code' => $item['code']],
                [
                    'name' => $item['name'],
                    'type' => $item['type'],
                    'degree_level' => null,
                    'parent_id' => $units['REKTORAT']->id,
                    'accreditation_instrument' => $item['instrument'],
                    'is_active' => true,
                ]
            );
        }

        $childUnits = [
            // Bawah FESH (termasuk MM S2 sesuai konfirmasi)
            ['code' => 'MM', 'name' => 'Program Pascasarjana Magister Manajemen', 'type' => UnitType::Prodi, 'degree' => DegreeLevel::S2, 'parent' => 'FESH', 'body' => 'LAMEMBA', 'instrument' => AccreditationInstrument::Lamemba->value],
            ['code' => 'MNJ', 'name' => 'Program Studi S1 Manajemen', 'type' => UnitType::Prodi, 'degree' => DegreeLevel::S1, 'parent' => 'FESH', 'body' => 'LAMEMBA', 'instrument' => AccreditationInstrument::Lamemba->value],
            ['code' => 'AKT', 'name' => 'Program Studi S1 Akuntansi', 'type' => UnitType::Prodi, 'degree' => DegreeLevel::S1, 'parent' => 'FESH', 'body' => 'LAMEMBA', 'instrument' => AccreditationInstrument::Lamemba->value],
            ['code' => 'HKM', 'name' => 'Program Studi S1 Ilmu Hukum', 'type' => UnitType::Prodi, 'degree' => DegreeLevel::S1, 'parent' => 'FESH', 'body' => 'BAN-PT', 'instrument' => AccreditationInstrument::BanPt->value],

            // Bawah FIT
            ['code' => 'TI', 'name' => 'Program Studi S1 Informatika', 'type' => UnitType::Prodi, 'degree' => DegreeLevel::S1, 'parent' => 'FIT', 'body' => 'LAM Infokom', 'instrument' => AccreditationInstrument::LamInfokom->value],
            ['code' => 'RSK', 'name' => 'Program Studi S1 Rekayasa Sistem Komputer', 'type' => UnitType::Prodi, 'degree' => DegreeLevel::S1, 'parent' => 'FIT', 'body' => 'LAM Infokom', 'instrument' => AccreditationInstrument::LamInfokom->value],
            ['code' => 'SI', 'name' => 'Program Studi S1 Sistem Informasi', 'type' => UnitType::Prodi, 'degree' => DegreeLevel::S1, 'parent' => 'FIT', 'body' => 'LAM Infokom', 'instrument' => AccreditationInstrument::LamInfokom->value],

            // Bawah FITH
            ['code' => 'AGT', 'name' => 'Program Studi S1 Agroteknologi', 'type' => UnitType::Prodi, 'degree' => DegreeLevel::S1, 'parent' => 'FITH', 'body' => 'BAN-PT', 'instrument' => AccreditationInstrument::BanPt->value],
            ['code' => 'IKN', 'name' => 'Program Studi S1 Ilmu Perikanan', 'type' => UnitType::Prodi, 'degree' => DegreeLevel::S1, 'parent' => 'FITH', 'body' => 'BAN-PT', 'instrument' => AccreditationInstrument::BanPt->value],
            ['code' => 'LAB-FITH', 'name' => 'UPT Laboratorium Fakultas Ilmu Tanaman dan Hewani', 'type' => UnitType::Upt, 'degree' => null, 'parent' => 'FITH', 'body' => null, 'instrument' => null],
        ];

        foreach ($childUnits as $child) {
            $units[$child['code']] = Unit::updateOrCreate(
                ['code' => $child['code']],
                [
                    'name' => $child['name'],
                    'type' => $child['type'],
                    'degree_level' => $child['degree'],
                    'parent_id' => $units[$child['parent']]->id,
                    'accreditation_body' => $child['body'],
                    'accreditation_instrument' => $child['instrument'],
                    'is_active' => true,
                ]
            );
        }

        return $units;
    }

    /**
     * @param  array<string, Unit>  $units
     * @return array<string, User>
     */
    private function seedUsers(array $units): array
    {
        $employees = [
            ['name' => 'Dr. Sardiyo, M.M', 'position' => 'Rektor Universitas Bina Insan', 'email' => 'dr.sardiyo@univbinainsan.ac.id', 'unit' => 'REKTORAT', 'roles' => ['rektor']],
            ['name' => 'Dr. Muhamad Akbar, S.T., M.IT', 'position' => 'Wakil Rektor I', 'email' => 'akbartea@gmail.com', 'unit' => 'REKTORAT', 'roles' => ['warek']],
            ['name' => 'Wakhid Nur Mukhlis, M.Pd, M.M', 'position' => 'Wakil Rektor II', 'email' => 'wakhidnurmukhils@univbinainsan.ac.id', 'unit' => 'REKTORAT', 'roles' => ['warek']],
            ['name' => 'Tri Hasanah Bimastari Aviani, M.Kom', 'position' => 'Kepala Bagian Tata Laksana, Humas, Marketing, Bisnis, dan Informasi Pangkalan Data', 'email' => 'trihasanah@univbinainsan.ac.id', 'unit' => 'HUMAS', 'roles' => ['kepala_unit', 'humas']],
            ['name' => 'Wisdalia Maya Sari D, S.Kom., M.M', 'position' => 'Kepala Bagian Kemahasiswaan dan Tracer Study', 'email' => 'wisdalia_maya_sari@univbinainsan.ac.id', 'unit' => 'KMHS', 'roles' => ['kepala_unit']],
            ['name' => 'Novi Lestari, M.Kom', 'position' => 'Kepala Bagian Administrasi Akademik', 'email' => 'novilestari@univbinainsan.ac.id', 'unit' => 'BAAK', 'roles' => ['kepala_unit']],
            ['name' => 'Davit Irawan, M.Kom', 'position' => 'Kepala Bagian SDM dan Keuangan', 'email' => 'davit_irawan@univbinainsan.ac.id', 'unit' => 'SDM-KEU', 'roles' => ['kepala_unit']],
            ['name' => 'Muhammad Irvai, M.Kom', 'position' => 'Ketua SPMI UNIV.BI', 'email' => 'muhammadirvai@univbinainsan.ac.id', 'unit' => 'SPMI', 'roles' => ['ketua_spmi']],
            ['name' => 'Dr. Dheo Rimbano, SE., M.Si', 'position' => 'Dekan F. Ilmu Ekonomi dan Sosial Humaniora', 'email' => 'dheo_rimbano@univbinainsan.ac.id', 'unit' => 'FESH', 'roles' => ['dekan']],
            ['name' => 'Dr. Rudi Kurniawan, ST, M.Kom', 'position' => 'Dekan F. Ilmu Teknik', 'email' => 'rudi.kurniawan@univbinainsan.ac.id', 'unit' => 'FIT', 'roles' => ['dekan']],
            ['name' => 'Dr. Ir. Sugito Loso, S.P., M.M., M.Si', 'position' => 'Dekan F. Ilmu Tanaman dan Hewani', 'email' => 'sugito_loso@univbinainsan.ac.id', 'unit' => 'FITH', 'roles' => ['dekan']],
            ['name' => 'Dr. Ahmad Fahrudin, S.Pd., M.Pd', 'position' => 'Kepala UPT LPPM dan Kerja Sama', 'email' => 'fahrudinahmad63@gmail.com', 'unit' => 'LPPM', 'roles' => ['kepala_unit']],
            ['name' => 'Vero Faloris, S.Kom', 'position' => 'Plt. Kepala UPT Laboratorium Terpadu dan Pusat Komputer', 'email' => 'verofaloris@univbinainsan.ac.id', 'unit' => 'PUSKOM', 'roles' => ['kepala_unit']],
            ['name' => 'Rahmawati, S.Sos, MM', 'position' => 'Kepala UPT Perpustakaan', 'email' => 'rahmawati@univbinainsan.ac.id', 'unit' => 'PERPUS', 'roles' => ['kepala_unit']],
            ['name' => 'Dr. Rini Suryani, S.P., M.Si', 'position' => 'Kepala UPT Laboratorium Fakultas Ilmu Tanaman dan Hewani', 'email' => 'rinisuryani@univbinainsan.ac.id', 'unit' => 'LAB-FITH', 'roles' => ['kepala_unit']],
            ['name' => 'Dr. Herman Paleni, S.HI, S.E, M.Si', 'position' => 'Ketua Program Pascasarjana Magister Manajemen', 'email' => 'herman_paleni@univbinainsan.ac.id', 'unit' => 'MM', 'roles' => ['kaprodi']],
            ['name' => 'Irma Idayati, S.E., M.Si', 'position' => 'Ketua Program Studi Manajemen', 'email' => 'irmaidayati@univbinainsan.ac.id', 'unit' => 'MNJ', 'roles' => ['kaprodi']],
            ['name' => 'Indrawati Mara Kesuma, S.E., M.Si', 'position' => 'Ketua Program Studi Akuntansi', 'email' => 'indrawati_marakesuma@univbinainsan.ac.id', 'unit' => 'AKT', 'roles' => ['kaprodi']],
            ['name' => 'Ahmad Fuadi, S.HI., M.H', 'position' => 'Ketua Program Studi Hukum', 'email' => 'ahmadfuadi@univbinainsan.ac.id', 'unit' => 'HKM', 'roles' => ['kaprodi']],
            ['name' => 'Budi Santoso, M.Kom', 'position' => 'Ketua Program Studi Informatika', 'email' => 'budisantoso@univbinainsan.ac.id', 'unit' => 'TI', 'roles' => ['kaprodi']],
            ['name' => 'Melly Febriyanti, S.P., M.P', 'position' => 'Ketua Program Studi Agroteknologi', 'email' => 'mellyfebriyanti@univbinainsan.ac.id', 'unit' => 'AGT', 'roles' => ['kaprodi']],
            ['name' => 'Sri Murtini, S.Pi., M.Si', 'position' => 'Ketua Program Studi Ilmu Perikanan', 'email' => 'sri_murtini@univbinainsan.ac.id', 'unit' => 'IKN', 'roles' => ['kaprodi']],
            ['name' => 'Mujiran, SM, MM', 'position' => 'Kepala SAPRAS YPDTP', 'email' => 'mujiran@univbinainsan.ac.id', 'unit' => 'YPDTP', 'roles' => ['kepala_unit']],
            ['name' => 'Suparlan, S.Kom', 'position' => 'Kepala Perlengkapan ATK YPDTP', 'email' => 'suparlan@univbinainsan.ac.id', 'unit' => 'YPDTP', 'roles' => ['staf_unit']],
            ['name' => 'Lucia Ira Yuliastari, S.E., M.M', 'position' => 'Bendahara Pelaksana Harian YPDTP & Staf Keuangan UNIV.BI', 'email' => 'luciaira@univbinainsan.ac.id', 'unit' => 'SDM-KEU', 'roles' => ['staf_unit']],
            ['name' => 'Susilo, S.E, M.M', 'position' => 'Bendahara Pelaksana Pelaporan YPDTP & Staf Keuangan UNIV.BI', 'email' => 'susilo@univbinainsan.ac.id', 'unit' => 'SDM-KEU', 'roles' => ['staf_unit']],
            ['name' => 'Rani Ryani Putri, S.M', 'position' => 'Staf Rektor', 'email' => 'raniryani@univbinainsan.ac.id', 'unit' => 'REKTORAT', 'roles' => ['staf_unit']],
            ['name' => 'Puji Nopri Lestari, S.Kom', 'position' => 'Kepala Urusan Marketing', 'email' => 'pujinoprilestari@univbinainsan.ac.id', 'unit' => 'HUMAS', 'roles' => ['humas']],
            ['name' => 'Merlin Ayu Pratiwi, SE., M.M', 'position' => 'Kepala Urusan Keuangan', 'email' => 'merlinayu@univbinainsan.ac.id', 'unit' => 'SDM-KEU', 'roles' => ['staf_unit']],
            ['name' => 'Surajiyo, S.E., M.M', 'position' => 'Kepala Urusan Tata Laksana dan Bisnis', 'email' => 'surajiyo@univbinainsan.ac.id', 'unit' => 'HUMAS', 'roles' => ['humas']],
            ['name' => 'Arif Octavian, S.E., M.M', 'position' => 'Kepala Urusan SDM', 'email' => 'arif.octavian2410@gmail.com', 'unit' => 'SDM-KEU', 'roles' => ['staf_unit']],
            ['name' => 'Suhaipa, S.HI., S.H., M.M', 'position' => 'Staf Humas', 'email' => 'suhaipa@univbinainsan.ac.id', 'unit' => 'HUMAS', 'roles' => ['humas']],
            ['name' => 'M. Edo Bitra Kuncahyo, MM', 'position' => 'Staf Tatalaksana dan Kerjasama', 'email' => 'edobitra@univbinainsan.ac.id', 'unit' => 'HUMAS', 'roles' => ['humas']],
            ['name' => 'Erwin Susanto, S.Kom', 'position' => 'Kepala Produksi dan Operasional Pabrik Pelet UNIVBI', 'email' => 'erwinsusanto@univbinainsan.ac.id', 'unit' => 'PELET', 'roles' => ['kepala_unit']],
            ['name' => 'Deni Nurdiansyah, M.Kom', 'position' => 'Ketua Prodi RSK', 'email' => 'deninurdiansyah@univbinainsan.ac.id', 'unit' => 'RSK', 'roles' => ['kaprodi']],
            ['name' => 'Rahmad Dani, S.Kom', 'position' => 'Staf Administrasi Akademik', 'email' => 'rahmaddani@univbinainsan.ac.id', 'unit' => 'BAAK', 'roles' => ['staf_unit']],
            ['name' => 'Nur Sukmamuji Octadiana Sari, S.Kom', 'position' => 'Staf Administrasi Akademik', 'email' => 'nursukmamuji@univbinainsan.ac.id', 'unit' => 'BAAK', 'roles' => ['staf_unit']],
            ['name' => 'Rudi Irawan, S.M', 'position' => 'Staf Program Studi Magister Manajemen', 'email' => 'rudiirawan@univbinainsan.ac.id', 'unit' => 'MM', 'roles' => ['staf_unit']],
            ['name' => 'Fido Rizki, M.Kom', 'position' => 'Staf Kemahasiswaan', 'email' => 'fidorizki@univbinainsan.ac.id', 'unit' => 'KMHS', 'roles' => ['staf_unit']],
            ['name' => 'Sisca Ferannica Riast Agusty, S.E', 'position' => 'Staf Kemahasiswaan', 'email' => 'siscaferannica@univbinainsan.ac.id', 'unit' => 'KMHS', 'roles' => ['staf_unit']],
            ['name' => 'Bunga Intan, M.Kom', 'position' => 'Ketua Prodi SI', 'email' => 'bungaintan@univbinainsan.ac.id', 'unit' => 'SI', 'roles' => ['kaprodi']],
            ['name' => 'Andes Efiriansa, S.Kom., M.M', 'position' => 'Staf Fakultas Ilmu Ekonomi dan Sosial Humaniora', 'email' => 'andesefiriansa@univbinainsan.ac.id', 'unit' => 'FESH', 'roles' => ['staf_unit']],
            ['name' => 'Nosi Fanira, SE., M.M', 'position' => 'Staf Fakultas Ilmu Teknik', 'email' => 'nosifanira@univbinainsan.ac.id', 'unit' => 'FIT', 'roles' => ['staf_unit']],
            ['name' => 'Sri Tita Faulina, S.Kom., M.Kom', 'position' => 'Staf UPT LPPM UNIV.BI', 'email' => 'srititafaulina@univbinainsan.ac.id', 'unit' => 'LPPM', 'roles' => ['staf_unit']],
            ['name' => 'Nelly Khairani Daulay, M.Kom', 'position' => 'Staf UPT LPPM UNIV.BI', 'email' => 'nellykhairani@univbinainsan.ac.id', 'unit' => 'LPPM', 'roles' => ['staf_unit']],
            ['name' => 'Irnawati, A.Md, S.H', 'position' => 'Staf Fakultas Ilmu Tanaman dan Hewani', 'email' => 'irnawati@univbinainsan.ac.id', 'unit' => 'FITH', 'roles' => ['staf_unit']],
            ['name' => 'Ahmad Sobri, M.Kom', 'position' => 'Staf UPT Laboratorium Terpadu dan Pusat Komputer', 'email' => 'ahmadsobri@univbinainsan.ac.id', 'unit' => 'PUSKOM', 'roles' => ['staf_unit']],
            ['name' => 'Ilham Septian Harahap, S.Kom', 'position' => 'Staf UPT Laboratorium Terpadu dan Pusat Komputer', 'email' => 'ilhamseptian@univbinainsan.ac.id', 'unit' => 'PUSKOM', 'roles' => ['staf_unit']],
            ['name' => 'Desi Susanti, S.Pi., M.Si', 'position' => 'Staf SPMI UNIV.BI', 'email' => 'desisusanti@univbinainsan.ac.id', 'unit' => 'SPMI', 'roles' => ['staf_spmi']],
            ['name' => 'Dini Marlita, S.E', 'position' => 'Staf Administrasi Akademik', 'email' => 'dinimarlita@univbinainsan.ac.id', 'unit' => 'BAAK', 'roles' => ['staf_unit']],
            ['name' => 'Syafitri, S.Kom', 'position' => 'Staf SPMI UNIV.BI', 'email' => 'syafitri@univbinainsan.ac.id', 'unit' => 'SPMI', 'roles' => ['staf_spmi']],
            ['name' => 'Herlina Widiastuti, S.Kom', 'position' => 'Staf Keuangan UNIV.BI', 'email' => 'herlinawidiastuti@univbinainsan.ac.id', 'unit' => 'SDM-KEU', 'roles' => ['staf_unit']],
            ['name' => 'Rafi Taufiqurahman, S.Kom', 'position' => 'Staf UPT Laboratorium Terpadu dan Pusat Komputer', 'email' => 'rafitaufiqurahman@univbinainsan.ac.id', 'unit' => 'PUSKOM', 'roles' => ['staf_unit']],
            ['name' => 'Tri Anggun Setyo Putri, S.SI., M.A', 'position' => 'Staf Perpustakaan', 'email' => 'trianggun@univbinainsan.ac.id', 'unit' => 'PERPUS', 'roles' => ['staf_unit']],
            ['name' => 'Darmanto', 'position' => 'Staf Tatalaksana / Teknisi di bawah naungan Wakil Rektor II UNIV.BI', 'email' => 'darmanto@univbinainsan.ac.id', 'unit' => 'REKTORAT', 'roles' => ['staf_unit']],
            ['name' => 'Dr. Wawan Fransisco, SH., MH', 'position' => 'Ketua LSP Bina Insan', 'email' => 'wawanfransisco@univbinainsan.ac.id', 'unit' => 'LSP', 'roles' => ['kepala_unit']],
            ['name' => 'Rian Dinata, S.Kom', 'position' => 'Staf Pusat Informasi Pangkalan Data', 'email' => 'riandinata@univbinainsan.ac.id', 'unit' => 'HUMAS', 'roles' => ['super_admin']],
        ];

        $defaultPasswordHash = Hash::make('password');
        $seededUsers = [];

        foreach ($employees as $employee) {
            $user = User::firstOrNew(['email' => $employee['email']]);
            $user->fill([
                'name' => $employee['name'],
                'position_title' => $employee['position'],
                'unit_id' => $units[$employee['unit']]->id,
                'is_active' => true,
            ]);

            if (! $user->exists) {
                $user->password = $defaultPasswordHash;
                $user->email_verified_at = now();
            }

            $user->save();
            $user->syncRoles($employee['roles']);

            $seededUsers[$employee['email']] = $user;
        }

        return $seededUsers;
    }

    /**
     * @param  array<string, Unit>  $units
     * @param  array<string, User>  $usersByEmail
     */
    private function assignUnitHeads(array $units, array $usersByEmail): void
    {
        $headMap = [
            'REKTORAT' => 'dr.sardiyo@univbinainsan.ac.id',
            'FESH' => 'dheo_rimbano@univbinainsan.ac.id',
            'FIT' => 'rudi.kurniawan@univbinainsan.ac.id',
            'FITH' => 'sugito_loso@univbinainsan.ac.id',
            'MM' => 'herman_paleni@univbinainsan.ac.id',
            'MNJ' => 'irmaidayati@univbinainsan.ac.id',
            'AKT' => 'indrawati_marakesuma@univbinainsan.ac.id',
            'HKM' => 'ahmadfuadi@univbinainsan.ac.id',
            'TI' => 'budisantoso@univbinainsan.ac.id',
            'RSK' => 'deninurdiansyah@univbinainsan.ac.id',
            'SI' => 'bungaintan@univbinainsan.ac.id',
            'AGT' => 'mellyfebriyanti@univbinainsan.ac.id',
            'IKN' => 'sri_murtini@univbinainsan.ac.id',
            'LAB-FITH' => 'rinisuryani@univbinainsan.ac.id',
            'SPMI' => 'muhammadirvai@univbinainsan.ac.id',
            'HUMAS' => 'trihasanah@univbinainsan.ac.id',
            'BAAK' => 'novilestari@univbinainsan.ac.id',
            'KMHS' => 'wisdalia_maya_sari@univbinainsan.ac.id',
            'SDM-KEU' => 'davit_irawan@univbinainsan.ac.id',
            'LPPM' => 'fahrudinahmad63@gmail.com',
            'PUSKOM' => 'verofaloris@univbinainsan.ac.id',
            'PERPUS' => 'rahmawati@univbinainsan.ac.id',
            'LSP' => 'wawanfransisco@univbinainsan.ac.id',
            'YPDTP' => 'mujiran@univbinainsan.ac.id',
            'PELET' => 'erwinsusanto@univbinainsan.ac.id',
        ];

        foreach ($headMap as $unitCode => $headEmail) {
            if (isset($units[$unitCode], $usersByEmail[$headEmail])) {
                $units[$unitCode]->update([
                    'head_user_id' => $usersByEmail[$headEmail]->id,
                ]);
            }
        }
    }

    /**
     * Memastikan seluruh daftar izin standar Shield, widget dashboard, dan custom permissions tersedia di tabel permissions.
     *
     * @return list<string>
     */
    public static function ensurePermissionsExist(): array
    {
        $standardShieldAffixes = [
            'view_any',
            'view',
            'create',
            'update',
            'delete',
            'delete_any',
            'restore',
            'restore_any',
            'force_delete',
            'force_delete_any',
            'replicate',
            'reorder',
        ];

        $shieldSubjects = [
            'role' => $standardShieldAffixes,
            'user' => $standardShieldAffixes,
            'unit' => $standardShieldAffixes,
            'academic_year' => $standardShieldAffixes,
            'document_category' => $standardShieldAffixes,
            'accreditation_criterion' => $standardShieldAffixes,
            'document' => $standardShieldAffixes,
            'incoming_letter' => $standardShieldAffixes,
            'outgoing_letter' => $standardShieldAffixes,
            'post_category' => $standardShieldAffixes,
            'post' => $standardShieldAffixes,
            'activity_log' => ['view_any', 'view'],
        ];

        $shieldResourcePermissions = [];
        foreach ($shieldSubjects as $subject => $affixes) {
            foreach ($affixes as $affix) {
                $shieldResourcePermissions[] = "{$affix}:{$subject}";
            }
        }

        $widgetPermissions = [
            'view:dashboard_welcome_banner_widget',
            'view:dashboard_quick_actions_widget',
            'view:document_overview_widget',
            'view:document_lifecycle_stats_widget',
            'view:annual_published_document_chart_widget',
            'view:pending_approvals_widget',
            'view:pending_letters_alert_widget',
            'view:post_period_overview_widget',
        ];

        $customPermissions = array_keys((array) config('filament-shield.custom_permissions', []));

        $allPermissions = array_values(array_unique(array_merge(
            $shieldResourcePermissions,
            $widgetPermissions,
            $customPermissions,
        )));

        foreach ($allPermissions as $permissionName) {
            Permission::findOrCreate($permissionName, 'web');
        }

        return $allPermissions;
    }
}
