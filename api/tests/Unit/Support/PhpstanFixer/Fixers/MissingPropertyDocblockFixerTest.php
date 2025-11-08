<?php

declare(strict_types=1);

namespace Tests\Unit\Support\PhpstanFixer\Fixers;

use App\Support\PhpstanFixer\AutoFixMode;
use App\Support\PhpstanFixer\Fixers\MissingPropertyDocblockFixer;
use App\Support\PhpstanFixer\PhpstanIssue;
use Illuminate\Filesystem\Filesystem;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class MissingPropertyDocblockFixerTest extends TestCase
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
    public function it_adds_property_docblock(): void
    {
        $this->copyFixtureToTemp();

        $issue = new PhpstanIssue(
            filePath: $this->tempPath(),
            message: 'Access to an undefined property Tests\\Fixtures\\Models\\HasDynamicProperty::$aliases.',
            line: 10,
        );

        $fixer = new MissingPropertyDocblockFixer($this->filesystem);

        $suggestion = $fixer->handle($issue, AutoFixMode::APPLY);

        $this->assertNotNull($suggestion);
        $this->assertTrue($suggestion->applied);

        $updated = $this->filesystem->get($this->tempPath());
        $this->assertStringContainsString('@property mixed $aliases', $updated);
    }

    private function copyFixtureToTemp(): void
    {
        $this->filesystem->ensureDirectoryExists(dirname($this->tempPath()));
        $this->filesystem->copy(
            base_path('tests/Fixtures/Models/HasDynamicProperty.php'),
            $this->tempPath()
        );
    }

    private function tempPath(): string
    {
        return base_path('tests/Temp/HasDynamicProperty.php');
    }
}
