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

    /**
     * @var array<string, string>
     */
    private array $tempFiles;

    protected function setUp(): void
    {
        parent::setUp();

        $this->filesystem = new Filesystem;
        $this->tempFiles = [
            base_path('tests/Temp/HasPivot.php') => base_path('tests/Fixtures/Models/HasPivot.php'),
            base_path('tests/Temp/NeedsParamDocblock.php') => base_path('tests/Fixtures/Models/NeedsParamDocblock.php'),
            base_path('tests/Temp/NeedsReturnDocblock.php') => base_path('tests/Fixtures/Models/NeedsReturnDocblock.php'),
            base_path('tests/Temp/HasDynamicProperty.php') => base_path('tests/Fixtures/Models/HasDynamicProperty.php'),
            base_path('tests/Temp/HasCollectionProperty.php') => base_path('tests/Fixtures/Models/HasCollectionProperty.php'),
        ];

        foreach ($this->tempFiles as $tempPath => $fixturePath) {
            $this->filesystem->ensureDirectoryExists(dirname($tempPath));
            $this->filesystem->copy($fixturePath, $tempPath);
        }
    }

    protected function tearDown(): void
    {
        foreach (array_keys($this->tempFiles) as $tempPath) {
            if ($this->filesystem->exists($tempPath)) {
                $this->filesystem->delete($tempPath);
            }
        }

        parent::tearDown();
    }

    #[Test]
    public function it_reports_all_available_fixes_in_suggest_mode(): void
    {
        $exitCode = Artisan::call('phpstan:auto-fix', [
            '--mode' => 'suggest',
            '--input' => 'tests/Fixtures/Phpstan/extended-errors.json',
        ]);

        $this->assertSame(0, $exitCode);

        $output = Artisan::output();

        $this->assertStringContainsString('Would add @property-read $pivot', $output);
        $this->assertStringContainsString('Would add @param mixed $rating docblock', $output);
        $this->assertStringContainsString('Would add @return mixed docblock', $output);
        $this->assertStringContainsString('Would add @property mixed $aliases docblock', $output);
        $this->assertStringContainsString('Would add @property Collection<int, mixed> $items docblock', $output);

        $this->assertFalse(str_contains($this->filesystem->get(base_path('tests/Temp/HasPivot.php')), '@property-read'));
        $this->assertFalse(str_contains($this->filesystem->get(base_path('tests/Temp/NeedsParamDocblock.php')), '@param mixed'));
        $this->assertFalse(str_contains($this->filesystem->get(base_path('tests/Temp/NeedsReturnDocblock.php')), '@return'));
        $this->assertFalse(str_contains($this->filesystem->get(base_path('tests/Temp/HasDynamicProperty.php')), '@property mixed $aliases'));
        $this->assertFalse(str_contains($this->filesystem->get(base_path('tests/Temp/HasCollectionProperty.php')), 'Collection<int, mixed>'));
    }

    #[Test]
    public function it_applies_all_fixes_in_apply_mode(): void
    {
        $exitCode = Artisan::call('phpstan:auto-fix', [
            '--mode' => 'apply',
            '--input' => 'tests/Fixtures/Phpstan/extended-errors.json',
        ]);

        $this->assertSame(0, $exitCode);

        $this->assertStringContainsString(
            '@property-read \Illuminate\Database\Eloquent\Relations\Pivot|null $pivot',
            $this->filesystem->get(base_path('tests/Temp/HasPivot.php'))
        );

        $this->assertStringContainsString(
            '@param mixed $rating',
            $this->filesystem->get(base_path('tests/Temp/NeedsParamDocblock.php'))
        );

        $this->assertStringContainsString(
            '@return mixed',
            $this->filesystem->get(base_path('tests/Temp/NeedsReturnDocblock.php'))
        );

        $this->assertStringContainsString(
            '@property mixed $aliases',
            $this->filesystem->get(base_path('tests/Temp/HasDynamicProperty.php'))
        );

        $this->assertStringContainsString(
            '@property \Illuminate\Support\Collection<int, mixed> $items',
            $this->filesystem->get(base_path('tests/Temp/HasCollectionProperty.php'))
        );
    }
}
