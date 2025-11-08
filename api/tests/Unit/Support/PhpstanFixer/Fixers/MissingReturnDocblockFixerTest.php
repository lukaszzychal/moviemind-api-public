<?php

declare(strict_types=1);

namespace Tests\Unit\Support\PhpstanFixer\Fixers;

use App\Support\PhpstanFixer\AutoFixMode;
use App\Support\PhpstanFixer\Fixers\MissingReturnDocblockFixer;
use App\Support\PhpstanFixer\PhpstanIssue;
use Illuminate\Filesystem\Filesystem;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class MissingReturnDocblockFixerTest extends TestCase
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
    public function it_suggests_return_docblock(): void
    {
        $this->copyFixtureToTemp();

        $issue = new PhpstanIssue(
            filePath: $this->tempPath(),
            message: 'Method Tests\\Fixtures\\Models\\NeedsReturnDocblock::getRating() has no return type specified.',
            line: 9,
        );

        $fixer = new MissingReturnDocblockFixer($this->filesystem);

        $suggestion = $fixer->handle($issue, AutoFixMode::SUGGEST);

        $this->assertNotNull($suggestion);
        $this->assertFalse($suggestion->applied);
        $this->assertStringContainsString('@return mixed', $suggestion->preview);
    }

    #[Test]
    public function it_applies_return_docblock(): void
    {
        $this->copyFixtureToTemp();

        $issue = new PhpstanIssue(
            filePath: $this->tempPath(),
            message: 'Method Tests\\Fixtures\\Models\\NeedsReturnDocblock::getRating() has no return type specified.',
            line: 9,
        );

        $fixer = new MissingReturnDocblockFixer($this->filesystem);

        $suggestion = $fixer->handle($issue, AutoFixMode::APPLY);

        $this->assertNotNull($suggestion);
        $this->assertTrue($suggestion->applied);

        $updated = $this->filesystem->get($this->tempPath());
        $this->assertStringContainsString('@return mixed', $updated);
    }

    private function copyFixtureToTemp(): void
    {
        $this->filesystem->ensureDirectoryExists(dirname($this->tempPath()));
        $this->filesystem->copy(
            base_path('tests/Fixtures/Models/NeedsReturnDocblock.php'),
            $this->tempPath()
        );
    }

    private function tempPath(): string
    {
        return base_path('tests/Temp/NeedsReturnDocblock.php');
    }
}
