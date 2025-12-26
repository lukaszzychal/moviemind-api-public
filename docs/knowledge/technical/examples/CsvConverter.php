<?php

declare(strict_types=1);

namespace App\Services;

/**
 * CSV Converter
 *
 * Converts PHP arrays to CSV format.
 *
 * ⚠️ WARNING: CSV is NOT recommended for AI communication due to context loss issues.
 * Use TOON or JSON instead for LLM prompts.
 *
 * CSV should only be used for:
 * - Export to Excel/Google Sheets
 * - Import from external sources (in CSV format)
 * - Very simple tabular data (<10 rows)
 *
 * @see TOON_VS_JSON_VS_CSV_ANALYSIS.md for details on CSV problems
 */
class CsvConverter
{
    /**
     * Convert array data to CSV format.
     *
     * @param  array<string, mixed>  $data
     * @param  string  $key  Key containing array of rows
     * @return string CSV formatted string
     * @throws \InvalidArgumentException If data structure is invalid
     */
    public function convert(array $data, string $key = 'data'): string
    {
        if (!isset($data[$key]) || !is_array($data[$key])) {
            throw new \InvalidArgumentException("Data must contain array under key '{$key}'");
        }
        
        $rows = $data[$key];
        if (empty($rows)) {
            return '';
        }
        
        // Get headers from first row
        $headers = array_keys($rows[0]);
        
        // Build CSV
        $csv = [];
        $csv[] = implode(',', $headers); // Header row
        
        foreach ($rows as $row) {
            $values = array_map(fn($key) => $this->escapeCsvValue($row[$key] ?? ''), $headers);
            $csv[] = implode(',', $values);
        }
        
        return implode("\n", $csv);
    }
    
    /**
     * Escape value for CSV format.
     *
     * CSV escaping rules:
     * - Values containing commas, quotes, or newlines must be quoted
     * - Quotes inside quoted values must be doubled
     *
     * @param  mixed  $value
     * @return string
     */
    private function escapeCsvValue($value): string
    {
        if ($value === null) {
            return '';
        }
        
        $str = (string) $value;
        
        // Escape if contains comma, quote, or newline
        if (str_contains($str, ',') || str_contains($str, '"') || str_contains($str, "\n")) {
            return '"' . str_replace('"', '""', $str) . '"';
        }
        
        return $str;
    }
}

