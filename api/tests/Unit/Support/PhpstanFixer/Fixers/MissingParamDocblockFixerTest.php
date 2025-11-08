<?php

declare(strict_types=1);

namespace Tests\Unit\Support\PhpstanFixer\Fixers;

use App\Support\PhpstanFixer\AutoFixMode;
use App\Support\PhpstanFixer\Fixers\MissingParamDocblockFixer;
use App\Support\PhpstanFixer\PhpstanIssue;
use Illuminate\Filesystem\Filesystem;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class MissingParamDocblockFixerTest extends TestCase
{
    private Filesystem $filesystem;

    protected function setUp(): void
    {
        parent::setUp();

        $this->filesystem = new Filesystem;
    }

    protected function tearDown(): void
    {
        $paths = [
            $this->tempPath('NeedsParamDocblock.php'),
            $this->tempPath('NeedsParamDocblockWithDoc.php'),
        ];

        foreach ($paths as $path) {
            if ($this->filesystem->exists($path)) {
                $this->filesystem->delete($path);
            }
        }

        parent::tearDown();
    }

    #[Test]
    public function it_appends_param_to_existing_docblock(): void
    {
        $this->filesystem->ensureDirectoryExists(dirname($this->tempPath('NeedsParamDocblockWithDoc.php')));
        $this->filesystem->put(
            $this->tempPath('NeedsParamDocblockWithDoc.php'),
            <<<'PHP'
<?php

class NeedsParamDocblockWithDoc
{
    /**
     * Update rating.
     */
    public function setRating($rating): void
    {
    }
}
PHP
        );

        $issue = new PhpstanIssue(
            filePath: $this->tempPath('NeedsParamDocblockWithDoc.php'),
            message: 'Method Tests\\Fixtures\\Models\\NeedsParamDocblock::setRating() has parameter $rating with no typehint specified.',
            line: 8,
        );

        $fixer = new MissingParamDocblockFixer($this->filesystem);

        $suggestion = $fixer->handle($issue, AutoFixMode::APPLY);

        $this->assertNotNull($suggestion);
        $this->assertTrue($suggestion->applied);

        $updated = $this->filesystem->get($this->tempPath('NeedsParamDocblockWithDoc.php'));
        $this->assertStringContainsString('@param mixed $rating', $updated);
    }

    #[Test]
    public function it_creates_docblock_when_missing(): void
    {
        $this->copyFixtureToTemp();

        $issue = new PhpstanIssue(
            filePath: $this->tempPath(),
            message: 'Method Tests\\Fixtures\\Models\\NeedsParamDocblock::setRating() has parameter $rating with no typehint specified.',
            line: 10,
        );

        $fixer = new MissingParamDocblockFixer($this->filesystem);

        $suggestion = $fixer->handle($issue, AutoFixMode::SUGGEST);

        $this->assertNotNull($suggestion);
        $this->assertFalse($suggestion->applied);
        $this->assertStringContainsString('@param mixed $rating', $suggestion->preview);
    }

    private function copyFixtureToTemp(): void
    {
        $this->filesystem->ensureDirectoryExists(dirname($this->tempPath()));
        $this->filesystem->copy(
            base_path('tests/Fixtures/Models/NeedsParamDocblock.php'),
            $this->tempPath()
        );
    }

    private function tempPath(string $file = 'NeedsParamDocblock.php'): string
    {
        return base_path('tests/Temp/'.$file);
    }
}
