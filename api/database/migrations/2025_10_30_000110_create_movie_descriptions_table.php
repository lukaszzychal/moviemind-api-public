<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('movie_descriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('movie_id')->constrained('movies')->cascadeOnDelete();
            $table->string('locale', 10);
            $table->text('text');
            $table->string('context_tag', 64)->nullable();
            $table->string('origin', 32)->default('GENERATED');
            $table->string('ai_model', 64)->nullable();
            $table->timestamps();
            $table->unique(['movie_id', 'locale', 'context_tag']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('movie_descriptions');
    }
};


