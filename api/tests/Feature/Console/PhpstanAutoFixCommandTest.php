<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Artisan;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class PhpstanAutoFixCommandTest extends TestCase
{
    private Filesystem $filesystem;

    private string $pivotTempFile;

    private string $paramTempFile;

    protected function setUp(): void
    {
        parent::setUp();

        $this->filesystem = new Filesystem;
        $this->pivotTempFile = base_path('tests/Temp/HasPivot.php');
        $this->paramTempFile = base_path('tests/Temp/NeedsParamDocblock.php');

        $this->filesystem->ensureDirectoryExists(dirname($this->pivotTempFile));
        $this->filesystem->copy(
            base_path('tests/Fixtures/Models/HasPivot.php'),
            $this->pivotTempFile
        );

        $this->filesystem->copy(
            base_path('tests/Fixtures/Models/NeedsParamDocblock.php'),
            $this->paramTempFile
        );
    }

    protected function tearDown(): void
    {
        foreach ([$this->pivotTempFile, $this->paramTempFile] as $file) {
            if ($this->filesystem->exists($file)) {
                $this->filesystem->delete($file);
            }
        }

        parent::tearDown();
    }

    #[Test]
    public function it_suggests_fixes_from_phpstan_log(): void
    {
        $result = Artisan::call('phpstan:auto-fix', [
            '--mode' => 'suggest',
            '--input' => 'tests/Fixtures/Phpstan/combined-errors.json',
        ]);

        $this->assertSame(0, $result);

        $output = Artisan::output();

        $this->assertStringContainsString('Would add @property-read $pivot', $output);
        $this->assertStringContainsString('Would add @param mixed $rating docblock', $output);
        $this->assertFalse(str_contains($this->filesystem->get($this->pivotTempFile), '@property-read'));
        $this->assertFalse(str_contains($this->filesystem->get($this->paramTempFile), '@param mixed $rating'));
    }

    #[Test]
    public function it_applies_fixes_in_apply_mode(): void
    {
        $result = Artisan::call('phpstan:auto-fix', [
            '--mode' => 'apply',
            '--input' => 'tests/Fixtures/Phpstan/combined-errors.json',
        ]);

        $this->assertSame(0, $result);

        $pivotUpdated = $this->filesystem->get($this->pivotTempFile);
        $paramUpdated = $this->filesystem->get($this->paramTempFile);

        $this->assertStringContainsString('@property-read \Illuminate\Database\Eloquent\Relations\Pivot|null $pivot', $pivotUpdated);
        $this->assertStringContainsString('@param mixed $rating', $paramUpdated);
    }
}
