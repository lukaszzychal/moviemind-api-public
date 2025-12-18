<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('movies', function (Blueprint $table) {
            // Use UUID for primary key (UUIDv7 via HasUuids trait)
            $table->uuid('id')->primary();
            $table->string('title');
            $table->smallInteger('release_year')->nullable();
            $table->string('director')->nullable();
            $table->json('genres')->nullable();
            $table->uuid('default_description_id')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('movies');
    }
};
