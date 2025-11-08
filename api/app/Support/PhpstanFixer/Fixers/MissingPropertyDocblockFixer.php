<?php

declare(strict_types=1);

namespace App\Support\PhpstanFixer\Fixers;

use App\Support\PhpstanFixer\AutoFixMode;
use App\Support\PhpstanFixer\FixSuggestion;
use App\Support\PhpstanFixer\PhpstanIssue;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;

final class MissingPropertyDocblockFixer implements FixStrategy
{
    public function __construct(
        private readonly Filesystem $filesystem,
    ) {}

    public function supports(PhpstanIssue $issue): bool
    {
        return Str::contains($issue->message, 'Access to an undefined property')
            && ! Str::contains($issue->message, '::$pivot');
    }

    public function handle(PhpstanIssue $issue, AutoFixMode $mode): ?FixSuggestion
    {
        if (! preg_match(
            '/Access to an undefined property\s+([A-Za-z0-9_\\\\]+)::\$([A-Za-z0-9_]+)\./',
            $issue->message,
            $matches
        )) {
            return null;
        }

        $className = $matches[1];
        $property = $matches[2];

        if ($property === 'pivot') {
            return null;
        }

        if (! $this->filesystem->exists($issue->filePath)) {
            return null;
        }

        $original = $this->filesystem->get($issue->filePath);

        $updated = $this->injectDocblock($original, $issue, $property);

        if ($updated === null || $updated === $original) {
            return null;
        }

        $summary = sprintf(
            'add @property mixed $%s docblock for %s',
            $property,
            Str::afterLast($className, '\\')
        );

        $preview = <<<TXT
/**
 * @property mixed \${$property}
 */
class {$issue->className()} extends ...
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

    private function injectDocblock(string $contents, PhpstanIssue $issue, string $property): ?string
    {
        $className = $issue->className();

        if (! $className) {
            return null;
        }

        $docLine = '@property mixed $'.$property;

        $patternWithDoc = '/\/\*\*[\s\S]*?\*\/\s*class\s+'.preg_quote($className, '/').'\b/';

        if (preg_match($patternWithDoc, $contents, $matches)) {
            $fullMatch = $matches[0];
            $classPosition = strpos($fullMatch, 'class');

            if ($classPosition === false) {
                return null;
            }

            $docComment = substr($fullMatch, 0, $classPosition);
            $classLine = substr($fullMatch, $classPosition);

            if (Str::contains($docComment, $docLine)) {
                return null;
            }

            $closingPosition = strrpos($docComment, '*/');

            if ($closingPosition === false) {
                return null;
            }

            $docComment = substr($docComment, 0, $closingPosition);
            $docComment = rtrim($docComment, " \t\n\r\0\x0B");

            $indent = $this->detectDocIndentation($docComment);
            $docComment .= PHP_EOL.$indent.' * '.$docLine.PHP_EOL.$indent.'*/';

            $replacement = $docComment.PHP_EOL.$classLine;

            return Str::replaceFirst($fullMatch, $replacement, $contents);
        }

        $patternClass = '/(class\s+'.preg_quote($className, '/').'\b)/';

        if (! preg_match($patternClass, $contents, $classMatch, PREG_OFFSET_CAPTURE)) {
            return null;
        }

        $classSegment = $classMatch[0][0];
        $classOffset = $classMatch[0][1];

        $indentation = $this->detectIndentation($contents, $classOffset);

        $docblock = $indentation.'/**'.PHP_EOL
            .$indentation.' * '.$docLine.PHP_EOL
            .$indentation.' */'.PHP_EOL
            .$indentation.$classSegment;

        return substr_replace($contents, $docblock, $classOffset, strlen($classSegment));
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
