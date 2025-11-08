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

    private string $tempFile;

    protected function setUp(): void
    {
        parent::setUp();

        $this->filesystem = new Filesystem;
        $this->tempFile = base_path('tests/Temp/HasPivot.php');

        $this->filesystem->ensureDirectoryExists(dirname($this->tempFile));
        $this->filesystem->copy(
            base_path('tests/Fixtures/Models/HasPivot.php'),
            $this->tempFile
        );
    }

    protected function tearDown(): void
    {
        if ($this->filesystem->exists($this->tempFile)) {
            $this->filesystem->delete($this->tempFile);
        }

        parent::tearDown();
    }

    #[Test]
    public function it_suggests_fixes_from_phpstan_log(): void
    {
        $result = Artisan::call('phpstan:auto-fix', [
            '--mode' => 'suggest',
            '--input' => 'tests/Fixtures/Phpstan/pivot-error.json',
        ]);

        $this->assertSame(0, $result);

        $output = Artisan::output();

        $this->assertStringContainsString('Would add @property-read $pivot', $output);
        $this->assertFalse(str_contains($this->filesystem->get($this->tempFile), '@property-read'));
    }

    #[Test]
    public function it_applies_fixes_in_apply_mode(): void
    {
        $result = Artisan::call('phpstan:auto-fix', [
            '--mode' => 'apply',
            '--input' => 'tests/Fixtures/Phpstan/pivot-error.json',
        ]);

        $this->assertSame(0, $result);

        $updated = $this->filesystem->get($this->tempFile);

        $this->assertStringContainsString('@property-read \Illuminate\Database\Eloquent\Relations\Pivot|null $pivot', $updated);
    }
}
