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
        Schema::create('reminder_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('document_id')->constrained('documents')->cascadeOnDelete();
            $table->foreignId('recipient_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('recipient_email', 255);
            $table->string('interval_type', 20);
            $table->date('sent_date')->index('idx_reminder_logs_sent_date');
            $table->timestamp('sent_at');
            $table->boolean('is_success')->default(true);
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->unique(
                ['document_id', 'recipient_email', 'interval_type', 'sent_date'],
                'idx_reminder_logs_dedup_unique'
            );
            $table->index(['document_id', 'interval_type'], 'idx_reminder_logs_doc_interval');
        });

        Schema::create('notifications', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('type', 255);
            $table->string('notifiable_type', 255)->default('App\\Models\\User');
            $table->unsignedBigInteger('notifiable_id');
            $table->jsonb('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['notifiable_type', 'notifiable_id', 'read_at'], 'idx_notifications_unread_lookup');
            $table->index('created_at', 'idx_notifications_created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notifications');
        Schema::dropIfExists('reminder_logs');
    }
};
