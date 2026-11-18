<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('CREATE EXTENSION IF NOT EXISTS pg_trgm');

        DB::statement('CREATE INDEX IF NOT EXISTS idx_documents_title_trgm ON documents USING gin (title gin_trgm_ops)');
        DB::statement('CREATE INDEX IF NOT EXISTS idx_documents_number_trgm ON documents USING gin (document_number gin_trgm_ops)');
        DB::statement('CREATE INDEX IF NOT EXISTS idx_posts_title_trgm ON posts USING gin (title gin_trgm_ops)');
        DB::statement('CREATE INDEX IF NOT EXISTS idx_assets_name_trgm ON assets USING gin (name gin_trgm_ops)');
        DB::statement('CREATE INDEX IF NOT EXISTS idx_assets_code_trgm ON assets USING gin (asset_code gin_trgm_ops)');
        DB::statement('CREATE INDEX IF NOT EXISTS idx_assets_serial_trgm ON assets USING gin (serial_number gin_trgm_ops)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('DROP INDEX IF EXISTS idx_assets_serial_trgm');
        DB::statement('DROP INDEX IF EXISTS idx_assets_code_trgm');
        DB::statement('DROP INDEX IF EXISTS idx_assets_name_trgm');
        DB::statement('DROP INDEX IF EXISTS idx_posts_title_trgm');
        DB::statement('DROP INDEX IF EXISTS idx_documents_number_trgm');
        DB::statement('DROP INDEX IF EXISTS idx_documents_title_trgm');
    }
};
