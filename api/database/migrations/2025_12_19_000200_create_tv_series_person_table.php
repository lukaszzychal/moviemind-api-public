<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tv_series_person', function (Blueprint $table) {
            $table->foreignUuid('tv_series_id')->constrained('tv_series')->cascadeOnDelete();
            $table->foreignUuid('person_id')->constrained('people')->cascadeOnDelete();
            $table->string('role', 16); // ACTOR, DIRECTOR, WRITER, PRODUCER, CREATOR, EXECUTIVE_PRODUCER
            $table->string('character_name')->nullable(); // for ACTOR
            $table->string('job')->nullable(); // for crew
            $table->unsignedSmallInteger('billing_order')->nullable();
            $table->primary(['tv_series_id', 'person_id', 'role']);
            $table->index(['role', 'billing_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tv_series_person');
    }
};
