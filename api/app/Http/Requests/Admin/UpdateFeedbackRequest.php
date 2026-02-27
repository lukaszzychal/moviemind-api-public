<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Models\ApplicationFeedback;
use Illuminate\Foundation\Http\FormRequest;

class UpdateFeedbackRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'status' => ['sometimes', 'string', 'in:'.implode(',', ApplicationFeedback::statuses())],
        ];
    }
}
