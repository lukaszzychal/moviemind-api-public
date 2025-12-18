<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Migration to change movie_relationships table primary key from bigint to UUIDv7.
 *
 * Uses Laravel's built-in UUID support (HasUuids trait).
 *
 * IMPORTANT: This migration is designed for fresh/empty databases.
 *
 * @author MovieMind API Team
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Changes the `id` column from bigint to uuid (UUIDv7).
     * Changes the `movie_id` and `related_movie_id` columns from bigint to uuid.
     */
    public function up(): void
    {
        $driver = DB::connection()->getDriverName();

        // SQLite is used in tests - skip migration as tables are created fresh with UUID
        if ($driver === 'sqlite') {
            return;
        }

        // PostgreSQL/MySQL migration
        Schema::table('movie_relationships', function (Blueprint $table) {
            // Drop foreign key constraints
            $table->dropForeign(['movie_id']);
            $table->dropForeign(['related_movie_id']);
        });

        // Change id column type from bigint to uuid
        DB::statement('ALTER TABLE movie_relationships DROP CONSTRAINT IF EXISTS movie_relationships_pkey');
        DB::statement('ALTER TABLE movie_relationships ALTER COLUMN id TYPE uuid USING gen_random_uuid()');
        DB::statement('ALTER TABLE movie_relationships ADD PRIMARY KEY (id)');

        // Change movie_id and related_movie_id columns type from bigint to uuid
        DB::statement('ALTER TABLE movie_relationships ALTER COLUMN movie_id TYPE uuid');
        DB::statement('ALTER TABLE movie_relationships ALTER COLUMN related_movie_id TYPE uuid');

        Schema::table('movie_relationships', function (Blueprint $table) {
            // Recreate foreign key constraints with uuid type
            $table->foreign('movie_id')
                ->references('id')
                ->on('movies')
                ->cascadeOnDelete();

            $table->foreign('related_movie_id')
                ->references('id')
                ->on('movies')
                ->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('movie_relationships', function (Blueprint $table) {
            $table->dropForeign(['movie_id']);
            $table->dropForeign(['related_movie_id']);
        });

        // Change movie_id and related_movie_id columns type back from uuid to bigint
        DB::statement('ALTER TABLE movie_relationships ALTER COLUMN movie_id TYPE bigint USING NULL');
        DB::statement('ALTER TABLE movie_relationships ALTER COLUMN related_movie_id TYPE bigint USING NULL');

        // Change id column type back from uuid to bigint
        DB::statement('ALTER TABLE movie_relationships DROP CONSTRAINT IF EXISTS movie_relationships_pkey');
        DB::statement('ALTER TABLE movie_relationships ALTER COLUMN id TYPE bigint USING NULL');
        DB::statement('ALTER TABLE movie_relationships ADD PRIMARY KEY (id)');

        Schema::table('movie_relationships', function (Blueprint $table) {
            $table->foreign('movie_id')
                ->references('id')
                ->on('movies')
                ->cascadeOnDelete();

            $table->foreign('related_movie_id')
                ->references('id')
                ->on('movies')
                ->cascadeOnDelete();
        });
    }
};
