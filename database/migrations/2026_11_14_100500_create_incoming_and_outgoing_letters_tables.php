<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('incoming_letters', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('unit_id')->constrained('units')->restrictOnDelete();
            $table->foreignId('academic_year_id')->nullable()->constrained('academic_years')->nullOnDelete();
            $table->foreignId('registered_by')->constrained('users')->restrictOnDelete();

            $table->string('sender_name');
            $table->string('external_letter_number')->nullable();
            $table->date('external_letter_date')->nullable();
            $table->date('received_date');

            $table->string('agenda_number')->unique()->nullable();
            $table->unsignedSmallInteger('sequence_year')->nullable();
            $table->unsignedInteger('sequence_number')->nullable();

            $table->string('subject');
            $table->text('summary')->nullable();
            $table->string('priority')->default('biasa');
            $table->string('status')->default('in_progress');
            $table->unsignedTinyInteger('current_step')->default(1);
            $table->unsignedTinyInteger('total_steps')->default(0);

            $table->string('original_file_path')->nullable();
            $table->string('stamped_file_path')->nullable();
            $table->string('file_name')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['unit_id', 'sequence_year', 'sequence_number'], 'idx_incoming_unit_seq');
            $table->index(['status', 'current_step'], 'idx_incoming_status_step');
        });

        Schema::create('incoming_letter_recipients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('incoming_letter_id')->constrained('incoming_letters')->cascadeOnDelete();
            $table->unsignedTinyInteger('step_order');
            $table->foreignId('recipient_user_id')->constrained('users')->restrictOnDelete();
            $table->string('recipient_position_snapshot')->nullable();
            $table->text('instruction_note')->nullable();
            $table->text('disposition_note')->nullable();
            $table->string('status')->default('waiting');
            $table->timestamp('notified_at')->nullable();
            $table->timestamp('opened_at')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['incoming_letter_id', 'step_order'], 'idx_inc_recip_step');
            $table->index(['recipient_user_id', 'status'], 'idx_inc_recip_user_status');
        });

        Schema::create('outgoing_letters', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('unit_id')->constrained('units')->restrictOnDelete();
            $table->foreignId('academic_year_id')->nullable()->constrained('academic_years')->nullOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();

            $table->string('destination_organization');
            $table->string('subject');
            $table->text('description')->nullable();
            $table->date('letter_date');

            $table->string('letter_number')->unique()->nullable();
            $table->unsignedSmallInteger('sequence_year')->nullable();
            $table->unsignedInteger('sequence_number')->nullable();

            $table->string('status')->default('pending_humas');
            $table->text('review_note')->nullable();
            $table->timestamp('reviewed_at')->nullable();

            $table->string('original_file_path')->nullable();
            $table->string('stamped_file_path')->nullable();
            $table->string('file_name')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['unit_id', 'sequence_year', 'sequence_number'], 'idx_outgoing_unit_seq');
            $table->index(['status', 'unit_id'], 'idx_outgoing_status_unit');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('outgoing_letters');
        Schema::dropIfExists('incoming_letter_recipients');
        Schema::dropIfExists('incoming_letters');
    }
};
