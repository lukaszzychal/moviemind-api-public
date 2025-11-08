<?php

declare(strict_types=1);

namespace App\Support\PhpstanFixer;

use Illuminate\Support\Arr;
use RuntimeException;

final class PhpstanLogParser
{
    /**
     * @return PhpstanIssue[]
     */
    public function parse(string $rawJson, string $workingDirectory): array
    {
        $decoded = json_decode($rawJson, true);

        if (! is_array($decoded)) {
            throw new RuntimeException('Invalid PHPStan JSON log supplied.');
        }

        $files = Arr::get($decoded, 'files', []);

        $issues = [];

        foreach ($files as $file => $data) {
            if (! is_array($data)) {
                continue;
            }

            $messages = Arr::get($data, 'messages', []);

            foreach ($messages as $messageData) {
                if (! is_array($messageData)) {
                    continue;
                }

                $message = Arr::get($messageData, 'message');

                if (! is_string($message) || $message === '') {
                    continue;
                }

                $line = Arr::get($messageData, 'line');

                $absolutePath = $this->normalizePath(
                    $file,
                    $workingDirectory
                );

                $issues[] = new PhpstanIssue(
                    filePath: $absolutePath,
                    message: $message,
                    line: is_int($line) ? $line : null,
                );
            }
        }

        return $issues;
    }

    private function normalizePath(string $path, string $workingDirectory): string
    {
        if ($this->isAbsolutePath($path)) {
            return $path;
        }

        return rtrim($workingDirectory, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.$path;
    }

    private function isAbsolutePath(string $path): bool
    {
        return str_starts_with($path, DIRECTORY_SEPARATOR)
            || (strlen($path) > 1 && ctype_alpha($path[0]) && $path[1] === ':');
    }
}
