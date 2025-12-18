<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Migration to change people table primary key from bigint to UUIDv7.
 *
 * Uses Laravel's built-in UUID support (HasUuids trait).
 * Laravel 12 automatically generates UUIDv7 for models using HasUuids trait.
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
     */
    public function up(): void
    {
        $driver = DB::connection()->getDriverName();

        // SQLite is used in tests - skip migration as tables are created fresh with UUID
        if ($driver === 'sqlite') {
            return;
        }

        // PostgreSQL/MySQL migration
        // IMPORTANT: Must drop all foreign keys that reference people.id BEFORE changing the primary key

        if ($driver === 'pgsql') {
            // Drop foreign key constraint on default_bio_id if it exists
            DB::statement('ALTER TABLE people DROP CONSTRAINT IF EXISTS people_default_bio_id_foreign');

            // Drop all foreign keys that reference people.id
            DB::statement('ALTER TABLE movie_person DROP CONSTRAINT IF EXISTS movie_person_person_id_foreign');
            DB::statement('ALTER TABLE person_bios DROP CONSTRAINT IF EXISTS person_bios_person_id_foreign');
        } else {
            // MySQL - try to drop foreign keys if they exist
            Schema::table('people', function (Blueprint $table) {
                try {
                    $table->dropForeign(['default_bio_id']);
                } catch (\Exception $e) {
                    // Foreign key might not exist, ignore
                }
            });

            Schema::table('movie_person', function (Blueprint $table) {
                try {
                    $table->dropForeign(['person_id']);
                } catch (\Exception $e) {
                    // Foreign key might not exist, ignore
                }
            });

            Schema::table('person_bios', function (Blueprint $table) {
                try {
                    $table->dropForeign(['person_id']);
                } catch (\Exception $e) {
                    // Foreign key might not exist, ignore
                }
            });
        }

        // Now we can safely drop primary key constraint
        DB::statement('ALTER TABLE people DROP CONSTRAINT IF EXISTS people_pkey');

        // Change id column type from bigint to uuid
        DB::statement('ALTER TABLE people ALTER COLUMN id TYPE uuid USING gen_random_uuid()');
        DB::statement('ALTER TABLE people ADD PRIMARY KEY (id)');

        // Recreate foreign keys that reference people.id (now UUID)
        if ($driver === 'pgsql') {
            // Recreate foreign keys using raw SQL for PostgreSQL
            DB::statement('ALTER TABLE movie_person ADD CONSTRAINT movie_person_person_id_foreign FOREIGN KEY (person_id) REFERENCES people(id) ON DELETE CASCADE');
            DB::statement('ALTER TABLE person_bios ADD CONSTRAINT person_bios_person_id_foreign FOREIGN KEY (person_id) REFERENCES people(id) ON DELETE CASCADE');
        } else {
            // MySQL - recreate foreign keys using Schema
            Schema::table('movie_person', function (Blueprint $table) {
                $table->foreign('person_id')->references('id')->on('people')->cascadeOnDelete();
            });

            Schema::table('person_bios', function (Blueprint $table) {
                $table->foreign('person_id')->references('id')->on('people')->cascadeOnDelete();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Change id column type back from uuid to bigint
        DB::statement('ALTER TABLE people DROP CONSTRAINT IF EXISTS people_pkey');
        DB::statement('ALTER TABLE people ALTER COLUMN id TYPE bigint USING NULL');
        DB::statement('ALTER TABLE people ADD PRIMARY KEY (id)');
    }
};
