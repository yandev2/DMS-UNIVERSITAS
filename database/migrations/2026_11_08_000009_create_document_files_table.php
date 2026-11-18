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
        Schema::create('document_files', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('document_id')->constrained('documents')->cascadeOnDelete();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('is_primary')->default(true);
            $table->string('label', 150)->nullable();
            $table->integer('order_index')->default(1);
            $table->integer('version')->default(1);

            // Penamaan Otomatis & Penyimpanan Dual-File (Draft vs Stamped Official)
            $table->string('original_file_name', 255);
            $table->string('standardized_file_name', 255);
            $table->string('original_file_path', 500);
            $table->string('stamped_file_path', 500)->nullable();
            $table->string('disk', 20)->default('private')->index('idx_doc_files_disk');

            // Atribut Teknis & Validasi Integritas Berkas
            $table->string('mime_type', 100);
            $table->string('file_extension', 20);
            $table->unsignedBigInteger('file_size');
            $table->integer('page_count')->nullable();
            $table->string('file_hash_sha256', 64)->nullable()->index('idx_doc_files_hash');
            $table->boolean('is_active')->default(true);

            // Persiapan Backup & Sinkronisasi Cloud (Google Drive Backup)
            $table->string('google_drive_id', 255)->nullable()->index('idx_doc_files_gdrive_id');
            $table->string('google_drive_url', 500)->nullable();
            $table->boolean('is_synced')->default(false);
            $table->timestamp('synced_at')->nullable();

            $table->timestamps();

            $table->index(['document_id', 'is_active', 'is_primary'], 'idx_doc_files_doc_active_primary');
            $table->index(['document_id', 'order_index'], 'idx_doc_files_doc_order');
            $table->index(['document_id', 'version'], 'idx_doc_files_version');
            $table->index(['is_synced', 'is_active'], 'idx_doc_files_gdrive_sync');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('document_files');
    }
};
