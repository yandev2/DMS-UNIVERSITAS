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
        Schema::create('accreditation_criteria', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 20)->unique('idx_accred_criteria_code_unique');
            $table->string('name', 255);
            $table->string('instrument_source', 100)->default('BAN-PT / LAM');
            $table->text('description')->nullable();
            $table->integer('order_index')->default(1);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['order_index', 'is_active'], 'idx_accred_criteria_order');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('accreditation_criteria');
    }
};
