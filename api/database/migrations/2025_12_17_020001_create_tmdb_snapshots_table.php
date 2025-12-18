<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('tmdb_snapshots', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('entity_type', 20)->comment('MOVIE, PERSON, TV_SERIES, etc.');
            $table->uuid('entity_id')->comment('FK to movies/people/etc (UUID)');
            $table->unsignedInteger('tmdb_id')->comment('TMDb ID');
            $table->string('tmdb_type', 20)->comment('movie, person, tv');
            $table->jsonb('raw_data')->comment('Full TMDb response');
            $table->timestamp('fetched_at')->comment('When data was fetched from TMDb');
            $table->timestamps();

            $table->unique(['entity_type', 'entity_id', 'tmdb_id'], 'tmdb_snapshots_unique');
            $table->index(['entity_type', 'entity_id'], 'tmdb_snapshots_entity_idx');
            $table->index('tmdb_id', 'tmdb_snapshots_tmdb_id_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tmdb_snapshots');
    }
};
