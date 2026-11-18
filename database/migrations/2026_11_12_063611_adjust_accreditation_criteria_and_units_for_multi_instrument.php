<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Modifikasi tabel accreditation_criteria: ganti global unique menjadi composite unique (code + instrument_source)
        Schema::table('accreditation_criteria', function (Blueprint $table): void {
            $table->dropUnique('idx_accred_criteria_code_unique');
            $table->unique(['code', 'instrument_source'], 'idx_accred_criteria_code_instrument');
            $table->index('instrument_source', 'idx_accred_criteria_instrument');
        });

        // 2. Tambahkan kolom accreditation_instrument pada tabel units
        Schema::table('units', function (Blueprint $table): void {
            $table->string('accreditation_instrument', 100)
                ->nullable()
                ->after('accreditation_body')
                ->index('idx_units_accred_instrument');
        });

        // 3. Normalisasi data kriteria eksisting (dari 'BAN-PT / LAM' menjadi 'BAN-PT')
        DB::table('accreditation_criteria')
            ->where('instrument_source', 'BAN-PT / LAM')
            ->update(['instrument_source' => 'BAN-PT']);

        // 4. Backfill kolom accreditation_instrument pada unit-unit kerja yang ada (cross-database compatible)
        DB::table('units')
            ->where(DB::raw('LOWER(accreditation_body)'), 'like', '%infokom%')
            ->orWhere('code', 'FIK')
            ->update(['accreditation_instrument' => 'LAM-INFOKOM']);

        DB::table('units')
            ->where(DB::raw('LOWER(accreditation_body)'), 'like', '%emba%')
            ->orWhere('code', 'FEB')
            ->update(['accreditation_instrument' => 'LAMEMBA']);

        DB::table('units')
            ->where(DB::raw('LOWER(accreditation_body)'), 'like', '%ban%')
            ->orWhere('code', 'REKTORAT')
            ->orWhere('type', 'rektorat')
            ->update(['accreditation_instrument' => 'BAN-PT']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('units', function (Blueprint $table): void {
            $table->dropIndex('idx_units_accred_instrument');
            $table->dropColumn('accreditation_instrument');
        });

        // Kembalikan nama instrumen lama jika di-rollback
        DB::table('accreditation_criteria')
            ->where('instrument_source', 'BAN-PT')
            ->update(['instrument_source' => 'BAN-PT / LAM']);

        Schema::table('accreditation_criteria', function (Blueprint $table): void {
            $table->dropIndex('idx_accred_criteria_instrument');
            $table->dropUnique('idx_accred_criteria_code_instrument');
            $table->unique('code', 'idx_accred_criteria_code_unique');
        });
    }
};
