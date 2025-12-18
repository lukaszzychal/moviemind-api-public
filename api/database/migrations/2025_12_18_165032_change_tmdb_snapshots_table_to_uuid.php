<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Migration to change tmdb_snapshots table primary key from bigint to UUIDv7.
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
     * Changes the `entity_id` column from bigint to uuid (references movies.id or people.id).
     */
    public function up(): void
    {
        $driver = DB::connection()->getDriverName();

        // SQLite is used in tests - skip migration as tables are created fresh with UUID
        if ($driver === 'sqlite') {
            return;
        }

        // PostgreSQL/MySQL migration
        // Change id column type from bigint to uuid
        DB::statement('ALTER TABLE tmdb_snapshots DROP CONSTRAINT IF EXISTS tmdb_snapshots_pkey');
        DB::statement('ALTER TABLE tmdb_snapshots ALTER COLUMN id TYPE uuid USING gen_random_uuid()');
        DB::statement('ALTER TABLE tmdb_snapshots ADD PRIMARY KEY (id)');

        // Change entity_id column type from bigint to uuid
        DB::statement('ALTER TABLE tmdb_snapshots ALTER COLUMN entity_id TYPE uuid');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Change entity_id column type back from uuid to bigint
        DB::statement('ALTER TABLE tmdb_snapshots ALTER COLUMN entity_id TYPE bigint USING NULL');

        // Change id column type back from uuid to bigint
        DB::statement('ALTER TABLE tmdb_snapshots DROP CONSTRAINT IF EXISTS tmdb_snapshots_pkey');
        DB::statement('ALTER TABLE tmdb_snapshots ALTER COLUMN id TYPE bigint USING NULL');
        DB::statement('ALTER TABLE tmdb_snapshots ADD PRIMARY KEY (id)');
    }
};
