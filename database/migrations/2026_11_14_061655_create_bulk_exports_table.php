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
        Schema::create('bulk_exports', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('module_type', 50)->index();
            $table->json('record_ids');
            $table->unsignedInteger('total_files')->default(0);
            $table->string('zip_file_name');
            $table->string('zip_file_path')->nullable();
            $table->string('disk', 30)->default('private');
            $table->unsignedBigInteger('file_size_bytes')->default(0);
            $table->string('status', 30)->default('pending')->index();
            $table->text('error_message')->nullable();
            $table->timestamp('expires_at')->index();
            $table->timestamp('downloaded_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bulk_exports');
    }
};
