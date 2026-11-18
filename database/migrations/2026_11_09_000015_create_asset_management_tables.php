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
        Schema::create('asset_categories', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 20)->unique('idx_asset_categories_code_unique');
            $table->string('name', 150);
            $table->text('description')->nullable();
            $table->smallInteger('useful_life_years')->default(5);
            $table->smallInteger('maintenance_interval_days')->nullable();
            $table->boolean('is_it_asset')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['is_active', 'is_it_asset'], 'idx_asset_categories_active_it');
        });

        Schema::create('asset_locations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('unit_id')->constrained('units')->restrictOnDelete();
            $table->string('code', 30)->unique('idx_asset_locations_code_unique');
            $table->string('name', 150);
            $table->string('building_name', 100);
            $table->string('floor', 30)->nullable();
            $table->foreignId('pic_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('pic_name_snapshot', 150)->nullable();
            $table->smallInteger('capacity')->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['unit_id', 'is_active'], 'idx_asset_locations_unit_active');
            $table->index(['building_name', 'floor'], 'idx_asset_locations_building_floor');
        });

        Schema::create('assets', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique('idx_assets_uuid_unique');
            $table->uuid('batch_uuid')->nullable()->index('idx_assets_batch_uuid');
            $table->string('asset_code', 100)->unique('idx_assets_code_unique');
            $table->integer('sequence_number');
            $table->foreignId('category_id')->constrained('asset_categories')->restrictOnDelete();

            // Posisi & Kepemilikan Saat Ini (Denormalized Current State)
            $table->foreignId('initial_unit_id')->constrained('units')->restrictOnDelete();
            $table->foreignId('current_unit_id')->constrained('units')->restrictOnDelete();
            $table->foreignId('current_location_id')->nullable()->constrained('asset_locations')->nullOnDelete();
            $table->foreignId('assigned_to_user_id')->nullable()->constrained('users')->nullOnDelete();

            // Identitas & Spesifikasi Fisik Barang
            $table->string('name', 255);
            $table->string('brand', 100)->nullable();
            $table->string('model_name', 100)->nullable();
            $table->string('serial_number', 100)->nullable()->index('idx_assets_serial_number');
            $table->text('specifications')->nullable();

            // Status Operasional, Kondisi Fisik & Kepemilikan Borang K5
            $table->string('status', 30)->default('in_use');
            $table->string('condition', 30)->default('good');
            $table->string('ownership_status', 30)->default('owned');

            // Informasi Perolehan, Garansi & Tautan Dokumen E-Arsip (BAST / SK)
            $table->smallInteger('acquisition_year')->index('idx_assets_acquisition_year');
            $table->date('acquisition_date')->nullable();
            $table->decimal('acquisition_cost', 15, 2)->default(0);
            $table->string('funding_source', 100)->nullable();
            $table->string('vendor_name', 150)->nullable();
            $table->date('warranty_expired_date')->nullable();
            $table->date('next_maintenance_date')->nullable();
            $table->foreignId('bast_document_id')->nullable()->constrained('documents')->nullOnDelete();
            $table->foreignId('disposal_document_id')->nullable()->constrained('documents')->nullOnDelete();
            $table->timestamp('disposed_at')->nullable();

            // Dokumentasi Foto Fisik & Counter Cache Perawatan
            $table->string('photo_path', 500)->nullable();
            $table->string('thumbnail_path', 500)->nullable();
            $table->unsignedInteger('maintenances_count')->default(0);
            $table->decimal('total_maintenance_cost', 15, 2)->default(0);
            $table->timestamp('last_verified_at')->nullable();
            $table->foreignId('last_verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['current_unit_id', 'status', 'condition'], 'idx_assets_unit_status_cond');
            $table->index(['current_location_id', 'status'], 'idx_assets_location_status');
            $table->index(['category_id', 'status'], 'idx_assets_category_status');
            $table->unique(
                ['initial_unit_id', 'category_id', 'acquisition_year', 'sequence_number'],
                'idx_assets_seq_lock_unique'
            );
            $table->index(['status', 'next_maintenance_date'], 'idx_assets_preventive_maint');
            $table->index('deleted_at', 'idx_assets_deleted_at');
        });

        Schema::create('asset_movements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('asset_id')->constrained('assets')->cascadeOnDelete();
            $table->string('event_type', 40);

            // Jejak Perpindahan Unit, Lokasi Ruangan & Pemegang Personal (Dari Mana -> Ke Mana)
            $table->foreignId('from_unit_id')->nullable()->constrained('units')->nullOnDelete();
            $table->foreignId('to_unit_id')->constrained('units')->restrictOnDelete();
            $table->foreignId('from_location_id')->nullable()->constrained('asset_locations')->nullOnDelete();
            $table->foreignId('to_location_id')->nullable()->constrained('asset_locations')->nullOnDelete();
            $table->foreignId('from_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('to_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('recipient_name_snapshot', 150)->nullable();

            // Jejak Perubahan Status & Kondisi Fisik Saat Kejadian
            $table->string('previous_status', 30)->nullable();
            $table->string('new_status', 30);
            $table->string('previous_condition', 30)->nullable();
            $table->string('new_condition', 30);

            // Dokumen Bukti BAST/SK, Batas Peminjaman & Dokumentasi Lapangan
            $table->foreignId('reference_document_id')->nullable()->constrained('documents')->nullOnDelete();
            $table->date('expected_return_date')->nullable();
            $table->timestamp('returned_at')->nullable();
            $table->text('notes')->nullable();
            $table->string('evidence_photo_path', 500)->nullable();
            $table->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('event_date');
            $table->timestamp('created_at')->nullable();

            $table->index(['asset_id', 'event_date'], 'idx_asset_movements_timeline');
            $table->index(['to_unit_id', 'event_type', 'event_date'], 'idx_asset_movements_to_unit_history');
            $table->index(['from_unit_id', 'event_type', 'event_date'], 'idx_asset_movements_from_unit_history');
            $table->index(['event_type', 'expected_return_date'], 'idx_asset_movements_borrow_due');
        });

        Schema::create('asset_maintenances', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('asset_id')->constrained('assets')->cascadeOnDelete();
            $table->foreignId('unit_id')->constrained('units')->restrictOnDelete();
            $table->foreignId('reported_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('handled_by')->nullable()->constrained('users')->nullOnDelete();

            // Detail Keluhan Kerusakan, Tindakan Servis & Biaya
            $table->string('maintenance_type', 40)->default('repair');
            $table->text('issue_description');
            $table->text('action_taken')->nullable();
            $table->string('vendor_or_technician', 150)->nullable();
            $table->timestamp('reported_date');
            $table->date('start_date')->nullable();
            $table->date('completed_date')->nullable();
            $table->decimal('cost', 15, 2)->default(0);
            $table->string('status', 30)->default('pending');
            $table->string('condition_after', 30)->nullable();
            $table->string('damage_photo_path', 500)->nullable();
            $table->string('invoice_photo_path', 500)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['asset_id', 'reported_date'], 'idx_asset_maintenances_asset_history');
            $table->index(['status', 'unit_id', 'reported_date'], 'idx_asset_maintenances_status_queue');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('asset_maintenances');
        Schema::dropIfExists('asset_movements');
        Schema::dropIfExists('assets');
        Schema::dropIfExists('asset_locations');
        Schema::dropIfExists('asset_categories');
    }
};
