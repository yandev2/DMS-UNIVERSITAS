<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('activity_log', function (Blueprint $table): void {
            $table->index(['log_name', 'created_at'], 'idx_activity_log_logname_created');
            $table->index(['event', 'created_at'], 'idx_activity_log_event_created');
            $table->index(['log_name', 'event', 'created_at'], 'idx_activity_log_logname_event_created');
        });

        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            try {
                DB::statement('CREATE EXTENSION IF NOT EXISTS pg_trgm');
                DB::statement('CREATE INDEX IF NOT EXISTS idx_activity_log_description_trgm ON activity_log USING gin (description gin_trgm_ops)');
            } catch (Throwable $e) {
                Log::warning('Pembuatan index trigram activity_log.description dilewati: '.$e->getMessage());
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::statement('DROP INDEX IF EXISTS idx_activity_log_description_trgm');
        }

        Schema::table('activity_log', function (Blueprint $table): void {
            $table->dropIndex('idx_activity_log_logname_event_created');
            $table->dropIndex('idx_activity_log_event_created');
            $table->dropIndex('idx_activity_log_logname_created');
        });
    }
};
