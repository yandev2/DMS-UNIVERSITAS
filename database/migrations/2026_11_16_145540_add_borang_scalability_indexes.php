<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Menambahkan index anti-bloat khusus modul Borang Akreditasi:
     * - Cross-DB composite B-Tree index pada posts untuk halaman Dokumentasi Borang (defaultSort created_at DESC).
     * - PostgreSQL partial index hanya untuk baris is_borang = true (nol overhead bagi jutaan dokumen/berita reguler).
     */
    public function up(): void
    {
        Schema::table('posts', function (Blueprint $table): void {
            $table->index(['is_borang', 'created_at'], 'idx_posts_borang_created');
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement(
                "CREATE INDEX IF NOT EXISTS idx_documents_borang_portal_partial ON documents (unit_id, issued_date DESC, title) WHERE is_borang = true AND status = 'approved' AND deleted_at IS NULL"
            );

            DB::statement(
                'CREATE INDEX IF NOT EXISTS idx_posts_borang_active_partial ON posts (unit_id, created_at DESC) WHERE is_borang = true AND deleted_at IS NULL'
            );
        }
    }

    /**
     * Menghapus seluruh index skalabilitas Borang secara simetris.
     */
    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('DROP INDEX IF EXISTS idx_documents_borang_portal_partial');
            DB::statement('DROP INDEX IF EXISTS idx_posts_borang_active_partial');
        }

        Schema::table('posts', function (Blueprint $table): void {
            $table->dropIndex('idx_posts_borang_created');
        });
    }
};
