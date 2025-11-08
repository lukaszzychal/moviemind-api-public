<?php

declare(strict_types=1);

namespace App\Support\PhpstanFixer\Fixers;

use App\Support\PhpstanFixer\AutoFixMode;
use App\Support\PhpstanFixer\FixSuggestion;
use App\Support\PhpstanFixer\PhpstanIssue;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;

final class MissingParamDocblockFixer implements FixStrategy
{
    private const PARAM_TEMPLATE = '@param mixed $%s';

    public function __construct(
        private readonly Filesystem $filesystem,
    ) {}

    public function supports(PhpstanIssue $issue): bool
    {
        return Str::contains($issue->message, 'has parameter $')
            && Str::contains($issue->message, 'with no typehint specified');
    }

    public function handle(PhpstanIssue $issue, AutoFixMode $mode): ?FixSuggestion
    {
        if (! preg_match(
            '/Method\s+([A-Za-z0-9_\\\\]+)::([A-Za-z0-9_]+)\(\)\s+has parameter\s+\$([A-Za-z0-9_]+)\s+with no typehint specified\./',
            $issue->message,
            $matches
        )) {
            return null;
        }

        $className = $matches[1];
        $methodName = $matches[2];
        $parameterName = $matches[3];

        if (! $this->filesystem->exists($issue->filePath)) {
            return null;
        }

        $original = $this->filesystem->get($issue->filePath);

        $updated = $this->ensureDocblock($original, $methodName, $parameterName);

        if ($updated === null || $updated === $original) {
            return null;
        }

        $summary = sprintf(
            'add @param mixed $%s docblock for %s::%s()',
            $parameterName,
            Str::afterLast($className, '\\'),
            $methodName
        );

        $preview = <<<TXT
/**
 * @param mixed \${$parameterName}
 */
function {$methodName}(...)
TXT;

        if ($mode === AutoFixMode::APPLY) {
            $this->filesystem->put($issue->filePath, $updated);

            return new FixSuggestion(
                filePath: $issue->filePath,
                summary: ucfirst($summary).'.',
                preview: $preview,
                applied: true,
            );
        }

        return new FixSuggestion(
            filePath: $issue->filePath,
            summary: 'Would '.$summary.'.',
            preview: $preview,
            applied: false,
        );
    }

    private function ensureDocblock(
        string $contents,
        string $methodName,
        string $parameterName,
    ): ?string {
        $patternWithDoc = '/\/\*\*[\s\S]*?\*\/\s*(public|protected|private)?\s*function\s+'
            .preg_quote($methodName, '/').'\s*\(/';

        if (preg_match($patternWithDoc, $contents, $matches, PREG_OFFSET_CAPTURE)) {
            $fullMatch = $matches[0][0];
            $offset = $matches[0][1];

            $docblockPattern = '/\/\*\*([\s\S]*?)\*\//';
            if (! preg_match($docblockPattern, $fullMatch, $docMatch)) {
                return null;
            }

            $docblock = $docMatch[0];

            if (Str::contains($docblock, sprintf(self::PARAM_TEMPLATE, $parameterName))) {
                return null;
            }

            $indent = $this->detectDocIndentation($docblock);

            $modifiedDoc = Str::replaceLast(
                $indent.'*/',
                $indent.' * '.sprintf(self::PARAM_TEMPLATE, $parameterName).PHP_EOL.$indent.'*/',
                rtrim($docblock).PHP_EOL
            );

            $replacement = Str::replaceFirst($docblock, $modifiedDoc, $fullMatch);

            return substr_replace($contents, $replacement, $offset, strlen($fullMatch));
        }

        $patternMethod = '/((public|protected|private)?\s*function\s+'.preg_quote($methodName, '/').'\s*\()/';

        if (! preg_match($patternMethod, $contents, $methodMatch, PREG_OFFSET_CAPTURE)) {
            return null;
        }

        $methodSegment = $methodMatch[0][0];
        $methodOffset = $methodMatch[0][1];

        $indentation = $this->detectIndentation($contents, $methodOffset);

        $docblock = $indentation.'/**'.PHP_EOL
            .$indentation.' * '.sprintf(self::PARAM_TEMPLATE, $parameterName).PHP_EOL
            .$indentation.' */'.PHP_EOL
            .$indentation.$methodSegment;

        return substr_replace($contents, $docblock, $methodOffset, strlen($methodSegment));
    }

    private function detectIndentation(string $contents, int $offset): string
    {
        $lineStart = strrpos(substr($contents, 0, $offset), PHP_EOL);

        if ($lineStart === false) {
            $lineStart = 0;
        }

        $line = substr($contents, $lineStart, $offset - $lineStart);

        preg_match('/\R?([ \t]*)$/', $line, $matches);

        return $matches[1] ?? '';
    }

    private function detectDocIndentation(string $docblock): string
    {
        if (preg_match('/\n([ \t]*)\*/', $docblock, $matches)) {
            return $matches[1];
        }

        return '';
    }
}
