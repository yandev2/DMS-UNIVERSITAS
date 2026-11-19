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
        // Mengisi struktur resmi UNIVBI (10 role, 25 unit, 57 akun dengan 1 super_admin)
        // beserta 11 kategori dokumen umum dan alur persetujuan standarnya.
        $this->call([
            CampusStructureSeeder::class,
            DocumentCategorySeeder::class,
        ]);
    }
}
