<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('outgoing_webhooks', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('event_type', 100)->index()->comment('Type of event: generation.completed, etc.');
            $table->json('payload')->comment('Webhook payload data');
            $table->string('url', 500)->comment('Webhook URL to send to');
            $table->enum('status', ['pending', 'sent', 'failed', 'permanently_failed'])->default('pending')->index();
            $table->unsignedTinyInteger('attempts')->default(0)->comment('Number of delivery attempts');
            $table->unsignedTinyInteger('max_attempts')->default(3)->comment('Maximum retry attempts');
            $table->unsignedSmallInteger('response_code')->nullable()->comment('HTTP response code');
            $table->json('response_body')->nullable()->comment('Response body from webhook endpoint');
            $table->text('error_message')->nullable()->comment('Error message if delivery failed');
            $table->timestamp('sent_at')->nullable()->comment('When webhook was successfully sent');
            $table->timestamp('next_retry_at')->nullable()->index()->comment('Next retry attempt time');
            $table->timestamps();

            $table->index(['status', 'next_retry_at']);
            $table->index(['event_type', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('outgoing_webhooks');
    }
};
