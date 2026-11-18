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
        Schema::create('activity_log', function (Blueprint $table): void {
            $table->id();
            $table->string('log_name', 255)->nullable();
            $table->text('description');
            $table->nullableMorphs('subject', 'idx_activity_log_subject');
            $table->string('event', 100)->nullable();
            $table->nullableMorphs('causer', 'idx_activity_log_causer');
            $table->jsonb('attribute_changes')->nullable();
            $table->jsonb('properties')->nullable();
            $table->string('ip_address', 45)->nullable()->index('idx_activity_log_ip');
            $table->text('user_agent')->nullable();
            $table->uuid('batch_uuid')->nullable();
            $table->timestamps();

            $table->index(['subject_type', 'subject_id', 'created_at'], 'idx_activity_log_subject_timeline');
            $table->index(['causer_type', 'causer_id', 'created_at'], 'idx_activity_log_causer_timeline');
            $table->index(['log_name', 'event'], 'idx_activity_log_name_event');
            $table->index('created_at', 'idx_activity_log_created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('activity_log');
    }
};
