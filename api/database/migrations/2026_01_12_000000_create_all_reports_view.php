<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Drop view if exists to support SQLite which doesn't support OR REPLACE
        DB::statement('DROP VIEW IF EXISTS all_reports');

        DB::statement("
            CREATE VIEW all_reports AS
            SELECT
                id,
                'movie' as entity_type,
                movie_id as entity_id,
                description_id,
                type,
                message,
                suggested_fix,
                status,
                priority_score,
                created_at,
                updated_at,
                verified_at,
                resolved_at
            FROM movie_reports
            UNION ALL
            SELECT
                id,
                'person' as entity_type,
                person_id as entity_id,
                bio_id as description_id,
                type,
                message,
                suggested_fix,
                status,
                priority_score,
                created_at,
                updated_at,
                verified_at,
                resolved_at
            FROM person_reports
            UNION ALL
            SELECT
                id,
                'tv_series' as entity_type,
                tv_series_id as entity_id,
                description_id,
                type,
                message,
                suggested_fix,
                status,
                priority_score,
                created_at,
                updated_at,
                verified_at,
                resolved_at
            FROM tv_series_reports
            UNION ALL
            SELECT
                id,
                'tv_show' as entity_type,
                tv_show_id as entity_id,
                description_id,
                type,
                message,
                suggested_fix,
                status,
                priority_score,
                created_at,
                updated_at,
                verified_at,
                resolved_at
            FROM tv_show_reports
        ");
    }

    public function down(): void
    {
        DB::statement('DROP VIEW IF EXISTS all_reports');
    }
};
