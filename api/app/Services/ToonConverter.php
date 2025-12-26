<?php

declare(strict_types=1);

namespace App\Services;

/**
 * TOON (Token-Oriented Object Notation) Converter
 *
 * Converts PHP arrays to TOON format for efficient communication with LLMs.
 * TOON can save 30-60% tokens compared to JSON for tabular data.
 *
 * @see https://dev.to/akki907/toon-vs-json-the-new-format-designed-for-ai-nk5
 */
class ToonConverter
{
    /**
     * Convert array data to TOON format.
     *
     * @param  array<int|string, mixed>  $data
     * @return string TOON formatted string
     */
    public function convert(array $data): string
    {
        // Check if it's a tabular array (array of objects with same keys)
        if ($this->isTabularArray($data)) {
            /** @var array<int, array<string, mixed>> $data */
            return $this->convertTabularArray($data);
        }

        // For nested structures use YAML-like format
        return $this->convertNested($data);
    }

    /**
     * Check if array is tabular (array of objects with same structure).
     *
     * @param  array<string, mixed>  $data
     */
    /**
     * @param  array<int|string, mixed>  $data
     */
    private function isTabularArray(array $data): bool
    {
        if (empty($data)) {
            return false;
        }

        if (! isset($data[0]) || ! is_array($data[0])) {
            return false;
        }

        /** @var array<string, mixed> $firstItem */
        $firstItem = $data[0];
        $firstKeys = array_keys($firstItem);

        // Check if all elements have the same keys
        foreach ($data as $item) {
            if (! is_array($item)) {
                return false;
            }

            $keys = array_keys($item);
            if ($keys !== $firstKeys) {
                return false;
            }

            // Check if values are primitive (not nested)
            foreach ($item as $value) {
                if (is_array($value) || is_object($value)) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Convert tabular array to TOON format.
     *
     * Example:
     * [3]{title,year,director}:
     * The Matrix,1999,The Wachowskis
     * Inception,2010,Christopher Nolan
     * Interstellar,2014,Christopher Nolan
     *
     * @param  array<int, array<string, mixed>>  $data
     */
    private function convertTabularArray(array $data): string
    {
        $count = count($data);
        $keys = array_keys($data[0]);
        $keysStr = implode(',', $keys);

        $rows = [];
        foreach ($data as $item) {
            $values = array_map(fn ($key) => $this->escapeValue($item[$key] ?? ''), $keys);
            $rows[] = implode(',', $values);
        }

        return "[{$count}]{{$keysStr}}:\n".implode("\n", $rows);
    }

    /**
     * Convert nested structure to TOON format (YAML-like).
     *
     * Example:
     * movie:
     *   title: The Matrix
     *   year: 1999
     *   genres[2]: Action,Sci-Fi
     *
     * @param  array<string, mixed>  $data
     */
    private function convertNested(array $data, int $indent = 0): string
    {
        $lines = [];
        $prefix = str_repeat('  ', $indent);

        foreach ($data as $key => $value) {
            if (is_array($value)) {
                if ($this->isNumericArray($value)) {
                    // Array of values
                    $values = array_map(fn ($v) => $this->escapeValue($v), $value);
                    $lines[] = "{$prefix}{$key}[".count($value).']: '.implode(',', $values);
                } else {
                    // Nested object
                    $lines[] = "{$prefix}{$key}:";
                    $lines[] = $this->convertNested($value, $indent + 1);
                }
            } else {
                $lines[] = "{$prefix}{$key}: ".$this->escapeValue($value);
            }
        }

        return implode("\n", $lines);
    }

    /**
     * Check if array is numeric (list) vs associative (object).
     *
     * @param  array<int|string, mixed>  $array
     */
    private function isNumericArray(array $array): bool
    {
        return array_keys($array) === range(0, count($array) - 1);
    }

    /**
     * Escape value for TOON format.
     *
     * TOON uses smart quoting - quotes only when necessary:
     * - "hello, world" → quotes required (contains comma)
     * - "padded " → quotes required (spaces at start/end)
     * - hello world → no quotes (spaces inside are OK)
     *
     * @param  mixed  $value
     */
    private function escapeValue($value): string
    {
        if ($value === null) {
            return '';
        }

        $str = (string) $value;

        // Quotes only when necessary
        if (str_contains($str, ',') || str_contains($str, '"') ||
            str_contains($str, "\n") || preg_match('/^\s|\s$/', $str)) {
            return '"'.str_replace('"', '""', $str).'"';
        }

        return $str;
    }
}
