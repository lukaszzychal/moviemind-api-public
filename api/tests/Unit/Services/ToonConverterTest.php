<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\ToonConverter;
use Tests\TestCase;

class ToonConverterTest extends TestCase
{
    private ToonConverter $converter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->converter = new ToonConverter;
    }

    public function test_converts_tabular_array_to_toon_format(): void
    {
        $data = [
            ['title' => 'The Matrix', 'year' => 1999, 'director' => 'The Wachowskis'],
            ['title' => 'Inception', 'year' => 2010, 'director' => 'Christopher Nolan'],
            ['title' => 'Interstellar', 'year' => 2014, 'director' => 'Christopher Nolan'],
        ];

        $result = $this->converter->convert($data);

        $this->assertStringContainsString('[3]{title,year,director}:', $result);
        $this->assertStringContainsString('The Matrix,1999,The Wachowskis', $result);
        $this->assertStringContainsString('Inception,2010,Christopher Nolan', $result);
        $this->assertStringContainsString('Interstellar,2014,Christopher Nolan', $result);
    }

    public function test_converts_nested_structure_to_toon_format(): void
    {
        $data = [
            'title' => 'The Matrix',
            'year' => 1999,
            'genres' => ['Action', 'Sci-Fi'],
            'director' => 'The Wachowskis',
        ];

        $result = $this->converter->convert($data);

        $this->assertStringContainsString('title: The Matrix', $result);
        $this->assertStringContainsString('year: 1999', $result);
        $this->assertStringContainsString('genres[2]: Action,Sci-Fi', $result);
        $this->assertStringContainsString('director: The Wachowskis', $result);
    }

    public function test_escapes_values_with_commas(): void
    {
        $data = [
            ['title' => 'Hello, World', 'year' => 2020],
        ];

        $result = $this->converter->convert($data);

        $this->assertStringContainsString('"Hello, World"', $result);
    }

    public function test_escapes_values_with_quotes(): void
    {
        $data = [
            ['title' => 'Movie "The Best"', 'year' => 2020],
        ];

        $result = $this->converter->convert($data);

        $this->assertStringContainsString('"Movie ""The Best"""', $result);
    }

    public function test_handles_empty_array(): void
    {
        $data = [];

        $result = $this->converter->convert($data);

        $this->assertIsString($result);
    }

    public function test_handles_single_item_tabular_array(): void
    {
        $data = [
            ['title' => 'The Matrix', 'year' => 1999],
        ];

        $result = $this->converter->convert($data);

        $this->assertStringContainsString('[1]{title,year}:', $result);
        $this->assertStringContainsString('The Matrix,1999', $result);
    }

    public function test_handles_null_values(): void
    {
        $data = [
            ['title' => 'The Matrix', 'year' => null, 'director' => 'The Wachowskis'],
        ];

        $result = $this->converter->convert($data);

        $this->assertStringContainsString('The Matrix,,The Wachowskis', $result);
    }
}
