<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('people', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->date('birth_date')->nullable();
            $table->string('birthplace')->nullable();
            $table->timestamps();
        });

        Schema::create('movie_person', function (Blueprint $table) {
            $table->foreignUuid('movie_id')->constrained('movies')->cascadeOnDelete();
            $table->foreignUuid('person_id')->constrained('people')->cascadeOnDelete();
            $table->string('role', 16); // ACTOR, DIRECTOR, WRITER, PRODUCER
            $table->string('character_name')->nullable(); // for ACTOR
            $table->string('job')->nullable(); // for crew
            $table->unsignedSmallInteger('billing_order')->nullable();
            $table->primary(['movie_id', 'person_id', 'role']);
            $table->index(['role', 'billing_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('movie_person');
        Schema::dropIfExists('people');
    }
};
