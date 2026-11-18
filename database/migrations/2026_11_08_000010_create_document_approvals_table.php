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
        Schema::create('document_approvals', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('document_id')->constrained('documents')->cascadeOnDelete();
            $table->integer('revision_round')->default(1);
            $table->integer('step_order');
            $table->string('approver_role', 100);
            $table->foreignId('target_unit_id')->nullable()->constrained('units')->nullOnDelete();
            $table->string('status', 30)->default('waiting');
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('approver_position_snapshot', 150)->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['document_id', 'revision_round', 'step_order'], 'idx_doc_approvals_timeline');
            $table->index(['status', 'approver_role', 'target_unit_id'], 'idx_doc_approvals_queue_widget');
            $table->index(['approved_by', 'status'], 'idx_doc_approvals_user_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('document_approvals');
    }
};
