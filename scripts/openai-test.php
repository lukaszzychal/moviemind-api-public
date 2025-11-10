<?php

declare(strict_types=1);

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

$autoloadPaths = [
    __DIR__ . '/../vendor/autoload.php',
    __DIR__ . '/../api/vendor/autoload.php',
];

$autoloadLoaded = false;

foreach ($autoloadPaths as $autoloadPath) {
    if (is_file($autoloadPath)) {
        require $autoloadPath;
        $autoloadLoaded = true;
        break;
    }
}

if ($autoloadLoaded === false) {
    fwrite(STDERR, "Nie znaleziono pliku autoload.php. Uruchom `composer install` w katalogu głównym projektu lub w `api/`.\n");
    exit(1);
}

$envPaths = [
    __DIR__ . '/../.env',
    __DIR__ . '/../env/local.env',
    __DIR__ . '/../api/.env',
];

foreach ($envPaths as $envPath) {
    loadEnvFile($envPath);
}

$apiKey = getenv('OPENAI_API_KEY');
$maskedKey = $apiKey !== false && $apiKey !== ''
    ? sprintf('%s***%s', substr($apiKey, 0, 4), substr($apiKey, -4))
    : '(brak)';
echo "Api Key: {$maskedKey}\n";

if ($apiKey === false || $apiKey === '') {
    fwrite(STDERR, "Brak klucza API. Ustaw OPENAI_API_KEY w środowisku.\n");
    exit(1);
}

$client = new Client([
    'base_uri' => 'https://api.openai.com/v1/',
    'headers' => [
        'Authorization' => 'Bearer ' . $apiKey,
        'Content-Type'  => 'application/json',
    ],
    'timeout' => 15,
]);

$payload = [
    'model' => 'gpt-4o-mini',
    'input' => [
        [
            'role' => 'user',
            'content' => [
                [
                    'type' => 'input_text',
                    'text' => 'Napisz jedno zdanie o filmach science fiction.',
                ],
            ],
        ],
    ],
];

try {
    $response = $client->post('responses', ['json' => $payload]);
    $data = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);

    $output = $data['output'][0]['content'][0]['text'] ?? 'Brak odpowiedzi w spodziewanym formacie.';
    echo "Odpowiedź modelu:\n{$output}\n";
} catch (GuzzleException $e) {
    fwrite(STDERR, "Błąd połączenia z OpenAI: {$e->getMessage()}\n");
    exit(1);
} catch (JsonException $e) {
    fwrite(STDERR, "Problem z parsowaniem JSON: {$e->getMessage()}\n");
    exit(1);
}

function loadEnvFile(string $path): void
{
    if (!is_file($path)) {
        return;
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

    if ($lines === false) {
        return;
    }

    foreach ($lines as $line) {
        $line = trim($line);

        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }

        if (!str_contains($line, '=')) {
            continue;
        }

        [$name, $value] = explode('=', $line, 2);
        $name = trim($name);
        $value = trim($value);

        if ($name === '') {
            continue;
        }

        if ($value !== '') {
            $firstChar = $value[0];
            $lastChar = $value[strlen($value) - 1];

            if (($firstChar === '"' && $lastChar === '"') || ($firstChar === "'" && $lastChar === "'")) {
                $value = substr($value, 1, -1);
            }
        }

        putenv("{$name}={$value}");
        $_ENV[$name] = $value;
        $_SERVER[$name] = $value;
    }
}