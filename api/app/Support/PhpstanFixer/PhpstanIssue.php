<?php

declare(strict_types=1);

namespace App\Support\PhpstanFixer;

use Illuminate\Support\Str;

final class PhpstanIssue
{
    public function __construct(
        public readonly string $filePath,
        public readonly string $message,
        public readonly ?int $line,
    ) {}

    public function classFqn(): ?string
    {
        if (preg_match('/([A-Za-z0-9_\\\\]+)::\\$[A-Za-z0-9_]+/', $this->message, $matches)) {
            return $matches[1];
        }

        if (preg_match('/of class ([A-Za-z0-9_\\\\]+)/', $this->message, $matches)) {
            return $matches[1];
        }

        return null;
    }

    public function className(): ?string
    {
        $fqn = $this->classFqn();

        return $fqn ? Str::afterLast($fqn, '\\') : null;
    }
}
