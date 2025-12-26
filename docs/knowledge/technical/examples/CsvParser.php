<?php

declare(strict_types=1);

namespace App\Services;

/**
 * CSV Parser
 *
 * Parses CSV format back to PHP arrays.
 * Used for parsing CSV data from external sources or AI responses.
 *
 * ⚠️ WARNING: CSV parsing can be error-prone, especially with:
 * - Values containing commas/quotes
 * - Inconsistent formatting
 * - Missing headers
 *
 * Consider using a library like league/csv for production use.
 */
class CsvParser
{
    /**
     * Parse CSV format string to PHP array.
     *
     * @param  string  $csv
     * @return array<int, array<string, mixed>>
     */
    public function parse(string $csv): array
    {
        $lines = explode("\n", trim($csv));
        if (empty($lines)) {
            return [];
        }
        
        // First line is headers
        $headers = $this->parseCsvLine($lines[0]);
        
        // Parse data rows
        $result = [];
        for ($i = 1; $i < count($lines); $i++) {
            $values = $this->parseCsvLine($lines[$i]);
            if (count($values) !== count($headers)) {
                // Skip malformed rows
                continue;
            }
            
            $result[] = array_combine($headers, $values);
        }
        
        return $result;
    }
    
    /**
     * Parse a single CSV line, handling quoted values.
     *
     * @param  string  $line
     * @return array<int, string>
     */
    private function parseCsvLine(string $line): array
    {
        $result = [];
        $current = '';
        $inQuotes = false;
        $length = strlen($line);
        
        for ($i = 0; $i < $length; $i++) {
            $char = $line[$i];
            
            if ($char === '"') {
                if ($inQuotes && $i + 1 < $length && $line[$i + 1] === '"') {
                    // Escaped quote
                    $current .= '"';
                    $i++; // Skip next quote
                } else {
                    // Toggle quote state
                    $inQuotes = !$inQuotes;
                }
            } elseif ($char === ',' && !$inQuotes) {
                // Field separator
                $result[] = $current;
                $current = '';
            } else {
                $current .= $char;
            }
        }
        
        // Add last field
        $result[] = $current;
        
        return $result;
    }
}

