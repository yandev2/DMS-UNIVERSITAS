<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Data\System\SystemHealthSnapshotData;
use App\Filament\Pages\SystemHealthPage;
use App\Services\System\SystemHealthService;
use Filament\Widgets\Widget;
use Livewire\Attributes\On;

class SystemExtensionsWidget extends Widget
{
    protected static bool $isDiscovered = false;

    protected int|string|array $columnSpan = 'full';

    protected string $view = 'filament.widgets.system-health.extensions-widget';

    public static function canView(): bool
    {
        return SystemHealthPage::canAccess();
    }

    #[On('system-health-refreshed')]
    public function refreshSnapshot(): void
    {
        // Trigger re-render with fresh snapshot.
    }

    public function getSnapshot(): SystemHealthSnapshotData
    {
        return app(SystemHealthService::class)->check();
    }

    /**
     * @return list<array{name: string, loaded: bool, purpose: string}>
     */
    public function getExtensionsList(): array
    {
        $descriptions = [
            'pdo_pgsql' => 'Driver PDO PostgreSQL',
            'pdo_mysql' => 'Driver PDO MySQL / MariaDB',
            'pdo_sqlite' => 'Driver PDO SQLite (Testing/Lokal)',
            'redis' => 'Cache & Antrean Redis In-Memory',
            'gd' => 'Manipulasi Gambar & Thumbnail',
            'intl' => 'Lokalisasi Angka, Mata Uang & Tanggal',
            'mbstring' => 'Pengolahan String Multi-Byte UTF-8',
            'openssl' => 'Enkripsi TLS, Token & Keamanan',
            'zip' => 'Ekspor Arsip ZIP & Backup Paket',
            'fileinfo' => 'Deteksi MIME Type Lampiran Berkas',
            'curl' => 'Koneksi HTTP API & Webhook',
            'xml' => 'Parser XML & Ekspor Dokumen',
            'bcmath' => 'Aritmatika Presisi Tinggi',
            'ctype' => 'Validasi Karakter Standar PHP',
        ];

        $extensions = [];

        foreach ($this->getSnapshot()->requiredExtensions as $name => $loaded) {
            $extensions[] = [
                'name' => $name,
                'loaded' => $loaded,
                'purpose' => $descriptions[$name] ?? 'Ekstensi Runtime PHP',
            ];
        }

        return $extensions;
    }
}
