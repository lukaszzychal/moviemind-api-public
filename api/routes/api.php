<?php

use App\Http\Controllers\Admin\FlagController;
use App\Http\Controllers\Api\GenerateController;
use App\Http\Controllers\Api\HealthController;
use App\Http\Controllers\Api\JobsController;
use App\Http\Controllers\Api\MovieController;
use App\Http\Controllers\Api\PersonController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::get('movies', [MovieController::class, 'index']);
    Route::get('movies/{slug}', [MovieController::class, 'show']);
    Route::get('people/{slug}', [PersonController::class, 'show']);
    Route::post('generate', [GenerateController::class, 'generate']);
    Route::get('jobs/{id}', [JobsController::class, 'show']);
    Route::get('health/openai', [HealthController::class, 'openAi']);
});

Route::prefix('v1/admin/flags')->group(function () {
    Route::get('/', [FlagController::class, 'index']);
    Route::post('{name}', [FlagController::class, 'setFlag']); // body: {state:on|off}
    Route::get('usage', [FlagController::class, 'usage']);
});
