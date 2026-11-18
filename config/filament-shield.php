<?php

declare(strict_types=1);

use BezhanSalleh\FilamentShield\Resources\Roles\RoleResource;
use Filament\Pages\Dashboard;
use Filament\Widgets\AccountWidget;
use Filament\Widgets\FilamentInfoWidget;

return [

    /*
    |--------------------------------------------------------------------------
    | Shield Resource
    |--------------------------------------------------------------------------
    */

    'shield_resource' => [
        'slug' => 'shield/roles',
        'show_model_path' => true,
        'cluster' => null,
        'tabs' => [
            'pages' => true,
            'widgets' => true,
            'resources' => true,
            'custom_permissions' => true,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Multi-Tenancy
    |--------------------------------------------------------------------------
    */

    'tenant_model' => null,

    /*
    |--------------------------------------------------------------------------
    | User Model
    |--------------------------------------------------------------------------
    */

    'auth_provider_model' => 'App\\Models\\User',

    /*
    |--------------------------------------------------------------------------
    | Super Admin
    |--------------------------------------------------------------------------
    */

    'super_admin' => [
        'enabled' => true,
        'name' => 'super_admin',
        'define_via_gate' => false,
        'intercept_gate' => 'before',
    ],

    /*
    |--------------------------------------------------------------------------
    | Panel User
    |--------------------------------------------------------------------------
    */

    'panel_user' => [
        'enabled' => false,
        'name' => 'panel_user',
    ],

    /*
    |--------------------------------------------------------------------------
    | Permission Builder
    |--------------------------------------------------------------------------
    */

    'permissions' => [
        'separator' => ':',
        'case' => 'lower_snake',
        'generate' => true,
        'format_custom_permission_keys' => false,
    ],

    /*
    |--------------------------------------------------------------------------
    | Policies
    |--------------------------------------------------------------------------
    */

    'policies' => [
        'path' => app_path('Policies'),
        'merge' => true,
        'generate' => true,
        'methods' => [
            'viewAny',
            'view',
            'create',
            'update',
            'delete',
            'deleteAny',
            'restore',
            'forceDelete',
            'forceDeleteAny',
            'restoreAny',
            'replicate',
            'reorder',
        ],
        'single_parameter_methods' => [
            'viewAny',
            'create',
            'deleteAny',
            'forceDeleteAny',
            'restoreAny',
            'reorder',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Localization
    |--------------------------------------------------------------------------
    */

    'localization' => [
        'enabled' => false,
        'key' => 'filament-shield::filament-shield.resource_permission_prefixes_labels',
    ],

    /*
    |--------------------------------------------------------------------------
    | Resources
    |--------------------------------------------------------------------------
    */

    'resources' => [
        'subject' => 'model',
        'manage' => [
            RoleResource::class => [
                'viewAny',
                'view',
                'create',
                'update',
                'delete',
            ],
        ],
        'exclude' => [
            //
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Pages
    |--------------------------------------------------------------------------
    */

    'pages' => [
        'subject' => 'class',
        'prefix' => 'view',
        'exclude' => [
            Dashboard::class,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Widgets
    |--------------------------------------------------------------------------
    */

    'widgets' => [
        'subject' => 'class',
        'prefix' => 'view',
        'exclude' => [
            AccountWidget::class,
            FilamentInfoWidget::class,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Custom Permissions
    |--------------------------------------------------------------------------
    */

    'custom_permissions' => [
        'document.view_confidential' => 'Dokumen: Lihat Dokumen Confidential (Rahasia Pimpinan)',
        'document.download_private' => 'Dokumen: Unduh Berkas Fisik Internal (Private Storage)',
        'document.approve' => 'Dokumen: Persetujuan & Revisi Berjenjang (Multi-Level Approval)',
        'document.manual_numbering' => 'Dokumen: Input Nomor Surat Manual (Arsip Lama/Backdate)',
        'document.bypass_unit_scope' => 'Dokumen: Akses Lintas Seluruh Fakultas & Unit (Global Scope)',
        'letter.manage_humas' => 'Tata Persuratan: Registrasi Surat Masuk & Verifikasi Penomoran Surat Keluar (Tim Humas)',
        'archive.bulk_export_zip' => 'Arsip: Ekspor Massal Berkas ZIP Latar Belakang (Super Admin)',
        'borang.export_excel' => 'Borang Akreditasi: Ekspor Rekapitulasi ke Excel (.xlsx)',
        'borang.download_bulk_zip' => 'Borang Akreditasi: Unduh Paket Bukti Fisik (.ZIP)',
        'borang.configure_assessor' => 'Borang Akreditasi: Konfigurasi Masa Asesmen & Portal Asesor',
        'reminder.manage_recipients' => 'Pengingat: Kelola Daftar Email Penerima Notifikasi Kedaluwarsa',
        'post.moderate_humas' => 'Portal Berita: Moderasi & Publikasi Artikel (Tim Humas)',
        'post.download_zip' => 'Portal Berita: Unduh Dokumentasi Kegiatan (.ZIP)',
        'cms.manage_landing_page' => 'Portal Web: Kelola Pengaturan Beranda & Konten CMS',
        'system.backup_manage' => 'Sistem Backup: Kelola & Buat Paket Backup (Database & Storage)',
        'system.backup_restore' => 'Sistem Backup: Pulihkan (Restore) Database & Berkas Storage',
        'asset.access_panel' => 'Manajemen Aset: Akses Panel Inventaris Aset Tetap (/assets)',
        'asset.bypass_unit_scope' => 'Manajemen Aset: Pantau Aset Lintas Seluruh Unit Kampus',
        'asset.create' => 'Manajemen Aset: Registrasi Aset Tetap Baru',
        'asset.print_qr' => 'Manajemen Aset: Cetak Stiker QR Code Aset',
        'asset.transfer_unit' => 'Manajemen Aset: Mutasi Aset Antar Unit/Prodi',
        'asset.relocate_room' => 'Manajemen Aset: Pindah Ruangan Internal Unit',
        'asset.report_issue' => 'Manajemen Aset: Lapor Kerusakan / Ajukan Servis',
        'asset.process_maintenance' => 'Manajemen Aset: Proses & Selesaikan Perbaikan Teknis',
        'asset.stock_opname' => 'Manajemen Aset: Verifikasi Cek Fisik (Stock Opname)',
        'asset.dispose' => 'Manajemen Aset: Penghapusan / Afkir Aset (SK Rektor)',
        'asset.export_borang_k5' => 'Manajemen Aset: Ekspor Tabel 5.b Sarana Prasarana Borang K5',
    ],

    /*
    |--------------------------------------------------------------------------
    | Entity Discovery
    |--------------------------------------------------------------------------
    */

    'discovery' => [
        'discover_all_resources' => false,
        'discover_all_widgets' => false,
        'discover_all_pages' => false,
    ],

    /*
    |--------------------------------------------------------------------------
    | Role Policy
    |--------------------------------------------------------------------------
    */

    'register_role_policy' => true,

];
