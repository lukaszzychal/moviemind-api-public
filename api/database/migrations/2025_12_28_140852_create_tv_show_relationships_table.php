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
        Schema::create('tv_show_relationships', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tv_show_id')->constrained('tv_shows')->cascadeOnDelete();
            $table->foreignUuid('related_tv_show_id')->constrained('tv_shows')->cascadeOnDelete();
            $table->string('relationship_type', 20);
            $table->unsignedSmallInteger('order')->nullable();
            $table->timestamps();

            $table->index('tv_show_id');
            $table->index('related_tv_show_id');
            $table->index('relationship_type');
            $table->index(['tv_show_id', 'relationship_type']);
            $table->unique(['tv_show_id', 'related_tv_show_id', 'relationship_type'], 'unique_tv_show_relationship');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tv_show_relationships');
    }
};
