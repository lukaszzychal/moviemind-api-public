<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Migration to change movies table primary key from bigint to UUIDv7.
 *
 * This migration uses Laravel's built-in UUID support (HasUuids trait).
 * Laravel 12 automatically generates UUIDv7 for models using HasUuids trait.
 *
 * IMPORTANT: This migration is designed for fresh/empty databases.
 * For existing production data, you MUST use a data migration script first
 * to convert existing bigint IDs to UUIDs while preserving relationships.
 *
 * See docs/plan/UUIDV7_MIGRATION_PLAN.md for data migration strategy.
 *
 * @author MovieMind API Team
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Changes the `id` column from bigint to uuid (UUIDv7).
     * Changes the `default_description_id` column from bigint to uuid.
     */
    public function up(): void
    {
        $driver = DB::connection()->getDriverName();

        // SQLite doesn't support ALTER COLUMN TYPE directly
        // For SQLite (tests), skip this migration - tables will be created with UUID from start
        if ($driver === 'sqlite') {
            // SQLite is used in tests - skip migration as tables are created fresh
            // The original create_movies_table migration should be updated to use UUID
            return;
        }

        // PostgreSQL/MySQL migration
        // IMPORTANT: Must drop all foreign keys that reference movies.id BEFORE changing the primary key

        // Drop foreign key constraint on default_description_id if it exists (PostgreSQL)
        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE movies DROP CONSTRAINT IF EXISTS movies_default_description_id_foreign');

            // Drop all foreign keys that reference movies.id
            DB::statement('ALTER TABLE movie_descriptions DROP CONSTRAINT IF EXISTS movie_descriptions_movie_id_foreign');
            DB::statement('ALTER TABLE movie_genre DROP CONSTRAINT IF EXISTS movie_genre_movie_id_foreign');
            DB::statement('ALTER TABLE movie_person DROP CONSTRAINT IF EXISTS movie_person_movie_id_foreign');
            DB::statement('ALTER TABLE movie_relationships DROP CONSTRAINT IF EXISTS movie_relationships_movie_id_foreign');
            DB::statement('ALTER TABLE movie_relationships DROP CONSTRAINT IF EXISTS movie_relationships_related_movie_id_foreign');
        } else {
            // MySQL - try to drop foreign keys if they exist
            Schema::table('movies', function (Blueprint $table) {
                try {
                    $table->dropForeign(['default_description_id']);
                } catch (\Exception $e) {
                    // Foreign key might not exist, ignore
                }
            });

            Schema::table('movie_descriptions', function (Blueprint $table) {
                try {
                    $table->dropForeign(['movie_id']);
                } catch (\Exception $e) {
                    // Foreign key might not exist, ignore
                }
            });

            Schema::table('movie_genre', function (Blueprint $table) {
                try {
                    $table->dropForeign(['movie_id']);
                } catch (\Exception $e) {
                    // Foreign key might not exist, ignore
                }
            });

            Schema::table('movie_person', function (Blueprint $table) {
                try {
                    $table->dropForeign(['movie_id']);
                } catch (\Exception $e) {
                    // Foreign key might not exist, ignore
                }
            });

            Schema::table('movie_relationships', function (Blueprint $table) {
                try {
                    $table->dropForeign(['movie_id']);
                    $table->dropForeign(['related_movie_id']);
                } catch (\Exception $e) {
                    // Foreign keys might not exist, ignore
                }
            });
        }

        // Now we can safely drop primary key constraint
        DB::statement('ALTER TABLE movies DROP CONSTRAINT IF EXISTS movies_pkey');

        // Change id column type from bigint to uuid
        // Note: This will fail if table contains data - use data migration script first
        DB::statement('ALTER TABLE movies ALTER COLUMN id TYPE uuid USING gen_random_uuid()');
        DB::statement('ALTER TABLE movies ADD PRIMARY KEY (id)');

        // Change default_description_id column type from bigint to uuid
        DB::statement('ALTER TABLE movies ALTER COLUMN default_description_id TYPE uuid');

        // Recreate index on default_description_id if it doesn't exist
        // (The original create_movies_table migration already creates this index)
        if ($driver === 'pgsql') {
            DB::statement('CREATE INDEX IF NOT EXISTS movies_default_description_id_index ON movies (default_description_id)');
        } else {
            Schema::table('movies', function (Blueprint $table) {
                // Check if index exists before creating (MySQL)
                $indexes = DB::select("SHOW INDEXES FROM movies WHERE Key_name = 'movies_default_description_id_index'");
                if (empty($indexes)) {
                    $table->index('default_description_id');
                }
            });
        }

        // Recreate foreign keys that reference movies.id (now UUID)
        if ($driver === 'pgsql') {
            // Recreate foreign keys using raw SQL for PostgreSQL
            DB::statement('ALTER TABLE movie_descriptions ADD CONSTRAINT movie_descriptions_movie_id_foreign FOREIGN KEY (movie_id) REFERENCES movies(id) ON DELETE CASCADE');
            DB::statement('ALTER TABLE movie_genre ADD CONSTRAINT movie_genre_movie_id_foreign FOREIGN KEY (movie_id) REFERENCES movies(id) ON DELETE CASCADE');
            DB::statement('ALTER TABLE movie_person ADD CONSTRAINT movie_person_movie_id_foreign FOREIGN KEY (movie_id) REFERENCES movies(id) ON DELETE CASCADE');
            DB::statement('ALTER TABLE movie_relationships ADD CONSTRAINT movie_relationships_movie_id_foreign FOREIGN KEY (movie_id) REFERENCES movies(id) ON DELETE CASCADE');
            DB::statement('ALTER TABLE movie_relationships ADD CONSTRAINT movie_relationships_related_movie_id_foreign FOREIGN KEY (related_movie_id) REFERENCES movies(id) ON DELETE CASCADE');
        } else {
            // MySQL - recreate foreign keys using Schema
            Schema::table('movie_descriptions', function (Blueprint $table) {
                $table->foreign('movie_id')->references('id')->on('movies')->cascadeOnDelete();
            });

            Schema::table('movie_genre', function (Blueprint $table) {
                $table->foreign('movie_id')->references('id')->on('movies')->cascadeOnDelete();
            });

            Schema::table('movie_person', function (Blueprint $table) {
                $table->foreign('movie_id')->references('id')->on('movies')->cascadeOnDelete();
            });

            Schema::table('movie_relationships', function (Blueprint $table) {
                $table->foreign('movie_id')->references('id')->on('movies')->cascadeOnDelete();
                $table->foreign('related_movie_id')->references('id')->on('movies')->cascadeOnDelete();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('movies', function (Blueprint $table) {
            $table->dropIndex(['default_description_id']);
        });

        // Change id column type back from uuid to bigint
        // Note: This conversion will lose UUID data - only use for rollback during development
        DB::statement('ALTER TABLE movies DROP CONSTRAINT IF EXISTS movies_pkey');
        DB::statement('ALTER TABLE movies ALTER COLUMN id TYPE bigint USING NULL');
        DB::statement('ALTER TABLE movies ADD PRIMARY KEY (id)');

        // Change default_description_id column type back from uuid to bigint
        DB::statement('ALTER TABLE movies ALTER COLUMN default_description_id TYPE bigint USING NULL');

        Schema::table('movies', function (Blueprint $table) {
            $table->index('default_description_id');
        });
    }
};
