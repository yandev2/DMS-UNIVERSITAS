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
        Schema::create('post_categories', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 100);
            $table->string('slug', 100)->unique('idx_post_categories_slug_unique');
            $table->string('color', 50)->default('blue');
            $table->text('description')->nullable();
            $table->integer('order_index')->default(1);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['is_active', 'order_index'], 'idx_post_categories_active_order');
        });

        Schema::create('posts', function (Blueprint $table): void {
            $table->id();
            $table->string('title', 255);
            $table->string('slug', 255)->unique('idx_posts_slug_unique');
            $table->foreignId('post_category_id')->constrained('post_categories')->cascadeOnDelete();
            $table->foreignId('unit_id')->constrained('units')->cascadeOnDelete();
            $table->foreignId('academic_year_id')->nullable()->constrained('academic_years')->nullOnDelete();
            $table->foreignId('author_id')->constrained('users')->cascadeOnDelete();

            // Konten Utama & Metadata Kegiatan
            $table->string('thumbnail_url', 500)->nullable();
            $table->string('thumbnail_caption', 255)->nullable();
            $table->date('event_date')->nullable();
            $table->string('event_location', 255)->nullable();
            $table->text('excerpt')->nullable();
            $table->longText('content');
            $table->string('status', 30)->default('draft');

            // Alur Moderasi Terpusat oleh Tim Humas Universitas
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->text('moderation_note')->nullable();

            // Pengaturan Penayangan, Galeri Dokumentasi & Sorotan Portal Publik
            $table->timestamp('published_at')->nullable();
            $table->unsignedInteger('photos_count')->default(0);
            $table->boolean('allow_public_zip_download')->default(true);
            $table->unsignedInteger('views_count')->default(0);
            $table->unsignedInteger('shares_count')->default(0);
            $table->unsignedInteger('zip_downloads_count')->default(0);
            $table->boolean('is_featured')->default(false);
            $table->boolean('is_pinned')->default(false);

            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'published_at'], 'idx_posts_public_feed');
            $table->index(['unit_id', 'status', 'event_date'], 'idx_posts_unit_status_event');
            $table->index(['academic_year_id', 'unit_id'], 'idx_posts_academic_year_unit');
            $table->index(['post_category_id', 'status', 'published_at'], 'idx_posts_category_feed');
            $table->index(['status', 'is_featured', 'is_pinned', 'published_at'], 'idx_posts_featured_pinned');
            $table->index(['author_id', 'status'], 'idx_posts_author_status');
            $table->index('deleted_at', 'idx_posts_deleted_at');
        });

        Schema::create('post_document', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('post_id')->constrained('posts')->cascadeOnDelete();
            $table->foreignId('document_id')->constrained('documents')->cascadeOnDelete();
            $table->string('custom_button_label', 150)->nullable();
            $table->integer('order_index')->default(1);
            $table->timestamps();

            $table->unique(['post_id', 'document_id'], 'idx_post_doc_unique');
            $table->index(['post_id', 'order_index'], 'idx_post_doc_order');
        });

        Schema::create('post_photos', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique('idx_post_photos_uuid_unique');
            $table->foreignId('post_id')->constrained('posts')->cascadeOnDelete();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();

            // Penamaan Baku & Penyimpanan Dual-Resolution (Original High-Res vs Web Preview)
            $table->string('original_file_name', 255);
            $table->string('standardized_file_name', 255);
            $table->string('file_path', 500);
            $table->string('thumbnail_path', 500)->nullable();
            $table->string('disk', 20)->default('public');

            // Metadata Visual, Urutan & Statistik
            $table->string('caption', 255)->nullable();
            $table->string('alt_text', 255)->nullable();
            $table->string('mime_type', 100);
            $table->string('file_extension', 20);
            $table->unsignedBigInteger('file_size');
            $table->integer('width')->nullable();
            $table->integer('height')->nullable();
            $table->integer('order_index')->default(1);
            $table->boolean('is_cover')->default(false);
            $table->unsignedInteger('downloads_count')->default(0);
            $table->timestamps();

            $table->index(['post_id', 'order_index'], 'idx_post_photos_post_order');
            $table->index(['post_id', 'is_cover'], 'idx_post_photos_post_cover');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('post_photos');
        Schema::dropIfExists('post_document');
        Schema::dropIfExists('posts');
        Schema::dropIfExists('post_categories');
    }
};
