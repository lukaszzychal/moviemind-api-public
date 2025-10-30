<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\MovieController;
use App\Http\Controllers\Api\ActorController;
use App\Http\Controllers\Api\GenerateController;
use App\Http\Controllers\Api\JobsController;
use App\Http\Controllers\Api\PersonController;
use App\Http\Controllers\Admin\FlagController;

Route::prefix('v1')->group(function () {
    Route::get('movies', [MovieController::class, 'index']);
    Route::get('movies/{id}', [MovieController::class, 'show']);
    Route::get('actors/{id}', [ActorController::class, 'show']);
    Route::get('people/{id}', [PersonController::class, 'show']);
    Route::post('generate', [GenerateController::class, 'generate']);
    Route::get('jobs/{id}', [JobsController::class, 'show']);
});

Route::prefix('v1/admin/flags')->group(function () {
    Route::get('/', [FlagController::class, 'index']);
    Route::post('{name}', [FlagController::class, 'setFlag']); // body: {state:on|off}
    Route::get('usage', [FlagController::class, 'usage']);
});


