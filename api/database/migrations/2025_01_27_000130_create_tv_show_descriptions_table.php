<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tv_show_descriptions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tv_show_id')->constrained('tv_shows')->cascadeOnDelete();
            $table->string('locale', 10);
            $table->text('text');
            $table->string('context_tag', 64)->nullable();
            $table->string('origin', 32)->default('GENERATED');
            $table->string('ai_model', 64)->nullable();
            $table->unsignedInteger('version_number')->default(1);
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();
            $table->unique(['tv_show_id', 'locale', 'context_tag']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tv_show_descriptions');
    }
};
