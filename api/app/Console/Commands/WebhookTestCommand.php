<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Events\MovieGenerationRequested;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;
use Laravel\Pennant\Feature;

class WebhookTestCommand extends Command
{
    protected $signature = 'webhook:test
                            {--slug=test-movie- : Slug for the test event}';

    protected $description = 'Send a test "movie.generation.requested" webhook to configured URL (for debugging webhook.site)';

    public function handle(): int
    {
        $slug = $this->option('slug') ?: 'test-movie-'.time();

        // Ensure feature is on (often disabled in DB by default)
        Feature::activate('webhook_notifications');
        $this->info('Feature webhook_notifications: activated for this run.');

        $urls = $this->getConfiguredUrls();
        if (empty($urls)) {
            $this->error('No webhook URL configured. Set WEBHOOK_URL_MOVIE_GENERATION_COMPLETED (or REQUESTED) in .env and run: php artisan config:clear');
            $this->line('Current config:');
            foreach (['movie.generation.requested', 'movie.generation.completed', 'generation.completed'] as $key) {
                $arr = Config::get("webhooks.outgoing_urls.{$key}", []);
                $this->line("  {$key}: ".(is_array($arr) ? implode(', ', $arr) : 'n/a'));
            }

            return self::FAILURE;
        }

        $this->info('URL(s) that will receive the webhook: '.implode(', ', $urls));

        $jobId = (string) \Illuminate\Support\Str::uuid();
        event(new MovieGenerationRequested(
            slug: $slug,
            jobId: $jobId,
            locale: 'en-US',
            contextTag: 'modern'
        ));

        $this->info("Dispatched MovieGenerationRequested (job_id={$jobId}). Check your webhook inbox (e.g. webhook.site).");

        return self::SUCCESS;
    }

    /**
     * @return array<int, string>
     */
    private function getConfiguredUrls(): array
    {
        $outgoing = Config::get('webhooks.outgoing_urls', []);
        $fromRequested = $outgoing['movie.generation.requested'] ?? [];
        $fromCompleted = $outgoing['movie.generation.completed'] ?? [];
        $fromGeneric = $outgoing['generation.completed'] ?? [];
        $merged = array_filter(array_merge(
            is_array($fromRequested) ? $fromRequested : [],
            is_array($fromCompleted) ? $fromCompleted : [],
            is_array($fromGeneric) ? $fromGeneric : []
        ));

        return array_values(array_unique($merged));
    }
}
