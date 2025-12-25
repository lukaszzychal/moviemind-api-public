<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * ApiUsage model.
 *
 * @author MovieMind API Team
 *
 * @property string $id UUIDv7 primary key
 * @property string $api_key_id API key ID
 * @property string|null $plan_id Subscription plan ID
 * @property string $endpoint API endpoint path
 * @property string $method HTTP method
 * @property int $response_status HTTP response status
 * @property int|null $response_time_ms Response time in milliseconds
 * @property string $month Month in YYYY-MM format
 */
class ApiUsage extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'api_usage';

    protected $fillable = [
        'api_key_id',
        'plan_id',
        'endpoint',
        'method',
        'response_status',
        'response_time_ms',
        'month',
    ];

    protected $casts = [
        'response_status' => 'integer',
        'response_time_ms' => 'integer',
    ];

    /**
     * Get the API key for this usage record.
     */
    public function apiKey(): BelongsTo
    {
        return $this->belongsTo(ApiKey::class, 'api_key_id');
    }

    /**
     * Get the subscription plan for this usage record.
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class, 'plan_id');
    }
}
