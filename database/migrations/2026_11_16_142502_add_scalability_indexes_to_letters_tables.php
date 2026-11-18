<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Menambahkan index selektif anti-bloat untuk skala > 1 juta baris pada modul persuratan:
     * - B-Tree index komposit untuk defaultSort('created_at', 'desc'), filter unit/status, & scope akses user.
     * - Covering composite B-Tree index pada incoming_letter_recipients untuk antrean giliran baca user.
     * - Partial index PostgreSQL khusus antrean aktif (surat selesai/terhapus nol overhead) & pg_trgm GIN.
     */
    public function up(): void
    {
        Schema::table('incoming_letters', function (Blueprint $table): void {
            $table->index('created_at', 'idx_incoming_created_at');
            $table->index(['status', 'created_at'], 'idx_incoming_status_created');
            $table->index(['unit_id', 'created_at'], 'idx_incoming_unit_created');
            $table->index(['registered_by', 'created_at'], 'idx_incoming_reg_created');
        });

        Schema::table('incoming_letter_recipients', function (Blueprint $table): void {
            $table->index(
                ['recipient_user_id', 'status', 'incoming_letter_id', 'step_order'],
                'idx_inc_recip_turn_lookup'
            );
        });

        Schema::table('outgoing_letters', function (Blueprint $table): void {
            $table->index('created_at', 'idx_outgoing_created_at');
            $table->index(['status', 'created_at'], 'idx_outgoing_status_created');
            $table->index(['unit_id', 'created_at'], 'idx_outgoing_unit_created');
            $table->index(['created_by', 'created_at'], 'idx_outgoing_creator_created');
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement(
                "CREATE INDEX IF NOT EXISTS idx_incoming_active_queue_partial ON incoming_letters (current_step, id) WHERE status = 'in_progress' AND deleted_at IS NULL"
            );

            DB::statement(
                "CREATE INDEX IF NOT EXISTS idx_outgoing_active_queue_partial ON outgoing_letters (created_by, unit_id) WHERE status IN ('pending_humas', 'revision') AND deleted_at IS NULL"
            );

            try {
                DB::statement('CREATE EXTENSION IF NOT EXISTS pg_trgm');

                $hasTrgm = (bool) DB::selectOne("SELECT EXISTS (SELECT 1 FROM pg_extension WHERE extname = 'pg_trgm') AS installed")?->installed;
                if ($hasTrgm) {
                    DB::statement('CREATE INDEX IF NOT EXISTS idx_incoming_agenda_trgm ON incoming_letters USING gin (agenda_number gin_trgm_ops)');
                    DB::statement('CREATE INDEX IF NOT EXISTS idx_incoming_sender_trgm ON incoming_letters USING gin (sender_name gin_trgm_ops)');
                    DB::statement('CREATE INDEX IF NOT EXISTS idx_incoming_subject_trgm ON incoming_letters USING gin (subject gin_trgm_ops)');

                    DB::statement('CREATE INDEX IF NOT EXISTS idx_outgoing_number_trgm ON outgoing_letters USING gin (letter_number gin_trgm_ops)');
                    DB::statement('CREATE INDEX IF NOT EXISTS idx_outgoing_subject_trgm ON outgoing_letters USING gin (subject gin_trgm_ops)');
                }
            } catch (Throwable $e) {
                Log::warning('Pembuatan ekstensi/index pg_trgm pada PostgreSQL untuk tabel surat dilewati: '.$e->getMessage());
            }
        }
    }

    /**
     * Menghapus seluruh index skalabilitas surat secara simetris.
     */
    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('DROP INDEX IF EXISTS idx_incoming_agenda_trgm');
            DB::statement('DROP INDEX IF EXISTS idx_incoming_sender_trgm');
            DB::statement('DROP INDEX IF EXISTS idx_incoming_subject_trgm');
            DB::statement('DROP INDEX IF EXISTS idx_incoming_active_queue_partial');

            DB::statement('DROP INDEX IF EXISTS idx_outgoing_number_trgm');
            DB::statement('DROP INDEX IF EXISTS idx_outgoing_subject_trgm');
            DB::statement('DROP INDEX IF EXISTS idx_outgoing_active_queue_partial');
        }

        Schema::table('outgoing_letters', function (Blueprint $table): void {
            $table->dropIndex('idx_outgoing_created_at');
            $table->dropIndex('idx_outgoing_status_created');
            $table->dropIndex('idx_outgoing_unit_created');
            $table->dropIndex('idx_outgoing_creator_created');
        });

        Schema::table('incoming_letter_recipients', function (Blueprint $table): void {
            $table->dropIndex('idx_inc_recip_turn_lookup');
        });

        Schema::table('incoming_letters', function (Blueprint $table): void {
            $table->dropIndex('idx_incoming_created_at');
            $table->dropIndex('idx_incoming_status_created');
            $table->dropIndex('idx_incoming_unit_created');
            $table->dropIndex('idx_incoming_reg_created');
        });
    }
};
