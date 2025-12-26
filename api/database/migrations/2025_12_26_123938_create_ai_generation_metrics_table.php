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
        Schema::create('ai_generation_metrics', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('job_id')->nullable()->index()->comment('Link to jobs table');
            $table->string('entity_type', 50)->comment('MOVIE, PERSON, TV_SERIES, TV_SHOW');
            $table->string('entity_slug', 255)->index();
            $table->string('data_format', 10)->comment('JSON, TOON, CSV');
            $table->integer('prompt_tokens')->comment('Tokeny w promptcie');
            $table->integer('completion_tokens')->comment('Tokeny w odpowiedzi');
            $table->integer('total_tokens')->comment('Razem tokenów');
            $table->integer('bytes_sent')->nullable()->comment('Rozmiar w bajtach (dla porównania)');
            $table->decimal('token_savings_vs_json', 5, 2)->nullable()->comment('Oszczędności vs JSON baseline');
            $table->boolean('parsing_successful')->default(true)->comment('Czy parsowanie się powiodło');
            $table->text('parsing_errors')->nullable()->comment('Błędy parsowania (JSON)');
            $table->json('validation_errors')->nullable()->comment('Błędy walidacji struktury');
            $table->integer('response_time_ms')->nullable();
            $table->string('model', 50)->default('gpt-4o-mini');
            $table->timestamps();

            // Indexes for efficient queries
            $table->index(['entity_type', 'data_format']);
            $table->index(['created_at']);
            $table->index(['parsing_successful']);
            $table->index(['entity_slug', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_generation_metrics');
    }
};
