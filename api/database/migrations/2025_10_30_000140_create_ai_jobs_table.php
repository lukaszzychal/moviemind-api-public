<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_jobs', function (Blueprint $table) {
            $table->id();
            $table->string('entity_type', 16); // MOVIE, ACTOR
            $table->unsignedBigInteger('entity_id');
            $table->string('locale', 10)->nullable();
            $table->string('status', 16)->default('PENDING'); // PENDING, DONE, FAILED
            $table->json('payload_json')->nullable();
            $table->timestamps();

            $table->index(['entity_type', 'entity_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_jobs');
    }
};


