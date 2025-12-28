<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('webhook_events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('event_type', 100)->index()->comment('Type of webhook: billing, notification, etc.');
            $table->string('source', 100)->index()->comment('Source of webhook: rapidapi, stripe, etc.');
            $table->json('payload')->comment('Webhook payload data');
            $table->enum('status', ['pending', 'processing', 'processed', 'failed', 'permanently_failed'])->default('pending')->index();
            $table->unsignedTinyInteger('attempts')->default(0)->comment('Number of processing attempts');
            $table->unsignedTinyInteger('max_attempts')->default(3)->comment('Maximum retry attempts');
            $table->string('idempotency_key', 255)->nullable()->index()->comment('Prevent duplicate processing');
            $table->text('error_message')->nullable()->comment('Error message if processing failed');
            $table->json('error_context')->nullable()->comment('Additional error context');
            $table->timestamp('processed_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamp('next_retry_at')->nullable()->index()->comment('Next retry attempt time');
            $table->timestamps();

            $table->index(['status', 'next_retry_at']);
            $table->index(['event_type', 'status']);
            $table->unique('idempotency_key');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_events');
    }
};
