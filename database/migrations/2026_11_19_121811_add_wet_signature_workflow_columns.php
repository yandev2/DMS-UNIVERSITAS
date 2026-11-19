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
            $table->boolean('requires_wet_signature')->default(false)->after('allow_manual_number');
        });

        Schema::table('documents', function (Blueprint $table): void {
            $table->boolean('requires_wet_signature')->default(false)->after('is_borang');
            $table->foreignId('final_scan_uploaded_by')->nullable()->after('final_approved_by')->constrained('users')->nullOnDelete();
            $table->timestamp('final_scan_uploaded_at')->nullable()->after('approved_at');
        });

        Schema::table('outgoing_letters', function (Blueprint $table): void {
            $table->boolean('requires_wet_signature')->default(false)->after('status');
            $table->foreignId('final_scan_uploaded_by')->nullable()->after('reviewed_by')->constrained('users')->nullOnDelete();
            $table->timestamp('final_scan_uploaded_at')->nullable()->after('reviewed_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('outgoing_letters', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('final_scan_uploaded_by');
            $table->dropColumn(['requires_wet_signature', 'final_scan_uploaded_at']);
        });

        Schema::table('documents', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('final_scan_uploaded_by');
            $table->dropColumn(['requires_wet_signature', 'final_scan_uploaded_at']);
        });

        Schema::table('document_categories', function (Blueprint $table): void {
            $table->dropColumn('requires_wet_signature');
        });
    }
};
