<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('api_keys', function (Blueprint $table) {
            $table->foreign('plan_id')
                ->references('id')
                ->on('subscription_plans')
                ->onDelete('set null'); // Don't delete API keys if plan is deleted, just set plan_id to null
        });
    }

    public function down(): void
    {
        Schema::table('api_keys', function (Blueprint $table) {
            $table->dropForeign(['plan_id']);
        });
    }
};
