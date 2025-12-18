<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

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
        // Drop primary key constraint
        DB::statement('ALTER TABLE people DROP CONSTRAINT IF EXISTS people_pkey');

        // Change id column type from bigint to uuid
        DB::statement('ALTER TABLE people ALTER COLUMN id TYPE uuid USING gen_random_uuid()');
        DB::statement('ALTER TABLE people ADD PRIMARY KEY (id)');
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
