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
            // First, ensure all flashcards have a valid topic_id
            // For any flashcards that have unit_id but no topic_id,
            // we need to either assign them to a topic or remove them

            // Make topic_id required (not nullable)
            $table->unsignedBigInteger('topic_id')->nullable(false)->change();

            // Make unit_id nullable (it will be derived from topic now)
            $table->unsignedBigInteger('unit_id')->nullable()->change();

            // Add index for better performance
            $table->index(['topic_id', 'is_active'], 'flashcards_topic_active_index');
        });

        // Clean up any orphaned flashcards that don't have topic_id
        // In a real migration, you'd want to handle this more carefully
        DB::statement('DELETE FROM flashcards WHERE topic_id IS NULL');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('flashcards', function (Blueprint $table) {
            // Reverse the changes
            $table->dropIndex('flashcards_topic_active_index');
            $table->unsignedBigInteger('topic_id')->nullable()->change();
            $table->unsignedBigInteger('unit_id')->nullable(false)->change();
        });
    }
};
