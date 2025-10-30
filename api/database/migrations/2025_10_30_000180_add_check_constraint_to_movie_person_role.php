<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Skip on SQLite (no ALTER TABLE ADD CONSTRAINT support)
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        // PostgreSQL CHECK constraint for allowed roles
        DB::statement("ALTER TABLE movie_person ADD CONSTRAINT movie_person_role_check CHECK (role IN ('ACTOR','DIRECTOR','WRITER','PRODUCER'))");
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }
        DB::statement('ALTER TABLE movie_person DROP CONSTRAINT IF EXISTS movie_person_role_check');
    }
};
