<?php

use App\Http\Controllers\Admin\FlagController;
use App\Http\Controllers\Api\ActorController;
use App\Http\Controllers\Api\GenerateController;
use App\Http\Controllers\Api\JobsController;
use App\Http\Controllers\Api\MovieController;
use App\Http\Controllers\Api\PersonController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::get('movies', [MovieController::class, 'index']);
    Route::get('movies/{slug}', [MovieController::class, 'show']);
    Route::get('actors/{id}', [ActorController::class, 'show']);
    Route::get('people/{slug}', [PersonController::class, 'show']);
    Route::post('generate', [GenerateController::class, 'generate']);
    Route::get('jobs/{id}', [JobsController::class, 'show']);
});

Route::prefix('v1/admin/flags')->group(function () {
    Route::get('/', [FlagController::class, 'index']);
    Route::post('{name}', [FlagController::class, 'setFlag']); // body: {state:on|off}
    Route::get('usage', [FlagController::class, 'usage']);
});

Route::get('/', function () {
    return response()->json([
        'name' => config('app.name', 'MovieMind API'),
        'version' => '1.0.0',
        'status' => 'ok',
        'environment' => config('app.env', 'production'),
        'endpoints' => [
            'api' => '/api/v1',
            'health' => '/up',
            'movies' => '/api/v1/movies',
            'people' => '/api/v1/people',
            'generate' => '/api/v1/generate',
            'jobs' => '/api/v1/jobs',
        ],
        'documentation' => [
            'openapi' => '/api/doc',
            'postman' => 'docs/postman/moviemind-api.postman_collection.json',
            'insomnia' => 'docs/insomnia/moviemind-api-insomnia.json',
        ],
    ], 200, [
        'Content-Type' => 'application/json',
    ]);
});
