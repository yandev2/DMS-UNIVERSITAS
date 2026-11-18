<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Menambahkan kolom jenis edisi/periode berita (daily, weekly, monthly) terpisah dari kategori
     * beserta composite index untuk filter portal publik & rekapitulasi Humas.
     */
    public function up(): void
    {
        Schema::table('posts', function (Blueprint $table): void {
            $table->string('period', 20)
                ->default('daily')
                ->after('post_category_id');

            $table->index(['status', 'period', 'published_at'], 'idx_posts_status_period_pub');
        });
    }

    /**
     * Menghapus index dan kolom periode secara simetris.
     */
    public function down(): void
    {
        Schema::table('posts', function (Blueprint $table): void {
            $table->dropIndex('idx_posts_status_period_pub');
            $table->dropColumn('period');
        });
    }
};
