<?php

declare(strict_types=1);

namespace Tests\Unit\Support\PhpstanFixer\Fixers;

use App\Support\PhpstanFixer\AutoFixMode;
use App\Support\PhpstanFixer\Fixers\CollectionGenericDocblockFixer;
use App\Support\PhpstanFixer\PhpstanIssue;
use Illuminate\Filesystem\Filesystem;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class CollectionGenericDocblockFixerTest extends TestCase
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
    public function it_adds_collection_generic_docblock(): void
    {
        $this->copyFixtureToTemp();

        $issue = new PhpstanIssue(
            filePath: $this->tempPath(),
            message: 'Property Tests\\Fixtures\\Models\\HasCollectionProperty::$items has no value type specified in iterable type Illuminate\\Support\\Collection.',
            line: 9,
        );

        $fixer = new CollectionGenericDocblockFixer($this->filesystem);

        $suggestion = $fixer->handle($issue, AutoFixMode::APPLY);

        $this->assertNotNull($suggestion);
        $this->assertTrue($suggestion->applied);

        $updated = $this->filesystem->get($this->tempPath());
        $this->assertStringContainsString('@property \\Illuminate\\Support\\Collection<int, mixed> $items', $updated);
    }

    private function copyFixtureToTemp(): void
    {
        $this->filesystem->ensureDirectoryExists(dirname($this->tempPath()));
        $this->filesystem->copy(
            base_path('tests/Fixtures/Models/HasCollectionProperty.php'),
            $this->tempPath()
        );
    }

    private function tempPath(): string
    {
        return base_path('tests/Temp/HasCollectionProperty.php');
    }
}
