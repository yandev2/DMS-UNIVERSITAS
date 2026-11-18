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
        Schema::table('document_files', function (Blueprint $table): void {
            $table->string('stamped_file_hash_sha256', 64)
                ->nullable()
                ->after('file_hash_sha256')
                ->index('idx_doc_files_stamped_hash');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('document_files', function (Blueprint $table): void {
            $table->dropIndex('idx_doc_files_stamped_hash');
            $table->dropColumn('stamped_file_hash_sha256');
        });
    }
};
