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
        Schema::create('movie_reports', function (Blueprint $table) {
            // Use UUID for primary key (UUIDv7 via HasUuids trait)
            $table->uuid('id')->primary();
            $table->foreignUuid('movie_id')->constrained('movies')->cascadeOnDelete();
            $table->foreignUuid('description_id')->nullable()->constrained('movie_descriptions')->nullOnDelete();
            $table->string('type'); // ReportType enum value
            $table->text('message');
            $table->text('suggested_fix')->nullable();
            $table->string('status')->default('pending'); // ReportStatus enum value
            $table->decimal('priority_score', 10, 2)->default(0.0)->index();
            $table->uuid('verified_by')->nullable(); // User ID (if we have users table in future)
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            // Indexes for filtering and sorting
            $table->index(['status', 'priority_score']);
            $table->index(['movie_id', 'status']);
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('movie_reports');
    }
};
