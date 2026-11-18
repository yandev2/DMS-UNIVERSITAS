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
        Schema::create('document_categories', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 100);
            $table->string('slug', 100)->unique('idx_doc_categories_slug_unique');
            $table->string('code', 50);
            $table->string('number_format', 255)->default('{seq}/UNIVBI.{unit}/{cat}/{roman_month}/{year}');
            $table->boolean('requires_approval')->default(false);
            $table->boolean('allow_manual_number')->default(true);
            $table->boolean('has_expiry')->default(false);
            $table->string('default_visibility', 30)->default('internal');
            $table->string('stamp_position', 30)->default('top_right');
            $table->integer('stamp_x_offset')->default(0);
            $table->integer('stamp_y_offset')->default(0);
            $table->jsonb('allowed_unit_types')->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true)->index('idx_doc_categories_is_active');
            $table->timestamps();

            $table->index(['code', 'is_active'], 'idx_doc_categories_code_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('document_categories');
    }
};
