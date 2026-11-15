<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Services\AdminMaintenanceService;
use Illuminate\Foundation\Http\FormRequest;

class ClearCacheRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'cache_key' => ['sometimes', 'nullable', 'string', 'max:255'],
        ];
    }

    public function cacheKey(): string
    {
        $key = $this->validated('cache_key');

        return is_string($key) && $key !== '' ? $key : AdminMaintenanceService::CACHE_SCOPE_ALL;
    }
}
