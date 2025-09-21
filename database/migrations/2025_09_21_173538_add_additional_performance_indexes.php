<?php

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
        Schema::table('flashcards', function (Blueprint $table) {
            // Composite index for unit + topic + active queries (common in FlashcardController)
            $table->index(['unit_id', 'topic_id', 'is_active'], 'flashcards_unit_topic_active_idx');

            // Performance index for bulk operations by IDs
            $table->index(['id', 'is_active'], 'flashcards_id_active_idx');

            // Index for user access patterns (via unit -> subject -> user)
            $table->index(['created_at', 'is_active'], 'flashcards_created_active_idx');
        });

        // Add performance indexes to units table
        Schema::table('units', function (Blueprint $table) {
            // Composite index for subject + target completion date (common in dashboard queries)
            $table->index(['subject_id', 'target_completion_date'], 'units_subject_target_date_idx');

            // Index for date-based filtering
            $table->index(['target_completion_date'], 'units_target_date_idx');
        });

        // Add performance indexes to topics table
        Schema::table('topics', function (Blueprint $table) {
            // Composite index for unit + required status (for progress calculations)
            $table->index(['unit_id', 'required'], 'topics_unit_required_idx');

            // Index for prerequisite-based queries
            $table->index(['unit_id', 'created_at'], 'topics_unit_created_idx');
        });

        // Add performance indexes to subjects table
        Schema::table('subjects', function (Blueprint $table) {
            // Index for user-based queries
            $table->index(['user_id', 'created_at'], 'subjects_user_created_idx');
        });

        // Add indexes for better caching and performance monitoring
        if (config('database.default') === 'pgsql') {
            // PostgreSQL specific performance improvements (without CONCURRENTLY in migration)
            DB::statement('CREATE INDEX IF NOT EXISTS flashcards_performance_idx
                          ON flashcards (unit_id, topic_id, is_active, created_at, updated_at)');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('flashcards', function (Blueprint $table) {
            $table->dropIndex('flashcards_unit_topic_active_idx');
            $table->dropIndex('flashcards_id_active_idx');
            $table->dropIndex('flashcards_created_active_idx');
        });

        Schema::table('units', function (Blueprint $table) {
            $table->dropIndex('units_subject_target_date_idx');
            $table->dropIndex('units_target_date_idx');
        });

        Schema::table('topics', function (Blueprint $table) {
            $table->dropIndex('topics_unit_required_idx');
            $table->dropIndex('topics_unit_created_idx');
        });

        Schema::table('subjects', function (Blueprint $table) {
            $table->dropIndex('subjects_user_created_idx');
        });

        // Drop PostgreSQL specific indexes
        if (config('database.default') === 'pgsql') {
            DB::statement('DROP INDEX IF EXISTS flashcards_performance_idx');
        }
    }
};
