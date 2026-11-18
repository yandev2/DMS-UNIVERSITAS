<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Menambahkan index selektif non-redundan untuk skala > 1 juta dokumen:
     * - 5 B-Tree composite index yang belum memiliki leading-column sort index.
     * - Partial index khusus antrean pending (nol overhead bagi arsip sah/pasif) & GIN trigram pada PostgreSQL.
     */
    public function up(): void
    {
        Schema::table('documents', function (Blueprint $table): void {
            $table->index(['status', 'created_at'], 'idx_documents_status_created');
            $table->index(['visibility', 'created_at'], 'idx_documents_vis_created');
            $table->index(['document_category_id', 'created_at'], 'idx_documents_cat_created');
            $table->index(['academic_year_id', 'created_at'], 'idx_documents_year_created');
            $table->index('expired_date', 'idx_documents_expired_date');
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement(
                "CREATE INDEX IF NOT EXISTS idx_documents_pending_queue_partial ON documents (current_step, unit_id) WHERE status = 'pending' AND deleted_at IS NULL"
            );

            try {
                DB::statement('CREATE EXTENSION IF NOT EXISTS pg_trgm');

                $hasTrgm = (bool) DB::selectOne("SELECT EXISTS (SELECT 1 FROM pg_extension WHERE extname = 'pg_trgm') AS installed")?->installed;
                if ($hasTrgm) {
                    DB::statement('CREATE INDEX IF NOT EXISTS idx_documents_title_trgm ON documents USING gin (title gin_trgm_ops)');
                    DB::statement('CREATE INDEX IF NOT EXISTS idx_documents_number_trgm ON documents USING gin (document_number gin_trgm_ops)');
                }
            } catch (Throwable $e) {
                Log::warning('Pembuatan ekstensi/index pg_trgm pada PostgreSQL dilewati: '.$e->getMessage());
            }
        }
    }

    /**
     * Menghapus seluruh index skalabilitas yang ditambahkan secara simetris.
     */
    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('DROP INDEX IF EXISTS idx_documents_title_trgm');
            DB::statement('DROP INDEX IF EXISTS idx_documents_number_trgm');
            DB::statement('DROP INDEX IF EXISTS idx_documents_pending_queue_partial');
        }

        Schema::table('documents', function (Blueprint $table): void {
            $table->dropIndex('idx_documents_status_created');
            $table->dropIndex('idx_documents_vis_created');
            $table->dropIndex('idx_documents_cat_created');
            $table->dropIndex('idx_documents_year_created');
            $table->dropIndex('idx_documents_expired_date');
        });
    }
};
