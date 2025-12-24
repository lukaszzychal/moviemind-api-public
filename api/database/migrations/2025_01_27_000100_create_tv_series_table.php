<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tv_series', function (Blueprint $table) {
            // Use UUID for primary key (UUIDv7 via HasUuids trait)
            $table->uuid('id')->primary();
            $table->string('title');
            $table->string('slug')->unique();
            $table->date('first_air_date')->nullable();
            $table->date('last_air_date')->nullable();
            $table->unsignedSmallInteger('number_of_seasons')->nullable();
            $table->unsignedInteger('number_of_episodes')->nullable();
            $table->json('genres')->nullable();
            $table->unsignedInteger('tmdb_id')->nullable()->index();
            $table->uuid('default_description_id')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tv_series');
    }
};
