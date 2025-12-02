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

// API Documentation (Swagger UI)
Route::get('/api/doc', function () {
    return response()->file(public_path('docs/index.html'));
});

// OpenAPI Specification (dynamically generated with current host)
Route::get('/api/docs/openapi.yaml', function () {
    $basePath = public_path('docs/openapi.yaml');
    $yamlContent = file_get_contents($basePath);

    // Get current host
    $currentHost = request()->getSchemeAndHttpHost();
    $currentUrl = $currentHost.'/api';
    $env = config('app.env', 'production');
    $hostName = parse_url($currentHost, PHP_URL_HOST) ?? 'unknown';

    // Build servers section dynamically
    $serversYaml = "servers:\n";
    $serversYaml .= "  - url: http://localhost:8000/api\n";
    $serversYaml .= "    description: Local\n";

    // Add current host if not localhost
    if (! str_contains($currentHost, 'localhost') && ! str_contains($currentHost, '127.0.0.1')) {
        $serversYaml .= "  - url: {$currentUrl}\n";
        $serversYaml .= '    description: '.ucfirst($env)." ({$hostName})\n";
    }

    // Add production example (if not current)
    if ($currentUrl !== 'https://api.example.com/api') {
        $serversYaml .= "  - url: https://api.example.com/api\n";
        $serversYaml .= "    description: Production (example)\n";
    }

    // Replace servers section in YAML using regex
    // Match from "servers:" to next top-level key (starts at beginning of line)
    $pattern = '/^servers:.*?(?=\n[a-z]+:)/ms';
    $yamlContent = preg_replace($pattern, $serversYaml, $yamlContent, 1);

    return response($yamlContent, 200, [
        'Content-Type' => 'application/x-yaml',
    ]);
});
