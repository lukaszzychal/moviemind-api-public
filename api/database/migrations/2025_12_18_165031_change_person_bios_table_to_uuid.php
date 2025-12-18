<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Migration to change person_bios table primary key from bigint to UUIDv7.
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
     * Changes the `person_id` column from bigint to uuid.
     */
    public function up(): void
    {
        $driver = DB::connection()->getDriverName();

        // SQLite is used in tests - skip migration as tables are created fresh with UUID
        if ($driver === 'sqlite') {
            return;
        }

        // PostgreSQL/MySQL migration
        Schema::table('person_bios', function (Blueprint $table) {
            // Drop foreign key constraint on person_id
            $table->dropForeign(['person_id']);
        });

        // Change id column type from bigint to uuid
        DB::statement('ALTER TABLE person_bios DROP CONSTRAINT IF EXISTS person_bios_pkey');
        DB::statement('ALTER TABLE person_bios ALTER COLUMN id TYPE uuid USING gen_random_uuid()');
        DB::statement('ALTER TABLE person_bios ADD PRIMARY KEY (id)');

        // Change person_id column type from bigint to uuid
        DB::statement('ALTER TABLE person_bios ALTER COLUMN person_id TYPE uuid');

        Schema::table('person_bios', function (Blueprint $table) {
            // Recreate foreign key constraint with uuid type
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
        Schema::table('person_bios', function (Blueprint $table) {
            $table->dropForeign(['person_id']);
        });

        // Change id column type back from uuid to bigint
        DB::statement('ALTER TABLE person_bios DROP CONSTRAINT IF EXISTS person_bios_pkey');
        DB::statement('ALTER TABLE person_bios ALTER COLUMN id TYPE bigint USING NULL');
        DB::statement('ALTER TABLE person_bios ADD PRIMARY KEY (id)');

        // Change person_id column type back from uuid to bigint
        DB::statement('ALTER TABLE person_bios ALTER COLUMN person_id TYPE bigint USING NULL');

        Schema::table('person_bios', function (Blueprint $table) {
            $table->foreign('person_id')
                ->references('id')
                ->on('people')
                ->cascadeOnDelete();
        });
    }
};
