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
    $possiblePaths = [
        public_path('docs/index.html'),
        base_path('public/docs/index.html'),
    ];

    foreach ($possiblePaths as $filePath) {
        if (file_exists($filePath)) {
            return response()->file($filePath);
        }
    }

    // Fallback: return inline HTML if file doesn't exist
    $html = <<<'HTML'
<!doctype html>
<html>
<head>
  <meta charset="utf-8" />
  <title>MovieMind API Docs</title>
  <link rel="stylesheet" href="https://unpkg.com/swagger-ui-dist@5/swagger-ui.css" />
  <style>
    html, body { margin:0; padding:0; height:100%; }
    #swagger-ui { height:100%; }
  </style>
</head>
<body>
  <div id="swagger-ui"></div>
  <script src="https://unpkg.com/swagger-ui-dist@5/swagger-ui-bundle.js"></script>
  <script>
    window.onload = () => {
      window.ui = SwaggerUIBundle({
        url: '/api/docs/openapi.yaml',
        dom_id: '#swagger-ui',
        presets: [SwaggerUIBundle.presets.apis],
      });
    };
  </script>
</body>
</html>
HTML;

    return response($html, 200, ['Content-Type' => 'text/html']);
});

// OpenAPI Specification
Route::get('/api/docs/openapi.yaml', function () {
    $possiblePaths = [
        public_path('docs/openapi.yaml'),
        base_path('public/docs/openapi.yaml'),
    ];

    foreach ($possiblePaths as $filePath) {
        if (file_exists($filePath)) {
            return response()->file($filePath, [
                'Content-Type' => 'application/x-yaml',
            ]);
        }
    }

    // Fallback: try to read from docs directory in project root
    $fallbackPath = base_path('docs/openapi.yaml');
    if (file_exists($fallbackPath)) {
        return response()->file($fallbackPath, [
            'Content-Type' => 'application/x-yaml',
        ]);
    }

    abort(404, 'OpenAPI specification not found');
});
