<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration to make ai_jobs.entity_id nullable.
 *
 * entity_id can be null if entity doesn't exist yet (will be updated when entity is created).
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('ai_jobs', function (Blueprint $table) {
            $table->uuid('entity_id')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ai_jobs', function (Blueprint $table) {
            $table->uuid('entity_id')->nullable(false)->change();
        });
    }
};
