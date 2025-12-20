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
        Schema::table('movie_descriptions', function (Blueprint $table) {
            // Add version_number column (default 1 for existing records)
            $table->integer('version_number')->default(1)->after('ai_model');

            // Add archived_at column (nullable, for soft delete)
            $table->timestamp('archived_at')->nullable()->after('updated_at');

            // Add index for versioning queries
            $table->index(['movie_id', 'locale', 'context_tag', 'version_number'], 'movie_descriptions_versioning_index');
        });

        // Drop old unique constraint
        Schema::table('movie_descriptions', function (Blueprint $table) {
            $table->dropUnique(['movie_id', 'locale', 'context_tag']);
        });

        // Create partial unique index (only for non-archived descriptions)
        // This ensures only one active description per (movie_id, locale, context_tag)
        if (DB::getDriverName() !== 'sqlite') {
            DB::statement('
                CREATE UNIQUE INDEX movie_descriptions_unique_active 
                ON movie_descriptions (movie_id, locale, context_tag) 
                WHERE archived_at IS NULL
            ');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop partial unique index
        if (DB::getDriverName() !== 'sqlite') {
            DB::statement('DROP INDEX IF EXISTS movie_descriptions_unique_active');
        }

        Schema::table('movie_descriptions', function (Blueprint $table) {
            // Restore old unique constraint
            $table->unique(['movie_id', 'locale', 'context_tag']);

            // Drop index
            $table->dropIndex('movie_descriptions_versioning_index');

            // Drop columns
            $table->dropColumn(['version_number', 'archived_at']);
        });
    }
};
