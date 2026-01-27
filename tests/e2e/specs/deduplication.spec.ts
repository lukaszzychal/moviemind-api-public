import { test, expect } from '@playwright/test';
import { execSync } from 'child_process';

test.describe('Smart Deduplication Strategy', () => {

    // Helper to execute PHP code via Tinker
    const runPhp = (code: string) => {
        // Minify code to single line to avoid issues with standard input buffering or newlines
        const minified = code.replace(/\n/g, ' ').replace(/\s+/g, ' ').trim();
        // Escape quotes for bash argument
        // We wrap the whole php code in single quotes for the php -r or tinker execution
        // Actually, feeding into stdin is safest
        try {
            const cmd = `docker compose exec -T php php artisan tinker`;
            // Pass code via stdin
            return execSync(cmd, {
                input: code,
                encoding: 'utf-8'
            });
        } catch (e: any) {
            console.error('PHP Execution Failed:', e.stdout?.toString(), e.stderr?.toString());
            throw e;
        }
    };

    const parsePhpOutput = (output: string) => {
        const match = output.match(/\[\[JSON_START\]\](.*?)\[\[JSON_END\]\]/s);
        if (!match) {
            throw new Error('Could not find JSON output in: ' + output);
        }
        return JSON.parse(match[1]);
    };

    test('Person: should deduplicate by Name and Birth Date (Heuristic)', async () => {
        const phpCode = `
        try {
            // Disable guards
            \\Laravel\\Pennant\\Feature::deactivate('hallucination_guard');

            // Setup
            App\\Models\\Person::where('name', 'Keanu Dedup Test')->delete();

            // Seed Legacy 'Keanu' (No TMDb ID)
            $legacy = App\\Models\\Person::create([
                'name' => 'Keanu Dedup Test',
                'slug' => 'keanu-legacy-dedup-record', // CHANGED: To avoid prefix match with 'keanu-dedup-test'
                'birth_date' => '1964-09-02',
                'tmdb_id' => null
            ]);

            // Mock Client
            $mock = new class implements \\App\\Services\\OpenAiClientInterface {
                public function generatePerson(string $slug, ?array $tmdbData = null): array {
                    return [
                        'success' => true,
                        'name' => 'Keanu Dedup Test',
                        'birth_date' => '1964-09-02',
                        'birthplace' => 'Beirut, Lebanon',
                        'biography' => 'A test biography.',
                        'tmdb_id' => 12345,
                        'model' => 'mock-gpt'
                    ];
                }
                public function generateMovie(string $slug, ?array $tmdbData = null): array { return []; }
                public function generateMovieDescription(string $title, int $releaseYear, string $director, string $contextTag, string $locale, ?array $tmdbData = null): array { return []; }
                public function generateTvSeries(string $slug, ?array $tmdbData = null): array { return []; }
                public function generateTvShow(string $slug, ?array $tmdbData = null): array { return []; }
                public function health(): array { return []; }
            };

            // Run Job
            $job = new \\App\\Jobs\\RealGeneratePersonJob(
                'keanu-dedup-test',
                'test-job-person-1',
                null, null, null, null,
                ['id' => 12345] 
            );

            // Handle
            $job->handle($mock);

            // Verify
            $people = App\\Models\\Person::where('name', 'Keanu Dedup Test')->get();
            $person = $people->first();
            
            // Split delimiters to avoid matching source code in echo
            echo "[[JSON" . "_START]]" . json_encode([
                'count' => $people->count(),
                'person' => $person,
                'original_id' => $legacy->id,
                'is_same_id' => $person->id === $legacy->id,
                'has_tmdb_id' => !empty($person->tmdb_id),
                'debug_person_attributes' => $person->toArray()
            ]) . "[[JSON" . "_END]]";
        } catch (\\Exception $e) {
            echo "[[JSON" . "_START]]" . json_encode(['error' => $e->getMessage()]) . "[[JSON" . "_END]]";
        }
    `;

        const result = runPhp(phpCode);
        const data = parsePhpOutput(result);

        expect(data.error).toBeUndefined();
        expect(data.count).toBe(1);
        expect(data.is_same_id).toBe(true);
        expect(data.has_tmdb_id).toBe(true);
    });


    test('Movie: should deduplicate by Title and Release Year', async () => {
        const phpCode = `
try {
    \\Laravel\\Pennant\\Feature:: deactivate('hallucination_guard');

    // Setup
    App\\Models\\Movie:: where('title', 'Matrix Dedup Test') -> delete ();

    // Seed Legacy Movie
    $legacy = App\\Models\\Movie:: create([
        'title' => 'Matrix Dedup Test',
        'slug' => 'matrix-dedup-legacy',
        'release_year' => 1999,
        'release_date' => '1999-03-31',
        'tmdb_id' => null
    ]);

    // Mock Client
    $mock = new class implements \\App\\Services\\OpenAiClientInterface {
        public function generateMovie(string $slug, ?array $tmdbData = null): array {
            return [
                'success' => true,
                'title' => 'Matrix Dedup Test',
                'release_year' => 1999,
                'director' => 'Wachowskis',
                'description' => 'A sci-fi classic that redefined the genre with bullet time and philosophical depth. This description is long enough to pass validation.',
                'genres' => ['Sci-Fi'],
                'tmdb_id' => 99999,
                'model' => 'mock-gpt'
            ];
        }
        // Implement required methods
        public function generateMovieDescription(string $title, int $releaseYear, string $director, string $contextTag, string $locale, ?array $tmdbData = null): array { return []; }
        public function generatePerson(string $slug, ?array $tmdbData = null): array { return []; }
        public function generateTvSeries(string $slug, ?array $tmdbData = null): array { return []; }
        public function generateTvShow(string $slug, ?array $tmdbData = null): array { return []; }
        public function health(): array { return []; }
    };

    // Run Job
    $job = new \\App\\Jobs\\RealGenerateMovieJob(
        'matrix-dedup-test',
        'test-job-movie-1',
        null, null, null, null,
        ['id' => 99999]
    );

    $job -> handle($mock);

    // Verify
    $movies = App\\Models\\Movie:: where('title', 'Matrix Dedup Test') -> get();
    $movie = $movies -> first();
            
            echo "[[JSON". "_START]]".json_encode([
        'count' => $movies -> count(),
        'is_same_id' => $movie -> id === $legacy -> id,
        'has_tmdb_id' => !empty($movie -> tmdb_id)
    ]). "[[JSON". "_END]]";
} catch (\\Exception $e) {
            echo "[[JSON". "_START]]".json_encode(['error' => $e -> getMessage()]). "[[JSON". "_END]]";
}
`;

        const result = runPhp(phpCode);
        const data = parsePhpOutput(result);

        expect(data.error).toBeUndefined();
        expect(data.count).toBe(1);
        expect(data.is_same_id).toBe(true);
        expect(data.has_tmdb_id).toBe(true);
    });

    test('TV Series: should deduplicate by Title and First Air Year', async () => {
        // Use proper quoting to avoid syntax errors with single quotes inside the description
        const phpCode = `
try {
    \\Laravel\\Pennant\\Feature:: deactivate('hallucination_guard');

    // Setup
    App\\Models\\TvSeries:: where('title', 'Breaking Dedup') -> delete ();

    // Seed Legacy
    $legacy = App\\Models\\TvSeries:: create([
        'title' => 'Breaking Dedup',
        'slug' => 'breaking-dedup-legacy',
        'first_air_date' => '2008-01-20',
        'tmdb_id' => null
    ]);

    // Mock Client
    $mock = new class implements \\App\\Services\\OpenAiClientInterface {
        public function generateTvSeries(string $slug, ?array $tmdbData = null): array {
            return [
                'success' => true,
                'title' => 'Breaking Dedup',
                'first_air_year' => 2008,
                'description' => "A high school chemistry teacher diagnosed with inoperable lung cancer turns to manufacturing and selling methamphetamine in order to secure his family's future.",
                'genres' => ['Drama'],
                'tmdb_id' => 88888,
                'model' => 'mock-gpt'
            ];
        }
        public function generateTvShow(string $slug, ?array $tmdbData = null): array { return []; }
        public function generateMovie(string $slug, ?array $tmdbData = null): array { return []; }
        public function generateMovieDescription(string $title, int $releaseYear, string $director, string $contextTag, string $locale, ?array $tmdbData = null): array { return []; }
        public function generatePerson(string $slug, ?array $tmdbData = null): array { return []; }
        public function health(): array { return []; }
    };

    // Run Job
    $job = new \\App\\Jobs\\RealGenerateTvSeriesJob(
        'breaking-dedup-test',
        'test-job-series-1',
        null, null, null, null,
        ['id' => 88888]
    );

    $job -> handle($mock);

    // Verify
    $shows = App\\Models\\TvSeries:: where('title', 'Breaking Dedup') -> get();
    $show = $shows -> first();
            
            echo "[[JSON". "_START]]".json_encode([
        'count' => $shows -> count(),
        'is_same_id' => $show -> id === $legacy -> id,
        'has_tmdb_id' => !empty($show -> tmdb_id)
    ]). "[[JSON". "_END]]";
} catch (\\Exception $e) {
            echo "[[JSON". "_START]]".json_encode(['error' => $e -> getMessage()]). "[[JSON". "_END]]";
}
`;

        const result = runPhp(phpCode);
        const data = parsePhpOutput(result);

        expect(data.error).toBeUndefined();
        expect(data.count).toBe(1);
        expect(data.is_same_id).toBe(true);
        expect(data.has_tmdb_id).toBe(true);
    });
});
