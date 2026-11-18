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
        Schema::table('posts', function (Blueprint $table): void {
            $table->index(['created_at'], 'idx_posts_created_at');
            $table->index(['period', 'created_at'], 'idx_posts_period_created');
            $table->index(['status', 'created_at'], 'idx_posts_status_created');
            $table->index(['unit_id', 'created_at'], 'idx_posts_unit_created');
            $table->index(['post_category_id', 'created_at'], 'idx_posts_category_created');
        });

        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            // Partial index khusus antrean moderasi & draf agar scan ribuan–jutaan artikel published nol overhead
            DB::statement("CREATE INDEX IF NOT EXISTS idx_posts_pending_queue_partial ON posts (unit_id, created_at DESC) WHERE status IN ('pending_review', 'draft', 'revision') AND deleted_at IS NULL");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::statement('DROP INDEX IF EXISTS idx_posts_pending_queue_partial');
        }

        Schema::table('posts', function (Blueprint $table): void {
            $table->dropIndex('idx_posts_category_created');
            $table->dropIndex('idx_posts_unit_created');
            $table->dropIndex('idx_posts_status_created');
            $table->dropIndex('idx_posts_period_created');
            $table->dropIndex('idx_posts_created_at');
        });
    }
};
