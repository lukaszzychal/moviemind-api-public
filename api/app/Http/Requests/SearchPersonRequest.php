<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SearchPersonRequest extends FormRequest
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
            'birth_year' => 'nullable|integer|min:1800|max:'.(date('Y') + 10),
            'birthplace' => 'nullable|string|max:255',
            'role' => 'nullable',
            'role.*' => 'string|in:ACTOR,DIRECTOR,WRITER,PRODUCER', // For array format: role[]=ACTOR&role[]=DIRECTOR
            'movie' => 'nullable',
            'movie.*' => 'string|max:255', // For array format: movie[]=slug1&movie[]=slug2
            'limit' => 'nullable|integer|min:1|max:100', // Deprecated: use per_page instead
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:100',
            'sort' => 'nullable|string|in:name,birth_year,created_at',
            'order' => 'nullable|string|in:asc,desc',
            'local_limit' => 'nullable|integer|min:1|max:100',
            'external_limit' => 'nullable|integer|min:1|max:100',
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
            'birth_year' => 'birth year',
            'birthplace' => 'birthplace',
            'role' => 'role',
            'movie' => 'movie',
            'limit' => 'limit',
        ];
    }

    /**
     * Get validated search criteria.
     *
     * @return array{q?: string, birth_year?: int, birthplace?: string, role?: string|array, movie?: string|array, limit?: int, page?: int, per_page?: int, sort?: string, order?: string, local_limit?: int, external_limit?: int}
     */
    public function getSearchCriteria(): array
    {
        $validated = $this->validated();
        $criteria = [];

        if (isset($validated['q'])) {
            $criteria['q'] = $validated['q'];
        }

        if (isset($validated['birth_year'])) {
            $criteria['birth_year'] = (int) $validated['birth_year'];
        }

        if (isset($validated['birthplace'])) {
            $criteria['birthplace'] = $validated['birthplace'];
        }

        // Handle both single role and array of roles
        if (isset($validated['role'])) {
            if (is_array($validated['role'])) {
                $criteria['role'] = $validated['role'];
            } else {
                $criteria['role'] = $validated['role'];
            }
        }

        // Handle both single movie and array of movies
        if (isset($validated['movie'])) {
            if (is_array($validated['movie'])) {
                $criteria['movie'] = $validated['movie'];
            } else {
                $criteria['movie'] = $validated['movie'];
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

        // Limit per source
        if (isset($validated['local_limit'])) {
            $criteria['local_limit'] = (int) $validated['local_limit'];
        }

        if (isset($validated['external_limit'])) {
            $criteria['external_limit'] = (int) $validated['external_limit'];
        }

        return $criteria;
    }
}
