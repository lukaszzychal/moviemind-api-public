<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('api_usage', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('api_key_id')->index();
            $table->uuid('plan_id')->nullable()->index();
            $table->string('endpoint', 255)->comment('API endpoint path');
            $table->string('method', 10)->default('GET')->comment('HTTP method');
            $table->integer('response_status')->default(200)->comment('HTTP response status');
            $table->integer('response_time_ms')->nullable()->comment('Response time in milliseconds');
            $table->string('month', 7)->index()->comment('Month in YYYY-MM format');
            $table->timestamps();

            // Composite indexes for efficient queries
            $table->index(['api_key_id', 'month']);
            $table->index(['created_at']);
            $table->index(['plan_id', 'month']);

            // Foreign keys
            $table->foreign('api_key_id')
                ->references('id')
                ->on('api_keys')
                ->onDelete('cascade');

            $table->foreign('plan_id')
                ->references('id')
                ->on('subscription_plans')
                ->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_usage');
    }
};
