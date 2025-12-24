<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SearchTvSeriesRequest extends FormRequest
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
            'q' => 'nullable|string|max:255',
            'year' => 'nullable|integer|min:1950|max:'.(date('Y') + 10),
            'limit' => 'nullable|integer|min:1|max:100',
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:100',
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
            'q' => 'query',
            'year' => 'year',
            'limit' => 'limit',
        ];
    }

    /**
     * Get validated search criteria.
     *
     * @return array{q?: string, year?: int, limit?: int, page?: int, per_page?: int}
     */
    public function getSearchCriteria(): array
    {
        $validated = $this->validated();
        $criteria = [];

        if (isset($validated['q'])) {
            $criteria['q'] = $validated['q'];
        }

        if (isset($validated['year'])) {
            $criteria['year'] = (int) $validated['year'];
        }

        // Pagination: prefer per_page over limit
        if (isset($validated['per_page'])) {
            $criteria['per_page'] = (int) $validated['per_page'];
        } elseif (isset($validated['limit'])) {
            $criteria['per_page'] = (int) $validated['limit'];
        }

        if (isset($validated['page'])) {
            $criteria['page'] = (int) $validated['page'];
        }

        // Keep limit for backward compatibility (if not using pagination)
        if (isset($validated['limit']) && ! isset($validated['page'])) {
            $criteria['limit'] = (int) $validated['limit'];
        }

        return $criteria;
    }
}
