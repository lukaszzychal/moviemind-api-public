<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Migration to change movie_descriptions table primary key from bigint to UUIDv7.
 *
 * This migration:
 * 1. Drops foreign key constraint on movie_id
 * 2. Changes the `id` column from bigint to uuid
 * 3. Changes the `movie_id` column from bigint to uuid
 * 4. Recreates foreign key constraint with uuid type
 *
 * @author MovieMind API Team
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Changes the `id` column from bigint to uuid (UUIDv7).
     * Changes the `movie_id` column from bigint to uuid.
     * Recreates foreign key constraint with uuid type.
     */
    public function up(): void
    {
        $driver = DB::connection()->getDriverName();

        // SQLite is used in tests - skip migration as tables are created fresh with UUID
        if ($driver === 'sqlite') {
            return;
        }

        // PostgreSQL/MySQL migration
        Schema::table('movie_descriptions', function (Blueprint $table) {
            // Drop foreign key constraint on movie_id
            $table->dropForeign(['movie_id']);
        });

        // Change id column type from bigint to uuid
        DB::statement('ALTER TABLE movie_descriptions DROP CONSTRAINT IF EXISTS movie_descriptions_pkey');
        DB::statement('ALTER TABLE movie_descriptions ALTER COLUMN id TYPE uuid USING gen_random_uuid()');
        DB::statement('ALTER TABLE movie_descriptions ADD PRIMARY KEY (id)');

        // Change movie_id column type from bigint to uuid
        // Note: This requires matching UUIDs in the movies table
        DB::statement('ALTER TABLE movie_descriptions ALTER COLUMN movie_id TYPE uuid');

        Schema::table('movie_descriptions', function (Blueprint $table) {
            // Recreate foreign key constraint with uuid type
            $table->foreign('movie_id')
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
        Schema::table('movie_descriptions', function (Blueprint $table) {
            $table->dropForeign(['movie_id']);
        });

        // Change id column type back from uuid to bigint
        DB::statement('ALTER TABLE movie_descriptions DROP CONSTRAINT movie_descriptions_pkey');
        DB::statement('ALTER TABLE movie_descriptions ALTER COLUMN id TYPE bigint USING id::text::bigint');
        DB::statement('ALTER TABLE movie_descriptions ADD PRIMARY KEY (id)');

        // Change movie_id column type back from uuid to bigint
        DB::statement('ALTER TABLE movie_descriptions ALTER COLUMN movie_id TYPE bigint USING movie_id::text::bigint');

        Schema::table('movie_descriptions', function (Blueprint $table) {
            $table->foreign('movie_id')
                ->references('id')
                ->on('movies')
                ->cascadeOnDelete();
        });
    }
};
