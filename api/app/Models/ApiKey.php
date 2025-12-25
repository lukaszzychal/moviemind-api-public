<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * ApiKey model.
 *
 * @author MovieMind API Team
 *
 * @property string $id UUIDv7 primary key
 * @property string $key Hashed API key
 * @property string $name Description/label for the API key
 * @property string|null $user_id User ID (for future user association)
 * @property string|null $plan_id Subscription plan ID
 * @property bool $is_active Whether the key is active
 * @property \Illuminate\Support\Carbon|null $last_used_at Last usage timestamp
 * @property \Illuminate\Support\Carbon|null $expires_at Expiration timestamp
 */
class ApiKey extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'key',
        'key_prefix',
        'name',
        'user_id',
        'plan_id',
        'is_active',
        'last_used_at',
        'expires_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'last_used_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    protected $hidden = [
        'key', // Never expose hashed key in JSON responses
    ];

    /**
     * Check if the API key is expired.
     */
    public function isExpired(): bool
    {
        if ($this->expires_at === null) {
            return false;
        }

        return $this->expires_at->isPast();
    }

    /**
     * Check if the API key is valid (active and not expired).
     */
    public function isValid(): bool
    {
        return $this->is_active && ! $this->isExpired();
    }

    /**
     * Mark the key as used (update last_used_at timestamp).
     */
    public function markAsUsed(): void
    {
        $this->update(['last_used_at' => now()]);
    }

    /**
     * Get the subscription plan for this API key.
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class, 'plan_id');
    }
}
