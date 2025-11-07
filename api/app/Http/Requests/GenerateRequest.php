<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class GenerateRequest extends FormRequest
{
    /**
     * Prepare the data for validation.
     * Support both 'slug' and deprecated 'entity_id' fields.
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('entity_id') && ! $this->has('slug')) {
            $this->merge([
                'slug' => $this->input('entity_id'),
            ]);
        }
    }

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
            'entity_type' => 'required|in:MOVIE,ACTOR,PERSON',
            'slug' => 'required_without:entity_id|string|max:255',
            'entity_id' => 'required_without:slug|string|max:255',
            'locale' => 'nullable|string|max:10',
            'context_tag' => 'nullable|string|max:64',
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
            'entity_type' => 'entity type',
            'entity_id' => 'entity ID',
            'locale' => 'locale',
            'context_tag' => 'context tag',
        ];
    }

    /**
     * Get custom error messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'entity_type.required' => 'The entity type field is required.',
            'entity_type.in' => 'The entity type must be one of: MOVIE, ACTOR, or PERSON.',
            'entity_id.required' => 'The entity ID field is required.',
            'entity_id.string' => 'The entity ID must be a string.',
            'entity_id.max' => 'The entity ID may not be greater than 255 characters.',
            'locale.string' => 'The locale must be a string.',
            'locale.max' => 'The locale may not be greater than 10 characters.',
            'context_tag.string' => 'The context tag must be a string.',
            'context_tag.max' => 'The context tag may not be greater than 64 characters.',
        ];
    }
}
