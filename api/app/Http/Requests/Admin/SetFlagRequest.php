<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SetFlagRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'state' => ['required', Rule::in(['on', 'off'])],
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            /** @var \App\Services\FeatureFlag\FeatureFlagManager $manager */
            $manager = app(\App\Services\FeatureFlag\FeatureFlagManager::class);
            $flagName = $this->route('name');

            if (! $manager->isTogglable($flagName)) {
                $validator->errors()->add('name', "The feature flag '{$flagName}' cannot be toggled manually.");
            }
        });
    }

    public function wantsActivation(): bool
    {
        return $this->input('state') === 'on';
    }
}
