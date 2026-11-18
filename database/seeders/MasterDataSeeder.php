<?php

namespace Database\Seeders;

use App\Enums\AccreditationInstrument;
use App\Enums\DegreeLevel;
use App\Enums\StampingPosition;
use App\Enums\UnitType;
use App\Enums\VisibilityLevel;
use App\Models\AcademicYear;
use App\Models\AccreditationCriterion;
use App\Models\AssetCategory;
use App\Models\AssetLocation;
use App\Models\CategoryApprovalFlow;
use App\Models\DocumentCategory;
use App\Models\Permission;
use App\Models\PostCategory;
use App\Models\Role;
use App\Models\Setting;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\PermissionRegistrar;

class MasterDataSeeder extends Seeder
{
    /**
     * Run the master data seeds (Safe & Idempotent for both Local and Production).
     */
    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $this->seedRolesAndPermissions();
        $units = $this->seedUnits();
        $users = $this->seedOfficialUsers($units);
        $this->assignUnitHeads($units, $users);
        $this->seedAcademicYears();
        $this->seedAccreditationCriteria();
        $this->seedDocumentCategoriesAndFlows();
        $this->seedPostCategories();
        $this->seedAssetCategoriesAndLocations($units, $users);
        $this->seedSettings();
    }

    private function seedRolesAndPermissions(): void
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
            'view:annual_published_document_chart_widget',
            'view:pending_approvals_widget',
            'view:pending_letters_alert_widget',
            'view:post_period_overview_widget',
        ];

        $customPermissions = [
            // Custom Document & E-Arsip Permissions
            'document.view_confidential',
            'document.download_private',
            'document.approve',
            'document.manual_numbering',
            'document.bypass_unit_scope',
            'letter.manage_humas',
            'archive.bulk_export_zip',

            // Borang Akreditasi Permissions
            'borang.export_excel',
            'borang.download_bulk_zip',
            'borang.configure_assessor',

            // Reminder Permissions
            'reminder.manage_recipients',

            // CMS & Portal Publik Permissions
            'post.moderate_humas',
            'post.download_zip',
            'cms.manage_landing_page',

            // Sistem Backup & Pemulihan (Eksklusif Super Admin)
            'system.backup_manage',
            'system.backup_restore',

            // Dedicated Asset Management Panel (/assets) Permissions
            'asset.access_panel',
            'asset.bypass_unit_scope',
            'asset.create',
            'asset.print_qr',
            'asset.transfer_unit',
            'asset.relocate_room',
            'asset.report_issue',
            'asset.process_maintenance',
            'asset.stock_opname',
            'asset.dispose',
            'asset.export_borang_k5',
        ];

        $allPermissions = array_values(array_unique(array_merge(
            $shieldResourcePermissions,
            $widgetPermissions,
            $customPermissions,
        )));

        foreach ($allPermissions as $permissionName) {
            Permission::findOrCreate($permissionName, 'web');
        }

        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $documentReadWrite = array_merge($widgetPermissions, [
            'view_any:document',
            'view:document',
            'create:document',
            'update:document',
            'view_any:incoming_letter',
            'view:incoming_letter',
            'view_any:outgoing_letter',
            'view:outgoing_letter',
            'create:outgoing_letter',
            'update:outgoing_letter',
            'delete:outgoing_letter',
            'view_any:accreditation_criterion',
            'view:accreditation_criterion',
            'view_any:document_category',
            'view:document_category',
            'view_any:academic_year',
            'view:academic_year',
            'view_any:unit',
            'view:unit',
        ]);

        $postReadWrite = [
            'view_any:post',
            'view:post',
            'create:post',
            'update:post',
            'view_any:post_category',
            'view:post_category',
            'post.download_zip',
        ];

        $rolePermissions = [
            'super_admin' => $allPermissions,
            'rektor' => array_merge($documentReadWrite, [
                'view_any:activity_log',
                'view:activity_log',
                'document.view_confidential',
                'document.download_private',
                'document.approve',
                'document.manual_numbering',
                'document.bypass_unit_scope',
                'borang.export_excel',
                'borang.download_bulk_zip',
                'borang.configure_assessor',
                'cms.manage_landing_page',
                'post.download_zip',
                'asset.access_panel',
                'asset.bypass_unit_scope',
                'asset.export_borang_k5',
            ]),
            'warek' => array_merge($documentReadWrite, [
                'view_any:activity_log',
                'view:activity_log',
                'document.view_confidential',
                'document.download_private',
                'document.approve',
                'document.manual_numbering',
                'document.bypass_unit_scope',
                'borang.export_excel',
                'borang.download_bulk_zip',
                'borang.configure_assessor',
                'cms.manage_landing_page',
                'post.download_zip',
                'asset.access_panel',
                'asset.bypass_unit_scope',
                'asset.export_borang_k5',
            ]),
            'dekan' => array_merge($documentReadWrite, $postReadWrite, [
                'delete:document',
                'document.download_private',
                'document.approve',
                'document.manual_numbering',
                'borang.export_excel',
                'borang.download_bulk_zip',
                'borang.configure_assessor',
                'reminder.manage_recipients',
                'asset.access_panel',
                'asset.relocate_room',
                'asset.report_issue',
                'asset.export_borang_k5',
            ]),
            'kaprodi' => array_merge($documentReadWrite, $postReadWrite, [
                'delete:document',
                'document.download_private',
                'document.approve',
                'document.manual_numbering',
                'borang.export_excel',
                'borang.download_bulk_zip',
                'borang.configure_assessor',
                'reminder.manage_recipients',
                'asset.access_panel',
                'asset.print_qr',
                'asset.relocate_room',
                'asset.report_issue',
                'asset.export_borang_k5',
            ]),
            'lpm' => array_merge($documentReadWrite, [
                'create:accreditation_criterion',
                'update:accreditation_criterion',
                'document.download_private',
                'document.approve',
                'document.manual_numbering',
                'document.bypass_unit_scope',
                'borang.export_excel',
                'borang.download_bulk_zip',
                'borang.configure_assessor',
                'post.download_zip',
                'asset.access_panel',
                'asset.bypass_unit_scope',
                'asset.export_borang_k5',
            ]),
            'lppm' => array_merge($documentReadWrite, $postReadWrite, [
                'document.download_private',
                'document.approve',
                'document.manual_numbering',
                'reminder.manage_recipients',
                'asset.access_panel',
                'asset.report_issue',
            ]),
            'kepala_biro' => array_merge($documentReadWrite, [
                'document.download_private',
                'document.approve',
                'document.manual_numbering',
                'reminder.manage_recipients',
                'asset.access_panel',
                'asset.report_issue',
            ]),
            'bauk' => array_merge($documentReadWrite, [
                'document.download_private',
                'document.manual_numbering',
                'reminder.manage_recipients',
                'asset.access_panel',
                'asset.bypass_unit_scope',
                'asset.create',
                'asset.print_qr',
                'asset.transfer_unit',
                'asset.relocate_room',
                'asset.report_issue',
                'asset.process_maintenance',
                'asset.stock_opname',
                'asset.dispose',
                'asset.export_borang_k5',
            ]),
            'upt_ict' => array_merge($documentReadWrite, [
                'view_any:user',
                'view:user',
                'create:user',
                'update:user',
                'view_any:activity_log',
                'view:activity_log',
                'document.download_private',
                'asset.access_panel',
                'asset.bypass_unit_scope',
                'asset.create',
                'asset.print_qr',
                'asset.relocate_room',
                'asset.report_issue',
                'asset.process_maintenance',
                'asset.stock_opname',
                'asset.export_borang_k5',
            ]),
            'humas' => array_merge($documentReadWrite, $postReadWrite, [
                'create:incoming_letter',
                'update:incoming_letter',
                'delete:incoming_letter',
                'delete_any:incoming_letter',
                'delete_any:outgoing_letter',
                'create:post_category',
                'update:post_category',
                'delete:post',
                'document.download_private',
                'document.manual_numbering',
                'letter.manage_humas',
                'post.moderate_humas',
                'post.download_zip',
                'cms.manage_landing_page',
            ]),
            'staf_unit' => array_merge($documentReadWrite, $postReadWrite, [
                'document.download_private',
                'document.manual_numbering',
                'reminder.manage_recipients',
                'post.download_zip',
                'asset.access_panel',
                'asset.report_issue',
                'asset.export_borang_k5',
            ]),
        ];

        foreach ($rolePermissions as $roleName => $assignedPermissions) {
            $role = Role::findOrCreate($roleName, 'web');
            $role->syncPermissions(array_values(array_unique($assignedPermissions)));
        }
    }

    /**
     * @return array<string, Unit>
     */
    private function seedUnits(): array
    {
        $units = [];

        // 1. Rektorat
        $units['REKTORAT'] = Unit::updateOrCreate(
            ['code' => 'REKTORAT'],
            [
                'name' => 'Rektorat Universitas Bina Insan',
                'type' => UnitType::Rektorat,
                'accreditation_grade' => 'Baik Sekali',
                'accreditation_body' => 'BAN-PT',
                'accreditation_instrument' => AccreditationInstrument::BanPt->value,
                'is_active' => true,
            ]
        );

        // 2. Tiga Fakultas
        $faculties = [
            'FIK' => ['name' => 'Fakultas Ilmu Komputer', 'instrument' => AccreditationInstrument::LamInfokom->value],
            'FEB' => ['name' => 'Fakultas Ekonomi dan Bisnis', 'instrument' => AccreditationInstrument::Lamemba->value],
            'FHSH' => ['name' => 'Fakultas Hukum, Sosial dan Humaniora', 'instrument' => AccreditationInstrument::BanPt->value],
        ];

        foreach ($faculties as $code => $data) {
            $units[$code] = Unit::updateOrCreate(
                ['code' => $code],
                [
                    'name' => $data['name'],
                    'type' => UnitType::Fakultas,
                    'parent_id' => $units['REKTORAT']->id,
                    'accreditation_instrument' => $data['instrument'],
                    'is_active' => true,
                ]
            );
        }

        // 3. Sembilan Program Studi (9 Prodi)
        $prodis = [
            ['code' => 'TI', 'name' => 'Program Studi S1 Informatika', 'degree' => DegreeLevel::S1, 'parent' => 'FIK', 'body' => 'LAM Infokom', 'instrument' => AccreditationInstrument::LamInfokom->value, 'grade' => 'Baik Sekali'],
            ['code' => 'SI', 'name' => 'Program Studi S1 Sistem Informasi', 'degree' => DegreeLevel::S1, 'parent' => 'FIK', 'body' => 'LAM Infokom', 'instrument' => AccreditationInstrument::LamInfokom->value, 'grade' => 'Baik Sekali'],
            ['code' => 'SK', 'name' => 'Program Studi S1 Sistem Komputer', 'degree' => DegreeLevel::S1, 'parent' => 'FIK', 'body' => 'LAM Infokom', 'instrument' => AccreditationInstrument::LamInfokom->value, 'grade' => 'Baik'],
            ['code' => 'RPL', 'name' => 'Program Studi S1 Rekayasa Perangkat Lunak', 'degree' => DegreeLevel::S1, 'parent' => 'FIK', 'body' => 'LAM Infokom', 'instrument' => AccreditationInstrument::LamInfokom->value, 'grade' => 'Baik'],
            ['code' => 'KA', 'name' => 'Program Studi D3 Komputerisasi Akuntansi', 'degree' => DegreeLevel::D3, 'parent' => 'FIK', 'body' => 'LAM Infokom', 'instrument' => AccreditationInstrument::LamInfokom->value, 'grade' => 'Baik'],
            ['code' => 'MI', 'name' => 'Program Studi D3 Manajemen Informatika', 'degree' => DegreeLevel::D3, 'parent' => 'FIK', 'body' => 'LAM Infokom', 'instrument' => AccreditationInstrument::LamInfokom->value, 'grade' => 'Baik'],
            ['code' => 'MNJ', 'name' => 'Program Studi S1 Manajemen', 'degree' => DegreeLevel::S1, 'parent' => 'FEB', 'body' => 'LAMEMBA', 'instrument' => AccreditationInstrument::Lamemba->value, 'grade' => 'Baik Sekali'],
            ['code' => 'AKT', 'name' => 'Program Studi S1 Akuntansi', 'degree' => DegreeLevel::S1, 'parent' => 'FEB', 'body' => 'LAMEMBA', 'instrument' => AccreditationInstrument::Lamemba->value, 'grade' => 'Baik Sekali'],
            ['code' => 'HKM', 'name' => 'Program Studi S1 Ilmu Hukum', 'degree' => DegreeLevel::S1, 'parent' => 'FHSH', 'body' => 'BAN-PT', 'instrument' => AccreditationInstrument::BanPt->value, 'grade' => 'Baik Sekali'],
        ];

        foreach ($prodis as $prodi) {
            $units[$prodi['code']] = Unit::updateOrCreate(
                ['code' => $prodi['code']],
                [
                    'name' => $prodi['name'],
                    'type' => UnitType::Prodi,
                    'degree_level' => $prodi['degree'],
                    'parent_id' => $units[$prodi['parent']]->id,
                    'accreditation_grade' => $prodi['grade'],
                    'accreditation_body' => $prodi['body'],
                    'accreditation_instrument' => $prodi['instrument'],
                    'is_active' => true,
                ]
            );
        }

        // 4. Biro, Lembaga, UPT & Unit Pendukung
        $otherUnits = [
            ['code' => 'BAAK', 'name' => 'Biro Administrasi Akademik dan Kemahasiswaan (BAAK)', 'type' => UnitType::Biro],
            ['code' => 'BAUK', 'name' => 'Biro Administrasi Umum dan Keuangan (BAUK)', 'type' => UnitType::Biro],
            ['code' => 'LPM', 'name' => 'Lembaga Penjaminan Mutu (LPM)', 'type' => UnitType::Lembaga],
            ['code' => 'LPPM', 'name' => 'Lembaga Penelitian dan Pengabdian kepada Masyarakat (LPPM)', 'type' => UnitType::Lembaga],
            ['code' => 'ICT', 'name' => 'UPT Teknologi Informasi dan Komunikasi (ICT)', 'type' => UnitType::Upt],
            ['code' => 'HUMAS', 'name' => 'Bagian Humas, Protokoler dan Kerja Sama', 'type' => UnitType::UnitPendukung],
        ];

        foreach ($otherUnits as $other) {
            $units[$other['code']] = Unit::updateOrCreate(
                ['code' => $other['code']],
                [
                    'name' => $other['name'],
                    'type' => $other['type'],
                    'parent_id' => $units['REKTORAT']->id,
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
    private function seedOfficialUsers(array $units): array
    {
        $defaultPassword = Hash::make('password');

        $accounts = [
            'super_admin' => [
                'name' => 'Super Administrator UNBI',
                'email' => 'admin@univbi.ac.id',
                'nidn_nip' => '19900101202001',
                'position_title' => 'Kepala Sistem Informasi & E-Arsip',
                'unit_code' => 'ICT',
                'role' => 'super_admin',
            ],
            'rektor' => [
                'name' => 'Dr. H. Sardiyo, M.M.',
                'email' => 'rektor@univbi.ac.id',
                'nidn_nip' => '0201017001',
                'position_title' => 'Rektor Universitas Bina Insan',
                'unit_code' => 'REKTORAT',
                'role' => 'rektor',
            ],
            'warek_1' => [
                'name' => 'Dr. Yohanes, M.Kom.',
                'email' => 'warek1@univbi.ac.id',
                'nidn_nip' => '0202027501',
                'position_title' => 'Wakil Rektor I Bidang Akademik',
                'unit_code' => 'REKTORAT',
                'role' => 'warek',
            ],
            'dekan_fik' => [
                'name' => 'Dr. Sutrisno, M.Kom.',
                'email' => 'dekan.fik@univbi.ac.id',
                'nidn_nip' => '0203037801',
                'position_title' => 'Dekan Fakultas Ilmu Komputer',
                'unit_code' => 'FIK',
                'role' => 'dekan',
            ],
            'dekan_feb' => [
                'name' => 'Dr. Rina Andriani, S.E., M.Si.',
                'email' => 'dekan.feb@univbi.ac.id',
                'nidn_nip' => '0204047901',
                'position_title' => 'Dekan Fakultas Ekonomi dan Bisnis',
                'unit_code' => 'FEB',
                'role' => 'dekan',
            ],
            'dekan_fhsh' => [
                'name' => 'Dr. Hendra Kusuma, S.H., M.H.',
                'email' => 'dekan.fhsh@univbi.ac.id',
                'nidn_nip' => '0205058001',
                'position_title' => 'Dekan Fakultas Hukum, Sosial dan Humaniora',
                'unit_code' => 'FHSH',
                'role' => 'dekan',
            ],
            'kaprodi_ti' => [
                'name' => 'Lukman Hakim, S.Kom., M.Kom.',
                'email' => 'kaprodi.ti@univbi.ac.id',
                'nidn_nip' => '0206068501',
                'position_title' => 'Ketua Program Studi S1 Informatika',
                'unit_code' => 'TI',
                'role' => 'kaprodi',
            ],
            'kaprodi_si' => [
                'name' => 'Dwi Ratnasari, S.Kom., M.Kom.',
                'email' => 'kaprodi.si@univbi.ac.id',
                'nidn_nip' => '0207078601',
                'position_title' => 'Ketua Program Studi S1 Sistem Informasi',
                'unit_code' => 'SI',
                'role' => 'kaprodi',
            ],
            'kaprodi_mnj' => [
                'name' => 'Agus Setiawan, S.E., M.M.',
                'email' => 'kaprodi.mnj@univbi.ac.id',
                'nidn_nip' => '0208088701',
                'position_title' => 'Ketua Program Studi S1 Manajemen',
                'unit_code' => 'MNJ',
                'role' => 'kaprodi',
            ],
            'kepala_lpm' => [
                'name' => 'Dr. Fitriani, M.Pd.',
                'email' => 'lpm@univbi.ac.id',
                'nidn_nip' => '0209098201',
                'position_title' => 'Kepala Lembaga Penjaminan Mutu (LPM)',
                'unit_code' => 'LPM',
                'role' => 'lpm',
            ],
            'kepala_bauk' => [
                'name' => 'H. Bambang Wijaya, S.E., M.Si.',
                'email' => 'bauk@univbi.ac.id',
                'nidn_nip' => '19820512201501',
                'position_title' => 'Kepala Biro Administrasi Umum dan Keuangan (BAUK)',
                'unit_code' => 'BAUK',
                'role' => 'bauk',
            ],
            'kepala_baak' => [
                'name' => 'Siti Aminah, S.Kom., M.Si.',
                'email' => 'baak@univbi.ac.id',
                'nidn_nip' => '19850819201602',
                'position_title' => 'Kepala Biro Administrasi Akademik dan Kemahasiswaan (BAAK)',
                'unit_code' => 'BAAK',
                'role' => 'kepala_biro',
            ],
            'kepala_ict' => [
                'name' => 'Ryan Pratama, S.Kom., M.T.I.',
                'email' => 'ict@univbi.ac.id',
                'nidn_nip' => '19920310201901',
                'position_title' => 'Kepala UPT Teknologi Informasi dan Komunikasi (ICT)',
                'unit_code' => 'ICT',
                'role' => 'upt_ict',
            ],
            'staf_humas' => [
                'name' => 'Nadia Putri, S.I.Kom.',
                'email' => 'humas@univbi.ac.id',
                'nidn_nip' => '19950721202102',
                'position_title' => 'Koordinator Humas & Publikasi Kampus',
                'unit_code' => 'HUMAS',
                'role' => 'humas',
            ],
            'staf_ti' => [
                'name' => 'Andi Saputra, S.Kom.',
                'email' => 'staf.ti@univbi.ac.id',
                'nidn_nip' => '19971104202201',
                'position_title' => 'Staf Administrasi Prodi S1 Informatika',
                'unit_code' => 'TI',
                'role' => 'staf_unit',
            ],
        ];

        $users = [];

        foreach ($accounts as $key => $account) {
            $user = User::updateOrCreate(
                ['email' => $account['email']],
                [
                    'name' => $account['name'],
                    'nidn_nip' => $account['nidn_nip'],
                    'position_title' => $account['position_title'],
                    'phone' => '08127300'.str_pad((string) count($users), 4, '0', STR_PAD_LEFT),
                    'password' => $defaultPassword,
                    'unit_id' => $units[$account['unit_code']]->id,
                    'is_active' => true,
                    'email_verified_at' => now(),
                ]
            );

            $user->syncRoles([$account['role']]);
            $users[$key] = $user;
        }

        return $users;
    }

    /**
     * @param  array<string, Unit>  $units
     * @param  array<string, User>  $users
     */
    private function assignUnitHeads(array $units, array $users): void
    {
        $mapping = [
            'REKTORAT' => 'rektor',
            'FIK' => 'dekan_fik',
            'FEB' => 'dekan_feb',
            'FHSH' => 'dekan_fhsh',
            'TI' => 'kaprodi_ti',
            'SI' => 'kaprodi_si',
            'MNJ' => 'kaprodi_mnj',
            'LPM' => 'kepala_lpm',
            'BAUK' => 'kepala_bauk',
            'BAAK' => 'kepala_baak',
            'ICT' => 'kepala_ict',
            'HUMAS' => 'staf_humas',
        ];

        foreach ($mapping as $unitCode => $userKey) {
            if (isset($units[$unitCode], $users[$userKey])) {
                $units[$unitCode]->update(['head_user_id' => $users[$userKey]->id]);
            }
        }
    }

    private function seedAcademicYears(): void
    {
        $years = [
            ['name' => '2024/2025 Ganjil', 'semester' => 'ganjil', 'start_date' => '2024-09-01', 'end_date' => '2025-01-31', 'is_active' => false],
            ['name' => '2024/2025 Genap', 'semester' => 'genap', 'start_date' => '2025-02-01', 'end_date' => '2025-07-31', 'is_active' => false],
            ['name' => '2025/2026 Ganjil', 'semester' => 'ganjil', 'start_date' => '2025-09-01', 'end_date' => '2026-01-31', 'is_active' => false],
            ['name' => '2025/2026 Genap', 'semester' => 'genap', 'start_date' => '2026-02-01', 'end_date' => '2026-07-31', 'is_active' => false],
            ['name' => '2026/2027 Ganjil', 'semester' => 'ganjil', 'start_date' => '2026-09-01', 'end_date' => '2027-01-31', 'is_active' => true],
        ];

        foreach ($years as $year) {
            AcademicYear::updateOrCreate(['name' => $year['name']], $year);
        }
    }

    private function seedAccreditationCriteria(): void
    {
        $instruments = [
            AccreditationInstrument::BanPt->value => [
                'prefix' => 'BAN-PT',
                'criteria' => [
                    ['code' => 'K1', 'name' => 'Kriteria 1 - Visi, Misi, Tujuan, dan Strategi', 'order_index' => 1],
                    ['code' => 'K2', 'name' => 'Kriteria 2 - Tata Pamong, Tata Kelola, dan Kerja Sama', 'order_index' => 2],
                    ['code' => 'K3', 'name' => 'Kriteria 3 - Mahasiswa', 'order_index' => 3],
                    ['code' => 'K4', 'name' => 'Kriteria 4 - Sumber Daya Manusia', 'order_index' => 4],
                    ['code' => 'K5', 'name' => 'Kriteria 5 - Keuangan, Sarana, dan Prasarana', 'order_index' => 5],
                    ['code' => 'K6', 'name' => 'Kriteria 6 - Pendidikan', 'order_index' => 6],
                    ['code' => 'K7', 'name' => 'Kriteria 7 - Penelitian', 'order_index' => 7],
                    ['code' => 'K8', 'name' => 'Kriteria 8 - Pengabdian kepada Masyarakat', 'order_index' => 8],
                    ['code' => 'K9', 'name' => 'Kriteria 9 - Luaran dan Capaian Tridharma', 'order_index' => 9],
                ],
            ],
            AccreditationInstrument::LamInfokom->value => [
                'prefix' => 'LAM-INFOKOM',
                'criteria' => [
                    ['code' => 'K1', 'name' => 'Kriteria 1 - Visi, Misi, Tujuan, dan Strategi (Keilmuan Informatika & Komputer)', 'order_index' => 1],
                    ['code' => 'K2', 'name' => 'Kriteria 2 - Tata Pamong, Tata Kelola, dan Kerjasama', 'order_index' => 2],
                    ['code' => 'K3', 'name' => 'Kriteria 3 - Mahasiswa & Daya Tarik Calon Mahasiswa', 'order_index' => 3],
                    ['code' => 'K4', 'name' => 'Kriteria 4 - Sumber Daya Manusia (Dosen Tetap INFOKOM & Tenaga Kependidikan)', 'order_index' => 4],
                    ['code' => 'K5', 'name' => 'Kriteria 5 - Keuangan, Sarana, dan Prasarana Laboratorium Komputer', 'order_index' => 5],
                    ['code' => 'K6', 'name' => 'Kriteria 6 - Pendidikan (Kurikulum Berbasis OBE & Capaian Pembelajaran)', 'order_index' => 6],
                    ['code' => 'K7', 'name' => 'Kriteria 7 - Penelitian (Riset Informatika, Publikasi & Karya Rekayasa Perangkat Lunak)', 'order_index' => 7],
                    ['code' => 'K8', 'name' => 'Kriteria 8 - Pengabdian kepada Masyarakat (Pemberdayaan Teknologi)', 'order_index' => 8],
                    ['code' => 'K9', 'name' => 'Kriteria 9 - Luaran dan Capaian Tridharma (Daya Saing Lulusan & HKI/Paten)', 'order_index' => 9],
                ],
            ],
            AccreditationInstrument::Lamemba->value => [
                'prefix' => 'LAMEMBA',
                'criteria' => [
                    ['code' => 'K1', 'name' => 'Kriteria 1 - Visi, Misi, Tujuan, dan Strategi (Konteks Bisnis & Manajemen)', 'order_index' => 1],
                    ['code' => 'K2', 'name' => 'Kriteria 2 - Tata Pamong, Tata Kelola, dan Kerja Sama Industri & Perbankan', 'order_index' => 2],
                    ['code' => 'K3', 'name' => 'Kriteria 3 - Mahasiswa (Prestasi Bisnis & Kewirausahaan)', 'order_index' => 3],
                    ['code' => 'K4', 'name' => 'Kriteria 4 - Sumber Daya Manusia (Dosen Berkualifikasi Bisnis & Praktisi)', 'order_index' => 4],
                    ['code' => 'K5', 'name' => 'Kriteria 5 - Keuangan, Sarana, dan Prasarana (Lab Pasar Modal & Galeri Investasi)', 'order_index' => 5],
                    ['code' => 'K6', 'name' => 'Kriteria 6 - Pendidikan (Kurikulum Bisnis & Kasus Studi Nyata)', 'order_index' => 6],
                    ['code' => 'K7', 'name' => 'Kriteria 7 - Penelitian (Riset Manajemen & Akuntansi Terindeks)', 'order_index' => 7],
                    ['code' => 'K8', 'name' => 'Kriteria 8 - Pengabdian kepada Masyarakat (Pendampingan UMKM & Finansial)', 'order_index' => 8],
                    ['code' => 'K9', 'name' => 'Kriteria 9 - Luaran dan Capaian Tridharma (Karier Lulusan di Sektor Bisnis)', 'order_index' => 9],
                ],
            ],
        ];

        foreach ($instruments as $instrumentSource => $itemGroup) {
            foreach ($itemGroup['criteria'] as $item) {
                AccreditationCriterion::updateOrCreate(
                    [
                        'code' => $item['code'],
                        'instrument_source' => $instrumentSource,
                    ],
                    [
                        'name' => $item['name'],
                        'order_index' => $item['order_index'],
                        'is_active' => true,
                    ]
                );
            }
        }
    }

    private function seedDocumentCategoriesAndFlows(): void
    {
        $categories = [
            [
                'slug' => 'sk-rektor',
                'name' => 'Surat Keputusan (SK) Rektor',
                'code' => 'SK',
                'number_format' => '{seq}/UNIVBI.{unit}/{cat}/{roman_month}/{year}',
                'requires_approval' => true,
                'allow_manual_number' => true,
                'has_expiry' => false,
                'default_visibility' => VisibilityLevel::Internal,
                'stamp_position' => StampingPosition::Footer,
                'flows' => [
                    ['step_order' => 1, 'approver_role' => 'warek', 'step_label' => 'Verifikasi Wakil Rektor'],
                    ['step_order' => 2, 'approver_role' => 'rektor', 'step_label' => 'Pengesahan Final Rektor'],
                ],
            ],
            [
                'slug' => 'sk-dekan',
                'name' => 'Surat Keputusan (SK) Dekan Fakultas',
                'code' => 'SK',
                'number_format' => '{seq}/UNIVBI.{unit}/{cat}/{roman_month}/{year}',
                'requires_approval' => true,
                'allow_manual_number' => true,
                'has_expiry' => false,
                'default_visibility' => VisibilityLevel::Internal,
                'stamp_position' => StampingPosition::Footer,
                'flows' => [
                    ['step_order' => 1, 'approver_role' => 'kaprodi', 'step_label' => 'Verifikasi Ketua Program Studi'],
                    ['step_order' => 2, 'approver_role' => 'dekan', 'step_label' => 'Pengesahan Dekan Fakultas'],
                ],
            ],
            [
                'slug' => 'st-fakultas',
                'name' => 'Surat Tugas (ST) Dosen / Staf Fakultas',
                'code' => 'ST',
                'number_format' => '{seq}/UNIVBI.{unit}/{cat}/{roman_month}/{year}',
                'requires_approval' => true,
                'allow_manual_number' => true,
                'has_expiry' => false,
                'default_visibility' => VisibilityLevel::Internal,
                'stamp_position' => StampingPosition::Footer,
                'flows' => [
                    ['step_order' => 1, 'approver_role' => 'kaprodi', 'step_label' => 'Persetujuan Kaprodi'],
                    ['step_order' => 2, 'approver_role' => 'dekan', 'step_label' => 'Pengesahan Dekan'],
                ],
            ],
            [
                'slug' => 'se-rektorat',
                'name' => 'Surat Edaran (SE) Resmi Kampus',
                'code' => 'SE',
                'number_format' => '{seq}/UNIVBI.{unit}/{cat}/{roman_month}/{year}',
                'requires_approval' => true,
                'allow_manual_number' => true,
                'has_expiry' => false,
                'default_visibility' => VisibilityLevel::Public,
                'stamp_position' => StampingPosition::Footer,
                'flows' => [
                    ['step_order' => 1, 'approver_role' => 'warek', 'step_label' => 'Pemeriksaan Wakil Rektor'],
                    ['step_order' => 2, 'approver_role' => 'rektor', 'step_label' => 'Pengesahan Rektor'],
                ],
            ],
            [
                'slug' => 'mou',
                'name' => 'Nota Kesepahaman (MoU) Kerja Sama',
                'code' => 'MOU',
                'number_format' => '{seq}/UNIVBI.{unit}/{cat}/{roman_month}/{year}',
                'requires_approval' => true,
                'allow_manual_number' => true,
                'has_expiry' => true,
                'default_visibility' => VisibilityLevel::Internal,
                'stamp_position' => StampingPosition::Footer,
                'flows' => [
                    ['step_order' => 1, 'approver_role' => 'dekan', 'step_label' => 'Review Dekan / Kepala Unit'],
                    ['step_order' => 2, 'approver_role' => 'warek', 'step_label' => 'Verifikasi Wakil Rektor'],
                    ['step_order' => 3, 'approver_role' => 'rektor', 'step_label' => 'Pengesahan Final Rektor'],
                ],
            ],
            [
                'slug' => 'rps',
                'name' => 'Rencana Pembelajaran Semester (RPS) & Perangkat Ajar',
                'code' => 'RPS',
                'number_format' => '{seq}/UNIVBI.{unit}/{cat}/{roman_month}/{year}',
                'requires_approval' => true,
                'allow_manual_number' => true,
                'has_expiry' => false,
                'default_visibility' => VisibilityLevel::Internal,
                'stamp_position' => StampingPosition::Footer,
                'flows' => [
                    ['step_order' => 1, 'approver_role' => 'kaprodi', 'step_label' => 'Pengesahan Kaprodi'],
                ],
            ],
            [
                'slug' => 'bast-aset',
                'name' => 'Berita Acara Serah Terima (BAST) Inventaris Aset',
                'code' => 'BAST',
                'number_format' => '{seq}/UNIVBI.{unit}/{cat}/{roman_month}/{year}',
                'requires_approval' => false,
                'allow_manual_number' => true,
                'has_expiry' => false,
                'default_visibility' => VisibilityLevel::Internal,
                'stamp_position' => StampingPosition::Footer,
                'flows' => [],
            ],
            [
                'slug' => 'surat-masuk',
                'name' => 'Surat Masuk Eksternal / Arsip Historis',
                'code' => 'SM',
                'number_format' => '{seq}/UNIVBI.{unit}/{cat}/{roman_month}/{year}',
                'requires_approval' => false,
                'allow_manual_number' => true,
                'has_expiry' => false,
                'default_visibility' => VisibilityLevel::Internal,
                'stamp_position' => StampingPosition::Footer,
                'flows' => [],
            ],
        ];

        foreach ($categories as $item) {
            $flows = $item['flows'];
            unset($item['flows']);

            $category = DocumentCategory::updateOrCreate(
                ['slug' => $item['slug']],
                array_merge($item, ['is_active' => true])
            );

            foreach ($flows as $flow) {
                CategoryApprovalFlow::updateOrCreate(
                    [
                        'document_category_id' => $category->id,
                        'step_order' => $flow['step_order'],
                    ],
                    [
                        'approver_role' => $flow['approver_role'],
                        'step_label' => $flow['step_label'],
                        'is_mandatory' => true,
                    ]
                );
            }
        }
    }

    private function seedPostCategories(): void
    {
        $postCategories = [
            ['slug' => 'pengumuman-akademik', 'name' => 'Pengumuman Akademik', 'color' => 'blue', 'order_index' => 1],
            ['slug' => 'surat-edaran-resmi', 'name' => 'Surat Edaran Resmi', 'color' => 'red', 'order_index' => 2],
            ['slug' => 'berita-kegiatan', 'name' => 'Berita Acara & Dokumentasi Kegiatan', 'color' => 'green', 'order_index' => 3],
            ['slug' => 'informasi-beasiswa', 'name' => 'Informasi Beasiswa & Kemahasiswaan', 'color' => 'amber', 'order_index' => 4],
            ['slug' => 'prestasi-kampus', 'name' => 'Prestasi Mahasiswa & Dosen', 'color' => 'purple', 'order_index' => 5],
            ['slug' => 'kerjasama-karir', 'name' => 'Kerja Sama Industri & Karir', 'color' => 'orange', 'order_index' => 6],
        ];

        foreach ($postCategories as $category) {
            PostCategory::updateOrCreate(
                ['slug' => $category['slug']],
                array_merge($category, ['is_active' => true])
            );
        }
    }

    /**
     * @param  array<string, Unit>  $units
     * @param  array<string, User>  $users
     */
    private function seedAssetCategoriesAndLocations(array $units, array $users): void
    {
        $assetCategories = [
            ['code' => 'KOM', 'name' => 'Komputer, Laptop, Server & Perangkat IT', 'useful_life_years' => 4, 'maintenance_interval_days' => 180, 'is_it_asset' => true],
            ['code' => 'ELK', 'name' => 'Elektronik & Audio Visual (Proyektor, TV, Kamera)', 'useful_life_years' => 5, 'maintenance_interval_days' => 180, 'is_it_asset' => true],
            ['code' => 'AC', 'name' => 'Pendingin & Tata Udara (AC Split / Cassette)', 'useful_life_years' => 5, 'maintenance_interval_days' => 90, 'is_it_asset' => false],
            ['code' => 'FUR', 'name' => 'Furnitur & Mebel Kampus (Meja, Kursi, Lemari)', 'useful_life_years' => 8, 'maintenance_interval_days' => null, 'is_it_asset' => false],
            ['code' => 'LAB', 'name' => 'Alat Peraga & Peralatan Laboratorium Prodi', 'useful_life_years' => 5, 'maintenance_interval_days' => 180, 'is_it_asset' => false],
            ['code' => 'KND', 'name' => 'Kendaraan Dinas & Operasional Kampus', 'useful_life_years' => 8, 'maintenance_interval_days' => 90, 'is_it_asset' => false],
            ['code' => 'GDG', 'name' => 'Mesin & Instalasi Gedung (Genset, Pompa, APAR)', 'useful_life_years' => 10, 'maintenance_interval_days' => 180, 'is_it_asset' => false],
        ];

        foreach ($assetCategories as $cat) {
            AssetCategory::updateOrCreate(
                ['code' => $cat['code']],
                array_merge($cat, ['is_active' => true])
            );
        }

        $locations = [
            [
                'code' => 'GD-A-LAB01',
                'name' => 'Laboratorium Pemrograman 1',
                'building_name' => 'Gedung A Kampus Utama',
                'floor' => 'Lantai 2',
                'unit_id' => $units['TI']->id,
                'pic_user_id' => $users['kaprodi_ti']->id,
                'pic_name_snapshot' => $users['kaprodi_ti']->name,
                'capacity' => 40,
            ],
            [
                'code' => 'GD-A-LAB02',
                'name' => 'Laboratorium Jaringan & Server',
                'building_name' => 'Gedung A Kampus Utama',
                'floor' => 'Lantai 2',
                'unit_id' => $units['ICT']->id,
                'pic_user_id' => $users['kepala_ict']->id,
                'pic_name_snapshot' => $users['kepala_ict']->name,
                'capacity' => 35,
            ],
            [
                'code' => 'GD-A-R201',
                'name' => 'Ruang Dosen Fakultas Ilmu Komputer',
                'building_name' => 'Gedung A Kampus Utama',
                'floor' => 'Lantai 2',
                'unit_id' => $units['FIK']->id,
                'pic_user_id' => $users['dekan_fik']->id,
                'pic_name_snapshot' => $users['dekan_fik']->name,
                'capacity' => 25,
            ],
            [
                'code' => 'GD-B-R101',
                'name' => 'Ruang Dekanat & Dosen FEB',
                'building_name' => 'Gedung B',
                'floor' => 'Lantai 1',
                'unit_id' => $units['FEB']->id,
                'pic_user_id' => $users['dekan_feb']->id,
                'pic_name_snapshot' => $users['dekan_feb']->name,
                'capacity' => 25,
            ],
            [
                'code' => 'GD-REK-R101',
                'name' => 'Ruang Sidang Utama Rektorat',
                'building_name' => 'Gedung Rektorat',
                'floor' => 'Lantai 1',
                'unit_id' => $units['REKTORAT']->id,
                'pic_user_id' => $users['rektor']->id,
                'pic_name_snapshot' => $users['rektor']->name,
                'capacity' => 50,
            ],
            [
                'code' => 'GD-BAUK-GDG',
                'name' => 'Gudang Sentral Inventaris BAUK',
                'building_name' => 'Gedung B',
                'floor' => 'Lantai 1',
                'unit_id' => $units['BAUK']->id,
                'pic_user_id' => $users['kepala_bauk']->id,
                'pic_name_snapshot' => $users['kepala_bauk']->name,
                'capacity' => 100,
            ],
        ];

        foreach ($locations as $loc) {
            AssetLocation::updateOrCreate(
                ['code' => $loc['code']],
                array_merge($loc, ['is_active' => true])
            );
        }
    }

    private function seedSettings(): void
    {
        $settings = [
            ['key' => 'institution_name', 'value' => 'Universitas Bina Insan', 'group' => 'identity', 'display_name' => 'Nama Resmi Perguruan Tinggi', 'type' => 'string', 'is_public' => true],
            ['key' => 'institution_code', 'value' => 'UNIVBI', 'group' => 'identity', 'display_name' => 'Kode Singkatan Naskah Dinas', 'type' => 'string', 'is_public' => true],
            ['key' => 'campus_address', 'value' => 'Jl. HM. Soeharto KM. 13, Kelurahan Lubuk Kupang, Kota Lubuklinggau, Sumatera Selatan', 'group' => 'identity', 'display_name' => 'Alamat Lengkap Kampus', 'type' => 'text', 'is_public' => true],
            ['key' => 'default_number_format', 'value' => '{seq}/UNIVBI.{unit}/{cat}/{roman_month}/{year}', 'group' => 'stamping', 'display_name' => 'Pola Format Penomoran Surat Default', 'type' => 'string', 'is_public' => false],
        ];

        foreach ($settings as $setting) {
            Setting::updateOrCreate(['key' => $setting['key']], $setting);
        }
    }
}
