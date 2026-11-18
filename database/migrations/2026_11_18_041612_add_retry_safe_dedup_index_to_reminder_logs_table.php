<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Memperbaiki index deduplikasi agar kegagalan pengiriman (is_success = false)
     * tidak memblokir percobaan ulang (retry) berikutnya, sambil tetap menjamin
     * keunikan pengiriman sukses (is_success = true) pada tingkat database.
     */
    public function up(): void
    {
        Schema::table('reminder_logs', function (Blueprint $table): void {
            $table->dropUnique('idx_reminder_logs_dedup_snapshot');
            $table->index(
                ['document_id', 'recipient_email', 'interval_type', 'expired_date_snapshot', 'is_success'],
                'idx_reminder_logs_lookup'
            );
        });

        $driver = DB::getDriverName();

        if ($driver === 'pgsql') {
            DB::statement(
                'CREATE UNIQUE INDEX IF NOT EXISTS idx_reminder_logs_dedup_success ON reminder_logs (document_id, recipient_email, interval_type, expired_date_snapshot) WHERE is_success = true'
            );
        } elseif ($driver === 'sqlite') {
            DB::statement(
                'CREATE UNIQUE INDEX IF NOT EXISTS idx_reminder_logs_dedup_success ON reminder_logs (document_id, recipient_email, interval_type, expired_date_snapshot) WHERE is_success = 1'
            );
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $driver = DB::getDriverName();

        if (in_array($driver, ['pgsql', 'sqlite'], true)) {
            DB::statement('DROP INDEX IF EXISTS idx_reminder_logs_dedup_success');
        }

        Schema::table('reminder_logs', function (Blueprint $table): void {
            $table->dropIndex('idx_reminder_logs_lookup');
            $table->unique(
                ['document_id', 'recipient_email', 'interval_type', 'expired_date_snapshot'],
                'idx_reminder_logs_dedup_snapshot'
            );
        });
    }
};
