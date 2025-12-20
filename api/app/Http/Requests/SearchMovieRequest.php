<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SearchMovieRequest extends FormRequest
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
            'year' => 'nullable|integer|min:1900|max:'.(date('Y') + 10),
            'director' => 'nullable|string|max:255',
            'actor' => 'nullable',
            'actor.*' => 'string|max:255', // For array format: actor[]=name1&actor[]=name2
            'limit' => 'nullable|integer|min:1|max:100', // Deprecated: use per_page instead
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:100',
            'sort' => 'nullable|string|in:title,release_year,created_at',
            'order' => 'nullable|string|in:asc,desc',
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
            'director' => 'director',
            'actor' => 'actor',
            'limit' => 'limit',
        ];
    }

    /**
     * Get validated search criteria.
     *
     * @return array{q?: string, year?: int, director?: string, actor?: string|array, limit?: int, page?: int, per_page?: int, sort?: string, order?: string}
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

        if (isset($validated['director'])) {
            $criteria['director'] = $validated['director'];
        }

        // Handle both single actor and array of actors
        if (isset($validated['actor'])) {
            if (is_array($validated['actor'])) {
                $criteria['actor'] = $validated['actor'];
            } else {
                $criteria['actor'] = $validated['actor'];
            }
        }

        // Pagination: prefer per_page over limit (limit is deprecated but kept for backward compatibility)
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

        // Sorting
        if (isset($validated['sort'])) {
            $criteria['sort'] = $validated['sort'];
        }

        if (isset($validated['order'])) {
            $criteria['order'] = $validated['order'];
        }

        return $criteria;
    }
}
