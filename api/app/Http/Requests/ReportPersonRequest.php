<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\ReportType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReportPersonRequest extends FormRequest
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
            'type' => ['required', 'string', Rule::enum(ReportType::class)],
            'message' => 'required|string|min:10|max:2000',
            'suggested_fix' => 'nullable|string|max:2000',
            'bio_id' => 'nullable|uuid|exists:person_bios,id',
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
            'type' => 'report type',
            'message' => 'message',
            'suggested_fix' => 'suggested fix',
            'bio_id' => 'bio ID',
        ];
    }
}
