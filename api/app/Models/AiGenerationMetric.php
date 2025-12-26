<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * AiGenerationMetric model.
 *
 * Tracks AI generation metrics including token usage, parsing accuracy, and errors.
 *
 * @property string $id UUID primary key
 * @property string|null $job_id Link to jobs table
 * @property string $entity_type Entity type (MOVIE, PERSON, TV_SERIES, TV_SHOW)
 * @property string $entity_slug Entity slug
 * @property string $data_format Data format (JSON, TOON, CSV)
 * @property int $prompt_tokens Tokeny w promptcie
 * @property int $completion_tokens Tokeny w odpowiedzi
 * @property int $total_tokens Razem tokenów
 * @property int|null $bytes_sent Rozmiar w bajtach (dla porównania)
 * @property float|null $token_savings_vs_json Oszczędności vs JSON baseline
 * @property bool $parsing_successful Czy parsowanie się powiodło
 * @property string|null $parsing_errors Błędy parsowania
 * @property array|null $validation_errors Błędy walidacji struktury
 * @property int|null $response_time_ms Response time in milliseconds
 * @property string $model AI model used (e.g., gpt-4o-mini)
 */
class AiGenerationMetric extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'ai_generation_metrics';

    protected $fillable = [
        'job_id',
        'entity_type',
        'entity_slug',
        'data_format',
        'prompt_tokens',
        'completion_tokens',
        'total_tokens',
        'bytes_sent',
        'token_savings_vs_json',
        'parsing_successful',
        'parsing_errors',
        'validation_errors',
        'response_time_ms',
        'model',
    ];

    protected $casts = [
        'prompt_tokens' => 'integer',
        'completion_tokens' => 'integer',
        'total_tokens' => 'integer',
        'bytes_sent' => 'integer',
        'token_savings_vs_json' => 'decimal:2',
        'parsing_successful' => 'boolean',
        'validation_errors' => 'array',
        'response_time_ms' => 'integer',
    ];
}
