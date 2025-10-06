<?php
namespace Siagraph\Utils;

class Formatter
{
    public static function convertScientificToInteger($value): int
    {
        if (strpos((string) $value, 'e') !== false || strpos((string) $value, 'E') !== false) {
            return (int) number_format($value, 0, '', '');
        }
        return (int) $value;
    }

    public static function formatBytes($bytes): string
    {
        $isNegative = $bytes < 0;
        $bytes = abs($bytes);
        if ($bytes === 0) {
            return '0 Bytes';
        }
        $units = ['Bytes', 'KB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB'];
        $unitIndex = 0;
        while ($bytes >= 1000 && $unitIndex < count($units) - 1) {
            $bytes /= 1000;
            $unitIndex++;
        }
        $formatted = Locale::decimal($bytes, 3) . ' ' . $units[$unitIndex];
        return $isNegative ? "-$formatted" : $formatted;
    }

    /**
     * Ensure a value is rendered without scientific notation and
     * prepend a plus sign when non-negative.
     * Accepts mixed input to avoid PHP float->string E-notation on small values.
     */
    public static function prependPlusIfNeeded($value): string
    {
        // Normalize numeric-like inputs to a non-scientific decimal string
        $toDecimalString = function ($num, int $precision = 16): string {
            // If it's already a plain decimal string, keep it
            if (is_string($num) && preg_match('/^[-+]?\d+(?:\.\d+)?$/', $num)) {
                return $num;
            }
            if (is_numeric($num)) {
                $s = sprintf('%.' . $precision . 'F', (float) $num);
                $s = rtrim(rtrim($s, '0'), '.');
                return ($s === '' || $s === '-') ? '0' : $s;
            }
            // Fallback to string cast for any non-numeric values
            return (string) $num;
        };

        $normalized = $toDecimalString($value);
        if ($normalized === '') {
            $normalized = '0';
        }
        return ($normalized[0] === '-') ? $normalized : '+' . $normalized;
    }

    public static function calculateAverageExcludingOutliers(array $values)
    {
        if (count($values) === 0) {
            return null;
        }
        sort($values);
        $count = count($values);
        $q1 = $values[floor(($count - 1) * 0.25)];
        $q3 = $values[floor(($count - 1) * 0.75)];
        $iqr = $q3 - $q1;
        $lower = $q1 - 1.5 * $iqr;
        $upper = $q3 + 1.5 * $iqr;
        $filtered = array_filter($values, function ($v) use ($lower, $upper) {
            return $v >= $lower && $v <= $upper;
        });
        if (count($filtered) > 0) {
            return array_sum($filtered) / count($filtered);
        }
        return null;
    }

    public static function formatSiacoins(float $sc): string
    {
        if ($sc == 0.0) {
            return '0 SC';
        }
        $units = [
            ['value' => 1e12, 'symbol' => 'TS'],
            ['value' => 1e9, 'symbol' => 'GS'],
            ['value' => 1e6, 'symbol' => 'MS'],
            ['value' => 1e3, 'symbol' => 'KS'],
            ['value' => 1, 'symbol' => 'SC'],
            ['value' => 1e-3, 'symbol' => 'mS'],
            ['value' => 1e-6, 'symbol' => 'μS'],
            ['value' => 1e-9, 'symbol' => 'nS'],
            ['value' => 1e-12, 'symbol' => 'pS'],
            ['value' => 1e-24, 'symbol' => 'H'],
        ];
        $abs = abs($sc);
        foreach ($units as $unit) {
            if ($abs >= $unit['value']) {
                return Locale::decimal($sc / $unit['value'], 2) . ' ' . $unit['symbol'];
            }
        }
        return $sc . ' SC';
    }
}
