<?php

use App\Http\Controllers\Admin\ApiKeyController;
use App\Http\Controllers\Admin\FlagController;
use App\Http\Controllers\Admin\WebhookSubscriptionController;
use App\Http\Controllers\Api\GenerateController;
use App\Http\Controllers\Api\HealthController;
use App\Http\Controllers\Api\JobsController;
use App\Http\Controllers\Api\MovieController;
use App\Http\Controllers\Api\PersonController;
use App\Http\Controllers\Api\TvSeriesController;
use App\Http\Controllers\Api\TvShowController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::get('movies', [MovieController::class, 'index']);
    Route::get('movies/search', [MovieController::class, 'search'])->middleware('adaptive.rate.limit:search');
    Route::post('movies/bulk', [MovieController::class, 'bulk'])->middleware('adaptive.rate.limit:bulk');
    Route::get('movies/compare', [MovieController::class, 'compare']);
    Route::get('movies/{slug}', [MovieController::class, 'show'])->middleware('adaptive.rate.limit:show');
    Route::get('movies/{slug}/related', [MovieController::class, 'related']);
    Route::get('movies/{slug}/collection', [MovieController::class, 'collection']);
    Route::post('movies/{slug}/refresh', [MovieController::class, 'refresh']);
    Route::post('movies/{slug}/report', [MovieController::class, 'report'])->middleware('adaptive.rate.limit:report');
    Route::get('people', [PersonController::class, 'index']);
    Route::get('people/search', [PersonController::class, 'search'])->middleware('adaptive.rate.limit:search');
    Route::post('people/bulk', [PersonController::class, 'bulk'])->middleware('adaptive.rate.limit:bulk');
    Route::get('people/compare', [PersonController::class, 'compare']);
    Route::get('people/{slug}', [PersonController::class, 'show'])->middleware('adaptive.rate.limit:show');
    Route::get('people/{slug}/related', [PersonController::class, 'related']);
    Route::post('people/{slug}/refresh', [PersonController::class, 'refresh']);
    Route::post('people/{slug}/report', [PersonController::class, 'report'])->middleware('adaptive.rate.limit:report');
    Route::get('tv-series', [TvSeriesController::class, 'index']);
    Route::get('tv-series/search', [TvSeriesController::class, 'search'])->middleware('adaptive.rate.limit:search');
    Route::get('tv-series/compare', [TvSeriesController::class, 'compare']);
    Route::get('tv-series/{slug}', [TvSeriesController::class, 'show'])->middleware('adaptive.rate.limit:show');
    Route::get('tv-series/{slug}/related', [TvSeriesController::class, 'related']);
    Route::post('tv-series/{slug}/refresh', [TvSeriesController::class, 'refresh']);
    Route::post('tv-series/{slug}/report', [TvSeriesController::class, 'report'])->middleware('adaptive.rate.limit:report');
    Route::get('tv-shows', [TvShowController::class, 'index']);
    Route::get('tv-shows/search', [TvShowController::class, 'search'])->middleware('adaptive.rate.limit:search');
    Route::get('tv-shows/compare', [TvShowController::class, 'compare']);
    Route::get('tv-shows/{slug}', [TvShowController::class, 'show'])->middleware('adaptive.rate.limit:show');
    Route::get('tv-shows/{slug}/related', [TvShowController::class, 'related']);
    Route::post('tv-shows/{slug}/refresh', [TvShowController::class, 'refresh']);
    Route::post('tv-shows/{slug}/report', [TvShowController::class, 'report'])->middleware('adaptive.rate.limit:report');
    Route::post('generate', [GenerateController::class, 'generate'])->middleware([
        'adaptive.rate.limit:generate',
        'api.key.auth',
        'plan.rate.limit',
        'plan.feature:ai_generate',
    ]);
    Route::get('jobs/{id}', [JobsController::class, 'show']);
    Route::get('health', [HealthController::class, 'health']);
    Route::get('health/openai', [HealthController::class, 'openAi']);
    Route::get('health/tmdb', [HealthController::class, 'tmdb']);
    Route::get('health/tvmaze', [HealthController::class, 'tvmaze']);
    Route::get('health/instance', [HealthController::class, 'instance']);
    Route::get('health/db', [HealthController::class, 'database']);
});

