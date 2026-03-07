<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('api_keys', function (Blueprint $table) {
            // Flag to mark a key as publicly visible (e.g. demo/portfolio key)
            $table->boolean('is_public')->default(false)->after('is_active');
            // Store plaintext ONLY for public demo keys (portfolio use).
            // Regular keys never store plaintext — only hash.
            $table->string('public_plaintext_key', 255)->nullable()->after('is_public');
        });
    }

    public function down(): void
    {
        Schema::table('api_keys', function (Blueprint $table) {
            $table->dropColumn(['is_public', 'public_plaintext_key']);
        });
    }
};
