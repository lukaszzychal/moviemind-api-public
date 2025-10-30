<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\MovieController;
use App\Http\Controllers\Api\ActorController;
use App\Http\Controllers\Api\GenerateController;
use App\Http\Controllers\Api\JobsController;

Route::prefix('v1')->group(function () {
    Route::get('movies', [MovieController::class, 'index']);
    Route::get('movies/{id}', [MovieController::class, 'show']);
    Route::get('actors/{id}', [ActorController::class, 'show']);
    Route::post('generate', [GenerateController::class, 'generate']);
    Route::get('jobs/{id}', [JobsController::class, 'show']);
});


