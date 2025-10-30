<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('actor_bios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('actor_id')->constrained('actors')->cascadeOnDelete();
            $table->string('locale', 10);
            $table->text('text');
            $table->string('context_tag', 64)->nullable();
            $table->string('origin', 32)->default('GENERATED');
            $table->string('ai_model', 64)->nullable();
            $table->timestamps();
            $table->unique(['actor_id', 'locale', 'context_tag']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('actor_bios');
    }
};


