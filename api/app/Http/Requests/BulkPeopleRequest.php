<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BulkPeopleRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'slugs' => 'required|array|min:1|max:50',
            'slugs.*' => 'required|string|regex:/^[a-z0-9-]+$/i|max:255',
            'include' => 'nullable|array',
            'include.*' => 'string|in:bios,movies',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'slugs' => 'slugs',
            'slugs.*' => 'slug',
            'include' => 'include',
        ];
    }

    /**
     * Get validated slugs array.
     *
     * @return array<int, string>
     */
    public function getSlugs(): array
    {
        return $this->validated()['slugs'];
    }

    /**
     * Get include options.
     *
     * @return array<int, string>
     */
    public function getInclude(): array
    {
        return $this->validated()['include'] ?? [];
    }
}
