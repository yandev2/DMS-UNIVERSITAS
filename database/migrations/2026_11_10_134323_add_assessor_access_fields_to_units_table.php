<?php

declare(strict_types=1);

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
        Schema::table('units', function (Blueprint $table): void {
            $table->boolean('is_assessor_access_active')->default(false)->after('accreditation_document_id');
            $table->date('assessor_access_start')->nullable()->after('is_assessor_access_active');
            $table->date('assessor_access_end')->nullable()->after('assessor_access_start');
            $table->string('assessor_passcode', 50)->nullable()->after('assessor_access_end');
            $table->text('assessor_notes')->nullable()->after('assessor_passcode');
            $table->index(['is_assessor_access_active', 'assessor_access_start', 'assessor_access_end'], 'idx_units_assessor_window');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('units', function (Blueprint $table): void {
            $table->dropIndex('idx_units_assessor_window');
            $table->dropColumn([
                'is_assessor_access_active',
                'assessor_access_start',
                'assessor_access_end',
                'assessor_passcode',
                'assessor_notes',
            ]);
        });
    }
};
