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
    Route::get('movies/search', [MovieController::class, 'search'])->middleware('adaptive.rate.limit:search');
    Route::get('movies/{slug}', [MovieController::class, 'show'])->middleware('adaptive.rate.limit:show');
    Route::get('movies/{slug}/related', [MovieController::class, 'related']);
    Route::post('movies/{slug}/refresh', [MovieController::class, 'refresh']);
    Route::post('movies/{slug}/report', [MovieController::class, 'report'])->middleware('adaptive.rate.limit:report');
    Route::get('people', [PersonController::class, 'index']);
    Route::get('people/{slug}', [PersonController::class, 'show']);
    Route::post('people/{slug}/refresh', [PersonController::class, 'refresh']);
    Route::post('generate', [GenerateController::class, 'generate'])->middleware('adaptive.rate.limit:generate');
    Route::get('jobs/{id}', [JobsController::class, 'show']);
    Route::get('health/openai', [HealthController::class, 'openAi']);
    Route::get('health/tmdb', [HealthController::class, 'tmdb']);
});

Route::prefix('v1/admin')->middleware('admin.basic')->group(function () {
    Route::prefix('flags')->group(function () {
        Route::get('/', [FlagController::class, 'index']);
        Route::post('{name}', [FlagController::class, 'setFlag']); // body: {state:on|off}
        Route::get('usage', [FlagController::class, 'usage']);
    });
    Route::prefix('reports')->group(function () {
        Route::get('/', [\App\Http\Controllers\Admin\ReportController::class, 'index']);
        Route::post('{id}/verify', [\App\Http\Controllers\Admin\ReportController::class, 'verify']);
    });
    Route::get('debug/config', [HealthController::class, 'debugConfig']);
});
