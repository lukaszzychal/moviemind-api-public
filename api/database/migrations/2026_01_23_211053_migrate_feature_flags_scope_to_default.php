<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Migrate existing feature flags from '__laravel_null' scope to 'default' scope.
     * This ensures consistency with Feature::for('default') usage throughout the codebase.
     */
    public function up(): void
    {
        // Update all flags with '__laravel_null' scope to 'default' scope
        // Handle potential duplicates by keeping the most recent one
        DB::statement("
            UPDATE features 
            SET scope = 'default' 
            WHERE scope = '__laravel_null'
            AND NOT EXISTS (
                SELECT 1 FROM features f2 
                WHERE f2.name = features.name 
                AND f2.scope = 'default'
            )
        ");

        // Delete any remaining '__laravel_null' records that have duplicates in 'default'
        DB::table('features')
            ->where('scope', '__laravel_null')
            ->whereIn('name', function ($query) {
                $query->select('name')
                    ->from('features')
                    ->where('scope', 'default');
            })
            ->delete();
    }

    /**
     * Reverse the migrations.
     *
     * Note: This is a one-way migration. Reverting would require
     * merging 'default' scope back to '__laravel_null', which may cause data loss.
     */
    public function down(): void
    {
        // Revert 'default' scope back to '__laravel_null' for flags that were migrated
        // Only if no '__laravel_null' record already exists for that flag
        DB::statement("
            UPDATE features 
            SET scope = '__laravel_null' 
            WHERE scope = 'default'
            AND NOT EXISTS (
                SELECT 1 FROM features f2 
                WHERE f2.name = features.name 
                AND f2.scope = '__laravel_null'
            )
        ");
    }
};
