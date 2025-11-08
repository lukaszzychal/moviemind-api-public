<?php

declare(strict_types=1);

namespace Tests\Unit\Support\PhpstanFixer;

use App\Support\PhpstanFixer\PhpstanLogParser;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class PhpstanLogParserTest extends TestCase
{
    #[Test]
    public function it_parses_phpstan_json_into_issues(): void
    {
        $parser = new PhpstanLogParser;

        $raw = file_get_contents(base_path('tests/Fixtures/Phpstan/pivot-error.json'));

        $issues = $parser->parse($raw, base_path());

        $this->assertCount(1, $issues);
        $issue = $issues[0];

        $this->assertSame(
            base_path('tests/Temp/HasPivot.php'),
            $issue->filePath
        );
        $this->assertSame(
            'Access to an undefined property Tests\\Fixtures\\Models\\HasPivot::$pivot.',
            $issue->message
        );
        $this->assertSame('HasPivot', $issue->className());
    }
}
