<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('movie_locales', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('movie_id')->constrained('movies')->cascadeOnDelete();
            $table->string('locale', 10);
            $table->string('title_localized', 255)->nullable();
            $table->string('director_localized', 255)->nullable();
            $table->text('tagline')->nullable();
            $table->text('synopsis')->nullable();
            $table->timestamps();

            // Unique constraint: one locale per movie
            $table->unique(['movie_id', 'locale']);

            // Indexes for faster lookups
            $table->index('movie_id');
            $table->index('locale');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('movie_locales');
    }
};
