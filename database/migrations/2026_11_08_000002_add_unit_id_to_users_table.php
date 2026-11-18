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
        Schema::table('users', function (Blueprint $table): void {
            $table->foreignId('unit_id')->nullable()->after('password')->constrained('units')->nullOnDelete();
            $table->index(['unit_id', 'is_active'], 'idx_users_unit_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropIndex('idx_users_unit_active');
            $table->dropForeign(['unit_id']);
            $table->dropColumn('unit_id');
        });
    }
};
