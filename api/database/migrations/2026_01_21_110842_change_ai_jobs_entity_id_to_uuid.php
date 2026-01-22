<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Migration to change ai_jobs.entity_id from bigint to uuid.
 *
 * Models use UUID as primary key, so entity_id must be uuid to reference them.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $driver = DB::connection()->getDriverName();

        // SQLite is used in tests - skip migration as tables are created fresh with UUID
        if ($driver === 'sqlite') {
            return;
        }

        // PostgreSQL migration: change entity_id from bigint to uuid
        // If column is already uuid, skip
        if (Schema::hasColumn('ai_jobs', 'entity_id')) {
            $columnType = DB::selectOne("
                SELECT data_type 
                FROM information_schema.columns 
                WHERE table_name = 'ai_jobs' 
                AND column_name = 'entity_id'
            ");

            if ($columnType && $columnType->data_type !== 'uuid') {
                DB::statement('ALTER TABLE ai_jobs ALTER COLUMN entity_id TYPE uuid USING entity_id::text::uuid');
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'sqlite') {
            return;
        }

        // Change entity_id back to bigint (will lose data if UUIDs can't be converted)
        if (Schema::hasColumn('ai_jobs', 'entity_id')) {
            $columnType = DB::selectOne("
                SELECT data_type 
                FROM information_schema.columns 
                WHERE table_name = 'ai_jobs' 
                AND column_name = 'entity_id'
            ");

            if ($columnType && $columnType->data_type === 'uuid') {
                DB::statement('ALTER TABLE ai_jobs ALTER COLUMN entity_id TYPE bigint USING NULL');
            }
        }
    }
};
