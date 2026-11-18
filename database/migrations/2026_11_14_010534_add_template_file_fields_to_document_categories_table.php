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
        Schema::table('document_categories', function (Blueprint $table): void {
            $table->string('template_file_path', 255)->nullable()->after('description');
            $table->string('template_file_name', 255)->nullable()->after('template_file_path');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('document_categories', function (Blueprint $table): void {
            $table->dropColumn(['template_file_path', 'template_file_name']);
        });
    }
};
