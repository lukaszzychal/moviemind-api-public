<?php

declare(strict_types=1);

namespace App\Support\PhpstanFixer\Fixers;

use App\Support\PhpstanFixer\AutoFixMode;
use App\Support\PhpstanFixer\FixSuggestion;
use App\Support\PhpstanFixer\PhpstanIssue;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;

final class UndefinedPivotPropertyFixer implements FixStrategy
{
    private const PROPERTY_DOC = '@property-read \Illuminate\Database\Eloquent\Relations\Pivot|null $pivot';

    public function __construct(
        private readonly Filesystem $filesystem,
    ) {}

    public function supports(PhpstanIssue $issue): bool
    {
        return Str::contains($issue->message, 'Access to an undefined property')
            && Str::contains($issue->message, '::$pivot');
    }

    public function handle(PhpstanIssue $issue, AutoFixMode $mode): ?FixSuggestion
    {
        if (! $this->filesystem->exists($issue->filePath)) {
            return null;
        }

        $original = $this->filesystem->get($issue->filePath);

        if (Str::contains($original, self::PROPERTY_DOC)) {
            return null;
        }

        $updated = $this->injectDocblock($original, $issue);

        if ($updated === null || $updated === $original) {
            return null;
        }

        $preview = $this->buildPreviewSnippet($issue);

        if ($mode === AutoFixMode::APPLY) {
            $this->filesystem->put($issue->filePath, $updated);

            return new FixSuggestion(
                filePath: $issue->filePath,
                summary: 'Added @property-read $pivot docblock for Laravel relation pivot access.',
                preview: $preview,
                applied: true,
            );
        }

        return new FixSuggestion(
            filePath: $issue->filePath,
            summary: 'Would add @property-read $pivot docblock for Laravel relation pivot access.',
            preview: $preview,
            applied: false,
        );
    }

    private function injectDocblock(string $contents, PhpstanIssue $issue): ?string
    {
        $className = $issue->className();

        if (! $className) {
            return null;
        }

        $patternWithDoc = '/\/\*\*[\s\S]*?\*\/\s*class\s+'.preg_quote($className, '/').'\b/';

        if (preg_match($patternWithDoc, $contents, $matches)) {
            $fullMatch = $matches[0];
            $classPosition = strpos($fullMatch, 'class');

            if ($classPosition === false) {
                return null;
            }

            $docComment = substr($fullMatch, 0, $classPosition);
            $classLine = substr($fullMatch, $classPosition);

            if (Str::contains($docComment, self::PROPERTY_DOC)) {
                return null;
            }

            $closingPosition = strrpos($docComment, '*/');

            if ($closingPosition === false) {
                return null;
            }

            $docComment = substr($docComment, 0, $closingPosition);
            $docComment = rtrim($docComment, " \t\n\r\0\x0B");
            $docComment .= PHP_EOL.' * '.self::PROPERTY_DOC.PHP_EOL.' */';

            $replacement = $docComment.PHP_EOL.$classLine;

            return Str::replaceFirst($fullMatch, $replacement, $contents);
        }

        $patternClass = '/(class\s+'.preg_quote($className, '/').'\b)/';

        $replacement = <<<'DOC'
/**
 * @property-read \Illuminate\Database\Eloquent\Relations\Pivot|null $pivot
 */
$1
DOC;

        $updated = preg_replace($patternClass, $replacement, $contents, 1, $count);

        return $count > 0 ? $updated : null;
    }

    private function buildPreviewSnippet(PhpstanIssue $issue): string
    {
        return <<<TXT
/**
 * @property-read \Illuminate\Database\Eloquent\Relations\Pivot|null \$pivot
 */
class {$issue->className()} extends ...
TXT;
    }
}
