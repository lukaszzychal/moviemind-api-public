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
        Schema::create('movie_relationships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('movie_id')->constrained('movies')->cascadeOnDelete();
            $table->foreignId('related_movie_id')->constrained('movies')->cascadeOnDelete();
            $table->string('relationship_type', 20);
            $table->unsignedSmallInteger('order')->nullable();
            $table->timestamps();

            $table->index('movie_id');
            $table->index('related_movie_id');
            $table->index('relationship_type');
            $table->index(['movie_id', 'relationship_type']);
            $table->unique(['movie_id', 'related_movie_id', 'relationship_type'], 'unique_movie_relationship');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('movie_relationships');
    }
};
