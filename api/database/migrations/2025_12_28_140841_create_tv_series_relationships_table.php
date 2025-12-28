<?php

declare(strict_types=1);

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
        Schema::create('tv_series_relationships', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tv_series_id')->constrained('tv_series')->cascadeOnDelete();
            $table->foreignUuid('related_tv_series_id')->constrained('tv_series')->cascadeOnDelete();
            $table->string('relationship_type', 20);
            $table->unsignedSmallInteger('order')->nullable();
            $table->timestamps();

            $table->index('tv_series_id');
            $table->index('related_tv_series_id');
            $table->index('relationship_type');
            $table->index(['tv_series_id', 'relationship_type']);
            $table->unique(['tv_series_id', 'related_tv_series_id', 'relationship_type'], 'unique_tv_series_relationship');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tv_series_relationships');
    }
};
