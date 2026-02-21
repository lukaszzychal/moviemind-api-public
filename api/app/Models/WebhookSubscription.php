<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Webhook subscription: URL added via admin form to receive outgoing webhooks for an event type.
 *
 * @property string $id
 * @property string $event_type
 * @property string $url
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class WebhookSubscription extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'event_type',
        'url',
    ];
}
