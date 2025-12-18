<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Migration to change movie_person pivot table foreign keys from bigint to UUIDv7.
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
     * Changes the `movie_id` and `person_id` columns from bigint to uuid.
     */
    public function up(): void
    {
        $driver = DB::connection()->getDriverName();

        // SQLite is used in tests - skip migration as tables are created fresh with UUID
        if ($driver === 'sqlite') {
            return;
        }

        // PostgreSQL/MySQL migration
        Schema::table('movie_person', function (Blueprint $table) {
            // Drop foreign key constraints
            $table->dropForeign(['movie_id']);
            $table->dropForeign(['person_id']);
        });

        // Drop primary key constraint (composite key)
        DB::statement('ALTER TABLE movie_person DROP CONSTRAINT IF EXISTS movie_person_pkey');

        // Change movie_id and person_id columns type from bigint to uuid
        DB::statement('ALTER TABLE movie_person ALTER COLUMN movie_id TYPE uuid');
        DB::statement('ALTER TABLE movie_person ALTER COLUMN person_id TYPE uuid');

        // Recreate primary key constraint
        DB::statement('ALTER TABLE movie_person ADD PRIMARY KEY (movie_id, person_id, role)');

        Schema::table('movie_person', function (Blueprint $table) {
            // Recreate foreign key constraints with uuid type
            $table->foreign('movie_id')
                ->references('id')
                ->on('movies')
                ->cascadeOnDelete();

            $table->foreign('person_id')
                ->references('id')
                ->on('people')
                ->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('movie_person', function (Blueprint $table) {
            $table->dropForeign(['movie_id']);
            $table->dropForeign(['person_id']);
        });

        // Drop primary key constraint
        DB::statement('ALTER TABLE movie_person DROP CONSTRAINT IF EXISTS movie_person_pkey');

        // Change movie_id and person_id columns type back from uuid to bigint
        DB::statement('ALTER TABLE movie_person ALTER COLUMN movie_id TYPE bigint USING NULL');
        DB::statement('ALTER TABLE movie_person ALTER COLUMN person_id TYPE bigint USING NULL');

        // Recreate primary key constraint
        DB::statement('ALTER TABLE movie_person ADD PRIMARY KEY (movie_id, person_id, role)');

        Schema::table('movie_person', function (Blueprint $table) {
            $table->foreign('movie_id')
                ->references('id')
                ->on('movies')
                ->cascadeOnDelete();

            $table->foreign('person_id')
                ->references('id')
                ->on('people')
                ->cascadeOnDelete();
        });
    }
};
