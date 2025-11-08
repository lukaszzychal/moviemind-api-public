<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Support\PhpstanFixer\AutoFixMode;
use App\Support\PhpstanFixer\AutoFixService;
use App\Support\PhpstanFixer\PhpstanLogParser;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

final class PhpstanAutoFixCommand extends Command
{
    protected $signature = 'phpstan:auto-fix 
        {--mode=suggest : choose between suggest or apply} 
        {--input= : optional path to existing PHPStan JSON log}';

    protected $description = 'Analyse PHPStan output and suggest or apply automated fixes.';

    public function __construct(
        private readonly PhpstanLogParser $parser,
        private readonly AutoFixService $service,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $mode = AutoFixMode::fromString($this->option('mode'));

        try {
            $rawOutput = $this->option('input')
                ? $this->readInputFile($this->option('input'))
                : $this->runPhpstan();
        } catch (\InvalidArgumentException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $issues = $this->parser->parse($rawOutput, base_path());

        if (empty($issues)) {
            $this->info('PHPStan reported no actionable issues. 🎉');

            return self::SUCCESS;
        }

        $suggestions = Collection::make(
            $this->service->process($issues, $mode)
        );

        if ($suggestions->isEmpty()) {
            $this->info('No automated fixes were applicable for the reported issues.');

            return self::SUCCESS;
        }

        $this->table(
            ['File', 'Summary', 'Applied'],
            $suggestions->map(fn ($suggestion) => [
                $this->shortenPath($suggestion->filePath),
                $suggestion->summary,
                $suggestion->applied ? '✅' : '👀',
            ]),
        );

        if ($mode === AutoFixMode::SUGGEST) {
            $this->line(PHP_EOL.'Run with --mode=apply to write these changes.');
        }

        return self::SUCCESS;
    }

    private function readInputFile(string $path): string
    {
        $realPath = $this->isAbsolutePath($path) ? $path : base_path($path);

        if (! file_exists($realPath)) {
            throw new \InvalidArgumentException("Input file [{$realPath}] does not exist.");
        }

        return file_get_contents($realPath);
    }

    private function runPhpstan(): string
    {
        $process = Process::fromShellCommandline(
            'vendor/bin/phpstan analyse --error-format=json --no-progress',
            base_path()
        );

        $process->run();

        if (! $process->isSuccessful() && $process->getExitCode() === 1) {
            // Exit code 1 is expected when PHPStan finds errors.
            return $process->getOutput();
        }

        if (! $process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }

        return $process->getOutput();
    }

    private function shortenPath(string $path): string
    {
        return str_replace(base_path().DIRECTORY_SEPARATOR, '', $path);
    }

    private function isAbsolutePath(string $path): bool
    {
        return str_starts_with($path, DIRECTORY_SEPARATOR)
            || (strlen($path) > 1 && ctype_alpha($path[0]) && $path[1] === ':');
    }
}
