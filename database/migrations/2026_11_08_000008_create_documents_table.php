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
        Schema::create('documents', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique('idx_documents_uuid');
            $table->string('title', 255);
            $table->string('slug', 255);
            $table->foreignId('unit_id')->constrained('units')->cascadeOnDelete();
            $table->foreignId('document_category_id')->constrained('document_categories')->cascadeOnDelete();
            $table->foreignId('academic_year_id')->nullable()->constrained('academic_years')->nullOnDelete();
            $table->foreignId('uploaded_by')->constrained('users')->restrictOnDelete();

            // Penomoran Dokumen (Otomatis vs Manual Backdate / Surat Masuk Eksternal)
            $table->boolean('is_manual_number')->default(false);
            $table->integer('sequence_year')->nullable();
            $table->integer('sequence_number')->nullable();
            $table->string('document_number', 150)->nullable()->unique('idx_documents_doc_number');

            // Tata Kelola Akses, Alur Approval & Snapshot Pengesahan
            $table->string('visibility', 30)->default('internal');
            $table->string('status', 30)->default('draft');
            $table->integer('current_step')->default(0);
            $table->integer('total_steps')->default(0);
            $table->foreignId('final_approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->string('signer_name_snapshot', 255)->nullable();
            $table->string('signer_position_snapshot', 150)->nullable();

            // Manajemen Borang Akreditasi, Masa Berlaku & Rantai Pembaruan Dokumen
            $table->boolean('is_borang')->default(false);
            $table->boolean('has_expiry')->default(false);
            $table->date('issued_date')->index('idx_documents_issued_date');
            $table->date('expired_date')->nullable();
            $table->jsonb('reminder_intervals')->nullable();
            $table->jsonb('reminder_recipients')->nullable();
            $table->foreignId('replaces_document_id')->nullable()->constrained('documents')->nullOnDelete();

            // Metadata Ekstensibel, Deskripsi & Statistik Akses
            $table->jsonb('custom_metadata')->nullable();
            $table->text('description')->nullable();
            $table->unsignedInteger('views_count')->default(0);
            $table->unsignedInteger('downloads_count')->default(0);

            $table->timestamps();
            $table->softDeletes();

            // Composite B-Tree Indexes sesuai schema.dbml
            $table->index(['unit_id', 'status'], 'idx_documents_unit_status');
            $table->index(['status', 'visibility'], 'idx_documents_status_visibility');
            $table->index(['is_borang', 'status', 'unit_id', 'academic_year_id'], 'idx_documents_borang_lookup');
            $table->index(['has_expiry', 'expired_date', 'status'], 'idx_documents_expiry_cron');
            $table->index(['document_category_id', 'unit_id', 'sequence_year', 'sequence_number'], 'idx_documents_seq_lock');
            $table->index(['uploaded_by', 'status'], 'idx_documents_uploader_status');
            $table->index('created_at', 'idx_documents_created_at');
            $table->index('deleted_at', 'idx_documents_deleted_at');
        });

        Schema::table('units', function (Blueprint $table): void {
            $table->foreign('accreditation_document_id', 'fk_units_accreditation_document_id')
                ->references('id')
                ->on('documents')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('units', function (Blueprint $table): void {
            $table->dropForeign('fk_units_accreditation_document_id');
        });

        Schema::dropIfExists('documents');
    }
};
