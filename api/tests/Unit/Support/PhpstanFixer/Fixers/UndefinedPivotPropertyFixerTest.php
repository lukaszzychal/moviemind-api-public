<?php

declare(strict_types=1);

namespace Tests\Unit\Support\PhpstanFixer\Fixers;

use App\Support\PhpstanFixer\AutoFixMode;
use App\Support\PhpstanFixer\Fixers\UndefinedPivotPropertyFixer;
use App\Support\PhpstanFixer\PhpstanIssue;
use Illuminate\Filesystem\Filesystem;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class UndefinedPivotPropertyFixerTest extends TestCase
{
    private Filesystem $filesystem;

    protected function setUp(): void
    {
        parent::setUp();

        $this->filesystem = new Filesystem;
    }

    protected function tearDown(): void
    {
        if ($this->filesystem->exists($this->tempPath())) {
            $this->filesystem->delete($this->tempPath());
        }

        parent::tearDown();
    }

    #[Test]
    public function it_suggests_docblock_when_missing(): void
    {
        $this->copyFixtureToTemp();

        $issue = new PhpstanIssue(
            filePath: $this->tempPath(),
            message: 'Access to an undefined property Tests\\Fixtures\\Models\\HasPivot::$pivot.',
            line: 10,
        );

        $fixer = new UndefinedPivotPropertyFixer($this->filesystem);

        $suggestion = $fixer->handle($issue, AutoFixMode::SUGGEST);

        $this->assertNotNull($suggestion);
        $this->assertFalse($suggestion->applied);
        $this->assertStringContainsString('@property-read \\Illuminate\\Database\\Eloquent\\Relations\\Pivot|null $pivot', $suggestion->preview);
        $this->assertSame($this->tempPath(), $suggestion->filePath);
    }

    #[Test]
    public function it_applies_docblock_in_apply_mode(): void
    {
        $this->copyFixtureToTemp();

        $issue = new PhpstanIssue(
            filePath: $this->tempPath(),
            message: 'Access to an undefined property Tests\\Fixtures\\Models\\HasPivot::$pivot.',
            line: 10,
        );

        $fixer = new UndefinedPivotPropertyFixer($this->filesystem);

        $suggestion = $fixer->handle($issue, AutoFixMode::APPLY);

        $this->assertNotNull($suggestion);
        $this->assertTrue($suggestion->applied);

        $updated = $this->filesystem->get($this->tempPath());

        $this->assertStringContainsString('@property-read \\Illuminate\\Database\\Eloquent\\Relations\\Pivot|null $pivot', $updated);
    }

    private function copyFixtureToTemp(): void
    {
        $this->filesystem->ensureDirectoryExists(dirname($this->tempPath()));
        $this->filesystem->copy(
            base_path('tests/Fixtures/Models/HasPivot.php'),
            $this->tempPath()
        );
    }

    private function tempPath(): string
    {
        return base_path('tests/Temp/HasPivot.php');
    }
}
