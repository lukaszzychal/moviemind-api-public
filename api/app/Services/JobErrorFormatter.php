<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\JobErrorType;

class JobErrorFormatter
{
    /**
     * Format exception to structured error format.
     *
     * @return array{type: string, message: string, technical_message: string, user_message: string}
     */
    public function formatError(\Throwable $exception, string $slug, string $entityType): array
    {
        $type = $this->detectErrorType($exception);

        return [
            'type' => $type->value,
            'message' => $this->getShortMessage($type, $entityType),
            'technical_message' => $exception->getMessage(),
            'user_message' => $this->getUserFriendlyMessage($type, $entityType),
        ];
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
        $entity = strtolower($entityType) === 'movie' ? 'movie' : 'person';

        return match ($type) {
            JobErrorType::NOT_FOUND => "The requested {$entity} was not found",
            JobErrorType::AI_API_ERROR => 'AI API returned an error',
            JobErrorType::VALIDATION_ERROR => 'AI data validation failed',
            JobErrorType::UNKNOWN_ERROR => 'An unexpected error occurred',
        };
    }

    /**
     * Get user-friendly error message.
     */
    private function getUserFriendlyMessage(JobErrorType $type, string $entityType): string
    {
        $entity = strtolower($entityType) === 'movie' ? 'movie' : 'person';

        return match ($type) {
            JobErrorType::NOT_FOUND => "This {$entity} does not exist in our database",
            JobErrorType::AI_API_ERROR => 'AI service is temporarily unavailable. Please try again later.',
            JobErrorType::VALIDATION_ERROR => 'Generated data failed validation checks. Please try again.',
            JobErrorType::UNKNOWN_ERROR => 'An unexpected error occurred. Please try again later.',
        };
    }
}
