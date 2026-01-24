<?php

use Illuminate\Support\Facades\Route;

// OpenAPI Documentation routes (public, no authentication required)
Route::get('api/doc', function () {
    return response()->file(public_path('docs/index.html'));
});

Route::get('api/docs/openapi.yaml', function () {
    return response()->file(public_path('docs/openapi.yaml'))
        ->header('Content-Type', 'application/x-yaml');
});

// Welcome endpoint
Route::get('/', function () {
    // ... (rest of the file remains the same)
    $baseUrl = request()->getSchemeAndHttpHost();

    return response()->json([
        'message' => 'Welcome to MovieMind API',
        'status' => 'ok',
        'version' => '1.0.0',
        'api' => '/api/v1',
        'resources' => [
            'movies' => ['url' => $baseUrl.'/api/v1/movies', 'description' => 'List and search movies'],
            'people' => ['url' => $baseUrl.'/api/v1/people', 'description' => 'List and search people'],
            'tv_series' => ['url' => $baseUrl.'/api/v1/tv-series', 'description' => 'List and search TV series'],
            'tv_shows' => ['url' => $baseUrl.'/api/v1/tv-shows', 'description' => 'List and search TV shows'],
            'generate' => ['url' => $baseUrl.'/api/v1/generate', 'description' => 'Generate AI content'],
            'health' => ['url' => $baseUrl.'/api/v1/health/openai', 'description' => 'Check API health'],
        ],
        'documentation' => [
            'openapi' => $baseUrl.'/api/docs/openapi.yaml',
            'swagger_ui' => $baseUrl.'/api/doc',
        ],
    ], 200, ['Content-Type' => 'application/json']);
});
