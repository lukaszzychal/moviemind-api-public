<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\JobErrorType;

class JobErrorFormatter
{
    /**
     * Format exception to structured error format.
     *
     * @param  \Throwable  $exception  The exception to format
     * @param  string  $slug  The slug that caused the error
     * @param  string  $entityType  The entity type (MOVIE, PERSON, etc.)
     * @param  array<int, array{slug: string, title?: string, name?: string, release_year?: int|null, director?: string|null, tmdb_id: int}>|null  $suggestedSlugs  Optional list of suggested slugs
     * @return array{type: string, message: string, technical_message: string, user_message: string, suggested_slugs?: array}
     */
    public function formatError(\Throwable $exception, string $slug, string $entityType, ?array $suggestedSlugs = null): array
    {
        $type = $this->detectErrorType($exception);
        $entityName = trans('api.job_errors.entities.'.strtolower($entityType));

        $error = [
            'type' => $type->value,
            'message' => $this->getShortMessage($type, $entityType),
            'technical_message' => $exception->getMessage(),
            'user_message' => $this->getUserFriendlyMessage($type, $entityType),
        ];

        // Add suggested slugs for NOT_FOUND errors
        if ($type === JobErrorType::NOT_FOUND && ! empty($suggestedSlugs)) {
            $error['suggested_slugs'] = $suggestedSlugs;
            $error['user_message'] = trans('api.job_errors.did_you_mean', ['entity' => $entityName]);
        }

        return $error;
    }

    /**
     * Detect error type based on exception message.
     */
    private function detectErrorType(\Throwable $exception): JobErrorType
    {
        $message = $exception->getMessage();

        if (stripos($message, 'not found') !== false) {
            return JobErrorType::NOT_FOUND;
        }

        if (stripos($message, 'AI API returned error') !== false) {
            return JobErrorType::AI_API_ERROR;
        }

        if (stripos($message, 'validation failed') !== false) {
            return JobErrorType::VALIDATION_ERROR;
        }

        return JobErrorType::UNKNOWN_ERROR;
    }

    /**
     * Get short technical message.
     */
    private function getShortMessage(JobErrorType $type, string $entityType): string
    {
        $entityName = trans('api.job_errors.entities.'.strtolower($entityType));

        return match ($type) {
            JobErrorType::NOT_FOUND => trans('api.job_errors.not_found', ['entity' => $entityName]),
            JobErrorType::AI_API_ERROR => trans('api.job_errors.ai_api_error'),
            JobErrorType::VALIDATION_ERROR => trans('api.job_errors.validation_error'),
            JobErrorType::UNKNOWN_ERROR => trans('api.job_errors.unknown_error'),
        };
    }

    /**
     * Get user-friendly error message.
     */
    private function getUserFriendlyMessage(JobErrorType $type, string $entityType): string
    {
        $entityName = trans('api.job_errors.entities.'.strtolower($entityType));

        return match ($type) {
            JobErrorType::NOT_FOUND => trans('api.job_errors.user_not_found', ['entity' => $entityName]),
            JobErrorType::AI_API_ERROR => trans('api.job_errors.user_ai_api_error'),
            JobErrorType::VALIDATION_ERROR => trans('api.job_errors.user_validation_error'),
            JobErrorType::UNKNOWN_ERROR => trans('api.job_errors.user_unknown_error'),
        };
    }
}
