<?php

declare(strict_types=1);

namespace App\Services;

/**
 * TOON Parser
 *
 * Parses TOON format back to PHP arrays.
 * Used for parsing AI responses in TOON format (if AI returns TOON).
 *
 * ⚠️ NOTE: Currently MovieMind API uses JSON for AI responses.
 * This parser is provided for completeness and future use.
 */
class ToonParser
{
    /**
     * Parse TOON format string to PHP array.
     *
     * @param  string  $toon
     * @return array<string, mixed>
     */
    public function parse(string $toon): array
    {
        $lines = explode("\n", trim($toon));
        $result = [];
        
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) {
                continue;
            }
            
            // Check for tabular array: [N]{keys}:
            if (preg_match('/^\[(\d+)\]\{([^}]+)\}:\s*$/', $line, $matches)) {
                $count = (int) $matches[1];
                $keys = array_map('trim', explode(',', $matches[2]));
                // Tabular arrays are handled in next lines
                continue;
            }
            
            // Check for array declaration: key[N]: values
            if (preg_match('/^(\w+)\[(\d+)\]:\s*(.+)$/', $line, $matches)) {
                $key = $matches[1];
                $count = (int) $matches[2];
                $values = array_map('trim', explode(',', $matches[3]));
                $result[$key] = $values;
                continue;
            }
            
            // Check for key-value pair: key: value
            if (preg_match('/^(\w+):\s*(.+)$/', $line, $matches)) {
                $key = $matches[1];
                $value = $this->unescapeValue($matches[2]);
                $result[$key] = $value;
            }
        }
        
        return $result;
    }
    
    /**
     * Unescape TOON value (remove quotes if present).
     *
     * @param  string  $value
     * @return string
     */
    private function unescapeValue(string $value): string
    {
        $value = trim($value);
        
        // Remove quotes if present
        if (str_starts_with($value, '"') && str_ends_with($value, '"')) {
            $value = substr($value, 1, -1);
            $value = str_replace('""', '"', $value);
        }
        
        return $value;
    }
}

