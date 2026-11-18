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
        Schema::create('units', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 150);
            $table->string('code', 50)->unique('idx_units_code_unique');
            $table->string('type', 30)->default('prodi');
            $table->string('degree_level', 20)->nullable();
            $table->foreignId('parent_id')->nullable()->constrained('units')->nullOnDelete();
            $table->unsignedBigInteger('head_user_id')->nullable()->index('idx_units_head_user_id');

            // Atribut Akreditasi Program Studi / Institusi
            $table->string('accreditation_grade', 50)->nullable();
            $table->string('accreditation_body', 100)->nullable();
            $table->string('accreditation_sk_number', 150)->nullable();
            $table->date('accreditation_expired_date')->nullable()->index('idx_units_accred_expiry');
            $table->unsignedBigInteger('accreditation_document_id')->nullable()->index('idx_units_accred_doc_id');

            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['type', 'is_active'], 'idx_units_type_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('units');
    }
};
