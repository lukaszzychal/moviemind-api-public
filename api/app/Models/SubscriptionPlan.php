<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * SubscriptionPlan model.
 *
 * @author MovieMind API Team
 *
 * @property string $id UUIDv7 primary key
 * @property string $name Internal name (free, pro, enterprise)
 * @property string $display_name Display name (Free, Pro, Enterprise)
 * @property string|null $description Plan description
 * @property int $monthly_limit Monthly request limit (0 = unlimited)
 * @property int $rate_limit_per_minute Rate limit per minute
 * @property array $features Available features
 * @property float|null $price_monthly Monthly price in USD
 * @property float|null $price_yearly Yearly price in USD
 * @property bool $is_active Whether the plan is active
 */
class SubscriptionPlan extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'name',
        'display_name',
        'description',
        'monthly_limit',
        'rate_limit_per_minute',
        'features',
        'price_monthly',
        'price_yearly',
        'is_active',
    ];

    protected $casts = [
        'monthly_limit' => 'integer',
        'rate_limit_per_minute' => 'integer',
        'features' => 'array',
        'price_monthly' => 'decimal:2',
        'price_yearly' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    /**
     * Check if the plan has unlimited monthly limit.
     */
    public function isUnlimited(): bool
    {
        return $this->monthly_limit === 0;
    }

    /**
     * Check if the plan has a specific feature.
     *
     * Backward compatibility: 'generate' is treated as 'ai_generate' for /generate endpoint.
     */
    public function hasFeature(string $feature): bool
    {
        $features = $this->getFeaturesAsArray();

        if (in_array($feature, $features, true)) {
            return true;
        }

        // Backward compatibility: plans with 'generate' can use ai_generate-protected endpoints
        if ($feature === 'ai_generate' && in_array('generate', $features, true)) {
            return true;
        }

        return false;
    }

    /**
     * Add a feature to the plan.
     */
    public function addFeature(string $feature): void
    {
        $features = $this->features ?? [];

        if (! in_array($feature, $features, true)) {
            $features[] = $feature;
            $this->features = $features;
            $this->save();
        }
    }

    /**
     * Remove a feature from the plan.
     */
    public function removeFeature(string $feature): void
    {
        $features = $this->features ?? [];
        $key = array_search($feature, $features, true);

        if ($key !== false) {
            unset($features[$key]);
            $this->features = array_values($features); // Reindex array
            $this->save();
        }
    }

    /**
     * Normalize features to array (handles JSON cast, raw string, or CSV from DB).
     * Uses raw attribute to handle cases where cast returns unexpected types.
     */
    private function getFeaturesAsArray(): array
    {
        $features = $this->getRawOriginal('features');

        if ($features === null || $features === '') {
            return [];
        }

        if (is_array($features)) {
            return $features;
        }

        if (is_string($features)) {
            $decoded = json_decode($features, true);
            if (is_array($decoded)) {
                return $decoded;
            }

            // Fallback: CSV string (e.g. "read,generate,context_tags")
            return array_map('trim', explode(',', $features));
        }

        return [];
    }

    /**
     * Get API keys using this plan.
     */
    public function apiKeys(): HasMany
    {
        return $this->hasMany(ApiKey::class, 'plan_id');
    }
}
