# DMS-UNIVERSITAS (Archiver-Sistem)

Sistem Manajemen Dokumen Terpadu (*Document Management System*), E-Arsip Naskah Dinas, Bank Data Borang Akreditasi, Tata Persuratan Dinas, dan Portal Warta Publik Universitas.

## Teknologi Utama
- **Backend:** PHP 8.3+, Laravel 13 (`^13.17`), Filament (`^5.11`)
- **Keamanan & Akses:** Spatie Laravel Permission (`^8.5`), Filament Shield (`4.3`), Spatie Activitylog (`^5.1`)
- **Dokumen & Media:** `setasign/fpdi`, `setasign/fpdf`, `tecnickcom/tcpdf`, `phpoffice/phpword`, `phpoffice/phpspreadsheet`, `simplesoftwareio/simple-qrcode`, `intervention/image`
- **Frontend:** Tailwind CSS v4, Vite 8
- **Testing & Quality:** PHPUnit 12, Laravel Pint

## Modul Sistem (Top-Down Modular Architecture)
1. **Core, Auth, RBAC & Master Data** — Autentikasi, Filament Shield, Audit Log, Manajemen User, Unit/Prodi, dan Tahun Akademik.
2. **Manajemen Dokumen, Approval & Digital Stamping** — Kategori naskah, alur persetujuan bertingkat, penomoran otomatis, stempel QR pada PDF/DOCX, serta pengingat masa berlaku.
3. **Tata Persuratan Dinas** — Registrasi surat masuk berjenjang & pengajuan/penomoran surat keluar Humas.
4. **Akreditasi & Bank Data Borang** — Pengelolaan kriteria akreditasi multi-instrumen, portal akses asesor UUID, serta ekspor rekap Excel & ZIP.
5. **Portal Warta Publik & CMS Beranda** — Manajemen berita/kegiatan kampus, kompresi galeri foto, dan pengaturan landing page.
6. **Dashboard Eksekutif & System Backup** — Widget ringkasan dasbor dan fitur backup/restore sistem.

## Alur Branching & Kontribusi
Repository ini menerapkan alur **3-Tier Branching (`feat/*` → `dev` → `main`)**:
- `feat/*` / `chore/*` / `fix/*`: Branch kerja lokal dari `dev`.
- `dev`: Branch integrasi dan pengujian gabungan via Pull Request.
- `main`: Branch rilis stabil (*Production*).

## Instalasi Lokal
```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
npm install --ignore-scripts
npm run build
```

## Pengujian & Code Formatting
```bash
vendor/bin/pint --format agent
php artisan test --compact
```

## Lisensi
Dilisensikan di bawah [MIT License](LICENSE).
