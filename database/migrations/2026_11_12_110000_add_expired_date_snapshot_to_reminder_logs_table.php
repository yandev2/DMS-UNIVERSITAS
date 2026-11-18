<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('reminder_logs', function (Blueprint $table): void {
            $table->date('expired_date_snapshot')->nullable()->after('sent_date');
            $table->dropUnique('idx_reminder_logs_dedup_unique');
            $table->unique(
                ['document_id', 'recipient_email', 'interval_type', 'expired_date_snapshot'],
                'idx_reminder_logs_dedup_snapshot'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('reminder_logs', function (Blueprint $table): void {
            $table->dropUnique('idx_reminder_logs_dedup_snapshot');
            $table->unique(
                ['document_id', 'recipient_email', 'interval_type', 'sent_date'],
                'idx_reminder_logs_dedup_unique'
            );
            $table->dropColumn('expired_date_snapshot');
        });
    }
};
