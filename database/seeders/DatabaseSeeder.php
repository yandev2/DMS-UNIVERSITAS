<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // 1. Master Data Utama (Aman dijalankan di Local maupun Production Server Kampus)
        $this->call(MasterDataSeeder::class);
        $this->call(LandingPageSettingSeeder::class);

        // 2. ⚠️ KHUSUS TESTING / ENVIRONMENT LOCAL:
        // DummyTestingSeeder (beserta auto-generate file fisik .pdf, .docx, dan .jpg di storage/)
        // HANYA dijalankan apabila APP_ENV bernilai 'local' atau 'testing'.
        if (app()->environment(['local', 'testing'])) {
            $this->call(DummyTestingSeeder::class);
            $this->call(LetterSeeder::class);
        }
    }
}
