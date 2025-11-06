<?php

use Illuminate\Support\Facades\Route;

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
