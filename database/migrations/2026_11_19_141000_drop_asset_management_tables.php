<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Menghapus seluruh tabel & indeks manajemen aset karena aset telah dipindahkan ke sistem terpisah.
     */
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::statement('DROP INDEX IF EXISTS idx_assets_serial_trgm');
            DB::statement('DROP INDEX IF EXISTS idx_assets_code_trgm');
            DB::statement('DROP INDEX IF EXISTS idx_assets_name_trgm');
        }

        Schema::dropIfExists('asset_maintenances');
        Schema::dropIfExists('asset_movements');
        Schema::dropIfExists('assets');
        Schema::dropIfExists('asset_locations');
        Schema::dropIfExists('asset_categories');

        if (Schema::hasTable('permissions')) {
            DB::table('permissions')->where('name', 'like', 'asset.%')->delete();
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Modul manajemen aset telah dipindahkan permanen ke sistem tersendiri.
    }
};
