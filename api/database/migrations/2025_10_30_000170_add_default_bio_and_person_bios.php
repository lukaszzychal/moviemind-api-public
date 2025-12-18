<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('people', function (Blueprint $table) {
            $table->uuid('default_bio_id')->nullable()->after('birthplace')->index();
        });

        Schema::create('person_bios', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('person_id')->constrained('people')->cascadeOnDelete();
            $table->string('locale', 10);
            $table->text('text');
            $table->string('context_tag', 64)->nullable();
            $table->string('origin', 32)->default('GENERATED');
            $table->string('ai_model', 64)->nullable();
            $table->timestamps();
            $table->unique(['person_id', 'locale', 'context_tag']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('person_bios');
        Schema::table('people', function (Blueprint $table) {
            $table->dropColumn('default_bio_id');
        });
    }
};