Route::prefix('v1/admin')->middleware('admin.token')->group(function () {
    Route::prefix('flags')->group(function () {
        Route::get('/', [FlagController::class, 'index']);
        Route::get('overrides', [FlagController::class, 'overrides']); // New endpoint
        Route::post('{name}', [FlagController::class, 'setFlag']);
        Route::delete('{name}', [FlagController::class, 'resetFlag']);
        Route::get('usage', [FlagController::class, 'usage']);
    });
    Route::prefix('instances')->group(function () {
        Route::get('/', [\App\Http\Controllers\Admin\InstanceController::class, 'index']);
        Route::get('{instanceId}', [\App\Http\Controllers\Admin\InstanceController::class, 'show']);
    });
    Route::prefix('api-keys')->group(function () {
        Route::get('/', [ApiKeyController::class, 'index']);
        Route::post('/', [ApiKeyController::class, 'store']);
        Route::post('{id}/revoke', [ApiKeyController::class, 'revoke']);
        Route::post('{id}/regenerate', [ApiKeyController::class, 'regenerate']);
    });
    Route::prefix('reports')->group(function () {
        Route::get('/', [\App\Http\Controllers\Admin\ReportController::class, 'index']);
        Route::post('{id}/verify', [\App\Http\Controllers\Admin\ReportController::class, 'verify']);
    });
    Route::prefix('analytics')->group(function () {
        Route::get('/overview', [\App\Http\Controllers\Admin\AnalyticsController::class, 'overview']);
        Route::get('/by-plan', [\App\Http\Controllers\Admin\AnalyticsController::class, 'byPlan']);
        Route::get('/by-endpoint', [\App\Http\Controllers\Admin\AnalyticsController::class, 'byEndpoint']);
        Route::get('/by-time-range', [\App\Http\Controllers\Admin\AnalyticsController::class, 'byTimeRange']);
        Route::get('/top-api-keys', [\App\Http\Controllers\Admin\AnalyticsController::class, 'topApiKeys']);
        Route::get('/error-rate', [\App\Http\Controllers\Admin\AnalyticsController::class, 'errorRate']);
    });
    Route::prefix('ai-metrics')->group(function () {
        Route::get('/token-usage', [\App\Http\Controllers\Admin\AiMetricsController::class, 'tokenUsage']);
        Route::get('/parsing-accuracy', [\App\Http\Controllers\Admin\AiMetricsController::class, 'parsingAccuracy']);
        Route::get('/errors', [\App\Http\Controllers\Admin\AiMetricsController::class, 'errorStatistics']);
        Route::get('/comparison', [\App\Http\Controllers\Admin\AiMetricsController::class, 'formatComparison']);
    });
    Route::prefix('jobs-dashboard')->group(function () {
        Route::get('/overview', [\App\Http\Controllers\Admin\JobsDashboardController::class, 'overview']);
        Route::get('/by-queue', [\App\Http\Controllers\Admin\JobsDashboardController::class, 'byQueue']);
        Route::get('/recent', [\App\Http\Controllers\Admin\JobsDashboardController::class, 'recent']);
        Route::get('/failed', [\App\Http\Controllers\Admin\JobsDashboardController::class, 'failed']);
        Route::get('/failed/stats', [\App\Http\Controllers\Admin\JobsDashboardController::class, 'failedStats']);
        Route::get('/processing-times', [\App\Http\Controllers\Admin\JobsDashboardController::class, 'processingTimes']);
    });
    Route::prefix('subscription-plans')->group(function () {
        Route::get('/', [\App\Http\Controllers\Admin\SubscriptionPlanController::class, 'index']);
        Route::get('{id}', [\App\Http\Controllers\Admin\SubscriptionPlanController::class, 'show']);
        Route::get('{id}/features', [\App\Http\Controllers\Admin\SubscriptionPlanController::class, 'getFeatures']);
        Route::post('{id}/features', [\App\Http\Controllers\Admin\SubscriptionPlanController::class, 'addFeature']);
        Route::delete('{id}/features/{feature}', [\App\Http\Controllers\Admin\SubscriptionPlanController::class, 'removeFeature']);
    });
    Route::prefix('webhook-subscriptions')->group(function () {
        Route::get('/', [WebhookSubscriptionController::class, 'index']);
        Route::post('/', [WebhookSubscriptionController::class, 'store']);
        Route::patch('{id}', [WebhookSubscriptionController::class, 'update']);
        Route::delete('{id}', [WebhookSubscriptionController::class, 'destroy']);
    });
    Route::get('debug/config', [HealthController::class, 'debugConfig']);
});

// Billing webhooks (no auth required - uses signature verification)
Route::post('v1/webhooks/billing', [\App\Http\Controllers\Admin\BillingWebhookController::class, 'handle']);

// Notification webhooks (no auth required - uses signature verification)
Route::post('v1/webhooks/notification', [\App\Http\Controllers\Admin\NotificationWebhookController::class, 'handle']);
