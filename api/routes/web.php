<?php

use App\Models\ApiKey;
use Illuminate\Support\Facades\Route;

// OpenAPI Documentation routes (public, no authentication required)
Route::get('api/doc', function () {
    return response()->file(public_path('docs/index.html'));
});

Route::get('api/docs/openapi.yaml', function () {
    return response()->file(public_path('docs/openapi.yaml'), [
        'Content-Type' => 'application/x-yaml',
    ]);
});

// Welcome endpoint
Route::get('/', function () {
    $baseUrl = request()->getSchemeAndHttpHost();

    $response = [
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
    ];

    // Show public demo API key if available (for portfolio/demo purposes only)
    $publicKey = ApiKey::where('is_public', true)
        ->where('is_active', true)
        ->whereNotNull('public_plaintext_key')
        ->first();

    if ($publicKey !== null) {
        $response['demo'] = [
            'api_key' => $publicKey->public_plaintext_key,
            'plan' => $publicKey->plan?->name ?? 'free',
            'note' => 'This is a public demo key for portfolio/testing purposes. Rate limits apply.',
        ];
    }

    return response()->json($response, 200, ['Content-Type' => 'application/json']);
});
