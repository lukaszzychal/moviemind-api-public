<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreWebhookSubscriptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $eventTypes = array_keys(config('webhooks.outgoing_urls', []));

        return [
            'event_type' => ['required', 'string', 'max:100', Rule::in($eventTypes)],
            'url' => ['required', 'string', 'url', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'event_type.in' => 'The event type must be one of the configured webhook event types.',
        ];
    }
}
