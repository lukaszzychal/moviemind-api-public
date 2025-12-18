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
        Schema::table('movies', function (Blueprint $table) {
            // Drop foreign key constraint on default_description_id if it exists
            try {
                $table->dropForeign(['default_description_id']);
            } catch (\Exception $e) {
                // Foreign key might not exist, ignore
            }
        });

        // Drop primary key constraint
        DB::statement('ALTER TABLE movies DROP CONSTRAINT IF EXISTS movies_pkey');

        // Change id column type from bigint to uuid
        // Note: This will fail if table contains data - use data migration script first
        DB::statement('ALTER TABLE movies ALTER COLUMN id TYPE uuid USING gen_random_uuid()');
        DB::statement('ALTER TABLE movies ADD PRIMARY KEY (id)');

        // Change default_description_id column type from bigint to uuid
        DB::statement('ALTER TABLE movies ALTER COLUMN default_description_id TYPE uuid');

        Schema::table('movies', function (Blueprint $table) {
            // Recreate index on default_description_id
            $table->index('default_description_id');
        });
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
