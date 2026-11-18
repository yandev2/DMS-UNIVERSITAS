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
        Schema::create('category_approval_flows', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('document_category_id')->constrained('document_categories')->cascadeOnDelete();
            $table->integer('step_order');
            $table->string('approver_role', 100)->index('idx_cat_flow_approver_role');
            $table->string('step_label', 100)->nullable();
            $table->boolean('is_mandatory')->default(true);
            $table->timestamps();

            $table->unique(['document_category_id', 'step_order'], 'idx_cat_flow_step_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('category_approval_flows');
    }
};
