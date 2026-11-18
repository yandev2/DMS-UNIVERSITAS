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
        Schema::create('criterion_document', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('document_id')->constrained('documents')->cascadeOnDelete();
            $table->foreignId('accreditation_criterion_id')->constrained('accreditation_criteria')->cascadeOnDelete();
            $table->string('indicator_code', 50)->nullable();
            $table->integer('order_index')->default(1);
            $table->string('note', 255)->nullable();
            $table->timestamps();

            $table->unique(['document_id', 'accreditation_criterion_id'], 'idx_crit_doc_unique');
            $table->index(['accreditation_criterion_id', 'order_index', 'document_id'], 'idx_crit_doc_reverse');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('criterion_document');
    }
};
