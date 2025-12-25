<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('api_keys', function (Blueprint $table) {
            // Use UUID for primary key (UUIDv7 via HasUuids trait)
            $table->uuid('id')->primary();
            $table->string('key', 255)->unique()->comment('Hashed API key');
            $table->string('key_prefix', 20)->index()->comment('First 8 characters of plaintext key for quick lookup');
            $table->string('name')->comment('Description/label for the API key');
            $table->uuid('user_id')->nullable()->index()->comment('For future user association');
            // plan_id will be added as FK constraint in TASK-RAPI-002 when subscription_plans table exists
            $table->uuid('plan_id')->nullable()->index()->comment('Associated subscription plan');
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index(['is_active', 'expires_at']);
            $table->index('last_used_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_keys');
    }
};
