<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscription_plans', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name', 50)->unique()->comment('Internal name: free, pro, enterprise');
            $table->string('display_name', 100)->comment('Display name: Free, Pro, Enterprise');
            $table->text('description')->nullable();
            $table->integer('monthly_limit')->default(0)->comment('Monthly request limit (0 = unlimited)');
            $table->integer('rate_limit_per_minute')->default(10)->comment('Rate limit per minute');
            $table->json('features')->default('[]')->comment('Available features: read, generate, context_tags, webhooks, analytics');
            $table->decimal('price_monthly', 10, 2)->nullable()->comment('Monthly price in USD');
            $table->decimal('price_yearly', 10, 2)->nullable()->comment('Yearly price in USD');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('is_active');
            $table->index('name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_plans');
    }
};
